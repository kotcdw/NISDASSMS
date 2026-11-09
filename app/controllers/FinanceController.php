<?php
declare(strict_types=1);

/** Finance: cash book, income & expenditure, budgets, money accounts. */
class FinanceController extends Controller
{
    /** Overview → Finance dashboard: money held, fees, budget, collections, debtors and large payments. */
    public function dashboard(): void
    {
        $terms = db()->all('SELECT t.*, y.name AS year_name FROM terms t JOIN academic_years y ON y.id = t.academic_year_id ORDER BY y.start_date DESC, t.term_no DESC');
        $tid = (int) ($_GET['term'] ?? 0);
        $term = null;
        foreach ($terms as $t) {
            if (($tid && (int) $t['id'] === $tid) || (!$tid && (int) $t['is_current'] === 1)) {
                $term = $t;
            }
        }
        $term ??= $terms[0] ?? null;
        $accountId = (int) ($_GET['account'] ?? 0);
        $this->view('finance/dashboard', ['title' => 'Finance dashboard', 'terms' => $terms, 'term' => $term,
            'd' => FinanceDashboard::data($term, $accountId)]);
    }

    public function index(): void
    {
        $accounts = Finance::accounts();
        foreach ($accounts as &$a) {
            $a['balance'] = Finance::balance((int) $a['id']);
        }
        unset($a);
        $recent = db()->all(
            'SELECT t.*, a.name AS account_name, c.name AS category_name FROM fin_transactions t JOIN fin_accounts a ON a.id = t.account_id
             LEFT JOIN fin_categories c ON c.id = t.category_id ORDER BY t.id DESC LIMIT 8'
        );
        $this->view('finance/index', [
            'title' => 'Finance', 'accounts' => $accounts, 'recent' => $recent,
            'pendingPay' => (int) db()->value("SELECT COUNT(*) FROM requisitions WHERE status = 'approved'"),
        ]);
    }

    // ---------------------------------------------------------------- transactions

    public function transactions(): void
    {
        [$sql, $p] = $this->txnQuery();
        if (isset($_GET['export'])) {
            $rows = db()->all($sql . ' ORDER BY t.txn_date, t.id', $p);
            $out = csv_start('transactions-' . date('Y-m-d') . '.csv', ['Date', 'No.', 'Type', 'Account', 'To account', 'Category', 'Description', 'Paid to / received from', 'Reference', 'Amount (GHS)', 'Status', 'Source']);
            foreach ($rows as $r) {
                csv_row($out, [$r['txn_date'], $r['txn_no'], Finance::KINDS[$r['kind']], $r['account_name'], $r['to_account_name'], $r['category_name'], $r['description'], $r['party'], $r['reference'], number_format((float) $r['amount'], 2, '.', ''), $r['status'], Finance::SOURCES[$r['source']] ?? $r['source']]);
            }
            csv_end($out);
        }
        [$rows, $pager] = paginate($sql . ' ORDER BY t.txn_date DESC, t.id DESC', $p, 50);
        $this->view('finance/transactions', ['title' => 'Transactions', 'rows' => $rows, 'pager' => $pager]);
    }

    public function create(): void
    {
        $kind = (string) ($_GET['kind'] ?? 'expense');
        if (!in_array($kind, ['income', 'expense', 'transfer'], true)) {
            $kind = 'expense';
        }
        if (empty($GLOBALS['__old']) && isset($_GET['account'])) { // e.g. "add a missing entry" from the bank reconciliation
            $GLOBALS['__old'] = array_filter([
                'account_id' => (int) $_GET['account'], 'txn_date' => Validator::isDate((string) ($_GET['date'] ?? '')) ? $_GET['date'] : null,
                'category_id' => isset($_GET['cat']) ? db()->value('SELECT id FROM fin_categories WHERE code = ?', [(string) $_GET['cat']]) : null,
                'description' => isset($_GET['desc']) ? mb_substr((string) $_GET['desc'], 0, 255) : null,
            ]);
        }
        $this->view('finance/form', [
            'title' => ['income' => 'Record income', 'expense' => 'Record an expense', 'transfer' => 'Transfer between accounts'][$kind],
            'kind' => $kind, 'accounts' => Finance::accounts(), 'categories' => Finance::categories($kind === 'transfer' ? null : $kind),
        ]);
    }

