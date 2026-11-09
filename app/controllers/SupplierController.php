<?php
declare(strict_types=1);

/** Suppliers, bills to pay, supplier payments (with withholding tax) and paying the WHT to GRA. */
class SupplierController extends Controller
{
    // ------------------------------------------------------------------ bills to pay

    public function index(): void
    {
        $this->needsTable('supplier_bills');
        $show = in_array($_GET['show'] ?? '', ['open', 'paid', 'all'], true) ? $_GET['show'] : 'open';
        $where = $show === 'open' ? "b.status IN ('unpaid','part')" : ($show === 'paid' ? "b.status = 'paid'" : '1 = 1');
        $rows = db()->all(
            "SELECT b.*, s.name AS supplier_name, c.name AS category_name FROM supplier_bills b JOIN suppliers s ON s.id = b.supplier_id
             LEFT JOIN fin_categories c ON c.id = b.category_id WHERE $where ORDER BY b.status IN ('unpaid','part') DESC, COALESCE(b.due_date, b.bill_date), b.id DESC LIMIT 1000"
        );
        $owing = (float) db()->value("SELECT COALESCE(SUM(amount - paid_gross), 0) FROM supplier_bills WHERE status IN ('unpaid','part')");
        $overdue = (int) db()->value("SELECT COUNT(*) FROM supplier_bills WHERE status IN ('unpaid','part') AND due_date < CURDATE()");
        $this->view('suppliers/index', ['title' => 'Bills to pay', 'rows' => $rows, 'show' => $show, 'owing' => $owing, 'overdue' => $overdue,
            'whtOwed' => array_sum(array_map(fn($p) => (float) $p['wht'], Suppliers::whtOwed()))]);
    }

    public function newBill(): void
    {
        $this->needsTable('supplier_bills');
        $req = null;
        if (!empty($_GET['req'])) {
            $req = db()->one("SELECT * FROM requisitions WHERE id = ? AND status = 'approved' AND supplier_bill_id IS NULL", [(int) $_GET['req']]);
            if (!$req) {
                flash('error', 'Only an approved requisition that is not yet paid can become a supplier bill.');
                redirect('requisitions/' . (int) $_GET['req']);
            }
        }
        $this->view('suppliers/bill_form', [
            'title' => 'New supplier bill', 'req' => $req,
            'suppliers' => db()->all('SELECT * FROM suppliers WHERE is_active = 1 ORDER BY name'),
            'categories' => Finance::categories('expense'),
            'sel' => (int) ($_GET['supplier'] ?? 0),
        ]);
    }

