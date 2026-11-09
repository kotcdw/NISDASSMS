<?php
declare(strict_types=1);

/** Purchase orders, goods received notes and recording the supplier's invoice against an order (three-way match). */
class PurchasingController extends Controller
{
    private function canSee(): void
    {
        $this->needsTable('purchase_orders');
        if (!can('finance.view') && !can('purchasing.order') && !can('purchasing.receive')) {
            http_response_code(403);
            $this->view('errors/403', ['title' => 'Access denied']);
            exit;
        }
    }

    private function find(int $id): array
    {
        $po = Purchasing::po($id);
        if (!$po) {
            $this->notFound();
        }
        return $po;
    }

    public function index(): void
    {
        $this->canSee();
        $status = (string) ($_GET['status'] ?? 'open');
        $w = $status === 'open' ? "po.status IN ('draft','sent','part','received')" : (isset(Purchasing::STATUS[$status]) ? 'po.status = ' . db()->pdo()->quote($status) : '1=1');
        $this->view('purchasing/index', [
            'title' => 'Purchase orders', 'status' => $status,
            'rows' => db()->all(
                "SELECT po.*, s.name AS supplier_name, r.req_no,
                        (SELECT SUM(qty) FROM po_lines WHERE po_id = po.id) AS q, (SELECT SUM(received_qty) FROM po_lines WHERE po_id = po.id) AS rq,
                        (SELECT COUNT(*) FROM supplier_bills b WHERE b.po_id = po.id AND b.status <> 'cancelled') AS bills
                 FROM purchase_orders po JOIN suppliers s ON s.id = po.supplier_id LEFT JOIN requisitions r ON r.id = po.requisition_id
                 WHERE $w ORDER BY po.id DESC"
            ),
            'ready' => can('purchasing.order') ? db()->all(
                "SELECT r.id, r.req_no, r.title, r.department, COALESCE(r.approved_amount, r.amount) AS amt FROM requisitions r
                 WHERE r.status = 'approved' AND r.purchase_order_id IS NULL AND r.supplier_bill_id IS NULL ORDER BY r.approved_at"
            ) : [],
        ]);
    }

    public function create(): void
    {
        $this->needsTable('purchase_orders');
        $r = db()->one("SELECT r.*, COALESCE(r.approved_amount, r.amount) AS amt FROM requisitions r WHERE r.id = ? AND r.status = 'approved' AND r.purchase_order_id IS NULL AND r.supplier_bill_id IS NULL", [(int) ($_GET['req'] ?? 0)]);
        if (!$r) {
            flash('error', 'Choose an approved requisition that has no purchase order yet.');
            redirect('purchasing');
        }
        $quotes = (int) db()->value("SELECT COUNT(*) FROM requisition_files WHERE requisition_id = ? AND kind = 'quote'", [$r['id']]);
        $this->view('purchasing/form', [
            'title' => 'New purchase order', 'r' => $r, 'quotes' => $quotes, 'need' => Purchasing::quotesNeeded((float) $r['amt']),
            'items' => db()->all('SELECT * FROM requisition_items WHERE requisition_id = ? ORDER BY id', [$r['id']]),
            'suppliers' => db()->all('SELECT id, name, supply_type FROM suppliers WHERE is_active = 1 ORDER BY name'),
        ]);
    }

