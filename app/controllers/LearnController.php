<?php
declare(strict_types=1);

/** Pupils: take quizzes for their class. */
class LearnController extends Controller
{
    public function index(): void
    {
        $p = $this->pupil();
        Quiz::expireStale();
        $quizzes = $p['class_id'] ? db()->all(
            "SELECT q.*, s.name AS subject_name, u.name AS teacher,
                    (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.id) AS questions,
                    (SELECT COUNT(*) FROM quiz_attempts a WHERE a.quiz_id = q.id AND a.pupil_id = ?) AS used,
                    (SELECT MAX(a.score) FROM quiz_attempts a WHERE a.quiz_id = q.id AND a.pupil_id = ? AND a.submitted_at IS NOT NULL) AS best,
                    (SELECT MAX(a.max_score) FROM quiz_attempts a WHERE a.quiz_id = q.id AND a.pupil_id = ?) AS max_score,
                    (SELECT a.id FROM quiz_attempts a WHERE a.quiz_id = q.id AND a.pupil_id = ? AND a.submitted_at IS NULL LIMIT 1) AS open_attempt,
                    (SELECT a.id FROM quiz_attempts a WHERE a.quiz_id = q.id AND a.pupil_id = ? AND a.submitted_at IS NOT NULL ORDER BY a.score DESC, a.id DESC LIMIT 1) AS best_attempt
             FROM quizzes q JOIN class_subjects cs ON cs.id = q.class_subject_id JOIN subjects s ON s.id = cs.subject_id
             LEFT JOIN users u ON u.id = q.created_by
             WHERE cs.class_id = ? AND q.status IN ('published','closed') ORDER BY q.status = 'published' DESC, COALESCE(q.closes_at, '9999-12-31'), q.id DESC",
            [$p['id'], $p['id'], $p['id'], $p['id'], $p['id'], $p['class_id']]
        ) : [];
        $this->view('learn/index', ['title' => 'My quizzes', 'p' => $p, 'quizzes' => $quizzes]);
    }

    public function start(int $id): void
    {
        $p = $this->pupil();
        $q = $this->quizFor($p, $id);
        $open = db()->value('SELECT id FROM quiz_attempts WHERE quiz_id = ? AND pupil_id = ? AND submitted_at IS NULL', [$id, $p['id']]);
        if ($open) {
            redirect('learn/attempt/' . $open);
        }
        $used = (int) db()->value('SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = ? AND pupil_id = ?', [$id, $p['id']]);
        if (!Quiz::isOpen($q) || $used >= (int) $q['max_attempts']) {
            flash('error', !Quiz::isOpen($q) ? 'This quiz is not open.' : 'You have used all your attempts.');
            redirect('learn');
        }
        $ids = array_map('intval', array_column(db()->all('SELECT id FROM quiz_questions WHERE quiz_id = ? ORDER BY sort_order, id', [$id]), 'id'));
        if ((int) $q['shuffle']) {
            shuffle($ids);
        }
        $deadline = null;
        if ((int) $q['time_limit'] > 0) {
            $deadline = date('Y-m-d H:i:s', time() + (int) $q['time_limit'] * 60);
        }
        if ($q['closes_at'] && (!$deadline || $q['closes_at'] < $deadline)) {
            $deadline = $q['closes_at'];
        }
        try {
            $aid = db()->insert('quiz_attempts', [
                'quiz_id' => $id, 'pupil_id' => $p['id'], 'attempt_no' => $used + 1, 'started_at' => date('Y-m-d H:i:s'),
                'deadline_at' => $deadline, 'question_order' => json_encode($ids), 'answers' => '{}',
            ]);
        } catch (PDOException $e) { // double click: attempt number already taken
            redirect('learn');
        }
        redirect('learn/attempt/' . $aid);
    }

    public function take(int $aid): void
    {
        [$p, $a, $q] = $this->attempt($aid);
        if ($a['submitted_at'] || $this->expired($a)) {
            Quiz::finalise($aid);
            redirect('learn/attempt/' . $aid . '/result');
        }
        $this->view('learn/take', ['title' => $q['title'], 'q' => $q, 'a' => $a, 'questions' => $this->ordered($a), 'answers' => json_decode((string) $a['answers'], true) ?: []]);
    }

    /** Autosave (every few seconds from the page) so nothing is lost if the time runs out or the network drops. */
    public function save(int $aid): void
    {
        [$p, $a] = $this->attempt($aid);
        header('Content-Type: application/json');
        if ($a['submitted_at'] || $this->expired($a)) {
            echo json_encode(['ok' => false, 'closed' => true]);
            return;
        }
        db()->update('quiz_attempts', ['answers' => json_encode($this->collect($a), JSON_UNESCAPED_UNICODE)], 'id = ? AND submitted_at IS NULL', [$aid]);
        echo json_encode(['ok' => true]);
    }

