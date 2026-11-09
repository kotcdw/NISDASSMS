<?php
declare(strict_types=1);

/**
 * School events: plan and publish (shown on the calendar), registration by parents for their children
 * (with an optional fee billed to the pupil), capacity with waiting list, check-in on the day and a report.
 */
class EventController extends Controller
{
    public const STATUS = ['draft' => ['Draft', 'neutral'], 'published' => ['Open', 'info'], 'cancelled' => ['Cancelled', 'bad'], 'completed' => ['Finished', 'good']];
    public const AUDIENCE = ['all' => 'Everyone', 'parents' => 'Parents & students', 'staff' => 'Staff only', 'level' => 'One level'];

    public function index(): void
    {
        $manage = can('events.manage');
        $show = (string) ($_GET['show'] ?? 'upcoming');
        $sql = 'SELECT e.*, l.name AS level_name,
                (SELECT COUNT(*) + COALESCE(SUM(guests), 0) FROM event_registrations r WHERE r.event_id = e.id AND r.status = "registered") AS taken
                FROM school_events e LEFT JOIN levels l ON l.id = e.level_id WHERE 1=1';
        $p = [];
        if (!$manage) {
            $sql .= " AND e.status IN ('published','completed') AND " . $this->audienceSql();
        }
        if ($show === 'upcoming') {
            $sql .= ' AND COALESCE(e.end_at, e.start_at) >= CURDATE()';
        }
        $rows = db()->all($sql . ' ORDER BY e.start_at' . ($show === 'upcoming' ? '' : ' DESC') . ' LIMIT 100', $p);
        $mine = [];
        if (Auth::role() === 'parent' || can('staff')) {
            foreach (db()->all("SELECT event_id, COUNT(*) n FROM event_registrations WHERE user_id = ? AND status <> 'cancelled' GROUP BY event_id", [Auth::id()]) as $r) {
                $mine[(int) $r['event_id']] = (int) $r['n'];
            }
        }
        $this->view('events/index', ['title' => 'Events', 'rows' => $rows, 'manage' => $manage, 'show' => $show, 'mine' => $mine]);
    }

    public function create(): void
    {
        $this->view('events/form', ['title' => 'New event', 'ev' => null, 'levels' => db()->all('SELECT id, name FROM levels ORDER BY sort_order')]);
    }

    public function store(): void
    {
        $data = $this->input('events/create');
        $id = db()->insert('school_events', $data + ['created_by' => Auth::id()]);
        $this->syncCalendar($id);
        audit('event_created', 'school_events', $id);
        flash('success', $data['status'] === 'published' ? 'Event published. It is on the calendar and parents can register.' : 'Event saved as a draft.');
        redirect('events/' . $id);
    }

    public function show(int $id): void
    {
        $ev = $this->find($id);
        $regs = can('events.manage') ? db()->all(
            "SELECT r.*, p.first_name, p.last_name, p.admission_no, c.name AS class_name, u.name AS user_name, u.phone, i.invoice_no, i.status AS inv_status
             FROM event_registrations r LEFT JOIN pupils p ON p.id = r.pupil_id LEFT JOIN users u ON u.id = r.user_id
             LEFT JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ? AND e.status = 'active' LEFT JOIN classes c ON c.id = e.class_id
             LEFT JOIN invoices i ON i.id = r.invoice_id WHERE r.event_id = ? ORDER BY r.status, c.name, p.last_name, u.name",
            [current_year()['id'] ?? 0, $id]
        ) : [];
        $myRegs = db()->all("SELECT r.*, p.first_name, p.last_name, i.public_key, i.status AS inv_status FROM event_registrations r LEFT JOIN pupils p ON p.id = r.pupil_id LEFT JOIN invoices i ON i.id = r.invoice_id WHERE r.event_id = ? AND r.user_id = ? AND r.status <> 'cancelled'", [$id, Auth::id()]);
        $children = Auth::role() === 'parent' ? array_values(array_filter(Accounts::childrenOf((int) Auth::id()), fn($c) => $c['status'] === 'active' && $this->childFits($ev, $c))) : [];
        $this->view('events/show', ['title' => $ev['title'], 'ev' => $ev, 'regs' => $regs, 'myRegs' => $myRegs, 'children' => $children, 'left' => $this->left($ev), 'canRegister' => $this->open($ev)]);
    }

