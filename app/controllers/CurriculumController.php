<?php
declare(strict_types=1);

/** Curriculum strands & sub-strands, and the termly scheme of work teachers plan and tick off. */
class CurriculumController extends Controller
{
    public function index(): void
    {
        $term = $this->chosenTerm();
        $classId = (int) ($_GET['class'] ?? 0);
        $where = 'c.is_active = 1';
        $params = [];
        if (!can('curriculum.manage')) {
            $where .= ' AND (cs.teacher_id = ? OR c.class_teacher_id = ?)';
            $params = [Auth::id(), Auth::id()];
        }
        if ($classId) {
            $where .= ' AND c.id = ?';
            $params[] = $classId;
        }
        $rows = db()->all(
            "SELECT cs.id, cs.teacher_id, c.id AS class_id, c.name AS class_name, s.name AS subject_name, u.name AS teacher_name
             FROM class_subjects cs JOIN classes c ON c.id = cs.class_id JOIN levels l ON l.id = c.level_id
             JOIN subjects s ON s.id = cs.subject_id LEFT JOIN users u ON u.id = cs.teacher_id
             WHERE $where ORDER BY l.sort_order, c.name, s.is_core DESC, s.name",
            $params
        );
        $week = $term ? Curriculum::currentWeek($term) : null;
        foreach ($rows as &$r) {
            $r['cov'] = $term ? Curriculum::coverage((int) $term['id'], (int) $r['id'], $week) : null;
        }
        unset($r);
        $this->view('curriculum/index', [
            'title' => 'Scheme of work', 'rows' => $rows, 'term' => $term, 'week' => $week, 'classId' => $classId,
            'terms' => db()->all('SELECT t.id, t.name, y.name AS year_name FROM terms t JOIN academic_years y ON y.id = t.academic_year_id ORDER BY t.start_date DESC'),
            'classes' => db()->all('SELECT c.id, c.name FROM classes c JOIN levels l ON l.id = c.level_id WHERE c.is_active = 1 ORDER BY l.sort_order, c.name'),
        ]);
    }

    // ------------------------------------------------------------------ strands

    public function strands(): void
    {
        $subjects = db()->all('SELECT s.*, (SELECT COUNT(*) FROM curriculum_strands cs WHERE cs.subject_id = s.id) AS n FROM subjects s WHERE s.is_active = 1 ORDER BY s.name');
        $sid = (int) ($_GET['subject'] ?? ($subjects[0]['id'] ?? 0));
        $subject = db()->one('SELECT * FROM subjects WHERE id = ?', [$sid]);
        $this->view('curriculum/strands', [
            'title' => 'Curriculum strands', 'subjects' => $subjects, 'subject' => $subject,
            'tree' => $subject ? Curriculum::tree($sid, null, false) : [], 'edit' => can('curriculum.manage'),
        ]);
    }

