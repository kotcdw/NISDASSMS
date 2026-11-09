<?php
declare(strict_types=1);

/** Weekly lesson notes: teachers write and submit; the Head / Academic Coordinator vets. */
class LessonNoteController extends Controller
{
    public function index(): void
    {
        $this->needsTable('lesson_notes');
        $term = $this->chosenTerm();
        if (!$term) {
            flash('error', 'Set up academic terms first.');
            redirect('curriculum');
        }
        $weeks = Curriculum::weeks($term);
        $cur = Curriculum::currentWeek($term);
        $week = max(1, min($weeks, (int) ($_GET['week'] ?? ($cur ?? 1))));
        $vet = can('lessonnotes.vet');
        $classId = (int) ($_GET['class'] ?? 0);
        $status = (string) ($_GET['status'] ?? '');
        $rows = LessonNotes::classSubjects($classId ?: null, $vet && ($_GET['mine'] ?? '') !== '1');
        $notes = [];
        if ($rows) {
            $in = implode(',', array_map(fn($r) => (int) $r['id'], $rows));
            foreach (db()->all("SELECT id, class_subject_id, status, topic, submitted_at FROM lesson_notes WHERE term_id = ? AND week_no = ? AND class_subject_id IN ($in)", [$term['id'], $week]) as $n) {
                $notes[(int) $n['class_subject_id']] = $n;
            }
        }
        $late = $cur !== null && $week <= $cur;
        foreach ($rows as &$r) {
            $n = $notes[(int) $r['id']] ?? null;
            $r['note'] = $n;
            $r['st'] = $n['status'] ?? 'none';
            $r['late'] = $late && !in_array($r['st'], ['submitted', 'approved'], true);
        }
        unset($r);
        if ($status !== '') {
            $rows = array_values(array_filter($rows, fn($r) => $status === 'late' ? $r['late'] : $r['st'] === $status));
        }
        $this->view('lessonnotes/index', [
            'title' => 'Lesson notes', 'term' => $term, 'week' => $week, 'weeks' => $weeks, 'cur' => $cur, 'rows' => $rows, 'vet' => $vet,
            'classId' => $classId, 'status' => $status, 'waiting' => LessonNotes::waiting((int) $term['id']),
            'terms' => db()->all('SELECT t.id, t.name, y.name AS year_name FROM terms t JOIN academic_years y ON y.id = t.academic_year_id ORDER BY t.start_date DESC'),
            'classes' => db()->all('SELECT c.id, c.name FROM classes c JOIN levels l ON l.id = c.level_id WHERE c.is_active = 1 ORDER BY l.sort_order, c.name'),
        ]);
    }

    /** Open the note for a class subject + week (new notes start from the scheme of work topics). */
    public function write(): void
    {
        $this->needsTable('lesson_notes');
        $term = $this->chosenTerm() ?? $this->notFound();
        $cs = Curriculum::classSubject((int) ($_GET['cs'] ?? 0)) ?? $this->notFound();
        $week = max(1, min(Curriculum::weeks($term), (int) ($_GET['week'] ?? 1)));
        $existing = db()->value('SELECT id FROM lesson_notes WHERE term_id = ? AND class_subject_id = ? AND week_no = ?', [$term['id'], $cs['id'], $week]);
        if ($existing) {
            redirect('lesson-notes/' . $existing);
        }
        if (!LessonNotes::canWrite($cs)) {
            $this->forbidden();
        }
        $planned = LessonNotes::plannedTopics((int) $term['id'], (int) $cs['id'], $week);
        $note = [
            'id' => 0, 'term_id' => $term['id'], 'class_subject_id' => $cs['id'], 'week_no' => $week, 'status' => 'draft',
            'topic' => implode('; ', array_column($planned, 'topic')), 'strand_id' => $planned[0]['strand_id'] ?? null,
            'indicators' => implode('; ', array_filter(array_column($planned, 'indicators'))), 'resources' => implode('; ', array_filter(array_column($planned, 'resources'))),
        ];
        $this->showForm($note, $cs, $term);
    }

    public function show(int $id): void
    {
        $this->needsTable('lesson_notes');
        $n = LessonNotes::note($id) ?? $this->notFound();
        $cs = Curriculum::classSubject((int) $n['class_subject_id']);
        if (!LessonNotes::canSee($cs)) {
            $this->forbidden();
        }
        $term = db()->one('SELECT t.*, y.name AS year_name FROM terms t JOIN academic_years y ON y.id = t.academic_year_id WHERE t.id = ?', [$n['term_id']]);
        if (LessonNotes::canWrite($cs) && in_array($n['status'], ['draft', 'returned'], true) && ($_GET['view'] ?? '') !== '1') {
            $this->showForm($n, $cs, $term);
            return;
        }
        $this->view('lessonnotes/show', ['title' => 'Lesson note — ' . $n['class_name'] . ' ' . $n['subject_name'], 'n' => $n, 'cs' => $cs, 'term' => $term,
            'canEdit' => LessonNotes::canWrite($cs) && in_array($n['status'], ['draft', 'returned'], true), 'canVet' => can('lessonnotes.vet') && $n['status'] === 'submitted']);
    }

