<?php
declare(strict_types=1);

/** Exam timetable: papers per class (or a whole level at once), rooms, invigilators, clash checks, print and publish. */
class ExamTimetableController extends Controller
{
    public function index(): void
    {
        $manage = can('examtt.manage');
        $series = db()->all(
            'SELECT es.*, t.name AS term_name, y.name AS year_name, (SELECT COUNT(*) FROM exam_papers ep WHERE ep.series_id = es.id) AS papers
             FROM exam_series es JOIN terms t ON t.id = es.term_id JOIN academic_years y ON y.id = t.academic_year_id' .
            ($manage ? '' : " WHERE es.status = 'published'") . ' ORDER BY es.start_date DESC'
        );
        $this->view('examtt/index', [
            'title' => 'Exam timetable', 'series' => $series, 'manage' => $manage, 'term' => current_term(),
            'terms' => db()->all('SELECT t.id, t.name, y.name AS year_name FROM terms t JOIN academic_years y ON y.id = t.academic_year_id ORDER BY t.start_date DESC'),
        ]);
    }

    public function store(): void
    {
        $data = $this->seriesInput('exam-timetable');
        $id = db()->insert('exam_series', $data + ['created_by' => Auth::id()]);
        audit('exam_series_created', 'exam_series', $id);
        flash('success', 'Exam timetable created. Now add the papers.');
        redirect('exam-timetable/' . $id);
    }

    public function show(int $id): void
    {
        $s = $this->seriesOr404($id);
        $f = ['class' => (int) ($_GET['class'] ?? 0)];
        $papers = ExamTimetable::papers($id, $f);
        $manage = can('examtt.manage');
        $this->view('examtt/show', [
            'title' => $s['name'], 's' => $s, 'papers' => $papers, 'f' => $f, 'manage' => $manage,
            'clashes' => $manage ? ExamTimetable::clashes($s, $f['class'] ? ExamTimetable::papers($id) : $papers) : [],
            'classes' => db()->all('SELECT c.id, c.name, c.level_id FROM classes c JOIN levels l ON l.id = c.level_id WHERE c.is_active = 1 ORDER BY l.sort_order, c.name'),
            'levels' => db()->all('SELECT l.id, l.name FROM levels l WHERE EXISTS (SELECT 1 FROM classes c WHERE c.level_id = l.id AND c.is_active = 1) ORDER BY l.sort_order'),
            'subjects' => db()->all('SELECT id, name FROM subjects WHERE is_active = 1 ORDER BY name'),
            'rooms' => Resources::rooms(), 'staff' => $this->staff(),
            'plans' => db()->all("SELECT id, title, (SELECT COUNT(*) FROM seating_seats ss WHERE ss.plan_id = sp.id) AS seated FROM seating_plans sp WHERE kind = 'exam' AND series_id = ? ORDER BY title", [$id]),
        ]);
    }

    public function update(int $id): void
    {
        $this->seriesOr404($id);
        db()->update('exam_series', $this->seriesInput('exam-timetable/' . $id), 'id = ?', [$id]);
        audit('exam_series_updated', 'exam_series', $id);
        flash('success', 'Saved.' . $this->refreshNotice($id));
        redirect('exam-timetable/' . $id);
    }

    public function destroy(int $id): void
    {
        $s = $this->seriesOr404($id);
        if ($s['status'] === 'published') {
            flash('error', 'Withdraw the published timetable first.');
            redirect('exam-timetable/' . $id);
        }
        db()->run('DELETE FROM exam_series WHERE id = ?', [$id]);
        audit('exam_series_deleted', 'exam_series', $id, ['name' => $s['name']]);
        flash('success', 'Exam timetable deleted.');
        redirect('exam-timetable');
    }

