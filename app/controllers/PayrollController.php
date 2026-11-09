<?php
declare(strict_types=1);

/** Staff records and monthly payroll: Bursar prepares → Head approves → Bursar pays (posted to the cash book). */
class PayrollController extends Controller
{
    private const STAFF_FIELDS = [
        'name', 'gender', 'phone', 'position', 'category', 'employment', 'start_date', 'end_date', 'ssnit_no', 'tin', 'pay_method',
        'bank_name', 'bank_branch', 'account_no', 'momo_no', 'basic_salary', 'notes', 'user_id',
        'ghana_card_no', 'qualification', 'ntc_licence_no', 'ntc_expiry',
    ];

    public function index(): void
    {
        $runs = db()->all('SELECT r.*, u.name AS created_name, a.name AS approved_name FROM payroll_runs r LEFT JOIN users u ON u.id = r.created_by LEFT JOIN users a ON a.id = r.approved_by ORDER BY r.period DESC LIMIT 36');
        $staffCount = (int) db()->value('SELECT COUNT(*) FROM staff WHERE is_active = 1');
        $monthly = (float) db()->value('SELECT COALESCE(SUM(basic_salary), 0) FROM staff WHERE is_active = 1');
        $this->view('payroll/index', ['title' => 'Payroll', 'runs' => $runs, 'staffCount' => $staffCount, 'monthly' => $monthly]);
    }

    // ------------------------------------------------------------------ staff records

    public function staff(): void
    {
        $show = (string) ($_GET['show'] ?? 'active');
        $rows = db()->all(
            'SELECT s.*, u.email, (SELECT COALESCE(SUM(CASE WHEN kind = "allowance" THEN amount ELSE 0 END), 0) FROM staff_pay_items WHERE staff_id = s.id) AS allow
             FROM staff s LEFT JOIN users u ON u.id = s.user_id' . ($show === 'all' ? '' : ' WHERE s.is_active = 1') . ' ORDER BY s.category, s.name'
        );
        $this->view('payroll/staff', ['title' => 'Staff records', 'rows' => $rows]);
    }

    public function createStaff(): void
    {
        $this->view('payroll/staff_form', ['title' => 'Add staff member', 's' => null, 'items' => [], 'users' => $this->linkableUsers(null)]);
    }

    public function storeStaff(): void
    {
        $data = $this->staffInput('payroll/staff/create');
        $id = db()->transaction(function (Database $d) use ($data) {
            return $d->insert('staff', $data + ['staff_no' => Payroll::nextStaffNo($d)]);
        });
        audit('staff_created', 'staff', $id, ['basic' => $data['basic_salary']]);
        flash('success', 'Staff record saved. Add any allowances or deductions below.');
        redirect('payroll/staff/' . $id);
    }

    public function editStaff(int $id): void
    {
        $s = db()->one('SELECT * FROM staff WHERE id = ?', [$id]) ?? $this->notFound();
        $items = db()->all('SELECT * FROM staff_pay_items WHERE staff_id = ? ORDER BY kind, name', [$id]);
        $preview = Payroll::compute($s, $items);
        $this->view('payroll/staff_form', ['title' => $s['name'], 's' => $s, 'items' => $items, 'preview' => $preview, 'users' => $this->linkableUsers($id)]);
    }

    public function updateStaff(int $id): void
    {
        $s = db()->one('SELECT * FROM staff WHERE id = ?', [$id]) ?? $this->notFound();
        $data = $this->staffInput('payroll/staff/' . $id, $id);
        $data['is_active'] = !empty($_POST['is_active']) ? 1 : 0;
        db()->update('staff', $data, 'id = ?', [$id]);
        if ((float) $s['basic_salary'] !== (float) $data['basic_salary']) {
            audit('staff_salary_changed', 'staff', $id, ['from' => $s['basic_salary'], 'to' => $data['basic_salary']]);
        } else {
            audit('staff_updated', 'staff', $id);
        }
        flash('success', 'Staff record updated.');
        redirect('payroll/staff/' . $id);
    }

