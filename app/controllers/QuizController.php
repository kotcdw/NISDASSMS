<?php
declare(strict_types=1);

/** Teachers: build, publish and mark quizzes. */
class QuizController extends Controller
{
    public function index(): void
    {
        $css = Quiz::classSubjects();
        $ids = array_map('intval', array_column($css, 'id'));
        $rows = [];
        if ($ids) {
            $in = implode(',', $ids);
            $yid = current_year()['id'] ?? 0;
            $rows = db()->all(
                "SELECT q.*, c.name AS class_name, s.name AS subject_name, u.name AS author,
                        (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.id) AS questions,
                        (SELECT COUNT(DISTINCT a.pupil_id) FROM quiz_attempts a WHERE a.quiz_id = q.id AND a.submitted_at IS NOT NULL) AS done,
                        (SELECT AVG(a.score / NULLIF(a.max_score, 0)) * 100 FROM quiz_attempts a WHERE a.quiz_id = q.id AND a.submitted_at IS NOT NULL) AS avg_pct,
                        (SELECT COUNT(*) FROM enrolments e WHERE e.class_id = cs.class_id AND e.academic_year_id = ? AND e.status = 'active') AS pupils
                 FROM quizzes q JOIN class_subjects cs ON cs.id = q.class_subject_id JOIN classes c ON c.id = cs.class_id
                 JOIN subjects s ON s.id = cs.subject_id LEFT JOIN users u ON u.id = q.created_by
                 WHERE q.class_subject_id IN ($in) ORDER BY q.id DESC",
                [$yid]
            );
        }
        $this->view('quizzes/index', ['title' => 'Quizzes', 'rows' => $rows, 'css' => $css]);
    }

    public function store(): void
    {
        $data = $this->settingsInput('quizzes');
        $id = db()->insert('quizzes', $data + ['created_by' => Auth::id(), 'term_id' => current_term()['id'] ?? null, 'status' => 'draft']);
        audit('quiz_created', 'quizzes', $id);
        flash('success', 'Quiz created. Now add the questions.');
        redirect('quizzes/' . $id);
    }

    public function edit(int $id): void
    {
        $q = $this->find($id);
        $this->view('quizzes/edit', [
            'title' => $q['title'], 'q' => $q, 'css' => Quiz::classSubjects(),
            'questions' => db()->all('SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY sort_order, id', [$id]),
            'attempts' => (int) db()->value('SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = ?', [$id]),
        ]);
    }

    public function update(int $id): void
    {
        $this->find($id);
        $data = $this->settingsInput('quizzes/' . $id);
        db()->update('quizzes', $data, 'id = ?', [$id]);
        audit('quiz_updated', 'quizzes', $id);
        flash('success', 'Settings saved.');
        redirect('quizzes/' . $id);
    }

    public function saveQuestion(int $id, int $qid = 0): void
    {
        $q = $this->find($id);
        if ($qid) {
            db()->one('SELECT id FROM quiz_questions WHERE id = ? AND quiz_id = ?', [$qid, $id]) ?? $this->notFound();
        }
        $type = (string) ($_POST['type'] ?? 'mcq');
        $prompt = trim((string) ($_POST['prompt'] ?? ''));
        $marks = round((float) ($_POST['marks'] ?? 1), 2);
        $options = null;
        $answer = '';
        $err = null;
        if (!isset(Quiz::TYPES[$type]) || $prompt === '' || mb_strlen($prompt) > 4000) {
            $err = 'Write the question.';
        } elseif ($marks <= 0 || $marks > 100) {
            $err = 'Marks must be between 0.5 and 100.';
        } elseif ($type === 'mcq') {
            $opts = array_values(array_filter(array_map(fn($o) => mb_substr(trim((string) $o), 0, 300), (array) ($_POST['options'] ?? [])), fn($o) => $o !== ''));
            $correct = (int) ($_POST['correct'] ?? -1);
            // "correct" is the index among the boxes as typed; re-map after empty boxes are removed
            $raw = array_map(fn($o) => trim((string) $o), (array) ($_POST['options'] ?? []));
            $map = [];
            foreach ($raw as $i => $o) {
                if ($o !== '') {
                    $map[$i] = count($map);
                }
            }
            if (count($opts) < 2) {
                $err = 'Give at least two choices.';
            } elseif (!isset($map[$correct])) {
                $err = 'Tick the correct choice.';
            } else {
                $options = json_encode($opts, JSON_UNESCAPED_UNICODE);
                $answer = (string) $map[$correct];
            }
        } elseif ($type === 'truefalse') {
            $answer = ($_POST['tf'] ?? '') === 'false' ? 'false' : (($_POST['tf'] ?? '') === 'true' ? 'true' : '');
            if ($answer === '') {
                $err = 'Choose whether the statement is true or false.';
            }
        } else {
            $answer = mb_substr(implode('|', array_filter(array_map('trim', explode('|', (string) ($_POST['accepted'] ?? ''))))), 0, 255);
            if ($answer === '') {
                $err = 'Type the accepted answer(s). Separate alternatives with | e.g. Accra|accra city';
            }
        }
        if ($err) {
            flash('error', $err);
            redirect('quizzes/' . $id . ($qid ? '#q' . $qid : '#new'));
        }
        $data = ['type' => $type, 'prompt' => $prompt, 'options' => $options, 'answer' => $answer, 'marks' => $marks];
        if ($qid) {
            db()->update('quiz_questions', $data, 'id = ?', [$qid]);
        } else {
            $data['sort_order'] = 1 + (int) db()->value('SELECT COALESCE(MAX(sort_order), 0) FROM quiz_questions WHERE quiz_id = ?', [$id]);
            $data['quiz_id'] = $id;
            $qid = db()->insert('quiz_questions', $data);
        }
        $n = Quiz::remark($id);
        flash('success', 'Question saved.' . ($n ? " $n submitted attempt(s) re-marked." : ''));
        redirect('quizzes/' . $id . '#new');
    }