    public function addPaper(int $id): void
    {
        $s = $this->seriesOr404($id);
        $in = $this->paperInput('exam-timetable/' . $id);
        $target = (string) ($_POST['target'] ?? '');
        $classIds = [];
        if (preg_match('/^class:(\d+)$/', $target, $m)) {
            $classIds = [(int) $m[1]];
        } elseif (preg_match('/^level:(\d+)$/', $target, $m)) {
            $classIds = array_map('intval', array_column(db()->all('SELECT id FROM classes WHERE level_id = ? AND is_active = 1', [(int) $m[1]]), 'id'));
        }
        $classIds = array_values(array_filter($classIds, fn($c) => (bool) db()->value('SELECT 1 FROM classes WHERE id = ? AND is_active = 1', [$c])));
        if (!$classIds) {
            with_old($_POST);
            flash('error', 'Choose the class or level sitting the paper.');
            redirect('exam-timetable/' . $id);
        }
        $n = 0;
        $added = [];
        db()->transaction(function (Database $d) use ($classIds, $in, $id, &$n, &$added) {
            foreach ($classIds as $cid) {
                $room = $in['room_id'] ?? null;
                if (!$room) {
                    $room = $d->value("SELECT id FROM rooms WHERE class_id = ? AND is_active = 1 ORDER BY kind = 'classroom' DESC LIMIT 1", [$cid]) ?: null;
                }
                $d->insert('exam_papers', ['class_id' => $cid, 'series_id' => $id] + array_merge($in, ['room_id' => $room]));
                $added[] = $cid;
                $n++;
            }
        });
        audit('exam_papers_added', 'exam_series', $id, ['count' => $n]);
        $note = $this->refreshNotice($id, !empty($in['invigilator_id']) ? (int) $in['invigilator_id'] : null);
        $noSubject = array_column(db()->all(
            'SELECT c.name FROM classes c WHERE c.id IN (' . implode(',', array_map('intval', $added ?: [0])) . ') AND NOT EXISTS (SELECT 1 FROM class_subjects cs WHERE cs.class_id = c.id AND cs.subject_id = ?) ORDER BY c.name',
            [(int) $in['subject_id']]
        ), 'name');
        flash('success', ($n === 1 ? 'Paper added.' : "$n papers added (one per class).") . $note);
        if ($noSubject) {
            flash('warning', 'Check: ' . implode(', ', $noSubject) . ' do not take this subject (Subject Management → Subject teachers).');
        }
        redirect('exam-timetable/' . $id);
    }

    public function editPaper(int $id): void
    {
        $p = db()->one('SELECT ep.*, c.name AS class_name FROM exam_papers ep JOIN classes c ON c.id = ep.class_id WHERE ep.id = ?', [$id]) ?? $this->notFound();
        $s = $this->seriesOr404((int) $p['series_id']);
        $this->view('examtt/paper', [
            'title' => 'Edit paper', 'p' => $p, 's' => $s, 'subjects' => db()->all('SELECT id, name FROM subjects WHERE is_active = 1 ORDER BY name'),
            'rooms' => Resources::rooms(), 'staff' => $this->staff(),
        ]);
    }

    public function updatePaper(int $id): void
    {
        $p = db()->one('SELECT * FROM exam_papers WHERE id = ?', [$id]) ?? $this->notFound();
        $in = $this->paperInput('exam-timetable/papers/' . $id . '/edit');
        db()->update('exam_papers', $in, 'id = ?', [$id]);
        audit('exam_paper_updated', 'exam_papers', $id);
        $newInvig = ($in['invigilator_id'] ?? null) && (int) $in['invigilator_id'] !== (int) $p['invigilator_id'] ? (int) $in['invigilator_id'] : null;
        flash('success', 'Paper saved.' . $this->refreshNotice((int) $p['series_id'], $newInvig));
        redirect('exam-timetable/' . $p['series_id']);
    }

    public function destroyPaper(int $id): void
    {
        $p = db()->one('SELECT * FROM exam_papers WHERE id = ?', [$id]) ?? $this->notFound();
        db()->run('DELETE FROM exam_papers WHERE id = ?', [$id]);
        audit('exam_paper_deleted', 'exam_papers', $id);
        flash('success', 'Paper removed.' . $this->refreshNotice((int) $p['series_id']));
        redirect('exam-timetable/' . $p['series_id']);
    }

