<?php
declare(strict_types=1);

/**
 * Teachers: one profile page per teacher (details, subjects, time table, attendance, class results, scheme coverage)
 * and the termly appraisal (teacher's self-assessment, then the head's rating). Heads see everyone; staff see their own.
 */
class TeacherController extends Controller
{
    public function index(): void
    {
        $term = $this->chosenTerm();
        $rows = [];
        foreach (Teachers::all() as $t) {
            $uid = (int) $t['id'];
            $a = Teachers::assignments($uid);
            $res = $term ? Teachers::results($uid, $term) : [];
            $rows[] = $t + [
                'classes' => array_column($a['classes'], 'name'),
                'subjects' => count($a['subjects']),
                'lessons' => Teachers::lessonsPerWeek($uid),
                'att' => $term ? Teachers::attendance($uid, $term) : null,
                'avg' => Teachers::resultsAverage($res),
                'cov' => $term ? Teachers::coverage($uid, (int) $term['id']) : null,
                'appraisal' => $term ? db()->one('SELECT status, overall FROM appraisals WHERE user_id = ? AND term_id = ?', [$uid, $term['id']]) : null,
            ];
        }
        $this->view('teachers/index', ['title' => 'Teachers', 'rows' => $rows, 'term' => $term, 'terms' => $this->terms()]);
    }

    public function me(): void
    {
        redirect('teachers/' . Auth::id());
    }

    public function portalProfile(int $id): void
    {
        $classes = [];
        if (Auth::role() === 'pupil') {
            $child = Accounts::pupilOf((int) Auth::id());
            if ($child && !empty($child['class_id'])) {
                $classes[] = (int) $child['class_id'];
            }
        } elseif (Auth::role() === 'parent') {
            foreach (Accounts::childrenOf((int) Auth::id()) as $child) {
                if ($child['status'] === 'active' && !empty($child['class_id'])) {
                    $classes[] = (int) $child['class_id'];
                }
            }
        } elseif (can('portal.parent')) {
            // Staff previewing the parent portal: the teacher links there must open too
            $classes = array_map('intval', array_column(db()->all('SELECT id FROM classes'), 'id'));
        } else {
            $this->notFound();
        }
        $classes = array_values(array_unique($classes));
        if (!$classes) {
            $this->notFound();
        }
        $marks = implode(',', array_fill(0, count($classes), '?'));
        $teacher = db()->one(
            "SELECT u.id, u.name, s.position, s.qualification FROM users u
             LEFT JOIN staff s ON s.user_id = u.id AND s.is_active = 1
             WHERE u.id = ? AND u.is_active = 1 AND (
                 EXISTS (SELECT 1 FROM classes c WHERE c.class_teacher_id = u.id AND c.id IN ($marks))
                 OR EXISTS (SELECT 1 FROM class_subjects cs WHERE cs.teacher_id = u.id AND cs.class_id IN ($marks))
             )",
            array_merge([$id], $classes, $classes)
        );
        if (!$teacher) {
            $this->notFound();
        }
        $args = array_merge([$id], $classes);
        $assignments = db()->all(
            "SELECT DISTINCT c.name AS class_name, s.name AS subject_name
             FROM class_subjects cs JOIN classes c ON c.id = cs.class_id
             JOIN subjects s ON s.id = cs.subject_id
             WHERE cs.teacher_id = ? AND cs.class_id IN ($marks)
             ORDER BY c.name, s.name",
            $args
        );
        $classNames = db()->all(
            "SELECT name FROM classes WHERE class_teacher_id = ? AND id IN ($marks) ORDER BY name",
            $args
        );
        $this->view('teachers/portal_profile', [
            'title' => $teacher['name'],
            'teacher' => $teacher,
            'assignments' => $assignments,
            'classNames' => array_column($classNames, 'name'),
        ]);
    }

