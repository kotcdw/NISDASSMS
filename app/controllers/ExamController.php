<?php
declare(strict_types=1);

/** Exams & assessments: score sheets, approval workflow, grading setup, subject allocations. */
class ExamController extends Controller
{
    public const SHEET_STATUS = ['draft' => ['Draft', 'warn'], 'submitted' => ['Submitted', 'info'], 'approved' => ['Approved', 'good']];

    public function index(): void
    {
        [$term, $terms] = $this->termParam();
        $classId = (int) ($_GET['class'] ?? 0);
        $sql = "SELECT cs.*, s.name AS subject_name, s.code, c.name AS class_name, c.class_teacher_id, l.category, l.sort_order,
                       u.name AS teacher_name, ss.status, ss.return_note,
                       (SELECT COUNT(DISTINCT sc.pupil_id) FROM assessment_scores sc JOIN assessments a ON a.id = sc.assessment_id
                        WHERE a.class_subject_id = cs.id AND a.term_id = ? AND a.kind = 'exam' AND (sc.score IS NOT NULL OR sc.is_absent = 1)) AS exam_entered,
                       (SELECT COUNT(*) FROM enrolments e WHERE e.class_id = cs.class_id AND e.academic_year_id = ? AND e.status IN ('active','promoted','repeated')) AS pupils
                FROM class_subjects cs JOIN subjects s ON s.id = cs.subject_id JOIN classes c ON c.id = cs.class_id JOIN levels l ON l.id = c.level_id
                LEFT JOIN users u ON u.id = cs.teacher_id
                LEFT JOIN score_sheets ss ON ss.class_subject_id = cs.id AND ss.term_id = ?
                WHERE c.is_active = 1";
        $p = [$term['id'] ?? 0, $term['academic_year_id'] ?? 0, $term['id'] ?? 0];
        if (!can('exams.manage')) {
            $sql .= ' AND (cs.teacher_id = ? OR c.class_teacher_id = ?)';
            array_push($p, Auth::id(), Auth::id());
        }
        if ($classId) {
            $sql .= ' AND c.id = ?';
            $p[] = $classId;
        }
        $rows = $term ? db()->all($sql . ' ORDER BY l.sort_order, c.name, s.is_core DESC, s.name', $p) : [];
        $counts = ['draft' => 0, 'submitted' => 0, 'approved' => 0];
        foreach ($rows as $r) {
            $counts[$r['status'] ?? 'draft']++;
        }
        $this->view('exams/index', [
            'title' => 'Exams & Assessments', 'term' => $term, 'terms' => $terms, 'rows' => $rows, 'counts' => $counts, 'classId' => $classId,
            'classes' => db()->all('SELECT c.id, c.name FROM classes c JOIN levels l ON l.id = c.level_id WHERE c.is_active = 1 ORDER BY l.sort_order, c.name'),
        ]);
    }

    public function sheet(int $csId): void
    {
        [$term, $terms] = $this->termParam();
        $cs = $this->cs($csId);
        if (!$term) {
            flash('error', 'Set up academic terms first.');
            redirect('exams');
        }
        $sheet = Results::ensureSheet((int) $term['id'], $cs, $cs['category']);
        $comps = db()->all('SELECT * FROM assessments WHERE term_id = ? AND class_subject_id = ? ORDER BY kind = \'exam\', sort_order, id', [$term['id'], $csId]);
        $pupils = Results::roster((int) $cs['class_id'], (int) $term['id']);
        $scores = [];
        if ($comps) {
            $in = implode(',', array_fill(0, count($comps), '?'));
            foreach (db()->all("SELECT * FROM assessment_scores WHERE assessment_id IN ($in)", array_column($comps, 'id')) as $s) {
                $scores[$s['pupil_id']][$s['assessment_id']] = $s;
            }
        }
        $scheme = Results::scheme($cs['category'], (int) $term['id']);
        $computed = Results::subjectScores((int) $term['id'], $csId, array_map(fn($p) => (int) $p['id'], $pupils), $scheme);
        $ranks = Results::rank(array_map(fn($r) => $r['total'], $computed));
        $this->view('exams/sheet', [
            'title' => $cs['subject_name'] . ' — ' . $cs['class_name'], 'cs' => $cs, 'term' => $term, 'terms' => $terms, 'sheet' => $sheet,
            'comps' => $comps, 'pupils' => $pupils, 'scores' => $scores, 'computed' => $computed, 'ranks' => $ranks, 'scheme' => $scheme,
            'locked' => $this->locked($sheet), 'released' => Results::released((int) $cs['class_id'], (int) $term['id']),
        ]);
    }

