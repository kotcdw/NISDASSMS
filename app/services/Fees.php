<?php
declare(strict_types=1);

/** Fees engine: bills, discounts, payments, balances, receipts. Amounts are in Ghana cedis (GH₵). */
final class Fees
{
    public const METHODS = ['cash' => 'Cash', 'momo' => 'Mobile Money (direct)', 'bank' => 'Bank deposit', 'cheque' => 'Cheque', 'online' => 'Online (MoMo / card)'];
    /** Labels for every way a bill can be settled (METHODS are the ways money can be received). */
    public const PAID_BY = self::METHODS + ['credit' => 'Family credit'];
    public const STATUS = ['unpaid' => ['Unpaid', 'bad'], 'part' => ['Part paid', 'warn'], 'paid' => ['Paid', 'good'], 'cancelled' => ['Cancelled', 'neutral']];

    public static function money($v): string
    {
        return 'GH₵ ' . number_format((float) $v, 2);
    }

    /** Next number from a named sequence, e.g. INV-2026-00001 (call inside a transaction). */
    public static function nextNumber(Database $d, string $prefix): string
    {
        $name = strtolower($prefix) . '_' . date('Y');
        $d->run('INSERT IGNORE INTO number_sequences (name, next_value) VALUES (?, 1)', [$name]);
        $n = (int) $d->value('SELECT next_value FROM number_sequences WHERE name = ? FOR UPDATE', [$name]);
        $d->run('UPDATE number_sequences SET next_value = next_value + 1 WHERE name = ?', [$name]);
        return $prefix . '-' . date('Y') . '-' . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }

    /** Recompute totals, amount paid and status of an invoice. */
    public static function recalc(int $invoiceId, ?Database $d = null): void
    {
        $d = $d ?: db();
        $sum = $d->one('SELECT COALESCE(SUM(amount), 0) AS sub, COALESCE(SUM(discount), 0) AS disc FROM invoice_lines WHERE invoice_id = ?', [$invoiceId]);
        $paid = (float) $d->value("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = ? AND status = 'completed'", [$invoiceId]);
        if (self::hasCreditOut($d)) { // money moved from this bill to family credit no longer counts as paid on it
            $paid = round($paid - (float) $d->value('SELECT credit_out FROM invoices WHERE id = ?', [$invoiceId]), 2);
        }
        $inv = $d->one('SELECT status FROM invoices WHERE id = ?', [$invoiceId]);
        $total = round((float) $sum['sub'] - (float) $sum['disc'], 2);
        $status = $inv['status'] === 'cancelled' ? 'cancelled' : ($paid <= 0 ? ($total <= 0 ? 'paid' : 'unpaid') : ($paid + 0.004 >= $total ? 'paid' : 'part'));
        $d->update('invoices', ['subtotal' => $sum['sub'], 'discount' => $sum['disc'], 'total' => $total, 'paid' => $paid, 'status' => $status], 'id = ?', [$invoiceId]);
    }

    /** Is the database updated for family credit (migration 030)? */
    public static function hasCreditOut(?Database $d = null): bool
    {
        static $has = null;
        if ($has === null) {
            $has = (bool) ($d ?: db())->value("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'invoices' AND column_name = 'credit_out'");
        }
        return $has;
    }

    public static function balance(array $inv): float
    {
        return $inv['status'] === 'cancelled' ? 0.0 : round((float) $inv['total'] - (float) $inv['paid'], 2);
    }

    /** Still owed on this child's earlier bills (shown on a new bill as "Balance brought forward"). */
    public static function arrearsBefore(array $inv): float
    {
        return round((float) db()->value(
            "SELECT COALESCE(SUM(total - paid), 0) FROM invoices WHERE pupil_id = ? AND id <> ? AND status IN ('unpaid','part')
               AND (issue_date < ? OR (issue_date = ? AND id < ?))",
            [$inv['pupil_id'], $inv['id'], $inv['issue_date'], $inv['issue_date'], $inv['id']]
        ), 2);
    }

