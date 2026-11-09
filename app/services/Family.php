<?php
declare(strict_types=1);

/**
 * Family accounts: a parent/guardian and every child linked to them. One payment can cover several children's
 * bills; anything paid beyond what is owed is kept as family credit (advance) and used on the next bill.
 * Also credit notes (reduce a bill after it is issued) and fee refunds (pay credit back to the family).
 *
 * Ledger rule: family_credits.amount is + when credit is added and - when it is used or refunded; the balance is
 * the sum of posted rows. A bill settled from credit gets a payment with method 'credit' (no new cash), so the
 * cash book only counts the money once — when the advance itself was received.
 */
final class Family
{
    public const CN_REASONS = [
        'withdrawn' => 'Student withdrawn / left', 'wrong_charge' => 'Charged in error', 'scholarship' => 'Scholarship / bursary awarded late',
        'service_not_used' => 'Service not used (feeding, bus …)', 'goodwill' => 'Head\'s goodwill reduction', 'other' => 'Other',
    ];
    public const REFUND_METHODS = ['cash' => 'Cash', 'momo' => 'Mobile Money', 'bank' => 'Bank transfer', 'cheque' => 'Cheque'];
    public const LEDGER_KINDS = [
        'advance' => 'Advance payment', 'overpayment' => 'Overpaid bill', 'applied' => 'Used on a bill', 'refund' => 'Refunded',
        'credit_note' => 'Credit note', 'reversal' => 'Correction',
    ];
    public const STATUS = ['pending' => ['Waiting for approval', 'warn'], 'approved' => ['Approved', 'info'], 'rejected' => ['Rejected', 'bad'], 'paid' => ['Paid', 'good']];

    public static function ready(): bool
    {
        static $ok = null;
        if ($ok === null) {
            try {
                db()->value('SELECT 1 FROM family_credits LIMIT 1');
                $ok = true;
            } catch (PDOException $e) {
                $ok = false;
            }
        }
        return $ok;
    }

    /** The family (primary guardian) a student belongs to. */
    public static function guardianFor(int $pupilId, ?Database $d = null): ?int
    {
        $g = ($d ?: db())->value('SELECT guardian_id FROM pupil_guardian WHERE pupil_id = ? ORDER BY is_primary DESC, guardian_id LIMIT 1', [$pupilId]);
        return $g ? (int) $g : null;
    }

    public static function guardian(int $gid): ?array
    {
        return db()->one('SELECT * FROM guardians WHERE id = ?', [$gid]);
    }

    /** Children linked to the guardian, with their current class. */
    public static function children(int $gid): array
    {
        return db()->all(
            "SELECT p.id, p.first_name, p.last_name, p.admission_no, p.status,
                    (SELECT c.name FROM enrolments e JOIN classes c ON c.id = e.class_id WHERE e.pupil_id = p.id AND e.status = 'active' AND e.academic_year_id = ? LIMIT 1) AS class_name
             FROM pupil_guardian pg JOIN pupils p ON p.id = pg.pupil_id
             WHERE pg.guardian_id = ? ORDER BY p.date_of_birth, p.first_name",
            [current_year()['id'] ?? 0, $gid]
        );
    }

    public static function credit(int $gid, ?Database $d = null): float
    {
        if (!self::ready()) {
            return 0.0;
        }
        return round((float) ($d ?: db())->value("SELECT COALESCE(SUM(amount), 0) FROM family_credits WHERE guardian_id = ? AND status = 'posted'", [$gid]), 2);
    }

    /** Credit not already promised to a refund waiting for approval or payment. */
    public static function freeCredit(int $gid, ?Database $d = null): float
    {
        $held = (float) ($d ?: db())->value("SELECT COALESCE(SUM(amount), 0) FROM fee_refunds WHERE guardian_id = ? AND status IN ('pending','approved')", [$gid]);
        return round(self::credit($gid, $d) - $held, 2);
    }

