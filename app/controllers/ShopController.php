<?php
declare(strict_types=1);

/**
 * E-Commerce — the school shop. Parents order uniforms, books and stationery for their child and pay online by
 * Mobile Money; the Bursar prepares the order, marks it ready (the parent gets an SMS) and hands it over.
 * The Bursar can also sell at the counter for cash / MoMo, and manages products, prices and stock.
 */
class ShopController extends Controller
{
    public function __construct()
    {
        $this->needsTable('shop_orders');
        Shop::sync();
    }

    // ---------------------------------------------------------------- parents

    public function index(): void
    {
        $cat = isset(Shop::CATEGORIES[$_GET['category'] ?? '']) ? (string) $_GET['category'] : '';
        $products = array_values(array_filter(Shop::products(), fn($p) => $cat === '' || $p['category'] === $cat));
        $this->view('shop/index', ['title' => 'School shop', 'products' => $products, 'cat' => $cat, 'cart' => $this->cartLines(), 'children' => $this->children()]);
    }

    public function cart(): void
    {
        $this->view('shop/cart', ['title' => 'Your basket', 'lines' => $this->cartLines(), 'children' => $this->children()]);
    }

    public function updateCart(): void
    {
        $cart = $_SESSION['shop_cart'] ?? [];
        if (isset($_POST['product_id'])) { // add
            $p = db()->one('SELECT * FROM shop_products WHERE id = ? AND is_active = 1', [(int) $_POST['product_id']]);
            $size = trim((string) ($_POST['size'] ?? ''));
            $qty = max(1, min(Shop::MAX_QTY, (int) ($_POST['qty'] ?? 1)));
            if (!$p) {
                flash('error', 'That item is not available.');
                redirect('shop');
            }
            if (Shop::sizes($p) && !in_array($size, Shop::sizes($p), true)) {
                flash('error', 'Choose a size for ' . $p['name'] . '.');
                redirect('shop');
            }
            $key = $p['id'] . '|' . (Shop::sizes($p) ? $size : '');
            $cart[$key] = min(Shop::MAX_QTY, ($cart[$key] ?? 0) + $qty);
            $_SESSION['shop_cart'] = $cart;
            flash('success', $p['name'] . ' added to the basket.');
            redirect('shop', isset($_POST['category']) && $_POST['category'] !== '' ? ['category' => $_POST['category']] : []);
        }
        foreach ((array) ($_POST['qty'] ?? []) as $key => $q) { // update quantities (0 removes)
            $q = (int) $q;
            if (!isset($cart[$key])) {
                continue;
            }
            if ($q <= 0) {
                unset($cart[$key]);
            } else {
                $cart[$key] = min(Shop::MAX_QTY, $q);
            }
        }
        $_SESSION['shop_cart'] = $cart;
        redirect('shop/cart');
    }

    public function checkout(): void
    {
        $children = array_column($this->children(), null, 'id');
        $pid = (int) ($_POST['pupil_id'] ?? 0);
        if (!isset($children[$pid])) {
            flash('error', 'Choose the child the order is for.');
            redirect('shop/cart');
        }
        $items = [];
        foreach ($_SESSION['shop_cart'] ?? [] as $key => $qty) {
            [$prod, $size] = explode('|', (string) $key, 2) + [1 => ''];
            $items[] = [(int) $prod, $size, (int) $qty];
        }
        [$oid, $err] = Shop::place($children[$pid], $items, 'online');
        if ($err) {
            flash('error', $err);
            redirect('shop/cart');
        }
        unset($_SESSION['shop_cart']);
        audit('shop_order_placed', 'shop_orders', $oid);
        $inv = db()->one('SELECT i.* FROM invoices i JOIN shop_orders o ON o.invoice_id = i.id WHERE o.id = ?', [$oid]);
        header('Location: ' . request_url('pay/' . $inv['public_key']), true, 302); // pay online (Mobile Money) on the usual payment page, same address the parent is using
        exit;
    }

    public function orders(): void
    {
        $ids = array_map('intval', array_keys(array_column($this->children(), null, 'id'))) ?: [0];
        $rows = db()->all(
            'SELECT o.*, p.first_name, p.last_name, i.public_key, (SELECT GROUP_CONCAT(CONCAT(l.qty, "× ", l.name, IF(l.size IS NULL, "", CONCAT(" (", l.size, ")"))) SEPARATOR ", ") FROM shop_order_lines l WHERE l.order_id = o.id) AS items
             FROM shop_orders o JOIN pupils p ON p.id = o.pupil_id LEFT JOIN invoices i ON i.id = o.invoice_id WHERE o.pupil_id IN (' . implode(',', $ids) . ') ORDER BY o.created_at DESC LIMIT 50'
        );
        $this->view('shop/orders', ['title' => 'My shop orders', 'rows' => $rows]);
    }

