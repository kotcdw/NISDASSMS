<?php
declare(strict_types=1);

/** Seating plans: one per class (class teacher or all-classes roles) and exam-room plans mixing classes (exam timetable managers). */
class SeatingController extends Controller
{
    public function index(): void
    {
        [$w, $p] = Auth::scopeSql('c.id');
        $classes = db()->all(
            "SELECT c.id, c.name, c.class_teacher_id, u.name AS teacher_name, sp.id AS plan_id, sp.seat_rows, sp.seat_cols, sp.updated_at, sp.created_at,
                    (SELECT COUNT(*) FROM seating_seats ss WHERE ss.plan_id = sp.id) AS seated
             FROM classes c JOIN levels l ON l.id = c.level_id LEFT JOIN users u ON u.id = c.class_teacher_id
             LEFT JOIN seating_plans sp ON sp.kind = 'class' AND sp.class_id = c.id
             WHERE c.is_active = 1 AND $w ORDER BY l.sort_order, c.name",
            $p
        );
        $classes = array_values(array_filter($classes, fn($c) => $this->canClass($c)));
        $rolls = Resources::rolls();
        $this->view('seating/index', [
            'title' => 'Seating plans', 'classes' => $classes, 'rolls' => $rolls,
            'exams' => can('examtt.manage') ? db()->all(
                "SELECT sp.*, r.name AS room_name, es.name AS series_name, (SELECT COUNT(*) FROM seating_seats ss WHERE ss.plan_id = sp.id) AS seated
                 FROM seating_plans sp LEFT JOIN rooms r ON r.id = sp.room_id LEFT JOIN exam_series es ON es.id = sp.series_id
                 WHERE sp.kind = 'exam' ORDER BY sp.created_at DESC LIMIT 50"
            ) : [],
        ]);
    }

    /** Open the class plan, or the set-up form if there is none yet. */
    public function classPlan(int $id): void
    {
        $class = db()->one('SELECT * FROM classes WHERE id = ? AND is_active = 1', [$id]) ?? $this->notFound();
        if (!$this->canClass($class)) {
            $this->forbidden();
        }
        if ($pid = db()->value("SELECT id FROM seating_plans WHERE kind = 'class' AND class_id = ?", [$id])) {
            redirect('seating/' . $pid);
        }
        $n = count(Seating::pupils([$id]));
        [$rows, $cols] = Seating::suggest($n);
        $this->view('seating/setup', ['title' => 'Seating — ' . $class['name'], 'class' => $class, 'n' => $n, 'rows' => $rows, 'cols' => $cols]);
    }

    public function createClassPlan(int $id): void
    {
        $class = db()->one('SELECT * FROM classes WHERE id = ? AND is_active = 1', [$id]) ?? $this->notFound();
        if (!$this->canClass($class)) {
            $this->forbidden();
        }
        [$rows, $cols, $method] = $this->gridInput();
        $pid = (int) db()->value("SELECT id FROM seating_plans WHERE kind = 'class' AND class_id = ?", [$id]);
        if (!$pid) {
            $pid = db()->insert('seating_plans', ['kind' => 'class', 'class_id' => $id, 'title' => $class['name'] . ' seating', 'seat_rows' => $rows, 'seat_cols' => $cols, 'method' => $method, 'updated_by' => Auth::id()]);
        }
        $this->rearrangePlan($pid, $rows, $cols, $method);
        redirect('seating/' . $pid);
    }

    public function show(int $id): void
    {
        $plan = $this->planOrDeny($id);
        $pupils = Seating::pupils(Seating::classIds($plan));
        $seats = array_filter(Seating::seats($id), fn($pid) => isset($pupils[$pid]));
        $this->view('seating/show', [
            'title' => $plan['title'], 'plan' => $plan, 'pupils' => $pupils, 'seats' => $seats,
            'unseated' => array_diff_key($pupils, array_flip($seats)),
            'classNames' => array_values(array_unique(array_column($pupils, 'class_name'))),
        ]);
    }