    public function show(int $id): void
    {
        $t = $this->teacherOrDeny($id);
        $term = $this->chosenTerm();
        $assign = Teachers::assignments($id);
        $slots = db()->all(
            'SELECT ts.day, p.start_time, p.end_time, c.name AS class_name, s.name AS subject_name, ts.room
             FROM timetable_slots ts JOIN timetable_periods p ON p.id = ts.period_id JOIN classes c ON c.id = ts.class_id
             LEFT JOIN class_subjects cs ON cs.id = ts.class_subject_id LEFT JOIN subjects s ON s.id = cs.subject_id
             WHERE ts.teacher_id = ? ORDER BY ts.day, p.start_time',
            [$id]
        );
        $byDay = [];
        foreach ($slots as $s) {
            $byDay[$s['day']][] = $s;
        }
        $results = $term ? Teachers::results($id, $term) : [];
        $this->view('teachers/show', [
            'title' => $t['name'], 't' => $t, 'own' => $id === Auth::id(), 'term' => $term, 'terms' => $this->terms(),
            'record' => Teachers::record($id), 'assign' => $assign, 'byDay' => $byDay, 'lessons' => count($slots),
            'att' => $term ? Teachers::attendance($id, $term) : null,
            'results' => $results, 'avg' => Teachers::resultsAverage($results),
            'cov' => $term ? Teachers::coverage($id, (int) $term['id']) : null,
            'invig' => $term ? Teachers::invigilation($id, (int) $term['id']) : [],
            'appraisal' => $term ? Teachers::appraisal($id, (int) $term['id']) : null,
            'history' => Teachers::history($id),
        ]);
    }

    // ------------------------------------------------------------------ appraisal

    public function appraisal(int $id): void
    {
        $t = $this->teacherOrDeny($id);
        $term = $this->chosenTerm() ?? $this->notFound();
        $a = Teachers::appraisal($id, (int) $term['id']);
        $own = $id === Auth::id();
        $criteria = Teachers::criteria(!$a || $a['status'] !== 'completed');
        if ($a && $a['status'] === 'completed') {
            // Show the criteria that were scored, even if one was switched off later
            $criteria = array_values(array_filter(Teachers::criteria(false), fn($c) => isset($a['scores'][(int) $c['id']]) || (int) $c['is_active']));
        }
        $this->view('teachers/appraisal', [
            'title' => 'Appraisal — ' . $t['name'], 't' => $t, 'term' => $term, 'a' => $a, 'criteria' => $criteria,
            'own' => $own, 'canHead' => can('appraisal.manage') && !$own,
        ]);
    }

    public function printAppraisal(int $id): void
    {
        $t = $this->teacherOrDeny($id);
        $term = $this->chosenTerm() ?? $this->notFound();
        $a = Teachers::appraisal($id, (int) $term['id']) ?? $this->notFound();
        $criteria = array_values(array_filter(Teachers::criteria(false), fn($c) => isset($a['scores'][(int) $c['id']]) || (int) $c['is_active']));
        $this->view('teachers/appraisal_print', ['title' => 'Appraisal — ' . $t['name'], 't' => $t, 'term' => $term, 'a' => $a, 'criteria' => $criteria, 'record' => Teachers::record($id)], 'layouts/print');
    }

    /** Head opens the appraisal for one teacher. */
    public function start(int $id): void
    {
        $t = Teachers::user($id) ?? $this->notFound();
        $term = $this->chosenTerm() ?? $this->notFound();
        if ($id === Auth::id()) {
            flash('error', 'You cannot appraise yourself. Another administrator should open your appraisal.');
            redirect('teachers/' . $id);
        }
        $n = Teachers::open([$id], $term);
        audit('appraisal_opened', 'users', $id, ['term' => $term['id']]);
        flash('success', $n ? 'Appraisal opened. ' . $t['name'] . ' has been asked to do the self-assessment.' : 'This appraisal was already open.');
        redirect('teachers/' . $id . '/appraisal', ['term' => $term['id']]);
    }

    /** Head opens appraisals for every teacher for the term. */
    public function openAll(): void
    {
        $term = $this->chosenTerm() ?? $this->notFound();
        $ids = array_filter(array_map(fn($t) => (int) $t['id'], Teachers::all()), fn($id) => $id !== Auth::id());
        $n = Teachers::open($ids, $term);
        audit('appraisals_opened', 'appraisals', null, ['term' => $term['id'], 'count' => $n]);
        flash('success', $n ? "$n appraisal(s) opened for {$term['name']}. Each teacher has been asked to do the self-assessment." : 'Every teacher already has an appraisal for this term.');
        redirect('teachers', ['term' => $term['id']]);
    }

