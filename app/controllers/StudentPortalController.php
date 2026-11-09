<?php
declare(strict_types=1);

/** Student self-service, limited to the signed-in student's own record. */
class StudentPortalController extends Controller
{
    public function index(): void
    {
        if (Auth::role() !== 'pupil') {
            $this->notFound();
        }
        $student = Accounts::pupilOf((int) Auth::id()) ?? $this->notFound();
        $term = current_term();
        $progress = Progress::pupil((int) $student['id'], true);
        $classSummary = null;
        $ownSummary = null;
        $remarks = [];
        if ($term && $student['class_id'] && Results::released((int) $student['class_id'], (int) $term['id'])) {
            $classResults = Results::classResults((int) $student['class_id'], (int) $term['id']);
            $classSummary = array_values(array_filter(
                array_column($classResults['summary'], 'average'),
                static fn($average) => $average !== null
            ));
            $ownSummary = $classResults['summary'][(int) $student['id']] ?? null;
        }
        $remarks = db()->all(
            'SELECT t.name AS term_name, y.name AS year_name, rm.conduct, rm.teacher_remark, rm.head_remark, rm.personality
             FROM report_remarks rm JOIN terms t ON t.id = rm.term_id JOIN academic_years y ON y.id = t.academic_year_id
             JOIN enrolments e ON e.pupil_id = rm.pupil_id AND e.academic_year_id = t.academic_year_id
             JOIN report_releases rr ON rr.term_id = rm.term_id AND rr.class_id = e.class_id
             WHERE rm.pupil_id = ? ORDER BY t.start_date DESC LIMIT 10',
            [$student['id']]
        );

        $attendance = $term ? db()->one(
            "SELECT SUM(status IN ('present','late')) AS present, COUNT(*) AS marked
             FROM attendance_records WHERE pupil_id = ? AND date BETWEEN ? AND ?",
            [$student['id'], $term['start_date'], $term['end_date']]
        ) : null;
        $absences = $term ? db()->all(
            "SELECT date, status, note FROM attendance_records
             WHERE pupil_id = ? AND date BETWEEN ? AND ? AND status <> 'present' ORDER BY date DESC",
            [$student['id'], $term['start_date'], $term['end_date']]
        ) : [];

        $periods = [];
        $timetable = [];
        $teachers = [];
        if ($student['class_id']) {
            $periods = Timetable::periods((string) $student['category']);
            foreach (db()->all(
                'SELECT ts.day, ts.period_id, s.name AS subject_name, u.name AS teacher_name
                 FROM timetable_slots ts LEFT JOIN class_subjects cs ON cs.id = ts.class_subject_id
                 LEFT JOIN subjects s ON s.id = cs.subject_id LEFT JOIN users u ON u.id = ts.teacher_id
                 WHERE ts.class_id = ?',
                [$student['class_id']]
            ) as $slot) {
                $timetable[$slot['day']][$slot['period_id']] = $slot;
            }
            $teachers = db()->all(
                'SELECT u.id, u.name, MAX(u.id = ?) AS is_class_teacher,
                        GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ", ") AS subjects
                 FROM users u LEFT JOIN class_subjects cs ON cs.teacher_id = u.id AND cs.class_id = ?
                 LEFT JOIN subjects s ON s.id = cs.subject_id
                 WHERE u.is_active = 1 AND (u.id = ? OR cs.class_id = ?)
                 GROUP BY u.id, u.name ORDER BY u.name',
                [(int) ($student['class_teacher_id'] ?? 0), $student['class_id'], (int) ($student['class_teacher_id'] ?? 0), $student['class_id']]
            );
        }

        $transport = module_on('transport') ? db()->one(
            'SELECT a.stop, a.trips, r.name AS route_name, r.stops, r.vehicle, r.driver_name, r.driver_phone, r.attendant_name, r.attendant_phone
             FROM transport_assignments a JOIN transport_routes r ON r.id = a.route_id
             WHERE a.pupil_id = ? AND a.academic_year_id = ? AND a.ended_on IS NULL ORDER BY a.started_on DESC LIMIT 1',
            [$student['id'], current_year()['id'] ?? 0]
        ) : null;
        $events = db()->all(
            'SELECT * FROM calendar_events WHERE end_date >= CURDATE() AND ' . CalendarEvent::visibilitySql()
            . ' ORDER BY start_date, start_time LIMIT 6'
        );
        $user = auth();

        $this->view('student/index', [
            'title' => 'My learning', 'student' => $student, 'term' => $term, 'progress' => $progress,
            'passMark' => $student['category'] ? (float) Results::scheme((string) $student['category'])['pass_mark'] : null,
            'attendance' => $attendance, 'absences' => $absences, 'classSummary' => $classSummary,
            'ownSummary' => $ownSummary, 'remarks' => $remarks, 'periods' => $periods, 'timetable' => $timetable,
            'teachers' => $teachers, 'transport' => $transport, 'events' => $events, 'user' => $user,
        ]);
    }

    public function updateProfile(): void
    {
        if (Auth::role() !== 'pupil' || !Accounts::pupilOf((int) Auth::id())) {
            $this->notFound();
        }
        $in = $this->only(['email', 'phone']);
        $in['email'] = strtolower(trim((string) ($in['email'] ?? '')));
        $in['phone'] = trim((string) ($in['phone'] ?? '')) ?: null;
        $errors = Validator::check($in, [
            'email' => 'required|email|max:190|unique:users,email,' . Auth::id(),
            'phone' => 'phone|max:20',
        ]);
        if ($errors) {
            flash('error', reset($errors));
            redirect('student');
        }
        db()->update('users', $in, 'id = ?', [Auth::id()]);
        audit('student_profile_updated', 'users', (int) Auth::id());
        flash('success', 'Your contact details were updated.');
        redirect('student');
    }
}
