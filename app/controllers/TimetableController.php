<?php
declare(strict_types=1);

class TimetableController extends Controller
{
    public function index(): void
    {
        $classes = db()->all(
            'SELECT c.*, l.category, l.name AS level_name, u.name AS teacher_name,
                    (SELECT COALESCE(SUM(periods_per_week), 0) FROM class_subjects cs WHERE cs.class_id = c.id) AS planned,
                    (SELECT COUNT(*) FROM timetable_slots ts WHERE ts.class_id = c.id AND ts.class_subject_id IS NOT NULL) AS scheduled,
                    (SELECT COUNT(*) FROM timetable_periods p WHERE p.category = l.category AND p.kind = \'lesson\') * 5 AS capacity
             FROM classes c JOIN levels l ON l.id = c.level_id LEFT JOIN users u ON u.id = c.class_teacher_id
             WHERE c.is_active = 1 ORDER BY l.sort_order, c.name'
        );
        $teachers = db()->all(
            "SELECT u.id, u.name, (SELECT COUNT(*) FROM timetable_slots ts WHERE ts.teacher_id = u.id) AS lessons
             FROM users u WHERE u.is_active = 1 AND u.role IN (" . Auth::sqlRoles(['class_teacher', 'subject_teacher', 'head_teacher', 'academic_coordinator']) . ") ORDER BY u.name"
        );
        $this->view('timetable/index', [
            'title' => 'Time table', 'classes' => $classes, 'teachers' => $teachers,
            'clashCount' => can('timetable.manage') ? count(Timetable::clashes()) : 0,
        ]);
    }

    public function classView(int $id): void
    {
        $class = $this->classOr404($id);
        $periods = Timetable::periods($class['category']);
        $grid = [];
        foreach (db()->all(
            'SELECT ts.*, s.name AS subject_name, s.code, u.name AS teacher_name FROM timetable_slots ts
             LEFT JOIN class_subjects cs ON cs.id = ts.class_subject_id LEFT JOIN subjects s ON s.id = cs.subject_id
             LEFT JOIN users u ON u.id = ts.teacher_id WHERE ts.class_id = ?',
            [$id]
        ) as $s) {
            $grid[$s['day']][$s['period_id']] = $s;
        }
        $editing = can('timetable.manage') && isset($_GET['edit']);
        $this->view('timetable/class', [
            'title' => 'Time table — ' . $class['name'], 'class' => $class, 'periods' => $periods, 'grid' => $grid,
            'coverage' => Timetable::coverage($id), 'editing' => $editing,
        ]);
    }

    public function saveClass(int $id): void
    {
        $class = $this->classOr404($id);
        $periods = [];
        foreach (Timetable::periods($class['category'], true) as $p) {
            $periods[(int) $p['id']] = $p;
        }
        $subjects = [];
        foreach (db()->all('SELECT cs.*, s.name FROM class_subjects cs JOIN subjects s ON s.id = cs.subject_id WHERE cs.class_id = ?', [$id]) as $cs) {
            $subjects[(int) $cs['id']] = $cs;
        }
        $current = [];
        foreach (db()->all('SELECT * FROM timetable_slots WHERE class_id = ?', [$id]) as $s) {
            $current[$s['day'] . '-' . $s['period_id']] = $s;
        }
        $busy = Timetable::busyMap([$id]);
        $problems = [];
        $changed = 0;

        db()->transaction(function (Database $d) use ($id, $class, $periods, $subjects, $current, $busy, &$problems, &$changed) {
            foreach (Timetable::DAYS as $day => $dayName) {
                foreach ($periods as $pid => $p) {
                    $key = $day . '-' . $pid;
                    $csId = (int) ($_POST['cell'][$day][$pid] ?? 0);
                    $room = mb_substr(trim((string) ($_POST['room'][$day][$pid] ?? '')), 0, 40) ?: null;
                    $old = $current[$key] ?? null;
                    if ($csId === 0) {
                        if ($old) {
                            $d->run('DELETE FROM timetable_slots WHERE id = ?', [$old['id']]);
                            $changed++;
                        }
                        continue;
                    }
                    if (!isset($subjects[$csId])) {
                        continue;
                    }
                    $teacher = (int) ($subjects[$csId]['teacher_id'] ?: $class['class_teacher_id']) ?: null;
                    if ($old && (int) $old['class_subject_id'] === $csId && $old['room'] === $room) {
                        continue; // unchanged
                    }
                    if ($teacher && ($other = Timetable::teacherClash($busy, $teacher, $day, $p['start_time'], $p['end_time']))) {
                        $problems[] = "$dayName {$p['label']}: {$subjects[$csId]['name']} — the teacher already has " . db()->value('SELECT name FROM classes WHERE id = ?', [$other]) . ' at that time.';
                        continue;
                    }
                    if ($old) {
                        $d->update('timetable_slots', ['class_subject_id' => $csId, 'teacher_id' => $teacher, 'room' => $room, 'is_locked' => 1, 'updated_by' => Auth::id()], 'id = ?', [$old['id']]);
                    } else {
                        $d->insert('timetable_slots', ['class_id' => $id, 'day' => $day, 'period_id' => $pid, 'class_subject_id' => $csId, 'teacher_id' => $teacher, 'room' => $room, 'is_locked' => 1, 'updated_by' => Auth::id()]);
                    }
                    $changed++;
                }
            }
        });
        audit('timetable_saved', 'classes', $id, ['changes' => $changed, 'blocked' => count($problems)]);
        if ($problems) {
            flash('warning', 'Saved ' . $changed . ' change(s). Not saved because of teacher clashes: ' . implode(' ', array_slice($problems, 0, 5)) . (count($problems) > 5 ? ' …' : ''));
            redirect('timetable/class/' . $id, ['edit' => 1]);
        }
        flash('success', "Time table for {$class['name']} saved ($changed change" . ($changed === 1 ? '' : 's') . ').');
        redirect('timetable/class/' . $id);
    }

    public function generate(): void
    {
        $mode = in_array($_POST['mode'] ?? '', ['fill', 'keep_manual', 'replace'], true) ? $_POST['mode'] : 'keep_manual';
        $classIds = array_map('intval', (array) ($_POST['classes'] ?? []));
        if (!$classIds) {
            flash('error', 'Choose at least one class.');
            redirect('timetable');
        }
        @set_time_limit(120);
        $r = Timetable::generate($classIds, $mode);
        audit('timetable_generated', 'timetable_slots', null, ['classes' => $classIds, 'mode' => $mode, 'placed' => $r['placed']]);
        if ($r['unplaced']) {
            $list = array_map(fn($u) => "{$u['class']} {$u['subject']} ×{$u['missing']}", array_slice($r['unplaced'], 0, 8));
            flash('warning', "Placed {$r['placed']} lessons. Could not fit: " . implode(', ', $list) . (count($r['unplaced']) > 8 ? ' …' : '') . '. Usually the teacher is fully booked or the class has more lessons than periods — adjust lessons per week or teachers and run again.');
        } else {
            flash('success', "Placed {$r['placed']} lessons with no teacher clashes.");
        }
        redirect(count($classIds) === 1 ? 'timetable/class/' . $classIds[0] : 'timetable');
    }

    public function teacher(int $id): void
    {
        $teacher = db()->one("SELECT id, name, role FROM users WHERE id = ? AND role NOT IN ('parent','pupil')", [$id]) ?? $this->notFound();
        $rows = db()->all(
            'SELECT ts.day, ts.room, p.label, p.start_time, p.end_time, c.id AS class_id, c.name AS class_name, s.name AS subject_name
             FROM timetable_slots ts JOIN timetable_periods p ON p.id = ts.period_id JOIN classes c ON c.id = ts.class_id
             LEFT JOIN class_subjects cs ON cs.id = ts.class_subject_id LEFT JOIN subjects s ON s.id = cs.subject_id
             WHERE ts.teacher_id = ? ORDER BY ts.day, p.start_time',
            [$id]
        );
        $byDay = [];
        foreach ($rows as $r) {
            $byDay[$r['day']][] = $r;
        }
        $this->view('timetable/teacher', ['title' => 'Time table — ' . $teacher['name'], 'teacher' => $teacher, 'byDay' => $byDay, 'total' => count($rows)]);
    }

    public function my(): void
    {
        redirect('timetable/teacher/' . Auth::id());
    }

    // ---- Bell schedule ---------------------------------------------------------------

    public function periods(): void
    {
        $cat = $this->categoryParam();
        $this->view('timetable/periods', ['title' => 'Bell schedule', 'cat' => $cat, 'periods' => Timetable::periods($cat)]);
    }

    public function savePeriods(): void
    {
        $cat = $this->categoryParam();
        $rows = [];
        foreach ((array) ($_POST['p'] ?? []) as $key => $r) {
            $label = mb_substr(trim((string) ($r['label'] ?? '')), 0, 40);
            $start = (string) ($r['start'] ?? '');
            $end = (string) ($r['end'] ?? '');
            if ($label === '' && $start === '' && $end === '') {
                continue;
            }
            if ($label === '' || !preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end) || $end <= $start) {
                flash('error', 'Each row needs a name and a valid start and end time (end after start).');
                redirect('timetable/periods', ['cat' => $cat]);
            }
            $rows[] = [
                'id' => ctype_digit((string) $key) ? (int) $key : 0, 'label' => $label, 'start_time' => "$start:00", 'end_time' => "$end:00",
                'kind' => isset(Timetable::KINDS[$r['kind'] ?? '']) ? $r['kind'] : 'lesson', 'delete' => !empty($r['delete']),
            ];
        }
        usort($rows, fn($a, $b) => $a['start_time'] <=> $b['start_time']);
        $kept = array_values(array_filter($rows, fn($r) => !$r['delete']));
        for ($i = 1, $n = count($kept); $i < $n; $i++) {
            if ($kept[$i]['start_time'] < $kept[$i - 1]['end_time']) {
                flash('error', "“{$kept[$i]['label']}” overlaps “{$kept[$i - 1]['label']}”.");
                redirect('timetable/periods', ['cat' => $cat]);
            }
        }
        db()->transaction(function (Database $d) use ($rows, $cat) {
            $order = 0;
            foreach ($rows as $r) {
                if ($r['delete']) {
                    if ($r['id']) {
                        $d->run('DELETE FROM timetable_periods WHERE id = ? AND category = ?', [$r['id'], $cat]);
                    }
                    continue;
                }
                $data = ['label' => $r['label'], 'start_time' => $r['start_time'], 'end_time' => $r['end_time'], 'kind' => $r['kind'], 'sort_order' => ++$order];
                if ($r['id']) {
                    $d->update('timetable_periods', $data, 'id = ? AND category = ?', [$r['id'], $cat]);
                    if ($r['kind'] !== 'lesson') {
                        $d->run('DELETE FROM timetable_slots WHERE period_id = ?', [$r['id']]);
                    }
                } else {
                    $d->insert('timetable_periods', $data + ['category' => $cat]);
                }
            }
        });
        audit('bell_schedule_saved', 'timetable_periods', null, ['category' => $cat]);
        flash('success', 'Bell schedule saved.');
        redirect('timetable/periods', ['cat' => $cat]);
    }

