<?php
declare(strict_types=1);

/**
 * Assignment management: teachers set homework/classwork for a class and subject; pupils hand it in online
 * (text and/or a photo/PDF) or on paper; teachers mark it with a score and feedback; parents follow it in the portal.
 */
class AssignmentController extends Controller
{
    // ---------------------------------------------------------------- teachers

    public function index(): void
    {
        $f = ['class' => (int) ($_GET['class'] ?? 0), 'show' => (string) ($_GET['show'] ?? 'open')];
        $sql = 'SELECT a.*, c.name AS class_name, s.name AS subject_name, u.name AS teacher_name FROM assignments a
                JOIN classes c ON c.id = a.class_id LEFT JOIN class_subjects cs ON cs.id = a.class_subject_id
                LEFT JOIN subjects s ON s.id = cs.subject_id LEFT JOIN users u ON u.id = a.created_by WHERE 1=1';
        $p = [];
        if (Auth::classScope() !== null) {
            $sql .= ' AND (a.created_by = ? OR cs.teacher_id = ? OR c.class_teacher_id = ?)';
            array_push($p, Auth::id(), Auth::id(), Auth::id());
        }
        if ($f['class']) {
            $sql .= ' AND a.class_id = ?';
            $p[] = $f['class'];
        }
        if ($f['show'] === 'open') {
            $sql .= " AND a.status <> 'closed'";
        }
        [$rows, $pager] = paginate($sql . ' ORDER BY a.due_on DESC, a.id DESC', $p, 30);
        $counts = Assignments::counts(array_column($rows, 'id'));
        $rolls = [];
        foreach (array_unique(array_column($rows, 'class_id')) as $cid) {
            $rolls[(int) $cid] = count(Assignments::roster((int) $cid));
        }
        $classes = array_values(array_unique(array_map(fn($c) => $c['class_id'] . '|' . $c['class_name'], Assignments::choices())));
        $this->view('assignments/index', ['title' => 'Assignments', 'rows' => $rows, 'pager' => $pager, 'counts' => $counts, 'rolls' => $rolls, 'f' => $f, 'classes' => $classes]);
    }

    public function create(): void
    {
        $choices = Assignments::choices();
        if (!$choices) {
            flash('info', 'You have no class or subject to set work for. Subjects are given to teachers in Exams → Subject allocation.');
            redirect('assignments');
        }
        $this->view('assignments/form', ['title' => 'New assignment', 'a' => null, 'choices' => $choices]);
    }

    public function store(): void
    {
        $data = $this->input('assignments/create');
        [$file, $err] = Upload::store($_FILES['attachment'] ?? ['error' => UPLOAD_ERR_NO_FILE], 'assignments');
        if ($err) {
            with_old($_POST);
            flash('error', $err);
            redirect('assignments/create');
        }
        if ($file) {
            $data += ['attachment' => $file['stored_name'], 'attachment_name' => $file['original_name'], 'attachment_mime' => $file['mime']];
        }
        $id = db()->insert('assignments', $data + ['created_by' => Auth::id(), 'term_id' => current_term()['id'] ?? null]);
        audit('assignment_created', 'assignments', $id);
        if ($data['status'] === 'published') {
            $this->tellPupils($id);
        }
        flash('success', $data['status'] === 'published' ? 'Assignment set. Students and parents can see it now.' : 'Saved as a draft. Publish it when ready.');
        redirect('assignments/' . $id);
    }

    public function show(int $id): void
    {
        $a = $this->managed($id);
        $subs = [];
        foreach (db()->all('SELECT * FROM assignment_submissions WHERE assignment_id = ?', [$id]) as $s) {
            $subs[(int) $s['pupil_id']] = $s;
        }
        $roster = Assignments::roster((int) $a['class_id']);
        $handed = count(array_filter($subs, fn($s) => in_array($s['status'], ['submitted', 'late', 'marked', 'returned'], true)));
        $marked = array_values(array_filter(array_map(fn($s) => $s['score'] !== null ? (float) $s['score'] : null, $subs), fn($v) => $v !== null));
        $this->view('assignments/show', [
            'title' => $a['title'], 'a' => $a, 'roster' => $roster, 'subs' => $subs, 'handed' => $handed,
            'avg' => $marked ? round(array_sum($marked) / count($marked), 1) : null, 'nMarked' => count($marked),
        ]);
    }

    public function edit(int $id): void
    {
        $a = $this->managed($id);
        $this->view('assignments/form', ['title' => 'Edit assignment', 'a' => $a, 'choices' => Assignments::choices()]);
    }