    public function parentCancel(int $id): void
    {
        $ids = array_map('intval', array_keys(array_column($this->children(), null, 'id')));
        $o = db()->one('SELECT * FROM shop_orders WHERE id = ?', [$id]) ?? $this->notFound();
        if (!in_array((int) $o['pupil_id'], $ids, true) || $o['status'] !== 'pending_payment') {
            $this->forbidden();
        }
        $err = Shop::cancel($id, 'Cancelled by the parent');
        audit('shop_order_cancelled', 'shop_orders', $id, ['by' => 'parent']);
        flash($err ? 'error' : 'success', $err ?: 'Order cancelled.');
        redirect('shop/orders');
    }

    // ---------------------------------------------------------------- Bursar / Head

    public function admin(): void
    {
        $status = isset(Shop::STATUS[$_GET['status'] ?? '']) ? (string) $_GET['status'] : 'paid';
        [$rows, $pager] = paginate(
            'SELECT o.*, p.first_name, p.last_name, p.admission_no, (SELECT SUM(l.qty) FROM shop_order_lines l WHERE l.order_id = o.id) AS items FROM shop_orders o JOIN pupils p ON p.id = o.pupil_id WHERE o.status = ? ORDER BY o.created_at',
            [$status], 30
        );
        $counts = array_column(db()->all('SELECT status, COUNT(*) AS n FROM shop_orders GROUP BY status'), 'n', 'status');
        $term = current_term();
        $sales = (float) db()->value("SELECT COALESCE(SUM(total), 0) FROM shop_orders WHERE status IN ('paid','ready','collected') AND paid_at >= ?", [$term['start_date'] ?? date('Y-01-01')]);
        $low = db()->all('SELECT * FROM shop_products WHERE is_active = 1 AND track_stock = 1 AND stock_qty <= reorder_level ORDER BY stock_qty');
        $this->view('shop/admin', ['title' => 'School shop — orders', 'rows' => $rows, 'pager' => $pager, 'status' => $status, 'counts' => $counts, 'sales' => $sales, 'low' => $low]);
    }