    public static function pupilBalance(int $pupilId): float
    {
        return (float) db()->value("SELECT COALESCE(SUM(total - paid), 0) FROM invoices WHERE pupil_id = ? AND status <> 'cancelled'", [$pupilId]);
    }

    /** Balance on bills for terms up to (and including) the given term, plus other bills already issued — used on report cards. */
    public static function arrearsAsOf(int $pupilId, array $term): float
    {
        return (float) db()->value(
            "SELECT COALESCE(SUM(i.total - i.paid), 0) FROM invoices i LEFT JOIN terms t ON t.id = i.term_id
             WHERE i.pupil_id = ? AND i.status <> 'cancelled'
               AND ((i.kind = 'term' AND t.start_date <= ?) OR (i.kind <> 'term' AND i.issue_date <= CURDATE()))",
            [$pupilId, $term['start_date']]
        );
    }

    /** Best discount (percent) for a pupil: personal discounts + sibling discount if they are not the eldest child in school. */
    public static function discountFor(int $pupilId, bool $applySibling): ?array
    {
        $best = null;
        foreach (db()->all('SELECT d.* FROM pupil_discounts pd JOIN discounts d ON d.id = pd.discount_id WHERE pd.pupil_id = ? AND d.is_active = 1', [$pupilId]) as $d) {
            $best = self::better($best, $d);
        }
        if ($applySibling) {
            $sib = db()->one('SELECT * FROM discounts WHERE is_sibling = 1 AND is_active = 1 LIMIT 1');
            if ($sib) {
                // Other active pupils sharing a guardian who were admitted earlier
                $older = (int) db()->value(
                    "SELECT COUNT(*) FROM pupil_guardian a JOIN pupil_guardian b ON b.guardian_id = a.guardian_id AND b.pupil_id <> a.pupil_id
                     JOIN pupils p ON p.id = b.pupil_id JOIN pupils me ON me.id = a.pupil_id
                     WHERE a.pupil_id = ? AND p.status = 'active' AND p.deleted_at IS NULL
                       AND (p.admitted_on < me.admitted_on OR (p.admitted_on = me.admitted_on AND p.id < me.id))",
                    [$pupilId]
                );
                if ($older > 0) {
                    $best = self::better($best, $sib);
                }
            }
        }
        return $best;
    }

    private static function better(?array $a, array $b): array
    {
        if (!$a) {
            return $b;
        }
        // Compare on a GH₵ 1,000 reference amount so % and fixed discounts can be ranked
        $val = fn($d) => $d['kind'] === 'percent' ? (float) $d['value'] * 10 : (float) $d['value'];
        return $val($b) > $val($a) ? $b : $a;
    }

    /**
     * Create the term bill for a pupil from the fee schedule. Returns the new invoice id, or null if one
     * already exists for that term or nothing is payable.
     */
    public static function createTermInvoice(Database $d, array $pupil, array $term, array $opts = []): ?int
    {
        if ($d->value("SELECT id FROM invoices WHERE pupil_id = ? AND term_id = ? AND kind = 'term' AND status <> 'cancelled'", [$pupil['id'], $term['id']])) {
            return null;
        }
        $freq = (int) $term['term_no'] === 1 ? "('termly','yearly')" : "('termly')";
        $items = $d->all(
            "SELECT f.*, fs.amount FROM fee_structures fs JOIN fee_items f ON f.id = fs.fee_item_id
             WHERE fs.level_id = ? AND f.is_active = 1 AND f.frequency IN $freq AND fs.amount > 0 ORDER BY f.sort_order",
            [$pupil['level_id']]
        );
        if (!$items) {
            return null;
        }
        $disc = self::discountFor((int) $pupil['id'], !empty($opts['sibling']));
        $id = self::insertInvoice($d, $pupil, $term, 'term', $term['name'] . ' fees ' . $term['year_name'], $opts['due_date'] ?? null);
        foreach ($items as $it) {
            $dAmt = 0.0;
            if ($disc && (int) $it['discountable']) {
                $dAmt = $disc['kind'] === 'percent' ? round((float) $it['amount'] * (float) $disc['value'] / 100, 2) : min((float) $it['amount'], (float) $disc['value']);
            }
            $d->insert('invoice_lines', [
                'invoice_id' => $id, 'fee_item_id' => $it['id'], 'description' => $it['name'], 'amount' => $it['amount'],
                'discount' => $dAmt, 'discount_note' => $dAmt > 0 ? $disc['name'] : null,
            ]);
        }
        self::recalc($id, $d);
        Family::autoApply($d, $id);
        return $id;
    }

