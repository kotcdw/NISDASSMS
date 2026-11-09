<?php
declare(strict_types=1);

/** Staff timesheets and shifts: hours from sign-in/out, Head corrections and monthly approval (feeds daily-rate pay). */
class TimesheetController extends Controller
{
    public function index(): void
    {
        $period = $this->period();
        $rows = [];
        foreach (StaffAttendance::staff() as $u) {
            $m = Timesheets::month((int) $u['id'], $period);
            $rows[] = $u + ['tot' => $m['tot'], 'shift' => $m['shift'], 'appr' => Timesheets::approval((int) $u['id'], $period), 'daily' => $this->dailyPaid((int) $u['id'])];
        }
        if (isset($_GET['csv'])) {
            $out = csv_start('timesheets-' . $period . '.csv', ['Name', 'Role', 'Shift', 'Days expected', 'Days worked', 'Hours', 'Late', 'Left early', 'No sign-out', 'Absent', 'Approved']);
            foreach ($rows as $r) {
                csv_row($out, [$r['name'], Auth::roleLabel($r['role']), $r['shift']['name'] ?? 'School day', $r['tot']['expected'], $r['tot']['days'], $r['tot']['hours'], $r['tot']['late'], $r['tot']['early'], $r['tot']['no_out'], $r['tot']['absent'], ($r['appr']['status'] ?? '') === 'approved' ? 'Yes' : 'No']);
            }
            csv_end($out);
        }
        $this->view('timesheets/index', ['title' => 'Staff timesheets', 'period' => $period, 'rows' => $rows]);
    }

    public function show(int $id): void
    {
        $u = $this->staffUser($id);
        $period = $this->period();
        $this->view('timesheets/show', ['title' => 'Timesheet — ' . $u['name'], 'u' => $u, 'period' => $period, 'm' => Timesheets::month($id, $period),
            'appr' => Timesheets::approval($id, $period), 'mine' => false, 'daily' => $this->dailyPaid($id)]);
    }

    public function my(): void
    {
        $u = $this->staffUser((int) Auth::id());
        $period = $this->period();
        $this->view('timesheets/show', ['title' => 'My timesheet', 'u' => $u, 'period' => $period, 'm' => Timesheets::month((int) Auth::id(), $period),
            'appr' => Timesheets::approval((int) Auth::id(), $period), 'mine' => true, 'daily' => $this->dailyPaid((int) Auth::id())]);
    }