    public function storeBill(): void
    {
        $this->needsTable('supplier_bills');
        $sup = db()->one('SELECT * FROM suppliers WHERE id = ? AND is_active = 1', [(int) ($_POST['supplier_id'] ?? 0)]);
        $amount = round((float) str_replace(',', '', (string) ($_POST['amount'] ?? '0')), 2);
        $date = (string) ($_POST['bill_date'] ?? '');
        $due = (string) ($_POST['due_date'] ?? '');
        $type = isset(Suppliers::TYPES[$_POST['supply_type'] ?? '']) ? $_POST['supply_type'] : ($sup['supply_type'] ?? 'goods');
        $cat = db()->one("SELECT * FROM fin_categories WHERE id = ? AND kind = 'expense' AND is_active = 1", [(int) ($_POST['category_id'] ?? 0)]);
        $desc = mb_substr(trim((string) ($_POST['description'] ?? '')), 0, 255);
        $reqId = (int) ($_POST['requisition_id'] ?? 0);
        $req = $reqId ? db()->one("SELECT * FROM requisitions WHERE id = ? AND status = 'approved' AND supplier_bill_id IS NULL", [$reqId]) : null;
        $back = 'suppliers/bills/new' . ($reqId ? '?req=' . $reqId : '');
        $err = null;
        if (!$sup) {
            $err = 'Choose the supplier (add a new one first if needed).';
        } elseif ($amount <= 0 || $amount > 50000000) {
            $err = 'Enter the bill amount.';
        } elseif (!Validator::isDate($date) || $date > date('Y-m-d')) {
            $err = 'Enter the date on the supplier\'s invoice (not in the future).';
        } elseif ($due !== '' && (!Validator::isDate($due) || $due < $date)) {
            $err = 'The due date must be on or after the bill date.';
        } elseif (!$cat) {
            $err = 'Choose what the money is for (category).';
        } elseif ($desc === '') {
            $err = 'Describe what was supplied.';
        } elseif ($reqId && !$req) {
            $err = 'That requisition is no longer approved or already has a bill.';
        } elseif ($req && $amount > (float) ($req['approved_amount'] ?? $req['amount']) + 0.004) {
            $err = 'The bill is more than the approved requisition (' . Fees::money($req['approved_amount'] ?? $req['amount']) . '). Ask for a new approval.';
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
        [$rate, $wht] = Suppliers::wht($amount, $type, !empty($_POST['no_wht']));
        $id = db()->transaction(function (Database $d) use ($sup, $amount, $date, $due, $type, $cat, $desc, $req, $rate, $wht, $file) {
            $id = $d->insert('supplier_bills', [
                'bill_no' => Fees::nextNumber($d, 'SB'), 'supplier_id' => $sup['id'], 'supplier_ref' => mb_substr(trim((string) ($_POST['supplier_ref'] ?? '')), 0, 60) ?: null,
                'bill_date' => $date, 'due_date' => $due ?: null, 'category_id' => $cat['id'], 'description' => $desc, 'amount' => $amount,
                'supply_type' => $type, 'wht_rate' => $rate, 'wht_amount' => $wht, 'requisition_id' => $req['id'] ?? null,
                'attachment' => $file['stored_name'] ?? null, 'attachment_name' => $file['original_name'] ?? null, 'attachment_mime' => $file['mime'] ?? null,
                'created_by' => Auth::id(),
            ] + (Finance::hasDepartments() ? ['department' => ($req['department'] ?? null) ?: (in_array((string) ($_POST['department'] ?? ''), Finance::departments(false), true) ? $_POST['department'] : null)] : []));
            if ($req) {
                $d->update('requisitions', ['supplier_bill_id' => $id], 'id = ?', [$req['id']]);
            }
            return $id;
        });
        audit('supplier_bill_created', 'supplier_bills', $id, ['amount' => $amount, 'supplier' => $sup['name']]);
        flash('success', 'Bill saved' . ($wht > 0 ? ' — ' . Fees::money($wht) . ' withholding tax will be kept back when it is paid' : '') . '.');
        redirect('suppliers/bills/' . $id);
    }

    public function showBill(int $id): void
    {
        $this->needsTable('supplier_bills');
        $b = Suppliers::bill($id) ?? $this->notFound();
        $this->view('suppliers/bill', [
            'title' => 'Bill ' . $b['bill_no'], 'b' => $b, 'accounts' => Finance::accounts(true, false),
            'payments' => db()->all('SELECT p.*, a.name AS account_name, u.name AS by_name, w.wr_no FROM supplier_payments p JOIN fin_accounts a ON a.id = p.account_id LEFT JOIN users u ON u.id = p.created_by LEFT JOIN wht_remittances w ON w.id = p.wht_remit_id WHERE p.bill_id = ? ORDER BY p.id', [$id]),
        ]);
    }

    public function payBill(int $id): void
    {
        $this->needsTable('supplier_bills');
        $date = (string) ($_POST['pay_date'] ?? date('Y-m-d'));
        if (!Validator::isDate($date) || $date > date('Y-m-d')) {
            flash('error', 'Enter the payment date (not in the future).');
            redirect('suppliers/bills/' . $id);
        }
        [$pid, $err] = Suppliers::pay($id, round((float) str_replace(',', '', (string) ($_POST['amount'] ?? '0')), 2), (int) ($_POST['account_id'] ?? 0), $date, mb_substr(trim((string) ($_POST['reference'] ?? '')), 0, 100));
        if ($err) {
            flash('error', $err);
            redirect('suppliers/bills/' . $id);
        }
        $p = db()->one('SELECT * FROM supplier_payments WHERE id = ?', [$pid]);
        audit('supplier_paid', 'supplier_payments', $pid, ['gross' => $p['gross'], 'wht' => $p['wht']]);
        flash('success', 'Paid ' . Fees::money($p['net']) . ' to the supplier' . ((float) $p['wht'] > 0 ? ' and kept ' . Fees::money($p['wht']) . ' withholding tax for GRA' : '') . ' (' . $p['pv_no'] . ').');
        $b = db()->one("SELECT b.*, c.code FROM supplier_bills b LEFT JOIN fin_categories c ON c.id = b.category_id WHERE b.id = ?", [$id]);
        if (($b['code'] ?? '') === 'ASSET' && can('assets.manage')) {
            flash('info', 'This bill is for assets: use "+ Add to the asset register" on this page so they are counted and depreciated.');
        }
        redirect('suppliers/bills/' . $id, ['pv' => $pid]);
    }

    /** Head approves a big bill for payment. */
    public function approveBill(int $id): void
    {
        $this->needsTable('supplier_bills');
        if ($err = Suppliers::approvePay($id)) {
            flash('error', $err);
            redirect('suppliers/bills/' . $id);
        }
        $b = Suppliers::bill($id);
        audit('supplier_bill_pay_approved', 'supplier_bills', $id, ['amount' => $b['amount']]);
        Notifier::inappPermission('finance.manage', 'Bill approved for payment', $b['bill_no'] . ' — ' . $b['supplier_name'] . ' — ' . Fees::money($b['amount']), 'suppliers/bills/' . $id);
        flash('success', 'Approved for payment.');
        redirect('suppliers/bills/' . $id);
    }

    public function cancelBill(int $id): void
    {
        $this->needsTable('supplier_bills');
        $b = Suppliers::bill($id) ?? $this->notFound();
        $reason = mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 255);
        if ((float) $b['paid_gross'] > 0 || $b['status'] === 'cancelled' || $reason === '') {
            flash('error', (float) $b['paid_gross'] > 0 ? 'Cancel the payments on this bill first.' : 'Give a reason.');
            redirect('suppliers/bills/' . $id);
        }
        db()->transaction(function (Database $d) use ($b, $reason) {
            $d->update('supplier_bills', ['status' => 'cancelled', 'cancel_reason' => $reason], 'id = ?', [$b['id']]);
            if ($b['requisition_id']) {
                $d->update('requisitions', ['supplier_bill_id' => null], 'id = ?', [$b['requisition_id']]);
            }
        });
        audit('supplier_bill_cancelled', 'supplier_bills', $id, ['reason' => $reason]);
        flash('success', 'Bill cancelled.');
        redirect('suppliers/bills/' . $id);
    }