    public function addItem(int $id): void
    {
        db()->one('SELECT id FROM staff WHERE id = ?', [$id]) ?? $this->notFound();
        $kind = ($_POST['kind'] ?? '') === 'deduction' ? 'deduction' : 'allowance';
        $name = trim((string) ($_POST['name'] ?? ''));
        $amount = (string) ($_POST['amount'] ?? '');
        if ($name === '' || !is_numeric($amount) || (float) $amount <= 0 || (float) $amount > 100000) {
            flash('error', 'Give the item a name and an amount above zero.');
            redirect('payroll/staff/' . $id);
        }
        db()->insert('staff_pay_items', [
            'staff_id' => $id, 'kind' => $kind, 'name' => mb_substr($name, 0, 80), 'amount' => round((float) $amount, 2),
            'taxable' => $kind === 'allowance' && empty($_POST['taxable']) ? 0 : 1,
        ]);
        audit('staff_pay_item_added', 'staff', $id, ['kind' => $kind, 'name' => $name, 'amount' => $amount]);
        flash('success', ucfirst($kind) . ' added.');
        redirect('payroll/staff/' . $id);
    }

    public function deleteItem(int $id, int $iid): void
    {
        $it = db()->one('SELECT * FROM staff_pay_items WHERE id = ? AND staff_id = ?', [$iid, $id]) ?? $this->notFound();
        db()->run('DELETE FROM staff_pay_items WHERE id = ?', [$iid]);
        audit('staff_pay_item_removed', 'staff', $id, ['name' => $it['name'], 'amount' => $it['amount']]);
        flash('success', 'Removed "' . $it['name'] . '".');
        redirect('payroll/staff/' . $id);
    }

    // ------------------------------------------------------------------ payroll runs

    public function createRun(): void
    {
        $period = (string) ($_POST['period'] ?? '');
        if (!preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/', $period)) {
            flash('error', 'Choose the month to pay.');
            redirect('payroll');
        }
        if ($existing = db()->value('SELECT id FROM payroll_runs WHERE period = ?', [$period])) {
            flash('info', 'The payroll for ' . Payroll::periodLabel($period) . ' already exists.');
            redirect('payroll/runs/' . $existing);
        }
        if (!db()->value('SELECT 1 FROM staff WHERE is_active = 1 LIMIT 1')) {
            flash('error', 'Add staff records first (Payroll → Staff records).');
            redirect('payroll');
        }
        $id = db()->insert('payroll_runs', ['period' => $period, 'created_by' => Auth::id()]);
        Payroll::build($id);
        audit('payroll_created', 'payroll_runs', $id, ['period' => $period]);
        flash('success', 'Payroll for ' . Payroll::periodLabel($period) . ' prepared. Check it, then send it to the Head for approval.');
        redirect('payroll/runs/' . $id);
    }

    public function showRun(int $id): void
    {
        $run = $this->run($id);
        $slips = db()->all('SELECT ps.*, s.name, s.staff_no, s.position, s.pay_method FROM payslips ps JOIN staff s ON s.id = ps.staff_id WHERE ps.run_id = ? ORDER BY s.name', [$id]);
        $txn = db()->one("SELECT id, txn_no, status FROM fin_transactions WHERE source = 'payroll' AND source_id = ?", [$id]);
        $this->view('payroll/run', [
            'title' => 'Payroll ' . Payroll::periodLabel($run['period']), 'run' => $run, 'slips' => $slips, 'txn' => $txn,
            'accounts' => Finance::accounts(),
        ]);
    }

    public function rebuild(int $id): void
    {
        $run = $this->run($id);
        if ($run['status'] !== 'draft') {
            flash('error', 'Only a draft payroll can be recalculated.');
            redirect('payroll/runs/' . $id);
        }
        Payroll::build($id);
        audit('payroll_rebuilt', 'payroll_runs', $id);
        flash('success', 'Recalculated from the current staff records.');
        redirect('payroll/runs/' . $id);
    }

