<?php
declare(strict_types=1);

/** Terminal report cards: broadsheet, remarks, printing, release to parents. */
class ReportController extends Controller
{

    public const DEFAULT_TRAITS = ['Courtesy', 'Neatness', 'Emotional Control', 'Sociability', 'Initiative', 'Dependability', 'Cooperative Spirit', 'Creativity', 'Volunteerism', 'Honesty', 'Leadership Qualities'];

    /** Quick-pick comments for the Child's Personality Development Report (teachers can type their own). */
    public const TRAIT_SUGGESTIONS = [
        'Courtesy' => ['Uses "please" and "thank you" consistently', 'Greets teachers and visitors politely', 'Respectful to elders and classmates', 'Needs reminders to be polite'],
        'Neatness' => ['Writes assignments tidily', 'Keeps books and uniform neat', 'Keeps desk and work area tidy', 'Needs to improve on neatness of work'],
        'Emotional Control' => ['Calm and self-controlled', 'Handles disappointment well', 'Shows mood swings occasionally', 'Needs help managing anger'],
        'Sociability' => ['Works well in team projects', 'Friendly and gets along with others', 'Makes friends easily', 'Rather quiet; encourage to mix more'],
        'Initiative' => ['Shows enthusiasm for new challenges', 'Starts tasks without being told', 'Asks good questions in class', 'Waits to be told what to do'],
        'Dependability' => ['Shows accountability for mistakes', 'Can be trusted with responsibilities', 'Completes tasks on time', 'Needs reminders to complete tasks'],
        'Cooperative Spirit' => ['Participates in teamwork happily', 'Shares and helps classmates', 'Follows class rules willingly', 'Prefers to work alone'],
        'Creativity' => ['Shows imagination in art projects', 'Comes up with original ideas', 'Enjoys drama, music and drawing', 'Developing creative skills'],
        'Volunteerism' => ['Participates in class clean-up', 'Offers to help without being asked', 'Takes part in school activities', 'Seldom volunteers'],
        'Honesty' => ['Shows integrity during tests and assignments', 'Truthful in words and actions', 'Owns up to mistakes', 'Needs guidance on telling the truth'],
        'Leadership Qualities' => ['Takes charge of projects confidently', 'A good example to others', 'Leads group work well', 'Developing leadership skills'],
    ];

    /** Traits printed on page 2 (Grading setup → Report card). */
    public static function traits(): array
    {
        $t = array_values(array_filter(array_map('trim', preg_split('/\R/', setting('personality_traits', implode("\n", self::DEFAULT_TRAITS)))), fn($x) => $x !== ''));
        return array_slice($t ?: self::DEFAULT_TRAITS, 0, 20);
    }

    /** "UPPER PRIMARY SCHOOL", "JUNIOR HIGH SCHOOL"… from the class's level. */
    public static function sectionTitle(array $class): string
    {
        $level = db()->one('SELECT code, category FROM levels WHERE id = ?', [$class['level_id'] ?? 0]) ?? ['code' => '', 'category' => $class['category'] ?? ''];
        return match (true) {
            $level['category'] === 'creche' => 'CRECHE',
            $level['category'] === 'kg' => 'KINDERGARTEN',
            $level['category'] === 'jhs' => 'JUNIOR HIGH SCHOOL',
            in_array($level['code'], ['B1', 'B2', 'B3'], true) => 'LOWER PRIMARY SCHOOL',
            default => 'UPPER PRIMARY SCHOOL',
        };
    }

    public static function termWords(int $n): string
    {
        return [1 => 'One (1)', 2 => 'Two (2)', 3 => 'Three (3)'][$n] ?? '';
    }

    /** @return array{0:string,1:string} [promoted to, repeat] for the report card */
    public static function promotionText(array $class, string $promotion): array
    {
        if ($promotion === '') {
            return ['', ''];
        }
        $level = db()->one('SELECT name, sort_order FROM levels WHERE id = ?', [$class['level_id'] ?? 0]);
        if ($promotion === 'repeated') {
            return ['', $level['name'] ?? $class['name']];
        }
        $next = $level ? db()->value('SELECT name FROM levels WHERE sort_order > ? ORDER BY sort_order LIMIT 1', [$level['sort_order']]) : null;
        $to = $next ?: 'Completed';
        return [$promotion === 'on_trial' ? $to . ' (on trial)' : $to, ''];
    }