    /** Bills with money still owed, oldest first (the order a family payment is shared out). */
    public static function openBills(int $gid, ?Database $d = null): array
    {
        return ($d ?: db())->all(
            "SELECT i.*, p.first_name, p.last_name, ROUND(i.total - i.paid, 2) AS balance FROM invoices i JOIN pupils p ON p.id = i.pupil_id
             WHERE i.pupil_id IN (SELECT pupil_id FROM pupil_guardian WHERE guardian_id = ?) AND i.status IN ('unpaid','part') AND i.total - i.paid > 0.004
             ORDER BY COALESCE(i.due_date, i.issue_date), i.id",
            [$gid]
        );
    }

    public static function addEntry(Database $d, int $gid, float $amount, string $kind, array $extra = []): int
    {
        return $d->insert('family_credits', [
            'guardian_id' => $gid, 'pupil_id' => $extra['pupil_id'] ?? null, 'entry_date' => $extra['entry_date'] ?? date('Y-m-d'),
            'amount' => round($amount, 2), 'kind' => $kind, 'receipt_no' => $extra['receipt_no'] ?? null, 'method' => $extra['method'] ?? null,
            'reference' => isset($extra['reference']) ? mb_substr((string) $extra['reference'], 0, 100) : null,
            'invoice_id' => $extra['invoice_id'] ?? null, 'payment_id' => $extra['payment_id'] ?? null, 'refund_id' => $extra['refund_id'] ?? null,
            'note' => isset($extra['note']) ? mb_substr((string) $extra['note'], 0, 255) : null, 'created_by' => Auth::id(),
        ]);
    }

    /** Pay part of a bill from the family credit. Returns the amount used. */
    public static function applyToInvoice(Database $d, int $invoiceId, ?float $max = null): float
    {
        $inv = $d->one('SELECT * FROM invoices WHERE id = ? FOR UPDATE', [$invoiceId]);
        if (!$inv || $inv['status'] === 'cancelled') {
            return 0.0;
        }
        $gid = self::guardianFor((int) $inv['pupil_id'], $d);
        if (!$gid) {
            return 0.0;
        }
        $use = round(min(self::freeCredit($gid, $d), Fees::balance($inv), $max ?? PHP_FLOAT_MAX), 2);
        if ($use < 0.01) {
            return 0.0;
        }
        $pid = $d->insert('payments', [
            'receipt_no' => Fees::nextNumber($d, 'RCT'), 'invoice_id' => $invoiceId, 'pupil_id' => $inv['pupil_id'], 'amount' => $use,
            'method' => 'credit', 'reference' => 'Family credit', 'paid_on' => date('Y-m-d'), 'received_by' => Auth::id(),
        ]);
        self::addEntry($d, $gid, -$use, 'applied', ['pupil_id' => (int) $inv['pupil_id'], 'invoice_id' => $invoiceId, 'payment_id' => $pid, 'note' => $inv['invoice_no']]);
        Fees::recalc($invoiceId, $d);
        return $use;
    }

    /** Use credit automatically on a new bill (setting "fees_auto_credit", on unless switched off). */
    public static function autoApply(Database $d, int $invoiceId): float
    {
        if (!self::ready() || setting('fees_auto_credit', '1') !== '1') {
            return 0.0;
        }
        return self::applyToInvoice($d, $invoiceId);
    }

    /** Use the family credit on every open bill, oldest first. Returns the total used. */
    public static function applyAll(int $gid): float
    {
        return db()->transaction(function (Database $d) use ($gid) {
            $used = 0.0;
            foreach (self::openBills($gid, $d) as $b) {
                $used += self::applyToInvoice($d, (int) $b['id']);
            }
            return round($used, 2);
        });
    }

