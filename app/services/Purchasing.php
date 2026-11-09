<?php
declare(strict_types=1);

/**
 * Purchase orders and goods received. An approved requisition becomes a purchase order (PO) to one supplier;
 * the storekeeper signs a goods received note (GRN) for what actually arrives; the supplier's invoice is recorded
 * against the PO. A bill is paid only for what was ordered, received AND invoiced, at no more than the PO price
 * (three-way match).
 */
final class Purchasing
{
    public const STATUS = [
        'draft' => ['Draft — not sent', 'neutral'], 'sent' => ['Sent — waiting for goods', 'info'], 'part' => ['Part received', 'warn'],
        'received' => ['All received', 'good'], 'closed' => ['Closed', 'good'], 'cancelled' => ['Cancelled', 'neutral'],
    ];

    private static ?bool $ready = null;

    public static function ready(): bool
    {
        if (self::$ready === null) {
            try {
                db()->value('SELECT 1 FROM purchase_orders LIMIT 1');
                self::$ready = true;
            } catch (PDOException $e) {
                self::$ready = false;
            }
        }
        return self::$ready;
    }

    public static function badge(string $status): string
    {
        [$l, $t] = self::STATUS[$status] ?? [$status, 'neutral'];
        return badge($l, $t);
    }

    public static function po(int $id, ?Database $d = null): ?array
    {
        return ($d ?: db())->one(
            'SELECT po.*, s.name AS supplier_name, s.phone AS supplier_phone, s.address AS supplier_address, s.tin, s.supply_type,
                    r.req_no, r.title AS req_title, r.requested_by, c.name AS category_name, u.name AS created_name, su.name AS sent_name
             FROM purchase_orders po JOIN suppliers s ON s.id = po.supplier_id LEFT JOIN requisitions r ON r.id = po.requisition_id
             LEFT JOIN fin_categories c ON c.id = po.category_id LEFT JOIN users u ON u.id = po.created_by LEFT JOIN users su ON su.id = po.sent_by
             WHERE po.id = ?',
            [$id]
        );
    }

    public static function lines(int $poId, ?Database $d = null): array
    {
        return ($d ?: db())->all('SELECT * FROM po_lines WHERE po_id = ? ORDER BY id', [$poId]);
    }

    /** Quotations a requisition needs: 1 above "req_require_quote_above", 3 above "pur_quote3_above". */
    public static function quotesNeeded(float $amount): int
    {
        $one = (float) setting('req_require_quote_above', '1000');
        $three = (float) setting('pur_quote3_above', '5000');
        return $three > 0 && $amount > $three ? 3 : ($one > 0 && $amount > $one ? 1 : 0);
    }

    /** Create a draft PO from an approved requisition. $lines = [[description, unit, qty, unit_price], …]. */
    public static function create(int $reqId, int $supplierId, array $lines, array $extra): array
    {
        try {
            $id = db()->transaction(function (Database $d) use ($reqId, $supplierId, $lines, $extra) {
                $r = $d->one('SELECT * FROM requisitions WHERE id = ? FOR UPDATE', [$reqId]);
                if (!$r || $r['status'] !== 'approved') {
                    throw new DomainException('Only an approved requisition can become a purchase order.');
                }
                if (!empty($r['purchase_order_id']) && $d->value("SELECT id FROM purchase_orders WHERE id = ? AND status <> 'cancelled'", [$r['purchase_order_id']])) {
                    throw new DomainException('This requisition already has a purchase order.');
                }
                if (!empty($r['supplier_bill_id'])) {
                    throw new DomainException('This requisition is already being paid through a supplier bill.');
                }
                if (!$d->value('SELECT id FROM suppliers WHERE id = ? AND is_active = 1', [$supplierId])) {
                    throw new DomainException('Choose the supplier.');
                }
                if (!$lines) {
                    throw new DomainException('Add at least one item.');
                }
                $total = round(array_sum(array_map(fn($l) => $l['qty'] * $l['unit_price'], $lines)), 2);
                if ($total > (float) ($r['approved_amount'] ?? $r['amount']) + 0.004) {
                    throw new DomainException('The order (' . Fees::money($total) . ') is more than the approved ' . Fees::money($r['approved_amount'] ?? $r['amount']) . '. Ask for a new approval.');
                }
                $id = $d->insert('purchase_orders', [
                    'po_no' => Fees::nextNumber($d, 'PO'), 'requisition_id' => $reqId, 'supplier_id' => $supplierId,
                    'order_date' => $extra['order_date'] ?? date('Y-m-d'), 'expected_date' => $extra['expected_date'] ?? null,
                    'department' => $r['department'], 'category_id' => $r['category_id'], 'deliver_to' => $extra['deliver_to'] ?? null,
                    'notes' => $extra['notes'] ?? null, 'total' => $total, 'created_by' => Auth::id(),
                ]);
                foreach ($lines as $l) {
                    $d->insert('po_lines', ['po_id' => $id, 'description' => $l['description'], 'unit' => $l['unit'] ?? null, 'qty' => $l['qty'], 'unit_price' => $l['unit_price']]);
                }
                $d->update('requisitions', ['purchase_order_id' => $id], 'id = ?', [$reqId]);
                Requisition::log($d, $reqId, 'po', 'approved', 'approved', 'Purchase order ' . $d->value('SELECT po_no FROM purchase_orders WHERE id = ?', [$id]) . ' made');
                return $id;
            });
            return [$id, null];
        } catch (DomainException $e) {
            return [null, $e->getMessage()];
        }
    }