    public function store(): void
    {
        $kind = (string) ($_POST['kind'] ?? '');
        $in = $this->only(['txn_date', 'account_id', 'to_account_id', 'category_id', 'amount', 'description', 'party', 'reference']);
        $back = 'finance/new?kind=' . rawurlencode(in_array($kind, ['income', 'expense', 'transfer'], true) ? $kind : 'expense');
        $amount = round((float) str_replace(',', '', (string) $in['amount']), 2);
        $acct = db()->one('SELECT * FROM fin_accounts WHERE id = ? AND is_active = 1', [(int) $in['account_id']]);
        $err = null;
        if (!in_array($kind, ['income', 'expense', 'transfer'], true)) {
            $err = 'Choose income, expense or transfer.';
        } elseif (!$in['txn_date'] || !Validator::isDate((string) $in['txn_date']) || $in['txn_date'] > date('Y-m-d')) {
            $err = 'Enter a valid date (not in the future).';
        } elseif ($closed = Finance::closedError((string) $in['txn_date'])) {
            $err = $closed;
        } elseif (!$acct) {
            $err = 'Choose the account.';
        } elseif ($amount <= 0 || $amount > 10000000) {
            $err = 'Enter an amount above 0.';
        } elseif (!$in['description']) {
            $err = 'Describe the transaction.';
        }
        $cat = null;
        $to = null;
        if (!$err && $kind === 'transfer') {
            $to = db()->one('SELECT * FROM fin_accounts WHERE id = ? AND is_active = 1', [(int) $in['to_account_id']]);
            if (!$to || (int) $to['id'] === (int) $acct['id']) {
                $err = 'Choose a different account to transfer to.';
            }
        } elseif (!$err) {
            $cat = db()->one('SELECT * FROM fin_categories WHERE id = ? AND kind = ? AND is_active = 1', [(int) $in['category_id'], $kind]);
            if (!$cat) {
                $err = 'Choose a category.';
            } elseif ($cat['is_system'] && $cat['code'] === 'FEES') {
                $err = 'School fees are posted automatically from Fees. Record fee payments there so the student\'s account is updated.';
            }
        }
        if (!$err && in_array($kind, ['expense', 'transfer'], true) && $amount > Finance::balance((int) $acct['id']) + 0.004 && empty($_POST['allow_overdraw'])) {
            $err = 'There is only ' . Fees::money(Finance::balance((int) $acct['id'])) . ' in ' . $acct['name'] . '. Tick "allow" if the balance is really lower than the money paid out (for example an opening balance not yet entered).';
        }
        if ($err) {
            with_old($_POST);
            flash('error', $err);
            redirect($back);
        }
        [$file, $ferr] = Upload::store($_FILES['attachment'] ?? ['error' => UPLOAD_ERR_NO_FILE], 'finance');
        if ($ferr) {
            with_old($_POST);
            flash('error', $ferr);
            redirect($back);
        }
        $id = db()->transaction(fn(Database $d) => Finance::post($d, [
            'kind' => $kind, 'txn_date' => $in['txn_date'], 'account_id' => (int) $acct['id'], 'to_account_id' => $to['id'] ?? null,
            'category_id' => $cat['id'] ?? null, 'amount' => $amount, 'description' => $in['description'],
            'party' => $in['party'] ? mb_substr($in['party'], 0, 150) : null, 'reference' => $in['reference'] ? mb_substr($in['reference'], 0, 100) : null,
            'attachment' => $file['stored_name'] ?? null, 'attachment_name' => $file['original_name'] ?? null, 'attachment_mime' => $file['mime'] ?? null,
            'department' => $kind !== 'income' && in_array((string) ($_POST['department'] ?? ''), Finance::departments(false), true) ? $_POST['department'] : null,
        ]));
        audit('fin_txn_created', 'fin_transactions', $id, ['kind' => $kind, 'amount' => $amount]);
        flash('success', Finance::KINDS[$kind] . ' of ' . Fees::money($amount) . ' recorded.');
        redirect(!empty($_POST['another']) ? $back : 'finance/transactions/' . $id);
    }

