<?php
declare(strict_types=1);

/**
 * Teacher profiles and performance: assignments, time table load, attendance, class results,
 * scheme-of-work coverage and the termly appraisal (teacher's self-assessment + head's rating).
 */
final class Teachers
{
    public const RATINGS = [1 => 'Poor', 2 => 'Fair', 3 => 'Good', 4 => 'Very good', 5 => 'Excellent'];
    public const STATUS = ['open' => ['Waiting for self-assessment', 'warn'], 'self_done' => ['Ready for head\'s rating', 'info'], 'completed' => ['Completed', 'good']];

    /** Active staff who teach: teaching roles, or anyone given a class or a subject to teach. */
    public static function all(): array
    {
        $roles = Auth::sqlRoles(['head_teacher', 'academic_coordinator', 'class_teacher', 'subject_teacher']);
        return db()->all(
            "SELECT u.id, u.name, u.role, u.email, u.phone FROM users u
             WHERE u.is_active = 1 AND u.role NOT IN ('parent','pupil','super_admin')
               AND (u.role IN ($roles)
                    OR EXISTS (SELECT 1 FROM classes c WHERE c.class_teacher_id = u.id AND c.is_active = 1)
                    OR EXISTS (SELECT 1 FROM class_subjects cs WHERE cs.teacher_id = u.id))
             ORDER BY u.name"
        );
    }

    public static function user(int $id): ?array
    {
        return db()->one("SELECT id, name, role, email, phone, is_active, last_login_at, created_at FROM users WHERE id = ? AND role NOT IN ('parent','pupil')", [$id]);
    }

    /** Staff record (no pay details). */
    public static function record(int $userId): ?array
    {
        return db()->one(
            'SELECT id, user_id, staff_no, position, category, employment, start_date, gender, qualification, ntc_licence_no, ntc_expiry, phone
             FROM staff WHERE user_id = ? AND is_active = 1',
            [$userId]
        );
    }

    /** Classes they are class teacher of + subjects they teach (with lessons a week). */
    public static function assignments(int $userId): array
    {
        $classes = db()->all(
            'SELECT c.id, c.name, l.category FROM classes c JOIN levels l ON l.id = c.level_id
             WHERE c.class_teacher_id = ? AND c.is_active = 1 ORDER BY l.sort_order, c.name',
            [$userId]
        );
        $subjects = db()->all(
            'SELECT cs.id, cs.class_id, cs.subject_id, c.name AS class_name, s.name AS subject_name, s.code AS subject_code, l.category, c.class_teacher_id,
                    (SELECT COUNT(*) FROM timetable_slots ts WHERE ts.class_subject_id = cs.id) AS lessons
             FROM class_subjects cs JOIN classes c ON c.id = cs.class_id JOIN levels l ON l.id = c.level_id JOIN subjects s ON s.id = cs.subject_id
             WHERE cs.teacher_id = ? AND c.is_active = 1 ORDER BY l.sort_order, c.name, s.name',
            [$userId]
        );
        return ['classes' => $classes, 'subjects' => $subjects];
    }

    public static function lessonsPerWeek(int $userId): int
    {
        return (int) db()->value('SELECT COUNT(*) FROM timetable_slots WHERE teacher_id = ?', [$userId]);
    }

    /**
     * Sign-in record for the term so far, counted against school days (see StaffAttendance).
     * @return array{days:int, present:int, late:int, pct:?float, late_after:string}
     */
    public static function attendance(int $userId, array $term): array
    {
        $to = min(date('Y-m-d'), $term['end_date']);
        $sum = $term['start_date'] <= $to ? StaffAttendance::summary($term['start_date'], $to, $userId) : ['days' => [], 'rows' => []];
        $r = $sum['rows'][0] ?? ['present' => 0, 'late' => 0, 'pct' => null, 'avg_in' => null];
        return ['days' => count($sum['days']), 'present' => (int) $r['present'], 'late' => (int) $r['late'], 'pct' => $sum['days'] ? $r['pct'] : null,
            'avg_in' => $r['avg_in'], 'late_after' => StaffAttendance::lateAfter()];
    }

    /** Results of each class subject they teach this term: class average, pass rate, sheet status. */
    public static function results(int $userId, array $term): array
    {
        $out = [];
        foreach (self::assignments($userId)['subjects'] as $cs) {
            $scheme = Results::scheme((string) $cs['category'], (int) $term['id']);
            $pids = array_map('intval', array_column(db()->all(
                "SELECT e.pupil_id FROM enrolments e JOIN pupils p ON p.id = e.pupil_id
                 WHERE e.class_id = ? AND e.academic_year_id = ? AND e.status IN ('active','promoted','repeated') AND p.deleted_at IS NULL",
                [$cs['class_id'], $term['academic_year_id']]
            ), 'pupil_id'));
            $totals = array_values(array_filter(array_map(fn($r) => $r['total'], Results::subjectScores((int) $term['id'], (int) $cs['id'], $pids, $scheme)), fn($v) => $v !== null));
            $out[] = $cs + [
                'roll' => count($pids), 'scored' => count($totals),
                'average' => $totals ? round(array_sum($totals) / count($totals), 1) : null,
                'pass_rate' => $totals ? round(count(array_filter($totals, fn($v) => $v >= (float) $scheme['pass_mark'])) / count($totals) * 100, 1) : null,
                'highest' => $totals ? max($totals) : null,
                'sheet' => db()->value('SELECT status FROM score_sheets WHERE term_id = ? AND class_subject_id = ?', [$term['id'], $cs['id']]) ?: null,
            ];
        }
        return $out;
    }

