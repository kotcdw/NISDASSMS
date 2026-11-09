<?php
declare(strict_types=1);

/**
 * Year-end promotion: move each class's pupils into next year's classes.
 * Uses the promotion decision on the last-term report card as the suggestion.
 */
class PromotionController extends Controller
{
    public function index(): void
    {
        [$from, $to, $years] = $this->years();
        $classes = [];
        if ($from) {
            $classes = db()->all(
                "SELECT c.id, c.name, l.name AS level_name, l.sort_order,
                        SUM(e.status = 'active') AS waiting,
                        SUM(e.status IN ('promoted','repeated','left')) AS processed
                 FROM classes c JOIN levels l ON l.id = c.level_id
                 JOIN enrolments e ON e.class_id = c.id AND e.academic_year_id = ?
                 JOIN pupils p ON p.id = e.pupil_id AND p.deleted_at IS NULL
                 GROUP BY c.id, c.name, l.name, l.sort_order ORDER BY l.sort_order DESC, c.name",
                [$from['id']]
            );
        }
        $this->view('promotion/index', ['title' => 'Year-end promotion', 'from' => $from, 'to' => $to, 'years' => $years, 'classes' => $classes]);
    }

    public function classView(int $id): void
    {
        [$from, $to] = $this->years();
        if (!$from || !$to) {
            redirect('promotion');
        }
        $class = $this->classRow($id);
        $lastTerm = db()->one('SELECT * FROM terms WHERE academic_year_id = ? ORDER BY term_no DESC LIMIT 1', [$from['id']]);
        $next = $this->nextLevel((int) $class['sort_order']);
        $pupils = db()->all(
            "SELECT p.id, p.first_name, p.middle_name, p.last_name, p.admission_no, p.status AS pupil_status, e.status AS enrol_status,
                    rr.promotion, ne.class_id AS new_class_id, nc.name AS new_class_name,
                    (SELECT COALESCE(SUM(i.total - i.paid), 0) FROM invoices i WHERE i.pupil_id = p.id AND i.status IN ('unpaid','part')) AS owing
             FROM enrolments e JOIN pupils p ON p.id = e.pupil_id
             LEFT JOIN report_remarks rr ON rr.pupil_id = p.id AND rr.term_id = ?
             LEFT JOIN enrolments ne ON ne.pupil_id = p.id AND ne.academic_year_id = ? LEFT JOIN classes nc ON nc.id = ne.class_id
             WHERE e.class_id = ? AND e.academic_year_id = ? AND p.deleted_at IS NULL ORDER BY p.last_name, p.first_name",
            [$lastTerm['id'] ?? 0, $to['id'], $id, $from['id']]
        );
        $this->view('promotion/class', [
            'title' => 'Promotion — ' . $class['name'], 'class' => $class, 'from' => $from, 'to' => $to, 'pupils' => $pupils, 'next' => $next,
            'nextClasses' => $next ? $this->classesOfLevel((int) $next['id']) : [], 'sameClasses' => $this->classesOfLevel((int) $class['level_id']),
            'defaultNext' => $next ? $this->matchClass($class, (int) $next['id']) : null, 'lastTerm' => $lastTerm,
        ]);
    }

    public function apply(int $id): void
    {
        [$from, $to] = $this->years();
        $class = $this->classRow($id);
        if (!$from || !$to) {
            redirect('promotion');
        }
        $next = $this->nextLevel((int) $class['sort_order']);
        $validNext = $next ? array_map('intval', array_column($this->classesOfLevel((int) $next['id']), 'id')) : [];
        $validSame = array_map('intval', array_column($this->classesOfLevel((int) $class['level_id']), 'id'));
        // decision[pupil] = promote:<class id> | repeat:<class id> | graduate | leave | skip
        $actions = [];
        $targets = [];
        foreach ((array) ($_POST['decision'] ?? []) as $pid => $val) {
            [$a, $t] = array_pad(explode(':', (string) $val, 2), 2, '0');
            $actions[(int) $pid] = $a;
            $targets[(int) $pid] = (int) $t;
        }
        $counts = ['promoted' => 0, 'repeated' => 0, 'graduated' => 0, 'left_school' => 0];
        $errors = 0;
        $done = [];
        db()->transaction(function (Database $d) use ($from, $to, $id, $actions, $targets, $validNext, $validSame, $next, &$counts, &$errors, &$done) {
            $rows = $d->all(
                "SELECT e.id AS enrol_id, e.pupil_id FROM enrolments e JOIN pupils p ON p.id = e.pupil_id
                 WHERE e.class_id = ? AND e.academic_year_id = ? AND e.status = 'active' AND p.status = 'active' AND p.deleted_at IS NULL FOR UPDATE",
                [$id, $from['id']]
            );
            foreach ($rows as $r) {
                $pid = (int) $r['pupil_id'];
                $act = (string) ($actions[$pid] ?? '');
                $target = (int) ($targets[$pid] ?? 0);
                if ($act === '' || $act === 'skip' || $d->value('SELECT id FROM enrolments WHERE pupil_id = ? AND academic_year_id = ?', [$pid, $to['id']])) {
                    continue;
                }
                if (($act === 'promote' && !in_array($target, $validNext, true)) || ($act === 'repeat' && !in_array($target, $validSame, true))
                    || ($act === 'graduate' && $next) || !in_array($act, ['promote', 'repeat', 'graduate', 'leave'], true)) {
                    $errors++;
                    continue;
                }
                $done[$pid] = $act;
                if ($act === 'promote' || $act === 'repeat') {
                    $d->insert('enrolments', ['pupil_id' => $pid, 'class_id' => $target, 'academic_year_id' => $to['id'], 'enrolled_on' => $to['start_date']]);
                    $d->update('enrolments', ['status' => $act === 'promote' ? 'promoted' : 'repeated'], 'id = ?', [$r['enrol_id']]);
                    $counts[$act === 'promote' ? 'promoted' : 'repeated']++;
                } elseif ($act === 'graduate') {
                    $d->update('enrolments', ['status' => 'promoted'], 'id = ?', [$r['enrol_id']]);
                    $d->update('pupils', ['status' => 'graduated', 'left_on' => $from['end_date']], 'id = ?', [$pid]);
                    $counts['graduated']++;
                } else {
                    $d->update('enrolments', ['status' => 'left'], 'id = ?', [$r['enrol_id']]);
                    $d->update('pupils', ['status' => 'withdrawn', 'left_on' => date('Y-m-d') < $from['end_date'] ? date('Y-m-d') : $from['end_date']], 'id = ?', [$pid]);
                    $counts['left_school']++;
                }
            }
            if (array_sum($counts)) {
                $d->insert('promotion_runs', $counts + ['from_year_id' => $from['id'], 'to_year_id' => $to['id'], 'class_id' => $id, 'run_by' => Auth::id(), 'details' => json_encode($done)]);
            }
        });
        audit('promotion_applied', 'classes', $id, $counts + ['to_year' => $to['name']]);
        flash(array_sum($counts) ? 'success' : 'warning', sprintf(
            '%s: %d promoted, %d repeating, %d completed JHS, %d left.%s',
            $class['name'], $counts['promoted'], $counts['repeated'], $counts['graduated'], $counts['left_school'],
            $errors ? " $errors student(s) skipped — check their target class." : ''
        ));
        redirect('promotion/class/' . $id);
    }