    public function approve(int $id): void
    {
        $run = $this->run($id);
        if ($run['status'] !== 'draft') {
            flash('error', 'This payroll is not waiting for approval.');
            redirect('payroll/runs/' . $id);
        }
        if ((int) $run['created_by'] === Auth::id()) {
            flash('error', 'Someone other than the person who prepared the payroll must approve it.');
            redirect('payroll/runs/' . $id);
        }
        if ($neg = Payroll::negativeSlips($id)) {
            flash('error', 'Take-home pay is below zero for ' . implode(', ', $neg) . '. Reduce their deductions (for example spread a salary advance over several months), recalculate, then approve.');
            redirect('payroll/runs/' . $id);
        }
        db()->update('payroll_runs', ['status' => 'approved', 'approved_by' => Auth::id(), 'approved_at' => date('Y-m-d H:i:s')], "id = ? AND status = 'draft'", [$id]);
        audit('payroll_approved', 'payroll_runs', $id, ['net' => $run['net']]);
        Notifier::inappPermission('payroll.manage', 'Payroll ' . Payroll::periodLabel($run['period']) . ' approved', 'Net pay ' . Fees::money($run['net']) . ' is ready to be paid.', 'payroll/runs/' . $id);
        flash('success', 'Payroll approved. The Bursar can now pay it.');
        redirect('payroll/runs/' . $id);
    }

    public function reopen(int $id): void
    {
        $run = $this->run($id);
        if ($run['status'] !== 'approved') {
            flash('error', 'Only an approved, unpaid payroll can be sent back.');
            redirect('payroll/runs/' . $id);
        }
        db()->update('payroll_runs', ['status' => 'draft', 'approved_by' => null, 'approved_at' => null], 'id = ?', [$id]);
        audit('payroll_reopened', 'payroll_runs', $id, ['reason' => mb_substr((string) ($_POST['reason'] ?? ''), 0, 200)]);
        flash('success', 'Payroll sent back to draft for corrections.');
        redirect('payroll/runs/' . $id);
    }

    public function pay(int $id): void
    {
        $run = $this->run($id);
        $acct = (int) ($_POST['account_id'] ?? 0);
        $date = (string) ($_POST['paid_on'] ?? '');
        if (!db()->value('SELECT id FROM fin_accounts WHERE id = ? AND is_active = 1', [$acct]) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date > date('Y-m-d')) {
            flash('error', 'Choose the account the salaries were paid from and a date that is not in the future.');
            redirect('payroll/runs/' . $id);
        }
        if ((int) $run['approved_by'] === Auth::id() && Auth::role() !== 'super_admin') {
            flash('error', 'The person who approved the payroll cannot also pay it.');
            redirect('payroll/runs/' . $id);
        }
        if ($run['status'] !== 'approved') {
            flash('error', 'This payroll must be approved before it is paid.');
            redirect('payroll/runs/' . $id);
        }
        if ((float) $run['net'] > Finance::balance($acct) + 0.004 && empty($_POST['allow_overdraw'])) {
            $name = (string) db()->value('SELECT name FROM fin_accounts WHERE id = ?', [$acct]);
            flash('error', 'There is only ' . Fees::money(Finance::balance($acct)) . ' in ' . $name . ', less than the ' . Fees::money($run['net']) . ' net pay. Choose the account the salaries were really paid from, or tick "allow" if its balance is not up to date.');
            redirect('payroll/runs/' . $id);
        }
        try {
            Payroll::markPaid($id, $acct, $date);
        } catch (RuntimeException $e) {
            flash('error', 'This payroll must be approved before it is paid.');
            redirect('payroll/runs/' . $id);
        }
        setting_set('payroll_account', (string) $acct);
        audit('payroll_paid', 'payroll_runs', $id, ['net' => $run['net'], 'account' => $acct]);
        $sent = 0;
        if (!empty($_POST['sms'])) {
            foreach (db()->all('SELECT ps.net, s.name, COALESCE(s.phone, s.momo_no) AS phone, s.pay_method FROM payslips ps JOIN staff s ON s.id = ps.staff_id WHERE ps.run_id = ? AND COALESCE(s.phone, s.momo_no) IS NOT NULL', [$id]) as $s) {
                $body = Notifier::render('salary_paid', [
                    'name' => $s['name'], 'month' => Payroll::periodLabel($run['period']), 'net' => number_format((float) $s['net'], 2),
                    'method' => strtolower(Payroll::PAY_METHOD[$s['pay_method']]),
                ]);
                if ($body && Notifier::sms($s['phone'], $body, ['name' => $s['name'], 'event' => 'salary_paid', 'entity' => 'payroll_runs', 'entity_id' => $id])) {
                    $sent++;
                }
            }
        }
        flash('success', 'Payroll marked as paid and ' . Fees::money($run['net']) . ' recorded in the cash book' . ($sent ? "; $sent staff SMS queued" : '') . '.');
        redirect('payroll/runs/' . $id);
    }

