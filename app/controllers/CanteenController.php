<?php
declare(strict_types=1);

/** Canteen: counter sales (wallet, cash or MoMo), wallets and top-ups, items and stock, menu, meal plans, meals served. */
class CanteenController extends Controller
{
    // ---------------------------------------------------------------- counter

    public function index(): void
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $found = [];
        if ($q !== '') {
            $found = db()->all(
                "SELECT p.id, p.first_name, p.last_name, p.admission_no, c.name AS class_name, w.balance FROM pupils p
                 JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ? AND e.status = 'active' JOIN classes c ON c.id = e.class_id
                 LEFT JOIN canteen_wallets w ON w.pupil_id = p.id
                 WHERE p.deleted_at IS NULL AND (p.admission_no LIKE ? OR CONCAT(p.first_name, ' ', p.last_name) LIKE ? OR CONCAT(p.last_name, ' ', p.first_name) LIKE ?)
                 ORDER BY p.last_name LIMIT 20",
                [current_year()['id'] ?? 0, "%$q%", "%$q%", "%$q%"]
            );
        }
        $today = db()->one("SELECT COUNT(*) n, COALESCE(SUM(total), 0) total, COALESCE(SUM(CASE WHEN paid_by = 'wallet' THEN total END), 0) wallet,
                                   COALESCE(SUM(CASE WHEN paid_by <> 'wallet' THEN total END), 0) cash FROM canteen_sales WHERE status = 'completed' AND DATE(created_at) = CURDATE()");
        $topups = (float) db()->value("SELECT COALESCE(SUM(amount), 0) FROM canteen_wallet_txns WHERE kind = 'topup' AND DATE(created_at) = CURDATE()");
        $low = db()->all('SELECT * FROM canteen_items WHERE is_active = 1 AND track_stock = 1 AND stock_qty <= reorder_level ORDER BY stock_qty');
        $recent = db()->all('SELECT s.*, p.first_name, p.last_name FROM canteen_sales s LEFT JOIN pupils p ON p.id = s.pupil_id ORDER BY s.id DESC LIMIT 12');
        $this->view('canteen/index', ['title' => 'Canteen', 'q' => $q, 'found' => $found, 'today' => $today, 'topups' => $topups, 'low' => $low, 'recent' => $recent,
            'menu' => Canteen::menu(date('Y-m-d'), date('Y-m-d'))[date('Y-m-d')] ?? []]);
    }

    public function sellForm(): void
    {
        $pid = (int) ($_GET['pupil'] ?? 0);
        $p = $pid ? (Canteen::pupil($pid) ?? $this->notFound()) : null;
        $this->view('canteen/sell', ['title' => 'Sell', 'p' => $p, 'items' => Canteen::items(), 'spent' => $p ? Canteen::spentToday($pid) : 0,
            'menuToday' => array_merge(...array_values(Canteen::menu(date('Y-m-d'), date('Y-m-d'))[date('Y-m-d')] ?? [[]]))]);
    }

