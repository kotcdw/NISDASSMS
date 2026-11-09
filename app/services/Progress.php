<?php
declare(strict_types=1);

/**
 * A pupil's progress across terms: each subject's total (out of 100) and the overall average, term by term.
 * Used on the pupil profile (staff) and the parent portal (released report cards only).
 */
final class Progress
{
    /**
     * @param bool $releasedOnly only terms whose report cards were released to parents
     * @return array{terms: list<array{id:int,label:string,name:string,class:string,average:?float}>, subjects: array<string, array<int,?float>>}
     */
    public static function pupil(int $pupilId, bool $releasedOnly = false): array
    {
        $sql = 'SELECT t.id, t.name, t.term_no, y.name AS year_name, e.class_id, c.name AS class_name, l.category
                FROM enrolments e JOIN terms t ON t.academic_year_id = e.academic_year_id
                JOIN academic_years y ON y.id = t.academic_year_id
                JOIN classes c ON c.id = e.class_id JOIN levels l ON l.id = c.level_id
                WHERE e.pupil_id = ? AND t.start_date <= CURDATE()';
        if ($releasedOnly) {
            $sql .= ' AND EXISTS (SELECT 1 FROM report_releases rr WHERE rr.term_id = t.id AND rr.class_id = e.class_id)';
        }
        $terms = [];
        $subjects = [];
        foreach (db()->all($sql . ' ORDER BY t.start_date', [$pupilId]) as $t) {
            $scheme = Results::scheme((string) $t['category'], (int) $t['id']);
            $vals = [];
            foreach (db()->all(
                'SELECT cs.id, s.name FROM class_subjects cs JOIN subjects s ON s.id = cs.subject_id
                 WHERE cs.class_id = ? AND EXISTS (SELECT 1 FROM assessments a WHERE a.term_id = ? AND a.class_subject_id = cs.id)
                 ORDER BY s.is_core DESC, s.name',
                [$t['class_id'], $t['id']]
            ) as $cs) {
                $total = Results::subjectScores((int) $t['id'], (int) $cs['id'], [$pupilId], $scheme)[$pupilId]['total'] ?? null;
                if ($total !== null) {
                    $vals[$cs['name']] = (float) $total;
                }
            }
            if (!$vals) {
                continue;
            }
            $i = count($terms);
            $terms[] = [
                'id' => (int) $t['id'], 'name' => $t['year_name'] . ' · ' . $t['name'], 'class' => $t['class_name'],
                'label' => 'T' . (int) $t['term_no'] . ' ' . self::shortYear((string) $t['year_name']),
                'average' => round(array_sum($vals) / count($vals), 1),
            ];
            foreach ($vals as $name => $v) {
                $subjects[$name][$i] = $v;
            }
        }
        // Fill gaps so every subject has a value (or null) for every term
        foreach ($subjects as $name => $byTerm) {
            $row = [];
            foreach ($terms as $i => $_) {
                $row[$i] = $byTerm[$i] ?? null;
            }
            $subjects[$name] = $row;
        }
        return ['terms' => $terms, 'subjects' => $subjects];
    }

    /** "2025/2026" → "25/26" */
    public static function shortYear(string $y): string
    {
        return preg_match('/^(\d{4})\D+(\d{4})$/', $y, $m) ? substr($m[1], 2) . '/' . substr($m[2], 2) : $y;
    }

    /** Change from the previous term with a value: [arrow, css class, text] */
    public static function trend(array $row): array
    {
        $vals = array_values(array_filter($row, fn($v) => $v !== null));
        if (count($vals) < 2) {
            return ['', '', ''];
        }
        $d = round($vals[count($vals) - 1] - $vals[count($vals) - 2], 1);
        if (abs($d) < 0.05) {
            return ['→', 'muted', 'no change'];
        }
        return $d > 0 ? ['▲', 'trend-up', '+' . Results::fmt($d)] : ['▼', 'trend-down', Results::fmt($d)];
    }
}
