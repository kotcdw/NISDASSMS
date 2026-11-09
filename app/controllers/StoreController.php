<?php
declare(strict_types=1);

/**
 * Store ledger (GES stores/inventory book): items kept in the school store, every receipt and issue with who
 * received it, a ledger card per item with the running balance, and a low-stock list. Nothing can be issued
 * beyond what is in stock. Entries are never edited or deleted — a mistake is put right with a correction line.
 */
class StoreController extends Controller
{
    public function __construct()
    {
        if (!Registers::ready()) {
            $this->needsTable('store_items');
        }
    }

    public function index(): void
    {
        $cat = (string) ($_GET['category'] ?? '');
        $sql = 'SELECT * FROM store_items WHERE is_active = 1';
        $p = [];
        if (isset(Registers::STORE_CATEGORIES[$cat])) {
            $sql .= ' AND category = ?';
            $p[] = $cat;
        }
        $items = db()->all($sql . ' ORDER BY category, name', $p);
        $stock = Registers::stock();
        // Entries for the period tab (default this month); older ?from&to links still work.
        $gf = (string) ($_GET['from'] ?? '');
        $gt = (string) ($_GET['to'] ?? '');
        if (!isset($_GET['period']) && (Validator::isDate($gf) || Validator::isDate($gt))) {
            $to = Validator::isDate($gt) ? $gt : date('Y-m-d');
            $from = Validator::isDate($gf) ? $gf : $to;
            if ($from > $to) {
                [$from, $to] = [$to, $from];
            }
            $period = 'custom';
        } else {
            [$from, $to, $period] = RegisterController::periodDates((string) ($_GET['period'] ?? ''), 'month');
        }
        $recent = db()->all('SELECT m.*, i.name, i.unit, u.name AS by_name FROM store_moves m JOIN store_items i ON i.id = m.item_id LEFT JOIN users u ON u.id = m.recorded_by
                             WHERE m.moved_on BETWEEN ? AND ? ORDER BY m.moved_on DESC, m.id DESC LIMIT 500', [$from, $period === 'all' ? '2099-12-31' : $to]);
        $this->view('stores/index', ['title' => 'Store ledger', 'items' => $items, 'stock' => $stock, 'recent' => $recent, 'cat' => $cat, 'from' => $from, 'to' => $to, 'period' => $period,
            'all' => db()->all('SELECT id, name, unit FROM store_items WHERE is_active = 1 ORDER BY name')]);
    }

    public function item(int $id): void
    {
        $item = db()->one('SELECT * FROM store_items WHERE id = ?', [$id]) ?? $this->notFound();
        $moves = db()->all('SELECT m.*, u.name AS by_name FROM store_moves m LEFT JOIN users u ON u.id = m.recorded_by WHERE m.item_id = ? ORDER BY m.moved_on, m.id', [$id]);
        $bal = 0.0;
        foreach ($moves as &$m) {
            $bal += $m['kind'] === 'issue' ? -(float) $m['qty'] : (float) $m['qty'];
            $m['balance'] = $bal;
        }
        unset($m);
        $this->view(!empty($_GET['print']) ? 'stores/card_print' : 'stores/item', ['title' => 'Ledger card — ' . $item['name'], 'item' => $item, 'moves' => $moves, 'balance' => $bal],
            !empty($_GET['print']) ? 'layouts/print' : 'layouts/app');
    }

    public function saveItem(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 150);
        $cat = (string) ($_POST['category'] ?? 'stationery');
        $unit = mb_substr(trim((string) ($_POST['unit'] ?? 'piece')), 0, 30) ?: 'piece';
        $reorder = (string) ($_POST['reorder_level'] ?? '0');
        $err = null;
        if ($name === '' || !isset(Registers::STORE_CATEGORIES[$cat])) {
            $err = 'Give the item a name and a category.';
        } elseif (!is_numeric($reorder) || $reorder < 0) {
            $err = 'The re-order level must be 0 or more.';
        } elseif (db()->value('SELECT 1 FROM store_items WHERE name = ? AND id <> ?', [$name, $id])) {
            $err = 'There is already an item with that name.';
        }
        if ($err) {
            with_old($_POST);
            flash('error', $err);
            redirect($id ? 'stores/items/' . $id : 'stores');
        }
        $data = ['name' => $name, 'category' => $cat, 'unit' => $unit, 'reorder_level' => $reorder, 'location' => mb_substr(trim((string) ($_POST['location'] ?? '')), 0, 80) ?: null];
        if ($id) {
            db()->one('SELECT id FROM store_items WHERE id = ?', [$id]) ?? $this->notFound();
            db()->update('store_items', $data + ['is_active' => isset($_POST['is_active']) ? 1 : 0], 'id = ?', [$id]);
            audit('store_item_updated', 'store_items', $id);
            flash('success', 'Item saved.');
            redirect('stores/items/' . $id);
        }
        $id = db()->insert('store_items', $data);
        audit('store_item_added', 'store_items', $id);
        flash('success', "$name added. Record the stock you have with \"Received\" or a stock count correction.");
        redirect('stores');
    }

