<?php
declare(strict_types=1);

/**
 * GES registers kept at the office: the Head teacher's log book, the visitors' book and the staff movement book.
 * Log book entries are a permanent record: the writer may correct an entry on the day it was written; after that
 * a new entry is added instead. Nothing here can be deleted.
 */
class RegisterController extends Controller
{
    public function __construct()
    {
        if (!Registers::ready()) {
            $this->needsTable('head_logbook');
        }
    }

    // ---------------------------------------------------------------- Head teacher's log book

    public function logbook(): void
    {
        [$from, $to, $period] = $this->range('logbook', 30, 'month');
        $cat = (string) ($_GET['category'] ?? '');
        $q = trim((string) ($_GET['q'] ?? ''));
        $sql = 'SELECT l.*, u.name AS author FROM head_logbook l LEFT JOIN users u ON u.id = l.created_by WHERE l.entry_date BETWEEN ? AND ?';
        $p = [$from, $to];
        if (isset(Registers::LOG_CATEGORIES[$cat])) {
            $sql .= ' AND l.category = ?';
            $p[] = $cat;
        }
        if ($q !== '') {
            $sql .= ' AND (l.title LIKE ? OR l.body LIKE ?)';
            array_push($p, "%$q%", "%$q%");
        }
        $rows = db()->all($sql . ' ORDER BY l.entry_date DESC, l.id DESC LIMIT 500', $p);
        if (!empty($_GET['print'])) {
            $this->view('registers/logbook_print', ['title' => "Head teacher's log book", 'rows' => array_reverse($rows), 'from' => $from, 'to' => $to], 'layouts/print');
            return;
        }
        $edit = isset($_GET['edit']) ? db()->one('SELECT * FROM head_logbook WHERE id = ?', [(int) $_GET['edit']]) : null;
        if ($edit && !$this->canEditLog($edit)) {
            $edit = null;
        }
        $this->view('registers/logbook', ['title' => "Head teacher's log book", 'rows' => $rows, 'from' => $from, 'to' => $to, 'period' => $period, 'cat' => $cat, 'q' => $q, 'edit' => $edit]);
    }

