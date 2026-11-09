<?php
declare(strict_types=1);

/** Cash book: money accounts, income/expense/refund/transfer transactions, balances and budgets. */
final class Finance
{
    public const KINDS = ['income' => 'Income', 'expense' => 'Expense', 'refund' => 'Refund (money back)', 'transfer' => 'Transfer'];
    public const ACCOUNT_KINDS = ['cash' => 'Cash', 'bank' => 'Bank', 'momo' => 'Mobile Money', 'online' => 'Online gateway', 'petty' => 'Petty cash box'];
    public const SOURCES = ['manual' => 'Entered by hand', 'fee_payment' => 'Fee payment', 'requisition' => 'Requisition', 'req_refund' => 'Requisition change', 'payroll' => 'Payroll (salaries)', 'payroll_stat' => 'Payroll (SSNIT & PAYE)', 'canteen_sale' => 'Canteen sale', 'canteen_topup' => 'Canteen wallet top-up',
        'family_advance' => 'Advance fees (family credit)', 'fee_refund' => 'Fee refund', 'supplier_payment' => 'Supplier payment', 'wht_remit' => 'Withholding tax to GRA',
        'petty_topup' => 'Petty cash top-up', 'petty_spend' => 'Petty cash voucher', 'asset_disposal' => 'Asset sold',
        'gateway_charge' => 'Online payment charge (parent)', 'gateway_fee' => 'Online gateway fee'];

    /** Is the database updated for department budgets (migration 032)? */
    public static function hasDepartments(): bool
    {
        static $has = null;
        if ($has === null) {
            $has = (bool) db()->value("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'fin_transactions' AND column_name = 'department'");
        }
        return $has;
    }

