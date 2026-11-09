<?php
declare(strict_types=1);

/** Month-end close (lock a finished month) and the monthly finance report pack for the Board / School Manager. */
class CloseController extends Controller
{
    /** The month that can be closed next ('Y-m'), or null when last month is already closed. */
    private static function nextMonth(): ?string
    {
        $c = Finance::closedUntil();
        $last = date('Y-m', strtotime('first day of last month'));
        $next = $c === '' ? $last : date('Y-m', strtotime($c . ' +1 day'));
        return $next <= $last ? $next : null;
    }

    /** Checks before closing a month: [label, ok, detail, link]. */
    private static function checks(string $month): array
    {
        $end = date('Y-m-t', strtotime($month . '-01'));
        $out = [];
        if (Family::ready()) {
            foreach (db()->all("SELECT * FROM fin_accounts WHERE is_active = 1 AND kind IN ('bank','momo') ORDER BY sort_order") as $a) {
                $done = db()->value("SELECT 1 FROM fin_reconciliations WHERE account_id = ? AND status = 'completed' AND period_end >= ?", [$a['id'], $end]);
                $out[] = ['Bank reconciliation: ' . $a['name'], (bool) $done, $done ? 'Reconciled to ' . fmt_date($end) : 'Not reconciled to ' . fmt_date($end), url('finance/reconcile/' . $a['id'], ['end' => $end])];
            }
            $petty = (int) db()->value("SELECT COUNT(*) FROM fin_transactions t JOIN fin_accounts a ON a.id = t.account_id WHERE a.kind = 'petty' AND t.kind = 'expense' AND t.status = 'posted' AND t.petty_topup_id IS NULL AND t.txn_date <= ?", [$end]);
            $out[] = ['Petty cash vouchers checked (topped up)', $petty === 0, $petty ? $petty . ' voucher(s) of the month not yet checked in a top-up' : 'All checked', url('petty-cash')];
            $late = (int) db()->value("SELECT COUNT(*) FROM supplier_bills WHERE status IN ('unpaid','part') AND due_date <= ?", [$end]);
            $out[] = ['Supplier bills due by month end paid', $late === 0, $late ? $late . ' bill(s) overdue' : 'None overdue', url('suppliers')];
            $pend = (int) db()->value("SELECT (SELECT COUNT(*) FROM credit_notes WHERE status = 'pending') + (SELECT COUNT(*) FROM fee_refunds WHERE status IN ('pending','approved'))");
            $out[] = ['Credit notes & refunds decided', $pend === 0, $pend ? $pend . ' waiting' : 'Nothing waiting', url('fees/refunds')];
        }
        try {
            $run = db()->one('SELECT status FROM payroll_runs WHERE period = ?', [$month]);
            $out[] = ['Payroll for ' . date('F', strtotime($month . '-01')) . ' paid', ($run['status'] ?? '') === 'paid', $run ? ucfirst($run['status']) : 'No payroll prepared', url('payroll')];
        } catch (PDOException $e) {
            // payroll not installed
        }
        if (Family::ready()) {
            $start = $month . '-01';
            $oldWht = (float) db()->value("SELECT COALESCE(SUM(wht), 0) FROM supplier_payments WHERE status = 'posted' AND wht > 0 AND wht_remit_id IS NULL AND pay_date < ?", [$start]);
            $out[] = ['Withholding tax from earlier months paid to GRA', $oldWht < 0.01, $oldWht >= 0.01 ? Fees::money($oldWht) . ' still to pay (due by the 15th)' : 'Up to date', url('suppliers/wht')];
        }
        if (Purchasing::ready()) {
            $grn = (int) db()->value("SELECT COUNT(DISTINCT g.po_id) FROM goods_received g WHERE g.status = 'posted' AND g.received_date <= ? AND NOT EXISTS (SELECT 1 FROM supplier_bills b WHERE b.po_id = g.po_id AND b.status <> 'cancelled')", [$end]);
            $out[] = ['Goods received have their supplier invoice recorded', $grn === 0, $grn ? $grn . ' order(s) received but not invoiced' : 'All recorded', url('purchasing/received')];
        }
        if (Ledger::ready()) {
            Ledger::sync();
            $pendJ = (int) db()->value("SELECT COUNT(*) FROM gl_journals WHERE status = 'pending' AND entry_date <= ?", [$end]);
            $bad = array_filter(Ledger::controls(), fn($c) => !$c[1]);
            $out[] = ['The books agree with every module; no manual journal waiting', !$pendJ && !$bad, $pendJ ? $pendJ . ' manual journal(s) waiting for approval' : ($bad ? count($bad) . ' check(s) do not agree' : 'Trial balance agrees'), url($pendJ ? 'ledger/journals' : 'ledger')];
            $clear = (float) db()->value("SELECT COALESCE(SUM(l.debit - l.credit), 0) FROM gl_lines l JOIN gl_entries e ON e.id = l.entry_id JOIN gl_accounts a ON a.id = l.account_id WHERE a.sys_key = 'fa_clear' AND e.entry_date <= ?", [$end]);
            $out[] = ['Assets paid for are on the asset register', abs($clear) < 0.01, abs($clear) < 0.01 ? 'All on the register' : ($clear > 0 ? Fees::money($clear) . ' paid for assets not yet on the register' : Fees::money(-$clear) . ' on the register without a payment record'), url('fixed-assets')];
        }
        $reqs = (int) db()->value("SELECT COUNT(*) FROM requisitions WHERE status = 'paid'");
        $out[] = ['Requisitions paid out are retired (receipts & change)', $reqs === 0, $reqs ? $reqs . ' still waiting for receipts' : 'All retired', url('requisitions')];
        return $out;
    }