    /** Average of their subjects' class averages (null when no scores yet). */
    public static function resultsAverage(array $results): ?float
    {
        $a = array_values(array_filter(array_column($results, 'average'), fn($v) => $v !== null));
        return $a ? round(array_sum($a) / count($a), 1) : null;
    }

    /** Scheme-of-work coverage for the subjects they teach: [planned, taught, pct]. */
    public static function coverage(int $userId, int $termId): array
    {
        $r = db()->one(
            'SELECT COUNT(*) AS planned, SUM(si.taught_on IS NOT NULL) AS taught FROM scheme_items si
             JOIN class_subjects cs ON cs.id = si.class_subject_id WHERE si.term_id = ? AND cs.teacher_id = ?',
            [$termId, $userId]
        );
        $p = (int) ($r['planned'] ?? 0);
        $t = (int) ($r['taught'] ?? 0);
        return ['planned' => $p, 'taught' => $t, 'pct' => $p ? round($t / $p * 100, 1) : null];
    }

    /** Exam papers they invigilate in a term. */
    public static function invigilation(int $userId, int $termId): array
    {
        return db()->all(
            'SELECT ep.exam_date, ep.start_time, ep.duration_min, ep.paper, c.name AS class_name, s.name AS subject_name, r.name AS room_name, es.name AS series_name
             FROM exam_papers ep JOIN exam_series es ON es.id = ep.series_id JOIN classes c ON c.id = ep.class_id JOIN subjects s ON s.id = ep.subject_id
             LEFT JOIN rooms r ON r.id = ep.room_id
             WHERE ep.invigilator_id = ? AND es.term_id = ? ORDER BY ep.exam_date, ep.start_time',
            [$userId, $termId]
        );
    }

    // ------------------------------------------------------------------ appraisal

    public static function criteria(bool $activeOnly = true): array
    {
        return db()->all('SELECT * FROM appraisal_criteria' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY sort_order, id');
    }

    public static function appraisal(int $userId, int $termId): ?array
    {
        $a = db()->one(
            'SELECT a.*, u.name AS appraiser_name FROM appraisals a LEFT JOIN users u ON u.id = a.appraised_by WHERE a.user_id = ? AND a.term_id = ?',
            [$userId, $termId]
        );
        if ($a) {
            $a['scores'] = [];
            foreach (db()->all('SELECT * FROM appraisal_scores WHERE appraisal_id = ?', [$a['id']]) as $s) {
                $a['scores'][(int) $s['criterion_id']] = $s;
            }
        }
        return $a;
    }

    /** All appraisals of one teacher, newest first. */
    public static function history(int $userId): array
    {
        return db()->all(
            'SELECT a.id, a.status, a.overall, a.completed_at, a.term_id, t.name AS term_name, y.name AS year_name
             FROM appraisals a JOIN terms t ON t.id = a.term_id JOIN academic_years y ON y.id = t.academic_year_id
             WHERE a.user_id = ? ORDER BY t.start_date DESC',
            [$userId]
        );
    }

    /** Open appraisals for the term (one per teacher) and tell each teacher. Returns how many were new. */
    public static function open(array $userIds, array $term): int
    {
        $new = [];
        foreach (array_unique(array_map('intval', $userIds)) as $uid) {
            if ($uid > 0 && !db()->value('SELECT 1 FROM appraisals WHERE user_id = ? AND term_id = ?', [$uid, $term['id']])) {
                db()->insert('appraisals', ['user_id' => $uid, 'term_id' => $term['id'], 'created_by' => Auth::id()]);
                $new[] = $uid;
            }
        }
        foreach ($new as $uid) {
            Notifier::inapp([$uid], 'Your ' . $term['name'] . ' appraisal is open', 'Please fill in your self-assessment.', 'teachers/' . $uid . '/appraisal?term=' . $term['id']);
        }
        return count($new);
    }

    /** Average of a list of 1–5 scores, one decimal. */
    public static function mean(array $scores): ?float
    {
        $v = array_values(array_filter(array_map(fn($x) => $x === null || $x === '' ? null : (int) $x, $scores), fn($x) => $x !== null && $x >= 1 && $x <= 5));
        return $v ? round(array_sum($v) / count($v), 1) : null;
    }

    public static function ratingWord(?float $v): string
    {
        return $v === null ? '—' : (self::RATINGS[(int) round($v)] ?? '');
    }
}
