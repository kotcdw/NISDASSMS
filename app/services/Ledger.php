<?php
declare(strict_types=1);

/**
 * The books (double entry). Nobody types debits and credits for everyday work: sync() works out what every money
 * record in the system should look like as a journal entry (fee bills, receipts, family credit, the cash book,
 * supplier bills and payments, petty cash, payroll, fixed assets and depreciation, approved manual journals) and
 * posts only the difference from what is already in the books. A changed or cancelled record therefore gets a new
 * correcting entry: nothing already in the books is edited or deleted, and every entry balances by construction.
 *
 * Signs: in the working arrays an amount > 0 is a debit and < 0 a credit.
 */
final class Ledger
{
    public const TYPES = [
        'asset' => 'What the school owns', 'liability' => 'What the school owes', 'equity' => 'School fund',
        'income' => 'Income', 'expense' => 'Spending',
    ];
    /** Entry sources: key => label. */
    public const SOURCES = [
        'open' => 'Opening balance', 'txn' => 'Cash book', 'inv' => 'Fee bill', 'cn' => 'Credit note', 'pay' => 'Fee receipt',
        'fc' => 'Family credit', 'bill' => 'Supplier bill', 'spw' => 'Withholding tax', 'fa' => 'Fixed asset', 'fad' => 'Asset disposed',
        'dep' => 'Depreciation', 'jnl' => 'Manual journal',
    ];
    private const MONEY_BASE = ['cash' => 1010, 'bank' => 1020, 'momo' => 1030, 'online' => 1035, 'petty' => 1040];

    private static ?bool $ready = null;
    private static array $sys = [];
    private static array $money = [];
    private static array $cats = [];

    public static function ready(): bool
    {
        if (self::$ready === null) {
            try {
                db()->value('SELECT 1 FROM gl_entries LIMIT 1');
                self::$ready = true;
            } catch (PDOException $e) {
                self::$ready = false;
            }
        }
        return self::$ready;
    }

    /** A debit-normal account (assets, spending) shows its balance as debit − credit; the others as credit − debit. */
    public static function debitNormal(string $type): bool
    {
        return in_array($type, ['asset', 'expense'], true);
    }

    // ------------------------------------------------------------------ chart of accounts

    public static function sys(string $key): int
    {
        if (!self::$sys) {
            foreach (db()->all('SELECT id, sys_key FROM gl_accounts WHERE sys_key IS NOT NULL') as $r) {
                self::$sys[$r['sys_key']] = (int) $r['id'];
            }
        }
        return self::$sys[$key] ?? 0;
    }

    /** Make sure every money account and cash-book category has its own account in the books. */
    public static function ensureAccounts(?Database $d = null): void
    {
        $d = $d ?: db();
        $used = array_flip(array_map('strval', array_column($d->all('SELECT code FROM gl_accounts'), 'code')));
        $free = function (int $from, int $step, int $max) use (&$used): string {
            for ($c = $from; $c <= $max; $c += $step) {
                if (!isset($used[(string) $c])) {
                    $used[(string) $c] = true;
                    return (string) $c;
                }
            }
            for ($c = $from + 1; $c <= $max; $c++) { // ran out of round numbers
                if (!isset($used[(string) $c])) {
                    $used[(string) $c] = true;
                    return (string) $c;
                }
            }
            return (string) ($max + random_int(1, 999));
        };
        $have = array_column($d->all('SELECT fin_account_id FROM gl_accounts WHERE fin_account_id IS NOT NULL'), 'fin_account_id');
        foreach ($d->all('SELECT * FROM fin_accounts ORDER BY sort_order, id') as $a) {
            if (in_array((string) $a['id'], array_map('strval', $have), true)) {
                continue;
            }
            $base = self::MONEY_BASE[$a['kind']] ?? 1090;
            $d->insert('gl_accounts', ['code' => $free($base, 1, $base + 4), 'name' => $a['name'], 'type' => 'asset', 'fin_account_id' => $a['id']]);
        }
        $have = array_column($d->all('SELECT category_id FROM gl_accounts WHERE category_id IS NOT NULL'), 'category_id');
        foreach ($d->all('SELECT * FROM fin_categories ORDER BY kind, sort_order, id') as $c) {
            if (in_array((string) $c['id'], array_map('strval', $have), true) || $c['code'] === 'ASSET') {
                continue; // money spent on assets goes to "Assets paid for — waiting for the asset register"
            }
            $code = $c['code'] === 'FEES' && !isset($used['4000']) ? '4000' : ($c['code'] === 'SAL' && !isset($used['5000']) ? '5000' : null);
            if ($code) {
                $used[$code] = true;
            }
            $code = $code ?? ($c['kind'] === 'income' ? $free(4010, 10, 4890) : $free(5010, 10, 5890));
            $d->insert('gl_accounts', ['code' => $code, 'name' => $c['name'], 'type' => $c['kind'], 'category_id' => $c['id']]);
        }
        self::$money = [];
        self::$cats = [];
        foreach ($d->all('SELECT id, fin_account_id, category_id FROM gl_accounts WHERE fin_account_id IS NOT NULL OR category_id IS NOT NULL') as $r) {
            if ($r['fin_account_id']) {
                self::$money[(int) $r['fin_account_id']] = (int) $r['id'];
            } else {
                self::$cats[(int) $r['category_id']] = (int) $r['id'];
            }
        }
        foreach ($d->all("SELECT id FROM fin_categories WHERE code = 'ASSET'") as $r) {
            self::$cats[(int) $r['id']] = self::sys('fa_clear');
        }
    }

