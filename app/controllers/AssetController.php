<?php
declare(strict_types=1);

/** Fixed asset register: furniture, computers, vehicles, buildings … with location, holder and depreciation. */
class AssetController extends Controller
{
    public function index(): void
    {
        $this->needsTable('fixed_assets');
        $cat = isset(Assets::CATEGORIES[$_GET['category'] ?? '']) ? $_GET['category'] : '';
        $show = in_array($_GET['show'] ?? '', ['current', 'gone', 'all'], true) ? $_GET['show'] : 'current';
        $where = ['1 = 1'];
        $p = [];
        if ($cat) {
            $where[] = 'a.category = ?';
            $p[] = $cat;
        }
        if ($show === 'current') {
            $where[] = "a.status IN ('in_use','repair')";
        } elseif ($show === 'gone') {
            $where[] = "a.status IN ('disposed','lost')";
        }
        $rows = db()->all('SELECT a.*, u.name AS holder_name FROM fixed_assets a LEFT JOIN users u ON u.id = a.holder_id WHERE ' . implode(' AND ', $where) . ' ORDER BY a.category, a.name, a.asset_no', $p);
        $cost = 0.0;
        $nbv = 0.0;
        foreach ($rows as &$r) {
            $r['v'] = Assets::value($r);
            if (in_array($r['status'], ['in_use', 'repair'], true)) {
                $cost += (float) $r['cost'];
                $nbv += $r['v']['nbv'];
            }
        }
        unset($r);
        $this->view('assets/index', ['title' => 'Fixed assets', 'rows' => $rows, 'cat' => $cat, 'show' => $show, 'cost' => $cost, 'nbv' => $nbv,
            'locations' => array_column(db()->all('SELECT DISTINCT location FROM fixed_assets WHERE location IS NOT NULL ORDER BY location'), 'location')]);
    }

    public function create(): void
    {
        $this->needsTable('fixed_assets');
        $prefill = null;
        if ($bid = (int) ($_GET['bill'] ?? 0)) { // from a paid supplier bill for an asset
            $b = db()->one('SELECT * FROM supplier_bills WHERE id = ?', [$bid]);
            if ($b) {
                $prefill = ['name' => $b['description'], 'cost' => $b['amount'], 'supplier_id' => $b['supplier_id'], 'purchase_date' => $b['bill_date'],
                    'funded_by' => 'school', 'notes' => 'Bill ' . $b['bill_no'], 'bill_no' => $b['bill_no'], 'bill_id' => $b['id']];
            }
        }
        $this->view('assets/form', ['title' => 'Add an asset', 'a' => null, 'prefill' => $prefill] + $this->lists());
    }

    private function lists(): array
    {
        return [
            'staff' => db()->all("SELECT id, name FROM users WHERE is_active = 1 AND role NOT IN ('parent','pupil') ORDER BY name"),
            'suppliers' => db()->all('SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name'),
            'locations' => array_column(db()->all("SELECT name FROM rooms ORDER BY name"), 'name'),
        ];
    }