    public function update(int $id): void
    {
        $a = $this->managed($id);
        $data = $this->input('assignments/' . $id . '/edit');
        [$file, $err] = Upload::store($_FILES['attachment'] ?? ['error' => UPLOAD_ERR_NO_FILE], 'assignments');
        if ($err) {
            flash('error', $err);
            redirect('assignments/' . $id . '/edit');
        }
        if ($file) {
            $data += ['attachment' => $file['stored_name'], 'attachment_name' => $file['original_name'], 'attachment_mime' => $file['mime']];
        }
        if ($a['status'] !== 'draft' && $data['status'] === 'draft') {
            $data['status'] = $a['status']; // already seen by pupils: cannot go back to draft
        }
        db()->update('assignments', $data, 'id = ?', [$id]);
        audit('assignment_updated', 'assignments', $id);
        if ($a['status'] === 'draft' && $data['status'] === 'published') {
            $this->tellPupils($id);
        }
        flash('success', 'Assignment updated.');
        redirect('assignments/' . $id);
    }

    /** Open / close / delete (delete only while nobody has handed in). */
    public function status(int $id): void
    {
        $a = $this->managed($id);
        $to = (string) ($_POST['to'] ?? '');
        if ($to === 'delete') {
            if ((int) db()->value("SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = ? AND status <> 'missing'", [$id])) {
                flash('error', 'Students have already handed this in. Close it instead of deleting it.');
                redirect('assignments/' . $id);
            }
            db()->run('DELETE FROM assignments WHERE id = ?', [$id]);
            audit('assignment_deleted', 'assignments', $id, ['title' => $a['title']]);
            flash('success', 'Assignment deleted.');
            redirect('assignments');
        }
        if (!in_array($to, ['published', 'closed'], true)) {
            redirect('assignments/' . $id);
        }
        db()->update('assignments', ['status' => $to], 'id = ?', [$id]);
        if ($to === 'closed') {
            // Anyone who has not handed in by closing is recorded as "not handed in"
            foreach (Assignments::roster((int) $a['class_id']) as $p) {
                db()->run("INSERT IGNORE INTO assignment_submissions (assignment_id, pupil_id, status) VALUES (?, ?, 'missing')", [$id, $p['id']]);
            }
        }
        if ($a['status'] === 'draft' && $to === 'published') {
            $this->tellPupils($id);
        }
        audit('assignment_' . $to, 'assignments', $id);
        flash('success', $to === 'closed' ? 'Assignment closed. Students who did not hand in are marked "Not handed in".' : 'Assignment open for students.');
        redirect('assignments/' . $id);
    }

    /** Teacher records a hand-in on paper, a score and feedback for one pupil. */
    public function mark(int $id, int $pupil): void
    {
        $a = $this->managed($id);
        if (!in_array($pupil, array_map('intval', array_column(Assignments::roster((int) $a['class_id']), 'id')), true)) {
            $this->notFound();
        }
        $raw = trim(str_replace(',', '.', (string) ($_POST['score'] ?? '')));
        $score = $raw === '' ? null : (is_numeric($raw) ? round((float) $raw, 2) : -1.0);
        if ($score !== null && ($score < 0 || ($a['max_score'] !== null && $score > (float) $a['max_score']))) {
            flash('error', 'The score must be between 0 and ' . ($a['max_score'] !== null ? Results::fmt((float) $a['max_score']) : 'the maximum') . '.');
            redirect('assignments/' . $id . '#p' . $pupil);
        }
        $feedback = mb_substr(trim((string) ($_POST['feedback'] ?? '')), 0, 1000) ?: null;
        $ex = db()->one('SELECT * FROM assignment_submissions WHERE assignment_id = ? AND pupil_id = ?', [$id, $pupil]);
        $notIn = !$ex || $ex['status'] === 'missing';
        if (!empty($_POST['missing'])) {
            $status = 'missing';
        } elseif ($score !== null || $feedback !== null) {
            $status = !empty($_POST['return']) ? 'returned' : 'marked';
        } else {
            $status = $notIn ? (date('Y-m-d') > $a['due_on'] ? 'late' : 'submitted') : $ex['status'];
        }
        $row = ['status' => $status, 'score' => $status === 'missing' ? null : $score, 'feedback' => $feedback];
        if ($status !== 'missing' && $notIn) {
            $row += ['on_paper' => 1, 'submitted_at' => date('Y-m-d H:i:s')];
        }
        if (in_array($status, ['marked', 'returned'], true)) {
            $row += ['marked_by' => Auth::id(), 'marked_at' => date('Y-m-d H:i:s')];
        }
        if ($ex) {
            db()->update('assignment_submissions', $row, 'id = ?', [$ex['id']]);
        } else {
            db()->insert('assignment_submissions', $row + ['assignment_id' => $id, 'pupil_id' => $pupil]);
        }
        if ($status === 'returned' && ($u = db()->value('SELECT user_id FROM pupils WHERE id = ?', [$pupil]))) {
            Notifier::inapp([(int) $u], 'Your work was marked: ' . $a['title'], $score !== null ? 'Score ' . Results::fmt($score) . ($a['max_score'] !== null ? ' / ' . Results::fmt((float) $a['max_score']) : '') : null, 'homework/' . $id);
        }
        audit('assignment_marked', 'assignments', $id, ['pupil' => $pupil, 'status' => $status, 'score' => $score]);
        flash('success', 'Saved.');
        redirect('assignments/' . $id . '#p' . $pupil);
    }