    public function printPlan(int $id): void
    {
        $plan = $this->planOrDeny($id);
        $pupils = Seating::pupils(Seating::classIds($plan));
        $seats = array_filter(Seating::seats($id), fn($pid) => isset($pupils[$pid]));
        $this->view('seating/print', ['title' => $plan['title'], 'plan' => $plan, 'pupils' => $pupils, 'seats' => $seats, 'unseated' => array_diff_key($pupils, array_flip($seats))], 'layouts/print');
    }

    /** Save the layout after dragging pupils around (seat[r][c] = pupil id). */
    public function save(int $id): void
    {
        $plan = $this->planOrDeny($id);
        $pupils = Seating::pupils(Seating::classIds($plan));
        $map = [];
        $used = [];
        foreach ((array) ($_POST['seat'] ?? []) as $r => $cols) {
            foreach ((array) $cols as $c => $pid) {
                $r = (int) $r;
                $c = (int) $c;
                $pid = (int) $pid;
                if ($pid && isset($pupils[$pid]) && !isset($used[$pid]) && $r >= 1 && $r <= (int) $plan['seat_rows'] && $c >= 1 && $c <= (int) $plan['seat_cols']) {
                    $map["$r-$c"] = $pid;
                    $used[$pid] = true;
                }
            }
        }
        Seating::save($id, $map);
        db()->update('seating_plans', ['updated_by' => Auth::id()], 'id = ?', [$id]);
        audit('seating_saved', 'seating_plans', $id, ['seated' => count($map)]);
        flash('success', 'Seating plan saved.');
        redirect('seating/' . $id);
    }

    /** Swap two seats (works without JavaScript). */
    public function swap(int $id): void
    {
        $plan = $this->planOrDeny($id);
        $seats = Seating::seats($id);
        $a = (string) ($_POST['a'] ?? '');
        $b = (string) ($_POST['b'] ?? '');
        $ok = fn($k) => preg_match('/^(\d+)-(\d+)$/', $k, $m) && (int) $m[1] >= 1 && (int) $m[1] <= (int) $plan['seat_rows'] && (int) $m[2] >= 1 && (int) $m[2] <= (int) $plan['seat_cols'];
        if ($ok($a) && $ok($b) && $a !== $b) {
            $pa = $seats[$a] ?? null;
            $pb = $seats[$b] ?? null;
            unset($seats[$a], $seats[$b]);
            if ($pa) {
                $seats[$b] = $pa;
            }
            if ($pb) {
                $seats[$a] = $pb;
            }
            Seating::save($id, $seats);
            flash('success', 'Seats swapped.');
        }
        redirect('seating/' . $id);
    }

    public function rearrange(int $id): void
    {
        $this->planOrDeny($id);
        [$rows, $cols, $method] = $this->gridInput();
        $this->rearrangePlan($id, $rows, $cols, $method);
        redirect('seating/' . $id);
    }

    public function destroy(int $id): void
    {
        $plan = $this->planOrDeny($id);
        db()->run('DELETE FROM seating_plans WHERE id = ?', [$id]);
        audit('seating_deleted', 'seating_plans', $id);
        flash('success', 'Seating plan deleted.');
        redirect($plan['kind'] === 'exam' && $plan['series_id'] ? 'exam-timetable/' . $plan['series_id'] : 'seating');
    }

    // ------------------------------------------------------------------ exam seating

    public function createExam(): void
    {
        $seriesId = (int) ($_GET['series'] ?? 0);
        $this->view('seating/exam_form', [
            'title' => 'New exam seating plan', 'seriesId' => $seriesId,
            'series' => db()->all('SELECT id, name FROM exam_series ORDER BY start_date DESC LIMIT 20'),
            'rooms' => Resources::rooms(),
            'classes' => db()->all('SELECT c.id, c.name, l.name AS level_name FROM classes c JOIN levels l ON l.id = c.level_id WHERE c.is_active = 1 ORDER BY l.sort_order, c.name'),
            'rolls' => Resources::rolls(),
        ]);
    }

