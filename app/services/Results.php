<?php
declare(strict_types=1);

/**
 * Results engine: turns raw component scores into class score, exam score,
 * total, grade, subject position, overall average/position and JHS aggregate.
 *
 *   class score = (sum of class items ÷ their maximum) × class weight   (e.g. 30)
 *   exam score  = (exam mark ÷ exam maximum) × exam weight              (e.g. 70)
 *   total       = class score + exam score                               (out of 100)
 */
final class Results
{
    private static array $schemes = [];

    /**
     * Grading scheme + bands (highest first) for a department category. With a term, the grading frozen when that
     * term's reports were first released is used, so later changes in Grading setup never rewrite released reports.
     */
    public static function scheme(string $category, ?int $termId = null): array
    {
        if ($termId) {
            $key = $category . '#' . $termId;
            if (!isset(self::$schemes[$key])) {
                $snap = self::snapshot($termId, $category);
                if ($snap === null && self::termReleased($termId, $category)) {
                    $snap = self::freeze($termId, $category); // released before snapshots existed
                }
                self::$schemes[$key] = $snap ?? self::scheme($category);
            }
            return self::$schemes[$key];
        }
        if (!isset(self::$schemes[$category])) {
            $s = db()->one('SELECT * FROM grading_schemes WHERE category = ?', [$category]) ?? [
                'id' => 0, 'category' => $category, 'name' => $category, 'class_weight' => 30, 'exam_weight' => 70,
                'pass_mark' => 50, 'show_position' => 1, 'use_aggregate' => 0, 'show_levels' => 0,
            ];
            $s['bands'] = $s['id'] ? self::bands((int) $s['id'], 'grade') : [];
            $s['level_bands'] = $s['id'] ? self::bands((int) $s['id'], 'level') : [];
            $s['show_levels'] = (int) ($s['show_levels'] ?? 0) && $s['level_bands'] ? 1 : 0;
            self::$schemes[$category] = $s;
        }
        return self::$schemes[$category];
    }

    private static function snapshot(int $termId, string $category): ?array
    {
        try {
            $j = db()->value('SELECT data FROM grading_snapshots WHERE term_id = ? AND category = ?', [$termId, $category]);
        } catch (PDOException $e) {
            return null; // migration 027 not applied yet
        }
        $d = $j ? json_decode((string) $j, true) : null;
        return is_array($d) ? $d + ['frozen' => true] : null;
    }

    /** Freeze the current grading for a term (called when a class's reports are released). */
    public static function freeze(int $termId, string $category): array
    {
        $live = self::scheme($category);
        try {
            db()->run('INSERT IGNORE INTO grading_snapshots (term_id, category, data) VALUES (?, ?, ?)', [$termId, $category, json_encode($live)]);
        } catch (PDOException $e) {
            return $live;
        }
        unset(self::$schemes[$category . '#' . $termId]);
        return self::snapshot($termId, $category) ?? $live;
    }

    /** Has any class of this department released reports for the term? */
    private static function termReleased(int $termId, string $category): bool
    {
        return (bool) db()->value(
            'SELECT 1 FROM report_releases rr JOIN classes c ON c.id = rr.class_id JOIN levels l ON l.id = c.level_id WHERE rr.term_id = ? AND l.category = ? LIMIT 1',
            [$termId, $category]
        );
    }

    /** Are this class's reports for the term released to parents? */
    public static function released(int $classId, int $termId): bool
    {
        return (bool) db()->value('SELECT 1 FROM report_releases WHERE class_id = ? AND term_id = ?', [$classId, $termId]);
    }

    /** Students of a class in a term's academic year (the same list the report cards use). */
    public static function roster(int $classId, int $termId): array
    {
        return db()->all(
            "SELECT p.id, p.admission_no, p.first_name, p.middle_name, p.last_name, p.gender, p.photo_path
             FROM enrolments e JOIN pupils p ON p.id = e.pupil_id JOIN terms t ON t.academic_year_id = e.academic_year_id AND t.id = ?
             WHERE e.class_id = ? AND e.status IN ('active','promoted','repeated') AND p.deleted_at IS NULL
             ORDER BY p.last_name, p.first_name",
            [$termId, $classId]
        );
    }

    /** Bands of one set ('grade' or 'level'), highest first. */
    public static function bands(int $schemeId, string $set = 'grade'): array
    {
        return db()->all('SELECT * FROM grade_bands WHERE scheme_id = ? AND band_set = ? ORDER BY min_score DESC', [$schemeId, $set]);
    }

    /** NaCCA proficiency level for a total (JHS: HP, P, AP, D, E). @return array{0:string,1:string} */
    public static function level(array $scheme, ?float $total): array
    {
        return empty($scheme['show_levels']) ? ['', ''] : self::grade(['bands' => $scheme['level_bands']], $total);
    }