    public function lessons(): void
    {
        $classId = (int) ($_GET['class'] ?? 0);
        $classes = db()->all('SELECT c.id, c.name, l.category FROM classes c JOIN levels l ON l.id = c.level_id WHERE c.is_active = 1 ORDER BY l.sort_order, c.name');
        $classId = $classId ?: (int) ($classes[0]['id'] ?? 0);
        $class = $classId ? $this->classOr404($classId) : null;
        $this->view('timetable/lessons', [
            'title' => 'Lessons per week', 'classes' => $classes, 'class' => $class,
            'rows' => $class ? Timetable::coverage($classId) : [],
            'capacity' => $class ? count(Timetable::periods($class['category'], true)) * 5 : 0,
        ]);
    }

    public function saveLessons(int $classId): void
    {
        $this->classOr404($classId);
        foreach ((array) ($_POST['ppw'] ?? []) as $csId => $n) {
            db()->update('class_subjects', ['periods_per_week' => max(0, min(20, (int) $n))], 'id = ? AND class_id = ?', [(int) $csId, $classId]);
        }
        if (!empty($_POST['apply_level'])) {
            // Copy these numbers to other classes of the same level (by subject)
            db()->run(
                'UPDATE class_subjects dst JOIN classes dc ON dc.id = dst.class_id
                 JOIN classes sc ON sc.id = ? AND sc.level_id = dc.level_id
                 JOIN class_subjects src ON src.class_id = sc.id AND src.subject_id = dst.subject_id
                 SET dst.periods_per_week = src.periods_per_week WHERE dst.class_id <> sc.id',
                [$classId]
            );
        }
        audit('lessons_per_week_saved', 'classes', $classId);
        flash('success', 'Lessons per week saved.');
        redirect('timetable/lessons', ['class' => $classId]);
    }