    public function index(): void
    {
        $this->needsTable('fin_period_log');
        $next = self::nextMonth();
        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $m = date('Y-m', strtotime("first day of -$i months"));
            $months[] = ['m' => $m, 'closed' => Finance::isClosed(date('Y-m-t', strtotime($m . '-01')))];
        }
        $this->view('finance/close', [
            'title' => 'Month-end', 'next' => $next, 'checks' => $next ? self::checks($next) : [], 'months' => $months,
            'closedUntil' => Finance::closedUntil(),
            'log' => db()->all('SELECT l.*, u.name AS by_name FROM fin_period_log l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.id DESC LIMIT 20'),
        ]);
    }

    public function close(): void
    {
        $this->needsTable('fin_period_log');
        $m = (string) ($_POST['month'] ?? '');
        $next = self::nextMonth();
        if ($m !== $next) {
            flash('error', 'Months are closed in order. The next month to close is ' . ($next ? date('F Y', strtotime($next . '-01')) : 'not due yet') . '.');
            redirect('finance/close');
        }
        $failed = array_filter(self::checks($m), fn($c) => !$c[1]);
        if ($failed && empty($_POST['anyway'])) {
            flash('error', 'Some checks are not done. Finish them, or tick “Close anyway” and give the reason.');
            redirect('finance/close');
        }
        $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 255);
        if ($failed && $note === '') {
            flash('error', 'Say why the month is closed with checks not done.');
            redirect('finance/close');
        }
        $end = date('Y-m-t', strtotime($m . '-01'));
        setting_set('fin_closed_until', $end);
        db()->insert('fin_period_log', ['month' => $m, 'action' => 'close', 'note' => $note ?: null, 'user_id' => Auth::id()]);
        audit('month_closed', 'settings', null, ['month' => $m, 'open_checks' => count($failed)]);
        Notifier::inappPermission('finance.reopen', 'Month closed: ' . date('F Y', strtotime($end)), 'The finance records up to ' . fmt_date($end) . ' are locked. The board pack is ready.', 'finance/board-pack?month=' . $m);
        flash('success', date('F Y', strtotime($end)) . ' is closed. Money records up to ' . fmt_date($end) . ' can no longer change.');
        redirect('finance/close');
    }

    public function reopen(): void
    {
        $this->needsTable('fin_period_log');
        $c = Finance::closedUntil();
        $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 255);
        if ($c === '' || $note === '') {
            flash('error', $c === '' ? 'No month is closed.' : 'Give the reason for reopening.');
            redirect('finance/close');
        }
        $m = substr($c, 0, 7);
        $prev = date('Y-m-t', strtotime($m . '-01 -1 month'));
        $hadPrev = db()->value("SELECT 1 FROM fin_period_log WHERE action = 'close' AND month = ?", [substr($prev, 0, 7)]);
        setting_set('fin_closed_until', $hadPrev ? $prev : '');
        db()->insert('fin_period_log', ['month' => $m, 'action' => 'reopen', 'note' => $note, 'user_id' => Auth::id()]);
        audit('month_reopened', 'settings', null, ['month' => $m, 'note' => $note]);
        Notifier::inappPermission('finance.close', 'Month reopened: ' . date('F Y', strtotime($c)), $note, 'finance/close');
        flash('success', date('F Y', strtotime($c)) . ' reopened. Close it again when the correction is done.');
        redirect('finance/close');
    }

    /** Monthly finance report pack (print). */
    public function boardPack(): void
    {
        $m = (string) ($_GET['month'] ?? date('Y-m', strtotime('first day of last month')));
        if (!preg_match('/^\d{4}-\d{2}$/', $m) || $m > date('Y-m')) {
            $m = date('Y-m', strtotime('first day of last month'));
        }
        $from = $m . '-01';
        $to = date('Y-m-t', strtotime($from));
        $prevEnd = date('Y-m-d', strtotime($from . ' -1 day'));
        $year = db()->one('SELECT * FROM academic_years WHERE ? BETWEEN start_date AND end_date LIMIT 1', [$to]) ?? current_year();
        $ytdFrom = $year['start_date'] ?? date('Y-01-01');

        $accounts = [];
        foreach (Finance::accounts(false) as $a) {
            $open = Finance::balance((int) $a['id'], $prevEnd);
            $close = Finance::balance((int) $a['id'], $to);
            $rows = db()->all("SELECT * FROM fin_transactions WHERE status = 'posted' AND txn_date BETWEEN ? AND ? AND (account_id = ? OR to_account_id = ?)", [$from, $to, $a['id'], $a['id']]);
            $in = 0.0;
            $out = 0.0;
            foreach ($rows as $t) {
                $e = Finance::effect($t, (int) $a['id']);
                $e > 0 ? $in += $e : $out -= $e;
            }
            if ($a['is_active'] || abs($close) > 0.004 || $rows) {
                $rec = Family::ready() ? db()->value("SELECT MAX(period_end) FROM fin_reconciliations WHERE account_id = ? AND status = 'completed'", [$a['id']]) : null;
                $accounts[] = $a + ['open' => $open, 'in' => $in, 'out' => $out, 'close' => $close, 'rec' => $rec];
            }
        }
        $month = Finance::totalsByCategory($from, $to);
        $ytd = Finance::totalsByCategory($ytdFrom, $to);
        $budgets = [];
        foreach (db()->all('SELECT category_id, amount FROM fin_budgets WHERE academic_year_id = ?', [$year['id'] ?? 0]) as $b) {
            $budgets[(int) $b['category_id']] = (float) $b['amount'];
        }
        $cats = Finance::categories(null, false);

        // fees: this term's bills and debtors by age
        $term = db()->one('SELECT * FROM terms WHERE ? BETWEEN start_date AND end_date LIMIT 1', [$to]) ?? current_term();
        $fees = $term ? db()->one("SELECT COALESCE(SUM(total), 0) AS billed, COALESCE(SUM(paid), 0) AS paid FROM invoices WHERE term_id = ? AND status <> 'cancelled'", [$term['id']]) : ['billed' => 0, 'paid' => 0];
        $aging = ['Not yet due' => 0.0, '1–30 days late' => 0.0, '31–60 days' => 0.0, '61–90 days' => 0.0, 'Over 90 days' => 0.0];
        foreach (db()->all("SELECT total - paid AS bal, due_date FROM invoices WHERE status IN ('unpaid','part') AND issue_date <= ?", [$to]) as $i) {
            $days = $i['due_date'] ? (int) floor((strtotime($to) - strtotime($i['due_date'])) / 86400) : 0;
            $k = $days <= 0 ? 'Not yet due' : ($days <= 30 ? '1–30 days late' : ($days <= 60 ? '31–60 days' : ($days <= 90 ? '61–90 days' : 'Over 90 days')));
            $aging[$k] += (float) $i['bal'];
        }
        $extra = [];
        if (Family::ready()) {
            $extra = [
                'credit' => (float) db()->value("SELECT COALESCE(SUM(amount), 0) FROM family_credits WHERE status = 'posted' AND entry_date <= ?", [$to]),
                'cn' => db()->one("SELECT COUNT(*) AS n, COALESCE(SUM(amount), 0) AS amt FROM credit_notes WHERE status = 'approved' AND decided_at BETWEEN ? AND ?", [$from, $to . ' 23:59:59']),
                'refunds' => db()->one("SELECT COUNT(*) AS n, COALESCE(SUM(amount), 0) AS amt FROM fee_refunds WHERE status = 'paid' AND paid_on BETWEEN ? AND ?", [$from, $to]),
                'owed' => (float) db()->value("SELECT COALESCE(SUM(amount - paid_gross), 0) FROM supplier_bills WHERE status IN ('unpaid','part')"),
                'overdue' => (float) db()->value("SELECT COALESCE(SUM(amount - paid_gross), 0) FROM supplier_bills WHERE status IN ('unpaid','part') AND due_date < ?", [$to]),
                'wht' => array_sum(array_map(fn($p) => (float) $p['wht'], Suppliers::whtOwed())),
                'petty' => Petty::boxes(),
                'assets' => db()->all('SELECT * FROM fixed_assets WHERE purchase_date <= ?', [$to]),
                'newAssets' => db()->all('SELECT * FROM fixed_assets WHERE purchase_date BETWEEN ? AND ?', [$from, $to]),
                'gone' => db()->all("SELECT * FROM fixed_assets WHERE disposed_on BETWEEN ? AND ?", [$from, $to]),
            ];
        }
        $this->view('finance/board_pack', [
            'title' => 'Finance report ' . date('F Y', strtotime($from)), 'm' => $m, 'from' => $from, 'to' => $to, 'year' => $year, 'ytdFrom' => $ytdFrom,
            'accounts' => $accounts, 'month' => $month, 'ytd' => $ytd, 'budgets' => $budgets, 'cats' => $cats, 'term' => $term, 'fees' => $fees, 'aging' => $aging,
            'reqOpen' => db()->one("SELECT COUNT(*) AS n, COALESCE(SUM(COALESCE(approved_amount, amount)), 0) AS amt FROM requisitions WHERE status IN ('approved_l1','approved')"),
            'closed' => Finance::isClosed($to), 'x' => $extra,
        ], 'layouts/print');
    }
}