    public function printView(int $id): void
    {
        $s = $this->seriesOr404($id);
        $mode = (string) ($_GET['by'] ?? 'class');
        $papers = ExamTimetable::papers($id);
        $groups = [];
        foreach ($papers as $p) {
            if ($mode === 'invigilator') {
                $groups[$p['invigilator_name'] ?? 'No invigilator yet'][] = $p;
            } elseif ($mode === 'all') {
                $groups['All classes'][] = $p;
            } else {
                $groups[$p['class_name']][] = $p;
            }
        }
        if ($mode === 'invigilator') {
            ksort($groups);
        }
        $this->view('examtt/print', ['title' => $s['name'], 's' => $s, 'groups' => $groups, 'mode' => $mode], 'layouts/print');
    }

    public function publish(int $id): void
    {
        $s = $this->seriesOr404($id);
        $papers = ExamTimetable::papers($id);
        if (!$papers) {
            flash('error', 'Add papers before publishing.');
            redirect('exam-timetable/' . $id);
        }
        $bad = array_filter(ExamTimetable::clashes($s, $papers), fn($c) => $c[0] === 'bad');
        if ($bad && empty($_POST['ignore_clashes'])) {
            flash('error', 'There are ' . count($bad) . ' clash(es) to fix first (or tick "publish anyway").');
            redirect('exam-timetable/' . $id);
        }
        $notice = [
            'title' => mb_substr($s['name'] . ' timetable', 0, 150), 'body' => mb_substr(ExamTimetable::noticeBody($s, $papers), 0, 20000),
            'category' => 'event', 'audience' => 'all', 'publish_at' => date('Y-m-d H:i:s'), 'expires_on' => $s['end_date'], 'is_pinned' => 1,
        ];
        if ($s['notice_id'] && db()->value('SELECT 1 FROM notices WHERE id = ?', [$s['notice_id']])) {
            db()->update('notices', $notice, 'id = ?', [$s['notice_id']]);
            $nid = (int) $s['notice_id'];
        } else {
            $nid = db()->insert('notices', $notice + ['created_by' => Auth::id()]);
        }
        db()->update('exam_series', ['status' => 'published', 'published_at' => date('Y-m-d H:i:s'), 'notice_id' => $nid], 'id = ?', [$id]);
        $invig = array_unique(array_filter(array_map(fn($p) => (int) $p['invigilator_id'], $papers)));
        Notifier::inapp($invig, 'Invigilation duties: ' . $s['name'], 'Your invigilation duties are on your teacher profile.', 'teachers/me');
        audit('exam_series_published', 'exam_series', $id, ['notice' => $nid]);
        flash('success', 'Published. It is pinned on the notice board for staff, parents and students, and invigilators have been told.');
        redirect('exam-timetable/' . $id);
    }

    public function unpublish(int $id): void
    {
        $s = $this->seriesOr404($id);
        if ($s['notice_id']) {
            db()->run('DELETE FROM notices WHERE id = ?', [$s['notice_id']]);
        }
        db()->update('exam_series', ['status' => 'draft', 'notice_id' => null, 'published_at' => null], 'id = ?', [$id]);
        audit('exam_series_withdrawn', 'exam_series', $id);
        flash('success', 'Withdrawn: back to draft and removed from the notice board.');
        redirect('exam-timetable/' . $id);
    }

    // ------------------------------------------------------------------ helpers