    public function edit(int $id): void
    {
        $ev = $this->find($id, true);
        $this->view('events/form', ['title' => 'Edit event', 'ev' => $ev, 'levels' => db()->all('SELECT id, name FROM levels ORDER BY sort_order')]);
    }

    public function update(int $id): void
    {
        $ev = $this->find($id, true);
        $data = $this->input('events/' . $id . '/edit');
        if ((float) $ev['fee'] !== (float) $data['fee'] && db()->value("SELECT 1 FROM event_registrations WHERE event_id = ? AND invoice_id IS NOT NULL LIMIT 1", [$id])) {
            $data['fee'] = $ev['fee'];
            flash('warning', 'The fee was not changed because some families have already been billed.');
        }
        if ($ev['status'] !== 'draft' && $data['status'] === 'draft') {
            $data['status'] = $ev['status'];
        }
        db()->update('school_events', $data, 'id = ?', [$id]);
        $this->syncCalendar($id);
        audit('event_updated', 'school_events', $id);
        flash('success', 'Event updated.');
        redirect('events/' . $id);
    }

    public function status(int $id): void
    {
        $ev = $this->find($id, true);
        $to = (string) ($_POST['to'] ?? '');
        if (!in_array($to, ['published', 'cancelled', 'completed'], true)) {
            redirect('events/' . $id);
        }
        db()->update('school_events', ['status' => $to], 'id = ?', [$id]);
        if ($to === 'cancelled') {
            // Unpaid event bills are cancelled; families are told
            foreach (db()->all("SELECT r.*, i.paid FROM event_registrations r LEFT JOIN invoices i ON i.id = r.invoice_id WHERE r.event_id = ? AND r.status <> 'cancelled'", [$id]) as $r) {
                if ($r['invoice_id'] && (float) $r['paid'] == 0.0) {
                    db()->update('invoices', ['status' => 'cancelled'], 'id = ?', [$r['invoice_id']]);
                }
                if ($r['user_id']) {
                    Notifier::inapp([(int) $r['user_id']], 'Event cancelled: ' . $ev['title'], (float) ($r['paid'] ?? 0) > 0 ? 'Please see the accounts office about the money you paid.' : null, 'events/' . $id);
                }
            }
        }
        $this->syncCalendar($id);
        audit('event_' . $to, 'school_events', $id);
        flash('success', ['published' => 'Event published.', 'cancelled' => 'Event cancelled; unpaid bills cancelled and families told.', 'completed' => 'Event marked as finished.'][$to]);
        redirect('events/' . $id);
    }