    // ---------------------------------------------------------------- pupils

    public function homework(): void
    {
        $p = $this->pupil();
        $this->view('assignments/homework', ['title' => 'My homework', 'p' => $p, 'rows' => Assignments::forPupil((int) $p['id'])]);
    }

    public function homeworkShow(int $id): void
    {
        $p = $this->pupil();
        $a = Assignments::find($id);
        if (!$a || $a['status'] === 'draft' || (int) $a['class_id'] !== (int) $p['class_id']) {
            $this->notFound();
        }
        $sub = db()->one('SELECT * FROM assignment_submissions WHERE assignment_id = ? AND pupil_id = ?', [$id, $p['id']]);
        $this->view('assignments/homework_show', ['title' => $a['title'], 'a' => $a, 'sub' => $sub, 'p' => $p]);
    }

    public function submit(int $id): void
    {
        $p = $this->pupil();
        $a = Assignments::find($id);
        if (!$a || $a['status'] === 'draft' || (int) $a['class_id'] !== (int) $p['class_id']) {
            $this->notFound();
        }
        $sub = db()->one('SELECT * FROM assignment_submissions WHERE assignment_id = ? AND pupil_id = ?', [$id, $p['id']]);
        $err = null;
        if ($a['status'] === 'closed') {
            $err = 'This assignment is closed. Speak to your teacher.';
        } elseif (!(int) $a['submit_online']) {
            $err = 'Your teacher wants this handed in on paper.';
        } elseif ($sub && in_array($sub['status'], ['marked', 'returned'], true)) {
            $err = 'Your teacher has already marked this.';
        }
        $body = mb_substr(trim((string) ($_POST['body'] ?? '')), 0, 20000);
        [$file, $ferr] = $err ? [null, null] : Upload::store($_FILES['file'] ?? ['error' => UPLOAD_ERR_NO_FILE], 'homework');
        if (!$err && $ferr) {
            $err = $ferr;
        }
        if (!$err && $body === '' && !$file && !($sub['file'] ?? null)) {
            $err = 'Type your answer or attach a photo or PDF of your work.';
        }
        if ($err) {
            flash('error', $err);
            redirect('homework/' . $id);
        }
        $row = ['body' => $body ?: null, 'status' => date('Y-m-d') > $a['due_on'] ? 'late' : 'submitted', 'submitted_at' => date('Y-m-d H:i:s'), 'on_paper' => 0];
        if ($file) {
            $row += ['file' => $file['stored_name'], 'file_name' => $file['original_name'], 'file_mime' => $file['mime']];
        }
        if ($sub) {
            db()->update('assignment_submissions', $row, 'id = ?', [$sub['id']]);
        } else {
            db()->insert('assignment_submissions', $row + ['assignment_id' => $id, 'pupil_id' => $p['id']]);
        }
        audit('assignment_submitted', 'assignments', $id, ['pupil' => $p['id']]);
        flash('success', $row['status'] === 'late' ? 'Handed in (after the due date).' : 'Handed in. You can change it until your teacher marks it.');
        redirect('homework/' . $id);
    }

    // ---------------------------------------------------------------- files (teacher, pupil of the class, parent of a pupil in the class)

    public function file(int $id): void
    {
        $a = Assignments::find($id) ?? $this->notFound();
        if (!$a['attachment'] || !$this->canSeeAssignment($a)) {
            $this->notFound();
        }
        Upload::send($a['attachment'], $a['attachment_mime'], $a['attachment_name']);
    }