    public function saveLog(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $date = (string) ($_POST['entry_date'] ?? date('Y-m-d'));
        $cat = (string) ($_POST['category'] ?? 'event');
        $title = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 150);
        $body = mb_substr(trim((string) ($_POST['body'] ?? '')), 0, 10000);
        $err = null;
        if (!Validator::isDate($date) || $date > date('Y-m-d')) {
            $err = 'Choose the date it happened (not in the future).';
        } elseif (!isset(Registers::LOG_CATEGORIES[$cat])) {
            $err = 'Choose what kind of entry it is.';
        } elseif ($title === '' || $body === '') {
            $err = 'Write a heading and what happened.';
        }
        if ($err) {
            with_old($_POST);
            flash('error', $err);
            redirect('logbook');
        }
        $data = ['entry_date' => $date, 'category' => $cat, 'title' => $title, 'body' => $body];
        if ($id) {
            $row = db()->one('SELECT * FROM head_logbook WHERE id = ?', [$id]) ?? $this->notFound();
            if (!$this->canEditLog($row)) {
                flash('error', 'Entries can only be corrected on the day they were written, by the person who wrote them. Add a new entry instead.');
                redirect('logbook');
            }
            db()->update('head_logbook', $data, 'id = ?', [$id]);
            audit('logbook_corrected', 'head_logbook', $id, ['before' => mb_substr($row['title'] . ': ' . $row['body'], 0, 500)]);
            flash('success', 'Entry corrected.');
        } else {
            $id = db()->insert('head_logbook', $data + ['created_by' => Auth::id()]);
            audit('logbook_entry', 'head_logbook', $id);
            flash('success', 'Entry added to the log book.');
        }
        redirect('logbook');
    }

    private function canEditLog(array $row): bool
    {
        return (int) $row['created_by'] === (int) Auth::id() && substr((string) $row['created_at'], 0, 10) === date('Y-m-d');
    }

    // ---------------------------------------------------------------- visitors' book

    public function visitors(): void
    {
        [$from, $to, $period] = $this->range('visitors', 0);
        $q = trim((string) ($_GET['q'] ?? ''));
        $sql = 'SELECT v.*, u.name AS recorded_name, p.first_name, p.last_name FROM visitors v LEFT JOIN users u ON u.id = v.recorded_by
                LEFT JOIN pupils p ON p.id = v.pupil_id WHERE DATE(v.time_in) BETWEEN ? AND ?';
        $p = [$from, $to];
        if ($q !== '') {
            $sql .= ' AND (v.visitor_name LIKE ? OR v.phone LIKE ? OR v.organisation LIKE ? OR v.to_see LIKE ?)';
            array_push($p, "%$q%", "%$q%", "%$q%", "%$q%");
        }
        $rows = db()->all($sql . ' ORDER BY v.time_in DESC LIMIT 500', $p);
        if (!empty($_GET['csv'])) {
            $out = csv_start('visitors_' . $from . '_to_' . $to . '.csv', ["Visitors' book", fmt_date($from) . ' – ' . fmt_date($to)]);
            csv_row($out, ['Date', 'Time in', 'Time out', 'Visitor', 'Phone', 'Organisation', 'Purpose', 'Details', 'To see', 'Student', 'Badge', 'Recorded by']);
            foreach ($rows as $r) {
                csv_row($out, array_map('csv_safe', [substr($r['time_in'], 0, 10), substr($r['time_in'], 11, 5), $r['time_out'] ? substr($r['time_out'], 11, 5) : '', $r['visitor_name'], $r['phone'],
                    $r['organisation'], Registers::VISIT_PURPOSES[$r['purpose']] ?? $r['purpose'], $r['details'], $r['to_see'], $r['first_name'] ? $r['first_name'] . ' ' . $r['last_name'] : '', $r['badge_no'], $r['recorded_name']]));
            }
            csv_end($out);
        }
        $inside = (int) db()->value('SELECT COUNT(*) FROM visitors WHERE time_out IS NULL AND DATE(time_in) = CURDATE()');
        $this->view('registers/visitors', ['title' => "Visitors' book", 'rows' => $rows, 'from' => $from, 'to' => $to, 'period' => $period, 'q' => $q, 'inside' => $inside]);
    }

    public function signInVisitor(): void
    {
        $name = mb_substr(trim((string) ($_POST['visitor_name'] ?? '')), 0, 150);
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $purpose = (string) ($_POST['purpose'] ?? 'parent');
        $adm = trim((string) ($_POST['admission_no'] ?? ''));
        $pupil = $adm !== '' ? db()->value('SELECT id FROM pupils WHERE LOWER(admission_no) = ? AND deleted_at IS NULL', [strtolower($adm)]) : null;
        $err = null;
        if ($name === '') {
            $err = "Write the visitor's name.";
        } elseif ($phone !== '' && !SmsGateway::validPhone((string) Admission::normalisePhone($phone))) {
            $err = 'Check the phone number (a Ghana mobile number).';
        } elseif (!isset(Registers::VISIT_PURPOSES[$purpose])) {
            $err = 'Choose why they came.';
        } elseif ($adm !== '' && !$pupil) {
            $err = 'No student has that admission number.';
        }
        if ($err) {
            with_old($_POST);
            flash('error', $err);
            redirect('visitors');
        }
        $id = db()->insert('visitors', [
            'visitor_name' => $name, 'phone' => $phone !== '' ? Admission::normalisePhone($phone) : null,
            'organisation' => mb_substr(trim((string) ($_POST['organisation'] ?? '')), 0, 150) ?: null, 'purpose' => $purpose,
            'details' => mb_substr(trim((string) ($_POST['details'] ?? '')), 0, 255) ?: null, 'to_see' => mb_substr(trim((string) ($_POST['to_see'] ?? '')), 0, 150) ?: null,
            'pupil_id' => $pupil ?: null, 'badge_no' => mb_substr(trim((string) ($_POST['badge_no'] ?? '')), 0, 20) ?: null,
            'time_in' => date('Y-m-d H:i:s'), 'recorded_by' => Auth::id(),
        ]);
        audit('visitor_in', 'visitors', $id);
        flash('success', "$name signed in at " . date('H:i') . '.');
        redirect('visitors');
    }

    public function signOutVisitor(int $id): void
    {
        $v = db()->one('SELECT * FROM visitors WHERE id = ?', [$id]) ?? $this->notFound();
        if (!$v['time_out']) {
            db()->update('visitors', ['time_out' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
            flash('success', $v['visitor_name'] . ' signed out at ' . date('H:i') . '.');
        }
        back('visitors');
    }

    // ---------------------------------------------------------------- staff movement book

    public function movements(): void
    {
        $all = can('movement.view');
        [$from, $to, $period] = $this->range('movements', 0);
        $sql = 'SELECT m.*, u.name, u.role FROM staff_movements m JOIN users u ON u.id = m.user_id WHERE DATE(m.out_at) BETWEEN ? AND ?';
        $p = [$from, $to];
        if (!$all) {
            $sql .= ' AND m.user_id = ?';
            $p[] = Auth::id();
        }
        $rows = db()->all($sql . ' ORDER BY m.out_at DESC LIMIT 500', $p);
        $mine = db()->one('SELECT * FROM staff_movements WHERE user_id = ? AND back_at IS NULL AND DATE(out_at) = CURDATE() ORDER BY id DESC LIMIT 1', [Auth::id()]);
        $this->view('registers/movements', ['title' => 'Staff movement book', 'rows' => $rows, 'from' => $from, 'to' => $to, 'period' => $period, 'all' => $all, 'mine' => $mine,
            'outNow' => $all ? Registers::outNow() : []]);
    }

    public function goOut(): void
    {
        if (db()->value('SELECT 1 FROM staff_movements WHERE user_id = ? AND back_at IS NULL AND DATE(out_at) = CURDATE()', [Auth::id()])) {
            flash('error', 'You are already recorded as out. Mark that you are back first.');
            redirect('movements');
        }
        $dest = mb_substr(trim((string) ($_POST['destination'] ?? '')), 0, 150);
        $purpose = (string) ($_POST['purpose'] ?? 'official');
        $back = trim((string) ($_POST['expected_back'] ?? ''));
        if ($dest === '' || !isset(Registers::MOVE_PURPOSES[$purpose])) {
            with_old($_POST);
            flash('error', 'Say where you are going and why.');
            redirect('movements');
        }
        if ($back !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $back)) {
            flash('error', 'Give the time you expect to be back as HH:MM.');
            redirect('movements');
        }
        $id = db()->insert('staff_movements', [
            'user_id' => Auth::id(), 'purpose' => $purpose, 'destination' => $dest,
            'details' => mb_substr(trim((string) ($_POST['details'] ?? '')), 0, 255) ?: null,
            'out_at' => date('Y-m-d H:i:s'), 'expected_back' => $back !== '' ? date('Y-m-d') . ' ' . $back . ':00' : null,
        ]);
        audit('staff_out', 'staff_movements', $id);
        flash('success', 'Recorded: you left at ' . date('H:i') . '. Press "I am back" when you return.');
        redirect('movements');
    }

    public function comeBack(int $id): void
    {
        $m = db()->one('SELECT * FROM staff_movements WHERE id = ?', [$id]) ?? $this->notFound();
        if ((int) $m['user_id'] !== (int) Auth::id() && !can('movement.view')) {
            $this->forbidden();
        }
        if (!$m['back_at']) {
            db()->update('staff_movements', ['back_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
            flash('success', 'Back at ' . date('H:i') . '.');
        }
        back('movements');
    }

    // ---------------------------------------------------------------- helpers

    /**
     * Dates to show: ?period=today|week|month|term|all (the tabs), or the older ?from&to (kept working: period "custom").
     * With neither, $default is used. Returns [$from, $to, $period].
     */
    private function range(string $page, int $days = 30, string $default = 'today'): array
    {
        $period = (string) ($_GET['period'] ?? '');
        $gf = (string) ($_GET['from'] ?? '');
        $gt = (string) ($_GET['to'] ?? '');
        if ($period === '' && (Validator::isDate($gf) || Validator::isDate($gt))) {
            $to = Validator::isDate($gt) ? $gt : date('Y-m-d');
            $from = Validator::isDate($gf) ? $gf : date('Y-m-d', strtotime("-$days days"));
            if ($from > $to) {
                [$from, $to] = [$to, $from];
            }
            return [$from, $to, 'custom'];
        }
        return self::periodDates($period, $default);
    }

    /** [$from, $to, $period] for a period tab (today, week, month, term, all). */
    public static function periodDates(string $period, string $default = 'today'): array
    {
        $period = in_array($period, ['today', 'week', 'month', 'term', 'all'], true) ? $period : $default;
        $today = date('Y-m-d');
        $term = $period === 'term' ? current_term() : null;
        return match ($period) {
            'week' => [date('Y-m-d', strtotime('monday this week')), $today, $period],
            'month' => [date('Y-m-01'), $today, $period],
            'term' => [$term['start_date'] ?? date('Y-m-d', strtotime('-90 days')), $today, $period],
            'all' => ['2000-01-01', $today, $period],
            default => [$today, $today, 'today'],
        };
    }
}