    public function order(int $id): void
    {
        $o = db()->one('SELECT o.*, p.first_name, p.last_name, p.admission_no, u.name AS ordered_name, i.invoice_no, i.status AS inv_status, i.paid AS inv_paid FROM shop_orders o JOIN pupils p ON p.id = o.pupil_id
                        LEFT JOIN users u ON u.id = o.ordered_by LEFT JOIN invoices i ON i.id = o.invoice_id WHERE o.id = ?', [$id]) ?? $this->notFound();
        $this->view('shop/order', ['title' => $o['order_no'], 'o' => $o, 'lines' => db()->all('SELECT * FROM shop_order_lines WHERE order_id = ?', [$id]), 'guardian' => Fees::guardian((int) $o['pupil_id'])]);
    }

    public function orderAction(int $id): void
    {
        $o = db()->one('SELECT o.*, p.first_name FROM shop_orders o JOIN pupils p ON p.id = o.pupil_id WHERE o.id = ?', [$id]) ?? $this->notFound();
        $act = (string) ($_POST['action'] ?? '');
        if ($act === 'ready' && $o['status'] === 'paid') {
            db()->update('shop_orders', ['status' => 'ready', 'ready_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
            $g = Fees::guardian((int) $o['pupil_id']);
            if ($g && $g['phone']) {
                Notifier::smsTemplate('shop_ready', $g['phone'], ['guardian' => $g['name'], 'pupil' => $o['first_name'], 'order' => $o['order_no']], ['name' => $g['name'], 'entity' => 'shop_orders', 'entity_id' => $id]);
            }
            if ($u = Shop::parentUsers((int) $o['pupil_id'])) {
                Notifier::inapp($u, 'Shop order ready: ' . $o['order_no'], 'Collect it at the accounts office.', url('shop/orders'));
            }
            flash('success', 'Marked ready; the parent has been told.');
        } elseif ($act === 'collected' && in_array($o['status'], ['paid', 'ready'], true)) {
            $by = mb_substr(trim((string) ($_POST['collected_by'] ?? '')), 0, 120);
            if ($by === '') {
                flash('error', 'Write who collected it.');
                redirect('shop/admin/orders/' . $id);
            }
            db()->update('shop_orders', ['status' => 'collected', 'collected_at' => date('Y-m-d H:i:s'), 'collected_by' => $by], 'id = ?', [$id]);
            flash('success', 'Handed over.');
        } elseif ($act === 'cancel') {
            $reason = trim((string) ($_POST['reason'] ?? ''));
            $err = $reason === '' ? 'Give a reason for cancelling.' : Shop::cancel($id, $reason);
            flash($err ? 'error' : 'success', $err ?: 'Order cancelled; stock returned and the bill cancelled.');
        } else {
            flash('error', 'That cannot be done for an order that is "' . Shop::STATUS[$o['status']][0] . '".');
        }
        audit('shop_order_' . preg_replace('/\W/', '', $act), 'shop_orders', $id);
        redirect('shop/admin/orders/' . $id);
    }

    public function products(): void
    {
        $this->view('shop/products', ['title' => 'Shop products & stock', 'rows' => Shop::products(false)]);
    }

    public function saveProduct(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 120);
        $cat = (string) ($_POST['category'] ?? '');
        $price = trim((string) ($_POST['price'] ?? ''));
        $err = null;
        if ($name === '') {
            $err = 'Give the product a name.';
        } elseif (db()->value('SELECT 1 FROM shop_products WHERE name = ? AND id <> ?', [$name, $id])) {
            $err = 'Another product already has that name.';
        } elseif (!isset(Shop::CATEGORIES[$cat])) {
            $err = 'Choose a category.';
        } elseif (!is_numeric($price) || (float) $price <= 0 || (float) $price > 100000) {
            $err = 'Enter a price above 0.';
        }
        if ($err) {
            with_old($_POST);
            flash('error', $err);
            redirect('shop/admin/products');
        }
        $sizes = implode(',', array_slice(array_values(array_filter(array_map(fn($s) => mb_substr(trim($s), 0, 20), explode(',', (string) ($_POST['sizes'] ?? ''))), fn($s) => $s !== '')), 0, 12));
        $data = ['name' => $name, 'category' => $cat, 'price' => round((float) $price, 2), 'description' => mb_substr(trim((string) ($_POST['description'] ?? '')), 0, 255) ?: null,
            'sizes' => $sizes ?: null, 'track_stock' => empty($_POST['track_stock']) ? 0 : 1, 'reorder_level' => max(0, (int) ($_POST['reorder_level'] ?? 5)), 'is_active' => empty($_POST['is_active']) && $id ? 0 : 1];
        if ($id) {
            db()->update('shop_products', $data, 'id = ?', [$id]);
        } else {
            $id = db()->insert('shop_products', $data + ['stock_qty' => 0]);
        }
        audit('shop_product_saved', 'shop_products', $id, ['name' => $name, 'price' => $data['price']]);
        flash('success', 'Product saved.');
        redirect('shop/admin/products');
    }

    public function stock(int $id): void
    {
        $p = db()->one('SELECT * FROM shop_products WHERE id = ?', [$id]) ?? $this->notFound();
        $qty = (int) ($_POST['qty'] ?? 0);
        $reason = mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 120);
        if ($qty === 0 || abs($qty) > 10000 || $reason === '' || (int) $p['stock_qty'] + $qty < 0) {
            flash('error', (int) $p['stock_qty'] + $qty < 0 ? 'Stock cannot go below 0.' : 'Enter the number received (or a minus number for damaged / missing) and a reason.');
            redirect('shop/admin/products');
        }
        db()->transaction(function (Database $d) use ($id, $qty, $reason) {
            $d->run('UPDATE shop_products SET stock_qty = stock_qty + ? WHERE id = ?', [$qty, $id]);
            $d->insert('shop_stock_moves', ['product_id' => $id, 'qty' => $qty, 'reason' => $reason, 'created_by' => Auth::id()]);
        });
        audit('shop_stock_changed', 'shop_products', $id, ['qty' => $qty, 'reason' => $reason]);
        flash('success', $p['name'] . ': stock now ' . ((int) $p['stock_qty'] + $qty) . '.');
        redirect('shop/admin/products');
    }

    public function sellForm(): void
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $found = [];
        if ($q !== '') {
            $found = db()->all(
                "SELECT p.id, p.first_name, p.last_name, p.admission_no, c.name AS class_name FROM pupils p
                 LEFT JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ? AND e.status = 'active' LEFT JOIN classes c ON c.id = e.class_id
                 WHERE p.status = 'active' AND p.deleted_at IS NULL AND (p.admission_no LIKE ? OR CONCAT(p.first_name, ' ', p.last_name) LIKE ? OR CONCAT(p.last_name, ' ', p.first_name) LIKE ?) LIMIT 10",
                [current_year()['id'] ?? 0, "%$q%", "%$q%", "%$q%"]
            );
        }
        $pupil = isset($_GET['pupil']) ? $this->pupil((int) $_GET['pupil']) : null;
        $this->view('shop/sell', ['title' => 'Sell at the counter', 'q' => $q, 'found' => $found, 'pupil' => $pupil, 'products' => Shop::products()]);
    }