    private static function money(?int $finId): int
    {
        return self::$money[(int) $finId] ?? self::sys('expense_suspense');
    }

    private static function cat(?int $catId, string $kind = 'expense'): int
    {
        if ($catId && isset(self::$cats[$catId])) {
            return self::$cats[$catId];
        }
        return self::sys($kind === 'income' ? 'income_suspense' : 'expense_suspense');
    }

    /** Chart of accounts with balances (as at a date). */
    public static function chart(?string $asAt = null): array
    {
        $bal = [];
        $p = [];
        $w = '';
        if ($asAt) {
            $w = ' WHERE e.entry_date <= ?';
            $p[] = $asAt;
        }
        foreach (db()->all('SELECT l.account_id, SUM(l.debit) AS dr, SUM(l.credit) AS cr FROM gl_lines l JOIN gl_entries e ON e.id = l.entry_id' . $w . ' GROUP BY l.account_id', $p) as $r) {
            $bal[(int) $r['account_id']] = [(float) $r['dr'], (float) $r['cr']];
        }
        $out = [];
        foreach (db()->all('SELECT * FROM gl_accounts ORDER BY code') as $a) {
            [$dr, $cr] = $bal[(int) $a['id']] ?? [0.0, 0.0];
            $a['dr'] = round($dr, 2);
            $a['cr'] = round($cr, 2);
            $a['balance'] = round(self::debitNormal($a['type']) ? $dr - $cr : $cr - $dr, 2);
            $out[] = $a;
        }
        return $out;
    }

    /** Trial balance: [rows (non-zero accounts with debit or credit balance), total debit, total credit]. */
    public static function trialBalance(string $asAt): array
    {
        $rows = [];
        $td = $tc = 0.0;
        foreach (self::chart($asAt) as $a) {
            $net = round($a['dr'] - $a['cr'], 2);
            if (abs($net) < 0.005) {
                continue;
            }
            $a['tb_dr'] = $net > 0 ? $net : 0.0;
            $a['tb_cr'] = $net < 0 ? -$net : 0.0;
            $td += $a['tb_dr'];
            $tc += $a['tb_cr'];
            $rows[] = $a;
        }
        return [$rows, round($td, 2), round($tc, 2)];
    }