    public function printNote(int $id): void
    {
        $this->needsTable('lesson_notes');
        $n = LessonNotes::note($id) ?? $this->notFound();
        if (!LessonNotes::canSee(Curriculum::classSubject((int) $n['class_subject_id']))) {
            $this->forbidden();
        }
        $term = db()->one('SELECT * FROM terms WHERE id = ?', [$n['term_id']]);
        $this->view('lessonnotes/print', ['title' => 'Lesson note', 'n' => $n, 'term' => $term], 'layouts/print');
    }

    /** Save (and optionally submit) a note. */
    public function save(): void
    {
        $this->needsTable('lesson_notes');
        $id = (int) ($_POST['id'] ?? 0);
        if ($id) {
            $n = db()->one('SELECT * FROM lesson_notes WHERE id = ?', [$id]) ?? $this->notFound();
            $termId = (int) $n['term_id'];
            $cs = Curriculum::classSubject((int) $n['class_subject_id']);
            $week = (int) $n['week_no'];
        } else {
            $termId = (int) ($_POST['term'] ?? 0);
            $cs = Curriculum::classSubject((int) ($_POST['cs'] ?? 0)) ?? $this->notFound();
            $term = db()->one('SELECT * FROM terms WHERE id = ?', [$termId]) ?? $this->notFound();
            $week = max(1, min(Curriculum::weeks($term), (int) ($_POST['week'] ?? 1)));
            $n = db()->one('SELECT * FROM lesson_notes WHERE term_id = ? AND class_subject_id = ? AND week_no = ?', [$termId, $cs['id'], $week]);
        }
        if (!LessonNotes::canWrite($cs)) {
            $this->forbidden();
        }
        if ($n && !in_array($n['status'], ['draft', 'returned'], true)) {
            flash('error', 'This note is ' . strtolower(LessonNotes::STATUS[$n['status']][0]) . ' and can no longer be changed.');
            redirect('lesson-notes/' . $n['id']);
        }
        $d = [];
        foreach (LessonNotes::FIELDS as $f) {
            $v = trim((string) ($_POST[$f] ?? ''));
            $max = in_array($f, ['starter', 'main_activities', 'plenary', 'assessment', 'reflection'], true) ? 5000 : ($f === 'topic' ? 200 : ($f === 'content_standard' || $f === 'core_competencies' ? 255 : 500));
            $d[$f] = $v === '' ? null : mb_substr($v, 0, $max);
        }
        if (!empty($_POST['competencies']) && is_array($_POST['competencies'])) {
            $d['core_competencies'] = mb_substr(implode(', ', array_intersect(LessonNotes::COMPETENCIES, $_POST['competencies'])), 0, 255) ?: $d['core_competencies'];
        }
        $sid = (int) ($_POST['strand_id'] ?? 0);
        $d['strand_id'] = $sid && db()->value('SELECT 1 FROM curriculum_strands WHERE id = ? AND subject_id = ?', [$sid, $cs['subject_id']]) ? $sid : null;
        $submit = ($_POST['action'] ?? '') === 'submit';
        if ((string) $d['topic'] === '') {
            with_old($_POST);
            flash('error', 'Enter the topic of the lesson.');
            redirect($n ? 'lesson-notes/' . $n['id'] : 'lesson-notes/write', $n ? [] : ['cs' => $cs['id'], 'term' => $termId, 'week' => $week]);
        }
        if ($submit && (trim((string) $d['main_activities']) === '' || trim((string) $d['assessment']) === '')) {
            $submit = false;
            flash('warning', 'Saved as a draft. Fill in the main activities and the assessment before submitting.');
        }
        if ($submit) {
            $d += ['status' => 'submitted', 'submitted_at' => gmdate('Y-m-d H:i:s')];
        }
        if ($n) {
            db()->update('lesson_notes', $d, 'id = ?', [$n['id']]);
            $nid = (int) $n['id'];
        } else {
            $nid = db()->insert('lesson_notes', $d + ['term_id' => $termId, 'class_subject_id' => $cs['id'], 'week_no' => $week, 'created_by' => Auth::id()]);
        }
        audit($submit ? 'lesson_note_submitted' : 'lesson_note_saved', 'lesson_notes', $nid, ['week' => $week]);
        if ($submit) {
            Notifier::inappPermission('lessonnotes.vet', "Lesson note to vet: {$cs['class_name']} {$cs['subject_name']} (week $week)", (string) $d['topic'], 'lesson-notes/' . $nid);
            flash('success', 'Submitted for vetting. You will be told when it is approved or returned.');
            redirect('lesson-notes/' . $nid, ['view' => 1]);
        }
        if (!array_filter((array) ($_SESSION['_flash'] ?? []), fn($f) => ($f['type'] ?? '') === 'warning')) {
            flash('success', 'Draft saved.');
        }
        redirect('lesson-notes/' . $nid);
    }