    public function saveScores(int $csId): void
    {
        [$cs, $term, $sheet] = $this->editable($csId);
        $comps = db()->all('SELECT * FROM assessments WHERE term_id = ? AND class_subject_id = ?', [$term['id'], $csId]);
        $roster = array_flip(array_map(fn($p) => (int) $p['id'], Results::roster((int) $cs['class_id'], (int) $term['id'])));
        $input = (array) ($_POST['s'] ?? []);
        $errors = 0;
        db()->transaction(function (Database $d) use ($comps, $roster, $input, &$errors) {
            foreach ($comps as $c) {
                foreach ((array) ($input[$c['id']] ?? []) as $pid => $raw) {
                    $pid = (int) $pid;
                    if (!isset($roster[$pid])) {
                        continue;
                    }
                    [$score, $absent, $ok] = self::parseScore((string) $raw, (float) $c['max_mark']);
                    if (!$ok) {
                        $errors++;
                        continue;
                    }
                    $d->run(
                        'INSERT INTO assessment_scores (assessment_id, pupil_id, score, is_absent, updated_by) VALUES (?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE score = VALUES(score), is_absent = VALUES(is_absent), updated_by = VALUES(updated_by)',
                        [$c['id'], $pid, $score, $absent, Auth::id()]
                    );
                }
            }
        });
        audit('scores_saved', 'class_subjects', $csId, ['term' => $term['id']]);
        if ($errors) {
            flash('warning', "Saved, but $errors value(s) were skipped: scores must be numbers between 0 and the maximum (or “a” for absent).");
        } else {
            flash('success', 'Scores saved.');
        }
        redirect('exams/sheet/' . $csId, ['term' => $term['id']]);
    }

    /** Paste a block copied from Excel: admission no. followed by one column per component. */
    public function paste(int $csId): void
    {
        [$cs, $term] = $this->editable($csId);
        $comps = db()->all('SELECT * FROM assessments WHERE term_id = ? AND class_subject_id = ? ORDER BY kind = \'exam\', sort_order, id', [$term['id'], $csId]);
        $byAdm = [];
        foreach (Results::roster((int) $cs['class_id'], (int) $term['id']) as $p) {
            $byAdm[strtoupper($p['admission_no'])] = (int) $p['id'];
        }
        $done = 0;
        $skipped = [];
        $bad = 0;
        db()->transaction(function (Database $d) use ($comps, $byAdm, &$done, &$skipped, &$bad) {
            foreach (preg_split('/\R/', (string) ($_POST['data'] ?? '')) as $line) {
                // Excel copies with tabs; only without tabs fall back to ; or , (so "12,5" stays one decimal score)
                $line = trim($line);
                $cols = str_contains($line, "\t") ? explode("\t", $line) : preg_split(str_contains($line, ';') ? '/;/' : '/,/', $line);
                if (!$cols || trim($cols[0]) === '') {
                    continue;
                }
                $pid = $byAdm[strtoupper(trim($cols[0]))] ?? null;
                if (!$pid) {
                    $skipped[] = trim($cols[0]);
                    continue;
                }
                foreach ($comps as $i => $c) {
                    if (!array_key_exists($i + 1, $cols) || trim($cols[$i + 1]) === '') {
                        continue;
                    }
                    [$score, $absent, $ok] = self::parseScore($cols[$i + 1], (float) $c['max_mark']);
                    if (!$ok) {
                        $bad++;
                    } else {
                        $d->run(
                            'INSERT INTO assessment_scores (assessment_id, pupil_id, score, is_absent, updated_by) VALUES (?, ?, ?, ?, ?)
                             ON DUPLICATE KEY UPDATE score = VALUES(score), is_absent = VALUES(is_absent), updated_by = VALUES(updated_by)',
                            [$c['id'], $pid, $score, $absent, Auth::id()]
                        );
                    }
                }
                $done++;
            }
        });
        audit('scores_pasted', 'class_subjects', $csId, ['rows' => $done]);
        $skippedNote = '';
        if ($skipped) {
            $skippedNote = ' Not found: ' . implode(', ', array_slice($skipped, 0, 5)) . (count($skipped) > 5 ? '…' : '');
        }
        $badNote = $bad ? " $bad value(s) were skipped: scores must be numbers between 0 and the maximum (or “a” for absent)." : '';
        flash($skipped || $bad ? 'warning' : 'success', "Imported $done row(s)." . $skippedNote . $badNote);
        redirect('exams/sheet/' . $csId, ['term' => $term['id']]);
    }