    public function show(int $id): void
    {
        $t = $this->txn($id);
        $this->view('finance/show', ['title' => 'Transaction ' . $t['txn_no'], 't' => $t]);
    }

    public function file(int $id): void
    {
        $t = $this->txn($id);
        if (!$t['attachment']) {
            $this->notFound();
        }
        Upload::send($t['attachment'], $t['attachment_mime'], $t['attachment_name']);
    }

    public function void(int $id): void
    {
        $t = $this->txn($id);
        $reason = mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 255);
        if ($t['status'] !== 'posted' || $reason === '') {
            flash('error', 'Give a reason for voiding this entry.');
            redirect('finance/transactions/' . $id);
        }
        if ($err = Finance::closedError($t['txn_date'])) {
            flash('error', $err);
            redirect('finance/transactions/' . $id);
        }
        if (!empty($t['recon_id'])) {
            flash('error', 'This entry is ticked on a completed bank reconciliation. Reopen that reconciliation first.');
            redirect('finance/transactions/' . $id);
        }
        if ($t['source'] !== 'manual') {
            flash('error', 'This entry was created by ' . (Finance::SOURCES[$t['source']] ?? $t['source']) . '. Correct it there (for a fee payment: reverse the receipt in Fees).');
            redirect('finance/transactions/' . $id);
        }
        db()->update('fin_transactions', ['status' => 'void', 'void_reason' => $reason, 'voided_by' => Auth::id(), 'voided_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        audit('fin_txn_voided', 'fin_transactions', $id, ['reason' => $reason, 'amount' => $t['amount']]);
        flash('success', 'Entry ' . $t['txn_no'] . ' voided. It stays in the records but no longer counts.');
        redirect('finance/transactions/' . $id);
    }

    // ---------------------------------------------------------------- reports

    public function cashbook(): void
    {
        $accounts = Finance::accounts(false);
        $aid = (int) ($_GET['account'] ?? ($accounts[0]['id'] ?? 0));
        $acct = db()->one('SELECT * FROM fin_accounts WHERE id = ?', [$aid]);
        [$from, $to] = $this->range(date('Y-m-01'), date('Y-m-d'));
        $rows = [];
        $opening = 0.0;
        if ($acct) {
            $opening = Finance::balance($aid, date('Y-m-d', strtotime($from . ' -1 day')));
            $run = $opening;
            foreach (db()->all(
                "SELECT t.*, c.name AS category_name, a.name AS account_name, ta.name AS to_account_name FROM fin_transactions t
                 JOIN fin_accounts a ON a.id = t.account_id LEFT JOIN fin_accounts ta ON ta.id = t.to_account_id
                 LEFT JOIN fin_categories c ON c.id = t.category_id
                 WHERE (t.account_id = ? OR t.to_account_id = ?) AND t.txn_date BETWEEN ? AND ? ORDER BY t.txn_date, t.id",
                [$aid, $aid, $from, $to]
            ) as $t) {
                $eff = Finance::effect($t, $aid);
                $run += $eff;
                $t['in'] = $eff > 0 ? $eff : 0;
                $t['out'] = $eff < 0 ? -$eff : 0;
                $t['run'] = $run;
                $rows[] = $t;
            }
        }
        $data = ['title' => 'Cash book' . ($acct ? ' — ' . $acct['name'] : ''), 'accounts' => $accounts, 'acct' => $acct, 'from' => $from, 'to' => $to, 'rows' => $rows, 'opening' => $opening];
        isset($_GET['print']) ? $this->view('finance/print_cashbook', $data, 'layouts/print') : $this->view('finance/cashbook', $data);
    }

    public function statement(): void
    {
        $termId = (int) ($_GET['term'] ?? 0);
        $term = $termId ? db()->one('SELECT t.*, y.name AS year_name FROM terms t JOIN academic_years y ON y.id = t.academic_year_id WHERE t.id = ?', [$termId]) : null;
        $year = current_year();
        [$from, $to] = $term ? [$term['start_date'], $term['end_date']] : $this->range($year['start_date'] ?? date('Y-01-01'), date('Y-m-d'));
        $totals = Finance::totalsByCategory($from, $to);
        $income = [];
        $expense = [];
        foreach (Finance::categories(null, false) as $c) {
            $amt = $totals[(int) $c['id']] ?? 0.0;
            if (abs($amt) < 0.005) {
                continue;
            }
            $c['amount'] = $amt;
            if ($c['kind'] === 'income') {
                $income[] = $c;
            } else {
                $expense[] = $c;
            }
        }
        $data = [
            'title' => 'Income & expenditure', 'from' => $from, 'to' => $to, 'term' => $term, 'income' => $income, 'expense' => $expense,
            'terms' => db()->all('SELECT t.id, t.name, y.name AS year_name FROM terms t JOIN academic_years y ON y.id = t.academic_year_id ORDER BY t.start_date DESC'),
        ];
        isset($_GET['print']) ? $this->view('finance/print_statement', $data, 'layouts/print') : $this->view('finance/statement', $data);
    }

    public function budget(): void
    {
        $years = db()->all('SELECT * FROM academic_years ORDER BY start_date DESC');
        $yid = (int) ($_GET['year'] ?? (current_year()['id'] ?? ($years[0]['id'] ?? 0)));
        $year = db()->one('SELECT * FROM academic_years WHERE id = ?', [$yid]);
        $this->view('finance/budget', ['title' => 'Budget', 'years' => $years, 'year' => $year, 'rows' => $year ? $this->budgetRows($year) : []]);
    }

    public function saveBudget(): void
    {
        $year = db()->one('SELECT * FROM academic_years WHERE id = ?', [(int) ($_POST['year'] ?? 0)]);
        if (!$year) {
            flash('error', 'Choose an academic year.');
            redirect('finance/budget');
        }
        $cats = array_column(Finance::categories(null, false), 'id');
        $n = 0;
        db()->transaction(function (Database $d) use ($year, $cats, &$n) {
            foreach ((array) ($_POST['amount'] ?? []) as $cid => $raw) {
                $cid = (int) $cid;
                $raw = str_replace(',', '', trim((string) $raw));
                if (!in_array($cid, array_map('intval', $cats), true) || ($raw !== '' && (!is_numeric($raw) || (float) $raw < 0))) {
                    continue;
                }
                if ($raw === '' || (float) $raw == 0.0) {
                    $d->run('DELETE FROM fin_budgets WHERE academic_year_id = ? AND category_id = ?', [$year['id'], $cid]);
                    continue;
                }
                $d->run('INSERT INTO fin_budgets (academic_year_id, category_id, amount) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE amount = VALUES(amount)', [$year['id'], $cid, round((float) $raw, 2)]);
                $n++;
            }
        });
        audit('budget_saved', 'fin_budgets', (int) $year['id'], ['lines' => $n]);
        flash('success', 'Budget for ' . $year['name'] . ' saved.');
        redirect('finance/budget', ['year' => $year['id']]);
    }

    // ---------------------------------------------------------------- setup

    public function setup(): void
    {
        $accounts = Finance::accounts(false);
        foreach ($accounts as &$a) {
            $a['balance'] = Finance::balance((int) $a['id']);
            $a['used'] = (int) db()->value('SELECT COUNT(*) FROM fin_transactions WHERE account_id = ? OR to_account_id = ?', [$a['id'], $a['id']]);
        }
        unset($a);
        $this->view('finance/setup', ['title' => 'Finance setup', 'accounts' => $accounts, 'categories' => Finance::categories(null, false)]);
    }

    public function saveAccount(int $id = 0): void
    {
        $in = $this->only(['name', 'kind', 'account_no', 'opening_balance', 'opening_date', 'sort_order']);
        $ob = round((float) str_replace(',', '', (string) ($in['opening_balance'] ?? '0')), 2);
        if (!$in['name'] || !isset(Finance::ACCOUNT_KINDS[(string) $in['kind']]) || ($in['opening_date'] && !Validator::isDate($in['opening_date']))
            || db()->value('SELECT id FROM fin_accounts WHERE name = ? AND id <> ?', [$in['name'], $id])) {
            flash('error', 'Give the account a unique name and type (and a valid opening date).');
            redirect('finance/setup');
        }
        $data = ['name' => mb_substr($in['name'], 0, 80), 'kind' => $in['kind'], 'account_no' => $in['account_no'] ? mb_substr($in['account_no'], 0, 60) : null,
                 'opening_balance' => $ob, 'opening_date' => $in['opening_date'], 'sort_order' => (int) $in['sort_order'],
                 'is_active' => $id && !isset($_POST['is_active']) ? 0 : 1];
        if ($id) {
            $old = db()->one('SELECT * FROM fin_accounts WHERE id = ?', [$id]) ?? $this->notFound();
            db()->update('fin_accounts', $data, 'id = ?', [$id]);
            audit('fin_account_saved', 'fin_accounts', $id, ['opening_from' => $old['opening_balance'], 'opening_to' => $ob]);
        } else {
            $id = db()->insert('fin_accounts', $data);
            audit('fin_account_saved', 'fin_accounts', $id, $data);
        }
        flash('success', 'Account saved.');
        redirect('finance/setup');
    }

    public function saveCategory(int $id = 0): void
    {
        $in = $this->only(['kind', 'code', 'name', 'sort_order']);
        $in['code'] = strtoupper((string) $in['code']);
        $old = $id ? (db()->one('SELECT * FROM fin_categories WHERE id = ?', [$id]) ?? $this->notFound()) : null;
        if ($old) {
            $in['kind'] = $old['kind']; // kind cannot change once used
        }
        if (!$in['name'] || !in_array($in['kind'], ['income', 'expense'], true) || !preg_match('/^[A-Z0-9_-]{1,12}$/', $in['code'])
            || db()->value('SELECT id FROM fin_categories WHERE (code = ? OR (kind = ? AND name = ?)) AND id <> ?', [$in['code'], $in['kind'], $in['name'], $id])) {
            flash('error', 'Give the category a unique name and a short code (letters/numbers).');
            redirect('finance/setup', ['tab' => 'categories']);
        }
        $data = ['kind' => $in['kind'], 'code' => $old && $old['is_system'] ? $old['code'] : $in['code'], 'name' => mb_substr($in['name'], 0, 80), 'sort_order' => (int) $in['sort_order'],
                 'is_active' => ($old && $old['is_system']) || !$id || isset($_POST['is_active']) ? 1 : 0];
        if ($id) {
            db()->update('fin_categories', $data, 'id = ?', [$id]);
        } else {
            $id = db()->insert('fin_categories', $data);
        }
        audit('fin_category_saved', 'fin_categories', $id, $data);
        flash('success', 'Category saved.');
        redirect('finance/setup', ['tab' => 'categories']);
    }

    public function saveSettings(): void
    {
        $ids = array_map('intval', array_column(Finance::accounts(false), 'id'));
        foreach (['fin_acct_cash', 'fin_acct_bank', 'fin_acct_momo', 'fin_acct_online'] as $k) {
            $v = (int) ($_POST[$k] ?? 0);
            if (in_array($v, $ids, true)) {
                setting_set($k, (string) $v);
            }
        }
        if (can('finance.budget') || can('app.configure')) {
            setting_set('req_second_threshold', (string) max(0, round((float) ($_POST['req_second_threshold'] ?? 5000), 2)));
            setting_set('req_require_quote_above', (string) max(0, round((float) ($_POST['req_require_quote_above'] ?? 1000), 2)));
            foreach (['pur_quote3_above' => 5000, 'pur_pay_approval_above' => 5000] as $k => $def) {
                if (isset($_POST[$k])) {
                    setting_set($k, (string) max(0, round((float) str_replace(',', '', (string) $_POST[$k]), 2)));
                }
            }
        }
        audit('finance_settings_saved', 'settings');
        flash('success', 'Settings saved.');
        redirect('finance/setup', ['tab' => 'settings']);
    }

    // ---------------------------------------------------------------- department caps & reserves

    public function departments(): void
    {
        $this->needsTable('fin_dept_caps');
        $terms = db()->all('SELECT t.id, t.name, y.name AS year_name, t.start_date, t.end_date FROM terms t JOIN academic_years y ON y.id = t.academic_year_id ORDER BY t.start_date DESC');
        $term = DeptBudget::term((int) ($_GET['term'] ?? 0));
        $this->view('finance/departments', [
            'title' => 'Department budget caps', 'terms' => $terms, 'term' => $term, 'rows' => $term ? DeptBudget::rows($term) : [],
            'log' => $term ? db()->all('SELECT l.*, u.name AS by_name FROM fin_dept_cap_log l LEFT JOIN users u ON u.id = l.user_id WHERE l.term_id = ? ORDER BY l.id DESC LIMIT 30', [$term['id']]) : [],
            'all' => db()->all('SELECT * FROM fin_departments ORDER BY sort_order, name'),
        ]);
    }

    public function saveDepartments(): void
    {
        $this->needsTable('fin_dept_caps');
        $term = DeptBudget::term((int) ($_POST['term'] ?? 0));
        if (!$term) {
            flash('error', 'Choose a term.');
            redirect('finance/budget/departments');
        }
        $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 255);
        $caps = DeptBudget::caps((int) $term['id']);
        $n = 0;
        foreach ((array) ($_POST['cap'] ?? []) as $dept => $v) {
            $dept = mb_substr((string) $dept, 0, 80);
            $v = trim(str_replace(',', '', (string) $v));
            if ($v === '') {
                if (isset($caps[$dept])) {
                    DeptBudget::removeCap((int) $term['id'], $dept);
                    $n++;
                }
                continue;
            }
            $amt = round((float) $v, 2);
            if ($amt < 0 || $amt > 100000000) {
                continue;
            }
            if (!isset($caps[$dept]) || abs($caps[$dept] - $amt) >= 0.005) {
                DeptBudget::setCap((int) $term['id'], $dept, $amt, isset($caps[$dept]) && $amt > $caps[$dept] ? 'raise' : 'set', $note);
                $n++;
            }
        }
        audit('dept_caps_saved', 'fin_dept_caps', (int) $term['id'], ['changed' => $n]);
        flash('success', $n ? 'Caps saved for ' . $term['name'] . ' (' . $n . ' changed).' : 'Nothing changed.');
        redirect('finance/budget/departments', ['term' => $term['id']]);
    }