    public function statutory(int $id): void
    {
        $run = $this->run($id);
        $acct = (int) ($_POST['account_id'] ?? 0);
        $date = (string) ($_POST['paid_on'] ?? '');
        if (!db()->value('SELECT id FROM fin_accounts WHERE id = ? AND is_active = 1', [$acct]) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date > date('Y-m-d')) {
            flash('error', 'Choose the account and a date that is not in the future.');
            redirect('payroll/runs/' . $id);
        }
        if ($run['status'] !== 'paid' || $run['statutory_paid_on']) {
            flash('error', 'Pay the salaries first; SSNIT and PAYE can only be recorded once.');
            redirect('payroll/runs/' . $id);
        }
        $stat = round((float) $run['ssnit_employee'] + (float) $run['ssnit_employer'] + (float) $run['paye'], 2);
        if ($stat > Finance::balance($acct) + 0.004 && empty($_POST['allow_overdraw'])) {
            flash('error', 'There is only ' . Fees::money(Finance::balance($acct)) . ' in that account, less than the ' . Fees::money($stat) . ' for SSNIT and PAYE. Choose another account, or tick "allow" if its balance is not up to date.');
            redirect('payroll/runs/' . $id);
        }
        try {
            Payroll::markStatutoryPaid($id, $acct, $date);
        } catch (RuntimeException $e) {
            flash('error', 'Pay the salaries first; SSNIT and PAYE can only be recorded once.');
            redirect('payroll/runs/' . $id);
        }
        audit('payroll_statutory_paid', 'payroll_runs', $id);
        flash('success', 'SSNIT and PAYE payment recorded in the cash book.');
        redirect('payroll/runs/' . $id);
    }

    public function destroyRun(int $id): void
    {
        $run = $this->run($id);
        if ($run['status'] !== 'draft') {
            flash('error', 'Only a draft payroll can be deleted.');
            redirect('payroll/runs/' . $id);
        }
        db()->run('DELETE FROM payroll_runs WHERE id = ?', [$id]);
        audit('payroll_deleted', 'payroll_runs', $id, ['period' => $run['period']]);
        flash('success', 'Draft payroll deleted.');
        redirect('payroll');
    }

    /** Printable payslips (all, or one staff member with ?staff=). */
    public function slips(int $id): void
    {
        $run = $this->run($id);
        $sql = 'SELECT ps.*, s.name, s.staff_no, s.position, s.ssnit_no, s.tin, s.pay_method, s.bank_name, s.account_no, s.momo_no FROM payslips ps JOIN staff s ON s.id = ps.staff_id WHERE ps.run_id = ?';
        $p = [$id];
        if (!empty($_GET['staff'])) {
            $sql .= ' AND ps.staff_id = ?';
            $p[] = (int) $_GET['staff'];
        }
        $this->view('payroll/slips', ['title' => 'Payslips ' . Payroll::periodLabel($run['period']), 'run' => $run, 'slips' => db()->all($sql . ' ORDER BY s.name', $p)], 'layouts/print');
    }