    public function index(): void
    {
        [$term, $terms] = $this->termParam(isset($_POST['term_id']) ? (int) $_POST['term_id'] : null);
        $sql = 'SELECT c.*, l.name AS level_name, u.name AS teacher_name FROM classes c JOIN levels l ON l.id = c.level_id LEFT JOIN users u ON u.id = c.class_teacher_id WHERE c.is_active = 1';
        $p = [];
        if (!can('exams.manage')) {
            $sql .= ' AND c.class_teacher_id = ?';
            $p[] = Auth::id();
        }
        $classes = db()->all($sql . ' ORDER BY l.sort_order, c.name', $p);
        $info = [];
        if ($term) {
            foreach ($classes as $c) {
                $info[$c['id']] = db()->one(
                    "SELECT
                        (SELECT COUNT(*) FROM enrolments e WHERE e.class_id = ? AND e.academic_year_id = ? AND e.status = 'active') AS pupils,
                        (SELECT COUNT(*) FROM class_subjects cs WHERE cs.class_id = ?) AS subjects,
                        (SELECT COUNT(*) FROM class_subjects cs JOIN score_sheets ss ON ss.class_subject_id = cs.id AND ss.term_id = ? WHERE cs.class_id = ? AND ss.status = 'approved') AS approved,
                        (SELECT COUNT(*) FROM report_remarks r JOIN enrolments e ON e.pupil_id = r.pupil_id AND e.class_id = ? AND e.academic_year_id = ? WHERE r.term_id = ? AND r.teacher_remark IS NOT NULL AND r.teacher_remark <> '') AS remarks,
                        (SELECT released_at FROM report_releases rr WHERE rr.class_id = ? AND rr.term_id = ?) AS released_at",
                    [$c['id'], $term['academic_year_id'], $c['id'], $term['id'], $c['id'], $c['id'], $term['academic_year_id'], $term['id'], $c['id'], $term['id']]
                );
            }
        }
        $this->view('reports/index', ['title' => 'Report cards', 'term' => $term, 'terms' => $terms, 'classes' => $classes, 'info' => $info]);
    }

    public function broadsheet(int $classId): void
    {
        [$class, $term] = $this->classTerm($classId);
        $R = Results::classResults($classId, (int) $term['id']);
        $this->view('reports/broadsheet', ['title' => 'Broadsheet — ' . $class['name'], 'R' => $R, 'terms' => $this->termParam()[1]]);
    }

    public function exportBroadsheet(int $classId): void
    {
        [$class, $term] = $this->classTerm($classId);
        $R = Results::classResults($classId, (int) $term['id']);
        $name = preg_replace('/[^A-Za-z0-9_-]+/', '_', 'broadsheet_' . $class['name'] . '_' . $term['year_name'] . '_' . $term['name']);
        $out = csv_start($name . '.csv');
        $head = ['Admission no.', 'Name'];
        foreach ($R['subjects'] as $s) {
            array_push($head, $s['name'] . ' (total)', $s['name'] . ' (grade)');
        }
        array_push($head, 'Total', 'Average', 'Position');
        if ($R['scheme']['use_aggregate']) {
            $head[] = 'Aggregate';
        }
        csv_row($out, $head);
        foreach ($R['pupils'] as $p) {
            $pid = (int) $p['id'];
            $line = [$p['admission_no'], $p['last_name'] . ', ' . $p['first_name']];
            foreach ($R['subjects'] as $s) {
                $sc = $R['scores'][$pid][$s['id']] ?? [];
                array_push($line, $sc['total'] ?? '', $sc['grade'] ?? '');
            }
            $sm = $R['summary'][$pid];
            array_push($line, $sm['total'] ?? '', $sm['average'] ?? '', $sm['position'] ?? '');
            if ($R['scheme']['use_aggregate']) {
                $line[] = $sm['aggregate'] ?? '';
            }
            csv_row($out, $line);
        }
        audit('broadsheet_export', 'classes', $classId, ['term' => $term['id']]);
        csv_end($out);
    }