    /** Bill the one-off admission items (called when a pupil is enrolled). */
    public static function createAdmissionInvoice(Database $d, array $pupil): ?int
    {
        $items = $d->all(
            "SELECT f.*, fs.amount FROM fee_structures fs JOIN fee_items f ON f.id = fs.fee_item_id
             WHERE fs.level_id = ? AND f.is_active = 1 AND f.frequency = 'once' AND fs.amount > 0",
            [$pupil['level_id']]
        );
        if (!$items) {
            return null;
        }
        $id = self::insertInvoice($d, $pupil, current_term(), 'admission', 'Admission fees', null);
        foreach ($items as $it) {
            $d->insert('invoice_lines', ['invoice_id' => $id, 'fee_item_id' => $it['id'], 'description' => $it['name'], 'amount' => $it['amount']]);
        }
        self::recalc($id, $d);
        Family::autoApply($d, $id);
        return $id;
    }

    public static function insertInvoice(Database $d, array $pupil, ?array $term, string $kind, string $title, ?string $due): int
    {
        $due = $due ?: date('Y-m-d', strtotime('+' . max(0, (int) setting('fee_due_days', '21')) . ' days'));
        return $d->insert('invoices', [
            'invoice_no' => self::nextNumber($d, 'INV'), 'public_key' => bin2hex(random_bytes(12)),
            'pupil_id' => $pupil['id'], 'term_id' => $term['id'] ?? null, 'class_id' => $pupil['class_id'] ?? null,
            'kind' => $kind, 'title' => mb_substr($title, 0, 150), 'issue_date' => date('Y-m-d'), 'due_date' => $due,
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * Record a payment against an invoice. For gateway payments $gatewayRef makes it idempotent.
     * Returns the payment id (existing id if the gateway reference was already recorded).
     */
    public static function recordPayment(int $invoiceId, float $amount, string $method, array $extra = []): int
    {
        return db()->transaction(function (Database $d) use ($invoiceId, $amount, $method, $extra) {
            if (!empty($extra['gateway_ref'])) {
                $existing = $d->value('SELECT id FROM payments WHERE gateway_ref = ?', [$extra['gateway_ref']]);
                if ($existing) {
                    return (int) $existing;
                }
            }
            $inv = $d->one('SELECT * FROM invoices WHERE id = ? FOR UPDATE', [$invoiceId]);
            if (!$inv || $inv['status'] === 'cancelled') {
                throw new RuntimeException('invoice_unavailable');
            }
            $id = $d->insert('payments', [
                'receipt_no' => self::nextNumber($d, 'RCT'), 'invoice_id' => $invoiceId, 'pupil_id' => $inv['pupil_id'],
                'amount' => round($amount, 2), 'method' => $method,
                'reference' => isset($extra['reference']) ? mb_substr((string) $extra['reference'], 0, 100) : null,
                'payer_name' => isset($extra['payer_name']) ? mb_substr((string) $extra['payer_name'], 0, 150) : null,
                'payer_phone' => $extra['payer_phone'] ?? null,
                'paid_on' => $extra['paid_on'] ?? date('Y-m-d'),
                'gateway' => $extra['gateway'] ?? null, 'gateway_ref' => $extra['gateway_ref'] ?? null,
                'received_by' => Auth::id(),
            ]);
            self::recalc($invoiceId, $d);
            Finance::postFeePayment($d, $id);
            return $id;
        });
    }

    /** Absolute link a parent can open to view and pay a bill. */
    public static function payUrl(array $inv): string
    {
        return absolute_url('pay/' . $inv['public_key']);
    }

    public static function guardian(int $pupilId): ?array
    {
        return db()->one('SELECT g.* FROM pupil_guardian pg JOIN guardians g ON g.id = pg.guardian_id WHERE pg.pupil_id = ? ORDER BY pg.is_primary DESC LIMIT 1', [$pupilId]);
    }

    public static function smsInvoice(int $invoiceId): bool
    {
        $inv = db()->one('SELECT i.*, p.first_name FROM invoices i JOIN pupils p ON p.id = i.pupil_id WHERE i.id = ?', [$invoiceId]);
        $g = self::guardian((int) $inv['pupil_id']);
        if (!$g || self::balance($inv) <= 0) {
            return false;
        }
        return (bool) Notifier::smsTemplate('fee_invoice', $g['phone'], [
            'guardian' => $g['name'], 'pupil' => $inv['first_name'], 'title' => $inv['title'],
            'total' => number_format(self::balance($inv), 2), 'due' => $inv['due_date'] ? date('j M', strtotime($inv['due_date'])) : 'soon',
            'link' => self::payUrl($inv),
        ], ['name' => $g['name'], 'entity' => 'invoices', 'entity_id' => $invoiceId]);
    }

    public static function smsReceipt(int $paymentId): bool
    {
        $p = db()->one('SELECT pay.*, pu.first_name FROM payments pay JOIN pupils pu ON pu.id = pay.pupil_id WHERE pay.id = ?', [$paymentId]);
        $g = self::guardian((int) $p['pupil_id']);
        if (!$g) {
            return false;
        }
        return (bool) Notifier::smsTemplate('fee_receipt', $g['phone'], [
            'guardian' => $g['name'], 'pupil' => $p['first_name'], 'amount' => number_format((float) $p['amount'], 2),
            'receipt' => $p['receipt_no'], 'balance' => number_format(self::pupilBalance((int) $p['pupil_id']), 2),
        ], ['name' => $g['name'], 'entity' => 'payments', 'entity_id' => $paymentId, 'urgent' => true]);
    }

    /** Remind every family whose total balance is at least $min. Returns number queued. */
    public static function sendReminders(float $min = 1.0, ?int $classId = null, bool $overdueOnly = false): int
    {
        $sql = "SELECT i.pupil_id, SUM(i.total - i.paid) AS bal, MAX(i.id) AS last_inv, p.first_name
                FROM invoices i JOIN pupils p ON p.id = i.pupil_id
                WHERE i.status IN ('unpaid','part') AND p.status = 'active' AND p.deleted_at IS NULL";
        $params = [];
        if ($overdueOnly) {
            $sql .= ' AND i.due_date < CURDATE()';
        }
        if ($classId) {
            $sql .= ' AND i.class_id = ?';
            $params[] = $classId;
        }
        $rows = db()->all($sql . ' GROUP BY i.pupil_id, p.first_name HAVING bal >= ?', array_merge($params, [$min]));
        $n = 0;
        $tag = (int) date('Ymd'); // one reminder per pupil per day
        foreach ($rows as $r) {
            $g = self::guardian((int) $r['pupil_id']);
            $inv = db()->one('SELECT * FROM invoices WHERE id = ?', [$r['last_inv']]);
            if ($g && Notifier::smsTemplate('fee_reminder', $g['phone'], [
                'guardian' => $g['name'], 'pupil' => $r['first_name'], 'balance' => number_format((float) $r['bal'], 2), 'link' => self::payUrl($inv),
            ], ['name' => $g['name'], 'entity' => 'fee_reminder_' . $tag, 'entity_id' => (int) $r['pupil_id']])) {
                $n++;
            }
        }
        return $n;
    }
}
