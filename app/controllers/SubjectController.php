<?php
declare(strict_types=1);

/** Subject Management: results per subject (performance) and the approved textbooks & materials register. */
class SubjectController extends Controller
{
    public const KINDS = ['textbook' => 'Learner textbook', 'workbook' => 'Workbook', 'teacher_guide' => "Teacher's guide", 'reader' => 'Supplementary reader', 'other' => 'Other material'];
    public const APPROVERS = ['nacca' => 'NaCCA approved', 'ges' => 'GES supplied', 'school' => 'School choice'];

    // ------------------------------------------------------------------ performance

    public function performance(): void
    {
        $term = $this->chosenTerm();
        if (!$term) {
            flash('error', 'Set up academic terms first.');
            redirect('curriculum');
        }
        $subjectId = (int) ($_GET['subject'] ?? 0);
        $all = can('curriculum.manage') || can('exams.manage');
        $where = 'c.is_active = 1';
        $p = [];
        if (!$all) {
            $where .= ' AND (cs.teacher_id = ? OR c.class_teacher_id = ?)';
            array_push($p, Auth::id(), Auth::id());
        }
        if ($subjectId) {
            $where .= ' AND s.id = ?';
            $p[] = $subjectId;
        }
        $css = db()->all(
            "SELECT cs.id, cs.class_id, cs.teacher_id, c.name AS class_name, l.category, s.id AS subject_id, s.name AS subject_name, s.is_core, u.name AS teacher_name
             FROM class_subjects cs JOIN classes c ON c.id = cs.class_id JOIN levels l ON l.id = c.level_id JOIN subjects s ON s.id = cs.subject_id
             LEFT JOIN users u ON u.id = cs.teacher_id WHERE $where ORDER BY s.name, l.sort_order, c.name",
            $p
        );
        $week = Curriculum::currentWeek($term);
        $weeksSoFar = $week ?? (date('Y-m-d') > $term['end_date'] ? Curriculum::weeks($term) : 0);
        $notes = LessonNotes::approvedShare((int) $term['id'], $weeksSoFar);
        $rosters = [];
        $rows = [];
        foreach ($css as $cs) {
            $rosters[$cs['class_id']] ??= array_map(fn($x) => (int) $x['id'], Results::roster((int) $cs['class_id'], (int) $term['id']));
            $scheme = Results::scheme((string) $cs['category'], (int) $term['id']);
            $tot = array_values(array_filter(array_map(fn($r) => $r['total'], Results::subjectScores((int) $term['id'], (int) $cs['id'], $rosters[$cs['class_id']], $scheme)), fn($v) => $v !== null));
            $cov = Curriculum::coverage((int) $term['id'], (int) $cs['id'], $week);
            $rows[] = $cs + [
                'n' => count($tot), 'roll' => count($rosters[$cs['class_id']]), 'sum' => array_sum($tot),
                'avg' => $tot ? round(array_sum($tot) / count($tot), 1) : null,
                'pass' => $tot ? count(array_filter($tot, fn($v) => $v >= (float) $scheme['pass_mark'])) : 0,
                'cov' => $cov['pct'], 'notes' => $weeksSoFar && LessonNotes::ready() ? ($notes[(int) $cs['id']] ?? 0) : null,
            ];
        }
        // one line per subject (all classes together)
        $subjects = [];
        foreach ($rows as $r) {
            $s = &$subjects[$r['subject_id']];
            $s['id'] = $r['subject_id'];
            $s['name'] = $r['subject_name'];
            $s['is_core'] = $r['is_core'];
            $s['classes'] = ($s['classes'] ?? 0) + 1;
            $s['n'] = ($s['n'] ?? 0) + $r['n'];
            $s['sum'] = ($s['sum'] ?? 0) + $r['sum'];
            $s['pass'] = ($s['pass'] ?? 0) + $r['pass'];
            if ($r['cov'] !== null) {
                $s['covs'][] = $r['cov'];
            }
            if ($r['notes'] !== null) {
                $s['notess'][] = $r['notes'];
            }
            if ($r['avg'] !== null) {
                if (!isset($s['best']) || $r['avg'] > $s['best'][1]) {
                    $s['best'] = [$r['class_name'], $r['avg']];
                }
                if (!isset($s['weak']) || $r['avg'] < $s['weak'][1]) {
                    $s['weak'] = [$r['class_name'], $r['avg']];
                }
            }
            unset($s);
        }
        foreach ($subjects as &$s) {
            $s['avg'] = $s['n'] ? round($s['sum'] / $s['n'], 1) : null;
            $s['passpct'] = $s['n'] ? round($s['pass'] * 100 / $s['n']) : null;
            $s['cov'] = !empty($s['covs']) ? round(array_sum($s['covs']) / count($s['covs'])) : null;
            $s['notes'] = !empty($s['notess']) ? round(array_sum($s['notess']) / count($s['notess'])) : null;
        }
        unset($s);
        $this->view('subjects/performance', [
            'title' => 'Subject performance', 'term' => $term, 'subjectId' => $subjectId, 'rows' => $rows, 'subjects' => array_values($subjects), 'weeksSoFar' => $weeksSoFar,
            'terms' => db()->all('SELECT t.id, t.name, y.name AS year_name FROM terms t JOIN academic_years y ON y.id = t.academic_year_id ORDER BY t.start_date DESC'),
            'allSubjects' => db()->all('SELECT id, name FROM subjects WHERE is_active = 1 ORDER BY name'),
        ]);
    }

    // ------------------------------------------------------------------ textbooks & materials

