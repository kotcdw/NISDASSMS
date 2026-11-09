<?php
declare(strict_types=1);

/** Petty cash boxes (imprest): vouchers for small spends and top-ups back to the float. */
class PettyController extends Controller
{
    private function needsReady(): void
    {
        if (!Petty::ready()) {
            $this->needsTable('fin_period_log'); // shows the "update the database" page
        }
    }

    public function index(): void
    {
        $this->needsReady();
        $all = can('finance.view');
        $boxes = Petty::boxes($all);
        if (!$all && count($boxes) === 1) {
            redirect('petty-cash/' . $boxes[0]['id']);
        }
        if (!$all && !$boxes) {
            $this->forbidden();
        }
        $this->view('petty/index', [
            'title' => 'Petty cash', 'boxes' => $boxes, 'all' => $all,
            'staff' => can('finance.manage') ? db()->all("SELECT id, name FROM users WHERE is_active = 1 AND role NOT IN ('parent','pupil') ORDER BY name") : [],
            'accounts' => Finance::accounts(true, false), 'open' => isset($_GET['new']),
        ]);
    }

    public function show(int $id): void
    {
        $this->needsReady();
        $box = Petty::box($id) ?? $this->notFound();
        if (!Petty::canSee($box)) {
            $this->forbidden();
        }
        $box['balance'] = Finance::balance($id);
        $this->view('petty/show', [
            'title' => $box['name'], 'box' => $box, 'unclaimed' => Petty::unclaimedRows($id),
            'history' => db()->all(
                "SELECT t.*, c.name AS category_name, u.name AS by_name FROM fin_transactions t LEFT JOIN fin_categories c ON c.id = t.category_id LEFT JOIN users u ON u.id = t.created_by
                 WHERE (t.account_id = ? OR t.to_account_id = ?) AND (t.kind = 'transfer' OR t.petty_topup_id IS NOT NULL OR t.status = 'void') ORDER BY t.txn_date DESC, t.id DESC LIMIT 80",
                [$id, $id]
            ),
            'categories' => Finance::categories('expense'), 'accounts' => Finance::accounts(true, false),
            'staff' => can('finance.manage') ? db()->all("SELECT id, name FROM users WHERE is_active = 1 AND role NOT IN ('parent','pupil') ORDER BY name") : [],
        ]);
    }

