<?php
declare(strict_types=1);

/**
 * Requisition & approval: a staff member asks for money/items → Head Teacher approves
 * (and the School Manager above the threshold) → Bursar pays out through the cash book →
 * receipts and change are accounted for ("retired").
 */
class RequisitionController extends Controller
{
    private const MAX_ITEMS = 12;

    public function index(): void
    {
        $tab = (string) ($_GET['tab'] ?? '');
        $tabs = ['mine' => 'My requests'];
        if (can('requisitions.approve') || can('requisitions.approve2')) {
            $tabs['approve'] = 'To approve';
        }
        if (can('requisitions.pay')) {
            $tabs['pay'] = 'To pay / retire';
        }
        if (can('requisitions.view_all')) {
            $tabs['all'] = 'All requests';
        }
        if (!isset($tabs[$tab])) {
            $tab = isset($tabs['approve']) ? 'approve' : (isset($tabs['pay']) ? 'pay' : 'mine');
        }
        $sql = 'SELECT r.*, u.name AS requester, c.name AS category_name FROM requisitions r JOIN users u ON u.id = r.requested_by
                LEFT JOIN fin_categories c ON c.id = r.category_id WHERE ';
        $p = [];
        switch ($tab) {
            case 'approve':
                $st = [];
                if (can('requisitions.approve')) {
                    $st[] = "'submitted'";
                }
                if (can('requisitions.approve2')) {
                    $st[] = "'approved_l1'";
                }
                $sql .= 'r.status IN (' . implode(',', $st) . ') AND r.requested_by <> ?';
                $p[] = Auth::id();
                break;
            case 'pay':
                $sql .= "r.status IN ('approved','paid')";
                break;
            case 'all':
                $status = (string) ($_GET['status'] ?? '');
                $sql .= "r.status <> 'draft'";
                if (isset(Requisition::STATUS[$status])) {
                    $sql .= ' AND r.status = ?';
                    $p[] = $status;
                }
                break;
            default:
                $sql .= 'r.requested_by = ?';
                $p[] = Auth::id();
        }
        [$rows, $pager] = paginate($sql . ' ORDER BY r.id DESC', $p, 30);
        $counts = [
            'approve' => (int) db()->value("SELECT COUNT(*) FROM requisitions WHERE requested_by <> ? AND ((status = 'submitted' AND ?) OR (status = 'approved_l1' AND ?))", [Auth::id(), can('requisitions.approve') ? 1 : 0, can('requisitions.approve2') ? 1 : 0]),
            'pay' => (int) db()->value("SELECT COUNT(*) FROM requisitions WHERE status IN ('approved','paid')"),
        ];
        $this->view('requisitions/index', ['title' => 'Requisitions', 'rows' => $rows, 'pager' => $pager, 'tab' => $tab, 'tabs' => $tabs, 'counts' => $counts]);
    }

    public function create(): void
    {
        $this->view('requisitions/form', ['title' => 'New requisition', 'r' => null, 'items' => [], 'categories' => Finance::categories('expense')]);
    }

    public function store(): void
    {
        [$data, $items] = $this->input('requisitions/create');
        $id = db()->transaction(function (Database $d) use ($data, $items) {
            $id = $d->insert('requisitions', $data + ['req_no' => Fees::nextNumber($d, 'REQ'), 'requested_by' => Auth::id(), 'status' => 'draft']);
            $this->saveItems($d, $id, $items);
            Requisition::log($d, $id, 'created', null, 'draft');
            return $id;
        });
        $this->storeFile($id, 'quote');
        audit('requisition_created', 'requisitions', $id);
        if (!empty($_POST['submit_now'])) {
            $this->doSubmit($id);
        }
        flash('success', 'Requisition saved' . (!empty($_POST['submit_now']) ? ' and sent for approval.' : ' as a draft. Submit it when ready.'));
        redirect('requisitions/' . $id);
    }

    public function show(int $id): void
    {
        $r = $this->find($id);
        $this->view('requisitions/show', $this->showData($r) + ['title' => $r['req_no'] . ' — ' . $r['title']]);
    }