    /** Bank / MoMo payment schedule (print or CSV) and SSNIT/PAYE schedule. */
    public function schedule(int $id): void
    {
        $run = $this->run($id);
        $kind = ($_GET['kind'] ?? 'bank') === 'statutory' ? 'statutory' : 'bank';
        $rows = db()->all('SELECT ps.*, s.name, s.staff_no, s.ssnit_no, s.tin, s.pay_method, s.bank_name, s.bank_branch, s.account_no, s.momo_no FROM payslips ps JOIN staff s ON s.id = ps.staff_id WHERE ps.run_id = ? ORDER BY s.pay_method, s.bank_name, s.name', [$id]);
        if (isset($_GET['csv'])) {
            $out = csv_start(($kind === 'bank' ? 'salary-schedule' : 'ssnit-paye') . '-' . $run['period'] . '.csv');
            if ($kind === 'bank') {
                csv_row($out, ['Staff no.', 'Name', 'Method', 'Bank', 'Branch', 'Account no.', 'MoMo no.', 'Net pay (GHS)']);
                foreach ($rows as $r) {
                    csv_row($out, [$r['staff_no'], $r['name'], Payroll::PAY_METHOD[$r['pay_method']], $r['bank_name'], $r['bank_branch'], $r['account_no'], $r['momo_no'], number_format((float) $r['net'], 2, '.', '')]);
                }
            } else {
                csv_row($out, ['Staff no.', 'Name', 'SSNIT no.', 'TIN / Ghana Card', 'Basic salary', 'SSNIT employee', 'SSNIT employer', 'Total SSNIT', 'Taxable pay', 'PAYE']);
                foreach ($rows as $r) {
                    csv_row($out, [$r['staff_no'], $r['name'], $r['ssnit_no'], $r['tin'], $r['basic'], $r['ssnit_employee'], $r['ssnit_employer'], number_format((float) $r['ssnit_employee'] + (float) $r['ssnit_employer'], 2, '.', ''), $r['taxable'], $r['paye']]);
                }
            }
            csv_end($out);
        }
        $this->view('payroll/schedule', ['title' => ($kind === 'bank' ? 'Salary payment schedule ' : 'SSNIT & PAYE schedule ') . Payroll::periodLabel($run['period']), 'run' => $run, 'rows' => $rows, 'kind' => $kind], 'layouts/print');
    }

    public function settings(): void
    {
        $bands = trim(preg_replace('/\s+/', '', (string) ($_POST['paye_bands'] ?? '')));
        $e = (string) ($_POST['ssnit_employee_rate'] ?? '');
        $r = (string) ($_POST['ssnit_employer_rate'] ?? '');
        if (!Payroll::validBands($bands) || !is_numeric($e) || !is_numeric($r) || (float) $e < 0 || (float) $e > 30 || (float) $r < 0 || (float) $r > 30) {
            flash('error', 'Check the tax bands (pairs like 588:0, ending with 0:35) and the SSNIT rates.');
            redirect('payroll');
        }
        setting_set('paye_bands', $bands);
        setting_set('paye_bands_confirmed', date('Y-m-d'));
        setting_set('ssnit_employee_rate', (string) (float) $e);
        setting_set('ssnit_employer_rate', (string) (float) $r);
        audit('payroll_settings', 'settings', null, ['paye_bands' => $bands, 'ssnit' => "$e/$r"]);
        flash('success', 'Tax and SSNIT rates saved. They apply to payrolls prepared or recalculated from now on.');
        redirect('payroll');
    }

    /** Year summary per staff member (for SSNIT/GRA annual returns and staff tax certificates). */
    public function annual(): void
    {
        $year = (int) ($_GET['year'] ?? date('Y'));
        $year = $year >= 2000 && $year <= 2100 ? $year : (int) date('Y');
        $rows = db()->all(
            "SELECT s.id, s.staff_no, s.name, s.position, s.ssnit_no, s.tin, COUNT(ps.id) AS months,
                    SUM(ps.basic) AS basic, SUM(ps.allowances) AS allowances, SUM(ps.gross) AS gross, SUM(ps.ssnit_employee) AS ssnit_e,
                    SUM(ps.ssnit_employer) AS ssnit_r, SUM(ps.paye) AS paye, SUM(ps.deductions) AS ded, SUM(ps.net) AS net
             FROM payslips ps JOIN payroll_runs r ON r.id = ps.run_id AND r.status = 'paid' AND r.period LIKE ? JOIN staff s ON s.id = ps.staff_id
             GROUP BY s.id ORDER BY s.name",
            [$year . '-%']
        );
        if (isset($_GET['csv'])) {
            $out = csv_start('payroll-' . $year . '.csv', ['Staff no.', 'Name', 'Position', 'SSNIT no.', 'TIN', 'Months paid', 'Basic', 'Allowances', 'Gross', 'SSNIT (staff)', 'SSNIT (school)', 'PAYE', 'Other deductions', 'Net']);
            foreach ($rows as $r) {
                csv_row($out, [$r['staff_no'], $r['name'], $r['position'], $r['ssnit_no'], $r['tin'], $r['months'], $r['basic'], $r['allowances'], $r['gross'], $r['ssnit_e'], $r['ssnit_r'], $r['paye'], $r['ded'], $r['net']]);
            }
            csv_end($out);
        }
        $this->view('payroll/annual', ['title' => "Payroll summary $year", 'year' => $year, 'rows' => $rows], isset($_GET['print']) ? 'layouts/print' : 'layouts/app');
    }