    /** Parent registers children; staff register themselves. */
    public function register(int $id): void
    {
        $ev = $this->find($id);
        if (!$this->open($ev)) {
            flash('error', 'Registration is closed for this event.');
            redirect('events/' . $id);
        }
        $guests = max(0, min(10, (int) ($_POST['guests'] ?? 0)));
        $pupils = [];
        if (Auth::role() === 'parent') {
            $mine = array_column(array_filter(Accounts::childrenOf((int) Auth::id()), fn($c) => $c['status'] === 'active' && $this->childFits($ev, $c)), null, 'id');
            foreach ((array) ($_POST['pupils'] ?? []) as $pid) {
                if (isset($mine[(int) $pid])) {
                    $pupils[] = $mine[(int) $pid];
                }
            }
            if (!$pupils) {
                flash('error', 'Tick the child or children to register.');
                redirect('events/' . $id);
            }
        } elseif (!can('staff') || !in_array($ev['audience'], ['all', 'staff'], true)) {
            $this->forbidden();
        }
        $made = 0;
        $wait = 0;
        foreach ($pupils ?: [null] as $c) {
            $pid = $c ? (int) $c['id'] : null;
            if (db()->value("SELECT 1 FROM event_registrations WHERE event_id = ? AND status <> 'cancelled' AND " . ($pid ? 'pupil_id = ?' : 'user_id = ? AND pupil_id IS NULL'), [$id, $pid ?: Auth::id()])) {
                continue;
            }
            $left = $this->left($ev);
            $status = $left !== null && $left < 1 + ($made === 0 ? $guests : 0) ? 'waitlist' : 'registered';
            $old = $pid ? db()->one("SELECT id FROM event_registrations WHERE event_id = ? AND pupil_id = ? AND status = 'cancelled'", [$id, $pid]) : null;
            $row = ['status' => $status, 'guests' => $made === 0 ? $guests : 0, 'user_id' => Auth::id(), 'note' => mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 255) ?: null, 'invoice_id' => null, 'attended' => 0];
            if ($old) {
                db()->update('event_registrations', $row, 'id = ?', [$old['id']]);
                $rid = (int) $old['id'];
            } else {
                $rid = db()->insert('event_registrations', $row + ['event_id' => $id, 'pupil_id' => $pid]);
            }
            if ($status === 'registered') {
                $this->bill($ev, $rid);
                $made++;
            } else {
                $wait++;
            }
        }
        audit('event_registered', 'school_events', $id, ['registered' => $made, 'waitlist' => $wait]);
        flash($wait ? 'warning' : 'success', ($made ? "$made registered." : '') . ($wait ? " $wait on the waiting list (the event is full) — you will be told if a place opens." : '') . ($made && (float) $ev['fee'] > 0 ? ' A bill of ' . Fees::money($ev['fee']) . ' per child was added; pay it with your fees.' : ''));
        redirect('events/' . $id);
    }

    public function cancelReg(int $id, int $rid): void
    {
        $ev = $this->find($id);
        $r = db()->one('SELECT r.*, i.paid FROM event_registrations r LEFT JOIN invoices i ON i.id = r.invoice_id WHERE r.id = ? AND r.event_id = ?', [$rid, $id]) ?? $this->notFound();
        if (!can('events.manage') && (int) $r['user_id'] !== Auth::id()) {
            $this->notFound();
        }
        if ($r['status'] === 'cancelled' || substr($ev['start_at'], 0, 10) < date('Y-m-d')) {
            flash('error', 'This registration cannot be cancelled now.');
            redirect('events/' . $id);
        }
        db()->update('event_registrations', ['status' => 'cancelled'], 'id = ?', [$rid]);
        $paidNote = '';
        if ($r['invoice_id']) {
            if ((float) $r['paid'] == 0.0) {
                db()->update('invoices', ['status' => 'cancelled'], 'id = ?', [$r['invoice_id']]);
            } else {
                $paidNote = ' The bill was already paid — see the accounts office about a refund.';
            }
        }
        // First on the waiting list gets the place
        if ($r['status'] === 'registered' && ($w = db()->one("SELECT * FROM event_registrations WHERE event_id = ? AND status = 'waitlist' ORDER BY id LIMIT 1", [$id]))) {
            $ev = $this->find($id);
            if (($this->left($ev) ?? 1) >= 1 + (int) $w['guests']) {
                db()->update('event_registrations', ['status' => 'registered'], 'id = ?', [$w['id']]);
                $this->bill($ev, (int) $w['id']);
                if ($w['user_id']) {
                    Notifier::inapp([(int) $w['user_id']], 'A place opened: ' . $ev['title'], 'You are now registered.', 'events/' . $id);
                }
            }
        }
        audit('event_reg_cancelled', 'school_events', $id, ['registration' => $rid]);
        flash('success', 'Registration cancelled.' . $paidNote);
        redirect('events/' . $id);
    }

    public function checkin(int $id, int $rid): void
    {
        $this->find($id, true);
        $r = db()->one("SELECT * FROM event_registrations WHERE id = ? AND event_id = ? AND status = 'registered'", [$rid, $id]) ?? $this->notFound();
        $on = !(int) $r['attended'];
        db()->update('event_registrations', ['attended' => $on ? 1 : 0, 'checked_in_at' => $on ? date('Y-m-d H:i:s') : null], 'id = ?', [$rid]);
        flash('success', $on ? 'Checked in.' : 'Check-in removed.');
        redirect('events/' . $id . '#r' . $rid);
    }

    public function export(int $id): void
    {
        $ev = $this->find($id, true);
        $out = csv_start('event-' . $id . '.csv', [$ev['title'], date('j M Y H:i', strtotime($ev['start_at']))]);
        csv_row($out, ['Student', 'Class', 'Registered by', 'Phone', 'Guests', 'Status', 'Bill', 'Bill status', 'Attended']);
        foreach (db()->all(
            "SELECT r.*, p.first_name, p.last_name, c.name AS class_name, u.name AS user_name, u.phone, i.invoice_no, i.status AS inv_status FROM event_registrations r
             LEFT JOIN pupils p ON p.id = r.pupil_id LEFT JOIN users u ON u.id = r.user_id LEFT JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ? AND e.status = 'active'
             LEFT JOIN classes c ON c.id = e.class_id LEFT JOIN invoices i ON i.id = r.invoice_id WHERE r.event_id = ? ORDER BY c.name, p.last_name",
            [current_year()['id'] ?? 0, $id]
        ) as $r) {
            csv_row($out, [trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: '(staff)', $r['class_name'], $r['user_name'], $r['phone'], $r['guests'], $r['status'], $r['invoice_no'], $r['inv_status'], $r['attended'] ? 'Yes' : 'No']);
        }
        csv_end($out);
    }

    // ---------------------------------------------------------------- helpers

    private function bill(array $ev, int $rid): void
    {
        $r = db()->one('SELECT * FROM event_registrations WHERE id = ?', [$rid]);
        if ((float) $ev['fee'] <= 0 || !$r['pupil_id'] || $r['invoice_id']) {
            return;
        }
        $pupil = db()->one("SELECT p.id, e.class_id FROM pupils p LEFT JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ? AND e.status = 'active' WHERE p.id = ?", [current_year()['id'] ?? 0, $r['pupil_id']]);
        db()->transaction(function (Database $d) use ($ev, $rid, $pupil) {
            $due = $ev['reg_deadline'] ?: substr($ev['start_at'], 0, 10);
            $inv = Fees::insertInvoice($d, $pupil, current_term(), 'other', 'Event: ' . $ev['title'], max($due, date('Y-m-d')));
            $d->insert('invoice_lines', ['invoice_id' => $inv, 'description' => mb_substr('Event: ' . $ev['title'], 0, 150), 'amount' => $ev['fee']]);
            Fees::recalc($inv, $d);
            $d->update('event_registrations', ['invoice_id' => $inv], 'id = ?', [$rid]);
        });
    }

    /** Places left (null = no limit). Guests count as places. */
    private function left(array $ev): ?int
    {
        if (!$ev['capacity']) {
            return null;
        }
        $taken = (int) db()->value("SELECT COUNT(*) + COALESCE(SUM(guests), 0) FROM event_registrations WHERE event_id = ? AND status = 'registered'", [$ev['id']]);
        return max(0, (int) $ev['capacity'] - $taken);
    }

    private function open(array $ev): bool
    {
        return $ev['status'] === 'published' && (int) $ev['registration'] && substr($ev['start_at'], 0, 10) >= date('Y-m-d')
            && (!$ev['reg_deadline'] || $ev['reg_deadline'] >= date('Y-m-d'));
    }

    private function childFits(array $ev, array $c): bool
    {
        return $ev['audience'] !== 'staff' && ($ev['audience'] !== 'level' || (int) ($c['level_id'] ?? 0) === (int) $ev['level_id']);
    }

    private function audienceSql(): string
    {
        if (can('staff')) {
            return "e.audience IN ('all','staff','parents','level')";
        }
        if (Auth::role() === 'parent') {
            $levels = array_filter(array_map('intval', array_column(Accounts::childrenOf((int) Auth::id()), 'level_id')));
            return "(e.audience IN ('all','parents')" . ($levels ? " OR (e.audience = 'level' AND e.level_id IN (" . implode(',', $levels) . '))' : '') . ')';
        }
        if (Auth::role() === 'pupil') {
            $lv = (int) db()->value("SELECT c.level_id FROM pupils p JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ? AND e.status = 'active' JOIN classes c ON c.id = e.class_id WHERE p.user_id = ?", [current_year()['id'] ?? 0, Auth::id()]);
            return "(e.audience IN ('all','parents')" . ($lv ? " OR (e.audience = 'level' AND e.level_id = $lv)" : '') . ')';
        }
        return '0=1';
    }

    private function find(int $id, bool $manage = false): array
    {
        if ($manage && !can('events.manage')) {
            $this->forbidden();
        }
        $ev = db()->one('SELECT e.*, l.name AS level_name, u.name AS created_name FROM school_events e LEFT JOIN levels l ON l.id = e.level_id LEFT JOIN users u ON u.id = e.created_by WHERE e.id = ?', [$id]) ?? $this->notFound();
        if (!can('events.manage') && (!in_array($ev['status'], ['published', 'completed', 'cancelled'], true) || !db()->value('SELECT 1 FROM school_events e WHERE e.id = ? AND ' . $this->audienceSql(), [$id]))) {
            $this->notFound();
        }
        return $ev;
    }

    private function input(string $back): array
    {
        $in = $this->only(['title', 'description', 'venue', 'start_date', 'start_time', 'end_date', 'end_time', 'audience', 'level_id', 'capacity', 'fee', 'reg_deadline']);
        $t = '/^([01]\d|2[0-3]):[0-5]\d$/';
        $fee = str_replace(',', '', (string) ($in['fee'] ?? '0'));
        $err = null;
        if (!$in['title']) {
            $err = 'Give the event a title.';
        } elseif (!Validator::isDate((string) $in['start_date']) || ($in['start_time'] && !preg_match($t, $in['start_time']))) {
            $err = 'Enter the date (and time) the event starts.';
        } elseif ($in['end_date'] && (!Validator::isDate($in['end_date']) || $in['end_date'] < $in['start_date'])) {
            $err = 'The end date is before the start.';
        } elseif (!isset(self::AUDIENCE[(string) $in['audience']]) || ($in['audience'] === 'level' && !db()->value('SELECT id FROM levels WHERE id = ?', [(int) $in['level_id']]))) {
            $err = 'Choose who the event is for (and the level).';
        } elseif ($in['capacity'] !== null && (!ctype_digit((string) $in['capacity']) || (int) $in['capacity'] < 1)) {
            $err = 'Capacity must be a whole number, or left empty.';
        } elseif ($fee !== '' && (!is_numeric($fee) || (float) $fee < 0 || (float) $fee > 10000)) {
            $err = 'Enter a fee of 0 or more.';
        } elseif ($in['reg_deadline'] && (!Validator::isDate($in['reg_deadline']) || $in['reg_deadline'] > $in['start_date'])) {
            $err = 'The registration deadline must be on or before the event day.';
        }
        if ($err) {
            with_old($_POST);
            flash('error', $err);
            redirect($back);
        }
        $start = $in['start_date'] . ' ' . ($in['start_time'] ?: '00:00') . ':00';
        $end = $in['end_date'] || $in['end_time'] ? ($in['end_date'] ?: $in['start_date']) . ' ' . ($in['end_time'] && preg_match($t, $in['end_time']) ? $in['end_time'] : '23:59') . ':00' : null;
        return [
            'title' => mb_substr($in['title'], 0, 150), 'description' => $in['description'] ? mb_substr($in['description'], 0, 5000) : null, 'venue' => $in['venue'] ? mb_substr($in['venue'], 0, 120) : null,
            'start_at' => $start, 'end_at' => $end, 'audience' => $in['audience'], 'level_id' => $in['audience'] === 'level' ? (int) $in['level_id'] : null,
            'capacity' => $in['capacity'] !== null ? (int) $in['capacity'] : null, 'fee' => round((float) $fee, 2), 'registration' => empty($_POST['no_registration']) ? 1 : 0,
            'reg_deadline' => $in['reg_deadline'] ?: null, 'status' => ($_POST['status'] ?? '') === 'draft' ? 'draft' : 'published',
        ];
    }

    /** Keep a matching entry on the Academic Calendar for published events. */
    private function syncCalendar(int $id): void
    {
        $ev = db()->one('SELECT * FROM school_events WHERE id = ?', [$id]);
        if ($ev['status'] === 'draft' || $ev['status'] === 'cancelled') {
            if ($ev['calendar_event_id']) {
                db()->update('school_events', ['calendar_event_id' => null], 'id = ?', [$id]);
                db()->run('DELETE FROM calendar_events WHERE id = ?', [$ev['calendar_event_id']]);
            }
            return;
        }
        $cal = ['title' => mb_substr($ev['title'], 0, 150), 'description' => $ev['description'], 'category' => 'event', 'start_date' => substr($ev['start_at'], 0, 10),
            'end_date' => substr($ev['end_at'] ?? $ev['start_at'], 0, 10), 'start_time' => substr($ev['start_at'], 11, 8) !== '00:00:00' ? substr($ev['start_at'], 11, 8) : null,
            'audience' => $ev['audience'], 'level_id' => $ev['level_id']];
        if ($ev['calendar_event_id'] && db()->value('SELECT id FROM calendar_events WHERE id = ?', [$ev['calendar_event_id']])) {
            db()->update('calendar_events', $cal, 'id = ?', [$ev['calendar_event_id']]);
        } else {
            $cid = db()->insert('calendar_events', $cal + ['created_by' => Auth::id(), 'term_id' => Finance::termFor($cal['start_date'])]);
            db()->update('school_events', ['calendar_event_id' => $cid], 'id = ?', [$id]);
        }
    }
}