    /**
     * One payment for the whole family: $alloc = invoice_id => amount. Whatever is left becomes family credit.
     * Returns ['receipt' => FRC no, 'applied' => total on bills, 'credit' => kept, 'payments' => ids].
     */
    public static function receive(int $gid, float $amount, string $method, array $alloc, array $extra = []): array
    {
        return db()->transaction(function (Database $d) use ($gid, $amount, $method, $alloc, $extra) {
            $no = Fees::nextNumber($d, 'FRC');
            $date = $extra['paid_on'] ?? date('Y-m-d');
            $open = [];
            foreach (self::openBills($gid, $d) as $b) {
                $open[(int) $b['id']] = $b;
            }
            $applied = 0.0;
            $ids = [];
            foreach ($alloc as $iid => $amt) {
                $amt = round((float) $amt, 2);
                if ($amt < 0.01 || !isset($open[(int) $iid])) {
                    continue;
                }
                $amt = min($amt, (float) $open[(int) $iid]['balance'], round($amount - $applied, 2));
                if ($amt < 0.01) {
                    continue;
                }
                $pid = $d->insert('payments', [
                    'receipt_no' => Fees::nextNumber($d, 'RCT'), 'invoice_id' => (int) $iid, 'pupil_id' => $open[(int) $iid]['pupil_id'],
                    'amount' => $amt, 'method' => $method, 'reference' => $extra['reference'] ?? null,
                    'payer_name' => $extra['payer_name'] ?? null, 'paid_on' => $date, 'received_by' => Auth::id(), 'family_receipt' => $no,
                ]);
                Fees::recalc((int) $iid, $d);
                Finance::postFeePayment($d, $pid);
                $applied += $amt;
                $ids[] = $pid;
            }
            $credit = round($amount - $applied, 2);
            if ($credit >= 0.01) {
                $g = $d->one('SELECT name FROM guardians WHERE id = ?', [$gid]);
                $lid = self::addEntry($d, $gid, $credit, 'advance', ['entry_date' => $date, 'receipt_no' => $no, 'method' => $method, 'reference' => $extra['reference'] ?? null, 'note' => 'Advance / kept for the next bill']);
                $acct = Finance::accountForMethod($method);
                if ($acct) {
                    Finance::post($d, [
                        'txn_no' => $no, 'kind' => 'income', 'txn_date' => $date, 'account_id' => $acct,
                        'category_id' => $d->value("SELECT id FROM fin_categories WHERE code = 'FEES'") ?: null, 'amount' => $credit,
                        'description' => 'Advance fees (family credit): ' . ($g['name'] ?? 'family'), 'party' => $extra['payer_name'] ?? ($g['name'] ?? null),
                        'reference' => $extra['reference'] ?? null, 'source' => 'family_advance', 'source_id' => $lid,
                    ]);
                }
            }
            return ['receipt' => $no, 'applied' => round($applied, 2), 'credit' => max(0.0, $credit), 'payments' => $ids];
        });
    }

    /** Move money paid on a bill beyond its total to the family credit (double payment, credit note). */
    public static function moveOverpayment(Database $d, int $invoiceId, string $kind = 'overpayment', ?string $note = null): float
    {
        if (!self::ready()) {
            return 0.0;
        }
        $inv = $d->one('SELECT * FROM invoices WHERE id = ? FOR UPDATE', [$invoiceId]);
        $over = round((float) $inv['paid'] - (float) $inv['total'], 2);
        $gid = self::guardianFor((int) $inv['pupil_id'], $d);
        if ($over < 0.01 || !$gid) {
            return 0.0;
        }
        $d->run('UPDATE invoices SET credit_out = credit_out + ? WHERE id = ?', [$over, $invoiceId]);
        self::addEntry($d, $gid, $over, $kind, ['pupil_id' => (int) $inv['pupil_id'], 'invoice_id' => $invoiceId, 'note' => $note ?? ('Paid more than ' . $inv['invoice_no'] . ' needed')]);
        Fees::recalc($invoiceId, $d);
        return $over;
    }