    public function saveSelf(int $id): void
    {
        if ($id !== Auth::id()) {
            $this->forbidden();
        }
        $term = $this->chosenTerm() ?? $this->notFound();
        $a = Teachers::appraisal($id, (int) $term['id']) ?? $this->notFound();
        $back = fn() => redirect('teachers/' . $id . '/appraisal', ['term' => $term['id']]);
        if ($a['status'] === 'completed') {
            flash('error', 'This appraisal is already completed.');
            $back();
        }
        $criteria = Teachers::criteria();
        $scores = $this->scores('self', $criteria);
        $submit = isset($_POST['submit']);
        if ($submit && count(array_filter($scores, fn($v) => $v !== null)) < count($criteria)) {
            with_old($_POST);
            flash('error', 'Give yourself a rating for every point before sending.');
            $back();
        }
        db()->transaction(function (Database $d) use ($a, $scores, $submit) {
            foreach ($scores as $cid => $v) {
                $d->run('INSERT INTO appraisal_scores (appraisal_id, criterion_id, self_score) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE self_score = VALUES(self_score)', [$a['id'], $cid, $v]);
            }
            $upd = ['self_comment' => $this->text('self_comment', 3000)];
            if ($submit) {
                $upd += ['status' => 'self_done', 'self_submitted_at' => date('Y-m-d H:i:s')];
            }
            $d->update('appraisals', $upd, 'id = ?', [$a['id']]);
        });
        if ($submit) {
            audit('appraisal_self_submitted', 'appraisals', (int) $a['id']);
            Notifier::inappPermission('appraisal.manage', Auth::user()['name'] . ' finished the self-assessment', $term['name'] . ' appraisal is ready for your rating.', 'teachers/' . $id . '/appraisal?term=' . $term['id']);
        }
        flash('success', $submit ? 'Self-assessment sent to the head. You can still change it until the head completes the appraisal.' : 'Saved. Send it when you are ready.');
        $back();
    }

    public function saveHead(int $id): void
    {
        if ($id === Auth::id()) {
            $this->forbidden();
        }
        $t = Teachers::user($id) ?? $this->notFound();
        $term = $this->chosenTerm() ?? $this->notFound();
        $a = Teachers::appraisal($id, (int) $term['id']) ?? $this->notFound();
        $back = fn() => redirect('teachers/' . $id . '/appraisal', ['term' => $term['id']]);
        if ($a['status'] === 'completed') {
            flash('error', 'This appraisal is completed. Reopen it first to change it.');
            $back();
        }
        $criteria = Teachers::criteria();
        $scores = $this->scores('head', $criteria);
        $complete = isset($_POST['complete']);
        if ($complete && count(array_filter($scores, fn($v) => $v !== null)) < count($criteria)) {
            with_old($_POST);
            flash('error', 'Rate every point before completing the appraisal.');
            $back();
        }
        db()->transaction(function (Database $d) use ($a, $scores, $complete) {
            foreach ($scores as $cid => $v) {
                $d->run('INSERT INTO appraisal_scores (appraisal_id, criterion_id, head_score) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE head_score = VALUES(head_score)', [$a['id'], $cid, $v]);
            }
            $upd = [
                'strengths' => $this->text('strengths', 2000), 'improvements' => $this->text('improvements', 2000),
                'targets' => $this->text('targets', 2000), 'head_comment' => $this->text('head_comment', 3000),
                'overall' => Teachers::mean($scores), 'appraised_by' => Auth::id(),
            ];
            if ($complete) {
                $upd += ['status' => 'completed', 'completed_at' => date('Y-m-d H:i:s')];
            }
            $d->update('appraisals', $upd, 'id = ?', [$a['id']]);
        });
        if ($complete) {
            audit('appraisal_completed', 'appraisals', (int) $a['id'], ['teacher' => $id]);
            Notifier::inapp([$id], 'Your ' . $term['name'] . ' appraisal is complete', 'Read the head\'s rating and comments, then acknowledge it.', 'teachers/' . $id . '/appraisal?term=' . $term['id']);
        }
        flash('success', $complete ? 'Appraisal completed. ' . $t['name'] . ' can now read it and acknowledge it.' : 'Saved as a draft.');
        $back();
    }

