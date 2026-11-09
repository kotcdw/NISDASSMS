<?php
declare(strict_types=1);

class AttendanceController extends Controller
{
    /** Overview for a day: which registers are taken, absences, staff sign-in. */
    public function index(): void
    {
        $date = $this->dateParam();
        $classes = Attendance::classesForUser();
        $stats = [];
        foreach (db()->all(
            "SELECT r.class_id, r.taken_at, u.name AS taken_by_name,
                    SUM(a.status IN ('present','late')) AS present, SUM(a.status = 'absent') AS absent,
                    SUM(a.status = 'late') AS late, SUM(a.status IN ('excused','sick')) AS excused, COUNT(a.id) AS total
             FROM attendance_registers r LEFT JOIN attendance_records a ON a.register_id = r.id LEFT JOIN users u ON u.id = r.taken_by
             WHERE r.date = ? GROUP BY r.id, r.class_id, r.taken_at, u.name",
            [$date]
        ) as $row) {
            $stats[$row['class_id']] = $row;
        }
        $yid = current_year()['id'] ?? 0;
        $rosterCounts = array_column(db()->all("SELECT class_id, COUNT(*) n FROM enrolments WHERE academic_year_id = ? AND status = 'active' GROUP BY class_id", [$yid]), 'n', 'class_id');

        $this->view('attendance/index', [
            'title'   => 'Attendance', 'date' => $date, 'classes' => $classes, 'stats' => $stats,
            'rosterCounts' => $rosterCounts, 'blocked' => Attendance::blockedReason($date),
            'myStaff' => db()->one('SELECT * FROM staff_attendance WHERE user_id = ? AND date = CURDATE()', [Auth::id()]),
        ]);
    }

    public function register(int $classId): void
    {
        $class = $this->classOr404($classId);
        $date = $this->dateParam();
        $existing = [];
        $register = db()->one('SELECT r.*, u.name AS taken_by_name FROM attendance_registers r LEFT JOIN users u ON u.id = r.taken_by WHERE r.class_id = ? AND r.date = ?', [$classId, $date]);
        if ($register) {
            foreach (db()->all('SELECT * FROM attendance_records WHERE register_id = ?', [$register['id']]) as $rec) {
                $existing[$rec['pupil_id']] = $rec;
            }
        }
        $roster = Attendance::roster($classId, $date);
        $this->view('attendance/register', [
            'title' => 'Register — ' . $class['name'], 'class' => $class, 'date' => $date,
            'onLeave' => LeaveController::onLeave($date, array_column($roster, 'id')),
            'pupils' => $roster, 'existing' => $existing, 'register' => $register,
            'blocked' => Attendance::blockedReason($date),
        ]);
    }

