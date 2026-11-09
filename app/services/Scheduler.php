<?php
declare(strict_types=1);

/** Periodic jobs: calendar reminders + sending the SMS queue. Run by bin/cron.php every 5–15 minutes. */
final class Scheduler
{
    public static function run(): array
    {
        // Modules switched off in School setup → Modules on/off: their reminders and jobs stop
        $on = fn(string $m): bool => ModuleSwitch::isOn($m);
        $reminders = $on('calendar') ? self::eventReminders() : 0;
        $fees = $on('fees') ? self::feeReminders() + self::feeStageReminders() : 0;
        $online = self::settleOnline(); // always: online payments already made must still be recorded
        $ntc = $on('teachers') ? Payroll::ntcReminders() : 0;
        $inst = $on('fees') ? Instalments::reminders() : 0;
        $docs = $on('documents') ? Documents::reminders() : 0;
        $live = $on('live') ? LiveController::reminders() : 0;
        $shop = $on('shop') ? Shop::sync() : 0;
        $gov = $on('governance') ? Governance::reminders() : 0;
        $welfare = $on('welfare') ? Welfare::daily() : 0;
        $records = Registers::daily(); // licences & permits, staff contracts (GES records, migration 039)
        if ($on('notices')) {
            Notices::alertDue();
        }
        if ($on('crm')) {
            Crm::syncApplications();
        }
        if ($on('finance')) {
            try {
                Ledger::sync(); // keep the double-entry books up to date
            } catch (Throwable $e) {
                error_log('[NISDAS] ledger sync: ' . $e->getMessage());
            }
        }
        $sent = Notifier::process(200);
        try {
            $push = WebPush::flush(300);
        } catch (Throwable $e) {
            $push = 0;
        }
        return ['reminders' => $reminders, 'fee_reminders' => $fees, 'online_checked' => $online, 'ntc_reminders' => $ntc, 'instalment_reminders' => $inst, 'document_reminders' => $docs, 'live_reminders' => $live, 'shop_orders_updated' => $shop, 'governance_reminders' => $gov, 'welfare_followups' => $welfare, 'record_reminders' => $records, 'pushed' => $push] + $sent;
    }