    public function storeExam(): void
    {
        $classIds = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['classes'] ?? [])))));
        $classIds = array_values(array_filter($classIds, fn($c) => (bool) db()->value('SELECT 1 FROM classes WHERE id = ? AND is_active = 1', [$c])));
        $title = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 120);
        $series = (int) ($_POST['series_id'] ?? 0) ?: null;
        $room = (int) ($_POST['room_id'] ?? 0) ?: null;
        if (!$classIds || $title === '') {
            with_old($_POST);
            flash('error', 'Give the plan a name and tick at least one class.');
            redirect('seating/exam/create', $series ? ['series' => $series] : []);
        }
        if ($series && !db()->value('SELECT 1 FROM exam_series WHERE id = ?', [$series])) {
            $series = null;
        }
        if ($room && !db()->value('SELECT 1 FROM rooms WHERE id = ?', [$room])) {
            $room = null;
        }
        [$rows, $cols, $method] = $this->gridInput();
        $id = db()->insert('seating_plans', [
            'kind' => 'exam', 'series_id' => $series, 'room_id' => $room, 'title' => $title, 'class_list' => implode(',', $classIds),
            'seat_rows' => $rows, 'seat_cols' => $cols, 'method' => $method, 'updated_by' => Auth::id(),
        ]);
        $this->rearrangePlan($id, $rows, $cols, $method);
        audit('exam_seating_created', 'seating_plans', $id);
        redirect('seating/' . $id);
    }

    // ------------------------------------------------------------------ helpers

    private function rearrangePlan(int $id, int $rows, int $cols, string $method): void
    {
        $plan = Seating::plan($id);
        $pupils = Seating::pupils(Seating::classIds($plan));
        $map = Seating::arrange($pupils, $rows, $cols, $method, $plan['kind'] === 'exam');
        db()->update('seating_plans', ['seat_rows' => $rows, 'seat_cols' => $cols, 'method' => $method, 'updated_by' => Auth::id()], 'id = ?', [$id]);
        Seating::save($id, $map);
        audit('seating_arranged', 'seating_plans', $id, ['method' => $method, 'rows' => $rows, 'cols' => $cols]);
        $left = count($pupils) - count($map);
        flash($left > 0 ? 'warning' : 'success', $left > 0 ? "Seated " . count($map) . " students. $left did not fit — add rows or columns." : 'Seated all ' . count($map) . ' students. Drag students to move them, then save.');
        if ($plan['room_capacity'] && count($pupils) > (int) $plan['room_capacity']) {
            flash('warning', "{$plan['room_name']} has " . (int) $plan['room_capacity'] . ' seats but ' . count($pupils) . ' students are in this plan. Use a bigger room or split the classes into two plans.');
        }
    }

    /** @return array{0:int,1:int,2:string} */
    private function gridInput(): array
    {
        $method = (string) ($_POST['method'] ?? 'alpha');
        return [max(1, min(20, (int) ($_POST['seat_rows'] ?? 6))), max(1, min(16, (int) ($_POST['seat_cols'] ?? 6))), isset(Seating::METHODS[$method]) ? $method : 'alpha'];
    }

    /** Class teacher of the class, or a role that sees all classes. */
    private function canClass(array $class): bool
    {
        return can('seating.manage') && (Auth::classScope() === null || (int) ($class['class_teacher_id'] ?? 0) === Auth::id());
    }

    private function planOrDeny(int $id): array
    {
        $plan = Seating::plan($id) ?? $this->notFound();
        $ok = $plan['kind'] === 'exam' ? can('examtt.manage') : $this->canClass($plan);
        if (!$ok) {
            $this->forbidden();
        }
        return $plan;
    }
}
