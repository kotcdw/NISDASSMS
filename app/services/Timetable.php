<?php
declare(strict_types=1);

/**
 * Time table engine.
 * Teacher clashes are checked by TIME OVERLAP (not period number), because
 * KG/Creche and Basic/JHS run different bell schedules.
 */
final class Timetable
{
    public const DAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday'];
    public const KINDS = ['lesson' => 'Lesson', 'worship' => 'Worship / devotion', 'assembly' => 'Assembly', 'break' => 'Break', 'lunch' => 'Lunch', 'other' => 'Other'];

    public static function periods(string $category, bool $lessonsOnly = false): array
    {
        return db()->all(
            'SELECT * FROM timetable_periods WHERE category = ?' . ($lessonsOnly ? " AND kind = 'lesson'" : '') . ' ORDER BY sort_order, start_time',
            [$category]
        );
    }

    /** Copy the current subject teacher (or class teacher) onto every slot. */
    public static function syncTeachers(): void
    {
        db()->run(
            'UPDATE timetable_slots ts
             JOIN class_subjects cs ON cs.id = ts.class_subject_id
             JOIN classes c ON c.id = ts.class_id
             SET ts.teacher_id = COALESCE(cs.teacher_id, c.class_teacher_id)'
        );
    }

    /** All teacher bookings: teacher => day => list of [start, end, class_id, slot_id]. */
    public static function busyMap(array $excludeClassIds = []): array
    {
        $sql = 'SELECT ts.id, ts.class_id, ts.day, ts.teacher_id, p.start_time, p.end_time FROM timetable_slots ts
                JOIN timetable_periods p ON p.id = ts.period_id WHERE ts.teacher_id IS NOT NULL';
        $params = [];
        if ($excludeClassIds) {
            $sql .= ' AND ts.class_id NOT IN (' . implode(',', array_fill(0, count($excludeClassIds), '?')) . ')';
            $params = array_values($excludeClassIds);
        }
        $map = [];
        foreach (db()->all($sql, $params) as $r) {
            $map[(int) $r['teacher_id']][(int) $r['day']][] = [$r['start_time'], $r['end_time'], (int) $r['class_id'], (int) $r['id']];
        }
        return $map;
    }

    public static function overlaps(string $s1, string $e1, string $s2, string $e2): bool
    {
        return $s1 < $e2 && $s2 < $e1;
    }

    /** Is the teacher free at this time (ignoring bookings in $ignoreClassId)? Returns the clashing class id or 0. */
    public static function teacherClash(array $busy, int $teacherId, int $day, string $start, string $end, int $ignoreClassId = 0): int
    {
        foreach ($busy[$teacherId][$day] ?? [] as [$s, $e, $classId]) {
            if ($classId !== $ignoreClassId && self::overlaps($start, $end, $s, $e)) {
                return $classId;
            }
        }
        return 0;
    }