    /** Undo this class's promotion (only while the new year is not yet the current year). */
    public function undo(int $id): void
    {
        [$from, $to] = $this->years();
        $class = $this->classRow($id);
        if (!$from || !$to || (int) $to['is_current']) {
            flash('error', 'The new academic year is already in use, so promotion can no longer be undone. Change individual students instead.');
            redirect('promotion/class/' . $id);
        }
        $n = 0;
        db()->transaction(function (Database $d) use ($from, $to, $id, &$n) {
            // Only pupils moved by the promotion tool (not pupils withdrawn earlier in the year)
            foreach ($d->all('SELECT id, details FROM promotion_runs WHERE class_id = ? AND from_year_id = ? AND to_year_id = ?', [$id, $from['id'], $to['id']]) as $run) {
                foreach ((json_decode((string) $run['details'], true) ?: []) as $pid => $act) {
                    $pid = (int) $pid;
                    $d->run('DELETE FROM enrolments WHERE pupil_id = ? AND academic_year_id = ?', [$pid, $to['id']]);
                    $d->run("UPDATE enrolments SET status = 'active' WHERE pupil_id = ? AND academic_year_id = ? AND class_id = ?", [$pid, $from['id'], $id]);
                    if (in_array($act, ['graduate', 'leave'], true)) {
                        $d->run("UPDATE pupils SET status = 'active', left_on = NULL WHERE id = ?", [$pid]);
                    }
                    $n++;
                }
            }
            $d->run('DELETE FROM promotion_runs WHERE class_id = ? AND from_year_id = ? AND to_year_id = ?', [$id, $from['id'], $to['id']]);
        });
        audit('promotion_undone', 'classes', $id, ['pupils' => $n]);
        flash('success', "Promotion undone for $n student(s) in {$class['name']}.");
        redirect('promotion/class/' . $id);
    }

    // ---------------------------------------------------------------- helpers

    /** @return array{0: ?array, 1: ?array, 2: array} from-year (current), to-year (chosen or the next one), all later years */
    private function years(): array
    {
        $from = current_year();
        if (!$from) {
            return [null, null, []];
        }
        $later = db()->all('SELECT * FROM academic_years WHERE start_date > ? ORDER BY start_date', [$from['start_date']]);
        $toId = (int) ($_GET['to'] ?? $_POST['to'] ?? 0);
        $to = null;
        foreach ($later as $y) {
            if ((int) $y['id'] === $toId) {
                $to = $y;
            }
        }
        return [$from, $to ?: ($later[0] ?? null), $later];
    }

    private function classRow(int $id): array
    {
        return db()->one('SELECT c.*, l.name AS level_name, l.sort_order, l.category FROM classes c JOIN levels l ON l.id = c.level_id WHERE c.id = ?', [$id]) ?? $this->notFound();
    }

    private function nextLevel(int $sort): ?array
    {
        return db()->one('SELECT * FROM levels WHERE sort_order > ? ORDER BY sort_order LIMIT 1', [$sort]);
    }

    private function classesOfLevel(int $levelId): array
    {
        return db()->all('SELECT id, name, section FROM classes WHERE level_id = ? AND is_active = 1 ORDER BY name', [$levelId]);
    }

    /** Same stream in the next level (e.g. B4 A → B5 A), else the first class of that level. */
    private function matchClass(array $class, int $levelId): ?int
    {
        $list = $this->classesOfLevel($levelId);
        foreach ($list as $c) {
            if ($class['section'] !== null && $class['section'] !== '' && $c['section'] === $class['section']) {
                return (int) $c['id'];
            }
        }
        return isset($list[0]) ? (int) $list[0]['id'] : null;
    }
}