    // ------------------------------------------------------------------ my payslips (any staff member)

    public function mine(): void
    {
        $s = db()->one('SELECT * FROM staff WHERE user_id = ?', [Auth::id()]);
        $slips = $s ? db()->all("SELECT ps.*, r.period, r.status FROM payslips ps JOIN payroll_runs r ON r.id = ps.run_id WHERE ps.staff_id = ? AND r.status IN ('approved','paid') ORDER BY r.period DESC", [$s['id']]) : [];
        $this->view('payroll/mine', ['title' => 'My payslips', 's' => $s, 'slips' => $slips]);
    }

    public function mySlip(int $id): void
    {
        $slip = db()->one(
            "SELECT ps.*, s.name, s.staff_no, s.position, s.ssnit_no, s.tin, s.pay_method, s.bank_name, s.account_no, s.momo_no, s.user_id
             FROM payslips ps JOIN staff s ON s.id = ps.staff_id JOIN payroll_runs r ON r.id = ps.run_id
             WHERE ps.id = ? AND r.status IN ('approved','paid')",
            [$id]
        );
        if (!$slip || (int) $slip['user_id'] !== Auth::id()) {
            $this->notFound();
        }
        $run = db()->one('SELECT * FROM payroll_runs WHERE id = ?', [$slip['run_id']]);
        $this->view('payroll/slips', ['title' => 'Payslip ' . Payroll::periodLabel($run['period']), 'run' => $run, 'slips' => [$slip]], 'layouts/print');
    }

    // ------------------------------------------------------------------ helpers