    public function subFile(int $id, int $sub): void
    {
        $s = db()->one('SELECT s.*, a.class_id, a.created_by, a.class_subject_id FROM assignment_submissions s JOIN assignments a ON a.id = s.assignment_id WHERE s.id = ? AND s.assignment_id = ?', [$sub, $id]) ?? $this->notFound();
        $ok = (can('assignments.manage') && Assignments::canManage(['created_by' => $s['created_by'], 'class_subject_id' => $s['class_subject_id']]))
            || (Auth::role() === 'pupil' && (int) (Accounts::pupilOf((int) Auth::id())['id'] ?? 0) === (int) $s['pupil_id'])
            || (Auth::role() === 'parent' && in_array((int) $s['pupil_id'], array_map('intval', array_column(Accounts::childrenOf((int) Auth::id()), 'id')), true));
        if (!$s['file'] || !$ok) {
            $this->notFound();
        }
        Upload::send($s['file'], $s['file_mime'], $s['file_name']);
    }

    // ---------------------------------------------------------------- helpers

    private function canSeeAssignment(array $a): bool
    {
        if (Assignments::canManage($a)) {
            return true;
        }
        if ($a['status'] === 'draft') {
            return false;
        }
        if (Auth::role() === 'pupil') {
            return (int) (Accounts::pupilOf((int) Auth::id())['class_id'] ?? 0) === (int) $a['class_id'];
        }
        if (Auth::role() === 'parent') {
            return in_array((int) $a['class_id'], array_map('intval', array_column(Accounts::childrenOf((int) Auth::id()), 'class_id')), true);
        }
        return can('assignments.manage') && Auth::classScope() === null;
    }

    private function managed(int $id): array
    {
        $a = Assignments::find($id) ?? $this->notFound();
        if (!Assignments::canManage($a)) {
            $this->forbidden();
        }
        return $a;
    }

    private function pupil(): array
    {
        if (Auth::role() !== 'pupil') {
            flash('info', '"My homework" is the page students see. Teachers set work under Assignments.');
            redirect(can('assignments.manage') ? 'assignments' : (Auth::role() === 'parent' ? 'portal' : 'dashboard'));
        }
        $p = Accounts::pupilOf((int) Auth::id());
        if (!$p || !$p['class_id']) {
            flash('error', 'Your account is not linked to a class this year. Tell your class teacher.');
            redirect('learn');
        }
        return $p;
    }

    private function input(string $back): array
    {
        $in = $this->only(['class_subject_id', 'title', 'instructions', 'set_on', 'due_on', 'max_score']);
        $cs = null;
        foreach (Assignments::choices() as $c) {
            if ((int) $c['id'] === (int) $in['class_subject_id']) {
                $cs = $c;
            }
        }
        $max = trim((string) ($in['max_score'] ?? ''));
        $setOn = $in['set_on'] ?: date('Y-m-d');
        $err = null;
        if (!$cs) {
            $err = 'Choose the class and subject.';
        } elseif (!$in['title']) {
            $err = 'Give the assignment a title.';
        } elseif (!Validator::isDate((string) $setOn) || !$in['due_on'] || !Validator::isDate((string) $in['due_on'])) {
            $err = 'Enter valid dates.';
        } elseif ($in['due_on'] < $setOn) {
            $err = 'The due date is before the date it is set.';
        } elseif ($max !== '' && (!is_numeric($max) || (float) $max <= 0 || (float) $max > 1000)) {
            $err = 'The maximum score must be a number above 0 (or left empty).';
        }
        if ($err) {
            with_old($_POST);
            flash('error', $err);
            redirect($back);
        }
        return [
            'class_id' => (int) $cs['class_id'], 'class_subject_id' => (int) $cs['id'], 'title' => mb_substr($in['title'], 0, 150),
            'instructions' => $in['instructions'] ? mb_substr($in['instructions'], 0, 10000) : null, 'set_on' => $setOn, 'due_on' => $in['due_on'],
            'max_score' => $max === '' ? null : round((float) $max, 2), 'submit_online' => empty($_POST['on_paper']) ? 1 : 0,
            'status' => ($_POST['status'] ?? '') === 'draft' ? 'draft' : 'published',
        ];
    }

    /** In-app notice to pupils of the class who have a login. */
    private function tellPupils(int $id): void
    {
        $a = Assignments::find($id);
        $users = array_filter(array_map(fn($p) => (int) $p['user_id'], Assignments::roster((int) $a['class_id'])));
        if ($users) {
            Notifier::inapp(array_values($users), 'New homework: ' . $a['title'], ($a['subject_name'] ? $a['subject_name'] . ' · ' : '') . 'due ' . date('D j M', strtotime($a['due_on'])), 'homework/' . $id);
        }
    }
}