    // ---- Checks ------------------------------------------------------------------------

    public function check(): void
    {
        $short = [];
        foreach (db()->all('SELECT c.id, c.name FROM classes c JOIN levels l ON l.id = c.level_id WHERE c.is_active = 1 ORDER BY l.sort_order, c.name') as $c) {
            foreach (Timetable::coverage((int) $c['id']) as $r) {
                if ((int) $r['scheduled'] !== (int) $r['planned']) {
                    $short[] = $r + ['class_id' => $c['id'], 'class_name' => $c['name']];
                }
            }
        }
        $load = db()->all(
            "SELECT u.id, u.name, COUNT(ts.id) AS lessons FROM users u LEFT JOIN timetable_slots ts ON ts.teacher_id = u.id
             WHERE u.is_active = 1 AND u.role IN (" . Auth::sqlRoles(['class_teacher', 'subject_teacher', 'head_teacher', 'academic_coordinator']) . ")
             GROUP BY u.id, u.name ORDER BY lessons DESC, u.name"
        );
        $unassigned = (int) db()->value('SELECT COUNT(*) FROM timetable_slots WHERE class_subject_id IS NOT NULL AND teacher_id IS NULL');
        $this->view('timetable/check', ['title' => 'Time table check', 'clashes' => Timetable::clashes(), 'short' => $short, 'load' => $load, 'unassigned' => $unassigned]);
    }