    public function saveStrands(): void
    {
        $sid = (int) ($_POST['subject_id'] ?? 0);
        db()->one('SELECT id FROM subjects WHERE id = ?', [$sid]) ?? $this->notFound();
        $levels = fn($v) => implode(',', array_values(array_intersect(array_keys(Curriculum::LEVELS), (array) $v))) ?: 'primary,jhs';
        $action = (string) ($_POST['action'] ?? '');
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 150);
        if ($action === 'add_strand' && $name !== '') {
            db()->insert('curriculum_strands', ['subject_id' => $sid, 'name' => $name, 'levels' => $levels($_POST['levels'] ?? []),
                'sort_order' => (int) db()->value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM curriculum_strands WHERE subject_id = ? AND parent_id IS NULL', [$sid])]);
            flash('success', 'Strand added.');
        } elseif ($action === 'add_sub' && $name !== '') {
            $p = db()->one('SELECT * FROM curriculum_strands WHERE id = ? AND subject_id = ? AND parent_id IS NULL', [(int) ($_POST['parent_id'] ?? 0), $sid]) ?? $this->notFound();
            db()->insert('curriculum_strands', ['subject_id' => $sid, 'parent_id' => $p['id'], 'name' => $name, 'levels' => $p['levels'],
                'sort_order' => (int) db()->value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM curriculum_strands WHERE parent_id = ?', [$p['id']])]);
            flash('success', 'Sub-strand added.');
        } elseif ($action === 'save') {
            db()->transaction(function (Database $d) use ($sid, $levels) {
                foreach ((array) ($_POST['s'] ?? []) as $id => $r) {
                    $n = mb_substr(trim((string) ($r['name'] ?? '')), 0, 150);
                    if (!ctype_digit((string) $id) || $n === '') {
                        continue;
                    }
                    $row = $d->one('SELECT * FROM curriculum_strands WHERE id = ? AND subject_id = ?', [(int) $id, $sid]);
                    if (!$row) {
                        continue;
                    }
                    $upd = ['name' => $n, 'sort_order' => (int) ($r['sort_order'] ?? 0), 'is_active' => isset($r['is_active']) ? 1 : 0];
                    if ($row['parent_id'] === null) {
                        $upd['levels'] = $levels($r['levels'] ?? []);
                        $d->run('UPDATE curriculum_strands SET levels = ? WHERE parent_id = ?', [$upd['levels'], $row['id']]);
                    }
                    $d->update('curriculum_strands', $upd, 'id = ?', [$row['id']]);
                }
            });
            flash('success', 'Strands saved.');
        } elseif ($action === 'delete') {
            $row = db()->one('SELECT * FROM curriculum_strands WHERE id = ? AND subject_id = ?', [(int) ($_POST['id'] ?? 0), $sid]) ?? $this->notFound();
            db()->run('DELETE FROM curriculum_strands WHERE id = ?', [$row['id']]);
            flash('success', '“' . $row['name'] . '” removed. Scheme-of-work topics that used it keep their text.');
        }
        audit('curriculum_strands_' . ($action ?: 'save'), 'subjects', $sid);
        redirect('curriculum/strands', ['subject' => $sid]);
    }

    // ------------------------------------------------------------------ scheme of work

    public function scheme(int $id): void
    {
        [$cs, $term] = $this->csTerm($id, false);
        $week = Curriculum::currentWeek($term);
        $others = db()->all(
            'SELECT DISTINCT si.term_id, si.class_subject_id, c.name AS class_name, t.name AS term_name, y.name AS year_name, COUNT(*) AS n
             FROM scheme_items si JOIN class_subjects cs ON cs.id = si.class_subject_id JOIN classes c ON c.id = cs.class_id
             JOIN terms t ON t.id = si.term_id JOIN academic_years y ON y.id = t.academic_year_id
             WHERE cs.subject_id = ? AND NOT (si.term_id = ? AND si.class_subject_id = ?)
             GROUP BY si.term_id, si.class_subject_id, c.name, t.name, y.name, t.start_date ORDER BY t.start_date DESC, c.name LIMIT 30',
            [$cs['subject_id'], $term['id'], $cs['id']]
        );
        $this->view('curriculum/scheme', [
            'title' => 'Scheme of work — ' . $cs['class_name'] . ' ' . $cs['subject_name'], 'cs' => $cs, 'term' => $term,
            'items' => Curriculum::items((int) $term['id'], $id), 'tree' => Curriculum::tree((int) $cs['subject_id'], (string) $cs['category']),
            'weeks' => Curriculum::weeks($term), 'week' => $week, 'cov' => Curriculum::coverage((int) $term['id'], $id, $week),
            'edit' => Curriculum::canPlan($cs), 'others' => $others,
        ]);
    }

    public function printScheme(int $id): void
    {
        [$cs, $term] = $this->csTerm($id, false);
        $this->view('curriculum/scheme_print', [
            'title' => 'Scheme of work — ' . $cs['class_name'] . ' ' . $cs['subject_name'], 'cs' => $cs, 'term' => $term,
            'items' => Curriculum::items((int) $term['id'], $id), 'cov' => Curriculum::coverage((int) $term['id'], $id),
        ], 'layouts/print');
    }

    public function addItem(int $id): void
    {
        [$cs, $term] = $this->csTerm($id, true);
        $topic = mb_substr(trim((string) ($_POST['topic'] ?? '')), 0, 200);
        $week = (int) ($_POST['week_no'] ?? 0);
        if ($topic === '' || $week < 1 || $week > 20) {
            flash('error', 'Choose the week and type the topic.');
        } else {
            db()->insert('scheme_items', [
                'term_id' => $term['id'], 'class_subject_id' => $id, 'week_no' => $week, 'strand_id' => $this->strandId($cs),
                'topic' => $topic, 'indicators' => $this->str('indicators', 255), 'resources' => $this->str('resources', 200), 'created_by' => Auth::id(),
            ]);
            flash('success', 'Topic added to week ' . $week . '.');
        }
        redirect('curriculum/scheme/' . $id, ['term' => $term['id']]);
    }

    public function saveItems(int $id): void
    {
        [$cs, $term] = $this->csTerm($id, true);
        $del = array_map('intval', (array) ($_POST['delete'] ?? []));
        db()->transaction(function (Database $d) use ($id, $term, $cs, $del) {
            foreach ((array) ($_POST['i'] ?? []) as $iid => $r) {
                $iid = (int) $iid;
                if (in_array($iid, $del, true)) {
                    $d->run('DELETE FROM scheme_items WHERE id = ? AND term_id = ? AND class_subject_id = ?', [$iid, $term['id'], $id]);
                    continue;
                }
                $topic = mb_substr(trim((string) ($r['topic'] ?? '')), 0, 200);
                $week = (int) ($r['week_no'] ?? 0);
                if ($topic === '' || $week < 1 || $week > 20) {
                    continue;
                }
                $sid = (int) ($r['strand_id'] ?? 0);
                $sid = $sid && $d->value('SELECT 1 FROM curriculum_strands WHERE id = ? AND subject_id = ?', [$sid, $cs['subject_id']]) ? $sid : null;
                $taught = (string) ($r['taught_on'] ?? '');
                $taught = Validator::isDate($taught) && $taught >= $term['start_date'] && $taught <= max($term['end_date'], date('Y-m-d')) ? $taught : null;
                $old = $d->one('SELECT taught_on, taught_by FROM scheme_items WHERE id = ? AND term_id = ? AND class_subject_id = ?', [$iid, $term['id'], $id]);
                if (!$old) {
                    continue;
                }
                $d->update('scheme_items', [
                    'week_no' => $week, 'strand_id' => $sid, 'topic' => $topic,
                    'indicators' => mb_substr(trim((string) ($r['indicators'] ?? '')), 0, 255) ?: null,
                    'resources' => mb_substr(trim((string) ($r['resources'] ?? '')), 0, 200) ?: null,
                    'remark' => mb_substr(trim((string) ($r['remark'] ?? '')), 0, 200) ?: null,
                    'taught_on' => $taught, 'taught_by' => $taught ? ($old['taught_on'] === $taught ? $old['taught_by'] : Auth::id()) : null,
                ], 'id = ?', [$iid]);
            }
        });
        audit('scheme_saved', 'class_subjects', $id, ['term' => $term['id'], 'deleted' => count($del)]);
        flash('success', 'Scheme of work saved.');
        redirect('curriculum/scheme/' . $id, ['term' => $term['id']]);
    }

    /** Tick one topic as taught today (or untick). */
    public function tick(int $id): void
    {
        $item = db()->one('SELECT * FROM scheme_items WHERE id = ?', [$id]) ?? $this->notFound();
        $cs = Curriculum::classSubject((int) $item['class_subject_id']) ?? $this->notFound();
        if (!Curriculum::canPlan($cs)) {
            $this->forbidden();
        }
        $on = $item['taught_on'] ? null : date('Y-m-d');
        db()->update('scheme_items', ['taught_on' => $on, 'taught_by' => $on ? Auth::id() : null], 'id = ?', [$id]);
        flash('success', $on ? '“' . $item['topic'] . '” marked as taught today.' : 'Tick removed.');
        redirect('curriculum/scheme/' . $item['class_subject_id'], ['term' => $item['term_id']]);
    }

    public function draft(int $id): void
    {
        [$cs, $term] = $this->csTerm($id, true);
        if (db()->value('SELECT 1 FROM scheme_items WHERE term_id = ? AND class_subject_id = ? LIMIT 1', [$term['id'], $id])) {
            flash('error', 'This scheme already has topics.');
        } else {
            $n = Curriculum::draftFromCurriculum($cs, $term);
            flash($n ? 'success' : 'warning', $n ? "$n topics drafted from the curriculum. Edit the weeks and wording to suit your class." : 'No strands for this subject and level yet. Add them under Curriculum strands.');
        }
        redirect('curriculum/scheme/' . $id, ['term' => $term['id']]);
    }

    public function copy(int $id): void
    {
        [$cs, $term] = $this->csTerm($id, true);
        [$srcTerm, $srcCs] = array_map('intval', explode(':', (string) ($_POST['source'] ?? '0:0')) + [0, 0]);
        $src = Curriculum::classSubject($srcCs);
        if (!$src || (int) $src['subject_id'] !== (int) $cs['subject_id']) {
            flash('error', 'Choose a scheme of the same subject to copy.');
            redirect('curriculum/scheme/' . $id, ['term' => $term['id']]);
        }
        $n = 0;
        db()->transaction(function (Database $d) use ($srcTerm, $srcCs, $id, $term, &$n) {
            foreach ($d->all('SELECT * FROM scheme_items WHERE term_id = ? AND class_subject_id = ? ORDER BY week_no, id', [$srcTerm, $srcCs]) as $r) {
                $d->insert('scheme_items', [
                    'term_id' => $term['id'], 'class_subject_id' => $id, 'week_no' => $r['week_no'], 'strand_id' => $r['strand_id'],
                    'topic' => $r['topic'], 'indicators' => $r['indicators'], 'resources' => $r['resources'], 'created_by' => Auth::id(),
                ]);
                $n++;
            }
        });
        audit('scheme_copied', 'class_subjects', $id, ['from' => $srcCs, 'term' => $srcTerm, 'count' => $n]);
        flash('success', "$n topics copied (not ticked as taught).");
        redirect('curriculum/scheme/' . $id, ['term' => $term['id']]);
    }

    // ------------------------------------------------------------------ helpers

    /** @return array{0:array,1:array} the class subject and term, after the access check */
    private function csTerm(int $id, bool $edit): array
    {
        $cs = Curriculum::classSubject($id) ?? $this->notFound();
        $term = $this->chosenTerm() ?? $this->notFound();
        $mine = Curriculum::canPlan($cs);
        if ($edit ? !$mine : (!$mine && !can('curriculum.manage') && !Auth::classInScope((int) $cs['class_id']))) {
            $this->forbidden();
        }
        return [$cs, $term];
    }

    private function strandId(array $cs): ?int
    {
        $sid = (int) ($_POST['strand_id'] ?? 0);
        return $sid && db()->value('SELECT 1 FROM curriculum_strands WHERE id = ? AND subject_id = ?', [$sid, $cs['subject_id']]) ? $sid : null;
    }

    private function str(string $k, int $max): ?string
    {
        $v = trim((string) ($_POST[$k] ?? ''));
        return $v === '' ? null : mb_substr($v, 0, $max);
    }
}