    public function billFile(int $id): void
    {
        $b = Suppliers::bill($id) ?? $this->notFound();
        if (!$b['attachment']) {
            $this->notFound();
        }
        Upload::send($b['attachment'], $b['attachment_mime'], $b['attachment_name']);
    }

    public function voidPayment(int $id): void
    {
        $this->needsTable('supplier_payments');
        $p = db()->one('SELECT * FROM supplier_payments WHERE id = ?', [$id]) ?? $this->notFound();
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $err = $reason === '' ? 'Give a reason.' : Suppliers::voidPayment($id, $reason);
        if (!$err) {
            audit('supplier_payment_void', 'supplier_payments', $id, ['reason' => $reason]);
        }
        flash($err ? 'error' : 'success', $err ?? 'Payment ' . $p['pv_no'] . ' cancelled; the bill is open again.');
        redirect('suppliers/bills/' . $p['bill_id']);
    }

    /** Payment voucher (print) or WHT certificate (?doc=wht). */
    public function voucher(int $id): void
    {
        $this->needsTable('supplier_payments');
        $p = db()->one('SELECT p.*, a.name AS account_name, u.name AS by_name FROM supplier_payments p JOIN fin_accounts a ON a.id = p.account_id LEFT JOIN users u ON u.id = p.created_by WHERE p.id = ?', [$id]) ?? $this->notFound();
        $b = Suppliers::bill((int) $p['bill_id']);
        $wht = ($_GET['doc'] ?? '') === 'wht';
        if ($wht && (float) $p['wht'] <= 0) {
            $this->notFound();
        }
        $this->view($wht ? 'suppliers/print_wht' : 'suppliers/print_voucher', ['title' => ($wht ? 'WHT certificate ' : 'Payment voucher ') . $p['pv_no'], 'p' => $p, 'b' => $b, 'doc' => Docs::mark($wht ? 'wht' : 'pv', $p['pv_no'])], 'layouts/print');
    }

