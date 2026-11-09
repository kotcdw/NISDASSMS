<?php
declare(strict_types=1);

/** School bus: routes, which pupils ride which bus, printable route lists, and bus fees on the term bills. */
class TransportController extends Controller
{
    public const TRIPS = ['both' => 'Morning & afternoon', 'morning' => 'Morning only', 'afternoon' => 'Afternoon only'];

    public function index(): void
    {
        if (in_array(Auth::role(), ['parent', 'pupil'], true)) {
            $pupils = Auth::role() === 'pupil'
                ? array_filter([Accounts::pupilOf((int) Auth::id())])
                : array_values(array_filter(Accounts::childrenOf((int) Auth::id()), static fn($p) => $p['status'] === 'active'));
            $ids = array_map(static fn($p) => (int) $p['id'], $pupils);
            $assignments = [];
            if ($ids) {
                $in = implode(',', array_fill(0, count($ids), '?'));
                $assignments = db()->all(
                    "SELECT a.pupil_id, p.first_name, p.last_name, c.name AS class_name, a.stop, a.trips,
                            r.id AS route_id, r.name AS route_name, r.stops, r.vehicle, r.driver_name, r.driver_phone, r.attendant_name, r.attendant_phone
                     FROM transport_assignments a JOIN transport_routes r ON r.id = a.route_id
                     JOIN pupils p ON p.id = a.pupil_id LEFT JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = a.academic_year_id
                     LEFT JOIN classes c ON c.id = e.class_id
                     WHERE a.pupil_id IN ($in) AND a.academic_year_id = ? AND a.ended_on IS NULL ORDER BY p.first_name, p.last_name",
                    array_merge($ids, [current_year()['id'] ?? 0])
                );
            }
            $this->view('transport/portal_index', ['title' => 'My transport', 'assignments' => $assignments]);
            return;
        }
        $yid = current_year()['id'] ?? 0;
        $routes = db()->all(
            'SELECT r.*, (SELECT COUNT(*) FROM transport_assignments a JOIN pupils p ON p.id = a.pupil_id
                          WHERE a.route_id = r.id AND a.academic_year_id = ? AND a.ended_on IS NULL AND p.status = "active") AS riders
             FROM transport_routes r ORDER BY r.is_active DESC, r.name',
            [$yid]
        );
        $term = current_term();
        $unbilled = $term ? (int) db()->value(
            "SELECT COUNT(*) FROM transport_assignments a JOIN transport_routes r ON r.id = a.route_id JOIN pupils p ON p.id = a.pupil_id
             WHERE a.academic_year_id = ? AND a.ended_on IS NULL AND p.status = 'active' AND r.fee_per_term > 0
               AND NOT EXISTS (SELECT 1 FROM transport_bills tb JOIN invoices i ON i.id = tb.invoice_id WHERE tb.pupil_id = a.pupil_id AND tb.term_id = ? AND i.status <> 'cancelled')",
            [$yid, $term['id']]
        ) : 0;
        $this->view('transport/index', ['title' => 'Transport', 'routes' => $routes, 'term' => $term, 'unbilled' => $unbilled]);
    }

    public function create(): void
    {
        $this->view('transport/route_form', ['title' => 'New bus route', 'r' => null, 'riders' => []]);
    }

    public function store(): void
    {
        $data = $this->routeInput('transport/routes/create');
        $id = db()->insert('transport_routes', $data);
        audit('route_created', 'transport_routes', $id, ['name' => $data['name']]);
        flash('success', 'Route saved. Now add the students who ride it.');
        redirect('transport/routes/' . $id);
    }

    public function show(int $id): void
    {
        if (in_array(Auth::role(), ['parent', 'pupil'], true)) {
            $pupils = Auth::role() === 'pupil'
                ? array_filter([Accounts::pupilOf((int) Auth::id())])
                : array_values(array_filter(Accounts::childrenOf((int) Auth::id()), static fn($p) => $p['status'] === 'active'));
            $ids = array_map(static fn($p) => (int) $p['id'], $pupils);
            if (!$ids) {
                $this->notFound();
            }
            $in = implode(',', array_fill(0, count($ids), '?'));
            $assigned = db()->all(
                "SELECT p.first_name, p.last_name, c.name AS class_name, a.stop, a.trips
                 FROM transport_assignments a JOIN pupils p ON p.id = a.pupil_id
                 LEFT JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = a.academic_year_id
                 LEFT JOIN classes c ON c.id = e.class_id
                 WHERE a.route_id = ? AND a.pupil_id IN ($in) AND a.academic_year_id = ? AND a.ended_on IS NULL ORDER BY p.first_name, p.last_name",
                array_merge([$id], $ids, [current_year()['id'] ?? 0])
            );
            if (!$assigned) {
                $this->notFound();
            }
            $route = db()->one('SELECT id, name, stops, vehicle, driver_name, driver_phone, attendant_name, attendant_phone FROM transport_routes WHERE id = ?', [$id]) ?? $this->notFound();
            $this->view('transport/portal_route', ['title' => $route['name'], 'route' => $route, 'assigned' => $assigned]);
            return;
        }
        $r = db()->one('SELECT * FROM transport_routes WHERE id = ?', [$id]) ?? $this->notFound();
        $this->view('transport/route_form', ['title' => $r['name'], 'r' => $r, 'riders' => $this->riders($id)]);
    }

    public function update(int $id): void
    {
        db()->one('SELECT id FROM transport_routes WHERE id = ?', [$id]) ?? $this->notFound();
        $data = $this->routeInput('transport/routes/' . $id, $id);
        $data['is_active'] = !empty($_POST['is_active']) ? 1 : 0;
        db()->update('transport_routes', $data, 'id = ?', [$id]);
        audit('route_updated', 'transport_routes', $id);
        flash('success', 'Route saved.');
        redirect('transport/routes/' . $id);
    }

    public function assign(int $id): void
    {
        $r = db()->one('SELECT * FROM transport_routes WHERE id = ? AND is_active = 1', [$id]) ?? $this->notFound();
        $year = current_year();
        if (!$year) {
            flash('error', 'Set the current academic year first (School setup).');
            redirect('transport/routes/' . $id);
        }
        $adm = strtolower(trim((string) ($_POST['admission_no'] ?? '')));
        $p = db()->one("SELECT id, first_name, last_name FROM pupils WHERE LOWER(admission_no) = ? AND status = 'active' AND deleted_at IS NULL", [$adm]);
        if (!$p) {
            flash('error', 'No active student has admission number "' . $adm . '".');
            redirect('transport/routes/' . $id);
        }
        $trips = isset(self::TRIPS[$_POST['trips'] ?? '']) ? $_POST['trips'] : 'both';
        $stop = mb_substr(trim((string) ($_POST['stop'] ?? '')), 0, 80) ?: null;
        $riders = count($this->riders($id));
        if ($riders >= (int) $r['capacity']) {
            flash('warning', 'Note: this bus is now over its capacity of ' . (int) $r['capacity'] . '.');
        }
        $existing = db()->one('SELECT * FROM transport_assignments WHERE pupil_id = ? AND academic_year_id = ?', [$p['id'], $year['id']]);
        if ($existing) {
            db()->update('transport_assignments', ['route_id' => $id, 'stop' => $stop, 'trips' => $trips, 'ended_on' => null, 'started_on' => $existing['ended_on'] ? date('Y-m-d') : $existing['started_on']], 'id = ?', [$existing['id']]);
        } else {
            db()->insert('transport_assignments', ['pupil_id' => $p['id'], 'route_id' => $id, 'academic_year_id' => $year['id'], 'stop' => $stop, 'trips' => $trips, 'started_on' => date('Y-m-d')]);
        }
        audit('transport_assigned', 'pupils', (int) $p['id'], ['route' => $id]);
        flash('success', $p['first_name'] . ' ' . $p['last_name'] . ' now rides ' . $r['name'] . ($existing && (int) $existing['route_id'] !== $id ? ' (moved from another route)' : '') . '.');
        redirect('transport/routes/' . $id);
    }

    public function endAssignment(int $id): void
    {
        $a = db()->one('SELECT * FROM transport_assignments WHERE id = ?', [$id]) ?? $this->notFound();
        db()->update('transport_assignments', ['ended_on' => date('Y-m-d')], 'id = ?', [$id]);
        audit('transport_ended', 'pupils', (int) $a['pupil_id'], ['route' => $a['route_id']]);
        flash('success', 'Removed from the bus. Bills already issued are not changed.');
        redirect('transport/routes/' . $a['route_id']);
    }

    /** Printable route list for the driver/attendant, with parents' phone numbers. */
    public function manifest(int $id): void
    {
        if (!can('staff')) {
            $this->notFound();
        }
        $r = db()->one('SELECT * FROM transport_routes WHERE id = ?', [$id]) ?? $this->notFound();
        $this->view('transport/manifest', ['title' => 'Bus list — ' . $r['name'], 'r' => $r, 'riders' => $this->riders($id)], 'layouts/print');
    }

    /** Add bus fees for the current term to each rider's account (one bill per pupil per term). */
    public function bill(): void
    {
        $term = current_term();
        if (!$term) {
            flash('error', 'Set the current term first.');
            redirect('transport');
        }
        $yid = (int) $term['academic_year_id'];
        $due = (string) ($_POST['due_date'] ?? '');
        $due = preg_match('/^\d{4}-\d{2}-\d{2}$/', $due) ? $due : null;
        $rows = db()->all(
            "SELECT a.pupil_id AS id, a.trips, a.stop, r.name AS route_name, r.fee_per_term, e.class_id
             FROM transport_assignments a JOIN transport_routes r ON r.id = a.route_id JOIN pupils p ON p.id = a.pupil_id
             LEFT JOIN enrolments e ON e.pupil_id = a.pupil_id AND e.academic_year_id = a.academic_year_id
             WHERE a.academic_year_id = ? AND a.ended_on IS NULL AND p.status = 'active' AND r.fee_per_term > 0",
            [$yid]
        );
        $made = 0;
        $total = 0.0;
        foreach ($rows as $row) {
            $made += db()->transaction(function (Database $d) use ($row, $term, $due, &$total) {
                $have = $d->one('SELECT tb.invoice_id, i.status FROM transport_bills tb JOIN invoices i ON i.id = tb.invoice_id WHERE tb.pupil_id = ? AND tb.term_id = ? FOR UPDATE', [$row['id'], $term['id']]);
                if ($have && $have['status'] !== 'cancelled') {
                    return 0;
                }
                $inv = Fees::insertInvoice($d, $row, $term, 'other', 'Bus fees ' . $term['name'] . ' ' . $term['year_name'], $due);
                $d->insert('invoice_lines', ['invoice_id' => $inv, 'description' => 'School bus — ' . $row['route_name'] . ($row['trips'] !== 'both' ? ' (' . strtolower(self::TRIPS[$row['trips']]) . ')' : ''), 'amount' => $row['fee_per_term']]);
                Fees::recalc($inv, $d);
                $d->run('REPLACE INTO transport_bills (pupil_id, term_id, invoice_id) VALUES (?, ?, ?)', [$row['id'], $term['id'], $inv]);
                $total += (float) $row['fee_per_term'];
                return 1;
            });
        }
        audit('transport_billed', 'terms', (int) $term['id'], ['bills' => $made, 'total' => $total]);
        flash('success', $made ? "$made bus fee bill(s) created for " . $term['name'] . ' (' . Fees::money($total) . '). Parents see them with their other bills.' : 'Every rider already has a bus bill for this term.');
        redirect('transport');
    }

    // ------------------------------------------------------------------ helpers

    private function riders(int $routeId): array
    {
        $yid = current_year()['id'] ?? 0;
        return db()->all(
            "SELECT a.*, p.admission_no, p.first_name, p.last_name, c.name AS class_name,
                    (SELECT g.name FROM pupil_guardian pg JOIN guardians g ON g.id = pg.guardian_id WHERE pg.pupil_id = p.id ORDER BY pg.is_primary DESC LIMIT 1) AS guardian_name,
                    (SELECT g.phone FROM pupil_guardian pg JOIN guardians g ON g.id = pg.guardian_id WHERE pg.pupil_id = p.id ORDER BY pg.is_primary DESC LIMIT 1) AS guardian_phone
             FROM transport_assignments a JOIN pupils p ON p.id = a.pupil_id
             LEFT JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = a.academic_year_id LEFT JOIN classes c ON c.id = e.class_id
             WHERE a.route_id = ? AND a.academic_year_id = ? AND a.ended_on IS NULL AND p.status = 'active'
             ORDER BY a.stop, p.last_name, p.first_name",
            [$routeId, $yid]
        );
    }

    public static function hasPapers(): bool
    {
        static $h = null;
        return $h ??= (bool) db()->value("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'transport_routes' AND column_name = 'insurance_expiry'");
    }

    /** Routes whose driver's licence, roadworthy certificate or insurance has expired or expires within 30 days. */
    public static function papersDue(): int
    {
        if (!self::hasPapers()) {
            return 0;
        }
        return (int) db()->value("SELECT COUNT(*) FROM transport_routes WHERE is_active = 1 AND (driver_licence_expiry <= CURDATE() + INTERVAL 30 DAY OR roadworthy_expiry <= CURDATE() + INTERVAL 30 DAY OR insurance_expiry <= CURDATE() + INTERVAL 30 DAY)");
    }

    private function routeInput(string $back, ?int $id = null): array
    {
        $in = $this->only(['name', 'stops', 'vehicle', 'driver_name', 'driver_phone', 'attendant_name', 'attendant_phone', 'capacity', 'fee_per_term']);
        $errors = Validator::check($in, [
            'name' => 'required|max:80', 'stops' => 'max:500', 'vehicle' => 'max:60', 'driver_name' => 'max:100', 'attendant_name' => 'max:100',
            'driver_phone' => 'phone', 'attendant_phone' => 'phone',
        ]);
        if ($in['name'] && db()->value('SELECT id FROM transport_routes WHERE name = ? AND id <> ?', [$in['name'], $id ?? 0])) {
            $errors['name'] = 'Another route already has this name.';
        }
        if (!ctype_digit((string) $in['capacity']) || (int) $in['capacity'] < 1 || (int) $in['capacity'] > 200) {
            $errors['capacity'] = 'Enter the number of seats.';
        }
        if ($in['fee_per_term'] !== null && (!is_numeric($in['fee_per_term']) || (float) $in['fee_per_term'] < 0 || (float) $in['fee_per_term'] > 50000)) {
            $errors['fee_per_term'] = 'Enter the bus fee per term in GH₵ (0 if free).';
        }
        if ($errors) {
            with_old($_POST, $errors);
            flash('error', 'Please correct the highlighted fields.');
            redirect($back);
        }
        foreach (['driver_phone', 'attendant_phone'] as $f) {
            $in[$f] = $in[$f] ? Admission::normalisePhone($in[$f]) : null;
        }
        $in['capacity'] = (int) $in['capacity'];
        $in['fee_per_term'] = round((float) ($in['fee_per_term'] ?? 0), 2);
        if (self::hasPapers()) {
            // Driver's licence and vehicle papers (expiry dates are flagged on the dashboard 30 days ahead)
            $in['driver_licence_no'] = mb_substr(trim((string) ($_POST['driver_licence_no'] ?? '')), 0, 40) ?: null;
            foreach (['driver_licence_expiry', 'roadworthy_expiry', 'insurance_expiry'] as $f) {
                $v = trim((string) ($_POST[$f] ?? ''));
                $in[$f] = $v !== '' && Validator::isDate($v) ? $v : null;
            }
        }
        return $in;
    }
}