    public function deleteQuestion(int $id, int $qid): void
    {
        $this->find($id);
        db()->run('DELETE FROM quiz_questions WHERE id = ? AND quiz_id = ?', [$qid, $id]);
        Quiz::remark($id);
        flash('success', 'Question removed.');
        redirect('quizzes/' . $id);
    }

    public function moveQuestion(int $id, int $qid): void
    {
        $this->find($id);
        $list = array_map('intval', array_column(db()->all('SELECT id FROM quiz_questions WHERE quiz_id = ? ORDER BY sort_order, id', [$id]), 'id'));
        $pos = array_search($qid, $list, true);
        $to = $pos === false ? false : $pos + (($_POST['dir'] ?? '') === 'up' ? -1 : 1);
        if ($pos !== false && isset($list[$to])) {
            [$list[$pos], $list[$to]] = [$list[$to], $list[$pos]];
            foreach ($list as $i => $qqid) {
                db()->update('quiz_questions', ['sort_order' => $i + 1], 'id = ?', [$qqid]);
            }
        }
        redirect('quizzes/' . $id . '#q' . $qid);
    }

    public function status(int $id): void
    {
        $q = $this->find($id);
        $to = (string) ($_POST['status'] ?? '');
        if (!in_array($to, ['draft', 'published', 'closed'], true)) {
            redirect('quizzes/' . $id);
        }
        if ($to === 'published' && !(int) db()->value('SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = ?', [$id])) {
            flash('error', 'Add at least one question before publishing.');
            redirect('quizzes/' . $id);
        }
        if ($to === 'draft' && db()->value('SELECT 1 FROM quiz_attempts WHERE quiz_id = ? LIMIT 1', [$id])) {
            flash('error', 'Students have already started this quiz. Close it instead.');
            redirect('quizzes/' . $id);
        }
        db()->update('quizzes', ['status' => $to], 'id = ?', [$id]);
        if ($to === 'closed') {
            db()->run('UPDATE quiz_attempts SET deadline_at = NOW() WHERE quiz_id = ? AND submitted_at IS NULL', [$id]);
            foreach (db()->all('SELECT id FROM quiz_attempts WHERE quiz_id = ? AND submitted_at IS NULL', [$id]) as $a) {
                Quiz::finalise((int) $a['id']);
            }
        }
        audit('quiz_' . $to, 'quizzes', $id);
        if ($to === 'published') {
            $pupilUsers = array_column(db()->all(
                "SELECT p.user_id FROM enrolments e JOIN pupils p ON p.id = e.pupil_id JOIN class_subjects cs ON cs.class_id = e.class_id
                 WHERE cs.id = ? AND e.academic_year_id = ? AND e.status = 'active' AND p.user_id IS NOT NULL",
                [$q['class_subject_id'], current_year()['id'] ?? 0]
            ), 'user_id');
            Notifier::inapp(array_map('intval', $pupilUsers), 'New quiz: ' . $q['title'], $q['opens_at'] ? 'Opens ' . date('D j M H:i', strtotime($q['opens_at'])) : null, 'learn');
        }
        flash('success', ['published' => 'Quiz published. Students can take it ' . ($q['opens_at'] && $q['opens_at'] > date('Y-m-d H:i:s') ? 'from ' . date('D j M H:i', strtotime($q['opens_at'])) : 'now') . '.', 'closed' => 'Quiz closed.', 'draft' => 'Quiz moved back to draft.'][$to]);
        redirect('quizzes/' . $id);
    }