    /** Set up a new box: create it and issue the float from a main account. */
    public function store(): void
    {
        $this->needsReady();
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 80);
        $float = round((float) str_replace(',', '', (string) ($_POST['float_amount'] ?? '0')), 2);
        $limit = round((float) str_replace(',', '', (string) ($_POST['spend_limit'] ?? '0')), 2);
        $cust = (int) ($_POST['custodian_id'] ?? 0);
        $from = db()->one("SELECT * FROM fin_accounts WHERE id = ? AND is_active = 1 AND kind <> 'petty'", [(int) ($_POST['from_account'] ?? 0)]);
        $err = null;
        if ($name === '' || db()->value('SELECT id FROM fin_accounts WHERE name = ?', [$name])) {
            $err = $name === '' ? 'Name the petty cash box.' : 'An account with that name already exists.';
        } elseif (!db()->value("SELECT id FROM users WHERE id = ? AND is_active = 1 AND role NOT IN ('parent','pupil')", [$cust])) {
            $err = 'Choose the staff member who holds the box.';
        } elseif ($float <= 0 || $float > 100000) {
            $err = 'Enter the float (the amount the box is kept at).';
        } elseif (!$from) {
            $err = 'Choose the account the float is taken from.';
        } elseif (Finance::balance((int) $from['id']) + 0.004 < $float) {
            $err = $from['name'] . ' has only ' . Fees::money(Finance::balance((int) $from['id'])) . '.';
        } elseif ($e = Finance::closedError(date('Y-m-d'))) {
            $err = $e;
        }
        if ($err) {
            with_old($_POST);
            flash('error', $err);
            redirect('petty-cash?new=1');
        }
        $id = db()->transaction(function (Database $d) use ($name, $float, $limit, $cust, $from) {
            $id = $d->insert('fin_accounts', ['name' => $name, 'kind' => 'petty', 'opening_balance' => 0, 'opening_date' => date('Y-m-d'), 'custodian_id' => $cust, 'float_amount' => $float, 'spend_limit' => $limit > 0 ? $limit : null, 'sort_order' => 50]);
            Finance::post($d, [
                'kind' => 'transfer', 'txn_date' => date('Y-m-d'), 'account_id' => $from['id'], 'to_account_id' => $id, 'amount' => $float,
                'description' => 'Petty cash float issued: ' . $name, 'source' => 'petty_topup',
            ]);
            return $id;
        });
        audit('petty_box_created', 'fin_accounts', $id, ['float' => $float]);
        Notifier::inapp([$cust], 'You now hold a petty cash box', $name . ' — float ' . Fees::money($float) . '. Record every spend as a voucher.', 'petty-cash/' . $id);
        flash('success', $name . ' set up with a float of ' . Fees::money($float) . '.');
        redirect('petty-cash/' . $id);
    }

    public function update(int $id): void
    {
        $this->needsReady();
        $box = Petty::box($id) ?? $this->notFound();
        $float = round((float) str_replace(',', '', (string) ($_POST['float_amount'] ?? '0')), 2);
        $limit = round((float) str_replace(',', '', (string) ($_POST['spend_limit'] ?? '0')), 2);
        $cust = (int) ($_POST['custodian_id'] ?? 0);
        if ($float <= 0 || !db()->value("SELECT id FROM users WHERE id = ? AND is_active = 1", [$cust])) {
            flash('error', 'Enter the float and choose who holds the box.');
            redirect('petty-cash/' . $id);
        }
        db()->update('fin_accounts', ['float_amount' => $float, 'spend_limit' => $limit > 0 ? $limit : null, 'custodian_id' => $cust, 'is_active' => !empty($_POST['is_active']) ? 1 : 0], 'id = ?', [$id]);
        audit('petty_box_updated', 'fin_accounts', $id, ['float' => $float, 'custodian' => $cust]);
        if ($cust !== (int) $box['custodian_id']) {
            Notifier::inapp([$cust], 'You now hold a petty cash box', $box['name'] . '. Count the cash with the previous holder and sign the hand-over.', 'petty-cash/' . $id);
        }
        flash('success', 'Petty cash box updated.');
        redirect('petty-cash/' . $id);
    }

    public function spend(int $id): void
    {
        $this->needsReady();
        $box = Petty::box($id) ?? $this->notFound();
        if (!Petty::canUse($box)) {
            $this->forbidden();
        }
        $amount = round((float) str_replace(',', '', (string) ($_POST['amount'] ?? '0')), 2);
        $date = (string) ($_POST['txn_date'] ?? date('Y-m-d'));
        $cat = db()->one("SELECT * FROM fin_categories WHERE id = ? AND kind = 'expense' AND is_active = 1", [(int) ($_POST['category_id'] ?? 0)]);
        $desc = mb_substr(trim((string) ($_POST['description'] ?? '')), 0, 255);
        $bal = Finance::balance($id);
        $err = null;
        if ($amount <= 0) {
            $err = 'Enter the amount spent.';
        } elseif ($box['spend_limit'] && $amount > (float) $box['spend_limit'] + 0.004) {
            $err = 'A petty cash voucher can be at most ' . Fees::money($box['spend_limit']) . '. Larger spends need a requisition.';
        } elseif ($amount > $bal + 0.004) {
            $err = 'The box has only ' . Fees::money($bal) . '. Ask the Bursar for a top-up.';
        } elseif (!Validator::isDate($date) || $date > date('Y-m-d') || $date < date('Y-m-d', strtotime('-31 days'))) {
            $err = 'Enter the date of the spend (within the last month).';
        } elseif ($e = Finance::closedError($date)) {
            $err = $e;
        } elseif (!$cat) {
            $err = 'Choose what it was for.';
        } elseif ($desc === '') {
            $err = 'Describe what was bought.';
        }
        if ($err) {
            with_old($_POST);
            flash('error', $err);
            redirect('petty-cash/' . $id);
        }
        [$file, $ferr] = Upload::store($_FILES['attachment'] ?? ['error' => UPLOAD_ERR_NO_FILE], 'finance');
        if ($ferr) {
            with_old($_POST);
            flash('error', $ferr);
            redirect('petty-cash/' . $id);
        }
        $tid = db()->transaction(fn(Database $d) => Finance::post($d, [
            'txn_no' => Fees::nextNumber($d, 'PCV'), 'kind' => 'expense', 'txn_date' => $date, 'account_id' => $id, 'category_id' => $cat['id'],
            'amount' => $amount, 'description' => $desc, 'party' => mb_substr(trim((string) ($_POST['party'] ?? '')), 0, 150) ?: null,
            'reference' => mb_substr(trim((string) ($_POST['reference'] ?? '')), 0, 100) ?: null, 'source' => 'petty_spend',
            'attachment' => $file['stored_name'] ?? null, 'attachment_name' => $file['original_name'] ?? null, 'attachment_mime' => $file['mime'] ?? null,
            'department' => in_array((string) ($_POST['department'] ?? ''), Finance::departments(false), true) ? $_POST['department'] : null,
        ]));
        audit('petty_spend', 'fin_transactions', $tid, ['amount' => $amount]);
        $no = db()->value('SELECT txn_no FROM fin_transactions WHERE id = ?', [$tid]);
        flash('success', 'Voucher ' . $no . ' for ' . Fees::money($amount) . ' recorded.');
        if ($box['float_amount'] && Finance::balance($id) < 0.25 * (float) $box['float_amount']) {
            Notifier::inappPermission('finance.manage', 'Petty cash running low', $box['name'] . ' has ' . Fees::money(Finance::balance($id)) . ' left — top it up.', 'petty-cash/' . $id);
        }
        redirect('petty-cash/' . $id, ['pcv' => $tid]);
    }

    /** Cancel a voucher that is not yet claimed in a top-up. */
    public function voidSpend(int $id, int $txn): void
    {
        $this->needsReady();
        $t = db()->one("SELECT * FROM fin_transactions WHERE id = ? AND account_id = ? AND source = 'petty_spend'", [$txn, $id]) ?? $this->notFound();
        $reason = mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 255);
        $err = null;
        if ($t['status'] !== 'posted' || $t['petty_topup_id']) {
            $err = 'Only a voucher not yet claimed in a top-up can be cancelled.';
        } elseif ($reason === '') {
            $err = 'Give a reason.';
        } else {
            $err = Finance::closedError($t['txn_date']);
        }
        if (!$err) {
            db()->update('fin_transactions', ['status' => 'void', 'void_reason' => $reason, 'voided_by' => Auth::id(), 'voided_at' => date('Y-m-d H:i:s')], 'id = ?', [$txn]);
            audit('petty_spend_void', 'fin_transactions', $txn, ['reason' => $reason]);
        }
        flash($err ? 'error' : 'success', $err ?? 'Voucher ' . $t['txn_no'] . ' cancelled; the money is back in the box.');
        redirect('petty-cash/' . $id);
    }

    /** Top the box back up to its float: covers the vouchers since the last top-up. */
    public function topup(int $id): void
    {
        $this->needsReady();
        $box = Petty::box($id) ?? $this->notFound();
        if ((int) $box['custodian_id'] === (int) Auth::id() && Auth::role() !== 'super_admin') {
            flash('error', 'The holder of the box cannot top it up. The Bursar or Head must check the vouchers and do it.');
            redirect('petty-cash/' . $id);
        }
        $from = db()->one("SELECT * FROM fin_accounts WHERE id = ? AND is_active = 1 AND kind <> 'petty'", [(int) ($_POST['from_account'] ?? 0)]);
        $rows = Petty::unclaimedRows($id);
        $spent = round(array_sum(array_map(fn($t) => (float) $t['amount'], $rows)), 2);
        $need = round((float) $box['float_amount'] - Finance::balance($id), 2);
        $err = null;
        if (!$rows || $need <= 0) {
            $err = 'Nothing to top up — the box is at its float.';
        } elseif (!$from) {
            $err = 'Choose the account the cash comes from.';
        } elseif (Finance::balance((int) $from['id']) + 0.004 < $need) {
            $err = $from['name'] . ' has only ' . Fees::money(Finance::balance((int) $from['id'])) . '.';
        } else {
            $err = Finance::closedError(date('Y-m-d'));
        }
        if ($err) {
            flash('error', $err);
            redirect('petty-cash/' . $id);
        }
        $tid = db()->transaction(function (Database $d) use ($box, $from, $need, $rows, $spent) {
            $tid = Finance::post($d, [
                'txn_no' => Fees::nextNumber($d, 'IMP'), 'kind' => 'transfer', 'txn_date' => date('Y-m-d'), 'account_id' => $from['id'], 'to_account_id' => $box['id'], 'amount' => $need,
                'description' => 'Petty cash top-up: ' . $box['name'] . ' (' . count($rows) . ' vouchers, ' . number_format($spent, 2) . ')', 'source' => 'petty_topup',
            ]);
            $ids = implode(',', array_map(fn($t) => (int) $t['id'], $rows));
            $d->run("UPDATE fin_transactions SET petty_topup_id = ? WHERE id IN ($ids)", [$tid]);
            return $tid;
        });
        audit('petty_topup', 'fin_accounts', $id, ['amount' => $need]);
        flash('success', 'Topped up with ' . Fees::money($need) . '. The box is back to its float of ' . Fees::money($box['float_amount']) . '.');
        redirect('petty-cash/' . $id, ['claim' => $tid]);
    }

    /** Print a voucher (?pcv=) or a top-up claim (?claim=). */
    public function printDoc(int $id): void
    {
        $this->needsReady();
        $box = Petty::box($id) ?? $this->notFound();
        if (!Petty::canSee($box)) {
            $this->forbidden();
        }
        if (!empty($_GET['claim'])) {
            $t = db()->one("SELECT t.*, u.name AS by_name, a.name AS from_name FROM fin_transactions t LEFT JOIN users u ON u.id = t.created_by JOIN fin_accounts a ON a.id = t.account_id WHERE t.id = ? AND t.to_account_id = ?", [(int) $_GET['claim'], $id]) ?? $this->notFound();
            $rows = db()->all('SELECT t.*, c.name AS category_name FROM fin_transactions t LEFT JOIN fin_categories c ON c.id = t.category_id WHERE t.petty_topup_id = ? ORDER BY t.txn_date, t.id', [$t['id']]);
            $this->view('petty/print_claim', ['title' => 'Petty cash top-up ' . $t['txn_no'], 'box' => $box, 't' => $t, 'rows' => $rows], 'layouts/print');
            return;
        }
        $t = db()->one("SELECT t.*, c.name AS category_name, u.name AS by_name FROM fin_transactions t LEFT JOIN fin_categories c ON c.id = t.category_id LEFT JOIN users u ON u.id = t.created_by WHERE t.id = ? AND t.account_id = ? AND t.kind = 'expense'", [(int) ($_GET['pcv'] ?? 0), $id]) ?? $this->notFound();
        $this->view('petty/print_voucher', ['title' => 'Petty cash voucher ' . $t['txn_no'], 'box' => $box, 't' => $t, 'doc' => Docs::mark('pcv', $t['txn_no'])], 'layouts/print');
    }

    public function file(int $id, int $txn): void
    {
        $box = Petty::box($id) ?? $this->notFound();
        if (!Petty::canSee($box)) {
            $this->forbidden();
        }
        $t = db()->one('SELECT * FROM fin_transactions WHERE id = ? AND account_id = ?', [$txn, $id]);
        if (!$t || !$t['attachment']) {
            $this->notFound();
        }
        Upload::send($t['attachment'], $t['attachment_mime'], $t['attachment_name']);
    }
}