    private function run(int $id): array
    {
        return db()->one('SELECT r.*, u.name AS created_name, a.name AS approved_name, p.name AS paid_name, f.name AS account_name FROM payroll_runs r
                          LEFT JOIN users u ON u.id = r.created_by LEFT JOIN users a ON a.id = r.approved_by LEFT JOIN users p ON p.id = r.paid_by
                          LEFT JOIN fin_accounts f ON f.id = r.account_id WHERE r.id = ?', [$id]) ?? $this->notFound();
    }

    private function linkableUsers(?int $staffId): array
    {
        return db()->all(
            "SELECT u.id, u.name, u.role FROM users u WHERE u.role NOT IN ('parent','pupil') AND u.is_active = 1
             AND u.id NOT IN (SELECT user_id FROM staff WHERE user_id IS NOT NULL AND id <> ?) ORDER BY u.name",
            [$staffId ?? 0]
        );
    }

    private function staffInput(string $back, ?int $id = null): array
    {
        $in = $this->only(self::STAFF_FIELDS);
        $errors = Validator::check($in, [
            'name' => 'required|max:150', 'position' => 'required|max:80', 'start_date' => 'date', 'end_date' => 'date',
            'gender' => 'in:male,female', 'qualification' => 'in:' . implode(',', array_keys(Payroll::QUALIFICATION)),
            'ntc_licence_no' => 'max:30', 'ntc_expiry' => 'date',
        ]);
        if ($in['ghana_card_no'] !== null) {
            $gc = Payroll::normaliseGhanaCard($in['ghana_card_no']);
            if ($gc === null) {
                $errors['ghana_card_no'] = 'Enter the Ghana Card number like GHA-123456789-0.';
            } else {
                $in['ghana_card_no'] = $gc;
            }
        }
        if ($in['ntc_licence_no'] !== null && $in['ntc_expiry'] === null) $errors['ntc_expiry'] = 'Enter the date the licence expires.';
        if (!isset(Payroll::CATEGORY[$in['category'] ?? ''])) $errors['category'] = 'Choose one.';
        if (!isset(Payroll::EMPLOYMENT[$in['employment'] ?? ''])) $errors['employment'] = 'Choose one.';
        if (!isset(Payroll::PAY_METHOD[$in['pay_method'] ?? ''])) $errors['pay_method'] = 'Choose one.';
        if (!is_numeric($in['basic_salary'] ?? '') || (float) $in['basic_salary'] < 0 || (float) $in['basic_salary'] > 200000) $errors['basic_salary'] = 'Enter the monthly basic salary in GH₵.';
        foreach (['phone', 'momo_no'] as $f) {
            if ($in[$f] !== null) {
                $p = Admission::normalisePhone($in[$f]);
                if (!$p || !preg_match('/^0[2-5]\d{8}$/', $p)) {
                    $errors[$f] = 'Enter a Ghana number like 0244123456.';
                } else {
                    $in[$f] = $p;
                }
            }
        }
        // Contract and probation end dates (migration 039): alerts 60 days before a contract ends
        if (Registers::ready()) {
            foreach (['contract_end' => 'contract', 'probation_end' => 'probation'] as $f => $what) {
                $d = trim((string) ($_POST[$f] ?? ''));
                if ($d !== '' && !Validator::isDate($d)) {
                    $errors[$f] = 'Enter a valid date.';
                } elseif ($d !== '' && $in['start_date'] && $d < $in['start_date']) {
                    $errors[$f] = "The $what end is before the start date.";
                }
                $in[$f] = $d !== '' ? $d : null;
            }
            if ($id && ($old = db()->one('SELECT contract_end FROM staff WHERE id = ?', [$id])) && $old['contract_end'] !== $in['contract_end']) {
                $in['contract_alerted_on'] = null; // renewed: remind again before the new end date
            }
        }
        if (Payroll::hasDailyPay()) {
            $in['pay_basis'] = ($_POST['pay_basis'] ?? '') === 'daily' ? 'daily' : 'monthly';
            $rate = trim(str_replace(',', '', (string) ($_POST['daily_rate'] ?? '')));
            if ($in['pay_basis'] === 'daily' && (!is_numeric($rate) || (float) $rate <= 0 || (float) $rate > 10000)) {
                $errors['daily_rate'] = 'Enter the amount paid per day worked.';
            }
            if ($in['pay_basis'] === 'daily' && $in['user_id'] === null) {
                $errors['user_id'] = 'Link the staff login: days are counted from their sign-in and sign-out.';
            }
            $in['daily_rate'] = is_numeric($rate) && (float) $rate > 0 ? round((float) $rate, 2) : null;
        }
        if (($in['pay_method'] ?? '') === 'bank' && (!$in['bank_name'] || !$in['account_no'])) $errors['account_no'] = 'Bank name and account number are needed for bank payment.';
        if (($in['pay_method'] ?? '') === 'momo' && !$in['momo_no']) $errors['momo_no'] = 'Enter the MoMo number for Mobile Money payment.';
        if ($in['start_date'] && $in['end_date'] && $in['end_date'] < $in['start_date']) $errors['end_date'] = 'The end date is before the start date.';
        if ($in['user_id'] !== null) {
            $uid = (int) $in['user_id'];
            if (!array_filter($this->linkableUsers($id), fn($u) => (int) $u['id'] === $uid)) {
                $errors['user_id'] = 'That login is already linked to another staff record.';
            }
            $in['user_id'] = $uid;
        }
        if ($errors) {
            with_old($_POST, $errors);
            flash('error', 'Please correct the highlighted fields.');
            redirect($back);
        }
        foreach (['name', 'position', 'bank_name', 'bank_branch', 'account_no', 'ssnit_no', 'tin', 'notes', 'ntc_licence_no'] as $f) {
            if ($in[$f] !== null) {
                $in[$f] = mb_substr($in[$f], 0, $f === 'notes' ? 255 : ($f === 'name' ? 150 : 80));
            }
        }
        $in['basic_salary'] = round((float) $in['basic_salary'], 2);
        $in['pays_ssnit'] = !empty($_POST['pays_ssnit']) ? 1 : 0;
        $in['pays_paye'] = !empty($_POST['pays_paye']) ? 1 : 0;
        return $in;
    }
}