    public function results(int $id): void
    {
        $q = $this->find($id);
        Quiz::expireStale($id);
        $yid = current_year()['id'] ?? 0;
        $pupils = db()->all(
            "SELECT p.id, p.first_name, p.middle_name, p.last_name, p.admission_no, p.user_id FROM enrolments e JOIN pupils p ON p.id = e.pupil_id
             JOIN class_subjects cs ON cs.class_id = e.class_id WHERE cs.id = ? AND e.academic_year_id = ? AND e.status = 'active' AND p.status = 'active'
             ORDER BY p.last_name, p.first_name",
            [$q['class_subject_id'], $yid]
        );
        $best = [];
        foreach (db()->all('SELECT * FROM quiz_attempts WHERE quiz_id = ? ORDER BY attempt_no', [$id]) as $a) {
            $pid = (int) $a['pupil_id'];
            $best[$pid]['attempts'] = ($best[$pid]['attempts'] ?? 0) + 1;
            if ($a['submitted_at'] === null) {
                $best[$pid]['in_progress'] = true;
                continue;
            }
            if (!isset($best[$pid]['score']) || (float) $a['score'] > (float) $best[$pid]['score']) {
                $best[$pid] = array_merge($best[$pid], ['score' => $a['score'], 'max' => $a['max_score'], 'at' => $a['submitted_at'], 'attempt_id' => $a['id']]);
            }
        }
        $questions = db()->all('SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY sort_order, id', [$id]);
        // Item analysis: share of pupils (best attempt) who got each question right
        $right = [];
        $taken = 0;
        foreach ($best as $b) {
            if (empty($b['attempt_id'])) {
                continue;
            }
            $taken++;
            $ans = json_decode((string) db()->value('SELECT answers FROM quiz_attempts WHERE id = ?', [$b['attempt_id']]), true) ?: [];
            foreach ($questions as $qq) {
                if (Quiz::isCorrect($qq, $ans[$qq['id']] ?? null)) {
                    $right[$qq['id']] = ($right[$qq['id']] ?? 0) + 1;
                }
            }
        }
        if (isset($_GET['export'])) {
            $out = csv_start('quiz-' . $id . '-results.csv', ['Admission no', 'Student', 'Score', 'Out of', '%', 'Attempts', 'Submitted']);
            foreach ($pupils as $p) {
                $b = $best[(int) $p['id']] ?? [];
                csv_row($out, array_map('csv_safe', [$p['admission_no'], full_name($p), isset($b['score']) ? Quiz::fmt($b['score']) : '', isset($b['max']) ? Quiz::fmt($b['max']) : '',
                    isset($b['score']) && (float) $b['max'] > 0 ? round($b['score'] * 100 / $b['max']) : '', $b['attempts'] ?? 0, $b['at'] ?? '']));
            }
            csv_end($out);
        }
        $assessments = $q['term_id'] ? db()->all("SELECT * FROM assessments WHERE class_subject_id = ? AND term_id = ? AND kind = 'class' ORDER BY sort_order, id", [$q['class_subject_id'], $q['term_id']]) : [];
        $sheet = $q['term_id'] ? db()->value('SELECT status FROM score_sheets WHERE term_id = ? AND class_subject_id = ?', [$q['term_id'], $q['class_subject_id']]) : null;
        $this->view('quizzes/results', [
            'title' => 'Results — ' . $q['title'], 'q' => $q, 'pupils' => $pupils, 'best' => $best, 'questions' => $questions, 'right' => $right, 'taken' => $taken,
            'assessments' => $assessments, 'sheetLocked' => $sheet && $sheet !== 'draft',
        ]);
    }

    public function attempt(int $id, int $aid): void
    {
        $q = $this->find($id);
        $a = db()->one('SELECT a.*, p.first_name, p.last_name FROM quiz_attempts a JOIN pupils p ON p.id = a.pupil_id WHERE a.id = ? AND a.quiz_id = ?', [$aid, $id]) ?? $this->notFound();
        $this->view('quizzes/attempt', [
            'title' => $a['first_name'] . ' — ' . $q['title'], 'q' => $q, 'a' => $a,
            'questions' => db()->all('SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY sort_order, id', [$id]),
            'answers' => json_decode((string) $a['answers'], true) ?: [],
        ]);
    }