    /** @return array{0:string,1:string} [grade, remark] */
    public static function grade(array $scheme, ?float $total): array
    {
        if ($total === null) {
            return ['', ''];
        }
        foreach ($scheme['bands'] as $b) {
            if ($total >= (float) $b['min_score']) {
                return [$b['grade'], $b['remark']];
            }
        }
        return ['', ''];
    }

    /** Competition ranking (1, 2, 2, 4). @param array<int,float|null> $values @return array<int,int|null> */
    public static function rank(array $values): array
    {
        $valid = array_filter($values, fn($v) => $v !== null);
        arsort($valid);
        $ranks = array_fill_keys(array_keys($values), null);
        $pos = 0;
        $prev = null;
        $shown = 0;
        foreach ($valid as $k => $v) {
            $pos++;
            if ($prev === null || abs($v - $prev) > 0.0001) {
                $shown = $pos;
            }
            $ranks[$k] = $shown;
            $prev = $v;
        }
        return $ranks;
    }

    public static function ordinal(?int $n): string
    {
        if (!$n) {
            return '';
        }
        $s = ['th', 'st', 'nd', 'rd'];
        $v = $n % 100;
        return $n . ($s[($v - 20) % 10] ?? $s[$v] ?? $s[0]);
    }

    /** Ensure a score sheet + default components exist for this subject/term. */
    public static function ensureSheet(int $termId, array $cs, string $category): array
    {
        $sheet = db()->one('SELECT * FROM score_sheets WHERE term_id = ? AND class_subject_id = ?', [$termId, $cs['id']]);
        if ($sheet) {
            return $sheet;
        }
        $scheme = self::scheme($category, $termId);
        db()->transaction(function (Database $d) use ($termId, $cs, $scheme) {
            $d->run('INSERT IGNORE INTO score_sheets (term_id, class_subject_id) VALUES (?, ?)', [$termId, $cs['id']]);
            if (!$d->value('SELECT COUNT(*) FROM assessments WHERE term_id = ? AND class_subject_id = ?', [$termId, $cs['id']])) {
                $d->insert('assessments', ['term_id' => $termId, 'class_subject_id' => $cs['id'], 'kind' => 'class', 'title' => 'Class score', 'max_mark' => $scheme['class_weight'], 'sort_order' => 1, 'created_by' => Auth::id()]);
                $d->insert('assessments', ['term_id' => $termId, 'class_subject_id' => $cs['id'], 'kind' => 'exam', 'title' => 'End of term exam', 'max_mark' => 100, 'sort_order' => 99, 'created_by' => Auth::id()]);
            }
        });
        return db()->one('SELECT * FROM score_sheets WHERE term_id = ? AND class_subject_id = ?', [$termId, $cs['id']]);
    }

    /**
     * Compute one subject for a list of pupils. A student marked absent for the exam (and with no exam mark) gets
     * absent = true and no total, so they are left out of positions; the report shows ABS.
     * @return array<int,array{class:?float,exam:?float,total:?float,absent:bool}>
     */
    public static function subjectScores(int $termId, int $csId, array $pupilIds, array $scheme): array
    {
        $comps = db()->all('SELECT * FROM assessments WHERE term_id = ? AND class_subject_id = ?', [$termId, $csId]);
        $out = array_fill_keys($pupilIds, ['class' => null, 'exam' => null, 'total' => null, 'absent' => false]);
        if (!$comps || !$pupilIds) {
            return $out;
        }
        $max = ['class' => 0.0, 'exam' => 0.0];
        $kindOf = [];
        foreach ($comps as $c) {
            $max[$c['kind']] += (float) $c['max_mark'];
            $kindOf[$c['id']] = $c['kind'];
        }
        $sum = [];
        $examAbsent = [];
        $examMarked = [];
        $in = implode(',', array_fill(0, count($comps), '?'));
        foreach (db()->all("SELECT assessment_id, pupil_id, score, is_absent FROM assessment_scores WHERE assessment_id IN ($in)", array_column($comps, 'id')) as $s) {
            if ($s['score'] === null && !(int) $s['is_absent']) {
                continue;
            }
            $k = $kindOf[$s['assessment_id']];
            if ($k === 'exam') {
                if ((int) $s['is_absent']) {
                    $examAbsent[$s['pupil_id']] = true;
                } else {
                    $examMarked[$s['pupil_id']] = true;
                }
            }
            $sum[$s['pupil_id']][$k] = ($sum[$s['pupil_id']][$k] ?? 0) + (float) $s['score']; // absent counts as 0
        }
        foreach ($pupilIds as $pid) {
            $cls = isset($sum[$pid]['class']) && $max['class'] > 0 ? $sum[$pid]['class'] / $max['class'] * (float) $scheme['class_weight'] : null;
            $exm = isset($sum[$pid]['exam']) && $max['exam'] > 0 ? $sum[$pid]['exam'] / $max['exam'] * (float) $scheme['exam_weight'] : null;
            $cls = $cls === null ? null : round($cls, 1);
            $exm = $exm === null ? null : round($exm, 1);
            if (!empty($examAbsent[$pid]) && empty($examMarked[$pid])) {
                $out[$pid] = ['class' => $cls, 'exam' => null, 'total' => null, 'absent' => true];
                continue;
            }
            // total from the rounded parts, so class + exam always add up to the printed total
            $out[$pid] = ['class' => $cls, 'exam' => $exm, 'total' => ($cls === null && $exm === null) ? null : round(($cls ?? 0) + ($exm ?? 0), 1), 'absent' => false];
        }
        return $out;
    }