    /**
     * Keep the family credit right when a receipt is reversed (call inside the reversal transaction, before recalc).
     * Returns an error message when the reversal must not go ahead.
     */
    public static function onReverse(Database $d, array $pay): ?string
    {
        if (!self::ready()) {
            return null;
        }
        $gid = self::guardianFor((int) $pay['pupil_id'], $d);
        if ($pay['method'] === 'credit') { // the credit goes back to the family
            if ($gid) {
                self::addEntry($d, $gid, (float) $pay['amount'], 'reversal', ['pupil_id' => (int) $pay['pupil_id'], 'invoice_id' => (int) $pay['invoice_id'], 'payment_id' => (int) $pay['id'], 'note' => 'Credit returned: ' . $pay['receipt_no'] . ' reversed']);
            }
            return null;
        }
        $out = (float) $d->value('SELECT credit_out FROM invoices WHERE id = ?', [$pay['invoice_id']]);
        $claw = round(min($out, (float) $pay['amount']), 2);
        if ($claw < 0.01) {
            return null;
        }
        if (!$gid || self::freeCredit($gid, $d) + 0.004 < $claw) {
            return 'Part of this receipt (' . Fees::money($claw) . ') was moved to the family credit and has already been used or refunded. Reverse that first.';
        }
        $d->run('UPDATE invoices SET credit_out = credit_out - ? WHERE id = ?', [$claw, $pay['invoice_id']]);
        self::addEntry($d, $gid, -$claw, 'reversal', ['pupil_id' => (int) $pay['pupil_id'], 'invoice_id' => (int) $pay['invoice_id'], 'payment_id' => (int) $pay['id'], 'note' => 'Receipt ' . $pay['receipt_no'] . ' reversed']);
        return null;
    }

    /** Reverse an advance payment (money not actually received / entered wrongly), while its credit is unused. */
    public static function reverseAdvance(int $entryId, string $reason): ?string
    {
        return db()->transaction(function (Database $d) use ($entryId, $reason) {
            $e = $d->one("SELECT * FROM family_credits WHERE id = ? AND kind = 'advance' AND status = 'posted' FOR UPDATE", [$entryId]);
            if (!$e) {
                return 'That advance cannot be reversed.';
            }
            if ($err = Finance::closedError($e['entry_date'])) {
                return $err;
            }
            if (self::freeCredit((int) $e['guardian_id'], $d) + 0.004 < (float) $e['amount']) {
                return 'This credit has already been used on a bill or refunded. Reverse that first.';
            }
            $d->update('family_credits', ['status' => 'reversed', 'note' => mb_substr('Reversed: ' . $reason, 0, 255)], 'id = ?', [$entryId]);
            Finance::voidSource($d, 'family_advance', $entryId, 'Advance reversed: ' . $reason);
            return null;
        });
    }

    // ------------------------------------------------------------------ credit notes

    public static function cnDecide(int $id, bool $approve, string $note): ?string
    {
        return db()->transaction(function (Database $d) use ($id, $approve, $note) {
            $cn = $d->one('SELECT * FROM credit_notes WHERE id = ? FOR UPDATE', [$id]);
            if (!$cn || $cn['status'] !== 'pending') {
                return 'This credit note has already been decided.';
            }
            if ((int) $cn['requested_by'] === (int) Auth::id() && Auth::role() !== 'super_admin') {
                return 'Someone else must approve a credit note you asked for.';
            }
            $upd = ['status' => $approve ? 'approved' : 'rejected', 'decided_by' => Auth::id(), 'decided_at' => date('Y-m-d H:i:s'), 'decision_note' => mb_substr($note, 0, 255) ?: null];
            if ($approve) {
                $inv = $d->one('SELECT * FROM invoices WHERE id = ? FOR UPDATE', [$cn['invoice_id']]);
                if (!$inv || $inv['status'] === 'cancelled') {
                    return 'The bill was cancelled, so the credit note cannot be applied.';
                }
                if ((float) $cn['amount'] > (float) $inv['total'] + 0.004) {
                    return 'The credit note is now more than the bill total (' . Fees::money($inv['total']) . ').';
                }
                $d->insert('invoice_lines', ['invoice_id' => $cn['invoice_id'], 'description' => 'Credit note ' . $cn['cn_no'] . ' — ' . mb_substr($cn['reason'], 0, 110), 'amount' => -(float) $cn['amount']]);
                Fees::recalc((int) $cn['invoice_id'], $d);
                $upd['to_credit'] = self::moveOverpayment($d, (int) $cn['invoice_id'], 'credit_note', 'Credit note ' . $cn['cn_no'] . ' on ' . $inv['invoice_no']);
            }
            $d->update('credit_notes', $upd, 'id = ?', [$id]);
            return null;
        });
    }