    public function sell(): void
    {
        $pupil = $this->pupil((int) ($_POST['pupil_id'] ?? 0));
        $method = in_array($_POST['method'] ?? '', ['cash', 'momo'], true) ? $_POST['method'] : null;
        if (!$pupil || !$method) {
            flash('error', !$pupil ? 'Choose the student.' : 'Choose cash or MoMo.');
            redirect('shop/admin/sell', $pupil ? ['pupil' => $pupil['id']] : []);
        }
        $items = [];
        foreach ((array) ($_POST['qty'] ?? []) as $pid => $q) {
            if ((int) $q > 0) {
                $items[] = [(int) $pid, (string) ($_POST['size'][$pid] ?? ''), (int) $q];
            }
        }
        [$oid, $err] = $items ? Shop::place($pupil, $items, 'counter', [$method, mb_substr(trim((string) ($_POST['payer'] ?? '')), 0, 120)]) : [null, 'Enter how many of each item.'];
        if ($err) {
            flash('error', $err);
            redirect('shop/admin/sell', ['pupil' => $pupil['id']]);
        }
        audit('shop_counter_sale', 'shop_orders', $oid);
        $rid = db()->value('SELECT p.id FROM payments p JOIN shop_orders o ON o.invoice_id = p.invoice_id WHERE o.id = ? ORDER BY p.id DESC LIMIT 1', [$oid]);
        flash('success', 'Sold and paid. Receipt ready.');
        redirect($rid ? 'fees/receipts/' . $rid : 'shop/admin/orders/' . $oid);
    }

    public function report(): void
    {
        $term = current_term();
        $from = Validator::isDate((string) ($_GET['from'] ?? '')) ? (string) $_GET['from'] : ($term['start_date'] ?? date('Y-01-01'));
        $to = Validator::isDate((string) ($_GET['to'] ?? '')) ? (string) $_GET['to'] : date('Y-m-d');
        $rows = db()->all(
            "SELECT l.name, SUM(l.qty) AS qty, SUM(l.qty * l.price) AS amount FROM shop_order_lines l JOIN shop_orders o ON o.id = l.order_id
             WHERE o.status IN ('paid','ready','collected') AND DATE(o.paid_at) BETWEEN ? AND ? GROUP BY l.name ORDER BY amount DESC",
            [$from, $to]
        );
        $this->view('shop/report', ['title' => 'Shop sales', 'rows' => $rows, 'from' => $from, 'to' => $to]);
    }

    // ---------------------------------------------------------------- helpers

    private function children(): array
    {
        return Auth::role() === 'parent' ? array_values(array_filter(Accounts::childrenOf((int) Auth::id()), fn($c) => $c['status'] === 'active')) : [];
    }

    private function pupil(int $id): ?array
    {
        return db()->one(
            "SELECT p.*, e.class_id, c.name AS class_name FROM pupils p LEFT JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ? AND e.status = 'active' LEFT JOIN classes c ON c.id = e.class_id
             WHERE p.id = ? AND p.status = 'active' AND p.deleted_at IS NULL",
            [current_year()['id'] ?? 0, $id]
        );
    }

    /** Basket with product details: [key, product, size, qty, line total]. */
    private function cartLines(): array
    {
        $out = [];
        foreach ($_SESSION['shop_cart'] ?? [] as $key => $qty) {
            [$pid, $size] = explode('|', (string) $key, 2) + [1 => ''];
            $p = db()->one('SELECT * FROM shop_products WHERE id = ? AND is_active = 1', [(int) $pid]);
            if ($p) {
                $out[] = ['key' => $key, 'p' => $p, 'size' => $size, 'qty' => (int) $qty, 'total' => round((int) $qty * (float) $p['price'], 2)];
            }
        }
        return $out;
    }
}