    public function store(): void
    {
        $this->needsTable('purchase_orders');
        $reqId = (int) ($_POST['requisition_id'] ?? 0);
        $lines = [];
        foreach ((array) ($_POST['desc'] ?? []) as $k => $desc) {
            $desc = mb_substr(trim((string) $desc), 0, 150);
            $qty = round((float) str_replace(',', '', (string) ($_POST['qty'][$k] ?? '0')), 2);
            $price = round((float) str_replace(',', '', (string) ($_POST['price'][$k] ?? '0')), 2);
            if ($desc === '' || $qty <= 0) {
                continue;
            }
            if ($qty > 100000 || $price < 0 || $price > 10000000) {
                flash('error', 'Check the quantities and prices.');
                with_old($_POST);
                redirect('purchasing/new?req=' . $reqId);
            }
            $lines[] = ['description' => $desc, 'unit' => mb_substr(trim((string) ($_POST['unit'][$k] ?? '')), 0, 20) ?: null, 'qty' => $qty, 'unit_price' => $price];
        }
        $exp = (string) ($_POST['expected_date'] ?? '');
        [$id, $err] = Purchasing::create($reqId, (int) ($_POST['supplier_id'] ?? 0), $lines, [
            'order_date' => date('Y-m-d'), 'expected_date' => Validator::isDate($exp) ? $exp : null,
            'deliver_to' => mb_substr(trim((string) ($_POST['deliver_to'] ?? '')), 0, 150) ?: null,
            'notes' => mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 500) ?: null,
        ]);
        if ($err) {
            with_old($_POST);
            flash('error', $err);
            redirect('purchasing/new?req=' . $reqId);
        }
        audit('po_created', 'purchase_orders', $id);
        flash('success', 'Purchase order saved as a draft. Check it, then send it to the supplier.');
        redirect('purchasing/' . $id);
    }

    public function show(int $id): void
    {
        $this->canSee();
        $po = $this->find($id);
        $grns = db()->all('SELECT g.*, u.name AS by_name FROM goods_received g LEFT JOIN users u ON u.id = g.received_by WHERE g.po_id = ? ORDER BY g.id', [$id]);
        $grnLines = [];
        foreach (db()->all('SELECT gl.*, pl.description FROM grn_lines gl JOIN po_lines pl ON pl.id = gl.po_line_id JOIN goods_received g ON g.id = gl.grn_id WHERE g.po_id = ? ORDER BY gl.id', [$id]) as $l) {
            $grnLines[(int) $l['grn_id']][] = $l;
        }
        $bills = db()->all('SELECT * FROM supplier_bills WHERE po_id = ? ORDER BY id', [$id]);
        $billed = [];
        foreach (db()->all("SELECT bl.po_line_id, SUM(bl.qty) AS q FROM supplier_bill_lines bl JOIN supplier_bills b ON b.id = bl.bill_id WHERE b.po_id = ? AND b.status <> 'cancelled' GROUP BY bl.po_line_id", [$id]) as $r) {
            $billed[(int) $r['po_line_id']] = (float) $r['q'];
        }
        $this->view('purchasing/show', [
            'title' => $po['po_no'], 'po' => $po, 'lines' => Purchasing::lines($id), 'grns' => $grns, 'grnLines' => $grnLines, 'bills' => $bills, 'billed' => $billed,
            'quotes' => $po['requisition_id'] ? (int) db()->value("SELECT COUNT(*) FROM requisition_files WHERE requisition_id = ? AND kind = 'quote'", [$po['requisition_id']]) : 0,
            'need' => Purchasing::quotesNeeded((float) $po['total']),
        ]);
    }

    public function send(int $id): void
    {
        $this->needsTable('purchase_orders');
        if ($err = Purchasing::send($id)) {
            flash('error', $err);
            redirect('purchasing/' . $id);
        }
        $po = Purchasing::po($id);
        audit('po_sent', 'purchase_orders', $id);
        Notifier::inappPermission('purchasing.receive', 'Goods expected: ' . $po['po_no'], $po['supplier_name'] . ($po['expected_date'] ? ' — expected ' . fmt_date($po['expected_date']) : ''), 'purchasing/' . $id);
        flash('success', 'Purchase order marked as sent. Print it for the supplier; the storekeeper signs for the goods when they arrive.');
        redirect('purchasing/' . $id);
    }

    public function cancel(int $id): void
    {
        $this->needsTable('purchase_orders');
        $reason = mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 255);
        $err = $reason === '' ? 'Give a reason.' : Purchasing::cancel($id, $reason);
        if ($err) {
            flash('error', $err);
            redirect('purchasing/' . $id);
        }
        audit('po_cancelled', 'purchase_orders', $id, ['reason' => $reason]);
        flash('success', 'Purchase order cancelled. The requisition can be ordered again or paid another way.');
        redirect('purchasing/' . $id);
    }

    public function close(int $id): void
    {
        $this->needsTable('purchase_orders');
        $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 200);
        $err = $note === '' ? 'Say why the order is closed.' : Purchasing::close($id, $note);
        if ($err) {
            flash('error', $err);
            redirect('purchasing/' . $id);
        }
        audit('po_closed', 'purchase_orders', $id, ['note' => $note]);
        flash('success', 'Purchase order closed: nothing more is expected on it.');
        redirect('purchasing/' . $id);
    }

    public function receive(int $id): void
    {
        $this->needsTable('purchase_orders');
        $qty = [];
        foreach ((array) ($_POST['qty'] ?? []) as $lid => $q) {
            $qty[(int) $lid] = (float) str_replace(',', '', (string) $q);
        }
        [$gid, $err] = Purchasing::receive($id, $qty, (string) ($_POST['received_date'] ?? date('Y-m-d')), trim((string) ($_POST['delivery_note'] ?? '')), trim((string) ($_POST['note'] ?? '')));
        if ($err) {
            flash('error', $err);
            redirect('purchasing/' . $id);
        }
        $po = Purchasing::po($id);
        audit('grn_recorded', 'goods_received', $gid, ['po' => $po['po_no']]);
        Notifier::inappPermission('purchasing.order', 'Goods received on ' . $po['po_no'], $po['supplier_name'] . ' — ' . Purchasing::STATUS[$po['status']][0], 'purchasing/' . $id);
        flash('success', 'Goods received recorded (' . db()->value('SELECT grn_no FROM goods_received WHERE id = ?', [$gid]) . ').');
        redirect('purchasing/' . $id);
    }

    public function voidGrn(int $id): void
    {
        $this->needsTable('goods_received');
        $g = db()->one('SELECT * FROM goods_received WHERE id = ?', [$id]) ?? $this->notFound();
        $reason = mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 255);
        $err = $reason === '' ? 'Give a reason.' : Purchasing::voidGrn($id, $reason);
        if ($err) {
            flash('error', $err);
            redirect('purchasing/' . $g['po_id']);
        }
        audit('grn_voided', 'goods_received', $id, ['reason' => $reason]);
        flash('success', 'Goods received note ' . $g['grn_no'] . ' cancelled.');
        redirect('purchasing/' . $g['po_id']);
    }

    public function bill(int $id): void
    {
        $this->needsTable('supplier_bill_lines');
        $lines = [];
        foreach ((array) ($_POST['bq'] ?? []) as $lid => $q) {
            $lines[(int) $lid] = [str_replace(',', '', (string) $q), str_replace(',', '', (string) ($_POST['bp'][$lid] ?? '0'))];
        }
        [$file, $ferr] = Upload::store($_FILES['attachment'] ?? ['error' => UPLOAD_ERR_NO_FILE], 'finance');
        if ($ferr) {
            flash('error', $ferr);
            redirect('purchasing/' . $id);
        }
        [$bid, $err] = Purchasing::bill($id, $lines, [
            'bill_date' => (string) ($_POST['bill_date'] ?? ''), 'due_date' => (string) ($_POST['due_date'] ?? ''),
            'supplier_ref' => trim((string) ($_POST['supplier_ref'] ?? '')), 'no_wht' => !empty($_POST['no_wht']),
        ], $file);
        if ($err) {
            flash('error', $err);
            redirect('purchasing/' . $id);
        }
        $m = Purchasing::match($bid);
        audit('supplier_bill_created', 'supplier_bills', $bid, ['po' => $id]);
        flash($m['ok'] ? 'success' : 'warning', $m['ok'] ? 'Supplier invoice recorded — it matches the order and the goods received.' : 'Supplier invoice recorded, but it does not match yet: ' . implode('; ', $m['problems']) . '.');
        redirect('suppliers/bills/' . $bid);
    }

    public function received(): void
    {
        $this->canSee();
        $this->view('purchasing/received', [
            'title' => 'Goods received',
            'rows' => db()->all(
                "SELECT g.*, po.po_no, po.id AS po_id, s.name AS supplier_name, u.name AS by_name,
                        (SELECT COUNT(*) FROM supplier_bills b WHERE b.po_id = po.id AND b.status <> 'cancelled') AS bills
                 FROM goods_received g JOIN purchase_orders po ON po.id = g.po_id JOIN suppliers s ON s.id = po.supplier_id LEFT JOIN users u ON u.id = g.received_by
                 ORDER BY g.id DESC"
            ),
            'waiting' => db()->all(
                "SELECT po.*, s.name AS supplier_name FROM purchase_orders po JOIN suppliers s ON s.id = po.supplier_id
                 WHERE po.status IN ('sent','part') ORDER BY po.expected_date IS NULL, po.expected_date, po.id"
            ),
        ]);
    }

    public function printPo(int $id): void
    {
        $this->canSee();
        $po = $this->find($id);
        if ($po['status'] === 'draft') {
            flash('error', 'Send the purchase order before printing it for the supplier.');
            redirect('purchasing/' . $id);
        }
        $this->view('purchasing/print', ['title' => $po['po_no'], 'po' => $po, 'lines' => Purchasing::lines($id)], 'layouts/print');
    }

    public function printGrn(int $id): void
    {
        $this->canSee();
        $g = db()->one('SELECT g.*, u.name AS by_name FROM goods_received g LEFT JOIN users u ON u.id = g.received_by WHERE g.id = ?', [$id]) ?? $this->notFound();
        $this->view('purchasing/print_grn', [
            'title' => $g['grn_no'], 'g' => $g, 'po' => Purchasing::po((int) $g['po_id']),
            'lines' => db()->all('SELECT gl.*, pl.description, pl.unit, pl.qty AS ordered FROM grn_lines gl JOIN po_lines pl ON pl.id = gl.po_line_id WHERE gl.grn_id = ? ORDER BY gl.id', [$id]),
        ], 'layouts/print');
    }
}