    /** Movement of income and spending accounts between two dates: [income rows, expense rows, total income, total spending]. */
    public static function incomeSpending(string $from, string $to): array
    {
        $mv = [];
        foreach (db()->all('SELECT l.account_id, SUM(l.debit - l.credit) AS net FROM gl_lines l JOIN gl_entries e ON e.id = l.entry_id WHERE e.entry_date BETWEEN ? AND ? GROUP BY l.account_id', [$from, $to]) as $r) {
            $mv[(int) $r['account_id']] = (float) $r['net'];
        }
        $inc = $exp = [];
        $ti = $te = 0.0;
        foreach (db()->all("SELECT * FROM gl_accounts WHERE type IN ('income','expense') ORDER BY code") as $a) {
            $net = $mv[(int) $a['id']] ?? 0.0;
            if (abs($net) < 0.005) {
                continue;
            }
            if ($a['type'] === 'income') {
                $a['amount'] = round(-$net, 2);
                $ti += $a['amount'];
                $inc[] = $a;
            } else {
                $a['amount'] = round($net, 2);
                $te += $a['amount'];
                $exp[] = $a;
            }
        }
        return [$inc, $exp, round($ti, 2), round($te, 2)];
    }

    /** Balance sheet as at a date: groups of [rows, total] plus the surplus to date. */
    public static function balanceSheet(string $asAt): array
    {
        $g = ['asset' => [], 'liability' => [], 'equity' => []];
        $t = ['asset' => 0.0, 'liability' => 0.0, 'equity' => 0.0];
        $surplus = 0.0;
        foreach (self::chart($asAt) as $a) {
            if (in_array($a['type'], ['income', 'expense'], true)) {
                $surplus += $a['type'] === 'income' ? $a['balance'] : -$a['balance'];
                continue;
            }
            if (abs($a['balance']) < 0.005) {
                continue;
            }
            $g[$a['type']][] = $a;
            $t[$a['type']] += $a['balance'];
        }
        $surplus = round($surplus, 2);
        return ['groups' => $g, 'totals' => array_map(fn($v) => round($v, 2), $t), 'surplus' => $surplus,
            'balanced' => abs(round($t['asset'] - $t['liability'] - $t['equity'] - $surplus, 2)) < 0.01];
    }

    /**
     * Do the books agree with the modules they are built from? [label, ok, books, module, link]
     * (they always balance; these checks find records the books could not place, e.g. a money account removed).
     */
    public static function controls(): array
    {
        $by = [];
        foreach (self::chart() as $a) {
            $by[(int) $a['id']] = $a['balance'];
        }
        $out = [];
        $fees = (float) db()->value("SELECT COALESCE(SUM(total - paid), 0) FROM invoices WHERE status <> 'cancelled'");
        $out[] = ['Fees owed by parents agree with the Fees module', round($by[self::sys('ar')] ?? 0, 2), round($fees, 2), 'fees/debtors'];
        if (Family::ready()) {
            $fc = (float) db()->value("SELECT COALESCE(SUM(amount), 0) FROM family_credits WHERE status = 'posted'");
            $out[] = ['Family credit agrees with the family accounts', round($by[self::sys('family_credit')] ?? 0, 2), round($fc, 2), 'fees/families'];
            $ap = (float) db()->value("SELECT COALESCE(SUM(amount - paid_gross), 0) FROM supplier_bills WHERE status IN ('unpaid','part')");
            $out[] = ['Supplier bills owed agree with Bills to pay', round($by[self::sys('ap')] ?? 0, 2), round($ap, 2), 'suppliers'];
        }
        foreach (Finance::accounts(false) as $a) {
            $gl = isset(self::$money[(int) $a['id']]) ? ($by[self::$money[(int) $a['id']]] ?? 0) : 0;
            $out[] = ['Money in ' . $a['name'] . ' agrees with the cash book', round($gl, 2), Finance::balance((int) $a['id']), 'finance/statement?account=' . $a['id']];
        }
        return array_map(fn($c) => [$c[0], abs($c[1] - $c[2]) < 0.01, $c[1], $c[2], $c[3]], $out);
    }

    // ------------------------------------------------------------------ building the books