    /**
     * Full class results for a term (broadsheet + report cards).
     */
    public static function classResults(int $classId, int $termId): array
    {
        $class = db()->one('SELECT c.*, l.name AS level_name, l.category, l.sort_order AS level_order FROM classes c JOIN levels l ON l.id = c.level_id WHERE c.id = ?', [$classId]);
        $term = db()->one('SELECT t.*, y.name AS year_name FROM terms t JOIN academic_years y ON y.id = t.academic_year_id WHERE t.id = ?', [$termId]);
        $scheme = self::scheme($class['category'], $termId);
        $subjects = db()->all(
            "SELECT cs.id, cs.subject_id, cs.teacher_id, s.name, s.code, s.is_core, u.name AS teacher_name,
                    (SELECT status FROM score_sheets ss WHERE ss.class_subject_id = cs.id AND ss.term_id = ?) AS sheet_status
             FROM class_subjects cs JOIN subjects s ON s.id = cs.subject_id LEFT JOIN users u ON u.id = cs.teacher_id
             WHERE cs.class_id = ? ORDER BY s.is_core DESC, s.name",
            [$termId, $classId]
        );
        $pupils = db()->all(
            "SELECT p.* FROM enrolments e JOIN pupils p ON p.id = e.pupil_id
             WHERE e.class_id = ? AND e.academic_year_id = ? AND e.status IN ('active','promoted','repeated') AND p.deleted_at IS NULL
             ORDER BY p.last_name, p.first_name",
            [$classId, $term['academic_year_id']]
        );
        $pids = array_map(fn($p) => (int) $p['id'], $pupils);

        $scores = [];
        $subjectStats = [];
        foreach ($subjects as $s) {
            $sub = self::subjectScores($termId, (int) $s['id'], $pids, $scheme);
            $ranks = self::rank(array_map(fn($r) => $r['total'], $sub));
            $totals = array_filter(array_map(fn($r) => $r['total'], $sub), fn($v) => $v !== null);
            foreach ($sub as $pid => $r) {
                [$g, $rem] = $r['absent'] ? ['ABS', 'Absent from exam'] : self::grade($scheme, $r['total']);
                [$lv, $lvRem] = $r['absent'] ? ['', ''] : self::level($scheme, $r['total']);
                $scores[$pid][$s['id']] = $r + ['grade' => $g, 'remark' => $lvRem !== '' ? $lvRem : $rem, 'grade_remark' => $rem, 'level' => $lv, 'position' => $ranks[$pid]];
            }
            $subjectStats[$s['id']] = [
                'count'   => count($totals),
                'average' => $totals ? round(array_sum($totals) / count($totals), 1) : null,
                'highest' => $totals ? max($totals) : null,
                'lowest'  => $totals ? min($totals) : null,
                'passed'  => count(array_filter($totals, fn($v) => $v >= (float) $scheme['pass_mark'])),
            ];
        }

        // Overall position only for students with a result in every subject that has results (no gaps, no ABS)
        $active = array_values(array_filter(array_column($subjects, 'id'), fn($sid) => ($subjectStats[$sid]['count'] ?? 0) > 0));
        $summary = [];
        foreach ($pids as $pid) {
            $vals = [];
            $complete = (bool) $active;
            foreach ($subjects as $s) {
                $t = $scores[$pid][$s['id']]['total'] ?? null;
                if ($t !== null) {
                    $vals[] = $t;
                }
            }
            foreach ($active as $sid) {
                if (($scores[$pid][$sid]['total'] ?? null) === null) {
                    $complete = false;
                }
            }
            $summary[$pid] = [
                'total'     => $vals ? round(array_sum($vals), 1) : null,
                'subjects'  => count($vals),
                'average'   => $vals ? round(array_sum($vals) / count($vals), 1) : null,
                'aggregate' => $scheme['use_aggregate'] ? self::aggregate($subjects, $scores[$pid] ?? []) : null,
                'ranked'    => $complete,
            ];
        }
        $posRanks = self::rank(array_map(fn($s) => $s['ranked'] ? $s['average'] : null, $summary));
        foreach ($summary as $pid => &$s) {
            $s['position'] = $posRanks[$pid];
        }
        unset($s);
        $rankedCount = count(array_filter($summary, fn($s) => $s['ranked']));

        return [
            'class' => $class, 'term' => $term, 'scheme' => $scheme, 'subjects' => $subjects, 'pupils' => $pupils,
            'scores' => $scores, 'summary' => $summary, 'subjectStats' => $subjectStats, 'rankedCount' => $rankedCount,
            'attendance' => self::attendance($classId, $term), 'remarks' => self::remarks($termId, $pids),
            'nextTerm' => db()->one('SELECT * FROM terms WHERE start_date > ? ORDER BY start_date LIMIT 1', [$term['end_date']]),
        ];
    }