    public function sell(): void
    {
        $pid = (int) ($_POST['pupil_id'] ?? 0);
        $p = $pid ? Canteen::pupil($pid) : null;
        $paidBy = (string) ($_POST['paid_by'] ?? '');
        $back = 'canteen/sell' . ($pid ? '?pupil=' . $pid : '');
        $lines = [];
        $items = array_column(Canteen::items(), null, 'id');
        foreach ((array) ($_POST['qty'] ?? []) as $iid => $q) {
            $q = (int) $q;
            if ($q > 0 && isset($items[(int) $iid])) {
                $lines[] = ['item' => $items[(int) $iid], 'qty' => min($q, 50)];
            }
        }
        $total = round(array_sum(array_map(fn($l) => $l['qty'] * (float) $l['item']['price'], $lines)), 2);
        $err = null;
        if ($pid && !$p) {
            $err = 'Student not found.';
        } elseif (!$lines) {
            $err = 'Choose at least one item.';
        } elseif (!in_array($paidBy, ['wallet', 'cash', 'momo'], true)) {
            $err = 'Choose how it is paid.';
        } elseif ($paidBy === 'wallet' && !$p) {
            $err = 'Choose the student whose wallet pays.';
        } elseif ($paidBy === 'momo' && trim((string) ($_POST['reference'] ?? '')) === '') {
            $err = 'Enter the MoMo transaction ID.';
        }
        if (!$err && $paidBy === 'wallet') {
            $bal = (float) $p['balance'];
            if ($total > $bal + 0.004) {
                $err = 'Not enough money in the wallet: ' . Fees::money($bal) . ' left, this costs ' . Fees::money($total) . '.';
            } elseif ($p['daily_limit'] !== null && Canteen::spentToday($pid) + $total > (float) $p['daily_limit'] + 0.004) {
                $err = 'This goes over the daily limit of ' . Fees::money($p['daily_limit']) . ' set by the parent (already spent ' . Fees::money(Canteen::spentToday($pid)) . ' today).';
            }
        }
        if (!$err) {
            foreach ($lines as $l) {
                if ((int) $l['item']['track_stock'] && $l['qty'] > (int) $l['item']['stock_qty']) {
                    $err = 'Only ' . (int) $l['item']['stock_qty'] . ' ' . $l['item']['name'] . ' left in stock.';
                    break;
                }
            }
        }
        if (!$err && $p && empty($_POST['allergy_ok'])) {
            $clash = [];
            foreach ($lines as $l) {
                if ($c = Canteen::allergyClash($p['allergies'], $l['item']['allergens'])) {
                    $clash[] = $l['item']['name'] . ' (' . implode(', ', $c) . ')';
                }
            }
            if ($clash) {
                $err = 'ALLERGY WARNING: ' . $p['first_name'] . ' is allergic to: ' . $p['allergies'] . '. Contains: ' . implode('; ', $clash) . '. Do not sell unless the parent has agreed — tick the box to confirm.';
            }
        }
        if ($err) {
            with_old($_POST);
            flash('error', $err);
            redirect($back);
        }
        $ref = mb_substr(trim((string) ($_POST['reference'] ?? '')), 0, 100) ?: null;
        try {
            $saleId = db()->transaction(function (Database $d) use ($p, $pid, $paidBy, $lines, $total, $ref) {
                $id = $d->insert('canteen_sales', ['sale_no' => Fees::nextNumber($d, 'CNT'), 'pupil_id' => $pid ?: null, 'paid_by' => $paidBy, 'total' => $total, 'sold_by' => Auth::id()]);
                foreach ($lines as $l) {
                    $d->insert('canteen_sale_lines', ['sale_id' => $id, 'item_id' => $l['item']['id'], 'name' => $l['item']['name'], 'qty' => $l['qty'], 'price' => $l['item']['price']]);
                    if ((int) $l['item']['track_stock']) {
                        $d->run('UPDATE canteen_items SET stock_qty = stock_qty - ? WHERE id = ?', [$l['qty'], $l['item']['id']]);
                        $d->insert('canteen_stock_moves', ['item_id' => $l['item']['id'], 'qty' => -$l['qty'], 'kind' => 'sale', 'note' => 'Sale ' . $id, 'created_by' => Auth::id()]);
                    }
                }
                if ($paidBy === 'wallet') {
                    Canteen::walletMove($d, $pid, 'sale', -$total, ['sale_id' => $id]);
                } else {
                    $this->postIncome($d, 'canteen_sale', $id, $total, $paidBy, 'Canteen sale' . ($p ? ': ' . $p['first_name'] . ' ' . $p['last_name'] : ''), $ref);
                }
                return $id;
            });
        } catch (RuntimeException $e) {
            if ($e->getMessage() !== 'wallet_short') {
                throw $e;
            }
            flash('error', 'Not enough money in the wallet for this sale. Check the balance and try again.');
            redirect($back);
        }
        audit('canteen_sale', 'canteen_sales', $saleId, ['total' => $total, 'paid_by' => $paidBy]);
        flash('success', 'Sold for ' . Fees::money($total) . ($paidBy === 'wallet' ? ' — wallet balance now ' . Fees::money(Canteen::balance($pid)) . '.' : '.'));
        redirect($back);
    }