    public function remarks(int $classId): void
    {
        [$class, $term] = $this->classTerm($classId);
        $R = Results::classResults($classId, (int) $term['id']);
        $this->view('reports/remarks', ['title' => 'Remarks — ' . $class['name'], 'R' => $R, 'terms' => $this->termParam()[1], 'isLastTerm' => $this->isLastTerm($term)]);
    }

    public function saveRemarks(int $classId): void
    {
        [$class, $term] = $this->classTerm($classId);
        $this->stopIfReleased($class, $term, 'reports/class/' . $classId . '/remarks');
        $roster = array_flip(array_map(fn($p) => (int) $p['id'], Results::classResults($classId, (int) $term['id'])['pupils']));
        $canHead = can('reports.head');
        $cut = fn($v, $n) => ($v = mb_substr(trim((string) $v), 0, $n)) === '' ? null : $v;
        db()->transaction(function (Database $d) use ($roster, $term, $canHead, $cut) {
            foreach ((array) ($_POST['r'] ?? []) as $pid => $r) {
                $pid = (int) $pid;
                if (!isset($roster[$pid]) || !is_array($r)) {
                    continue;
                }
                $data = [
                    'teacher_remark' => $cut($r['teacher_remark'] ?? '', 255),
                    'promotion' => in_array($r['promotion'] ?? '', ['promoted', 'repeated', 'on_trial'], true) ? $r['promotion'] : '',
                    'updated_by' => Auth::id(),
                ];
                if ($canHead) {
                    $data['head_remark'] = $cut($r['head_remark'] ?? '', 255);
                }
                $exists = $d->value('SELECT id FROM report_remarks WHERE term_id = ? AND pupil_id = ?', [$term['id'], $pid]);
                if ($exists) {
                    $d->update('report_remarks', $data, 'id = ?', [$exists]);
                } else {
                    $d->insert('report_remarks', $data + ['term_id' => $term['id'], 'pupil_id' => $pid]);
                }
            }
        });
        audit('remarks_saved', 'classes', $classId, ['term' => $term['id']]);
        flash('success', 'Remarks saved.');
        redirect('reports/class/' . $classId . '/remarks', ['term' => $term['id']]);
    }

    /** Child's Personality Development Report: one pupil at a time, quick-pick comments per trait. */
    public function personality(int $classId): void
    {
        [$class, $term] = $this->classTerm($classId);
        $R = Results::classResults($classId, (int) $term['id']);
        if (!$R['pupils']) {
            flash('warning', 'No students in this class.');
            redirect('reports', ['term' => $term['id']]);
        }
        $ids = array_map(fn($p) => (int) $p['id'], $R['pupils']);
        $pid = (int) ($_GET['pupil'] ?? 0);
        if (!in_array($pid, $ids, true)) {
            $pid = $ids[0];
            foreach ($ids as $i) { // first pupil not done yet
                if (empty(json_decode((string) ($R['remarks'][$i]['personality'] ?? ''), true))) {
                    $pid = $i;
                    break;
                }
            }
        }
        $this->view('reports/personality', ['title' => 'Personality report — ' . $class['name'], 'R' => $R, 'pid' => $pid, 'traits' => self::traits()]);
    }

    public function savePersonality(int $classId): void
    {
        [$class, $term] = $this->classTerm($classId);
        $this->stopIfReleased($class, $term, 'reports/class/' . $classId . '/personality');
        $R = Results::classResults($classId, (int) $term['id']);
        $ids = array_map(fn($p) => (int) $p['id'], $R['pupils']);
        $pid = (int) ($_POST['pupil_id'] ?? 0);
        if (!in_array($pid, $ids, true)) {
            $this->notFound();
        }
        $out = [];
        foreach (self::traits() as $i => $t) {
            $v = mb_substr(trim((string) ($_POST['trait'][$i] ?? '')), 0, 150);
            if ($v !== '') {
                $out[$t] = $v;
            }
        }
        $json = $out ? json_encode($out, JSON_UNESCAPED_UNICODE) : null;
        $exists = db()->value('SELECT id FROM report_remarks WHERE term_id = ? AND pupil_id = ?', [$term['id'], $pid]);
        if ($exists) {
            db()->update('report_remarks', ['personality' => $json, 'updated_by' => Auth::id()], 'id = ?', [$exists]);
        } else {
            db()->insert('report_remarks', ['term_id' => $term['id'], 'pupil_id' => $pid, 'personality' => $json, 'updated_by' => Auth::id()]);
        }
        $pos = array_search($pid, $ids, true);
        $next = !empty($_POST['next']) && isset($ids[$pos + 1]) ? $ids[$pos + 1] : $pid;
        flash('success', 'Saved.' . ($next !== $pid ? ' Next student.' : ''));
        redirect('reports/class/' . $classId . '/personality', ['term' => $term['id'], 'pupil' => $next]);
    }