    /** Copy best scores into a class assessment of the score sheet (scaled to its maximum mark). */
    public function toAssessment(int $id): void
    {
        $q = $this->find($id);
        $as = db()->one("SELECT * FROM assessments WHERE id = ? AND class_subject_id = ? AND term_id = ? AND kind = 'class'", [(int) ($_POST['assessment_id'] ?? 0), $q['class_subject_id'], (int) $q['term_id']]);
        $sheet = db()->value('SELECT status FROM score_sheets WHERE term_id = ? AND class_subject_id = ?', [(int) $q['term_id'], $q['class_subject_id']]);
        if (!$as || ($sheet && $sheet !== 'draft')) {
            flash('error', !$as ? 'Choose a class assessment.' : 'The score sheet is submitted/approved and locked.');
            redirect('quizzes/' . $id . '/results');
        }
        $n = 0;
        db()->transaction(function (Database $d) use ($id, $as, &$n) {
            foreach ($d->all('SELECT pupil_id, MAX(score / NULLIF(max_score, 0)) AS ratio FROM quiz_attempts WHERE quiz_id = ? AND submitted_at IS NOT NULL GROUP BY pupil_id', [$id]) as $r) {
                if ($r['ratio'] === null) {
                    continue;
                }
                $score = round((float) $r['ratio'] * (float) $as['max_mark'], 1);
                $d->run(
                    'INSERT INTO assessment_scores (assessment_id, pupil_id, score, is_absent, updated_by) VALUES (?, ?, ?, 0, ?)
                     ON DUPLICATE KEY UPDATE score = VALUES(score), is_absent = 0, updated_by = VALUES(updated_by)',
                    [$as['id'], $r['pupil_id'], $score, Auth::id()]
                );
                $n++;
            }
        });
        audit('quiz_to_assessment', 'quizzes', $id, ['assessment' => $as['id'], 'pupils' => $n]);
        flash('success', "$n score(s) copied into \"{$as['title']}\" (out of " . Quiz::fmt($as['max_mark']) . ').');
        redirect('quizzes/' . $id . '/results');
    }

    public function destroy(int $id): void
    {
        $q = $this->find($id);
        if (db()->value('SELECT 1 FROM quiz_attempts WHERE quiz_id = ? AND submitted_at IS NOT NULL LIMIT 1', [$id])) {
            flash('error', 'Students have submitted this quiz, so it cannot be deleted. Close it instead.');
            redirect('quizzes/' . $id);
        }
        db()->run('DELETE FROM quizzes WHERE id = ?', [$id]);
        audit('quiz_deleted', 'quizzes', $id, ['title' => $q['title']]);
        flash('success', 'Quiz deleted.');
        redirect('quizzes');
    }

    // ---------------------------------------------------------------- helpers

    private function settingsInput(string $back): array
    {
        $in = $this->only(['title', 'class_subject_id', 'instructions', 'time_limit', 'opens_at', 'closes_at', 'max_attempts', 'show_answers']);
        $allowed = array_map('intval', array_column(Quiz::classSubjects(), 'id'));
        $opens = $in['opens_at'] ? date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', (string) $in['opens_at']))) : null;
        $closes = $in['closes_at'] ? date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', (string) $in['closes_at']))) : null;
        $err = null;
        if (!$in['title']) {
            $err = 'Give the quiz a title.';
        } elseif (!in_array((int) $in['class_subject_id'], $allowed, true)) {
            $err = 'Choose one of your classes and subjects.';
        } elseif ($opens && $closes && $closes <= $opens) {
            $err = 'The closing time must be after the opening time.';
        }
        if ($err) {
            flash('error', $err);
            redirect($back);
        }
        return [
            'title' => mb_substr($in['title'], 0, 150), 'class_subject_id' => (int) $in['class_subject_id'],
            'instructions' => $in['instructions'] ? mb_substr($in['instructions'], 0, 2000) : null,
            'time_limit' => max(0, min(300, (int) $in['time_limit'])), 'opens_at' => $opens, 'closes_at' => $closes,
            'max_attempts' => max(1, min(10, (int) ($in['max_attempts'] ?: 1))), 'shuffle' => isset($_POST['shuffle']) ? 1 : 0,
            'show_answers' => isset(Quiz::SHOW[(string) $in['show_answers']]) ? $in['show_answers'] : 'after_close',
        ];
    }

    private function find(int $id): array
    {
        $q = db()->one(
            'SELECT q.*, c.name AS class_name, s.name AS subject_name, cs.class_id FROM quizzes q JOIN class_subjects cs ON cs.id = q.class_subject_id
             JOIN classes c ON c.id = cs.class_id JOIN subjects s ON s.id = cs.subject_id WHERE q.id = ?',
            [$id]
        );
        if (!$q || !Quiz::canManage($q)) {
            $this->notFound();
        }
        return $q;
    }
}