    public function void(int $id): void
    {
        $s = db()->one('SELECT * FROM canteen_sales WHERE id = ?', [$id]) ?? $this->notFound();
        $reason = mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 160);
        if ($s['status'] !== 'completed' || $reason === '') {
            flash('error', 'Give a reason to cancel this sale.');
            redirect('canteen');
        }
        db()->transaction(function (Database $d) use ($s, $reason) {
            $d->update('canteen_sales', ['status' => 'void', 'void_reason' => $reason], 'id = ?', [$s['id']]);
            foreach ($d->all('SELECT l.*, i.track_stock FROM canteen_sale_lines l LEFT JOIN canteen_items i ON i.id = l.item_id WHERE l.sale_id = ?', [$s['id']]) as $l) {
                if ($l['item_id'] && (int) $l['track_stock']) {
                    $d->run('UPDATE canteen_items SET stock_qty = stock_qty + ? WHERE id = ?', [$l['qty'], $l['item_id']]);
                    $d->insert('canteen_stock_moves', ['item_id' => $l['item_id'], 'qty' => $l['qty'], 'kind' => 'adjust', 'note' => 'Sale ' . $s['id'] . ' cancelled', 'created_by' => Auth::id()]);
                }
            }
            if ($s['paid_by'] === 'wallet' && $s['pupil_id']) {
                Canteen::walletMove($d, (int) $s['pupil_id'], 'refund', (float) $s['total'], ['sale_id' => $s['id']]);
            } else {
                Finance::voidSource($d, 'canteen_sale', (int) $s['id'], 'Canteen sale cancelled: ' . $reason);
            }
        });
        audit('canteen_sale_void', 'canteen_sales', $id, ['reason' => $reason]);
        flash('success', 'Sale ' . $s['sale_no'] . ' cancelled' . ($s['paid_by'] === 'wallet' ? ' and the money returned to the wallet.' : '. Give the money back from the till.'));
        redirect('canteen');
    }

    // ---------------------------------------------------------------- wallets

    public function wallets(): void
    {
        $rows = db()->all(
            "SELECT p.id, p.first_name, p.last_name, p.admission_no, c.name AS class_name, w.balance, w.daily_limit,
                    (SELECT MAX(created_at) FROM canteen_wallet_txns t WHERE t.pupil_id = p.id) AS last_use
             FROM canteen_wallets w JOIN pupils p ON p.id = w.pupil_id
             LEFT JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ? AND e.status = 'active' LEFT JOIN classes c ON c.id = e.class_id
             ORDER BY w.balance < 10 DESC, p.last_name",
            [current_year()['id'] ?? 0]
        );
        $this->view('canteen/wallets', ['title' => 'Canteen wallets', 'rows' => $rows, 'total' => array_sum(array_map(fn($r) => (float) $r['balance'], $rows))]);
    }

    public function wallet(int $pupil): void
    {
        $p = Canteen::pupil($pupil) ?? $this->notFound();
        $txns = db()->all('SELECT t.*, u.name AS by_name FROM canteen_wallet_txns t LEFT JOIN users u ON u.id = t.created_by WHERE t.pupil_id = ? ORDER BY t.id DESC LIMIT 60', [$pupil]);
        $this->view('canteen/wallet', ['title' => 'Wallet — ' . $p['first_name'] . ' ' . $p['last_name'], 'p' => $p, 'txns' => $txns,
            'plan' => Canteen::planOf($pupil, (int) (current_term()['id'] ?? 0))]);
    }

    public function topup(int $pupil): void
    {
        $p = Canteen::pupil($pupil) ?? $this->notFound();
        $amount = round((float) str_replace(',', '', (string) ($_POST['amount'] ?? '')), 2);
        $method = (string) ($_POST['method'] ?? '');
        $ref = mb_substr(trim((string) ($_POST['reference'] ?? '')), 0, 100);
        if ($amount <= 0 || $amount > 5000 || !in_array($method, ['cash', 'momo'], true) || ($method === 'momo' && $ref === '')) {
            flash('error', 'Enter an amount between GH₵ 0.01 and 5,000, how it was paid, and the MoMo transaction ID for MoMo.');
            redirect('canteen/wallets/' . $pupil);
        }
        $new = db()->transaction(function (Database $d) use ($pupil, $amount, $method, $ref, $p) {
            $new = Canteen::walletMove($d, $pupil, 'topup', $amount, ['method' => $method, 'reference' => $ref ?: null]);
            $tid = (int) $d->value('SELECT MAX(id) FROM canteen_wallet_txns WHERE pupil_id = ?', [$pupil]);
            $this->postIncome($d, 'canteen_topup', $tid, $amount, $method, 'Canteen wallet top-up: ' . $p['first_name'] . ' ' . $p['last_name'], $ref ?: null);
            return $new;
        });
        audit('canteen_topup', 'canteen_wallets', $pupil, ['amount' => $amount, 'method' => $method]);
        foreach (db()->all('SELECT g.user_id FROM pupil_guardian pg JOIN guardians g ON g.id = pg.guardian_id WHERE pg.pupil_id = ? AND g.user_id IS NOT NULL', [$pupil]) as $g) {
            Notifier::inapp([(int) $g['user_id']], 'Canteen wallet topped up: ' . $p['first_name'], Fees::money($amount) . ' added. Balance ' . Fees::money($new) . '.', 'portal/child/' . $pupil);
        }
        flash('success', Fees::money($amount) . ' added. New balance ' . Fees::money($new) . '.');
        redirect('canteen/wallets/' . $pupil);
    }

    public function limit(int $pupil): void
    {
        Canteen::pupil($pupil) ?? $this->notFound();
        $raw = trim(str_replace(',', '', (string) ($_POST['daily_limit'] ?? '')));
        $lim = $raw === '' ? null : (is_numeric($raw) && (float) $raw > 0 ? round((float) $raw, 2) : false);
        if ($lim === false) {
            flash('error', 'Enter a daily limit above 0, or leave it empty for no limit.');
            redirect('canteen/wallets/' . $pupil);
        }
        db()->run('INSERT INTO canteen_wallets (pupil_id, balance, daily_limit) VALUES (?, 0, ?) ON DUPLICATE KEY UPDATE daily_limit = VALUES(daily_limit)', [$pupil, $lim]);
        audit('canteen_limit', 'canteen_wallets', $pupil, ['limit' => $lim]);
        flash('success', $lim === null ? 'No daily limit.' : 'Daily limit set to ' . Fees::money($lim) . '.');
        redirect('canteen/wallets/' . $pupil);
    }

    // ---------------------------------------------------------------- items & stock

    public function items(): void
    {
        $this->view('canteen/items', ['title' => 'Canteen items & stock', 'items' => Canteen::items(false),
            'moves' => db()->all('SELECT m.*, i.name, u.name AS by_name FROM canteen_stock_moves m JOIN canteen_items i ON i.id = m.item_id LEFT JOIN users u ON u.id = m.created_by WHERE m.kind <> "sale" ORDER BY m.id DESC LIMIT 20')]);
    }

    public function saveItem(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 80);
        $price = str_replace(',', '', (string) ($_POST['price'] ?? ''));
        $cat = (string) ($_POST['category'] ?? '');
        if ($name === '' || !is_numeric($price) || (float) $price < 0 || (float) $price > 1000 || !isset(Canteen::CATEGORIES[$cat]) || db()->value('SELECT id FROM canteen_items WHERE name = ? AND id <> ?', [$name, $id])) {
            flash('error', 'Give the item a unique name, a category and a price.');
            redirect('canteen/items');
        }
        $data = ['name' => $name, 'category' => $cat, 'price' => round((float) $price, 2), 'allergens' => mb_substr(trim((string) ($_POST['allergens'] ?? '')), 0, 160) ?: null,
            'track_stock' => empty($_POST['track_stock']) ? 0 : 1, 'reorder_level' => max(0, (int) ($_POST['reorder_level'] ?? 0)), 'is_active' => $id && empty($_POST['is_active']) ? 0 : 1];
        if ($id) {
            db()->one('SELECT id FROM canteen_items WHERE id = ?', [$id]) ?? $this->notFound();
            db()->update('canteen_items', $data, 'id = ?', [$id]);
        } else {
            $id = db()->insert('canteen_items', $data);
        }
        audit('canteen_item_saved', 'canteen_items', $id, $data);
        flash('success', 'Item saved.');
        redirect('canteen/items');
    }

    public function stock(int $id): void
    {
        $it = db()->one('SELECT * FROM canteen_items WHERE id = ?', [$id]) ?? $this->notFound();
        $kind = ($_POST['kind'] ?? '') === 'adjust' ? 'adjust' : 'in';
        $qty = (int) ($_POST['qty'] ?? 0);
        $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 160);
        if ($qty === 0 || ($kind === 'in' && $qty < 0) || ($kind === 'adjust' && $note === '') || (int) $it['stock_qty'] + $qty < 0) {
            flash('error', $kind === 'in' ? 'Enter how many came in.' : 'Enter the change (e.g. -3) and why (spoilt, counted…), without going below zero.');
            redirect('canteen/items');
        }
        db()->transaction(function (Database $d) use ($id, $qty, $kind, $note) {
            $d->run('UPDATE canteen_items SET stock_qty = stock_qty + ?, track_stock = 1 WHERE id = ?', [$qty, $id]);
            $d->insert('canteen_stock_moves', ['item_id' => $id, 'qty' => $qty, 'kind' => $kind, 'note' => $note ?: null, 'created_by' => Auth::id()]);
        });
        audit('canteen_stock', 'canteen_items', $id, ['qty' => $qty, 'kind' => $kind]);
        flash('success', 'Stock updated: ' . $it['name'] . ' now ' . ((int) $it['stock_qty'] + $qty) . '.');
        redirect('canteen/items');
    }

    // ---------------------------------------------------------------- menu

    public function menu(): void
    {
        $mon = (string) ($_GET['week'] ?? date('Y-m-d', strtotime('monday this week')));
        $mon = Validator::isDate($mon) ? date('Y-m-d', strtotime('monday this week', strtotime($mon))) : date('Y-m-d', strtotime('monday this week'));
        $this->view('canteen/menu', ['title' => 'Canteen menu', 'mon' => $mon, 'menu' => Canteen::menu($mon, date('Y-m-d', strtotime($mon . ' +4 days'))), 'items' => Canteen::items()]);
    }

    public function saveMenu(): void
    {
        $date = (string) ($_POST['date'] ?? '');
        $meal = (string) ($_POST['meal'] ?? '');
        if (!Validator::isDate($date) || !isset(Canteen::MEALS[$meal])) {
            flash('error', 'Choose a day and a meal.');
            redirect('canteen/menu');
        }
        $ids = array_map('intval', (array) ($_POST['items'] ?? []));
        $valid = array_map('intval', array_column(Canteen::items(), 'id'));
        db()->transaction(function (Database $d) use ($date, $meal, $ids, $valid) {
            $d->run('DELETE FROM canteen_menu WHERE menu_date = ? AND meal = ?', [$date, $meal]);
            foreach (array_unique(array_intersect($ids, $valid)) as $i) {
                $d->insert('canteen_menu', ['menu_date' => $date, 'meal' => $meal, 'item_id' => $i]);
            }
        });
        flash('success', Canteen::MEALS[$meal] . ' for ' . date('D j M', strtotime($date)) . ' saved.');
        redirect('canteen/menu', ['week' => $date]);
    }

    // ---------------------------------------------------------------- meal plans

    public function plans(): void
    {
        $term = current_term();
        if ($term) {
            self::releaseCancelledBills((int) $term['id']);
        }
        $plans = db()->all('SELECT p.*, (SELECT COUNT(*) FROM canteen_plan_members m WHERE m.plan_id = p.id AND m.term_id = ?) AS n FROM canteen_plans p ORDER BY p.is_active DESC, p.price', [$term['id'] ?? 0]);
        $members = $term ? db()->all(
            "SELECT m.*, p.first_name, p.last_name, p.admission_no, c.name AS class_name, pl.name AS plan_name, pl.price, i.invoice_no, i.status AS inv_status, i.public_key
             FROM canteen_plan_members m JOIN pupils p ON p.id = m.pupil_id JOIN canteen_plans pl ON pl.id = m.plan_id
             LEFT JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ? AND e.status = 'active' LEFT JOIN classes c ON c.id = e.class_id
             LEFT JOIN invoices i ON i.id = m.invoice_id WHERE m.term_id = ? ORDER BY c.name, p.last_name",
            [$term['academic_year_id'], $term['id']]
        ) : [];
        $classes = db()->all('SELECT c.id, c.name FROM classes c JOIN levels l ON l.id = c.level_id WHERE c.is_active = 1 ORDER BY l.sort_order, c.name');
        $classId = (int) ($_GET['class'] ?? 0);
        $pupils = $classId && $term ? db()->all(
            "SELECT p.id, p.first_name, p.last_name, p.allergies, (SELECT plan_id FROM canteen_plan_members m WHERE m.pupil_id = p.id AND m.term_id = ?) AS plan_id
             FROM enrolments e JOIN pupils p ON p.id = e.pupil_id WHERE e.class_id = ? AND e.academic_year_id = ? AND e.status = 'active' AND p.deleted_at IS NULL ORDER BY p.last_name",
            [$term['id'], $classId, $term['academic_year_id']]
        ) : [];
        $this->view('canteen/plans', ['title' => 'Meal plans', 'term' => $term, 'plans' => $plans, 'members' => $members, 'classes' => $classes, 'classId' => $classId, 'pupils' => $pupils]);
    }

    public function savePlan(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 80);
        $price = str_replace(',', '', (string) ($_POST['price'] ?? ''));
        if ($name === '' || !is_numeric($price) || (float) $price <= 0 || db()->value('SELECT id FROM canteen_plans WHERE name = ? AND id <> ?', [$name, $id])) {
            flash('error', 'Give the plan a unique name and a termly price.');
            redirect('canteen/plans');
        }
        $data = ['name' => $name, 'price' => round((float) $price, 2), 'meals' => mb_substr(trim((string) ($_POST['meals'] ?? '')), 0, 160) ?: null, 'is_active' => $id && empty($_POST['is_active']) ? 0 : 1];
        $id ? db()->update('canteen_plans', $data, 'id = ?', [$id]) : db()->insert('canteen_plans', $data);
        flash('success', 'Meal plan saved.');
        redirect('canteen/plans');
    }

    /** Put pupils of a class on a plan (or take them off) for the current term. */
    public function members(): void
    {
        $term = current_term();
        $classId = (int) ($_POST['class_id'] ?? 0);
        if (!$term || !$classId) {
            redirect('canteen/plans');
        }
        $roster = array_map('intval', array_column(db()->all("SELECT pupil_id FROM enrolments WHERE class_id = ? AND academic_year_id = ? AND status = 'active'", [$classId, $term['academic_year_id']]), 'pupil_id'));
        $plans = array_map('intval', array_column(db()->all('SELECT id FROM canteen_plans WHERE is_active = 1'), 'id'));
        self::releaseCancelledBills((int) $term['id']);
        $n = 0;
        $blocked = 0;
        foreach ((array) ($_POST['plan'] ?? []) as $pid => $plan) {
            $pid = (int) $pid;
            $plan = (int) $plan;
            if (!in_array($pid, $roster, true)) {
                continue;
            }
            $ex = db()->one('SELECT * FROM canteen_plan_members WHERE pupil_id = ? AND term_id = ?', [$pid, $term['id']]);
            if ($ex && $ex['invoice_id'] && (int) $ex['plan_id'] !== $plan) {
                $blocked++; // already billed: change it on the bill first
                continue;
            }
            if ($plan && in_array($plan, $plans, true)) {
                if (!$ex) {
                    db()->insert('canteen_plan_members', ['term_id' => $term['id'], 'pupil_id' => $pid, 'plan_id' => $plan]);
                    $n++;
                } elseif ((int) $ex['plan_id'] !== $plan) {
                    db()->update('canteen_plan_members', ['plan_id' => $plan], 'id = ?', [$ex['id']]);
                    $n++;
                }
            } elseif ($ex) {
                db()->run('DELETE FROM canteen_plan_members WHERE id = ?', [$ex['id']]);
                $n++;
            }
        }
        audit('canteen_plan_members', 'canteen_plan_members', null, ['class' => $classId, 'changes' => $n]);
        flash($blocked ? 'warning' : 'success', $n . ' change(s) saved.' . ($blocked ? " $blocked student(s) already billed were not changed — cancel or adjust their meal-plan bill first." : ''));
        redirect('canteen/plans', ['class' => $classId]);
    }

    /**
     * A meal-plan bill that was cancelled no longer counts (as with bus fees): the student can be moved to another
     * plan or billed again. The cancelled bill itself stays in Fees for the record.
     */
    private static function releaseCancelledBills(int $termId): void
    {
        db()->run("UPDATE canteen_plan_members m JOIN invoices i ON i.id = m.invoice_id SET m.invoice_id = NULL WHERE m.term_id = ? AND i.status = 'cancelled'", [$termId]);
    }

    /** One "Meal plan" bill per pupil on a plan this term who has not been billed yet. */
    public function bill(): void
    {
        $term = current_term() ?? $this->notFound();
        self::releaseCancelledBills((int) $term['id']);
        $made = 0;
        foreach (db()->all("SELECT m.*, pl.name AS plan_name, pl.price FROM canteen_plan_members m JOIN canteen_plans pl ON pl.id = m.plan_id WHERE m.term_id = ? AND m.invoice_id IS NULL", [$term['id']]) as $m) {
            $pupil = db()->one("SELECT p.id, e.class_id FROM pupils p LEFT JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ? AND e.status = 'active' WHERE p.id = ?", [$term['academic_year_id'], $m['pupil_id']]);
            $made += (int) db()->transaction(function (Database $d) use ($pupil, $term, $m) {
                // checked again inside the transaction so a double click cannot bill the same student twice
                if ($d->value('SELECT invoice_id FROM canteen_plan_members WHERE id = ? FOR UPDATE', [$m['id']])) {
                    return 0;
                }
                $inv = Fees::insertInvoice($d, $pupil, $term, 'other', 'Meal plan — ' . $term['name'], $_POST['due_date'] ?? null);
                $d->insert('invoice_lines', ['invoice_id' => $inv, 'description' => 'Feeding: ' . $m['plan_name'], 'amount' => $m['price']]);
                Fees::recalc($inv, $d);
                $d->update('canteen_plan_members', ['invoice_id' => $inv], 'id = ?', [$m['id']]);
                return 1;
            });
        }
        audit('canteen_plans_billed', 'canteen_plan_members', null, ['bills' => $made]);
        flash('success', $made . ' meal-plan bill(s) created. Parents see them with the other bills and can pay them the usual ways.');
        redirect('canteen/plans');
    }

    // ---------------------------------------------------------------- meals served

    public function served(): void
    {
        $date = (string) ($_GET['date'] ?? date('Y-m-d'));
        $date = Validator::isDate($date) && $date <= date('Y-m-d') ? $date : date('Y-m-d');
        $meal = ($_GET['meal'] ?? 'lunch') === 'breakfast' ? 'breakfast' : 'lunch';
        $term = current_term();
        $rows = $term ? db()->all(
            "SELECT p.id, p.first_name, p.last_name, p.allergies, c.name AS class_name, pl.name AS plan_name,
                    (SELECT 1 FROM canteen_served s WHERE s.served_on = ? AND s.pupil_id = p.id AND s.meal = ?) AS served,
                    (SELECT status FROM attendance_records a WHERE a.pupil_id = p.id AND a.date = ?) AS att
             FROM canteen_plan_members m JOIN pupils p ON p.id = m.pupil_id JOIN canteen_plans pl ON pl.id = m.plan_id
             LEFT JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ? AND e.status = 'active' LEFT JOIN classes c ON c.id = e.class_id
             WHERE m.term_id = ? ORDER BY c.name, p.last_name",
            [$date, $meal, $date, $term['academic_year_id'], $term['id']]
        ) : [];
        $this->view('canteen/served', ['title' => 'Meals served', 'date' => $date, 'meal' => $meal, 'rows' => $rows]);
    }

    public function saveServed(): void
    {
        $date = (string) ($_POST['date'] ?? '');
        $meal = ($_POST['meal'] ?? 'lunch') === 'breakfast' ? 'breakfast' : 'lunch';
        $term = current_term();
        if (!Validator::isDate($date) || $date > date('Y-m-d') || !$term) {
            flash('error', 'Choose a day that is not in the future.');
            redirect('canteen/served');
        }
        $members = array_map('intval', array_column(db()->all('SELECT pupil_id FROM canteen_plan_members WHERE term_id = ?', [$term['id']]), 'pupil_id'));
        $ids = array_intersect(array_map('intval', (array) ($_POST['served'] ?? [])), $members);
        db()->transaction(function (Database $d) use ($date, $meal, $ids) {
            $d->run('DELETE FROM canteen_served WHERE served_on = ? AND meal = ?', [$date, $meal]);
            foreach ($ids as $pid) {
                $d->insert('canteen_served', ['served_on' => $date, 'pupil_id' => $pid, 'meal' => $meal, 'marked_by' => Auth::id()]);
            }
        });
        flash('success', count($ids) . ' ' . $meal . 's served on ' . date('D j M', strtotime($date)) . '.');
        redirect('canteen/served', ['date' => $date, 'meal' => $meal]);
    }

    // ---------------------------------------------------------------- report

    public function report(): void
    {
        $from = (string) ($_GET['from'] ?? date('Y-m-01'));
        $to = (string) ($_GET['to'] ?? date('Y-m-d'));
        if (!Validator::isDate($from) || !Validator::isDate($to) || $from > $to) {
            [$from, $to] = [date('Y-m-01'), date('Y-m-d')];
        }
        $byDay = db()->all("SELECT DATE(created_at) d, COUNT(*) n, SUM(total) total, SUM(CASE WHEN paid_by = 'wallet' THEN total ELSE 0 END) wallet, SUM(CASE WHEN paid_by <> 'wallet' THEN total ELSE 0 END) cash
                            FROM canteen_sales WHERE status = 'completed' AND DATE(created_at) BETWEEN ? AND ? GROUP BY DATE(created_at) ORDER BY d", [$from, $to]);
        $byItem = db()->all("SELECT l.name, SUM(l.qty) qty, SUM(l.qty * l.price) amount FROM canteen_sale_lines l JOIN canteen_sales s ON s.id = l.sale_id
                             WHERE s.status = 'completed' AND DATE(s.created_at) BETWEEN ? AND ? GROUP BY l.name ORDER BY amount DESC", [$from, $to]);
        $topups = (float) db()->value("SELECT COALESCE(SUM(amount), 0) FROM canteen_wallet_txns WHERE kind = 'topup' AND DATE(created_at) BETWEEN ? AND ?", [$from, $to]);
        if (isset($_GET['csv'])) {
            $out = csv_start('canteen-' . $from . '-' . $to . '.csv', ['Date', 'Sales', 'Total', 'From wallets', 'Cash / MoMo']);
            foreach ($byDay as $r) {
                csv_row($out, [$r['d'], $r['n'], $r['total'], $r['wallet'], $r['cash']]);
            }
            csv_end($out);
        }
        $this->view('canteen/report', ['title' => 'Canteen report', 'from' => $from, 'to' => $to, 'byDay' => $byDay, 'byItem' => $byItem, 'topups' => $topups]);
    }

    // ---------------------------------------------------------------- helpers

    private function postIncome(Database $d, string $source, int $sourceId, float $amount, string $method, string $desc, ?string $ref): void
    {
        $acct = Finance::accountForMethod($method);
        if (!$acct) {
            return;
        }
        Finance::post($d, ['kind' => 'income', 'txn_date' => date('Y-m-d'), 'account_id' => $acct, 'category_id' => $d->value("SELECT id FROM fin_categories WHERE code = 'CANT'") ?: null,
            'amount' => $amount, 'description' => $desc, 'reference' => $ref, 'source' => $source, 'source_id' => $sourceId]);
    }
}