    public function acknowledge(int $id): void
    {
        if ($id !== Auth::id()) {
            $this->forbidden();
        }
        $term = $this->chosenTerm() ?? $this->notFound();
        $a = Teachers::appraisal($id, (int) $term['id']) ?? $this->notFound();
        if ($a['status'] === 'completed' && !$a['acknowledged_at']) {
            db()->update('appraisals', ['teacher_response' => $this->text('teacher_response', 2000), 'acknowledged_at' => date('Y-m-d H:i:s')], 'id = ?', [$a['id']]);
            audit('appraisal_acknowledged', 'appraisals', (int) $a['id']);
            flash('success', 'Thank you. Your acknowledgement has been recorded.');
        }
        redirect('teachers/' . $id . '/appraisal', ['term' => $term['id']]);
    }

    public function reopen(int $id): void
    {
        if ($id === Auth::id()) {
            $this->forbidden();
        }
        $term = $this->chosenTerm() ?? $this->notFound();
        $a = Teachers::appraisal($id, (int) $term['id']) ?? $this->notFound();
        db()->update('appraisals', ['status' => $a['self_submitted_at'] ? 'self_done' : 'open', 'completed_at' => null, 'acknowledged_at' => null, 'teacher_response' => null], 'id = ?', [$a['id']]);
        audit('appraisal_reopened', 'appraisals', (int) $a['id']);
        flash('success', 'Appraisal reopened for changes.');
        redirect('teachers/' . $id . '/appraisal', ['term' => $term['id']]);
    }

    // ------------------------------------------------------------------ criteria

    public function criteria(): void
    {
        $this->view('teachers/criteria', ['title' => 'Appraisal criteria', 'criteria' => Teachers::criteria(false)]);
    }

    public function saveCriteria(): void
    {
        db()->transaction(function (Database $d) {
            foreach ((array) ($_POST['c'] ?? []) as $id => $c) {
                $name = mb_substr(trim((string) ($c['name'] ?? '')), 0, 120);
                if ($name === '' || !ctype_digit((string) $id)) {
                    continue;
                }
                $d->update('appraisal_criteria', [
                    'name' => $name, 'description' => mb_substr(trim((string) ($c['description'] ?? '')), 0, 255) ?: null,
                    'sort_order' => (int) ($c['sort_order'] ?? 0), 'is_active' => isset($c['is_active']) ? 1 : 0,
                ], 'id = ?', [(int) $id]);
            }
            $new = mb_substr(trim((string) ($_POST['new_name'] ?? '')), 0, 120);
            if ($new !== '') {
                $d->insert('appraisal_criteria', [
                    'name' => $new, 'description' => mb_substr(trim((string) ($_POST['new_description'] ?? '')), 0, 255) ?: null,
                    'sort_order' => (int) $d->value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM appraisal_criteria'),
                ]);
            }
        });
        audit('appraisal_criteria_saved', 'appraisal_criteria');
        flash('success', 'Appraisal criteria saved. Changes apply to appraisals that are not yet completed.');
        redirect('teachers/criteria');
    }

    // ------------------------------------------------------------------ helpers

    /** The teacher, if the signed-in user may see them (own page, or Teacher profiles permission). */
    private function teacherOrDeny(int $id): array
    {
        $t = Teachers::user($id) ?? $this->notFound();
        if ($id !== Auth::id() && !can('teachers.view')) {
            $this->forbidden();
        }
        return $t;
    }

    /** @return array<int,?int> criterion id => 1..5 or null */
    private function scores(string $who, array $criteria): array
    {
        $in = (array) ($_POST[$who] ?? []);
        $out = [];
        foreach ($criteria as $c) {
            $v = (int) ($in[$c['id']] ?? 0);
            $out[(int) $c['id']] = $v >= 1 && $v <= 5 ? $v : null;
        }
        return $out;
    }

    private function text(string $key, int $max): ?string
    {
        $v = trim((string) ($_POST[$key] ?? ''));
        return $v === '' ? null : mb_substr($v, 0, $max);
    }

    private function terms(): array
    {
        return db()->all('SELECT t.id, t.name, y.name AS year_name FROM terms t JOIN academic_years y ON y.id = t.academic_year_id WHERE t.start_date <= CURDATE() OR t.is_current = 1 ORDER BY t.start_date DESC');
    }
}