    public function saveRegister(int $classId): void
    {
        $class = $this->classOr404($classId);
        $date = (string) ($_POST['date'] ?? '');
        if (!Validator::isDate($date)) {
            flash('error', 'Invalid date.');
            redirect('attendance');
        }
        if ($why = Attendance::blockedReason($date)) {
            flash('error', $why);
            redirect('attendance/register/' . $classId, ['date' => $date]);
        }
        $statuses = (array) ($_POST['status'] ?? []);
        $notes = (array) ($_POST['note'] ?? []);
        $roster = Attendance::roster($classId, $date);
        if (!$roster) {
            flash('error', 'There are no students in this class.');
            redirect('attendance');
        }
        $termId = db()->value('SELECT id FROM terms WHERE ? BETWEEN start_date AND end_date LIMIT 1', [$date]);

        $changed = db()->transaction(function (Database $d) use ($classId, $date, $statuses, $notes, $roster, $termId) {
            $regId = (int) $d->value('SELECT id FROM attendance_registers WHERE class_id = ? AND date = ?', [$classId, $date]);
            if ($regId) {
                $d->update('attendance_registers', ['taken_by' => Auth::id()], 'id = ?', [$regId]);
            } else {
                $regId = $d->insert('attendance_registers', ['class_id' => $classId, 'term_id' => $termId, 'date' => $date, 'taken_by' => Auth::id()]);
            }
            $before = array_column($d->all('SELECT pupil_id, status FROM attendance_records WHERE register_id = ?', [$regId]), 'status', 'pupil_id');
            $changed = [];
            foreach ($roster as $p) {
                $pid = (int) $p['id'];
                $st = (string) ($statuses[$pid] ?? 'present');
                if (!isset(Attendance::STATUSES[$st])) {
                    $st = 'present';
                }
                $note = mb_substr(trim((string) ($notes[$pid] ?? '')), 0, 160) ?: null;
                // A pupil can only be in one register per day (moved classes): replace any other record
                $d->run(
                    'INSERT INTO attendance_records (register_id, pupil_id, date, status, note) VALUES (?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE register_id = VALUES(register_id), status = VALUES(status), note = VALUES(note)',
                    [$regId, $pid, $date, $st, $note]
                );
                if (($before[$pid] ?? 'present') !== $st || !isset($before[$pid])) {
                    $changed[$pid] = $st;
                }
            }
            return $changed;
        });

        // SMS parents about absence/lateness — only for today's register
        $queued = 0;
        if ($date === date('Y-m-d')) {
            foreach ($changed as $pid => $st) {
                $tpl = $st === 'absent' && setting('notify_absent', '1') === '1' ? 'attendance_absent'
                    : ($st === 'late' && setting('notify_late', '0') === '1' ? 'attendance_late' : null);
                if (!$tpl) {
                    continue;
                }
                $g = db()->one('SELECT g.name, g.phone, p.first_name, p.last_name FROM pupils p JOIN pupil_guardian pg ON pg.pupil_id = p.id JOIN guardians g ON g.id = pg.guardian_id WHERE p.id = ? ORDER BY pg.is_primary DESC LIMIT 1', [$pid]);
                $recId = (int) db()->value('SELECT id FROM attendance_records WHERE pupil_id = ? AND date = ?', [$pid, $date]);
                if ($g && Notifier::smsTemplate($tpl, $g['phone'], [
                    'guardian' => $g['name'], 'pupil' => $g['first_name'] . ' ' . $g['last_name'],
                    'class' => $class['name'], 'date' => date('D j M', strtotime($date)),
                ], ['name' => $g['name'], 'entity' => 'attendance_records', 'entity_id' => $recId, 'urgent' => true])) {
                    $queued++;
                }
            }
            if ($queued) {
                Notifier::process(50);
            }
        }
        audit('register_saved', 'classes', $classId, ['date' => $date, 'changed' => count($changed)]);
        flash('success', "Register for {$class['name']} saved" . ($queued ? " — $queued parent SMS sent." : '.'));
        redirect('attendance', ['date' => $date]);
    }

    /** Term grid: pupils × school days with marks and percentages. */
    public function classReport(int $classId): void
    {
        $class = $this->classOr404($classId);
        [$term, $terms] = $this->termParam();
        $data = $term ? $this->classData($classId, $term) : null;
        $this->view('attendance/class', ['title' => 'Attendance — ' . $class['name'], 'class' => $class, 'term' => $term, 'terms' => $terms, 'report' => $data]);
    }

    public function exportClass(int $classId): void
    {
        $class = $this->classOr404($classId);
        [$term] = $this->termParam();
        if (!$term) {
            redirect('attendance/class/' . $classId);
        }
        $data = $this->classData($classId, $term);
        $name = preg_replace('/[^A-Za-z0-9_-]+/', '_', $class['name'] . '_' . $term['year_name'] . '_' . $term['name']);
        $out = csv_start('attendance_' . $name . '.csv', array_merge(['Admission no.', 'Name'], array_map(fn($d) => date('d/m', strtotime($d)), $data['days']), ['Present', 'Absent', 'Late', 'Excused/Sick', 'Days marked', '%']));
        foreach ($data['rows'] as $r) {
            $line = [$r['pupil']['admission_no'], $r['pupil']['last_name'] . ', ' . $r['pupil']['first_name']];
            foreach ($data['days'] as $d) {
                $line[] = isset($r['marks'][$d]) ? Attendance::STATUSES[$r['marks'][$d]][1] : '';
            }
            array_push($line, $r['present'], $r['absent'], $r['late'], $r['excused'], $r['marked'], $r['pct'] ?? '');
            csv_row($out, $line);
        }
        audit('attendance_export', 'classes', $classId, ['term' => $term['id']]);
        csv_end($out);
    }