    // ------------------------------------------------------------------ suppliers

    public function suppliers(): void
    {
        $this->needsTable('suppliers');
        $rows = db()->all(
            "SELECT s.*, c.name AS category_name,
                    (SELECT COALESCE(SUM(amount - paid_gross), 0) FROM supplier_bills WHERE supplier_id = s.id AND status IN ('unpaid','part')) AS owing,
                    (SELECT COALESCE(SUM(gross), 0) FROM supplier_payments p JOIN supplier_bills b ON b.id = p.bill_id WHERE b.supplier_id = s.id AND p.status = 'posted' AND p.pay_date >= ?) AS paid_year
             FROM suppliers s LEFT JOIN fin_categories c ON c.id = s.category_id ORDER BY s.is_active DESC, s.name",
            [current_year()['start_date'] ?? date('Y-01-01')]
        );
        $edit = isset($_GET['edit']) ? db()->one('SELECT * FROM suppliers WHERE id = ?', [(int) $_GET['edit']]) : null;
        $this->view('suppliers/suppliers', ['title' => 'Suppliers', 'rows' => $rows, 'edit' => $edit, 'categories' => Finance::categories('expense'), 'open' => $edit || isset($_GET['new'])]);
    }

    public function saveSupplier(int $id = 0): void
    {
        $this->needsTable('suppliers');
        $d = [
            'name' => mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 150),
            'contact_person' => mb_substr(trim((string) ($_POST['contact_person'] ?? '')), 0, 120) ?: null,
            'phone' => Admission::normalisePhone((string) ($_POST['phone'] ?? '')) ?: null,
            'email' => mb_substr(trim((string) ($_POST['email'] ?? '')), 0, 190) ?: null,
            'address' => mb_substr(trim((string) ($_POST['address'] ?? '')), 0, 255) ?: null,
            'tin' => mb_substr(strtoupper(preg_replace('/\s+/', '', (string) ($_POST['tin'] ?? ''))), 0, 20) ?: null,
            'supply_type' => isset(Suppliers::TYPES[$_POST['supply_type'] ?? '']) ? $_POST['supply_type'] : 'goods',
            'pay_details' => mb_substr(trim((string) ($_POST['pay_details'] ?? '')), 0, 150) ?: null,
            'category_id' => (int) ($_POST['category_id'] ?? 0) ?: null,
            'notes' => mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 255) ?: null,
            'is_active' => $id ? (!empty($_POST['is_active']) ? 1 : 0) : 1,
        ];
        $back = $id ? 'suppliers/list?edit=' . $id : 'suppliers/list?new=1';
        $dup = db()->value('SELECT id FROM suppliers WHERE name = ? AND id <> ?', [$d['name'], $id]);
        if ($d['name'] === '' || $dup) {
            with_old($_POST);
            flash('error', $dup ? 'A supplier with that name already exists.' : 'Enter the supplier name.');
            redirect($back);
        }
        if ($d['email'] && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            with_old($_POST);
            flash('error', 'Check the email address.');
            redirect($back);
        }
        if ($id) {
            db()->one('SELECT id FROM suppliers WHERE id = ?', [$id]) ?? $this->notFound();
            db()->update('suppliers', $d, 'id = ?', [$id]);
        } else {
            $id = db()->insert('suppliers', $d);
        }
        audit('supplier_saved', 'suppliers', $id, ['name' => $d['name']]);
        flash('success', '“' . $d['name'] . '” saved.');
        redirect(!empty($_POST['then_bill']) ? 'suppliers/bills/new?supplier=' . $id : 'suppliers/list');
    }

    // ------------------------------------------------------------------ withholding tax

    public function wht(): void
    {
        $this->needsTable('wht_remittances');
        $this->view('suppliers/wht', [
            'title' => 'Withholding tax', 'owed' => Suppliers::whtOwed(), 'accounts' => Finance::accounts(true, false),
            'remits' => db()->all('SELECT w.*, a.name AS account_name, u.name AS by_name, (SELECT COUNT(*) FROM supplier_payments p WHERE p.wht_remit_id = w.id) AS n FROM wht_remittances w JOIN fin_accounts a ON a.id = w.account_id LEFT JOIN users u ON u.id = w.created_by ORDER BY w.remit_date DESC, w.id DESC LIMIT 60'),
        ]);
    }

    public function remit(): void
    {
        $this->needsTable('wht_remittances');
        $date = (string) ($_POST['remit_date'] ?? date('Y-m-d'));
        if (!Validator::isDate($date) || $date > date('Y-m-d')) {
            $date = date('Y-m-d');
        }
        [$rid, $err] = Suppliers::remit((int) ($_POST['account_id'] ?? 0), $date, mb_substr(trim((string) ($_POST['reference'] ?? '')), 0, 100));
        if (!$err) {
            audit('wht_remitted', 'wht_remittances', $rid, []);
        }
        flash($err ? 'error' : 'success', $err ?? 'Withholding tax paid to GRA and entered in the cash book.');
        redirect('suppliers/wht');
    }

    public function remitPrint(int $id): void
    {
        $this->needsTable('wht_remittances');
        $w = db()->one('SELECT w.*, a.name AS account_name, u.name AS by_name FROM wht_remittances w JOIN fin_accounts a ON a.id = w.account_id LEFT JOIN users u ON u.id = w.created_by WHERE w.id = ?', [$id]) ?? $this->notFound();
        $rows = db()->all('SELECT p.*, b.bill_no, b.supply_type, b.wht_rate, s.name AS supplier_name, s.tin FROM supplier_payments p JOIN supplier_bills b ON b.id = p.bill_id JOIN suppliers s ON s.id = b.supplier_id WHERE p.wht_remit_id = ? ORDER BY p.pay_date, p.id', [$id]);
        $this->view('suppliers/print_remit', ['title' => 'WHT schedule ' . $w['wr_no'], 'w' => $w, 'rows' => $rows], 'layouts/print');
    }

    public function rates(): void
    {
        foreach (['goods', 'works', 'services'] as $k) {
            $v = round((float) str_replace(',', '', (string) ($_POST[$k] ?? '')), 2);
            if ($v >= 0 && $v <= 30) {
                setting_set('wht_' . $k, (string) $v);
            }
        }
        $t = round((float) str_replace(',', '', (string) ($_POST['threshold'] ?? '')), 2);
        if ($t >= 0 && $t <= 1000000) {
            setting_set('wht_threshold', (string) $t);
        }
        audit('wht_rates_saved', 'settings', null, []);
        flash('success', 'Withholding tax rates saved. New bills use them.');
        redirect('suppliers/wht');
    }
}