    public function materials(): void
    {
        $this->needsTable('subject_materials');
        $subjectId = (int) ($_GET['subject'] ?? 0);
        $levelId = (int) ($_GET['level'] ?? 0);
        $sql = 'SELECT m.*, s.name AS subject_name, l.name AS level_name, l.sort_order FROM subject_materials m JOIN subjects s ON s.id = m.subject_id LEFT JOIN levels l ON l.id = m.level_id WHERE m.is_active = 1';
        $p = [];
        if ($subjectId) {
            $sql .= ' AND m.subject_id = ?';
            $p[] = $subjectId;
        }
        if ($levelId) {
            $sql .= ' AND (m.level_id = ? OR m.level_id IS NULL)';
            $p[] = $levelId;
        }
        $rows = db()->all($sql . ' ORDER BY s.name, l.sort_order, m.kind, m.title', $p);
        $rolls = [];
        foreach (db()->all("SELECT c.level_id, COUNT(*) AS n FROM enrolments e JOIN classes c ON c.id = e.class_id JOIN pupils p ON p.id = e.pupil_id
                            WHERE e.academic_year_id = ? AND e.status = 'active' AND p.status = 'active' GROUP BY c.level_id", [current_year()['id'] ?? 0]) as $r) {
            $rolls[(int) $r['level_id']] = (int) $r['n'];
        }
        foreach ($rows as &$m) {
            $m['library'] = self::libraryCopies($m);
            $m['held'] = (int) $m['copies_held'] + $m['library'];
            $m['needed'] = (int) $m['per_student'] ? ($m['level_id'] ? ($rolls[(int) $m['level_id']] ?? 0) : null) : null;
            $m['short'] = $m['needed'] !== null ? max(0, $m['needed'] - $m['held']) : 0;
        }
        unset($m);
        $edit = isset($_GET['edit']) ? db()->one('SELECT * FROM subject_materials WHERE id = ?', [(int) $_GET['edit']]) : null;
        $this->view('subjects/materials', [
            'title' => 'Textbooks & materials', 'rows' => $rows, 'subjectId' => $subjectId, 'levelId' => $levelId, 'edit' => $edit,
            'subjects' => db()->all('SELECT id, name FROM subjects WHERE is_active = 1 ORDER BY name'),
            'levels' => db()->all('SELECT id, name FROM levels ORDER BY sort_order'),
        ]);
    }

    /** Copies of the same book in the library (same ISBN, or the same title). */
    private static function libraryCopies(array $m): int
    {
        try {
            if (!empty($m['isbn'])) {
                return (int) db()->value('SELECT COALESCE(SUM(copies - copies_lost), 0) FROM library_books WHERE is_active = 1 AND REPLACE(isbn, "-", "") = ?', [str_replace('-', '', (string) $m['isbn'])]);
            }
            return (int) db()->value('SELECT COALESCE(SUM(copies - copies_lost), 0) FROM library_books WHERE is_active = 1 AND title = ?', [$m['title']]);
        } catch (PDOException $e) {
            return 0;
        }
    }

    public function saveMaterial(?int $id = null): void
    {
        $this->needsTable('subject_materials');
        if ($id) {
            db()->one('SELECT id FROM subject_materials WHERE id = ?', [$id]) ?? $this->notFound();
        }
        $d = [
            'subject_id' => (int) ($_POST['subject_id'] ?? 0), 'level_id' => (int) ($_POST['level_id'] ?? 0) ?: null,
            'kind' => isset(self::KINDS[$_POST['kind'] ?? '']) ? $_POST['kind'] : 'textbook',
            'title' => mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 200),
            'author_publisher' => mb_substr(trim((string) ($_POST['author_publisher'] ?? '')), 0, 200) ?: null,
            'isbn' => mb_substr(preg_replace('/[^0-9Xx-]/', '', (string) ($_POST['isbn'] ?? '')), 0, 20) ?: null,
            'approved_by' => isset(self::APPROVERS[$_POST['approved_by'] ?? '']) ? $_POST['approved_by'] : 'nacca',
            'per_student' => !empty($_POST['per_student']) ? 1 : 0,
            'copies_held' => max(0, min(9999, (int) ($_POST['copies_held'] ?? 0))),
            'notes' => mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 255) ?: null,
        ];
        $back = ['subject' => $_POST['f_subject'] ?? null, 'level' => $_POST['f_level'] ?? null];
        if ($d['title'] === '' || !db()->value('SELECT 1 FROM subjects WHERE id = ?', [$d['subject_id']]) || ($d['level_id'] && !db()->value('SELECT 1 FROM levels WHERE id = ?', [$d['level_id']]))) {
            with_old($_POST);
            flash('error', 'Choose the subject and enter the title.');
            redirect('curriculum/materials', array_filter($back + ($id ? ['edit' => $id] : [])));
        }
        if ($id) {
            db()->update('subject_materials', $d, 'id = ?', [$id]);
        } else {
            $id = db()->insert('subject_materials', $d + ['created_by' => Auth::id()]);
        }
        audit('subject_material_saved', 'subject_materials', $id, ['title' => $d['title']]);
        flash('success', '“' . $d['title'] . '” saved.');
        redirect('curriculum/materials', array_filter($back));
    }

    public function deleteMaterial(int $id): void
    {
        $this->needsTable('subject_materials');
        $m = db()->one('SELECT * FROM subject_materials WHERE id = ?', [$id]) ?? $this->notFound();
        db()->update('subject_materials', ['is_active' => 0], 'id = ?', [$id]);
        audit('subject_material_removed', 'subject_materials', $id, ['title' => $m['title']]);
        flash('success', '“' . $m['title'] . '” removed from the list.');
        redirect('curriculum/materials');
    }
}
