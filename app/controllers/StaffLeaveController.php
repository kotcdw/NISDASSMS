<?php
declare(strict_types=1);

/**
 * Staff leave (every staff member applies; the Head approves — never their own request) and staff training (CPD).
 * Days are working days (Mon–Fri, not school-closed days). Annual leave is counted against the yearly allowance
 * (School setting staff_leave_annual_days). Approved leave shows on staff attendance as "On leave", not absent.
 */
class StaffLeaveController extends Controller
{
    public function __construct()
    {
        if (!Registers::ready()) {
            $this->needsTable('staff_leave');
        }
    }

    public function index(): void
    {
        $approver = can('staffleave.approve');
        $status = (string) ($_GET['status'] ?? '');
        $mine = db()->all('SELECT l.*, d.name AS decided_name FROM staff_leave l LEFT JOIN users d ON d.id = l.decided_by WHERE l.user_id = ? ORDER BY l.start_date DESC LIMIT 100', [Auth::id()]);
        $all = [];
        if ($approver) {
            $sql = 'SELECT l.*, u.name, u.role, d.name AS decided_name FROM staff_leave l JOIN users u ON u.id = l.user_id LEFT JOIN users d ON d.id = l.decided_by';
            $p = [];
            if (isset(Registers::LEAVE_STATUS[$status])) {
                $sql .= ' WHERE l.status = ?';
                $p[] = $status;
            }
            $all = db()->all($sql . " ORDER BY l.status = 'pending' DESC, l.start_date DESC LIMIT 300", $p);
        }
        $this->view('staffleave/index', ['title' => 'Staff leave', 'mine' => $mine, 'all' => $all, 'approver' => $approver, 'status' => $status,
            'balance' => Registers::annualBalance((int) Auth::id()), 'today' => Registers::leaveMap(date('Y-m-d'), date('Y-m-d'))]);
    }