    public function addAssessment(int $csId): void
    {
        [$cs, $term] = $this->editable($csId);
        $title = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 80);
        $max = (float) ($_POST['max_mark'] ?? 0);
        $kind = ($_POST['kind'] ?? '') === 'exam' ? 'exam' : 'class';
        if ($title === '' || $max <= 0 || $max > 1000) {
            flash('error', 'Give the item a name and a maximum mark between 1 and 1000.');
            redirect('exams/sheet/' . $csId, ['term' => $term['id']]);
        }
        db()->insert('assessments', [
            'term_id' => $term['id'], 'class_subject_id' => $csId, 'kind' => $kind, 'title' => $title, 'max_mark' => $max,
            'sort_order' => (int) db()->value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM assessments WHERE term_id = ? AND class_subject_id = ? AND kind = ?', [$term['id'], $csId, $kind]),
            'created_by' => Auth::id(),
        ]);
        flash('success', "“{$title}” added.");
        redirect('exams/sheet/' . $csId, ['term' => $term['id']]);
    }

    public function updateAssessment(int $csId, int $aid): void
    {
        [$cs, $term] = $this->editable($csId);
        $a = db()->one('SELECT * FROM assessments WHERE id = ? AND class_subject_id = ? AND term_id = ?', [$aid, $csId, $term['id']]) ?? $this->notFound();
        if (isset($_POST['delete'])) {
            db()->run('DELETE FROM assessments WHERE id = ?', [$aid]);
            audit('assessment_deleted', 'assessments', $aid, ['title' => $a['title']]);
            flash('success', "“{$a['title']}” and its scores were removed.");
        } else {
            $title = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 80);
            $max = (float) ($_POST['max_mark'] ?? 0);
            $highest = (float) db()->value('SELECT COALESCE(MAX(score), 0) FROM assessment_scores WHERE assessment_id = ?', [$aid]);
            if ($title === '' || $max <= 0 || $max > 1000 || $max < $highest) {
                flash('error', $max < $highest ? "Maximum cannot be below the highest score already entered ($highest)." : 'Enter a name and a valid maximum mark.');
            } else {
                db()->update('assessments', ['title' => $title, 'max_mark' => $max], 'id = ?', [$aid]);
                flash('success', 'Item updated.');
            }
        }
        redirect('exams/sheet/' . $csId, ['term' => $term['id']]);
    }

    public function submit(int $csId): void
    {
        [$cs, $term, $sheet] = $this->editable($csId);
        $missing = $this->missingExam($cs, $term);
        if ($missing > 0 && empty($_POST['confirm_missing'])) {
            flash('warning', "$missing student(s) have no exam score. Enter them (or “a” for absent), or tick the box to submit anyway.");
            redirect('exams/sheet/' . $csId, ['term' => $term['id']]);
        }
        db()->update('score_sheets', ['status' => 'submitted', 'submitted_by' => Auth::id(), 'submitted_at' => gmdate('Y-m-d H:i:s'), 'return_note' => null], 'id = ?', [$sheet['id']]);
        audit('sheet_submitted', 'class_subjects', $csId, ['term' => $term['id']]);
        Notifier::inappPermission('exams.manage', "Scores submitted: {$cs['subject_name']} — {$cs['class_name']}", 'Waiting for your approval.', 'exams/sheet/' . $csId . '?term=' . $term['id']);
        flash('success', 'Submitted for approval. Scores are now locked.');
        redirect('exams/sheet/' . $csId, ['term' => $term['id']]);
    }

    public function approve(int $csId): void
    {
        [$cs, $term, $sheet] = $this->sheetFor($csId);
        $this->stopIfReleased($cs, $term, $csId);
        $return = isset($_POST['return']);
        if ($sheet['status'] === 'approved') {
            flash('warning', 'This score sheet is already approved.');
            redirect('exams/sheet/' . $csId, ['term' => $term['id']]);
        }
        if ($return && $sheet['status'] !== 'submitted') {
            flash('error', 'Only a submitted score sheet can be returned to the teacher.');
            redirect('exams/sheet/' . $csId, ['term' => $term['id']]);
        }
        if (!$return && $sheet['status'] === 'draft' && ($missing = $this->missingExam($cs, $term)) > 0) {
            flash('error', "$missing student(s) have no exam score, so this sheet cannot be approved directly. Enter them (or “a” for absent) first.");
            redirect('exams/sheet/' . $csId, ['term' => $term['id']]);
        }
        if ($return) {
            $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 255) ?: 'Please review the scores.';
            db()->update('score_sheets', ['status' => 'draft', 'return_note' => $note, 'approved_by' => null, 'approved_at' => null], 'id = ?', [$sheet['id']]);
            $notify = array_filter([(int) $sheet['submitted_by'], (int) $cs['teacher_id']]);
            Notifier::inapp($notify, "Scores returned: {$cs['subject_name']} — {$cs['class_name']}", $note, 'exams/sheet/' . $csId . '?term=' . $term['id']);
            audit('sheet_returned', 'class_subjects', $csId, ['note' => $note]);
            flash('success', 'Returned to the teacher for correction.');
        } else {
            $direct = $sheet['status'] === 'draft';
            db()->update('score_sheets', ['status' => 'approved', 'approved_by' => Auth::id(), 'approved_at' => gmdate('Y-m-d H:i:s')]
                + ($direct ? ['submitted_by' => Auth::id(), 'submitted_at' => gmdate('Y-m-d H:i:s')] : []), 'id = ?', [$sheet['id']]);
            audit('sheet_approved', 'class_subjects', $csId, ['term' => $term['id'], 'direct' => $direct]);
            $notify = array_values(array_unique(array_filter([(int) $sheet['submitted_by'], (int) $cs['teacher_id']], fn($u) => $u && $u !== Auth::id())));
            if ($notify) {
                Notifier::inapp($notify, "Scores approved: {$cs['subject_name']} — {$cs['class_name']}", 'The Academic Coordinator approved this score sheet.', 'exams/sheet/' . $csId . '?term=' . $term['id']);
            }
            flash('success', 'Approved.');
        }
        redirect('exams/sheet/' . $csId, ['term' => $term['id']]);
    }

    /** Re-open an approved sheet (managers only). */
    public function reopen(int $csId): void
    {
        [$cs, $term, $sheet] = $this->sheetFor($csId);
        $this->stopIfReleased($cs, $term, $csId);
        db()->update('score_sheets', ['status' => 'draft', 'approved_by' => null, 'approved_at' => null], 'id = ?', [$sheet['id']]);
        audit('sheet_reopened', 'class_subjects', $csId, ['term' => $term['id']]);
        flash('success', 'Score sheet re-opened for editing.');
        redirect('exams/sheet/' . $csId, ['term' => $term['id']]);
    }

    // ---- Setup -------------------------------------------------------------------------

    public function setup(): void
    {
        $schemes = db()->all("SELECT * FROM grading_schemes ORDER BY FIELD(category, 'creche','kg','primary','jhs')");
        foreach ($schemes as &$s) {
            $s['bands'] = Results::bands((int) $s['id'], 'grade');
            $s['level_bands'] = Results::bands((int) $s['id'], 'level') ?: self::nacaLevels();
        }
        unset($s);
        $this->view('exams/setup', ['title' => 'Grading setup', 'schemes' => $schemes]);
    }

    public function saveScheme(int $id): void
    {
        $scheme = db()->one('SELECT * FROM grading_schemes WHERE id = ?', [$id]) ?? $this->notFound();
        $cw = (int) ($_POST['class_weight'] ?? 30);
        $ew = (int) ($_POST['exam_weight'] ?? 70);
        $pass = (float) ($_POST['pass_mark'] ?? 50);
        if ($cw < 0 || $ew < 0 || $cw + $ew !== 100 || $pass < 0 || $pass > 100) {
            flash('error', 'Class score and exam weights must add up to 100, and the pass mark must be between 0 and 100.');
            redirect('exams/setup#' . $scheme['category']);
        }
        $bands = $this->bandsInput('bands');
        if (!$bands || !isset($bands['0'])) {
            flash('error', 'The lowest grade must start at 0 so every score gets a grade.');
            redirect('exams/setup#' . $scheme['category']);
        }
        $showLevels = isset($_POST['show_levels']) ? 1 : 0;
        $levels = $this->bandsInput('levels');
        $levelNote = '';
        if ($showLevels && !$levels) { // nothing entered: use the NaCCA levels
            foreach (self::nacaLevels() as $b) {
                $levels[(string) (float) $b['min_score']] = ['min' => (float) $b['min_score'], 'grade' => $b['grade'], 'remark' => $b['remark']];
            }
            $levelNote = ' NaCCA proficiency levels added.';
        } elseif ($levels && !isset($levels['0'])) { // lowest level starts at 0 so every score gets a level
            $low = min(array_map(fn($b) => $b['min'], $levels));
            $b = $levels[(string) (float) $low];
            unset($levels[(string) (float) $low]);
            $b['min'] = 0.0;
            $levels['0'] = $b;
            $levelNote = " The lowest level ({$b['grade']}) now starts at 0.";
        }
        db()->transaction(function (Database $d) use ($id, $cw, $ew, $pass, $bands, $levels, $showLevels) {
            $d->update('grading_schemes', [
                'class_weight' => $cw, 'exam_weight' => $ew, 'pass_mark' => $pass,
                'show_position' => isset($_POST['show_position']) ? 1 : 0, 'use_aggregate' => isset($_POST['use_aggregate']) ? 1 : 0,
                'show_levels' => $showLevels,
            ], 'id = ?', [$id]);
            $d->run('DELETE FROM grade_bands WHERE scheme_id = ?', [$id]);
            foreach (['grade' => $bands, 'level' => $levels] as $set => $rows) {
                foreach ($rows as $b) {
                    $d->insert('grade_bands', ['scheme_id' => $id, 'band_set' => $set, 'min_score' => $b['min'], 'grade' => $b['grade'], 'remark' => $b['remark']]);
                }
            }
        });
        audit('grading_updated', 'grading_schemes', $id, ['class' => $cw, 'exam' => $ew]);
        flash('success', "Grading for {$scheme['name']} saved." . $levelNote . $this->frozenNote($scheme['category']));
        redirect('exams/setup#' . $scheme['category']);
    }

    /** NaCCA proficiency levels (standards-based curriculum), used when a stage has none yet. */
    public static function nacaLevels(): array
    {
        return array_map(fn($r) => ['min_score' => $r[0], 'grade' => $r[1], 'remark' => $r[2]], [
            [80, 'HP', 'Highly Proficient'], [68, 'P', 'Proficient'], [54, 'AP', 'Approaching Proficiency'], [40, 'D', 'Developing'], [0, 'E', 'Emerging'],
        ]);
    }

    /** The school's own grading as printed on its terminal report: class 50% + exams 50%, grades 1–9. */
    public const SCHOOL_PRESET = [[90, '1', 'Excellent'], [79, '2', 'Very Good'], [65, '3', 'Good'], [60, '4', 'Credit'], [55, '5', 'Pass'], [50, '6', 'Satisfactory'], [45, '7', 'Weak'], [40, '8', 'Buck Up'], [0, '9', 'Fail']];

    public function applyPreset(int $id): void
    {
        $scheme = db()->one('SELECT * FROM grading_schemes WHERE id = ?', [$id]) ?? $this->notFound();
        db()->transaction(function (Database $d) use ($id) {
            $d->update('grading_schemes', ['class_weight' => 50, 'exam_weight' => 50, 'pass_mark' => 40, 'show_position' => 1], 'id = ?', [$id]);
            $d->run("DELETE FROM grade_bands WHERE scheme_id = ? AND band_set = 'grade'", [$id]);
            foreach (self::SCHOOL_PRESET as [$min, $g, $r]) {
                $d->insert('grade_bands', ['scheme_id' => $id, 'band_set' => 'grade', 'min_score' => $min, 'grade' => $g, 'remark' => $r]);
            }
        });
        audit('grading_preset_applied', 'grading_schemes', $id, ['preset' => 'school_1_9']);
        flash('success', "{$scheme['name']}: class 50% + exams 50% and grades 1–9 (school report) applied." . ((int) $scheme['use_aggregate'] ? ' Note: the BECE aggregate now uses these grades.' : '') . $this->frozenNote($scheme['category']));
        redirect('exams/setup#' . $scheme['category']);
    }

    /** Report card options: page 2 (personality), its traits, overall position, fees line. */
    public function saveReportSettings(): void
    {
        $traits = array_values(array_filter(array_map(fn($t) => mb_substr(trim($t), 0, 60), preg_split('/\R/', (string) ($_POST['personality_traits'] ?? ''))), fn($t) => $t !== ''));
        if (count($traits) > 20) {
            flash('error', 'Use at most 20 personality traits.');
            redirect('exams/setup#report');
        }
        setting_set('personality_traits', implode("\n", $traits ?: ReportController::DEFAULT_TRAITS));
        setting_set('report_personality', !empty($_POST['report_personality']) ? '1' : '0');
        setting_set('report_overall_position', !empty($_POST['report_overall_position']) ? '1' : '0');
        setting_set('fee_show_on_report', !empty($_POST['fee_show_on_report']) ? '1' : '0');
        audit('report_settings_saved', 'settings', null, ['traits' => count($traits)]);
        flash('success', 'Report card settings saved.');
        redirect('exams/setup#report');
    }

    /** Terms whose reports were already released keep the grading they were released with. */
    private function frozenNote(string $category): string
    {
        try {
            $terms = db()->all('SELECT t.name, y.name AS year_name FROM grading_snapshots g JOIN terms t ON t.id = g.term_id JOIN academic_years y ON y.id = t.academic_year_id WHERE g.category = ? ORDER BY t.start_date DESC LIMIT 3', [$category]);
        } catch (PDOException $e) {
            return '';
        }
        return $terms ? ' Released reports keep their original grading (' . implode(', ', array_map(fn($t) => $t['year_name'] . ' ' . $t['name'], $terms)) . ').' : '';
    }

    /** Band rows from the grading form (empty rows skipped). @return array<string,array{min:float,grade:string,remark:string}> */
    private function bandsInput(string $field): array
    {
        $out = [];
        foreach ((array) ($_POST[$field] ?? []) as $b) {
            $g = mb_substr(trim((string) ($b['grade'] ?? '')), 0, 10);
            $r = mb_substr(trim((string) ($b['remark'] ?? '')), 0, 60);
            $m = trim((string) ($b['min'] ?? ''));
            if ($g === '' && $r === '' && $m === '') {
                continue;
            }
            if ($g === '' || !is_numeric($m) || $m < 0 || $m > 100) {
                flash('error', 'Each grade or level needs a name and a minimum score between 0 and 100.');
                redirect('exams/setup');
            }
            $out[(string) (float) $m] = ['min' => (float) $m, 'grade' => $g, 'remark' => $r];
        }
        return $out;
    }

    public function allocations(): void
    {
        $classId = (int) ($_GET['class'] ?? 0);
        $classes = db()->all('SELECT c.id, c.name, l.category FROM classes c JOIN levels l ON l.id = c.level_id WHERE c.is_active = 1 ORDER BY l.sort_order, c.name');
        $classId = $classId ?: (int) ($classes[0]['id'] ?? 0);
        $current = null;
        foreach ($classes as $c) {
            if ((int) $c['id'] === $classId) {
                $current = $c;
            }
        }
        $rows = db()->all('SELECT cs.*, s.name, s.code, s.is_core FROM class_subjects cs JOIN subjects s ON s.id = cs.subject_id WHERE cs.class_id = ? ORDER BY s.is_core DESC, s.name', [$classId]);
        $missing = db()->all('SELECT id, name FROM subjects WHERE is_active = 1 AND id NOT IN (SELECT subject_id FROM class_subjects WHERE class_id = ?) ORDER BY name', [$classId]);
        $teachers = db()->all("SELECT id, name FROM users WHERE is_active = 1 AND role IN (" . Auth::sqlRoles(['class_teacher', 'subject_teacher', 'head_teacher', 'academic_coordinator']) . ") ORDER BY name");
        $this->view('exams/allocations', ['title' => 'Subject teachers', 'classes' => $classes, 'current' => $current, 'rows' => $rows, 'missing' => $missing, 'teachers' => $teachers]);
    }

    public function saveAllocations(int $classId): void
    {
        db()->one('SELECT id FROM classes WHERE id = ?', [$classId]) ?? $this->notFound();
        $teacherIds = array_flip(array_column(db()->all("SELECT id FROM users WHERE is_active = 1 AND role NOT IN ('parent','pupil')"), 'id'));
        foreach ((array) ($_POST['teacher'] ?? []) as $csId => $tid) {
            $tid = (int) $tid;
            db()->update('class_subjects', ['teacher_id' => $tid && isset($teacherIds[$tid]) ? $tid : null], 'id = ? AND class_id = ?', [(int) $csId, $classId]);
        }
        if (!empty($_POST['add_subject'])) {
            db()->run('INSERT IGNORE INTO class_subjects (class_id, subject_id) VALUES (?, ?)', [$classId, (int) $_POST['add_subject']]);
        }
        foreach ((array) ($_POST['remove'] ?? []) as $csId) {
            $hasScores = db()->value('SELECT COUNT(*) FROM assessment_scores sc JOIN assessments a ON a.id = sc.assessment_id WHERE a.class_subject_id = ?', [(int) $csId]);
            if ($hasScores) {
                flash('warning', 'A subject with scores cannot be removed; its scores are kept.');
                continue;
            }
            db()->run('DELETE FROM class_subjects WHERE id = ? AND class_id = ?', [(int) $csId, $classId]);
        }
        Timetable::syncTeachers();
        audit('allocations_saved', 'classes', $classId);
        flash('success', 'Subjects and teachers saved.');
        redirect('exams/allocations', ['class' => $classId]);
    }

    // ---------------------------------------------------------------------------------

    /** @return array{0:?float,1:int,2:bool} [score, absent, ok] */
    private static function parseScore(string $raw, float $max): array
    {
        $raw = strtolower(trim($raw));
        if ($raw === '') {
            return [null, 0, true];
        }
        if (in_array($raw, ['a', 'abs', 'absent'], true)) {
            return [null, 1, true];
        }
        $raw = str_replace(',', '.', $raw);
        if (!is_numeric($raw) || (float) $raw < 0 || (float) $raw > $max) {
            return [null, 0, false];
        }
        return [round((float) $raw, 2), 0, true];
    }

    private function cs(int $csId): array
    {
        $cs = db()->one(
            'SELECT cs.*, s.name AS subject_name, s.code, c.name AS class_name, c.class_teacher_id, l.category, u.name AS teacher_name
             FROM class_subjects cs JOIN subjects s ON s.id = cs.subject_id JOIN classes c ON c.id = cs.class_id JOIN levels l ON l.id = c.level_id
             LEFT JOIN users u ON u.id = cs.teacher_id WHERE cs.id = ?',
            [$csId]
        ) ?? $this->notFound();
        if (!Results::canEnter($cs)) {
            http_response_code(403);
            $this->view('errors/403', ['title' => 'Access denied']);
            exit;
        }
        return $cs;
    }

    private function locked(array $sheet): bool
    {
        return $sheet['status'] !== 'draft';
    }

    /** Load cs + term + sheet and stop if the sheet is locked. */
    private function editable(int $csId): array
    {
        [$cs, $term, $sheet] = $this->sheetFor($csId, false);
        $this->stopIfReleased($cs, $term, $csId);
        if ($this->locked($sheet)) {
            flash('error', 'This score sheet is ' . $sheet['status'] . ' and locked.' . (can('exams.manage') ? ' Re-open it to make changes.' : ' Ask the Academic Coordinator to return it.'));
            redirect('exams/sheet/' . $csId, ['term' => $term['id']]);
        }
        return [$cs, $term, $sheet];
    }

    /** Reports already with parents: nothing changes until the release is withdrawn in Report cards. */
    private function stopIfReleased(array $cs, array $term, int $csId): void
    {
        if (Results::released((int) $cs['class_id'], (int) $term['id'])) {
            flash('error', "{$cs['class_name']} reports for {$term['name']} are released to parents, so these scores are locked. To correct them, withdraw the release in Report cards first, then release again (parents get a “corrected report” SMS).");
            redirect('exams/sheet/' . $csId, ['term' => $term['id']]);
        }
    }

    /** Students on the class list with no exam score (or ABS). */
    private function missingExam(array $cs, array $term): int
    {
        $ids = array_map(fn($p) => (int) $p['id'], Results::roster((int) $cs['class_id'], (int) $term['id']));
        if (!$ids) {
            return 0;
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $done = (int) db()->value(
            "SELECT COUNT(DISTINCT sc.pupil_id) FROM assessment_scores sc JOIN assessments a ON a.id = sc.assessment_id
             WHERE a.class_subject_id = ? AND a.term_id = ? AND a.kind = 'exam' AND (sc.score IS NOT NULL OR sc.is_absent = 1) AND sc.pupil_id IN ($in)",
            array_merge([(int) $cs['id'], (int) $term['id']], $ids)
        );
        return count($ids) - $done;
    }

    private function sheetFor(int $csId, bool $managerOnly = true): array
    {
        if ($managerOnly && !can('exams.manage')) {
            http_response_code(403);
            $this->view('errors/403', ['title' => 'Access denied']);
            exit;
        }
        $cs = $this->cs($csId);
        $termId = (int) ($_POST['term_id'] ?? $_GET['term'] ?? 0);
        $term = db()->one('SELECT t.*, y.name AS year_name FROM terms t JOIN academic_years y ON y.id = t.academic_year_id WHERE t.id = ?', [$termId]) ?? $this->notFound();
        $sheet = Results::ensureSheet((int) $term['id'], $cs, $cs['category']);
        return [$cs, $term, $sheet];
    }
}