    public function printForm(int $id): void
    {
        $r = $this->find($id);
        $this->view('requisitions/print', $this->showData($r) + ['title' => 'Requisition ' . $r['req_no']], 'layouts/print');
    }

    public function edit(int $id): void
    {
        $r = $this->find($id);
        if (!Requisition::editable($r)) {
            flash('error', 'Only your own draft or returned requisitions can be changed.');
            redirect('requisitions/' . $id);
        }
        $this->view('requisitions/form', [
            'title' => 'Edit ' . $r['req_no'], 'r' => $r, 'categories' => Finance::categories('expense'),
            'items' => db()->all('SELECT * FROM requisition_items WHERE requisition_id = ? ORDER BY id', [$id]),
        ]);
    }

    public function update(int $id): void
    {
        $r = $this->find($id);
        if (!Requisition::editable($r)) {
            flash('error', 'Only your own draft or returned requisitions can be changed.');
            redirect('requisitions/' . $id);
        }
        [$data, $items] = $this->input('requisitions/' . $id . '/edit');
        db()->transaction(function (Database $d) use ($id, $data, $items) {
            $d->update('requisitions', $data, 'id = ?', [$id]);
            $d->run('DELETE FROM requisition_items WHERE requisition_id = ?', [$id]);
            $this->saveItems($d, $id, $items);
            Requisition::log($d, $id, 'edited', null, null);
        });
        $this->storeFile($id, 'quote');
        audit('requisition_edited', 'requisitions', $id);
        if (!empty($_POST['submit_now'])) {
            $this->doSubmit($id);
        }
        flash('success', 'Requisition updated' . (!empty($_POST['submit_now']) ? ' and sent for approval.' : '.'));
        redirect('requisitions/' . $id);
    }

    public function submit(int $id): void
    {
        $r = $this->find($id);
        if (!Requisition::editable($r)) {
            flash('error', 'This requisition cannot be submitted.');
            redirect('requisitions/' . $id);
        }
        $this->doSubmit($id);
        flash('success', 'Sent to the Head Teacher for approval.');
        redirect('requisitions/' . $id);
    }

    public function cancel(int $id): void
    {
        $r = $this->find($id);
        if ((int) $r['requested_by'] !== Auth::id() || !in_array($r['status'], ['draft', 'submitted', 'returned', 'approved_l1', 'approved'], true)) {
            flash('error', 'This requisition cannot be cancelled.');
            redirect('requisitions/' . $id);
        }
        db()->transaction(function (Database $d) use ($r) {
            $d->update('requisitions', ['status' => 'cancelled'], 'id = ?', [$r['id']]);
            Requisition::log($d, (int) $r['id'], 'cancelled', $r['status'], 'cancelled', trim((string) ($_POST['note'] ?? '')) ?: null);
        });
        audit('requisition_cancelled', 'requisitions', $id);
        flash('success', 'Requisition cancelled.');
        redirect('requisitions/' . $id);
    }