    /** Absent today + pupils below 80% this term. */
    public function absentees(): void
    {
        $date = $this->dateParam();
        [$term, $terms] = $this->termParam();
        $classIds = array_map(fn($c) => (int) $c['id'], Attendance::classesForUser());
        $in = $classIds ? implode(',', $classIds) : '0';
        $today = db()->all(
            "SELECT a.status, a.note, p.id, p.admission_no, p.first_name, p.last_name, c.name AS class_name,
                    (SELECT g.phone FROM pupil_guardian pg JOIN guardians g ON g.id = pg.guardian_id WHERE pg.pupil_id = p.id ORDER BY pg.is_primary DESC LIMIT 1) AS phone
             FROM attendance_records a JOIN attendance_registers r ON r.id = a.register_id JOIN pupils p ON p.id = a.pupil_id JOIN classes c ON c.id = r.class_id
             WHERE a.date = ? AND a.status IN ('absent','late','sick','excused') AND r.class_id IN ($in)
             ORDER BY c.name, p.last_name",
            [$date]
        );
        $low = [];
        if ($term) {
            $low = db()->all(
                "SELECT p.id, p.admission_no, p.first_name, p.last_name, c.name AS class_name,
                        SUM(a.status IN ('present','late')) AS present, SUM(a.status = 'absent') AS absent, COUNT(*) AS marked
                 FROM attendance_records a JOIN attendance_registers r ON r.id = a.register_id JOIN pupils p ON p.id = a.pupil_id JOIN classes c ON c.id = r.class_id
                 WHERE a.date BETWEEN ? AND ? AND r.class_id IN ($in) AND p.status = 'active'
                 GROUP BY p.id, p.admission_no, p.first_name, p.last_name, c.name
                 HAVING COUNT(*) >= 5 AND SUM(a.status IN ('present','late')) / COUNT(*) < 0.8
                 ORDER BY SUM(a.status IN ('present','late')) / COUNT(*), c.name",
                [$term['start_date'], $term['end_date']]
            );
        }
        $this->view('attendance/absentees', ['title' => 'Absentees', 'date' => $date, 'today' => $today, 'low' => $low, 'term' => $term, 'terms' => $terms]);
    }

    // ---- Staff sign-in -------------------------------------------------------------

    public function staffSign(): void
    {
        $uid = Auth::id();
        $row = db()->one('SELECT * FROM staff_attendance WHERE user_id = ? AND date = CURDATE()', [$uid]);
        $now = date('H:i:s');
        if (!$row) {
            db()->insert('staff_attendance', ['user_id' => $uid, 'date' => date('Y-m-d'), 'time_in' => $now, 'ip' => $_SERVER['REMOTE_ADDR'] ?? null]);
            flash(StaffAttendance::isLate($now, Auth::id()) ? 'warning' : 'success', 'Signed in at ' . substr($now, 0, 5) . (StaffAttendance::isLate($now, Auth::id()) ? ' — after ' . StaffAttendance::lateAfter(Auth::id()) . ', so this counts as late.' : '. Have a blessed day!'));
        } elseif (!$row['time_out']) {
            db()->update('staff_attendance', ['time_out' => $now], 'id = ?', [$row['id']]);
            flash('success', 'Signed out at ' . substr($now, 0, 5) . '.');
        } else {
            flash('info', 'You have already signed in and out today.');
        }
        back('dashboard');
    }

    public function staffReport(): void
    {
        $date = (string) ($_GET['date'] ?? date('Y-m-d'));
        if (!Validator::isDate($date)) {
            $date = date('Y-m-d');
        }
        $rows = db()->all(
            "SELECT u.id, u.name, u.role, s.time_in, s.time_out FROM users u
             LEFT JOIN staff_attendance s ON s.user_id = u.id AND s.date = ?
             WHERE u.is_active = 1 AND u.role NOT IN ('parent','pupil','super_admin') ORDER BY s.time_in IS NULL, s.time_in, u.name",
            [$date]
        );
        $this->view('attendance/staff', ['title' => 'Staff attendance', 'date' => $date, 'rows' => $rows, 'lateAfter' => StaffAttendance::lateAfter(),
            'leave' => Registers::leaveMap($date, $date), 'outNow' => $date === date('Y-m-d') ? Registers::outNow() : [],
            'schoolDay' => in_array($date, StaffAttendance::schoolDays($date, $date), true)]);
    }