    // ------------------------------------------------------------------ refunds

    public static function refundDecide(int $id, bool $approve, string $note): ?string
    {
        $r = db()->one('SELECT * FROM fee_refunds WHERE id = ?', [$id]);
        if (!$r || $r['status'] !== 'pending') {
            return 'This refund has already been decided.';
        }
        if ((int) $r['requested_by'] === (int) Auth::id() && Auth::role() !== 'super_admin') {
            return 'Someone else must approve a refund you asked for.';
        }
        db()->update('fee_refunds', ['status' => $approve ? 'approved' : 'rejected', 'decided_by' => Auth::id(), 'decided_at' => date('Y-m-d H:i:s'), 'decision_note' => mb_substr($note, 0, 255) ?: null], 'id = ?', [$id]);
        return null;
    }

    public static function refundPay(int $id, int $accountId, string $date, string $reference): ?string
    {
        if ($err = Finance::closedError($date)) {
            return $err;
        }
        return db()->transaction(function (Database $d) use ($id, $accountId, $date, $reference) {
            $r = $d->one('SELECT * FROM fee_refunds WHERE id = ? FOR UPDATE', [$id]);
            if (!$r || $r['status'] !== 'approved') {
                return 'Only an approved refund can be paid.';
            }
            if (self::credit((int) $r['guardian_id'], $d) + 0.004 < (float) $r['amount']) {
                return 'The family credit is now less than the refund (' . Fees::money(self::credit((int) $r['guardian_id'], $d)) . ').';
            }
            if (!$d->value('SELECT id FROM fin_accounts WHERE id = ? AND is_active = 1', [$accountId])) {
                return 'Choose the account the refund is paid from.';
            }
            if (Finance::balance($accountId, $date) + 0.004 < (float) $r['amount']) {
                return 'That account does not have enough money for this refund.';
            }
            $lid = self::addEntry($d, (int) $r['guardian_id'], -(float) $r['amount'], 'refund', ['entry_date' => $date, 'refund_id' => $id, 'reference' => $reference, 'method' => $r['method'], 'note' => 'Refund ' . $r['rf_no']]);
            $txn = Finance::post($d, [
                'txn_no' => $r['rf_no'], 'kind' => 'expense', 'txn_date' => $date, 'account_id' => $accountId,
                'category_id' => $d->value("SELECT id FROM fin_categories WHERE code = 'FREF'") ?: null, 'amount' => $r['amount'],
                'description' => 'Fee refund to ' . $r['pay_to'] . ': ' . mb_substr($r['reason'], 0, 150), 'party' => $r['pay_to'],
                'reference' => $reference ?: null, 'source' => 'fee_refund', 'source_id' => $id,
            ]);
            $d->update('fee_refunds', ['status' => 'paid', 'account_id' => $accountId, 'paid_on' => $date, 'paid_by' => Auth::id(), 'txn_id' => $txn, 'reference' => $reference ?: null], 'id = ?', [$id]);
            unset($lid);
            return null;
        });
    }
}