    public function vet(int $id): void
    {
        $this->needsTable('lesson_notes');
        $n = LessonNotes::note($id) ?? $this->notFound();
        if ($n['status'] !== 'submitted') {
            flash('warning', 'Only a submitted note can be vetted.');
            redirect('lesson-notes/' . $id);
        }
        $return = ($_POST['decision'] ?? '') === 'return';
        $comment = mb_substr(trim((string) ($_POST['comment'] ?? '')), 0, 500);
        if ($return && $comment === '') {
            flash('error', 'Write what needs correcting before returning the note.');
            redirect('lesson-notes/' . $id);
        }
        db()->update('lesson_notes', ['status' => $return ? 'returned' : 'approved', 'reviewed_by' => Auth::id(), 'reviewed_at' => gmdate('Y-m-d H:i:s'), 'review_comment' => $comment ?: null], 'id = ?', [$id]);
        audit($return ? 'lesson_note_returned' : 'lesson_note_approved', 'lesson_notes', $id, ['comment' => $comment]);
        $to = array_values(array_filter([(int) $n['created_by'], (int) $n['teacher_id']]));
        if ($to) {
            Notifier::inapp(array_unique($to), ($return ? 'Lesson note returned: ' : 'Lesson note approved: ') . "{$n['class_name']} {$n['subject_name']} (week {$n['week_no']})", $comment ?: ($return ? 'Please correct and submit again.' : 'Well done.'), 'lesson-notes/' . $id);
        }
        flash('success', $return ? 'Returned to the teacher with your comment.' : 'Approved.');
        $next = db()->value("SELECT id FROM lesson_notes WHERE term_id = ? AND status = 'submitted' AND id <> ? ORDER BY submitted_at LIMIT 1", [$n['term_id'], $id]);
        redirect($next && !empty($_POST['next']) ? 'lesson-notes/' . $next : 'lesson-notes', $next && !empty($_POST['next']) ? [] : ['term' => $n['term_id'], 'week' => $n['week_no'], 'status' => 'submitted']);
    }

    /** SMS + in-app reminder to teachers whose notes for the week are not submitted. */
    public function remind(): void
    {
        $this->needsTable('lesson_notes');
        $term = $this->chosenTerm() ?? $this->notFound();
        $week = (int) ($_POST['week'] ?? 0);
        $by = [];
        foreach (LessonNotes::classSubjects(null, true) as $cs) {
            $st = db()->value('SELECT status FROM lesson_notes WHERE term_id = ? AND class_subject_id = ? AND week_no = ?', [$term['id'], $cs['id'], $week]);
            $uid = (int) ($cs['teacher_id'] ?: $cs['class_teacher_id']);
            if ($uid && !in_array($st, ['submitted', 'approved'], true)) {
                $by[$uid][] = $cs['class_name'] . ' ' . $cs['subject_name'];
            }
        }
        $sms = 0;
        foreach ($by as $uid => $list) {
            Notifier::inapp([$uid], "Lesson notes for week $week are due", implode(', ', array_slice($list, 0, 6)) . (count($list) > 6 ? '…' : ''), 'lesson-notes?week=' . $week . '&mine=1');
            $u = db()->one('SELECT name, phone FROM users WHERE id = ?', [$uid]);
            if (!empty($_POST['sms']) && $u && $u['phone'] && ($body = Notifier::render('lesson_notes_late', ['teacher' => $u['name'], 'week' => $week, 'subjects' => implode(', ', array_slice($list, 0, 3)) . (count($list) > 3 ? '…' : ''), 'school' => school_name()]))) {
                if (Notifier::sms($u['phone'], $body, ['name' => $u['name'], 'event' => 'lesson_notes_late'])) {
                    $sms++;
                }
            }
        }
        audit('lesson_notes_reminded', 'terms', (int) $term['id'], ['week' => $week, 'teachers' => count($by)]);
        flash('success', count($by) ? count($by) . ' teacher(s) reminded' . ($sms ? " ($sms by SMS)." : '.') : 'Every note for this week is in.');
        redirect('lesson-notes', ['term' => $term['id'], 'week' => $week]);
    }

    // ------------------------------------------------------------------

    private function showForm(array $note, array $cs, array $term): void
    {
        $this->view('lessonnotes/form', [
            'title' => 'Lesson note — ' . $cs['class_name'] . ' ' . $cs['subject_name'], 'n' => $note, 'cs' => $cs, 'term' => $term,
            'tree' => Curriculum::tree((int) $cs['subject_id'], (string) $cs['category']),
            'planned' => LessonNotes::plannedTopics((int) $term['id'], (int) $cs['id'], (int) $note['week_no']),
        ]);
    }
}