    /** A published timetable keeps its notice-board copy up to date; a newly given invigilator is told. */
    private function refreshNotice(int $seriesId, ?int $newInvigilator = null): string
    {
        $s = ExamTimetable::series($seriesId);
        if (!$s || $s['status'] !== 'published') {
            return '';
        }
        $papers = ExamTimetable::papers($seriesId);
        if ($s['notice_id'] && db()->value('SELECT 1 FROM notices WHERE id = ?', [$s['notice_id']])) {
            db()->update('notices', ['title' => mb_substr($s['name'] . ' timetable', 0, 150), 'body' => mb_substr(ExamTimetable::noticeBody($s, $papers), 0, 20000), 'expires_on' => $s['end_date']], 'id = ?', [$s['notice_id']]);
        }
        if ($newInvigilator) {
            Notifier::inapp([$newInvigilator], 'Invigilation duty: ' . $s['name'], 'You have a new or changed invigilation duty. See your teacher profile.', 'teachers/me');
        }
        return ' The notice board copy was updated' . ($newInvigilator ? ' and the invigilator told.' : '.');
    }

    private function seriesOr404(int $id): array
    {
        $s = ExamTimetable::series($id) ?? $this->notFound();
        if ($s['status'] !== 'published' && !can('examtt.manage')) {
            $this->notFound();
        }
        return $s;
    }

    private function seriesInput(string $back): array
    {
        $in = $this->only(['term_id', 'name', 'start_date', 'end_date', 'instructions']);
        $err = null;
        if (!$in['name']) {
            $err = 'Give the exam a name, e.g. "End of Term 1 Examination".';
        } elseif (!db()->value('SELECT 1 FROM terms WHERE id = ?', [(int) $in['term_id']])) {
            $err = 'Choose the term.';
        } elseif (!Validator::isDate((string) $in['start_date']) || !Validator::isDate((string) $in['end_date']) || $in['end_date'] < $in['start_date']) {
            $err = 'Enter the first and last exam days (the last on or after the first).';
        }
        if ($err) {
            with_old($_POST);
            flash('error', $err);
            redirect($back);
        }
        return ['term_id' => (int) $in['term_id'], 'name' => mb_substr($in['name'], 0, 120), 'start_date' => $in['start_date'], 'end_date' => $in['end_date'], 'instructions' => $in['instructions'] ? mb_substr($in['instructions'], 0, 3000) : null];
    }

    private function paperInput(string $back): array
    {
        $in = $this->only(['subject_id', 'paper', 'exam_date', 'start_time', 'duration_min', 'room_id', 'invigilator_id', 'notes']);
        $err = null;
        if (!db()->value('SELECT 1 FROM subjects WHERE id = ?', [(int) $in['subject_id']])) {
            $err = 'Choose the subject.';
        } elseif (!Validator::isDate((string) $in['exam_date'])) {
            $err = 'Enter the exam date.';
        } elseif (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', substr((string) $in['start_time'], 0, 5))) {
            $err = 'Enter the start time.';
        } elseif ((int) $in['duration_min'] < 10 || (int) $in['duration_min'] > 300) {
            $err = 'Duration must be between 10 and 300 minutes.';
        } elseif ($in['room_id'] && !db()->value('SELECT 1 FROM rooms WHERE id = ?', [(int) $in['room_id']])) {
            $err = 'Choose a valid room.';
        } elseif ($in['invigilator_id'] && !db()->value("SELECT 1 FROM users WHERE id = ? AND is_active = 1 AND role NOT IN ('parent','pupil')", [(int) $in['invigilator_id']])) {
            $err = 'Choose a valid invigilator.';
        }
        if ($err) {
            with_old($_POST);
            flash('error', $err);
            redirect($back);
        }
        return [
            'subject_id' => (int) $in['subject_id'], 'paper' => $in['paper'] ? mb_substr($in['paper'], 0, 40) : null, 'exam_date' => $in['exam_date'],
            'start_time' => substr((string) $in['start_time'], 0, 5) . ':00', 'duration_min' => (int) $in['duration_min'],
            'room_id' => $in['room_id'] ? (int) $in['room_id'] : null, 'invigilator_id' => $in['invigilator_id'] ? (int) $in['invigilator_id'] : null,
            'notes' => $in['notes'] ? mb_substr($in['notes'], 0, 200) : null,
        ];
    }

    private function staff(): array
    {
        return db()->all("SELECT id, name FROM users WHERE is_active = 1 AND role NOT IN ('parent','pupil') ORDER BY name");
    }
}
