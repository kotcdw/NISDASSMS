<?php
declare(strict_types=1);

/** Printable pupil documents: class lists, ID cards, letters (testimonial, transfer, attendance/admission confirmation), monthly register. */
class PupilDocsController extends Controller
{
    public const LETTERS = [
        'confirmation' => 'Letter confirming a student is in the school',
        'transfer'     => 'Transfer letter',
        'testimonial'  => 'Testimonial (leaving)',
    ];

    public function classList(): void
    {
        $classId = (int) ($_GET['class'] ?? 0);
        $class = db()->one('SELECT c.*, u.name AS teacher FROM classes c LEFT JOIN users u ON u.id = c.class_teacher_id WHERE c.id = ?', [$classId]);
        if ($class && !Auth::classInScope($classId)) {
            $this->denied();
        }
        if (!$class) {
            $this->pickClass('class-list'); // opened from the menu: let them choose the class here
            return;
        }
        $this->view('pupils/class_list', ['title' => 'Class list — ' . $class['name'], 'class' => $class, 'pupils' => $this->pupils($classId), 'blank' => (int) ($_GET['cols'] ?? 0)], 'layouts/print');
    }

    public function idCards(): void
    {
        $classId = (int) ($_GET['class'] ?? 0);
        $pid = (int) ($_GET['pupil'] ?? 0);
        if (($classId && !Auth::classInScope($classId)) || ($pid && !Auth::pupilInScope($pid))) {
            $this->denied();
        }
        if (!$pid && !$classId) {
            $this->pickClass('id-cards'); // opened from the menu: let them choose the class here
            return;
        }
        $pupils = $pid ? array_filter($this->pupils(null), fn($p) => (int) $p['id'] === $pid) : $this->pupils($classId);
        if (!$pupils) {
            flash('error', 'There are no active students in that class yet.');
            redirect('pupils/id-cards');
        }
        $this->view('pupils/id_cards', ['title' => 'Student ID cards', 'pupils' => array_values($pupils), 'year' => current_year()], 'layouts/print');
    }

    public function letter(int $id): void
    {
        $kind = (string) ($_GET['kind'] ?? 'confirmation');
        if (!isset(self::LETTERS[$kind])) {
            $kind = 'confirmation';
        }
        if (!Auth::pupilInScope($id)) {
            $this->denied(); // class teachers: only students in their own classes
        }
        $p = db()->one(
            "SELECT p.*, c.name AS class_name, (SELECT MIN(e.enrolled_on) FROM enrolments e WHERE e.pupil_id = p.id) AS first_enrolled
             FROM pupils p LEFT JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ? LEFT JOIN classes c ON c.id = e.class_id
             WHERE p.id = ? AND p.deleted_at IS NULL",
            [current_year()['id'] ?? 0, $id]
        ) ?? $this->notFound();
        $att = null;
        if (($t = current_term())) {
            $att = db()->one("SELECT COUNT(*) AS n, SUM(status IN ('present','late')) AS p FROM attendance_records WHERE pupil_id = ? AND date BETWEEN ? AND ?", [$id, $t['start_date'], $t['end_date']]);
        }
        $conduct = trim((string) ($_GET['conduct'] ?? ''));
        audit('pupil_letter_printed', 'pupils', $id, ['kind' => $kind]);
        $this->view('pupils/letter', ['title' => self::LETTERS[$kind], 'kind' => $kind, 'p' => $p, 'att' => $att, 'conduct' => mb_substr($conduct, 0, 300),
            'balance' => can('fees.view') ? Fees::pupilBalance($id) : null], 'layouts/print');
    }

