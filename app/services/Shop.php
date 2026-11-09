<?php
declare(strict_types=1);

/**
 * School shop: uniforms, books and stationery. A parent's order becomes a bill (invoice) that they pay online by
 * Mobile Money through the usual payment page; stock is held while the order waits for payment (3 days).
 */
final class Shop
{
    public const CATEGORIES = ['uniform' => 'Uniforms', 'book' => 'Books', 'stationery' => 'Stationery', 'sports' => 'Sports wear', 'other' => 'Other'];
    public const STATUS = [
        'pending_payment' => ['Waiting for payment', 'warn'],
        'paid'            => ['Paid — being prepared', 'info'],
        'ready'           => ['Ready to collect', 'good'],
        'collected'       => ['Collected', 'neutral'],
        'cancelled'       => ['Cancelled', 'neutral'],
    ];
    public const HOLD_DAYS = 3;
    public const MAX_QTY = 20;

    public static function products(bool $activeOnly = true): array
    {
        return db()->all('SELECT * FROM shop_products' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY FIELD(category, "uniform", "sports", "book", "stationery", "other"), name');
    }

    public static function sizes(array $p): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $p['sizes'])), fn($s) => $s !== ''));
    }

    /**
     * Place an order for a pupil: $items = [[product_id, size, qty], …]. Holds stock, creates the bill.
     * Returns [order_id, error]. $paid = [method, payer] for a counter sale paid on the spot.
     */
    public static function place(array $pupil, array $items, string $channel, ?array $paid = null): array
    {
        if (!$items) {
            return [null, 'The basket is empty.'];
        }
        try {
            $orderId = db()->transaction(function (Database $d) use ($pupil, $items, $channel) {
                $lines = [];
                $total = 0.0;
                foreach ($items as [$pid, $size, $qty]) {
                    $p = $d->one('SELECT * FROM shop_products WHERE id = ? AND is_active = 1 FOR UPDATE', [(int) $pid]);
                    $qty = (int) $qty;
                    if (!$p || $qty < 1 || $qty > self::MAX_QTY) {
                        throw new DomainException('An item in the basket is no longer available.');
                    }
                    $sizes = self::sizes($p);
                    if ($sizes && !in_array($size, $sizes, true)) {
                        throw new DomainException('Choose a size for ' . $p['name'] . '.');
                    }
                    if ((int) $p['track_stock'] && (int) $p['stock_qty'] < $qty) {
                        throw new DomainException('Only ' . max(0, (int) $p['stock_qty']) . ' × ' . $p['name'] . ' left in stock.');
                    }
                    $lines[] = [$p, $sizes ? $size : null, $qty];
                    $total += $qty * (float) $p['price'];
                }
                $no = Fees::nextNumber($d, 'SHP');
                $invId = Fees::insertInvoice($d, $pupil, current_term(), 'other', 'School shop order ' . $no, date('Y-m-d', strtotime('+' . self::HOLD_DAYS . ' days')));
                $d->update('invoices', ['source' => 'shop'], 'id = ?', [$invId]);
                $oid = $d->insert('shop_orders', ['order_no' => $no, 'pupil_id' => $pupil['id'], 'ordered_by' => Auth::id(), 'channel' => $channel, 'total' => round($total, 2), 'invoice_id' => $invId]);
                foreach ($lines as [$p, $size, $qty]) {
                    $d->insert('shop_order_lines', ['order_id' => $oid, 'product_id' => $p['id'], 'name' => $p['name'], 'size' => $size, 'qty' => $qty, 'price' => $p['price']]);
                    $d->insert('invoice_lines', ['invoice_id' => $invId, 'description' => mb_substr($p['name'] . ($size ? " (size $size)" : '') . ($qty > 1 ? " × $qty" : ''), 0, 150), 'amount' => round($qty * (float) $p['price'], 2)]);
                    if ((int) $p['track_stock']) {
                        $d->run('UPDATE shop_products SET stock_qty = stock_qty - ? WHERE id = ?', [$qty, $p['id']]);
                        $d->insert('shop_stock_moves', ['product_id' => $p['id'], 'qty' => -$qty, 'reason' => 'Order ' . $no, 'order_id' => $oid, 'created_by' => Auth::id()]);
                    }
                }
                Fees::recalc($invId, $d);
                return $oid;
            });
        } catch (DomainException $e) {
            return [null, $e->getMessage()];
        }
        if ($paid) {
            $o = db()->one('SELECT * FROM shop_orders WHERE id = ?', [$orderId]);
            Fees::recordPayment((int) $o['invoice_id'], (float) $o['total'], $paid[0], ['payer_name' => $paid[1] ?? null]);
            db()->update('shop_orders', ['status' => 'collected', 'paid_at' => date('Y-m-d H:i:s'), 'collected_at' => date('Y-m-d H:i:s'), 'collected_by' => $paid[1] ?: 'At the counter'], 'id = ?', [$orderId]);
        }
        return [$orderId, null];
    }

    /** Cancel an order: give the stock back and cancel the bill (only when nothing was paid). Returns an error or null. */
    public static function cancel(int $orderId, string $reason): ?string
    {
        return db()->transaction(function (Database $d) use ($orderId, $reason) {
            $o = $d->one('SELECT * FROM shop_orders WHERE id = ? FOR UPDATE', [$orderId]);
            if (!$o || in_array($o['status'], ['cancelled', 'collected'], true)) {
                return 'This order cannot be cancelled.';
            }
            $paid = $o['invoice_id'] ? (float) $d->value('SELECT paid FROM invoices WHERE id = ?', [$o['invoice_id']]) : 0.0;
            if ($paid > 0) {
                return 'Money has been paid on this order. Reverse the receipt under Fees first (refund), then cancel.';
            }
            foreach ($d->all('SELECT l.*, p.track_stock FROM shop_order_lines l LEFT JOIN shop_products p ON p.id = l.product_id WHERE l.order_id = ?', [$orderId]) as $l) {
                if ($l['product_id'] && (int) $l['track_stock']) {
                    $d->run('UPDATE shop_products SET stock_qty = stock_qty + ? WHERE id = ?', [$l['qty'], $l['product_id']]);
                    $d->insert('shop_stock_moves', ['product_id' => $l['product_id'], 'qty' => (int) $l['qty'], 'reason' => 'Order ' . $o['order_no'] . ' cancelled', 'order_id' => $orderId, 'created_by' => Auth::id()]);
                }
            }
            if ($o['invoice_id']) {
                $d->update('invoices', ['status' => 'cancelled', 'notes' => mb_substr('Shop order cancelled: ' . $reason, 0, 255)], 'id = ?', [$o['invoice_id']]);
            }
            $d->update('shop_orders', ['status' => 'cancelled', 'cancelled_at' => date('Y-m-d H:i:s'), 'cancel_reason' => mb_substr($reason, 0, 255)], 'id = ?', [$orderId]);
            return null;
        });
    }

    /** Orders whose bill has been paid move to "paid"; unpaid orders older than the hold time are cancelled. */
    public static function sync(): int
    {
        try {
            $rows = db()->all("SELECT o.id, o.order_no, i.status AS inv_status, o.created_at, i.paid FROM shop_orders o JOIN invoices i ON i.id = o.invoice_id WHERE o.status = 'pending_payment'");
        } catch (PDOException $e) {
            return 0;
        }
        $n = 0;
        foreach ($rows as $r) {
            if ($r['inv_status'] === 'paid') {
                if (db()->update('shop_orders', ['status' => 'paid', 'paid_at' => date('Y-m-d H:i:s')], "id = ? AND status = 'pending_payment'", [$r['id']])) {
                    Notifier::inappPermission('shop.manage', 'Shop order paid: ' . $r['order_no'], 'Prepare it for collection.', url('shop/admin/orders/' . $r['id']));
                    $n++;
                }
            } elseif ((float) $r['paid'] <= 0 && strtotime($r['created_at']) < time() - self::HOLD_DAYS * 86400) {
                self::cancel((int) $r['id'], 'Not paid within ' . self::HOLD_DAYS . ' days');
                $n++;
            }
        }
        return $n;
    }

    /** Parent sign-ins of a pupil (to tell them the order is ready). */
    public static function parentUsers(int $pupilId): array
    {
        return array_map('intval', array_column(db()->all('SELECT g.user_id FROM pupil_guardian pg JOIN guardians g ON g.id = pg.guardian_id WHERE pg.pupil_id = ? AND g.user_id IS NOT NULL', [$pupilId]), 'user_id'));
    }
}