    /** Send a draft PO to the supplier (checks the quotations). */
    public static function send(int $id): ?string
    {
        $po = self::po($id);
        if (!$po || $po['status'] !== 'draft') {
            return 'Only a draft purchase order can be sent.';
        }
        $need = self::quotesNeeded((float) $po['total']);
        $have = $po['requisition_id'] ? (int) db()->value("SELECT COUNT(*) FROM requisition_files WHERE requisition_id = ? AND kind = 'quote'", [$po['requisition_id']]) : 0;
        if ($have < $need) {
            return 'Orders of ' . Fees::money($po['total']) . ' need ' . $need . ' quotation' . ($need > 1 ? 's' : '') . ' attached to the requisition (' . $have . ' attached). Attach them on ' . ($po['req_no'] ?? 'the requisition') . ', then send.';
        }
        db()->update('purchase_orders', ['status' => 'sent', 'sent_by' => Auth::id(), 'sent_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        return null;
    }

    public static function cancel(int $id, string $reason): ?string
    {
        return db()->transaction(function (Database $d) use ($id, $reason) {
            $po = $d->one('SELECT * FROM purchase_orders WHERE id = ? FOR UPDATE', [$id]);
            if (!$po || in_array($po['status'], ['closed', 'cancelled'], true)) {
                return 'This purchase order cannot be cancelled.';
            }
            if ($d->value("SELECT 1 FROM goods_received WHERE po_id = ? AND status = 'posted'", [$id])) {
                return 'Goods have been received on this order. Close it instead (what is not delivered is no longer expected).';
            }
            if ($d->value("SELECT 1 FROM supplier_bills WHERE po_id = ? AND status <> 'cancelled'", [$id])) {
                return 'A supplier invoice is recorded on this order. Cancel the bill first.';
            }
            $d->update('purchase_orders', ['status' => 'cancelled', 'cancel_reason' => mb_substr($reason, 0, 255)], 'id = ?', [$id]);
            if ($po['requisition_id']) {
                $d->update('requisitions', ['purchase_order_id' => null], 'id = ?', [$po['requisition_id']]);
                Requisition::log($d, (int) $po['requisition_id'], 'po_cancelled', 'approved', 'approved', 'Purchase order ' . $po['po_no'] . ' cancelled: ' . $reason);
            }
            return null;
        });
    }

    /** Close a PO: nothing more is expected (part delivery accepted). */
    public static function close(int $id, string $note): ?string
    {
        $po = db()->one('SELECT * FROM purchase_orders WHERE id = ?', [$id]);
        if (!$po || !in_array($po['status'], ['sent', 'part', 'received'], true)) {
            return 'This purchase order cannot be closed now.';
        }
        db()->update('purchase_orders', ['status' => 'closed', 'notes' => mb_substr(trim(($po['notes'] ? $po['notes'] . ' · ' : '') . 'Closed: ' . $note), 0, 500)], 'id = ?', [$id]);
        return null;
    }

    /** Record goods received. $qty = po_line_id => quantity. Returns [grn id, null] or [null, error]. */
    public static function receive(int $poId, array $qty, string $date, string $deliveryNote, string $note): array
    {
        try {
            $id = db()->transaction(function (Database $d) use ($poId, $qty, $date, $deliveryNote, $note) {
                $po = $d->one('SELECT * FROM purchase_orders WHERE id = ? FOR UPDATE', [$poId]);
                if (!$po || !in_array($po['status'], ['sent', 'part'], true)) {
                    throw new DomainException('Goods can be received only on a purchase order that has been sent.');
                }
                if (Auth::role() !== 'super_admin' && in_array((int) Auth::id(), [(int) $po['created_by'], (int) $po['sent_by']], true)) {
                    throw new DomainException('The person who made or sent the order cannot sign for the goods. The storekeeper (or another officer) must do it.');
                }
                if (!Validator::isDate($date) || $date > date('Y-m-d') || $date < $po['order_date']) {
                    throw new DomainException('Enter the date the goods arrived (between the order date and today).');
                }
                $lines = array_column(self::lines($poId, $d), null, 'id');
                $rows = [];
                foreach ($qty as $lid => $q) {
                    $q = round((float) $q, 2);
                    if ($q <= 0) {
                        continue;
                    }
                    $l = $lines[(int) $lid] ?? null;
                    if (!$l) {
                        continue;
                    }
                    $open = round((float) $l['qty'] - (float) $l['received_qty'], 2);
                    if ($q > $open + 0.004) {
                        throw new DomainException('Only ' . rtrim(rtrim(number_format($open, 2), '0'), '.') . ' of "' . $l['description'] . '" are still expected.');
                    }
                    $rows[(int) $lid] = $q;
                }
                if (!$rows) {
                    throw new DomainException('Enter how many of each item arrived.');
                }
                $id = $d->insert('goods_received', [
                    'grn_no' => Fees::nextNumber($d, 'GRN'), 'po_id' => $poId, 'received_date' => $date,
                    'delivery_note' => mb_substr($deliveryNote, 0, 60) ?: null, 'note' => mb_substr($note, 0, 255) ?: null, 'received_by' => Auth::id(),
                ]);
                foreach ($rows as $lid => $q) {
                    $d->insert('grn_lines', ['grn_id' => $id, 'po_line_id' => $lid, 'qty' => $q]);
                }
                self::refresh($d, $poId);
                return $id;
            });
            return [$id, null];
        } catch (DomainException $e) {
            return [null, $e->getMessage()];
        }
    }

    public static function voidGrn(int $grnId, string $reason): ?string
    {
        try {
            return self::voidGrnTx($grnId, $reason);
        } catch (DomainException $e) {
            return 'The supplier has already been paid for these goods. Cancel that payment first.';
        }
    }

    private static function voidGrnTx(int $grnId, string $reason): ?string
    {
        return db()->transaction(function (Database $d) use ($grnId, $reason) {
            $g = $d->one('SELECT * FROM goods_received WHERE id = ? FOR UPDATE', [$grnId]);
            if (!$g || $g['status'] !== 'posted') {
                return 'This goods received note cannot be cancelled.';
            }
            $d->update('goods_received', ['status' => 'void', 'void_reason' => mb_substr($reason, 0, 255)], 'id = ?', [$grnId]);
            self::refresh($d, (int) $g['po_id']);
            // paid more than what is now received?
            foreach ($d->all("SELECT id FROM supplier_bills WHERE po_id = ? AND status <> 'cancelled'", [$g['po_id']]) as $b) {
                $m = self::match((int) $b['id'], $d);
                if ($m['paid'] > $m['payable'] + 0.004) {
                    throw new DomainException('cannot');
                }
            }
            return null;
        });
    }

    /** Received quantities and status of a PO. */
    public static function refresh(Database $d, int $poId): void
    {
        $po = $d->one('SELECT * FROM purchase_orders WHERE id = ?', [$poId]);
        if (!$po) {
            return;
        }
        foreach (self::lines($poId, $d) as $l) {
            $rec = (float) $d->value("SELECT COALESCE(SUM(gl.qty), 0) FROM grn_lines gl JOIN goods_received g ON g.id = gl.grn_id WHERE gl.po_line_id = ? AND g.status = 'posted'", [$l['id']]);
            $d->update('po_lines', ['received_qty' => $rec], 'id = ?', [$l['id']]);
        }
        if (in_array($po['status'], ['draft', 'cancelled', 'closed'], true)) {
            return;
        }
        $t = $d->one('SELECT SUM(qty) AS q, SUM(received_qty) AS r FROM po_lines WHERE po_id = ?', [$poId]);
        $status = (float) $t['r'] <= 0.004 ? 'sent' : ((float) $t['r'] + 0.004 >= (float) $t['q'] ? 'received' : 'part');
        if ($status === 'received') { // everything received and every bill on it paid: the order is done
            $open = (int) $d->value("SELECT COUNT(*) FROM supplier_bills WHERE po_id = ? AND status IN ('unpaid','part')", [$poId]);
            $billed = (float) $d->value("SELECT COALESCE(SUM(amount), 0) FROM supplier_bills WHERE po_id = ? AND status = 'paid'", [$poId]);
            if (!$open && $billed > 0) {
                $status = 'closed';
            }
        }
        $d->update('purchase_orders', ['status' => $status], 'id = ?', [$poId]);
    }

    /**
     * Three-way match of a bill recorded on a PO: per line ordered / received / invoiced, PO price / invoice price,
     * and what may be paid (received quantity not already matched by an earlier bill, at the lower of the two prices).
     * @return array{lines: array, payable: float, paid: float, ok: bool, problems: array}
     */
    public static function match(int $billId, ?Database $d = null): array
    {
        $d = $d ?: db();
        $b = $d->one('SELECT * FROM supplier_bills WHERE id = ?', [$billId]);
        $out = ['lines' => [], 'payable' => 0.0, 'paid' => (float) ($b['paid_gross'] ?? 0), 'ok' => true, 'problems' => []];
        if (!$b || !$b['po_id']) {
            return $out;
        }
        $po = array_column(self::lines((int) $b['po_id'], $d), null, 'id');
        $payable = 0.0;
        foreach ($d->all('SELECT * FROM supplier_bill_lines WHERE bill_id = ? ORDER BY id', [$billId]) as $l) {
            $pl = $po[(int) $l['po_line_id']] ?? null;
            $ordered = $pl ? (float) $pl['qty'] : 0.0;
            $received = $pl ? (float) $pl['received_qty'] : 0.0;
            $earlier = $pl ? (float) $d->value("SELECT COALESCE(SUM(bl.qty), 0) FROM supplier_bill_lines bl JOIN supplier_bills sb ON sb.id = bl.bill_id WHERE bl.po_line_id = ? AND sb.status <> 'cancelled' AND sb.id < ?", [$pl['id'], $billId]) : 0.0;
            $poPrice = $pl ? (float) $pl['unit_price'] : 0.0;
            $qty = (float) $l['qty'];
            $price = (float) $l['unit_price'];
            $okQty = max(0.0, min($qty, $received - $earlier));
            $value = round($okQty * min($price, $poPrice), 2);
            $payable += $value;
            $issues = [];
            if (!$pl) {
                $issues[] = 'not on the order';
            }
            if ($qty > $okQty + 0.004) {
                $issues[] = rtrim(rtrim(number_format($qty - $okQty, 2), '0'), '.') . ' not received';
            }
            if ($price > $poPrice + 0.004) {
                $issues[] = 'price above the order';
            }
            if ($earlier + $qty > $ordered + 0.004) {
                $issues[] = 'more than ordered';
            }
            if ($issues) {
                $out['ok'] = false;
                $out['problems'][] = $l['description'] . ': ' . implode(', ', $issues);
            }
            $out['lines'][] = $l + ['ordered' => $ordered, 'received' => $received, 'earlier' => $earlier, 'po_price' => $poPrice, 'ok_qty' => $okQty, 'value' => $value, 'issues' => $issues];
        }
        $out['payable'] = round($payable, 2);
        return $out;
    }

    /** Record the supplier's invoice against a PO. $lines = po_line_id => [qty, unit_price]. */
    public static function bill(int $poId, array $lines, array $in, ?array $file): array
    {
        try {
            $id = db()->transaction(function (Database $d) use ($poId, $lines, $in, $file) {
                $po = $d->one('SELECT po.*, s.supply_type, s.name AS supplier_name FROM purchase_orders po JOIN suppliers s ON s.id = po.supplier_id WHERE po.id = ? FOR UPDATE', [$poId]);
                if (!$po || in_array($po['status'], ['draft', 'cancelled'], true)) {
                    throw new DomainException('Record the supplier\'s invoice on an order that has been sent.');
                }
                if (!Validator::isDate($in['bill_date'] ?? '') || $in['bill_date'] > date('Y-m-d')) {
                    throw new DomainException('Enter the date on the supplier\'s invoice (not in the future).');
                }
                if (($in['due_date'] ?? '') !== '' && (!Validator::isDate($in['due_date']) || $in['due_date'] < $in['bill_date'])) {
                    throw new DomainException('The due date must be on or after the invoice date.');
                }
                $pl = array_column(self::lines($poId, $d), null, 'id');
                $rows = [];
                foreach ($lines as $lid => [$q, $p]) {
                    $q = round((float) $q, 2);
                    $p = round((float) $p, 2);
                    if ($q <= 0 || !isset($pl[(int) $lid])) {
                        continue;
                    }
                    if ($p < 0 || $p > 10000000) {
                        throw new DomainException('Check the prices on the invoice.');
                    }
                    $rows[(int) $lid] = [$q, $p];
                }
                if (!$rows) {
                    throw new DomainException('Enter the quantities on the supplier\'s invoice.');
                }
                $amount = round(array_sum(array_map(fn($r) => $r[0] * $r[1], $rows)), 2);
                $type = $po['supply_type'] ?: 'goods';
                [$rate, $wht] = Suppliers::wht($amount, $type, !empty($in['no_wht']));
                $id = $d->insert('supplier_bills', [
                    'bill_no' => Fees::nextNumber($d, 'SB'), 'supplier_id' => $po['supplier_id'], 'supplier_ref' => mb_substr((string) ($in['supplier_ref'] ?? ''), 0, 60) ?: null,
                    'bill_date' => $in['bill_date'], 'due_date' => ($in['due_date'] ?? '') ?: null, 'category_id' => $po['category_id'],
                    'description' => 'Goods on ' . $po['po_no'], 'amount' => $amount, 'supply_type' => $type, 'wht_rate' => $rate, 'wht_amount' => $wht,
                    'requisition_id' => null, 'po_id' => $poId, 'department' => $po['department'],
                    'attachment' => $file['stored_name'] ?? null, 'attachment_name' => $file['original_name'] ?? null, 'attachment_mime' => $file['mime'] ?? null,
                    'created_by' => Auth::id(),
                ]);
                foreach ($rows as $lid => [$q, $p]) {
                    $d->insert('supplier_bill_lines', ['bill_id' => $id, 'po_line_id' => $lid, 'description' => $pl[$lid]['description'], 'qty' => $q, 'unit_price' => $p]);
                }
                return $id;
            });
            return [$id, null];
        } catch (DomainException $e) {
            return [null, $e->getMessage()];
        }
    }

    /** Money promised on open POs and not yet invoiced, per department. */
    public static function openValueByDepartment(): array
    {
        if (!self::ready()) {
            return [];
        }
        $out = [];
        foreach (db()->all(
            "SELECT po.department, SUM(po.total - COALESCE((SELECT SUM(b.amount) FROM supplier_bills b WHERE b.po_id = po.id AND b.status <> 'cancelled'), 0)) AS amt
             FROM purchase_orders po WHERE po.status IN ('draft','sent','part','received') AND po.department IS NOT NULL GROUP BY po.department"
        ) as $r) {
            $out[$r['department']] = max(0.0, (float) $r['amt']);
        }
        return $out;
    }
}
