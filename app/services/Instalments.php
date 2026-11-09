<?php
declare(strict_types=1);

/** Fee payment plans: a bill split into dated instalments; money paid covers the earliest instalments first. */
final class Instalments
{
    /** Instalments of a bill with how much of each is covered by what has been paid. */
    public static function schedule(array $inv): array
    {
        try {
            $rows = db()->all('SELECT * FROM invoice_instalments WHERE invoice_id = ? ORDER BY seq', [$inv['id']]);
        } catch (PDOException $e) {
            return []; // migration 019 not applied yet
        }
        $left = (float) $inv['paid'];
        foreach ($rows as &$r) {
            $cov = min((float) $r['amount'], max(0.0, $left));
            $left -= $cov;
            $r['covered'] = round($cov, 2);
            $r['due_left'] = round((float) $r['amount'] - $cov, 2);
            $r['state'] = $r['due_left'] <= 0.004 ? 'paid' : ($r['due_date'] < date('Y-m-d') ? 'overdue' : ($cov > 0 ? 'part' : 'due'));
        }
        return $rows;
    }

    /**
     * Save a plan. $parts = [[due_date, amount], …]; amounts must add up to the bill balance.
     * Returns an error message or null.
     */
    public static function save(array $inv, array $parts): ?string
    {
        $bal = round((float) $inv['total'] - (float) $inv['paid'], 2);
        if (!in_array($inv['status'], ['unpaid', 'part'], true) || $bal <= 0) {
            return 'Only a bill with money still owed can have a payment plan.';
        }
        if (count($parts) < 2 || count($parts) > 6) {
            return 'A payment plan has 2 to 6 instalments.';
        }
        $prev = '';
        $sum = 0.0;
        foreach ($parts as [$date, $amt]) {
            if (!Validator::isDate($date) || $date < date('Y-m-d', strtotime('-1 day')) || ($prev !== '' && $date <= $prev)) {
                return 'Each instalment needs a date, in order, not in the past.';
            }
            if ($amt <= 0) {
                return 'Each instalment needs an amount above 0.';
            }
            $prev = $date;
            $sum += $amt;
        }
        if (abs(round($sum, 2) - $bal) > 0.009) {
            return 'The instalments add up to ' . Fees::money($sum) . ' but ' . Fees::money($bal) . ' is owed.';
        }
        // Instalments start after what is already paid: shift by the amount paid so far
        db()->transaction(function (Database $d) use ($inv, $parts) {
            $d->run('DELETE FROM invoice_instalments WHERE invoice_id = ?', [$inv['id']]);
            $paid = (float) $inv['paid'];
            $seq = 0;
            if ($paid > 0) {
                $d->insert('invoice_instalments', ['invoice_id' => $inv['id'], 'seq' => ++$seq, 'due_date' => date('Y-m-d'), 'amount' => round($paid, 2), 'reminded_at' => date('Y-m-d H:i:s')]);
            }
            foreach ($parts as [$date, $amt]) {
                $d->insert('invoice_instalments', ['invoice_id' => $inv['id'], 'seq' => ++$seq, 'due_date' => $date, 'amount' => round($amt, 2)]);
            }
        });
        return null;
    }

    /** Daily: SMS parents 3 days before each instalment date (once per instalment) while it is unpaid. */
    public static function reminders(): int
    {
        try {
            if (setting('instalment_last_check') === date('Y-m-d')) {
                return 0;
            }
            setting_set('instalment_last_check', date('Y-m-d'));
            $rows = db()->all(
                "SELECT ii.*, i.id AS inv_id FROM invoice_instalments ii JOIN invoices i ON i.id = ii.invoice_id
                 WHERE ii.reminded_at IS NULL AND i.status IN ('unpaid','part') AND ii.due_date <= CURDATE() + INTERVAL 3 DAY"
            );
        } catch (PDOException $e) {
            return 0;
        }
        $n = 0;
        foreach ($rows as $r) {
            $inv = db()->one('SELECT i.*, p.first_name, p.id AS pid FROM invoices i JOIN pupils p ON p.id = i.pupil_id WHERE i.id = ?', [$r['inv_id']]);
            $row = null;
            foreach (self::schedule($inv) as $s) {
                if ((int) $s['id'] === (int) $r['id']) {
                    $row = $s;
                }
            }
            db()->update('invoice_instalments', ['reminded_at' => date('Y-m-d H:i:s')], 'id = ?', [$r['id']]);
            if (!$row || $row['due_left'] <= 0) {
                continue;
            }
            $g = Fees::guardian((int) $inv['pid']);
            if ($g && $g['phone'] && Notifier::smsTemplate('fee_instalment', $g['phone'], ['guardian' => $g['name'], 'pupil' => $inv['first_name'], 'no' => $r['seq'], 'title' => $inv['title'],
                'amount' => number_format($row['due_left'], 2), 'due' => date('j M', strtotime($r['due_date'])), 'link' => Fees::payUrl($inv)], ['name' => $g['name'], 'entity' => 'invoice_instalments', 'entity_id' => $r['id']])) {
                $n++;
            }
        }
        return $n;
    }
}