    /** Staff summary over a week, month, term or chosen dates: present, absent, late, %, usual arrival time, day-by-day grid. */
    public function staffSummary(): void
    {
        [$from, $to, $label] = StaffAttendance::period((string) ($_GET['p'] ?? 'week'), $_GET['from'] ?? null, $_GET['to'] ?? null);
        $this->view('attendance/staff_summary', [
            'title' => 'Staff attendance summary', 'p' => (string) ($_GET['p'] ?? 'week'), 'from' => $from, 'to' => $to, 'label' => $label,
            'data' => StaffAttendance::summary($from, $to), 'lateAfter' => StaffAttendance::lateAfter(),
        ]);
    }

    public function exportStaff(): void
    {
        [$from, $to, $label] = StaffAttendance::period((string) ($_GET['p'] ?? 'week'), $_GET['from'] ?? null, $_GET['to'] ?? null);
        $data = StaffAttendance::summary($from, $to);
        $out = csv_start('staff_attendance_' . $from . '_to_' . $to . '.csv', ['Staff attendance', $label, 'Late after ' . StaffAttendance::lateAfter(), count($data['days']) . ' school days']);
        csv_row($out, array_merge(['Name', 'Role', 'Present', 'Absent', 'Late', '%', 'Usual sign-in', 'No sign-out'], array_map(fn($d) => date('d/m', strtotime($d)), $data['days'])));
        foreach ($data['rows'] as $r) {
            $line = array_merge([$r['name'], Auth::roleLabel($r['role']), $r['present'], $r['absent'], $r['late'], $r['pct'] ?? '', $r['avg_in'] ?? '', $r['no_out']], array_values($r['grid']));
            csv_row($out, $line);
        }
        audit('staff_attendance_export', 'staff_attendance', null, ['from' => $from, 'to' => $to]);
        csv_end($out);
    }

    public function saveStaffSettings(): void
    {
        $t = (string) ($_POST['staff_late_after'] ?? '');
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t)) {
            flash('error', 'Enter the late time as hours and minutes, e.g. 07:30.');
        } else {
            setting_set('staff_late_after', $t);
            audit('staff_late_time_changed', 'settings', null, ['time' => $t]);
            flash('success', "Staff who sign in after $t are now marked late.");
        }
        back('attendance/staff');
    }

    // ---------------------------------------------------------------------------------

    private function classData(int $classId, array $term): array
    {
        $days = Attendance::schoolDays($term['start_date'], $term['end_date']);
        $taken = array_column(db()->all('SELECT date FROM attendance_registers WHERE class_id = ? AND date BETWEEN ? AND ?', [$classId, $term['start_date'], $term['end_date']]), 'date');
        $marks = [];
        foreach (db()->all(
            'SELECT a.pupil_id, a.date, a.status FROM attendance_records a JOIN attendance_registers r ON r.id = a.register_id
             WHERE r.class_id = ? AND a.date BETWEEN ? AND ?',
            [$classId, $term['start_date'], $term['end_date']]
        ) as $m) {
            $marks[$m['pupil_id']][$m['date']] = $m['status'];
        }
        $rows = [];
        foreach (Attendance::roster($classId) as $p) {
            $pm = $marks[$p['id']] ?? [];
            $c = array_count_values($pm);
            $present = ($c['present'] ?? 0) + ($c['late'] ?? 0);
            $rows[] = [
                'pupil' => $p, 'marks' => $pm, 'present' => $present, 'absent' => $c['absent'] ?? 0, 'late' => $c['late'] ?? 0,
                'excused' => ($c['excused'] ?? 0) + ($c['sick'] ?? 0), 'marked' => count($pm), 'pct' => Attendance::percent($present, count($pm)),
            ];
        }
        return ['days' => $days, 'taken' => array_flip($taken), 'rows' => $rows];
    }

    private function classOr404(int $id): array
    {
        $class = db()->one('SELECT c.*, l.name AS level_name, u.name AS teacher_name FROM classes c JOIN levels l ON l.id = c.level_id LEFT JOIN users u ON u.id = c.class_teacher_id WHERE c.id = ?', [$id]) ?? $this->notFound();
        if (!Attendance::canAccessClass($class)) {
            http_response_code(403);
            $this->view('errors/403', ['title' => 'Access denied']);
            exit;
        }
        return $class;
    }

    private function dateParam(): string
    {
        $d = (string) ($_GET['date'] ?? date('Y-m-d'));
        return Validator::isDate($d) ? $d : date('Y-m-d');
    }
}