    public function store(): void
    {
        $type = (string) ($_POST['leave_type'] ?? '');
        $from = (string) ($_POST['start_date'] ?? '');
        $to = (string) ($_POST['end_date'] ?? '');
        $err = null;
        $days = 0;
        if (!isset(Registers::LEAVE_TYPES[$type])) {
            $err = 'Choose the kind of leave.';
        } elseif (!Validator::isDate($from) || !Validator::isDate($to) || $to < $from) {
            $err = 'Choose the first and last day of leave.';
        } elseif ($from < date('Y-m-d', strtotime('-30 days'))) {
            $err = 'Leave that started more than 30 days ago cannot be requested here — ask the Head to record it.';
        } elseif (($days = Registers::leaveDays($from, $to)) < 1) {
            $err = 'Those dates have no working days (weekends and school-closed days are not counted).';
        } elseif (db()->value("SELECT 1 FROM staff_leave WHERE user_id = ? AND status IN ('pending','approved') AND start_date <= ? AND end_date >= ?", [Auth::id(), $to, $from])) {
            $err = 'You already have leave asked for or approved on some of those days.';
        } elseif ($type === 'annual') {
            $b = Registers::annualBalance((int) Auth::id(), (int) substr($from, 0, 4));
            if ($days > $b['left'] - $b['waiting']) {
                $err = "That is $days working days, but you have " . max(0, $b['left'] - $b['waiting']) . ' annual leave days left this year (allowance ' . $b['allowance'] . ').';
            }
        }
        if ($err) {
            with_old($_POST);
            flash('error', $err);
            redirect('staff-leave');
        }
        [$file, $upErr] = Upload::store($_FILES['attachment'] ?? ['error' => UPLOAD_ERR_NO_FILE], 'staffleave');
        if ($upErr) {
            with_old($_POST);
            flash('error', 'Attachment: ' . $upErr);
            redirect('staff-leave');
        }
        $ref = Registers::nextRef('LV');
        $id = db()->insert('staff_leave', [
            'ref_no' => $ref, 'user_id' => Auth::id(), 'leave_type' => $type, 'start_date' => $from, 'end_date' => $to, 'days' => $days,
            'reason' => mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 500) ?: null, 'relief' => mb_substr(trim((string) ($_POST['relief'] ?? '')), 0, 150) ?: null,
        ] + ($file ? ['attachment' => $file['stored_name'], 'attachment_name' => $file['original_name'], 'attachment_mime' => $file['mime']] : []));
        audit('staff_leave_requested', 'staff_leave', $id, ['days' => $days, 'type' => $type]);
        $who = (string) (auth()['name'] ?? '');
        foreach (Auth::rolesWith('staffleave.approve') as $role) {
            $ids = array_column(db()->all('SELECT id FROM users WHERE is_active = 1 AND role = ? AND id <> ?', [$role, Auth::id()]), 'id');
            Notifier::inapp($ids, 'Leave request: ' . $who, Registers::LEAVE_TYPES[$type] . ', ' . fmt_date($from) . ' – ' . fmt_date($to) . " ($days days)", 'staff-leave?status=pending');
        }
        flash('success', "Leave request $ref sent ($days working days). You will be told when the Head decides.");
        redirect('staff-leave');
    }

    public function decide(int $id): void
    {
        $l = db()->one('SELECT l.*, u.name FROM staff_leave l JOIN users u ON u.id = l.user_id WHERE l.id = ?', [$id]) ?? $this->notFound();
        $ok = ($_POST['decision'] ?? '') === 'approve';
        if ((int) $l['user_id'] === (int) Auth::id()) {
            flash('error', 'You cannot decide your own leave request.');
            redirect('staff-leave');
        }
        if ($l['status'] !== 'pending') {
            flash('error', 'This request was already decided.');
            redirect('staff-leave');
        }
        $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 255);
        if (!$ok && $note === '') {
            flash('error', 'Say why the leave is declined.');
            redirect('staff-leave?status=pending');
        }
        db()->update('staff_leave', ['status' => $ok ? 'approved' : 'declined', 'decided_by' => Auth::id(), 'decided_at' => date('Y-m-d H:i:s'), 'decision_note' => $note ?: null], "id = ? AND status = 'pending'", [$id]);
        audit($ok ? 'staff_leave_approved' : 'staff_leave_declined', 'staff_leave', $id);
        Notifier::inapp([(int) $l['user_id']], 'Leave ' . ($ok ? 'approved' : 'declined') . ': ' . $l['ref_no'], ($note !== '' ? $note . ' · ' : '') . fmt_date($l['start_date']) . ' – ' . fmt_date($l['end_date']), 'staff-leave');
        flash('success', 'Leave for ' . $l['name'] . ($ok ? ' approved.' : ' declined.'));
        redirect('staff-leave?status=pending');
    }

    public function cancel(int $id): void
    {
        $l = db()->one('SELECT * FROM staff_leave WHERE id = ?', [$id]) ?? $this->notFound();
        $own = (int) $l['user_id'] === (int) Auth::id();
        // Staff cancel their own request (before it starts); the Head can cancel approved leave (e.g. called back to work)
        if (!($own && ($l['status'] === 'pending' || ($l['status'] === 'approved' && $l['start_date'] > date('Y-m-d')))) && !(can('staffleave.approve') && !$own && $l['status'] === 'approved')) {
            flash('error', 'This leave can no longer be cancelled here.');
            redirect('staff-leave');
        }
        db()->update('staff_leave', ['status' => 'cancelled'], 'id = ?', [$id]);
        audit('staff_leave_cancelled', 'staff_leave', $id);
        if (!$own) {
            Notifier::inapp([(int) $l['user_id']], 'Leave cancelled: ' . $l['ref_no'], 'The Head cancelled this leave.', 'staff-leave');
        }
        flash('success', 'Leave ' . $l['ref_no'] . ' cancelled.');
        redirect('staff-leave');
    }

    public function file(int $id): void
    {
        $l = db()->one('SELECT * FROM staff_leave WHERE id = ?', [$id]) ?? $this->notFound();
        if (!$l['attachment'] || ((int) $l['user_id'] !== (int) Auth::id() && !can('staffleave.approve'))) {
            $this->notFound();
        }
        Upload::send($l['attachment'], $l['attachment_mime'], $l['attachment_name']);
    }

    // ---------------------------------------------------------------- training (CPD)

    public function cpd(): void
    {
        $all = can('cpd.view');
        $uid = $all && isset($_GET['user']) ? (int) $_GET['user'] : 0;
        $sql = 'SELECT c.*, u.name FROM staff_cpd c JOIN users u ON u.id = c.user_id';
        $p = [];
        if (!$all) {
            $sql .= ' WHERE c.user_id = ?';
            $p[] = Auth::id();
        } elseif ($uid) {
            $sql .= ' WHERE c.user_id = ?';
            $p[] = $uid;
        }
        // Period tab (default All, as before); older ?from&to links still work.
        $gf = (string) ($_GET['from'] ?? '');
        $gt = (string) ($_GET['to'] ?? '');
        if (!isset($_GET['period']) && (Validator::isDate($gf) || Validator::isDate($gt))) {
            $to = Validator::isDate($gt) ? $gt : date('Y-m-d');
            $from = Validator::isDate($gf) ? $gf : '2000-01-01';
            if ($from > $to) {
                [$from, $to] = [$to, $from];
            }
            $period = 'custom';
        } else {
            [$from, $to, $period] = RegisterController::periodDates((string) ($_GET['period'] ?? ''), 'all');
            if ($period === 'term') {
                $to = max($to, (string) (current_term()['end_date'] ?? $to)); // training booked later this term
            }
        }
        if ($period !== 'all') {
            // training that overlaps the period
            $sql .= ($p ? ' AND' : ' WHERE') . ' c.start_date <= ? AND COALESCE(c.end_date, c.start_date) >= ?';
            array_push($p, $to, $from);
        }
        $rows = db()->all($sql . ' ORDER BY c.start_date DESC LIMIT 500', $p);
        $totals = $all ? db()->all("SELECT u.id, u.name, COUNT(c.id) AS n, COALESCE(SUM(c.hours), 0) AS hours, COALESCE(SUM(c.points), 0) AS points
                                    FROM users u LEFT JOIN staff_cpd c ON c.user_id = u.id AND YEAR(c.start_date) = YEAR(CURDATE())
                                    WHERE u.is_active = 1 AND u.role NOT IN ('parent','pupil','super_admin') GROUP BY u.id ORDER BY u.name") : [];
        $this->view('staffleave/cpd', ['title' => 'Staff training (CPD)', 'rows' => $rows, 'all' => $all, 'uid' => $uid, 'totals' => $totals, 'from' => $from, 'to' => $to, 'period' => $period,
            'staff' => $all ? db()->all("SELECT id, name FROM users WHERE is_active = 1 AND role NOT IN ('parent','pupil') ORDER BY name") : []]);
    }

    public function storeCpd(): void
    {
        $uid = can('cpd.view') && (int) ($_POST['user_id'] ?? 0) ? (int) $_POST['user_id'] : (int) Auth::id();
        $title = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 150);
        $kind = (string) ($_POST['kind'] ?? 'workshop');
        $from = (string) ($_POST['start_date'] ?? '');
        $to = trim((string) ($_POST['end_date'] ?? ''));
        $hours = trim((string) ($_POST['hours'] ?? ''));
        $points = trim((string) ($_POST['points'] ?? ''));
        $err = null;
        if ($title === '' || !isset(Registers::CPD_KINDS[$kind])) {
            $err = 'Write the name of the training and choose its kind.';
        } elseif (!Validator::isDate($from) || ($to !== '' && (!Validator::isDate($to) || $to < $from))) {
            $err = 'Check the dates.';
        } elseif (($hours !== '' && (!is_numeric($hours) || $hours < 0 || $hours > 999)) || ($points !== '' && (!is_numeric($points) || $points < 0 || $points > 999))) {
            $err = 'Hours and points must be numbers.';
        } elseif (!db()->value("SELECT 1 FROM users WHERE id = ? AND role NOT IN ('parent','pupil')", [$uid])) {
            $err = 'Choose a staff member.';
        }
        if ($err) {
            with_old($_POST);
            flash('error', $err);
            redirect('cpd');
        }
        [$file, $upErr] = Upload::store($_FILES['certificate'] ?? ['error' => UPLOAD_ERR_NO_FILE], 'cpd');
        if ($upErr) {
            with_old($_POST);
            flash('error', 'Certificate: ' . $upErr);
            redirect('cpd');
        }
        $id = db()->insert('staff_cpd', [
            'user_id' => $uid, 'title' => $title, 'provider' => mb_substr(trim((string) ($_POST['provider'] ?? '')), 0, 150) ?: null, 'kind' => $kind,
            'start_date' => $from, 'end_date' => $to ?: null, 'hours' => $hours !== '' ? $hours : null, 'points' => $points !== '' ? $points : null, 'recorded_by' => Auth::id(),
        ] + ($file ? ['certificate' => $file['stored_name'], 'certificate_name' => $file['original_name'], 'certificate_mime' => $file['mime']] : []));
        audit('cpd_recorded', 'staff_cpd', $id);
        flash('success', 'Training recorded.');
        redirect('cpd');
    }

    public function deleteCpd(int $id): void
    {
        $c = db()->one('SELECT * FROM staff_cpd WHERE id = ?', [$id]) ?? $this->notFound();
        if ((int) $c['user_id'] !== (int) Auth::id() && !can('cpd.view')) {
            $this->forbidden();
        }
        db()->run('DELETE FROM staff_cpd WHERE id = ?', [$id]);
        audit('cpd_deleted', 'staff_cpd', $id, ['title' => $c['title']]);
        flash('success', 'Training record removed.');
        redirect('cpd');
    }

    public function cpdFile(int $id): void
    {
        $c = db()->one('SELECT * FROM staff_cpd WHERE id = ?', [$id]) ?? $this->notFound();
        if (!$c['certificate'] || ((int) $c['user_id'] !== (int) Auth::id() && !can('cpd.view'))) {
            $this->notFound();
        }
        Upload::send($c['certificate'], $c['certificate_mime'], $c['certificate_name']);
    }
}