    /** Monthly attendance register (GES style): pupils × school days with P / A / L marks. */
    public function monthRegister(int $classId): void
    {
        $class = db()->one('SELECT c.*, u.name AS teacher FROM classes c LEFT JOIN users u ON u.id = c.class_teacher_id WHERE c.id = ?', [$classId]) ?? $this->notFound();
        if (!can('attendance.all') && (int) $class['class_teacher_id'] !== Auth::id()) {
            http_response_code(403);
            $this->view('errors/403', ['title' => 'Access denied']);
            return;
        }
        $m = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['m'] ?? '')) ? $_GET['m'] : date('Y-m');
        $first = $m . '-01';
        $last = date('Y-m-t', strtotime($first));
        $days = [];
        for ($t = strtotime($first); $t <= strtotime($last); $t += 86400) {
            if ((int) date('N', $t) <= 5) {
                $days[] = date('Y-m-d', $t);
            }
        }
        $marks = [];
        foreach (db()->all(
            'SELECT a.pupil_id, r.date, a.status FROM attendance_records a JOIN attendance_registers r ON r.id = a.register_id WHERE r.class_id = ? AND r.date BETWEEN ? AND ?',
            [$classId, $first, $last]
        ) as $r) {
            $marks[$r['pupil_id']][$r['date']] = $r['status'];
        }
        $taken = array_column(db()->all('SELECT date FROM attendance_registers WHERE class_id = ? AND date BETWEEN ? AND ?', [$classId, $first, $last]), 'date');
        $this->view('attendance/month', [
            'title' => 'Register ' . $class['name'] . ' ' . date('F Y', strtotime($first)), 'class' => $class, 'm' => $m, 'days' => $days,
            'marks' => $marks, 'taken' => array_flip($taken), 'pupils' => $this->pupils($classId),
        ], 'layouts/print');
    }

    private function pupils(?int $classId): array
    {
        $yid = current_year()['id'] ?? 0;
        return db()->all(
            "SELECT p.*, c.name AS class_name,
                    (SELECT g.name FROM pupil_guardian pg JOIN guardians g ON g.id = pg.guardian_id WHERE pg.pupil_id = p.id ORDER BY pg.is_primary DESC LIMIT 1) AS guardian_name,
                    (SELECT g.phone FROM pupil_guardian pg JOIN guardians g ON g.id = pg.guardian_id WHERE pg.pupil_id = p.id ORDER BY pg.is_primary DESC LIMIT 1) AS guardian_phone
             FROM enrolments e JOIN pupils p ON p.id = e.pupil_id JOIN classes c ON c.id = e.class_id
             WHERE e.academic_year_id = ? AND e.status = 'active' AND p.status = 'active' AND p.deleted_at IS NULL" . ($classId ? ' AND e.class_id = ?' : '') . '
             ORDER BY c.name, p.last_name, p.first_name',
            $classId ? [$yid, $classId] : [$yid]
        );
    }

    /** Class lists / ID cards opened without a class: show the classes this person may print for. */
    private function pickClass(string $kind): void
    {
        $yid = current_year()['id'] ?? 0;
        $classes = array_values(array_filter(db()->all(
            "SELECT c.id, c.name, u.name AS teacher,
                    (SELECT COUNT(*) FROM enrolments e JOIN pupils p ON p.id = e.pupil_id
                     WHERE e.class_id = c.id AND e.academic_year_id = ? AND e.status = 'active' AND p.status = 'active' AND p.deleted_at IS NULL) AS students
             FROM classes c JOIN levels l ON l.id = c.level_id LEFT JOIN users u ON u.id = c.class_teacher_id
             WHERE c.is_active = 1 ORDER BY l.sort_order, c.name",
            [$yid]
        ), fn($c) => Auth::classInScope((int) $c['id'])));
        $this->view('pupils/pick_class', [
            'title' => $kind === 'id-cards' ? 'ID cards' : 'Class lists',
            'kind' => $kind, 'classes' => $classes,
        ]);
    }

    private function denied(): void
    {
        audit('access_denied', 'pupils', null, ['reason' => 'outside own classes', 'route' => current_route()]);
        http_response_code(403);
        $this->view('errors/403', ['title' => 'Access denied']);
        exit;
    }
}