    private function input(?array $old): array
    {
        $cat = isset(Assets::CATEGORIES[$_POST['category'] ?? '']) ? $_POST['category'] : 'furniture';
        return [
            'name' => mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 150),
            'category' => $cat,
            'location' => mb_substr(trim((string) ($_POST['location'] ?? '')), 0, 120) ?: null,
            'holder_id' => (int) ($_POST['holder_id'] ?? 0) ?: null,
            'serial_no' => mb_substr(trim((string) ($_POST['serial_no'] ?? '')), 0, 80) ?: null,
            'supplier_id' => (int) ($_POST['supplier_id'] ?? 0) ?: null,
            'funded_by' => isset(Assets::FUNDS[$_POST['funded_by'] ?? '']) ? $_POST['funded_by'] : 'school',
            'purchase_date' => (string) ($_POST['purchase_date'] ?? ''),
            'cost' => round((float) str_replace(',', '', (string) ($_POST['cost'] ?? '0')), 2),
            'life_years' => max(0, min(100, (int) ($_POST['life_years'] ?? Assets::CATEGORIES[$cat][1]))),
            'residual' => max(0.0, round((float) str_replace(',', '', (string) ($_POST['residual'] ?? '0')), 2)),
            'condition_state' => isset(Assets::CONDITION[$_POST['condition_state'] ?? '']) ? $_POST['condition_state'] : 'good',
            'notes' => mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 255) ?: null,
        ];
    }

    private function check(array $d): ?string
    {
        if ($d['name'] === '') {
            return 'Name the asset.';
        }
        if (!Validator::isDate($d['purchase_date']) || $d['purchase_date'] > date('Y-m-d')) {
            return 'Enter the date it was bought or received (not in the future).';
        }
        if ($d['cost'] <= 0 || $d['cost'] > 100000000) {
            return 'Enter what it cost (or its value when it was donated).';
        }
        if ($d['residual'] >= $d['cost']) {
            return 'The value left at the end must be less than the cost.';
        }
        return null;
    }

    public function store(): void
    {
        $this->needsTable('fixed_assets');
        $d = $this->input(null);
        $qty = max(1, min(200, (int) ($_POST['qty'] ?? 1)));
        if ($err = $this->check($d)) {
            with_old($_POST);
            flash('error', $err);
            redirect('fixed-assets/new');
        }
        $ids = db()->transaction(function (Database $db) use ($d, $qty) {
            $ids = [];
            for ($i = 0; $i < $qty; $i++) {
                $ids[] = $id = $db->insert('fixed_assets', $d + ['asset_no' => Fees::nextNumber($db, 'FA'), 'created_by' => Auth::id()]);
                $db->insert('asset_log', ['asset_id' => $id, 'action' => 'added', 'note' => ($d['location'] ? 'At ' . $d['location'] : null), 'user_id' => Auth::id()]);
            }
            return $ids;
        });
        audit('asset_added', 'fixed_assets', $ids[0], ['name' => $d['name'], 'qty' => $qty, 'cost' => $d['cost']]);
        flash('success', $qty > 1 ? $qty . ' × “' . $d['name'] . '” added (one tag number each).' : '“' . $d['name'] . '” added.');
        redirect($qty > 1 ? 'fixed-assets' : 'fixed-assets/' . $ids[0]);
    }

    public function show(int $id): void
    {
        $this->needsTable('fixed_assets');
        $a = db()->one('SELECT a.*, u.name AS holder_name, s.name AS supplier_name FROM fixed_assets a LEFT JOIN users u ON u.id = a.holder_id LEFT JOIN suppliers s ON s.id = a.supplier_id WHERE a.id = ?', [$id]) ?? $this->notFound();
        $sched = [];
        $y0 = (int) substr($a['purchase_date'], 0, 4);
        for ($y = $y0; $y <= (int) date('Y'); $y++) {
            $v = Assets::value($a, $y . '-12-31', $y);
            $sched[] = ['year' => $y, 'charge' => $v['charge'], 'nbv' => $v['nbv']];
        }
        $this->view('assets/show', ['title' => $a['name'], 'a' => $a, 'v' => Assets::value($a), 'sched' => array_slice($sched, -10),
            'log' => db()->all('SELECT l.*, u.name AS by_name FROM asset_log l LEFT JOIN users u ON u.id = l.user_id WHERE l.asset_id = ? ORDER BY l.id DESC', [$id]),
            'accounts' => Finance::accounts(true, false)] + $this->lists());
    }

    public function update(int $id): void
    {
        $this->needsTable('fixed_assets');
        $a = db()->one('SELECT * FROM fixed_assets WHERE id = ?', [$id]) ?? $this->notFound();
        $d = $this->input($a);
        if ($err = $this->check($d)) {
            flash('error', $err);
            redirect('fixed-assets/' . $id);
        }
        db()->update('fixed_assets', $d, 'id = ?', [$id]);
        $changes = [];
        foreach (['location' => 'Moved to', 'condition_state' => 'Condition', 'cost' => 'Cost'] as $k => $l) {
            if ((string) $a[$k] !== (string) $d[$k]) {
                $changes[] = $l . ' ' . ($d[$k] ?? '—');
            }
        }
        if ((int) $a['holder_id'] !== (int) $d['holder_id']) {
            $changes[] = 'Given to ' . ($d['holder_id'] ? db()->value('SELECT name FROM users WHERE id = ?', [$d['holder_id']]) : 'nobody');
        }
        Assets::log($id, 'updated', implode(' · ', $changes) ?: 'Details changed');
        audit('asset_updated', 'fixed_assets', $id, ['changes' => $changes]);
        flash('success', 'Asset updated.');
        redirect('fixed-assets/' . $id);
    }

    /** Dispose of, sell, write off or report lost; selling puts the money in the cash book. */
    public function dispose(int $id): void
    {
        $this->needsTable('fixed_assets');
        $a = db()->one('SELECT * FROM fixed_assets WHERE id = ?', [$id]) ?? $this->notFound();
        $status = ($_POST['status'] ?? '') === 'lost' ? 'lost' : 'disposed';
        $date = (string) ($_POST['disposed_on'] ?? date('Y-m-d'));
        $amount = round((float) str_replace(',', '', (string) ($_POST['disposal_amount'] ?? '0')), 2);
        $note = mb_substr(trim((string) ($_POST['disposal_note'] ?? '')), 0, 255);
        $acct = (int) ($_POST['account_id'] ?? 0);
        $err = null;
        if (in_array($a['status'], ['disposed', 'lost'], true)) {
            $err = 'This asset is already off the register.';
        } elseif (!Validator::isDate($date) || $date > date('Y-m-d') || $date < $a['purchase_date']) {
            $err = 'Enter the date (not in the future, not before it was bought).';
        } elseif ($note === '') {
            $err = 'Say what happened (e.g. sold to …, broken beyond repair, stolen — police report no.).';
        } elseif ($amount > 0 && !db()->value('SELECT id FROM fin_accounts WHERE id = ? AND is_active = 1', [$acct])) {
            $err = 'Choose the account the sale money was paid into.';
        } elseif ($amount > 0 && ($e = Finance::closedError($date))) {
            $err = $e;
        }
        if ($err) {
            flash('error', $err);
            redirect('fixed-assets/' . $id);
        }
        db()->transaction(function (Database $d) use ($a, $status, $date, $amount, $note, $acct) {
            $d->update('fixed_assets', ['status' => $status, 'disposed_on' => $date, 'disposal_amount' => $amount > 0 ? $amount : null, 'disposal_note' => $note], 'id = ?', [$a['id']]);
            if ($amount > 0) {
                Finance::post($d, [
                    'kind' => 'income', 'txn_date' => $date, 'account_id' => $acct, 'category_id' => $d->value("SELECT id FROM fin_categories WHERE code = 'OINC'") ?: null,
                    'amount' => $amount, 'description' => 'Sale of asset ' . $a['asset_no'] . ' — ' . $a['name'], 'source' => 'asset_disposal', 'source_id' => $a['id'],
                ]);
            }
        });
        Assets::log($id, $status, $note . ($amount > 0 ? ' · sold for ' . number_format($amount, 2) : ''));
        audit('asset_' . $status, 'fixed_assets', $id, ['amount' => $amount, 'note' => $note]);
        flash('success', '“' . $a['name'] . '” taken off the register.');
        redirect('fixed-assets/' . $id);
    }

    /** Printable register (and depreciation for a year). */
    public function printout(): void
    {
        $this->needsTable('fixed_assets');
        $year = max(2000, min((int) date('Y'), (int) ($_GET['year'] ?? date('Y'))));
        $rows = db()->all("SELECT a.*, u.name AS holder_name FROM fixed_assets a LEFT JOIN users u ON u.id = a.holder_id
                           WHERE a.purchase_date <= ? AND (a.status IN ('in_use','repair') OR a.disposed_on >= ?) ORDER BY a.category, a.name, a.asset_no", [$year . '-12-31', $year . '-01-01']);
        foreach ($rows as &$r) {
            $r['v'] = Assets::value($r, $year . '-12-31', $year);
        }
        unset($r);
        $this->view('assets/print', ['title' => 'Fixed asset register ' . $year, 'rows' => $rows, 'year' => $year], 'layouts/print');
    }
}