    /** Queue parent reminders for calendar events marked "notify parents". */
    public static function eventReminders(): int
    {
        $events = db()->all(
            "SELECT * FROM calendar_events
             WHERE notify_parents = 1 AND reminded_at IS NULL AND audience <> 'staff'
               AND start_date >= CURDATE() AND start_date <= (CURDATE() + INTERVAL reminder_days DAY)"
        );
        $queued = 0;
        foreach ($events as $ev) {
            $audience = $ev['audience'] === 'level' ? 'level' : 'parents:all';
            $phones = Audience::phones($audience, '', $ev['level_id'] ? (int) $ev['level_id'] : null);
            $when = date('D j M', strtotime($ev['start_date'])) . ($ev['start_time'] ? ' at ' . substr($ev['start_time'], 0, 5) : '');
            $body = Notifier::render('event_reminder', [
                'event' => $ev['title'], 'event_date' => $when,
                'details' => $ev['description'] ? mb_strimwidth(preg_replace('/\s+/', ' ', $ev['description']), 0, 80, '…') : '',
            ]);
            db()->transaction(function (Database $d) use ($ev, $phones, $body, &$queued) {
                // Mark first so a crash cannot cause a second blast
                $d->update('calendar_events', ['reminded_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [$ev['id']]);
                if ($body === null || !$phones) {
                    return;
                }
                $batch = $d->insert('message_batches', [
                    'title' => 'Reminder: ' . mb_substr($ev['title'], 0, 130), 'audience' => 'calendar',
                    'audience_label' => count($phones) . ' parents', 'body' => $body, 'recipients' => count($phones),
                ]);
                foreach ($phones as $phone => $name) {
                    if (Notifier::sms($phone, $body, ['name' => $name, 'event' => 'event_reminder', 'entity' => 'calendar_events', 'entity_id' => $ev['id'], 'batch_id' => $batch])) {
                        $queued++;
                    }
                }
            });
        }
        return $queued;
    }

    /** Weekly (Monday from 09:00) SMS to parents with overdue bills, when switched on in Fee settings. */
    public static function feeReminders(?DateTime $now = null): int
    {
        $now = $now ?: new DateTime();
        if (setting('fee_auto_reminders') !== '1' || $now->format('N') !== '1' || (int) $now->format('G') < 9) {
            return 0;
        }
        $today = $now->format('Y-m-d');
        if (setting('fee_last_auto_reminder') === $today) {
            return 0;
        }
        setting_set('fee_last_auto_reminder', $today);
        return Fees::sendReminders(1.0, null, true);
    }

    /**
     * Reminder schedule for late bills (setting "fee_stage_reminders"): daily from 09:00. A bill that is 7, 14 or 30 days
     * late (setting "fee_remind_days") gets the reminder for the latest step it has reached, once. Bills on a payment
     * plan are skipped (the plan sends its own reminders).
     */
    public static function feeStageReminders(?DateTime $now = null): int
    {
        $now = $now ?: new DateTime();
        if (setting('fee_stage_reminders') !== '1' || (int) $now->format('G') < 9) {
            return 0;
        }
        try {
            db()->value('SELECT 1 FROM fee_reminders LIMIT 1');
        } catch (PDOException $e) {
            return 0;
        }
        $today = $now->format('Y-m-d');
        if (setting('fee_last_stage_reminder') === $today) {
            return 0;
        }
        setting_set('fee_last_stage_reminder', $today);
        $days = array_values(array_filter(array_map('intval', explode(',', setting('fee_remind_days', '7,14,30'))), fn($d) => $d > 0));
        sort($days);
        if (!$days) {
            return 0;
        }
        $rows = db()->all(
            "SELECT i.*, p.first_name, p.last_name, DATEDIFF(?, i.due_date) AS late,
                    (SELECT MAX(stage) FROM fee_reminders r WHERE r.invoice_id = i.id) AS done
             FROM invoices i JOIN pupils p ON p.id = i.pupil_id
             WHERE i.status IN ('unpaid','part') AND i.due_date IS NOT NULL AND i.due_date < ? AND (i.total - i.paid) >= 1
               AND p.status = 'active' AND p.deleted_at IS NULL
               AND NOT EXISTS (SELECT 1 FROM invoice_instalments x WHERE x.invoice_id = i.id)",
            [$today, $today]
        );
        $n = 0;
        $heads = [];
        foreach ($rows as $inv) {
            $stage = 0;
            foreach ($days as $k => $d) {
                if ((int) $inv['late'] >= $d) {
                    $stage = min(3, $k + 1);
                }
            }
            if ($stage === 0 || $stage <= (int) $inv['done']) {
                continue;
            }
            $g = Fees::guardian((int) $inv['pupil_id']);
            db()->run('INSERT IGNORE INTO fee_reminders (invoice_id, stage) VALUES (?, ?)', [$inv['id'], $stage]);
            if (!$g || !$g['phone']) {
                continue;
            }
            $vars = ['guardian' => $g['name'], 'pupil' => $inv['first_name'], 'balance' => number_format((float) $inv['total'] - (float) $inv['paid'], 2),
                'due' => fmt_date($inv['due_date']), 'days' => (int) $inv['late'], 'link' => Fees::payUrl($inv)];
            $meta = ['name' => $g['name'], 'entity' => 'invoices', 'entity_id' => (int) $inv['id']];
            if ($stage === 1) {
                $meta['sms_only'] = true;
            }
            if (Notifier::smsTemplate('fee_overdue_' . $stage, $g['phone'], $vars, $meta)) {
                $n++;
            }
            if ($stage === 3) {
                $heads[] = $inv['first_name'] . ' ' . $inv['last_name'] . ' (' . $inv['invoice_no'] . ', ' . Fees::money((float) $inv['total'] - (float) $inv['paid']) . ')';
            }
        }
        if ($heads) {
            Notifier::inappPermission('fees.approve', count($heads) . ' bill' . (count($heads) > 1 ? 's' : '') . ' ' . $days[min(2, count($days) - 1)] . '+ days late', implode('; ', array_slice($heads, 0, 15)) . (count($heads) > 15 ? ' …' : ''), 'fees/debtors');
        }
        return $n;
    }

    /** Safety net if a gateway callback was missed: re-check recent unconfirmed online payments. */
    public static function settleOnline(): int
    {
        if (PaymentGateway::active() === 'none') {
            return 0;
        }
        $refs = db()->all(
            "SELECT reference FROM online_payments WHERE status = 'initiated' AND gateway IN ('paystack','hubtel')
             AND (gateway_message IS NULL OR gateway_message NOT LIKE 'PAID but bill cancelled%')
             AND created_at < (NOW() - INTERVAL 5 MINUTE) AND created_at > (NOW() - INTERVAL 2 DAY) ORDER BY id LIMIT 20"
        );
        foreach ($refs as $r) {
            PaymentGateway::settle($r['reference']);
        }
        return count($refs);
    }
}