    public function submit(int $aid): void
    {
        [$p, $a] = $this->attempt($aid);
        if (!$a['submitted_at']) {
            // Late beyond the grace period: keep what was autosaved, ignore the late form
            Quiz::finalise($aid, $this->expired($a) ? null : $this->collect($a));
        }
        redirect('learn/attempt/' . $aid . '/result');
    }

    public function result(int $aid): void
    {
        [$p, $a, $q] = $this->attempt($aid);
        if (!$a['submitted_at']) {
            if (!$this->expired($a)) {
                redirect('learn/attempt/' . $aid);
            }
            Quiz::finalise($aid);
            [$p, $a, $q] = $this->attempt($aid);
        }
        $this->view('learn/result', [
            'title' => 'Result — ' . $q['title'], 'q' => $q, 'a' => $a, 'questions' => $this->ordered($a),
            'answers' => json_decode((string) $a['answers'], true) ?: [], 'reveal' => Quiz::showAnswers($q),
        ]);
    }

    // ---------------------------------------------------------------- helpers

    private function collect(array $a): array
    {
        $valid = array_flip(json_decode((string) $a['question_order'], true) ?: []);
        $out = [];
        foreach ((array) ($_POST['answer'] ?? []) as $qid => $val) {
            if (isset($valid[(int) $qid]) && is_string($val)) {
                $out[(int) $qid] = mb_substr(trim($val), 0, 200);
            }
        }
        return $out;
    }

    private function expired(array $a): bool
    {
        return $a['deadline_at'] && strtotime($a['deadline_at']) + Quiz::GRACE_SECONDS < time();
    }

    private function ordered(array $a): array
    {
        $order = json_decode((string) $a['question_order'], true) ?: [];
        $all = [];
        foreach (db()->all('SELECT * FROM quiz_questions WHERE quiz_id = ?', [$a['quiz_id']]) as $qq) {
            $all[(int) $qq['id']] = $qq;
        }
        $out = [];
        foreach ($order as $qid) {
            if (isset($all[$qid])) {
                $out[] = $all[$qid];
                unset($all[$qid]);
            }
        }
        return array_merge($out, array_values($all)); // questions added later go at the end
    }

    private function pupil(): array
    {
        if (Auth::role() === 'super_admin' && current_route() === 'learn') {
            // Super Admin: preview the quiz page as a chosen pupil sees it (read-only — no attempts are started)
            $pid = (int) ($_GET['pupil'] ?? 0);
            $p = $pid ? Accounts::pupilById($pid) : null;
            if (!$p) {
                $this->previewPick();
            }
            $p['preview'] = true;
            return $p;
        }
        if (Auth::role() !== 'pupil') {
            // Other staff opening the pupils' quiz page: send them to the teachers' quiz pages instead
            flash('info', '"My quizzes" is the page students see. Teachers set and mark quizzes under Quizzes.');
            redirect(can('quiz.manage') ? 'quizzes' : 'dashboard');
        }
        $p = Accounts::pupilOf((int) Auth::id());
        if (!$p) {
            flash('error', 'Your account is not linked to a student in a class this year. Please ask the school office to enrol you in your class for ' . (current_year()['name'] ?? 'this year') . '.');
            redirect('notices');
        }
        return $p;
    }

    private function previewPick(): void
    {
        $classId = (int) ($_GET['class'] ?? 0);
        $yid = current_year()['id'] ?? 0;
        $classes = db()->all('SELECT c.id, c.name FROM classes c JOIN levels l ON l.id = c.level_id WHERE c.is_active = 1 ORDER BY l.sort_order, c.name');
        $pupils = $classId ? db()->all(
            "SELECT p.id, p.first_name, p.last_name, p.admission_no FROM enrolments e JOIN pupils p ON p.id = e.pupil_id
             WHERE e.class_id = ? AND e.academic_year_id = ? AND e.status = 'active' AND p.status = 'active' AND p.deleted_at IS NULL ORDER BY p.last_name, p.first_name",
            [$classId, $yid]
        ) : [];
        $this->view('learn/preview_pick', ['title' => 'Student quizzes (preview)', 'classes' => $classes, 'classId' => $classId, 'pupils' => $pupils]);
        exit;
    }

    private function quizFor(array $p, int $id): array
    {
        return db()->one(
            "SELECT q.* FROM quizzes q JOIN class_subjects cs ON cs.id = q.class_subject_id WHERE q.id = ? AND cs.class_id = ? AND q.status IN ('published','closed')",
            [$id, $p['class_id']]
        ) ?? $this->notFound();
    }

    /** @return array{0: array, 1: array, 2: array} pupil, attempt, quiz */
    private function attempt(int $aid): array
    {
        $p = $this->pupil();
        $a = db()->one('SELECT * FROM quiz_attempts WHERE id = ? AND pupil_id = ?', [$aid, $p['id']]) ?? $this->notFound();
        $q = db()->one('SELECT q.*, s.name AS subject_name FROM quizzes q JOIN class_subjects cs ON cs.id = q.class_subject_id JOIN subjects s ON s.id = cs.subject_id WHERE q.id = ?', [$a['quiz_id']]);
        return [$p, $a, $q];
    }
}
