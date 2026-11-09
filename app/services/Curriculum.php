<?php
declare(strict_types=1);

/** Curriculum strands / sub-strands and the termly scheme of work. */
final class Curriculum
{
    public const LEVELS = ['creche' => 'Creche', 'kg' => 'KG', 'primary' => 'Basic 1–6', 'jhs' => 'JHS'];

    /** Strands with their sub-strands for a subject, optionally only those used at a level category. */
    public static function tree(int $subjectId, ?string $category = null, bool $activeOnly = true): array
    {
        $where = 'subject_id = ?' . ($activeOnly ? ' AND is_active = 1' : '');
        $params = [$subjectId];
        if ($category !== null) {
            $where .= ' AND FIND_IN_SET(?, levels)';
            $params[] = $category;
        }
        $rows = db()->all("SELECT * FROM curriculum_strands WHERE $where ORDER BY sort_order, id", $params);
        $tree = [];
        foreach ($rows as $r) {
            if ($r['parent_id'] === null) {
                $tree[(int) $r['id']] = $r + ['subs' => []];
            }
        }
        foreach ($rows as $r) {
            if ($r['parent_id'] !== null && isset($tree[(int) $r['parent_id']])) {
                $tree[(int) $r['parent_id']]['subs'][] = $r;
            }
        }
        return array_values($tree);
    }

    /** A class subject with its class, level and subject details. */
    public static function classSubject(int $csId): ?array
    {
        return db()->one(
            'SELECT cs.*, c.name AS class_name, c.class_teacher_id, l.category, l.name AS level_name, s.name AS subject_name, u.name AS teacher_name
             FROM class_subjects cs JOIN classes c ON c.id = cs.class_id JOIN levels l ON l.id = c.level_id
             JOIN subjects s ON s.id = cs.subject_id LEFT JOIN users u ON u.id = cs.teacher_id WHERE cs.id = ?',
            [$csId]
        );
    }

    /** May the signed-in user plan and tick this class subject's scheme of work? */
    public static function canPlan(array $cs): bool
    {
        if (can('curriculum.manage')) {
            return true;
        }
        $me = (int) Auth::id();
        return can('scheme.plan') && ((int) ($cs['teacher_id'] ?? 0) === $me || (int) ($cs['class_teacher_id'] ?? 0) === $me);
    }

    /** Teaching weeks in a term (rounded up, 1–20). */
    public static function weeks(array $term): int
    {
        $days = (strtotime($term['end_date']) - strtotime($term['start_date'])) / 86400 + 1;
        return max(1, min(20, (int) ceil($days / 7)));
    }

    /** Week number of today within the term (null outside the term). */
    public static function currentWeek(array $term): ?int
    {
        $today = date('Y-m-d');
        if ($today < $term['start_date'] || $today > $term['end_date']) {
            return null;
        }
        return (int) floor((strtotime($today) - strtotime($term['start_date'])) / 86400 / 7) + 1;
    }

    public static function items(int $termId, int $csId): array
    {
        return db()->all(
            'SELECT si.*, st.name AS strand_name, pst.name AS parent_strand_name, u.name AS taught_by_name
             FROM scheme_items si LEFT JOIN curriculum_strands st ON st.id = si.strand_id
             LEFT JOIN curriculum_strands pst ON pst.id = st.parent_id LEFT JOIN users u ON u.id = si.taught_by
             WHERE si.term_id = ? AND si.class_subject_id = ? ORDER BY si.week_no, si.id',
            [$termId, $csId]
        );
    }

    /** Planned / taught / overdue (planned for a past week, not taught) for a class subject. */
    public static function coverage(int $termId, int $csId, ?int $currentWeek = null): array
    {
        $r = db()->one(
            'SELECT COUNT(*) AS planned, SUM(taught_on IS NOT NULL) AS taught, SUM(taught_on IS NULL AND week_no < ?) AS overdue, MAX(taught_on) AS last_taught
             FROM scheme_items WHERE term_id = ? AND class_subject_id = ?',
            [$currentWeek ?? 0, $termId, $csId]
        );
        $p = (int) ($r['planned'] ?? 0);
        $t = (int) ($r['taught'] ?? 0);
        return ['planned' => $p, 'taught' => $t, 'overdue' => (int) ($r['overdue'] ?? 0), 'last' => $r['last_taught'] ?? null, 'pct' => $p ? round($t / $p * 100, 1) : null];
    }

    /** Draft a scheme from the curriculum: one topic per sub-strand, spread over the teaching weeks. */
    public static function draftFromCurriculum(array $cs, array $term): int
    {
        $subs = [];
        foreach (self::tree((int) $cs['subject_id'], (string) $cs['category']) as $st) {
            foreach ($st['subs'] ?: [$st] as $sub) {
                $subs[] = $sub;
            }
        }
        if (!$subs) {
            return 0;
        }
        $weeks = max(1, self::weeks($term) - 1); // leave the last week for revision and exams
        $n = count($subs);
        db()->transaction(function (Database $d) use ($subs, $weeks, $n, $cs, $term) {
            foreach ($subs as $i => $sub) {
                $d->insert('scheme_items', [
                    'term_id' => $term['id'], 'class_subject_id' => $cs['id'], 'week_no' => min($weeks, (int) floor($i * $weeks / $n) + 1),
                    'strand_id' => $sub['id'], 'topic' => mb_substr($sub['name'], 0, 200), 'created_by' => Auth::id(),
                ]);
            }
        });
        return $n;
    }
}