    /** Clash list across the whole school. */
    public static function clashes(): array
    {
        $rows = db()->all(
            'SELECT ts.id, ts.class_id, ts.day, ts.teacher_id, p.start_time, p.end_time, p.label, c.name AS class_name, u.name AS teacher_name, s.name AS subject_name
             FROM timetable_slots ts JOIN timetable_periods p ON p.id = ts.period_id JOIN classes c ON c.id = ts.class_id
             JOIN users u ON u.id = ts.teacher_id LEFT JOIN class_subjects cs ON cs.id = ts.class_subject_id LEFT JOIN subjects s ON s.id = cs.subject_id
             ORDER BY ts.teacher_id, ts.day, p.start_time'
        );
        $out = [];
        $byTeacherDay = [];
        foreach ($rows as $r) {
            $byTeacherDay[$r['teacher_id'] . '-' . $r['day']][] = $r;
        }
        foreach ($byTeacherDay as $list) {
            $n = count($list);
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $a = $list[$i];
                    $b = $list[$j];
                    if ($a['class_id'] !== $b['class_id'] && self::overlaps($a['start_time'], $a['end_time'], $b['start_time'], $b['end_time'])) {
                        $out[] = ['a' => $a, 'b' => $b];
                    }
                }
            }
        }
        return $out;
    }

    /** Planned vs scheduled lessons per subject for a class. */
    public static function coverage(int $classId): array
    {
        return db()->all(
            'SELECT cs.id, s.name, s.code, cs.periods_per_week AS planned, COALESCE(cs.teacher_id, c.class_teacher_id) AS teacher_id,
                    COALESCE(t.name, ct.name) AS teacher_name,
                    (SELECT COUNT(*) FROM timetable_slots ts WHERE ts.class_subject_id = cs.id) AS scheduled
             FROM class_subjects cs JOIN subjects s ON s.id = cs.subject_id JOIN classes c ON c.id = cs.class_id
             LEFT JOIN users t ON t.id = cs.teacher_id LEFT JOIN users ct ON ct.id = c.class_teacher_id
             WHERE cs.class_id = ? ORDER BY s.is_core DESC, s.name',
            [$classId]
        );
    }

    /**
     * Automatic timetable for the given classes.
     * $mode: 'fill'        keep every existing lesson, fill the gaps
     *        'keep_manual' remove auto-placed lessons, keep ones placed by hand
     *        'replace'     start from empty
     * @return array{placed:int, unplaced:array<int,array{class:string,subject:string,missing:int}>}
     */
    public static function generate(array $classIds, string $mode = 'keep_manual', int $attempts = 40): array
    {
        $classes = [];
        foreach ($classIds as $cid) {
            $c = db()->one('SELECT c.*, l.category FROM classes c JOIN levels l ON l.id = c.level_id WHERE c.id = ? AND c.is_active = 1', [$cid]);
            if ($c) {
                $classes[(int) $c['id']] = $c;
            }
        }
        if (!$classes) {
            return ['placed' => 0, 'unplaced' => []];
        }
        $ids = array_keys($classes);
        $in = implode(',', array_fill(0, count($ids), '?'));

        db()->transaction(function (Database $d) use ($mode, $ids, $in) {
            if ($mode === 'replace') {
                $d->run("DELETE FROM timetable_slots WHERE class_id IN ($in)", $ids);
            } elseif ($mode === 'keep_manual') {
                $d->run("DELETE FROM timetable_slots WHERE class_id IN ($in) AND is_locked = 0", $ids);
            }
        });

        $baseBusy = self::busyMap(); // includes kept lessons of these classes
        // Per-class inputs
        $plan = [];
        foreach ($classes as $cid => $c) {
            $periods = self::periods($c['category'], true);
            $existing = [];
            $countPerCs = [];
            $perDay = [];
            foreach (db()->all('SELECT ts.*, p.start_time, p.end_time FROM timetable_slots ts JOIN timetable_periods p ON p.id = ts.period_id WHERE ts.class_id = ?', [$cid]) as $s) {
                $existing[$s['day'] . '-' . $s['period_id']] = true;
                if ($s['class_subject_id']) {
                    $countPerCs[$s['class_subject_id']] = ($countPerCs[$s['class_subject_id']] ?? 0) + 1;
                    $perDay[$s['class_subject_id']][$s['day']] = ($perDay[$s['class_subject_id']][$s['day']] ?? 0) + 1;
                }
            }
            $lessons = [];
            foreach (db()->all('SELECT cs.*, s.name, (s.is_core = 1 OR s.code IN (\'LL\',\'NUM\')) AS is_core FROM class_subjects cs JOIN subjects s ON s.id = cs.subject_id WHERE cs.class_id = ?', [$cid]) as $cs) {
                $need = (int) $cs['periods_per_week'] - ($countPerCs[$cs['id']] ?? 0);
                $teacher = (int) ($cs['teacher_id'] ?: $c['class_teacher_id']);
                for ($k = 0; $k < $need; $k++) {
                    $lessons[] = ['cs' => (int) $cs['id'], 'name' => $cs['name'], 'core' => (int) $cs['is_core'], 'teacher' => $teacher, 'total' => (int) $cs['periods_per_week']];
                }
            }
            $plan[$cid] = ['periods' => $periods, 'existing' => $existing, 'perDay' => $perDay, 'lessons' => $lessons, 'name' => $c['name']];
        }

        $best = null;
        for ($a = 0; $a < $attempts; $a++) {
            $busy = $baseBusy;
            $placements = [];
            $unplaced = [];
            $penalty = 0;
            $order = $ids;
            shuffle($order);
            // Classes with the most lessons to place first
            usort($order, fn($x, $y) => count($plan[$y]['lessons']) <=> count($plan[$x]['lessons']));
            foreach ($order as $cid) {
                $p = $plan[$cid];
                $taken = $p['existing'];
                $perDay = $p['perDay'];
                $lessons = $p['lessons'];
                shuffle($lessons);
                // Hardest first: subjects with a busy teacher / many periods, core first
                usort($lessons, fn($x, $y) => [$y['core'], $y['total']] <=> [$x['core'], $x['total']]);
                foreach ($lessons as $L) {
                    $maxPerDay = (int) max(1, ceil($L['total'] / 5));
                    $bestCell = null;
                    $bestScore = PHP_INT_MAX;
                    foreach (self::DAYS as $day => $_) {
                        $onDay = $perDay[$L['cs']][$day] ?? 0;
                        if ($onDay >= $maxPerDay) {
                            continue;
                        }
                        foreach ($p['periods'] as $idx => $per) {
                            $key = $day . '-' . $per['id'];
                            if (isset($taken[$key])) {
                                continue;
                            }
                            if ($L['teacher'] && self::teacherClash($busy, $L['teacher'], $day, $per['start_time'], $per['end_time'])) {
                                continue;
                            }
                            // Lower score is better: spread across days, core subjects in the morning, random tie-break
                            $score = $onDay * 100 + ($L['core'] ? $idx * 6 : 0) + random_int(0, 9);
                            if ($score < $bestScore) {
                                $bestScore = $score;
                                $bestCell = [$day, $per];
                            }
                        }
                    }
                    if (!$bestCell) {
                        $unplaced[$cid][$L['cs']] = ($unplaced[$cid][$L['cs']] ?? 0) + 1;
                        continue;
                    }
                    [$day, $per] = $bestCell;
                    $taken[$day . '-' . $per['id']] = true;
                    $perDay[$L['cs']][$day] = ($perDay[$L['cs']][$day] ?? 0) + 1;
                    if ($L['teacher']) {
                        $busy[$L['teacher']][$day][] = [$per['start_time'], $per['end_time'], $cid, 0];
                    }
                    $placements[] = [$cid, $day, (int) $per['id'], $L['cs'], $L['teacher'] ?: null];
                    $penalty += $bestScore;
                }
            }
            $missing = array_sum(array_map('array_sum', $unplaced));
            if ($best === null || $missing < $best['missing'] || ($missing === $best['missing'] && $penalty < $best['penalty'])) {
                $best = ['missing' => $missing, 'penalty' => $penalty, 'placements' => $placements, 'unplaced' => $unplaced];
                if ($missing === 0 && $a >= 5) {
                    break;
                }
            }
        }

        db()->transaction(function (Database $d) use ($best) {
            foreach ($best['placements'] as [$cid, $day, $pid, $cs, $teacher]) {
                $d->run(
                    'INSERT IGNORE INTO timetable_slots (class_id, day, period_id, class_subject_id, teacher_id, is_locked, updated_by) VALUES (?, ?, ?, ?, ?, 0, ?)',
                    [$cid, $day, $pid, $cs, $teacher, Auth::id()]
                );
            }
        });

        $report = [];
        foreach ($best['unplaced'] as $cid => $list) {
            foreach ($list as $csId => $n) {
                $report[] = ['class' => $plan[$cid]['name'], 'subject' => (string) db()->value('SELECT s.name FROM class_subjects cs JOIN subjects s ON s.id = cs.subject_id WHERE cs.id = ?', [$csId]), 'missing' => $n];
            }
        }
        return ['placed' => count($best['placements']), 'unplaced' => $report];
    }
}