    /** Find teachers who are free at a given day and time (for substitution). */
    public function free(): void
    {
        $day = (int) ($_GET['day'] ?? min(5, (int) date('N')));
        $day = isset(Timetable::DAYS[$day]) ? $day : 1;
        $periods = Timetable::periods('jhs', true) ?: Timetable::periods('primary', true);
        $pid = (int) ($_GET['period'] ?? ($periods[0]['id'] ?? 0));
        $period = null;
        foreach ($periods as $p) {
            if ((int) $p['id'] === $pid) {
                $period = $p;
            }
        }
        $free = [];
        $busyNow = [];
        if ($period) {
            $busy = Timetable::busyMap();
            foreach (db()->all("SELECT id, name, role FROM users WHERE is_active = 1 AND role IN (" . Auth::sqlRoles(['class_teacher', 'subject_teacher', 'head_teacher', 'academic_coordinator']) . ") ORDER BY name") as $t) {
                $clash = Timetable::teacherClash($busy, (int) $t['id'], $day, $period['start_time'], $period['end_time']);
                $lessonsToday = count($busy[(int) $t['id']][$day] ?? []);
                if ($clash) {
                    $busyNow[] = $t + ['class' => db()->value('SELECT name FROM classes WHERE id = ?', [$clash]), 'today' => $lessonsToday];
                } else {
                    $free[] = $t + ['today' => $lessonsToday];
                }
            }
            usort($free, fn($a, $b) => $a['today'] <=> $b['today']);
        }
        $this->view('timetable/free', ['title' => 'Find a substitute', 'day' => $day, 'periods' => $periods, 'period' => $period, 'free' => $free, 'busyNow' => $busyNow]);
    }

    public function printAll(): void
    {
        $data = [];
        foreach (db()->all('SELECT c.*, l.category, u.name AS teacher_name FROM classes c JOIN levels l ON l.id = c.level_id LEFT JOIN users u ON u.id = c.class_teacher_id WHERE c.is_active = 1 ORDER BY l.sort_order, c.name') as $c) {
            if (isset($_GET['class']) && (int) $_GET['class'] !== (int) $c['id']) {
                continue;
            }
            $grid = [];
            foreach (db()->all('SELECT ts.*, s.code, s.name AS subject_name, u.name AS teacher_name FROM timetable_slots ts LEFT JOIN class_subjects cs ON cs.id = ts.class_subject_id LEFT JOIN subjects s ON s.id = cs.subject_id LEFT JOIN users u ON u.id = ts.teacher_id WHERE ts.class_id = ?', [$c['id']]) as $s) {
                $grid[$s['day']][$s['period_id']] = $s;
            }
            $data[] = ['class' => $c, 'periods' => Timetable::periods($c['category']), 'grid' => $grid];
        }
        $this->view('timetable/print', ['title' => 'Time tables', 'data' => $data], 'layouts/print');
    }

    // ---------------------------------------------------------------------------------

    private function classOr404(int $id): array
    {
        return db()->one('SELECT c.*, l.category, l.name AS level_name, u.name AS teacher_name FROM classes c JOIN levels l ON l.id = c.level_id LEFT JOIN users u ON u.id = c.class_teacher_id WHERE c.id = ?', [$id]) ?? $this->notFound();
    }

    private function categoryParam(): string
    {
        $c = (string) ($_GET['cat'] ?? $_POST['cat'] ?? 'primary');
        return in_array($c, ['creche', 'kg', 'primary', 'jhs'], true) ? $c : 'primary';
    }
}