    /** WAEC core subject codes (School setup setting, default English, Maths, Science, Social Studies). */
    public static function coreCodes(): array
    {
        return array_values(array_filter(array_map(fn($c) => strtoupper(trim($c)), explode(',', (string) setting('bece_core_subjects', 'ENG,MATH,SCI,SOC')))));
    }

    /**
     * BECE-style aggregate: the 4 core subjects + the best 2 other BECE subjects (lower is better).
     * Bible Studies is a school subject, not a BECE subject, so it never counts. Null if any core is missing.
     */
    public static function aggregate(array $subjects, array $pupilScores): ?int
    {
        $coreCodes = self::coreCodes();
        $core = [];
        $others = [];
        foreach ($subjects as $s) {
            $code = strtoupper((string) ($s['code'] ?? ''));
            if ($code === 'BIB') {
                continue;
            }
            $g = $pupilScores[$s['id']]['grade'] ?? '';
            if (!ctype_digit((string) $g)) {
                continue;
            }
            if (in_array($code, $coreCodes, true)) {
                $core[$code] = (int) $g;
            } else {
                $others[] = (int) $g;
            }
        }
        return self::bestSix($core, $others, $coreCodes);
    }

    /**
     * The one BECE sum (report cards and the BECE results page): every core grade + the 2 best (lowest) other grades.
     * $core is code => grade, $others a list of grades. Null if a core is missing or fewer than 2 others.
     */
    public static function bestSix(array $core, array $others, array $coreCodes): ?int
    {
        if (!$coreCodes || array_diff($coreCodes, array_keys($core)) || count($others) < 2) {
            return null;
        }
        sort($others);
        return array_sum(array_intersect_key($core, array_flip($coreCodes))) + $others[0] + $others[1];
    }

    /** Days present / days the register was taken, per pupil, for a class in a term. */
    public static function attendance(int $classId, array $term): array
    {
        $taken = (int) db()->value('SELECT COUNT(*) FROM attendance_registers WHERE class_id = ? AND date BETWEEN ? AND ?', [$classId, $term['start_date'], $term['end_date']]);
        $out = ['_days' => $taken];
        foreach (db()->all(
            "SELECT ar.pupil_id, SUM(ar.status IN ('present','late')) AS present FROM attendance_records ar
             JOIN enrolments e ON e.pupil_id = ar.pupil_id AND e.class_id = ? AND e.academic_year_id = ?
             WHERE ar.date BETWEEN ? AND ? GROUP BY ar.pupil_id",
            [$classId, $term['academic_year_id'], $term['start_date'], $term['end_date']]
        ) as $r) {
            $out[(int) $r['pupil_id']] = (int) $r['present'];
        }
        return $out;
    }

    public static function remarks(int $termId, array $pids): array
    {
        if (!$pids) {
            return [];
        }
        $in = implode(',', array_fill(0, count($pids), '?'));
        $out = [];
        foreach (db()->all("SELECT * FROM report_remarks WHERE term_id = ? AND pupil_id IN ($in)", array_merge([$termId], $pids)) as $r) {
            $out[(int) $r['pupil_id']] = $r;
        }
        return $out;
    }

    public static function fmt(?float $v): string
    {
        return $v === null ? '—' : rtrim(rtrim(number_format($v, 1), '0'), '.');
    }

    /** Can the current user enter scores for this class subject? */
    public static function canEnter(array $cs): bool
    {
        if (can('exams.manage')) {
            return true;
        }
        if (!can('exams.enter')) {
            return false;
        }
        return (int) ($cs['teacher_id'] ?? 0) === Auth::id() || (int) ($cs['class_teacher_id'] ?? 0) === Auth::id();
    }

    /** Can the current user see reports for this class? */
    public static function canViewClass(array $class): bool
    {
        return can('exams.manage') || (can('reports.view') && (int) $class['class_teacher_id'] === Auth::id());
    }
}