    public function printClass(int $classId): void
    {
        [$class, $term] = $this->classTerm($classId);
        $R = Results::classResults($classId, (int) $term['id']);
        $only = (int) ($_GET['pupil'] ?? 0);
        $this->view('reports/print', ['title' => 'Report cards — ' . $class['name'], 'R' => $R, 'only' => $only, 'shared' => false], 'layouts/print');
    }

    /** Single pupil report card (from the pupil profile). */
    public function pupil(int $pupilId): void
    {
        [$term] = $this->termParam(isset($_POST['term_id']) ? (int) $_POST['term_id'] : null);
        if (!$term) {
            $this->notFound();
        }
        $classId = (int) db()->value('SELECT class_id FROM enrolments WHERE pupil_id = ? AND academic_year_id = ?', [$pupilId, $term['academic_year_id']]);
        if (!$classId) {
            flash('error', 'This student was not enrolled in a class in that academic year.');
            redirect('pupils/' . $pupilId);
        }
        $this->classTerm($classId); // access check
        $R = Results::classResults($classId, (int) $term['id']);
        $this->view('reports/print', ['title' => 'Report card', 'R' => $R, 'only' => $pupilId, 'shared' => false], 'layouts/print');
    }

    public function release(int $classId): void
    {
        [$class, $term] = $this->classTerm($classId);
        $R = Results::classResults($classId, (int) $term['id']);
        $notApproved = array_filter($R['subjects'], fn($s) => $s['sheet_status'] !== 'approved');
        if ($notApproved && empty($_POST['force'])) {
            flash('error', count($notApproved) . ' subject(s) are not approved yet: ' . implode(', ', array_column(array_slice($notApproved, 0, 4), 'name')) . '. Approve them, or tick “release anyway”.');
            redirect('reports', ['term' => $term['id']]);
        }
        if (Results::released($classId, (int) $term['id'])) {
            flash('warning', "Reports for {$class['name']} are already released.");
            redirect('reports', ['term' => $term['id']]);
        }
        db()->run('INSERT IGNORE INTO report_releases (term_id, class_id, released_by) VALUES (?, ?, ?)', [$term['id'], $classId, Auth::id()]);
        Results::freeze((int) $term['id'], (string) $R['class']['category']); // later grading changes never touch these reports
        $times = 1;
        try {
            db()->run('INSERT INTO report_release_history (term_id, class_id, times, last_released_at) VALUES (?, ?, 1, UTC_TIMESTAMP())
                       ON DUPLICATE KEY UPDATE times = times + 1, last_released_at = UTC_TIMESTAMP()', [$term['id'], $classId]);
            $times = (int) db()->value('SELECT times FROM report_release_history WHERE term_id = ? AND class_id = ?', [$term['id'], $classId]);
        } catch (PDOException $e) {
            // migration 027 not applied yet
        }
        $corrected = $times > 1;

        $queued = 0;
        $sendSms = !empty($_POST['sms']);
        foreach ($R['pupils'] as $p) {
            $pid = (int) $p['id'];
            $token = db()->value('SELECT token FROM report_links WHERE term_id = ? AND pupil_id = ?', [$term['id'], $pid]);
            if (!$token) {
                $token = bin2hex(random_bytes(12));
                db()->insert('report_links', ['token' => $token, 'term_id' => $term['id'], 'pupil_id' => $pid]);
            }
            if (!$sendSms) {
                continue;
            }
            $g = db()->one('SELECT g.name, g.phone FROM pupil_guardian pg JOIN guardians g ON g.id = pg.guardian_id WHERE pg.pupil_id = ? ORDER BY pg.is_primary DESC LIMIT 1', [$pid]);
            $sm = $R['summary'][$pid];
            if (!$g || $sm['average'] === null) {
                continue;
            }
            $position = $R['scheme']['show_position'] && $sm['position'] ? ', position ' . Results::ordinal($sm['position']) . ' of ' . $R['rankedCount'] : '';
            if (Notifier::smsTemplate($corrected ? 'results_corrected' : 'results_released', $g['phone'], [
                'guardian' => $g['name'], 'pupil' => $p['first_name'], 'term' => $term['name'],
                'average' => Results::fmt($sm['average']), 'position' => $position, 'link' => $this->absoluteUrl('r/' . $token),
            ], ['name' => $g['name'], 'entity' => 'report_links', 'entity_id' => crc32($token . ($corrected ? '#' . $times : '')) & 0x7fffffff])) {
                $queued++;
            }
        }
        audit('reports_released', 'classes', $classId, ['term' => $term['id'], 'sms' => $queued]);
        $r = $queued ? Notifier::process(200) : ['held' => 0];
        flash('success', "Reports for {$class['name']} " . ($corrected ? 're-released as corrected' : 'released') . '.' . ($queued ? " $queued parent SMS queued" . ($r['held'] ? ' (held until after the Sabbath).' : '.') : ''));
        if ($queued && ($pu = public_url_status()) && $pu[0] === 'bad') {
            flash('warning', $pu[1]);
        }
        redirect('reports', ['term' => $term['id']]);
    }

    public function unrelease(int $classId): void
    {
        [$class, $term] = $this->classTerm($classId);
        db()->run('DELETE FROM report_releases WHERE term_id = ? AND class_id = ?', [$term['id'], $classId]);
        audit('reports_unreleased', 'classes', $classId, ['term' => $term['id']]);
        flash('success', "Reports for {$class['name']} withdrawn. Scores and remarks can be corrected now; parent links stop working until you release again (parents then get a “corrected report” SMS if you tick SMS).");
        redirect('reports', ['term' => $term['id']]);
    }

    private function stopIfReleased(array $class, array $term, string $back): void
    {
        if (Results::released((int) $class['id'], (int) $term['id'])) {
            flash('error', "Reports for {$class['name']} are released to parents, so remarks are locked. Withdraw the release in Report cards to make corrections.");
            redirect($back, ['term' => $term['id']]);
        }
    }

    /** Public view for parents (link from SMS). */
    public function shared(string $token): void
    {
        $link = db()->one('SELECT * FROM report_links WHERE token = ?', [$token]) ?? $this->notFound();
        $term = db()->one('SELECT * FROM terms WHERE id = ?', [$link['term_id']]);
        $classId = (int) db()->value('SELECT class_id FROM enrolments WHERE pupil_id = ? AND academic_year_id = ?', [$link['pupil_id'], $term['academic_year_id']]);
        if (!$classId || !db()->value('SELECT id FROM report_releases WHERE term_id = ? AND class_id = ?', [$term['id'], $classId])) {
            $this->notFound();
        }
        db()->run('UPDATE report_links SET views = views + 1, last_viewed_at = UTC_TIMESTAMP() WHERE token = ?', [$token]);
        header('X-Robots-Tag: noindex');
        $R = Results::classResults($classId, (int) $term['id']);
        $this->view('reports/print', ['title' => 'Report card', 'R' => $R, 'only' => (int) $link['pupil_id'], 'shared' => true], 'layouts/print');
    }

    // ---------------------------------------------------------------------------------

    private function absoluteUrl(string $route): string
    {
        return absolute_url($route);
    }

    private function isLastTerm(array $term): bool
    {
        return !db()->value('SELECT id FROM terms WHERE academic_year_id = ? AND term_no > ?', [$term['academic_year_id'], $term['term_no']]);
    }

    /** @return array{0:array,1:array} */
    private function classTerm(int $classId): array
    {
        $class = db()->one('SELECT * FROM classes WHERE id = ?', [$classId]) ?? $this->notFound();
        if (!Results::canViewClass($class)) {
            http_response_code(403);
            $this->view('errors/403', ['title' => 'Access denied']);
            exit;
        }
        [$term] = $this->termParam(isset($_POST['term_id']) ? (int) $_POST['term_id'] : null);
        if (!$term) {
            $this->notFound();
        }
        return [$class, $term];
    }
}