    public function move(): void
    {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $kind = (string) ($_POST['kind'] ?? '');
        $qty = (string) ($_POST['qty'] ?? '');
        $date = (string) ($_POST['moved_on'] ?? date('Y-m-d'));
        $party = mb_substr(trim((string) ($_POST['party'] ?? '')), 0, 150);
        $cost = trim((string) ($_POST['unit_cost'] ?? ''));
        $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 255);
        $back = (string) ($_POST['back'] ?? 'stores') === 'item' ? 'stores/items/' . $itemId : 'stores';
        $item = db()->one('SELECT * FROM store_items WHERE id = ? AND is_active = 1', [$itemId]);
        $err = null;
        if (!$item) {
            $err = 'Choose an item.';
        } elseif (!isset(Registers::STORE_KINDS[$kind])) {
            $err = 'Choose received, issued or correction.';
        } elseif (!is_numeric($qty) || (float) $qty == 0.0 || ($kind !== 'adjust' && (float) $qty < 0)) {
            $err = $kind === 'adjust' ? 'Enter the correction (+ to add, − to take away).' : 'Enter a quantity above 0.';
        } elseif (!Validator::isDate($date) || $date > date('Y-m-d')) {
            $err = 'Choose the date (not in the future).';
        } elseif ($kind === 'issue' && $party === '') {
            $err = 'Write who received the items (person, class or department).';
        } elseif ($kind === 'adjust' && $note === '') {
            $err = 'Say why the count is corrected (e.g. stock count found 3 missing).';
        } elseif ($cost !== '' && (!is_numeric($cost) || $cost < 0)) {
            $err = 'The unit cost must be a number.';
        }
        if (!$err && $kind !== 'receive') {
            $have = Registers::stock($itemId)[$itemId] ?? 0.0;
            $after = $have + ($kind === 'issue' ? -(float) $qty : (float) $qty);
            if ($after < -0.0001) {
                $err = 'Only ' . rtrim(rtrim(number_format($have, 2, '.', ''), '0'), '.') . ' ' . $item['unit'] . ' in stock.';
            }
        }
        if ($err) {
            with_old($_POST);
            flash('error', $err);
            redirect($back);
        }
        $id = db()->insert('store_moves', [
            'item_id' => $itemId, 'kind' => $kind, 'qty' => $qty, 'unit_cost' => $cost !== '' ? $cost : null, 'moved_on' => $date,
            'reference' => mb_substr(trim((string) ($_POST['reference'] ?? '')), 0, 60) ?: null, 'party' => $party ?: null, 'note' => $note ?: null, 'recorded_by' => Auth::id(),
        ]);
        audit('store_' . $kind, 'store_moves', $id, ['item' => $item['name'], 'qty' => $qty]);
        $now = Registers::stock($itemId)[$itemId] ?? 0.0;
        flash('success', Registers::STORE_KINDS[$kind] . ': ' . $item['name'] . '. In stock now: ' . rtrim(rtrim(number_format($now, 2, '.', ''), '0'), '.') . ' ' . $item['unit'] . '.');
        redirect($back);
    }
}