    /** Department names for requisitions, orders, bills and budget caps. */
    public static function departments(bool $activeOnly = true): array
    {
        try {
            $rows = array_column(db()->all('SELECT name FROM fin_departments' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY sort_order, name'), 'name');
        } catch (PDOException $e) {
            $rows = [];
        }
        return $rows ?: Requisition::DEPARTMENTS;
    }

    // ------------------------------------------------------------------ month-end close

    /** Last day of the latest closed month ('' when nothing is closed). */
    public static function closedUntil(): string
    {
        $v = (string) setting('fin_closed_until', '');
        return Validator::isDate($v) ? $v : '';
    }

    public static function isClosed(?string $date): bool
    {
        $c = self::closedUntil();
        return $c !== '' && $date !== null && $date !== '' && substr($date, 0, 10) <= $c;
    }

    /** Error message when a money record may not be dated $date (its month is closed), else null. */
    public static function closedError(?string $date): ?string
    {
        return self::isClosed($date) ? date('F Y', strtotime((string) $date)) . ' is closed — money records up to ' . fmt_date(self::closedUntil()) . ' can no longer change. Use a date after that, or ask the Head to reopen the month.' : null;
    }

    /** Money accounts; $withPetty = false leaves out petty cash boxes (for paying bills, refunds, top-ups). */
    public static function accounts(bool $activeOnly = true, bool $withPetty = true): array
    {
        $w = [];
        if ($activeOnly) {
            $w[] = 'is_active = 1';
        }
        if (!$withPetty) {
            $w[] = "kind <> 'petty'";
        }
        return db()->all('SELECT * FROM fin_accounts' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY sort_order, name');
    }

    public static function categories(?string $kind = null, bool $activeOnly = true): array
    {
        $w = [];
        $p = [];
        if ($kind) {
            $w[] = 'kind = ?';
            $p[] = $kind;
        }
        if ($activeOnly) {
            $w[] = 'is_active = 1';
        }
        return db()->all('SELECT * FROM fin_categories' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY kind, sort_order, name', $p);
    }

    /** Balance of one account (optionally as at the end of a date). */
    public static function balance(int $accountId, ?string $asAt = null): float
    {
        $acc = db()->one('SELECT opening_balance FROM fin_accounts WHERE id = ?', [$accountId]);
        if (!$acc) {
            return 0.0;
        }
        $date = $asAt ? ' AND txn_date <= ?' : '';
        $p = $asAt ? [$accountId, $asAt] : [$accountId];
        $in = (float) db()->value("SELECT COALESCE(SUM(amount), 0) FROM fin_transactions WHERE status = 'posted' AND account_id = ? AND kind IN ('income','refund')$date", $p);
        $out = (float) db()->value("SELECT COALESCE(SUM(amount), 0) FROM fin_transactions WHERE status = 'posted' AND account_id = ? AND kind IN ('expense','transfer')$date", $p);
        $tin = (float) db()->value("SELECT COALESCE(SUM(amount), 0) FROM fin_transactions WHERE status = 'posted' AND to_account_id = ? AND kind = 'transfer'$date", $p);
        return round((float) $acc['opening_balance'] + $in - $out + $tin, 2);
    }

    /** Signed effect of a transaction on a given account. */
    public static function effect(array $t, int $accountId): float
    {
        if ($t['status'] !== 'posted') {
            return 0.0;
        }
        if ($t['kind'] === 'transfer') {
            return (int) $t['to_account_id'] === $accountId ? (float) $t['amount'] : -(float) $t['amount'];
        }
        return in_array($t['kind'], ['income', 'refund'], true) ? (float) $t['amount'] : -(float) $t['amount'];
    }

    public static function nextNo(Database $d, string $prefix = 'TXN'): string
    {
        return Fees::nextNumber($d, $prefix);
    }

    /** Insert a transaction. Returns its id. */
    public static function post(Database $d, array $t): int
    {
        $t += ['source' => 'manual', 'source_id' => null, 'to_account_id' => null, 'category_id' => null, 'party' => null, 'reference' => null, 'term_id' => null];
        if (empty($t['txn_no'])) {
            $t['txn_no'] = self::nextNo($d);
        }
        if (self::isClosed((string) $t['txn_date'])) {
            throw new RuntimeException('period_closed');
        }
        $t['amount'] = round((float) $t['amount'], 2);
        $t['description'] = mb_substr((string) $t['description'], 0, 255);
        $t['created_by'] = $t['created_by'] ?? Auth::id();
        if (!isset($t['term_id'])) {
            $t['term_id'] = self::termFor((string) $t['txn_date']);
        }
        if (array_key_exists('department', $t) && ($t['department'] === null || $t['department'] === '' || !self::hasDepartments())) {
            unset($t['department']);
        }
        return $d->insert('fin_transactions', $t);
    }

    public static function termFor(string $date): ?int
    {
        $id = db()->value('SELECT id FROM terms WHERE ? BETWEEN start_date AND end_date ORDER BY start_date LIMIT 1', [$date]);
        return $id ? (int) $id : null;
    }

    public static function accountForMethod(string $method): ?int
    {
        $key = ['cash' => 'fin_acct_cash', 'momo' => 'fin_acct_momo', 'online' => 'fin_acct_online'][$method] ?? 'fin_acct_bank';
        $id = (int) setting($key, '0');
        if ($id && db()->value('SELECT id FROM fin_accounts WHERE id = ?', [$id])) {
            return $id;
        }
        $first = db()->value('SELECT id FROM fin_accounts ORDER BY sort_order, id LIMIT 1');
        return $first ? (int) $first : null;
    }

    /** Called inside the fee payment transaction: every fee receipt goes into the cash book. */
    public static function postFeePayment(Database $d, int $paymentId): void
    {
        $p = $d->one(
            'SELECT pay.*, i.invoice_no, i.term_id, pu.first_name, pu.last_name FROM payments pay
             JOIN invoices i ON i.id = pay.invoice_id JOIN pupils pu ON pu.id = pay.pupil_id WHERE pay.id = ?',
            [$paymentId]
        );
        if (!$p || $p['method'] === 'credit') {
            return; // paid from family credit: the money reached the cash book when the advance was received
        }
        $acct = self::accountForMethod($p['method']);
        try { // school shop orders are billed as invoices too, but belong under "School shop" income
            $shop = $d->value('SELECT source FROM invoices WHERE id = ?', [$p['invoice_id']]) === 'shop';
        } catch (PDOException $e) {
            $shop = false;
        }
        $cat = $d->value('SELECT id FROM fin_categories WHERE code = ?', [$shop ? 'SHOP' : 'FEES']) ?: $d->value("SELECT id FROM fin_categories WHERE code = 'FEES'");
        if (!$acct || $d->value("SELECT id FROM fin_transactions WHERE source = 'fee_payment' AND source_id = ?", [$paymentId])) {
            return;
        }
        self::post($d, [
            'txn_no' => $p['receipt_no'], 'kind' => 'income', 'txn_date' => $p['paid_on'], 'account_id' => $acct,
            'category_id' => $cat ?: null, 'amount' => $p['amount'],
            'description' => ($shop ? 'School shop: ' : 'Fees: ') . $p['first_name'] . ' ' . $p['last_name'] . ' (' . $p['invoice_no'] . ')',
            'party' => $p['payer_name'], 'reference' => $p['reference'], 'term_id' => $p['term_id'],
            'source' => 'fee_payment', 'source_id' => $paymentId, 'created_by' => $p['received_by'],
        ]);
    }

    /** Void the cash book entry created by another module (e.g. a reversed fee receipt). */
    public static function voidSource(Database $d, string $source, int $sourceId, string $reason): void
    {
        $d->update('fin_transactions', [
            'status' => 'void', 'void_reason' => mb_substr($reason, 0, 255), 'voided_by' => Auth::id(), 'voided_at' => date('Y-m-d H:i:s'),
        ], "source = ? AND source_id = ? AND status = 'posted'", [$source, $sourceId]);
    }

    /**
     * Net spending per expense category (expenses minus refunds) and income per income category
     * between two dates. @return array<int,float> category_id => amount
     */
    public static function totalsByCategory(string $from, string $to): array
    {
        $out = [];
        foreach (db()->all(
            "SELECT category_id, SUM(CASE WHEN kind = 'refund' THEN -amount ELSE amount END) AS amt FROM fin_transactions
             WHERE status = 'posted' AND kind IN ('income','expense','refund') AND txn_date BETWEEN ? AND ? AND category_id IS NOT NULL
             GROUP BY category_id",
            [$from, $to]
        ) as $r) {
            $out[(int) $r['category_id']] = (float) $r['amt'];
        }
        return $out;
    }

    /** Approved requisitions not yet paid and unpaid supplier bills, per category (money promised but not spent). */
    public static function committedByCategory(): array
    {
        $out = [];
        $bills = Family::ready(); // supplier bills exist (migration 030)
        foreach (db()->all("SELECT category_id, SUM(COALESCE(approved_amount, amount)) AS amt FROM requisitions WHERE status IN ('approved_l1','approved') AND category_id IS NOT NULL" . ($bills ? ' AND supplier_bill_id IS NULL' : '') . ' GROUP BY category_id') as $r) {
            $out[(int) $r['category_id']] = (float) $r['amt'];
        }
        if ($bills) { // supplier bills not yet paid are money already owed
            foreach (db()->all("SELECT category_id, SUM(amount - paid_gross) AS amt FROM supplier_bills WHERE status IN ('unpaid','part') AND category_id IS NOT NULL GROUP BY category_id") as $r) {
                $out[(int) $r['category_id']] = ($out[(int) $r['category_id']] ?? 0) + (float) $r['amt'];
            }
        }
        return $out;
    }

    /** Budget position of one category for the current academic year. */
    public static function budgetPosition(?int $categoryId): ?array
    {
        $year = current_year();
        if (!$categoryId || !$year) {
            return null;
        }
        $budget = db()->value('SELECT amount FROM fin_budgets WHERE academic_year_id = ? AND category_id = ?', [$year['id'], $categoryId]);
        $spent = self::totalsByCategory($year['start_date'], $year['end_date'])[$categoryId] ?? 0.0;
        $committed = self::committedByCategory()[$categoryId] ?? 0.0;
        return [
            'budget' => $budget !== null ? (float) $budget : null, 'spent' => $spent, 'committed' => $committed,
            'remaining' => $budget !== null ? round((float) $budget - $spent - $committed, 2) : null, 'year' => $year['name'],
        ];
    }
}