    public function approve(int $id): void
    {
        $this->staffUser($id);
        $period = (string) ($_POST['period'] ?? '');
        if (!Timesheets::validPeriod($period) || $period > date('Y-m')) {
            flash('error', 'Choose a month that has started.');
            redirect('timesheets');
        }
        if ($id === Auth::id()) {
            flash('error', 'Someone else must approve your own timesheet.');
            redirect('timesheets/' . $id, ['period' => $period]);
        }
        if (($_POST['action'] ?? '') === 'undo') {
            db()->run('DELETE FROM timesheets WHERE user_id = ? AND period = ?', [$id, $period]);
            audit('timesheet_unapproved', 'users', $id, ['period' => $period]);
            flash('success', 'Approval removed. You can correct the days and approve again.');
            redirect('timesheets/' . $id, ['period' => $period]);
        }
        if ($this->payrollPaid($period) && $this->dailyPaid($id)) {
            flash('error', 'The payroll for this month is already paid; this timesheet can no longer change.');
            redirect('timesheets/' . $id, ['period' => $period]);
        }
        $t = Timesheets::month($id, $period)['tot'];
        db()->run(
            "INSERT INTO timesheets (user_id, period, days_worked, hours, late_days, status, note, approved_by, approved_at) VALUES (?, ?, ?, ?, ?, 'approved', ?, ?, NOW())
             ON DUPLICATE KEY UPDATE days_worked = VALUES(days_worked), hours = VALUES(hours), late_days = VALUES(late_days), status = 'approved', note = VALUES(note), approved_by = VALUES(approved_by), approved_at = NOW()",
            [$id, $period, $t['days'], $t['hours'], $t['late'], mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 255) ?: null, Auth::id()]
        );
        audit('timesheet_approved', 'users', $id, ['period' => $period, 'days' => $t['days'], 'hours' => $t['hours']]);
        flash('success', 'Timesheet approved: ' . $t['days'] . ' days, ' . $t['hours'] . ' hours.');
        redirect(!empty($_POST['back']) ? 'timesheets?period=' . $period : 'timesheets/' . $id . '?period=' . $period);
    }

    /** Head fixes a day: forgotten sign-out, wrong time, or a day worked without signing in. */
    public function correct(int $id): void
    {
        $this->staffUser($id);
        $date = (string) ($_POST['date'] ?? '');
        $in = trim((string) ($_POST['time_in'] ?? ''));
        $out = trim((string) ($_POST['time_out'] ?? ''));
        $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 160);
        $period = substr($date, 0, 7);
        $time = fn($t) => $t === '' || preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t);
        $err = null;
        if ($id === Auth::id()) {
            $err = 'You cannot correct your own sign-in times.';
        } elseif (!Validator::isDate($date) || $date > date('Y-m-d')) {
            $err = 'Choose a day that is not in the future.';
        } elseif (!$time($in) || !$time($out) || ($in === '' && $out !== '')) {
            $err = 'Enter times like 07:25 (a sign-out needs a sign-in).';
        } elseif ($note === '') {
            $err = 'Write why the time is corrected (e.g. "forgot to sign out — confirmed by the Head").';
        } elseif (($a = Timesheets::approval($id, $period)) && $a['status'] === 'approved') {
            $err = 'This month is already approved. Remove the approval first.';
        }
        if ($err) {
            flash('error', $err);
            redirect('timesheets/' . $id, ['period' => $period ?: date('Y-m')]);
        }
        $old = db()->one('SELECT * FROM staff_attendance WHERE user_id = ? AND date = ?', [$id, $date]);
        if ($in === '') {
            db()->run('DELETE FROM staff_attendance WHERE user_id = ? AND date = ?', [$id, $date]);
        } elseif ($old) {
            db()->update('staff_attendance', ['time_in' => $in . ':00', 'time_out' => $out !== '' ? $out . ':00' : null, 'note' => $note, 'corrected_by' => Auth::id()], 'id = ?', [$old['id']]);
        } else {
            db()->insert('staff_attendance', ['user_id' => $id, 'date' => $date, 'time_in' => $in . ':00', 'time_out' => $out !== '' ? $out . ':00' : null, 'note' => $note, 'corrected_by' => Auth::id()]);
        }
        audit('staff_attendance_corrected', 'users', $id, ['date' => $date, 'from' => $old ? [$old['time_in'], $old['time_out']] : null, 'to' => [$in, $out], 'note' => $note]);
        flash('success', 'Day corrected.');
        redirect('timesheets/' . $id, ['period' => $period]);
    }

    // ---------------------------------------------------------------- shifts

    public function shifts(): void
    {
        $shifts = db()->all('SELECT s.*, (SELECT COUNT(*) FROM staff_shift_assign a WHERE a.shift_id = s.id) AS n FROM staff_shifts s ORDER BY s.is_active DESC, s.start_time');
        $staff = db()->all("SELECT u.id, u.name, u.role, a.shift_id FROM users u LEFT JOIN staff_shift_assign a ON a.user_id = u.id WHERE u.is_active = 1 AND u.role NOT IN ('parent','pupil','super_admin') ORDER BY u.name");
        $this->view('timesheets/shifts', ['title' => 'Staff shifts', 'shifts' => $shifts, 'staff' => $staff]);
    }

    public function saveShift(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 60);
        $start = (string) ($_POST['start_time'] ?? '');
        $end = (string) ($_POST['end_time'] ?? '');
        $days = array_values(array_intersect(array_map('intval', (array) ($_POST['days'] ?? [])), range(1, 7)));
        $t = '/^([01]\d|2[0-3]):[0-5]\d$/';
        if ($name === '' || !preg_match($t, $start) || !preg_match($t, $end) || $start === $end || !$days
            || db()->value('SELECT id FROM staff_shifts WHERE name = ? AND id <> ?', [$name, $id])) {
            flash('error', 'Give the shift a unique name, a start and end time, and at least one day.');
            redirect('timesheets/shifts');
        }
        sort($days);
        $data = ['name' => $name, 'start_time' => $start . ':00', 'end_time' => $end . ':00', 'days' => implode(',', $days), 'is_active' => $id && empty($_POST['is_active']) ? 0 : 1];
        if ($id) {
            db()->one('SELECT id FROM staff_shifts WHERE id = ?', [$id]) ?? $this->notFound();
            db()->update('staff_shifts', $data, 'id = ?', [$id]);
        } else {
            $id = db()->insert('staff_shifts', $data);
        }
        audit('shift_saved', 'staff_shifts', $id, $data);
        flash('success', 'Shift saved.');
        redirect('timesheets/shifts');
    }

    public function assign(): void
    {
        $n = 0;
        $valid = array_map('intval', array_column(db()->all('SELECT id FROM staff_shifts WHERE is_active = 1'), 'id'));
        $staff = array_map('intval', array_column(db()->all("SELECT id FROM users WHERE is_active = 1 AND role NOT IN ('parent','pupil','super_admin')"), 'id'));
        foreach ((array) ($_POST['shift'] ?? []) as $uid => $sid) {
            $uid = (int) $uid;
            $sid = (int) $sid;
            if (!in_array($uid, $staff, true)) {
                continue;
            }
            if ($sid && in_array($sid, $valid, true)) {
                db()->run('INSERT INTO staff_shift_assign (user_id, shift_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE shift_id = VALUES(shift_id)', [$uid, $sid]);
            } else {
                db()->run('DELETE FROM staff_shift_assign WHERE user_id = ?', [$uid]);
            }
            $n++;
        }
        StaffAttendance::forgetShifts();
        audit('shifts_assigned', 'staff_shifts', null, ['count' => $n]);
        flash('success', 'Shifts saved for ' . $n . ' staff.');
        redirect('timesheets/shifts');
    }

    // ---------------------------------------------------------------- helpers

    private function period(): string
    {
        $p = (string) ($_GET['period'] ?? date('Y-m'));
        return Timesheets::validPeriod($p) && $p <= date('Y-m') ? $p : date('Y-m');
    }

    private function staffUser(int $id): array
    {
        return db()->one("SELECT id, name, role FROM users WHERE id = ? AND role NOT IN ('parent','pupil')", [$id]) ?? $this->notFound();
    }

    private function dailyPaid(int $userId): ?array
    {
        try {
            return db()->one("SELECT id, name, daily_rate FROM staff WHERE user_id = ? AND is_active = 1 AND pay_basis = 'daily'", [$userId]);
        } catch (PDOException $e) {
            return null;
        }
    }

    private function payrollPaid(string $period): bool
    {
        return (bool) db()->value("SELECT 1 FROM payroll_runs WHERE period = ? AND status = 'paid'", [$period]);
    }
}