    /** Head Teacher (level 1) or School Manager (level 2) decision. */
    public function decide(int $id): void
    {
        $r = $this->find($id);
        $decision = (string) ($_POST['decision'] ?? '');
        $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 500);
        $level = $r['status'] === 'submitted' ? 1 : ($r['status'] === 'approved_l1' ? 2 : 0);
        $err = null;
        if (!$level) {
            $err = 'This requisition is not waiting for approval.';
        } elseif (!can($level === 1 ? 'requisitions.approve' : 'requisitions.approve2')) {
            $err = $level === 1 ? 'Only the Head Teacher can approve this.' : 'This needs the School Manager\'s approval.';
        } elseif ((int) $r['requested_by'] === Auth::id()) {
            $err = 'You cannot approve your own requisition.';
        } elseif ($level === 2 && (int) $r['approved_by'] === Auth::id()) {
            $err = 'The second approval must be given by a different person.';
        } elseif (!in_array($decision, ['approve', 'reject', 'return'], true)) {
            $err = 'Choose approve, return or reject.';
        } elseif ($decision !== 'approve' && $note === '') {
            $err = 'Write a short note to explain the decision.';
        }
        $approved = round((float) str_replace(',', '', (string) ($_POST['approved_amount'] ?? $r['approved_amount'] ?? $r['amount'])), 2);
        $cap = $level === 2 ? (float) $r['approved_amount'] : (float) $r['amount'];
        if (!$err && $decision === 'approve' && ($approved <= 0 || $approved > $cap + 0.004)) {
            $err = 'The approved amount must be above 0 and not more than ' . Fees::money($cap) . '.';
        }
        if (!$err && $decision === 'approve' && ($stop = DeptBudget::check($r['department'], $approved, (int) $r['id'], $r['req_no']))) {
            $err = $stop;
        }
        if ($err) {
            flash('error', $err);
            redirect('requisitions/' . $id);
        }
        $to = ['reject' => 'rejected', 'return' => 'returned'][$decision] ?? (($level === 1 && $approved > Requisition::threshold()) ? 'approved_l1' : 'approved');
        try {
            db()->transaction(function (Database $d) use ($r, $level, $decision, $approved, $to, $note) {
                $fresh = $d->one('SELECT status FROM requisitions WHERE id = ? FOR UPDATE', [$r['id']]);
                if ($fresh['status'] !== $r['status']) {
                    throw new RuntimeException('changed');
                }
                $upd = ['status' => $to];
                if ($decision === 'approve') {
                    $upd['approved_amount'] = $approved;
                    if ($level === 1) {
                        $upd += ['approved_by' => Auth::id(), 'approved_at' => date('Y-m-d H:i:s'), 'needs_second' => $to === 'approved_l1' ? 1 : 0];
                    } else {
                        $upd += ['second_by' => Auth::id(), 'second_at' => date('Y-m-d H:i:s')];
                    }
                }
                $d->update('requisitions', $upd, 'id = ?', [$r['id']]);
                Requisition::log($d, (int) $r['id'], $decision . ($level === 2 ? '_l2' : ''), $r['status'], $to, trim(($decision === 'approve' ? 'Approved ' . Fees::money($approved) . '. ' : '') . $note) ?: null);
            });
        } catch (RuntimeException $e) {
            if ($e->getMessage() !== 'changed') {
                throw $e;
            }
            flash('warning', 'Someone else updated this requisition a moment ago. Please check it again.');
            redirect('requisitions/' . $r['id']);
        }
        audit('requisition_' . $decision, 'requisitions', $id, ['level' => $level, 'amount' => $approved]);
        $label = ['approved_l1' => 'approved by the Head Teacher and sent to the School Manager', 'approved' => 'approved', 'rejected' => 'rejected', 'returned' => 'returned for changes'][$to];
        Requisition::tellRequester($r, 'Requisition ' . $r['req_no'] . ' ' . $label, $note ?: null);
        if ($to === 'approved_l1') {
            Notifier::inappPermission('requisitions.approve2', 'Requisition needs your approval', $r['req_no'] . ': ' . $r['title'] . ' — ' . Fees::money($approved), 'requisitions/' . $id);
        } elseif ($to === 'approved') {
            Notifier::inappPermission('requisitions.pay', 'Requisition approved — ready to pay', $r['req_no'] . ': ' . $r['title'] . ' — ' . Fees::money($approved), 'requisitions/' . $id);
        }
        flash('success', 'Requisition ' . $label . '.');
        redirect('requisitions', ['tab' => 'approve']);
    }

    /** Bursar pays out the approved amount (expense in the cash book). */
    public function pay(int $id): void
    {
        $r = $this->find($id);
        $amount = round((float) str_replace(',', '', (string) ($_POST['amount'] ?? '0')), 2);
        $acct = db()->one('SELECT * FROM fin_accounts WHERE id = ? AND is_active = 1', [(int) ($_POST['account_id'] ?? 0)]);
        $date = (string) ($_POST['paid_on'] ?? date('Y-m-d'));
        $err = null;
        if ($r['status'] !== 'approved') {
            $err = 'Only approved requisitions can be paid.';
        } elseif (!empty($r['supplier_bill_id'])) {
            $err = 'This requisition is being paid to the supplier through a supplier bill.';
        } elseif (!empty($r['purchase_order_id'])) {
            $err = 'This requisition is being bought through a purchase order: the supplier is paid on its bill.';
        } elseif ((int) $r['requested_by'] === Auth::id()) {
            $err = 'You cannot pay out your own requisition. Another officer must do it.';
        } elseif ($closed = Finance::closedError($date)) {
            $err = $closed;
        } elseif (!$acct) {
            $err = 'Choose the account the money is paid from.';
        } elseif ($amount <= 0 || $amount > (float) $r['approved_amount'] + 0.004) {
            $err = 'Pay between GH₵ 0.01 and the approved ' . Fees::money($r['approved_amount']) . '.';
        } elseif (!Validator::isDate($date) || $date > date('Y-m-d')) {
            $err = 'Enter a valid payment date.';
        } elseif ($amount > Finance::balance((int) $acct['id']) + 0.004 && empty($_POST['allow_overdraw'])) {
            $err = 'There is only ' . Fees::money(Finance::balance((int) $acct['id'])) . ' in ' . $acct['name'] . '.';
        }
        if ($err) {
            flash('error', $err);
            redirect('requisitions/' . $id);
        }
        $payee = mb_substr(trim((string) ($_POST['party'] ?? '')), 0, 150) ?: $r['requester'];
        try {
            db()->transaction(function (Database $d) use ($r, $amount, $acct, $date, $payee) {
                $fresh = $d->one('SELECT status FROM requisitions WHERE id = ? FOR UPDATE', [$r['id']]);
                if ($fresh['status'] !== 'approved') {
                    throw new RuntimeException('changed');
                }
                Finance::post($d, [
                    'txn_no' => $r['req_no'], 'kind' => 'expense', 'txn_date' => $date, 'account_id' => (int) $acct['id'],
                    'category_id' => $r['category_id'] ?: $d->value("SELECT id FROM fin_categories WHERE code = 'OEXP'"),
                    'amount' => $amount, 'description' => 'Requisition ' . $r['req_no'] . ': ' . $r['title'], 'party' => $payee,
                    'reference' => mb_substr(trim((string) ($_POST['reference'] ?? '')), 0, 100) ?: null,
                    'source' => 'requisition', 'source_id' => (int) $r['id'], 'department' => $r['department'],
                ]);
                $d->update('requisitions', ['status' => 'paid', 'paid_amount' => $amount, 'paid_by' => Auth::id(), 'paid_at' => date('Y-m-d H:i:s')], 'id = ?', [$r['id']]);
                Requisition::log($d, (int) $r['id'], 'paid', 'approved', 'paid', 'Paid ' . Fees::money($amount) . ' from ' . $acct['name'] . ' to ' . $payee);
            });
        } catch (RuntimeException $e) {
            if ($e->getMessage() !== 'changed') {
                throw $e;
            }
            flash('warning', 'Someone else updated this requisition a moment ago. Please check it again.');
            redirect('requisitions/' . $r['id']);
        }
        audit('requisition_paid', 'requisitions', $id, ['amount' => $amount, 'account' => $acct['id']]);
        Requisition::tellRequester($r, 'Requisition ' . $r['req_no'] . ' paid', Fees::money($amount) . ' has been paid out. Please bring the receipts and any change to the Bursar.');
        flash('success', Fees::money($amount) . ' paid out and recorded in the cash book.');
        redirect('requisitions/' . $id);
    }

    /** Receipts checked and change returned: close the requisition. */
    public function retire(int $id): void
    {
        $r = $this->find($id);
        $spent = round((float) str_replace(',', '', (string) ($_POST['spent'] ?? '')), 2);
        $paid = (float) $r['paid_amount'];
        $change = round($paid - $spent, 2);
        $acct = db()->one('SELECT * FROM fin_accounts WHERE id = ? AND is_active = 1', [(int) ($_POST['account_id'] ?? 0)]);
        $err = null;
        if ($r['status'] !== 'paid') {
            $err = 'Only paid requisitions can be closed.';
        } elseif ($change > 0 && ($closed = Finance::closedError(date('Y-m-d')))) {
            $err = $closed;
        } elseif ((int) $r['requested_by'] === Auth::id()) {
            $err = 'You cannot sign off your own requisition. Another officer must check the receipts and change.';
        } elseif ($spent < 0 || $spent > $paid + 0.004) {
            $err = 'Amount spent must be between 0 and the ' . Fees::money($paid) . ' paid out. If more was spent, raise a new requisition for the difference.';
        } elseif ($change > 0 && !$acct) {
            $err = 'Choose the account the change is paid back into.';
        }
        if ($err) {
            flash('error', $err);
            redirect('requisitions/' . $id);
        }
        $this->storeFile($id, 'receipt');
        $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 255) ?: null;
        try {
            db()->transaction(function (Database $d) use ($r, $spent, $change, $acct, $note) {
                $fresh = $d->one('SELECT status FROM requisitions WHERE id = ? FOR UPDATE', [$r['id']]);
                if ($fresh['status'] !== 'paid') {
                    throw new RuntimeException('changed');
                }
                if ($change > 0) {
                    Finance::post($d, [
                        'txn_no' => $r['req_no'] . '-C', 'kind' => 'refund', 'txn_date' => date('Y-m-d'), 'account_id' => (int) $acct['id'],
                        'category_id' => $r['category_id'] ?: $d->value("SELECT id FROM fin_categories WHERE code = 'OEXP'"),
                        'amount' => $change, 'description' => 'Change returned on requisition ' . $r['req_no'], 'party' => $r['requester'],
                        'source' => 'req_refund', 'source_id' => (int) $r['id'], 'department' => $r['department'],
                    ]);
                }
                $d->update('requisitions', ['status' => 'retired', 'spent_amount' => $spent, 'retired_at' => date('Y-m-d H:i:s'), 'retire_note' => $note], 'id = ?', [$r['id']]);
                Requisition::log($d, (int) $r['id'], 'retired', 'paid', 'retired', 'Spent ' . Fees::money($spent) . ($change > 0 ? ', change ' . Fees::money($change) . ' returned' : '') . ($note ? '. ' . $note : ''));
            });
        } catch (RuntimeException $e) {
            if ($e->getMessage() !== 'changed') {
                throw $e;
            }
            flash('warning', 'Someone else updated this requisition a moment ago. Please check it again.');
            redirect('requisitions/' . $r['id']);
        }
        audit('requisition_retired', 'requisitions', $id, ['spent' => $spent, 'change' => $change]);
        flash('success', 'Requisition closed' . ($change > 0 ? '; change of ' . Fees::money($change) . ' recorded.' : '.'));
        redirect('requisitions/' . $id);
    }

    public function upload(int $id): void
    {
        $r = $this->find($id);
        if (in_array($r['status'], ['cancelled', 'rejected', 'retired'], true)) {
            flash('error', 'This requisition is closed.');
            redirect('requisitions/' . $id);
        }
        $kind = in_array($_POST['kind'] ?? '', ['quote', 'receipt', 'other'], true) ? $_POST['kind'] : 'other';
        if (!$this->storeFile($id, $kind, true)) {
            flash('error', 'Choose a PDF, JPG or PNG file (max 5 MB).');
        } else {
            flash('success', 'File attached.');
        }
        redirect('requisitions/' . $id);
    }

    public function file(int $id, int $file): void
    {
        $this->find($id);
        $f = db()->one('SELECT * FROM requisition_files WHERE id = ? AND requisition_id = ?', [$file, $id]) ?? $this->notFound();
        Upload::send($f['stored_name'], $f['mime'], $f['original_name']);
    }

    // ---------------------------------------------------------------- helpers

    private function doSubmit(int $id): void
    {
        $r = db()->one('SELECT * FROM requisitions WHERE id = ?', [$id]);
        $quoteAbove = (float) setting('req_require_quote_above', '1000');
        $hasQuote = (int) db()->value("SELECT COUNT(*) FROM requisition_files WHERE requisition_id = ? AND kind = 'quote'", [$id]);
        if ((float) $r['amount'] <= 0) {
            flash('error', 'Add at least one item with a cost before submitting.');
            redirect('requisitions/' . $id);
        }
        if ($quoteAbove > 0 && (float) $r['amount'] > $quoteAbove && !$hasQuote) {
            flash('error', 'Requests above ' . Fees::money($quoteAbove) . ' need a quotation or pro-forma invoice attached. Attach it, then submit.');
            redirect('requisitions/' . $id);
        }
        $need = Purchasing::quotesNeeded((float) $r['amount']);
        if ($need > 1 && $hasQuote < $need) {
            flash('error', 'Requests above ' . Fees::money((float) setting('pur_quote3_above', '5000')) . ' need three quotations from different suppliers (' . $hasQuote . ' attached). Attach them, then submit.');
            redirect('requisitions/' . $id);
        }
        if ($stop = DeptBudget::check($r['department'], (float) $r['amount'], null, $r['req_no'])) {
            flash('error', $stop);
            redirect('requisitions/' . $id);
        }
        db()->transaction(function (Database $d) use ($r) {
            $d->update('requisitions', ['status' => 'submitted', 'submitted_at' => date('Y-m-d H:i:s'), 'approved_amount' => null, 'approved_by' => null, 'approved_at' => null, 'second_by' => null, 'second_at' => null, 'needs_second' => 0], 'id = ?', [$r['id']]);
            Requisition::log($d, (int) $r['id'], 'submitted', $r['status'], 'submitted');
        });
        audit('requisition_submitted', 'requisitions', $id);
        Notifier::inappPermission('requisitions.approve', 'New requisition to approve', $r['req_no'] . ': ' . $r['title'] . ' — ' . Fees::money($r['amount']) . ' (' . auth()['name'] . ')', 'requisitions/' . $id);
    }

    /** @return array{0: array, 1: array} requisition fields, items */
    private function input(string $back): array
    {
        $in = $this->only(['title', 'purpose', 'department', 'category_id', 'needed_by']);
        $items = [];
        foreach ((array) ($_POST['item_desc'] ?? []) as $k => $desc) {
            $desc = mb_substr(trim((string) $desc), 0, 150);
            $qty = round((float) str_replace(',', '', (string) ($_POST['item_qty'][$k] ?? '1')), 2);
            $cost = round((float) str_replace(',', '', (string) ($_POST['item_cost'][$k] ?? '0')), 2);
            if ($desc === '') {
                continue;
            }
            if ($qty <= 0 || $qty > 100000 || $cost < 0 || $cost > 1000000) {
                $items = null;
                break;
            }
            $items[] = ['description' => $desc, 'quantity' => $qty, 'unit' => mb_substr(trim((string) ($_POST['item_unit'][$k] ?? '')), 0, 20) ?: null, 'unit_cost' => $cost];
            if (count($items) >= self::MAX_ITEMS) {
                break;
            }
        }
        $cat = $in['category_id'] ? db()->value("SELECT id FROM fin_categories WHERE id = ? AND kind = 'expense'", [(int) $in['category_id']]) : null;
        $err = null;
        if (!$in['title']) {
            $err = 'Give the requisition a title.';
        } elseif ($items === null) {
            $err = 'Check the quantities and costs of the items.';
        } elseif (!$items) {
            $err = 'Add at least one item.';
        } elseif ($in['needed_by'] && !Validator::isDate($in['needed_by'])) {
            $err = 'Enter a valid "needed by" date.';
        } elseif (!$cat) {
            $err = 'Choose what kind of expense this is.';
        }
        if ($err) {
            with_old($_POST);
            flash('error', $err);
            redirect($back);
        }
        $amount = round(array_sum(array_map(fn($i) => $i['quantity'] * $i['unit_cost'], $items)), 2);
        return [[
            'title' => mb_substr($in['title'], 0, 150), 'purpose' => $in['purpose'] ? mb_substr($in['purpose'], 0, 2000) : null,
            'department' => $in['department'] ? mb_substr($in['department'], 0, 80) : null, 'category_id' => (int) $cat,
            'needed_by' => $in['needed_by'], 'amount' => $amount,
        ], $items];
    }

    private function saveItems(Database $d, int $id, array $items): void
    {
        foreach ($items as $it) {
            $d->insert('requisition_items', $it + ['requisition_id' => $id]);
        }
        Requisition::recalc($d, $id);
    }

    private function storeFile(int $id, string $kind, bool $required = false): bool
    {
        if (empty($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return !$required ? true : false;
        }
        [$info, $err] = Upload::store($_FILES['file'], 'requisitions');
        if ($err || !$info) {
            flash('warning', 'The file was not attached: ' . ($err ?: 'no file'));
            return false;
        }
        db()->insert('requisition_files', $info + ['requisition_id' => $id, 'kind' => $kind, 'uploaded_by' => Auth::id()]);
        audit('requisition_file', 'requisitions', $id, ['kind' => $kind]);
        return true;
    }

    private function showData(array $r): array
    {
        $id = (int) $r['id'];
        return [
            'r' => $r,
            'items' => db()->all('SELECT * FROM requisition_items WHERE requisition_id = ? ORDER BY id', [$id]),
            'log' => db()->all('SELECT l.*, u.name AS user_name FROM requisition_log l LEFT JOIN users u ON u.id = l.user_id WHERE l.requisition_id = ? ORDER BY l.id', [$id]),
            'files' => db()->all('SELECT f.*, u.name AS uploader FROM requisition_files f LEFT JOIN users u ON u.id = f.uploaded_by WHERE f.requisition_id = ? ORDER BY f.id', [$id]),
            'budget' => Finance::budgetPosition($r['category_id'] ? (int) $r['category_id'] : null),
            'accounts' => Finance::accounts(),
            'payment' => db()->one("SELECT t.*, a.name AS account_name FROM fin_transactions t JOIN fin_accounts a ON a.id = t.account_id WHERE t.source = 'requisition' AND t.source_id = ?", [$id]),
            'po' => !empty($r['purchase_order_id']) ? db()->one('SELECT id, po_no, status FROM purchase_orders WHERE id = ?', [$r['purchase_order_id']]) : null,
            'dept' => self::deptPosition($r['department']),
        ];
    }

    /** Department cap position for the current term (null when the department has no cap). */
    private static function deptPosition(?string $dept): ?array
    {
        if (!$dept || !DeptBudget::ready() || !($t = DeptBudget::term())) {
            return null;
        }
        foreach (DeptBudget::rows($t) as $x) {
            if ($x['department'] === $dept && $x['cap'] !== null) {
                return $x + ['term' => $t['name']];
            }
        }
        return null;
    }

    private function find(int $id): array
    {
        $r = db()->one(
            'SELECT r.*, u.name AS requester, u.role AS requester_role, c.name AS category_name, a1.name AS approver_name, a2.name AS second_name, pb.name AS paid_by_name
             FROM requisitions r JOIN users u ON u.id = r.requested_by LEFT JOIN fin_categories c ON c.id = r.category_id
             LEFT JOIN users a1 ON a1.id = r.approved_by LEFT JOIN users a2 ON a2.id = r.second_by LEFT JOIN users pb ON pb.id = r.paid_by
             WHERE r.id = ?',
            [$id]
        );
        if (!$r || !Requisition::canSee($r) || ($r['status'] === 'draft' && (int) $r['requested_by'] !== Auth::id())) {
            $this->notFound();
        }
        return $r;
    }
}
