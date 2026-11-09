<?php
declare(strict_types=1);

/**
 * Printed money documents (receipts, vouchers, credit notes …): the first print is the ORIGINAL, every later print is
 * marked COPY; each document gets a random check code for the QR code that opens the public "is this genuine?" page.
 */
final class Docs
{
    /** type => [title, label of the 2 copies] */
    public const TYPES = [
        'receipt' => ['Official receipt', ["Parent's copy", 'School copy']],
        'family' => ['Official receipt', ["Parent's copy", 'School copy']],
        'credit' => ['Credit note', ["Parent's copy", 'School copy']],
        'refund' => ['Refund voucher', ["Parent's copy", 'School copy']],
        'pv' => ['Payment voucher', ["Supplier's copy", 'School copy']],
        'wht' => ['Withholding tax certificate', ["Supplier's copy", 'School copy']],
        'pcv' => ['Petty cash voucher', ["Payee's copy", 'Accounts copy']],
    ];

    public static function ready(): bool
    {
        static $ok = null;
        if ($ok === null) {
            try {
                db()->value('SELECT 1 FROM doc_register LIMIT 1');
                $ok = true;
            } catch (PDOException $e) {
                $ok = false;
            }
        }
        return $ok;
    }

    /** Record a print; returns ['token', 'n' (this print's number), 'copy' (bool), 'first_at', 'first_by'] (null before the database update). */
    public static function mark(string $type, string $no): ?array
    {
        if (!self::ready() || $no === '') {
            return null;
        }
        $row = db()->one('SELECT * FROM doc_register WHERE doc_type = ? AND doc_no = ?', [$type, $no]);
        $now = date('Y-m-d H:i:s');
        if (!$row) {
            db()->run('INSERT IGNORE INTO doc_register (doc_type, doc_no, token, prints, first_printed_at, first_printed_by, last_printed_at, last_printed_by) VALUES (?, ?, ?, 1, ?, ?, ?, ?)',
                [$type, $no, bin2hex(random_bytes(12)), $now, Auth::id(), $now, Auth::id()]);
        } else {
            db()->run('UPDATE doc_register SET prints = prints + 1, last_printed_at = ?, last_printed_by = ? WHERE id = ?', [$now, Auth::id(), $row['id']]);
        }
        $row = db()->one('SELECT r.*, u.name AS first_by FROM doc_register r LEFT JOIN users u ON u.id = r.first_printed_by WHERE r.doc_type = ? AND r.doc_no = ?', [$type, $no]);
        return ['token' => $row['token'], 'n' => (int) $row['prints'], 'copy' => (int) $row['prints'] > 1, 'first_at' => $row['first_printed_at'], 'first_by' => $row['first_by']];
    }

    public static function verifyUrl(string $token): string
    {
        return absolute_url('verify/' . $token);
    }

    /** Public facts about a document, by its check code (for the verify page). */
    public static function lookup(string $token): ?array
    {
        if (!self::ready() || !preg_match('/^[0-9a-f]{24}$/', $token)) {
            return null;
        }
        $r = db()->one('SELECT * FROM doc_register WHERE token = ?', [$token]);
        if (!$r) {
            return null;
        }
        $no = $r['doc_no'];
        $mask = fn(?string $first, ?string $last) => trim((string) $first . ' ' . ($last ? mb_substr($last, 0, 1) . '.' : ''));
        $d = null;
        switch ($r['doc_type']) {
            case 'receipt':
                $p = db()->one('SELECT pay.*, pu.first_name, pu.last_name, i.invoice_no FROM payments pay JOIN pupils pu ON pu.id = pay.pupil_id JOIN invoices i ON i.id = pay.invoice_id WHERE pay.receipt_no = ?', [$no]);
                $d = $p ? ['date' => $p['paid_on'], 'amount' => $p['amount'], 'for' => 'School fees for ' . $mask($p['first_name'], $p['last_name']) . ' (bill ' . $p['invoice_no'] . ')', 'ok' => $p['status'] === 'completed', 'status' => $p['status'] === 'completed' ? 'Valid' : 'Reversed — this receipt no longer counts'] : null;
                break;
            case 'family':
                $sum = (float) db()->value("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE family_receipt = ? AND status = 'completed'", [$no]);
                $adv = db()->one("SELECT * FROM family_credits WHERE receipt_no = ? AND kind = 'advance'", [$no]);
                $date = db()->value('SELECT MIN(paid_on) FROM payments WHERE family_receipt = ?', [$no]) ?: ($adv['entry_date'] ?? null);
                $tot = $sum + ($adv && $adv['status'] === 'posted' ? (float) $adv['amount'] : 0);
                $d = $date ? ['date' => $date, 'amount' => $tot, 'for' => 'School fees (family payment)', 'ok' => $tot > 0, 'status' => $tot > 0 ? 'Valid' : 'Reversed — this receipt no longer counts'] : null;
                break;
            case 'credit':
                $c = db()->one('SELECT cn.*, p.first_name, p.last_name FROM credit_notes cn JOIN pupils p ON p.id = cn.pupil_id WHERE cn.cn_no = ?', [$no]);
                $d = $c ? ['date' => substr((string) ($c['decided_at'] ?? $c['requested_at']), 0, 10), 'amount' => $c['amount'], 'for' => 'Bill reduction for ' . $mask($c['first_name'], $c['last_name']), 'ok' => $c['status'] === 'approved', 'status' => $c['status'] === 'approved' ? 'Valid (approved)' : 'Not approved'] : null;
                break;
            case 'refund':
                $f = db()->one('SELECT * FROM fee_refunds WHERE rf_no = ?', [$no]);
                $d = $f ? ['date' => $f['paid_on'] ?? substr($f['requested_at'], 0, 10), 'amount' => $f['amount'], 'for' => 'Fee refund', 'ok' => $f['status'] === 'paid', 'status' => $f['status'] === 'paid' ? 'Valid (paid)' : 'Not paid'] : null;
                break;
            case 'pv':
            case 'wht':
                $p = db()->one('SELECT p.*, s.name FROM supplier_payments p JOIN supplier_bills b ON b.id = p.bill_id JOIN suppliers s ON s.id = b.supplier_id WHERE p.pv_no = ?', [$no]);
                $d = $p ? ['date' => $p['pay_date'], 'amount' => $r['doc_type'] === 'wht' ? $p['wht'] : $p['net'], 'for' => ($r['doc_type'] === 'wht' ? 'Withholding tax deducted from ' : 'Payment to ') . $p['name'], 'ok' => $p['status'] === 'posted', 'status' => $p['status'] === 'posted' ? 'Valid' : 'Cancelled'] : null;
                break;
            case 'pcv':
                $t = db()->one("SELECT * FROM fin_transactions WHERE txn_no = ? AND source = 'petty_spend'", [$no]);
                $d = $t ? ['date' => $t['txn_date'], 'amount' => $t['amount'], 'for' => 'Petty cash: ' . $t['description'], 'ok' => $t['status'] === 'posted', 'status' => $t['status'] === 'posted' ? 'Valid' : 'Cancelled'] : null;
                break;
        }
        return $d ? $d + ['type' => self::TYPES[$r['doc_type']][0] ?? 'Document', 'no' => $no, 'printed' => (int) $r['prints']] : null;
    }
}