    public function moveCap(): void
    {
        $this->needsTable('fin_dept_caps');
        $term = DeptBudget::term((int) ($_POST['term'] ?? 0));
        $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 200);
        $amt = round((float) str_replace(',', '', (string) ($_POST['amount'] ?? '0')), 2);
        $err = !$term ? 'Choose a term.' : ($note === '' ? 'Say why the money is moved.' : DeptBudget::move((int) $term['id'], (string) ($_POST['from'] ?? ''), (string) ($_POST['to'] ?? ''), $amt, $note));
        if ($err) {
            flash('error', $err);
        } else {
            audit('dept_cap_moved', 'fin_dept_caps', (int) $term['id'], ['from' => $_POST['from'], 'to' => $_POST['to'], 'amount' => $amt]);
            flash('success', Fees::money($amt) . ' moved from ' . $_POST['from'] . ' to ' . $_POST['to'] . '.');
        }
        redirect('finance/budget/departments', ['term' => $term['id'] ?? 0]);
    }

    public function saveDepartmentList(): void
    {
        $this->needsTable('fin_departments');
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 80);
        if ($id = (int) ($_POST['id'] ?? 0)) {
            db()->update('fin_departments', ['is_active' => empty($_POST['is_active']) ? 0 : 1], 'id = ?', [$id]);
            flash('success', 'Department updated.');
        } elseif ($name !== '' && !db()->value('SELECT id FROM fin_departments WHERE name = ?', [$name])) {
            db()->insert('fin_departments', ['name' => $name, 'sort_order' => (int) db()->value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM fin_departments')]);
            audit('department_added', 'fin_departments', null, ['name' => $name]);
            flash('success', 'Department "' . $name . '" added.');
        } else {
            flash('error', $name === '' ? 'Type the department name.' : 'That department is already listed.');
        }
        redirect('finance/budget/departments');
    }

    public function reserves(): void
    {
        $year = current_year();
        $income = 0.0;
        $plan = 0.0;
        if ($year) {
            $income = (float) db()->value("SELECT COALESCE(SUM(amount), 0) FROM fin_transactions WHERE status = 'posted' AND kind = 'income' AND txn_date BETWEEN ? AND ?", [$year['start_date'], $year['end_date']]);
            $plan = (float) db()->value("SELECT COALESCE(SUM(b.amount), 0) FROM fin_budgets b JOIN fin_categories c ON c.id = b.category_id WHERE c.kind = 'income' AND b.academic_year_id = ?", [$year['id']]);
        }
        $spend = (float) db()->value("SELECT COALESCE(SUM(CASE WHEN kind = 'refund' THEN -amount ELSE amount END), 0) FROM fin_transactions WHERE status = 'posted' AND kind IN ('expense','refund') AND txn_date >= (CURDATE() - INTERVAL 3 MONTH)");
        $avg = round($spend / 3, 2);
        $cash = 0.0;
        foreach (Finance::accounts() as $a) {
            $cash += Finance::balance((int) $a['id']);
        }
        $this->view('finance/reserves', ['title' => 'Reserves', 'year' => $year, 'income' => $income, 'plan' => $plan, 'avg' => $avg, 'cash' => round($cash, 2)]);
    }

    public function saveReserves(): void
    {
        foreach (['reserve_contingency_pct', 'reserve_capital_pct'] as $k) {
            $v = trim((string) ($_POST[$k] ?? ''));
            setting_set($k, $v === '' ? '' : (string) max(0, min(50, round((float) $v, 1))));
        }
        setting_set('reserve_min_months', (string) max(0, min(12, (int) ($_POST['reserve_min_months'] ?? 3))));
        audit('reserves_saved', 'settings');
        flash('success', 'Reserves saved.');
        redirect('finance/budget/reserves');
    }

    // ---------------------------------------------------------------- helpers

    /** Budget vs actual rows for a year. */
    private function budgetRows(?array $year = null): array
    {
        $year = $year ?: current_year();
        if (!$year) {
            return [];
        }
        $budgets = array_column(db()->all('SELECT category_id, amount FROM fin_budgets WHERE academic_year_id = ?', [$year['id']]), 'amount', 'category_id');
        $actual = Finance::totalsByCategory($year['start_date'], $year['end_date']);
        $isCurrent = (int) $year['id'] === (int) (current_year()['id'] ?? 0);
        $committed = $isCurrent ? Finance::committedByCategory() : [];
        $rows = [];
        foreach (Finance::categories(null, false) as $c) {
            $b = isset($budgets[$c['id']]) ? (float) $budgets[$c['id']] : null;
            $act = $actual[(int) $c['id']] ?? 0.0;
            $com = $committed[(int) $c['id']] ?? 0.0;
            $pct = $b ? round(($act + ($c['kind'] === 'expense' ? $com : 0)) * 100 / $b) : null;
            $rows[] = $c + ['budget' => $b, 'actual' => $act, 'committed' => $com, 'pct' => $pct];
        }
        return $rows;
    }

    private function txnQuery(): array
    {
        $f = [
            'account' => (int) ($_GET['account'] ?? 0), 'kind' => (string) ($_GET['kind'] ?? ''), 'category' => (int) ($_GET['category'] ?? 0),
            'source' => (string) ($_GET['source'] ?? ''), 'q' => trim((string) ($_GET['q'] ?? '')), 'status' => (string) ($_GET['status'] ?? ''),
        ];
        [$f['from'], $f['to']] = $this->range('2000-01-01', date('Y-m-d')); // All entries: everything unless a link asks for dates
        $sql = 'SELECT t.*, a.name AS account_name, ta.name AS to_account_name, c.name AS category_name, u.name AS created_by_name
                FROM fin_transactions t JOIN fin_accounts a ON a.id = t.account_id LEFT JOIN fin_accounts ta ON ta.id = t.to_account_id
                LEFT JOIN fin_categories c ON c.id = t.category_id LEFT JOIN users u ON u.id = t.created_by
                WHERE t.txn_date BETWEEN ? AND ?';
        $p = [$f['from'], $f['to']];
        if ($f['account']) {
            $sql .= ' AND (t.account_id = ? OR t.to_account_id = ?)';
            array_push($p, $f['account'], $f['account']);
        }
        if (isset(Finance::KINDS[$f['kind']])) {
            $sql .= ' AND t.kind = ?';
            $p[] = $f['kind'];
        }
        if ($f['category']) {
            $sql .= ' AND t.category_id = ?';
            $p[] = $f['category'];
        }
        if (isset(Finance::SOURCES[$f['source']])) {
            $sql .= ' AND t.source = ?';
            $p[] = $f['source'];
        }
        if (in_array($f['status'], ['posted', 'void'], true)) {
            $sql .= ' AND t.status = ?';
            $p[] = $f['status'];
        }
        if ($f['q'] !== '') {
            $sql .= ' AND (t.txn_no LIKE ? OR t.description LIKE ? OR t.party LIKE ? OR t.reference LIKE ?)';
            array_push($p, "%{$f['q']}%", "%{$f['q']}%", "%{$f['q']}%", "%{$f['q']}%");
        }
        return [$sql, $p, $f];
    }

    /** Date range from ?from=&to= with defaults; swapped if reversed. */
    private function range(string $defFrom, string $defTo): array
    {
        $from = (string) ($_GET['from'] ?? $defFrom);
        $to = (string) ($_GET['to'] ?? $defTo);
        $from = Validator::isDate($from) ? $from : $defFrom;
        $to = Validator::isDate($to) ? $to : $defTo;
        return $from <= $to ? [$from, $to] : [$to, $from];
    }

    private function txn(int $id): array
    {
        return db()->one(
            'SELECT t.*, a.name AS account_name, ta.name AS to_account_name, c.name AS category_name, u.name AS created_by_name, v.name AS voided_by_name
             FROM fin_transactions t JOIN fin_accounts a ON a.id = t.account_id LEFT JOIN fin_accounts ta ON ta.id = t.to_account_id
             LEFT JOIN fin_categories c ON c.id = t.category_id LEFT JOIN users u ON u.id = t.created_by LEFT JOIN users v ON v.id = t.voided_by
             WHERE t.id = ?',
            [$id]
        ) ?? $this->notFound();
    }

}