    /** Bring the books up to date. Returns the number of new entries. */
    public static function sync(): int
    {
        if (!self::ready()) {
            return 0;
        }
        $lock = (int) db()->value("SELECT GET_LOCK('nisdas_ledger_sync', 10)");
        if (!$lock) {
            return 0;
        }
        try {
            return db()->transaction(function (Database $d) {
                self::ensureAccounts($d);
                $docs = self::expected($d);
                $have = [];
                foreach ($d->all('SELECT e.source, e.source_id, l.account_id, SUM(l.debit - l.credit) AS net FROM gl_lines l JOIN gl_entries e ON e.id = l.entry_id GROUP BY e.source, e.source_id, l.account_id') as $r) {
                    $have[$r['source'] . ':' . $r['source_id']][(int) $r['account_id']] = (float) $r['net'];
                }
                $started = setting('gl_started_on', '') !== '';
                $closed = Finance::closedUntil();
                $firstOpen = $closed !== '' ? date('Y-m-d', strtotime($closed . ' +1 day')) : '';
                $today = date('Y-m-d');
                $n = 0;
                $keys = array_unique(array_merge(array_keys($docs), array_keys($have)));
                foreach ($keys as $key) {
                    $doc = $docs[$key] ?? null;
                    $old = $have[$key] ?? [];
                    $new = $doc['lines'] ?? [];
                    $diff = [];
                    foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $acct) {
                        $v = round(($new[$acct] ?? 0) - ($old[$acct] ?? 0), 2);
                        if (abs($v) >= 0.005) {
                            $diff[$acct] = $v;
                        }
                    }
                    if (!$diff || abs(round(array_sum($diff), 2)) >= 0.005) {
                        continue; // nothing to do (or a record that does not balance: never post half an entry)
                    }
                    $first = !$old;
                    $date = $first ? ($doc['date'] ?? $today) : ($doc['changed'] ?? $today);
                    if ($firstOpen !== '' && $date < $firstOpen && ($started || !$first)) {
                        $date = $firstOpen; // a closed month never changes: the correction goes into the first open day
                    }
                    [$src, $sid] = explode(':', $key);
                    $memo = $doc['memo'] ?? ('Record removed: ' . (self::SOURCES[$src] ?? $src) . ' #' . $sid);
                    if (!$first) {
                        $memo = ($new ? 'Change: ' : 'Cancelled: ') . $memo;
                    }
                    $eid = $d->insert('gl_entries', [
                        'entry_date' => $date, 'source' => $src, 'source_id' => (int) $sid, 'ref' => isset($doc['ref']) ? mb_substr((string) $doc['ref'], 0, 40) : null,
                        'memo' => mb_substr($memo, 0, 255), 'is_change' => $first ? 0 : 1, 'created_by' => Auth::id(),
                    ]);
                    $vals = [];
                    $p = [];
                    foreach ($diff as $acct => $v) {
                        $vals[] = '(?, ?, ?, ?)';
                        array_push($p, $eid, $acct, $v > 0 ? $v : 0, $v < 0 ? -$v : 0);
                    }
                    $d->run('INSERT INTO gl_lines (entry_id, account_id, debit, credit) VALUES ' . implode(', ', $vals), $p);
                    $n++;
                }
                if (!$started) {
                    setting_set('gl_started_on', $today);
                }
                return $n;
            });
        } finally {
            db()->value("SELECT RELEASE_LOCK('nisdas_ledger_sync')");
        }
    }

    /** After a form that may have changed money records: bring the books up to date (never fails the request). */
    public static function afterRequest(): void
    {
        $r = current_route();
        $prefixes = ['fees', 'finance', 'suppliers', 'purchasing', 'petty-cash', 'fixed-assets', 'payroll', 'requisitions', 'ledger', 'pay', 'canteen', 'shop', 'transport'];
        $hit = false;
        foreach ($prefixes as $p) {
            if ($r === $p || str_starts_with($r, $p . '/')) {
                $hit = true;
                break;
            }
        }
        if (!$hit || !self::ready()) {
            return;
        }
        try {
            self::sync();
        } catch (Throwable $e) {
            error_log('[NISDAS] ledger sync: ' . $e->getMessage());
        }
    }

    private static function add(array &$lines, int $acct, float $amount): void
    {
        if ($acct && abs($amount) >= 0.005) {
            $lines[$acct] = round(($lines[$acct] ?? 0) + $amount, 2);
            if (abs($lines[$acct]) < 0.005) {
                unset($lines[$acct]);
            }
        }
    }

    /** What the books should hold for every record: "source:id" => [date, changed, memo, ref, lines]. */
    private static function expected(Database $d): array
    {
        $docs = [];
        $put = function (string $src, int $id, string $date, string $memo, ?string $ref, array $lines, ?string $changed = null) use (&$docs) {
            $docs[$src . ':' . $id] = ['date' => substr($date, 0, 10), 'memo' => $memo, 'ref' => $ref, 'lines' => $lines, 'changed' => $changed ? substr($changed, 0, 10) : null];
        };
        $ar = self::sys('ar');
        $fcAcct = self::sys('family_credit');
        $fund = self::sys('fund');

        // Opening balances of money accounts
        foreach ($d->all('SELECT a.*, (SELECT MIN(txn_date) FROM fin_transactions t WHERE t.account_id = a.id) AS first_txn FROM fin_accounts a') as $a) {
            $l = [];
            self::add($l, self::money((int) $a['id']), (float) $a['opening_balance']);
            self::add($l, $fund, -(float) $a['opening_balance']);
            $put('open', (int) $a['id'], $a['opening_date'] ?: ($a['first_txn'] ?: date('Y-m-d')), 'Opening balance: ' . $a['name'], null, $l);
        }

        // Cash book (fee receipts are taken from the receipts themselves, below)
        $override = ['family_advance' => $fcAcct, 'fee_refund' => $fcAcct, 'supplier_payment' => self::sys('ap'), 'wht_remit' => self::sys('wht'), 'asset_disposal' => self::sys('asset_disposal')];
        foreach ($d->all("SELECT * FROM fin_transactions WHERE source <> 'fee_payment'") as $t) {
            $l = [];
            if ($t['status'] === 'posted') {
                $amt = (float) $t['amount'];
                $m = self::money((int) $t['account_id']);
                $other = $override[$t['source']] ?? self::cat($t['category_id'] ? (int) $t['category_id'] : null, $t['kind'] === 'income' ? 'income' : 'expense');
                switch ($t['kind']) {
                    case 'income':
                    case 'refund':
                        self::add($l, $m, $amt);
                        self::add($l, $other, -$amt);
                        break;
                    case 'expense':
                        self::add($l, $other, $amt);
                        self::add($l, $m, -$amt);
                        break;
                    case 'transfer':
                        self::add($l, self::money((int) $t['to_account_id']), $amt);
                        self::add($l, $m, -$amt);
                        break;
                }
            }
            $put('txn', (int) $t['id'], $t['txn_date'], $t['description'], $t['txn_no'], $l, $t['voided_at']);
        }

        // Fee bills (income when billed), credit notes, receipts, family credit
        $hasSource = (bool) $d->value("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'invoices' AND column_name = 'source'");
        $fees = self::cat((int) $d->value("SELECT id FROM fin_categories WHERE code = 'FEES'"), 'income');
        $shop = self::cat((int) ($d->value("SELECT id FROM fin_categories WHERE code = 'SHOP'") ?: 0), 'income');
        $cnByInv = [];
        $incomeOf = [];
        $cancelled = [];
        if (Family::ready()) {
            foreach ($d->all("SELECT invoice_id, SUM(amount) AS amt FROM credit_notes WHERE status = 'approved' GROUP BY invoice_id") as $r) {
                $cnByInv[(int) $r['invoice_id']] = (float) $r['amt'];
            }
        }
        foreach ($d->all('SELECT i.id, i.invoice_no, i.title, i.issue_date, i.total, i.status, i.updated_at' . ($hasSource ? ', i.source' : ", NULL AS source") . ', p.first_name, p.last_name FROM invoices i JOIN pupils p ON p.id = i.pupil_id') as $i) {
            $inc = ($i['source'] ?? '') === 'shop' ? $shop : $fees;
            $incomeOf[(int) $i['id']] = $inc;
            $l = [];
            if ($i['status'] !== 'cancelled') {
                $amt = (float) $i['total'] + ($cnByInv[(int) $i['id']] ?? 0);
                self::add($l, $ar, $amt);
                self::add($l, $inc, -$amt);
            } else {
                $cancelled[(int) $i['id']] = true;
            }
            $put('inv', (int) $i['id'], $i['issue_date'], $i['title'] . ' — ' . $i['first_name'] . ' ' . $i['last_name'], $i['invoice_no'], $l, $i['status'] === 'cancelled' ? $i['updated_at'] : null);
        }
        if (Family::ready()) {
            foreach ($d->all("SELECT * FROM credit_notes WHERE status = 'approved'") as $c) {
                $l = [];
                if (!isset($cancelled[(int) $c['invoice_id']])) {
                    self::add($l, $incomeOf[(int) $c['invoice_id']] ?? $fees, (float) $c['amount']);
                    self::add($l, $ar, -(float) $c['amount']);
                }
                $put('cn', (int) $c['id'], $c['decided_at'] ?: $c['requested_at'], 'Credit note: ' . $c['reason'], $c['cn_no'], $l);
            }
        }
        $feeTxnAcct = [];
        foreach ($d->all("SELECT source_id, account_id FROM fin_transactions WHERE source = 'fee_payment'") as $r) {
            $feeTxnAcct[(int) $r['source_id']] = (int) $r['account_id'];
        }
        $creditPay = [];
        foreach ($d->all('SELECT pay.*, p.first_name, p.last_name FROM payments pay JOIN pupils p ON p.id = pay.pupil_id') as $p) {
            $l = [];
            if ($p['method'] === 'credit') {
                $creditPay[(int) $p['id']] = true;
            }
            if ($p['status'] === 'completed') {
                $from = $p['method'] === 'credit' ? $fcAcct : self::money($feeTxnAcct[(int) $p['id']] ?? Finance::accountForMethod($p['method']));
                self::add($l, $from, (float) $p['amount']);
                self::add($l, $ar, -(float) $p['amount']);
            }
            $put('pay', (int) $p['id'], $p['paid_on'], 'Fee receipt — ' . $p['first_name'] . ' ' . $p['last_name'] . ($p['method'] === 'credit' ? ' (paid from family credit)' : ''), $p['receipt_no'], $l, $p['reversed_at']);
        }
        if (Family::ready()) {
            foreach ($d->all("SELECT * FROM family_credits WHERE status = 'posted' AND kind IN ('overpayment','credit_note','reversal')") as $f) {
                if ($f['kind'] === 'reversal' && $f['payment_id'] && isset($creditPay[(int) $f['payment_id']])) {
                    continue; // credit returned when a "paid from credit" receipt was reversed: the receipt's own entry covers it
                }
                $l = [];
                self::add($l, $ar, (float) $f['amount']);
                self::add($l, $fcAcct, -(float) $f['amount']);
                $put('fc', (int) $f['id'], $f['entry_date'], 'Family credit: ' . ($f['note'] ?: Family::LEDGER_KINDS[$f['kind']] ?? $f['kind']), $f['receipt_no'], $l);
            }

            // Supplier bills and the tax withheld on their payments
            foreach ($d->all('SELECT b.*, s.name AS supplier FROM supplier_bills b JOIN suppliers s ON s.id = b.supplier_id') as $b) {
                $l = [];
                if ($b['status'] !== 'cancelled') {
                    self::add($l, self::cat($b['category_id'] ? (int) $b['category_id'] : null), (float) $b['amount']);
                    self::add($l, self::sys('ap'), -(float) $b['amount']);
                }
                $put('bill', (int) $b['id'], $b['bill_date'], $b['supplier'] . ': ' . $b['description'], $b['bill_no'], $l);
            }
            foreach ($d->all('SELECT p.*, s.name AS supplier FROM supplier_payments p JOIN supplier_bills b ON b.id = p.bill_id JOIN suppliers s ON s.id = b.supplier_id WHERE p.wht > 0') as $p) {
                $l = [];
                if ($p['status'] === 'posted') {
                    self::add($l, self::sys('ap'), (float) $p['wht']);
                    self::add($l, self::sys('wht'), -(float) $p['wht']);
                }
                $put('spw', (int) $p['id'], $p['pay_date'], 'Withholding tax kept back from ' . $p['supplier'], $p['pv_no'], $l);
            }

            // Fixed assets: cost, disposals and monthly depreciation
            $assets = $d->all('SELECT * FROM fixed_assets');
            $start = (string) ($d->value('SELECT MIN(txn_date) FROM fin_transactions') ?: date('Y-m-d'));
            $firstMonth = null;
            foreach ($assets as $a) {
                $l = [];
                self::add($l, self::sys('fa_cost'), (float) $a['cost']);
                // bought with school money before the system was used: part of the opening fund, not waiting for a payment record
                $credit = $a['funded_by'] !== 'school' ? 'capital_grants' : ($a['purchase_date'] < $start ? 'fund' : 'fa_clear');
                self::add($l, self::sys($credit), -(float) $a['cost']);
                $put('fa', (int) $a['id'], $a['purchase_date'], 'Asset added: ' . $a['name'], $a['asset_no'], $l);
                if (in_array($a['status'], ['disposed', 'lost'], true) && $a['disposed_on']) {
                    $acc = Assets::value($a, $a['disposed_on'])['accum'];
                    $l = [];
                    self::add($l, self::sys('fa_dep'), $acc);
                    self::add($l, self::sys('asset_disposal'), (float) $a['cost'] - $acc);
                    self::add($l, self::sys('fa_cost'), -(float) $a['cost']);
                    $put('fad', (int) $a['id'], $a['disposed_on'], ($a['status'] === 'lost' ? 'Asset lost: ' : 'Asset disposed: ') . $a['name'], $a['asset_no'], $l);
                }
                $m = substr((string) $a['purchase_date'], 0, 7);
                $firstMonth = $firstMonth === null || $m < $firstMonth ? $m : $firstMonth;
            }
            if ($firstMonth) {
                $prev = array_fill_keys(array_keys($assets), 0.0);
                for ($m = $firstMonth; $m <= date('Y-m'); $m = date('Y-m', strtotime($m . '-01 +1 month'))) {
                    $end = date('Y-m-t', strtotime($m . '-01'));
                    $charge = 0.0;
                    foreach ($assets as $k => $a) {
                        $acc = Assets::value($a, $end)['accum'];
                        $charge += $acc - $prev[$k];
                        $prev[$k] = $acc;
                    }
                    $l = [];
                    self::add($l, self::sys('depreciation'), $charge);
                    self::add($l, self::sys('fa_dep'), -$charge);
                    $put('dep', (int) str_replace('-', '', $m), $end, 'Depreciation for ' . date('F Y', strtotime($m . '-01')), null, $l);
                }
            }
        }

        // Approved manual journals
        foreach ($d->all("SELECT * FROM gl_journals WHERE status = 'approved'") as $j) {
            $l = [];
            foreach ($d->all('SELECT * FROM gl_journal_lines WHERE journal_id = ?', [$j['id']]) as $x) {
                self::add($l, (int) $x['account_id'], (float) $x['debit'] - (float) $x['credit']);
            }
            $put('jnl', (int) $j['id'], $j['entry_date'], $j['memo'], $j['jnl_no'], $l);
        }
        return $docs;
    }

    // ------------------------------------------------------------------ reading the books

    /** Link to the record an entry was built from. */
    public static function sourceLink(string $source, int $id): ?string
    {
        switch ($source) {
            case 'txn': return url('finance/transactions/' . $id);
            case 'inv': return url('fees/invoices/' . $id);
            case 'pay': return ($inv = db()->value('SELECT invoice_id FROM payments WHERE id = ?', [$id])) ? url('fees/invoices/' . $inv) : null;
            case 'cn': return ($inv = db()->value('SELECT invoice_id FROM credit_notes WHERE id = ?', [$id])) ? url('fees/invoices/' . $inv) : null;
            case 'fc': return ($g = db()->value('SELECT guardian_id FROM family_credits WHERE id = ?', [$id])) ? url('fees/family/' . $g) : null;
            case 'bill': return url('suppliers/bills/' . $id);
            case 'spw': return ($b = db()->value('SELECT bill_id FROM supplier_payments WHERE id = ?', [$id])) ? url('suppliers/bills/' . $b) : null;
            case 'fa':
            case 'fad': return url('fixed-assets/' . $id);
            case 'jnl': return url('ledger/journals/' . $id);
        }
        return null;
    }

}
