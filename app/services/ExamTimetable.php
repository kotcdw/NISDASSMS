<?php
declare(strict_types=1);

/** Exam timetable: papers by class with date, time, room and invigilator, clash checks and the published notice. */
final class ExamTimetable
{
    public static function series(int $id): ?array
    {
        return db()->one(
            'SELECT es.*, t.name AS term_name, y.name AS year_name FROM exam_series es JOIN terms t ON t.id = es.term_id
             JOIN academic_years y ON y.id = t.academic_year_id WHERE es.id = ?',
            [$id]
        );
    }

    public static function papers(int $seriesId, array $f = []): array
    {
        $where = 'ep.series_id = ?';
        $p = [$seriesId];
        if (!empty($f['class'])) {
            $where .= ' AND ep.class_id = ?';
            $p[] = (int) $f['class'];
        }
        if (!empty($f['invigilator'])) {
            $where .= ' AND ep.invigilator_id = ?';
            $p[] = (int) $f['invigilator'];
        }
        return db()->all(
            "SELECT ep.*, c.name AS class_name, l.sort_order AS level_order, s.name AS subject_name, r.name AS room_name, r.capacity AS room_capacity,
                    u.name AS invigilator_name, ADDTIME(ep.start_time, SEC_TO_TIME(ep.duration_min * 60)) AS end_time
             FROM exam_papers ep JOIN classes c ON c.id = ep.class_id JOIN levels l ON l.id = c.level_id JOIN subjects s ON s.id = ep.subject_id
             LEFT JOIN rooms r ON r.id = ep.room_id LEFT JOIN users u ON u.id = ep.invigilator_id
             WHERE $where ORDER BY ep.exam_date, ep.start_time, l.sort_order, c.name",
            $p
        );
    }

    /** Is this date/time on the Sabbath (Friday from 17:30 to Saturday sunset)? */
    public static function onSabbath(string $date, string $start, int $minutes): bool
    {
        $dow = (int) date('N', strtotime($date));
        if ($dow === 6) {
            return true;
        }
        $end = date('H:i', strtotime($date . ' ' . $start) + $minutes * 60);
        return $dow === 5 && $end > '17:30';
    }

    /**
     * Problems with the timetable. @return list<array{0:string,1:string,2:int}> [tone, message, paper id]
     */
    public static function clashes(array $series, array $papers): array
    {
        $out = [];
        $rolls = Resources::rolls();
        $fmt = fn($p) => $p['class_name'] . ' ' . $p['subject_name'] . ($p['paper'] ? ' ' . $p['paper'] : '') . ' (' . date('D j M', strtotime($p['exam_date'])) . ' ' . substr($p['start_time'], 0, 5) . ')';
        $teaches = [];
        foreach (db()->all('SELECT class_id, subject_id, teacher_id FROM class_subjects WHERE teacher_id IS NOT NULL') as $r) {
            $teaches[$r['class_id'] . ':' . $r['subject_id']] = (int) $r['teacher_id'];
        }
        $n = count($papers);
        for ($i = 0; $i < $n; $i++) {
            $a = $papers[$i];
            if ($a['exam_date'] < $series['start_date'] || $a['exam_date'] > $series['end_date']) {
                $out[] = ['warn', $fmt($a) . ' is outside the exam dates (' . date('j M', strtotime($series['start_date'])) . ' – ' . date('j M', strtotime($series['end_date'])) . ').', (int) $a['id']];
            }
            if (self::onSabbath($a['exam_date'], $a['start_time'], (int) $a['duration_min'])) {
                $out[] = ['bad', $fmt($a) . ' falls on the Sabbath.', (int) $a['id']];
            }
            if ((int) date('N', strtotime($a['exam_date'])) === 7) {
                $out[] = ['warn', $fmt($a) . ' is on a Sunday.', (int) $a['id']];
            }
            if ($a['invigilator_id'] && ($teaches[$a['class_id'] . ':' . $a['subject_id']] ?? 0) === (int) $a['invigilator_id']) {
                $out[] = ['info', $fmt($a) . ': ' . $a['invigilator_name'] . ' teaches this subject to this class and is also invigilating it.', (int) $a['id']];
            }
            for ($j = $i + 1; $j < $n; $j++) {
                $b = $papers[$j];
                if ($b['exam_date'] !== $a['exam_date'] || !($a['start_time'] < $b['end_time'] && $b['start_time'] < $a['end_time'])) {
                    continue;
                }
                if ((int) $a['class_id'] === (int) $b['class_id']) {
                    $out[] = ['bad', $a['class_name'] . ' has two papers at the same time: ' . $fmt($a) . ' and ' . $fmt($b) . '.', (int) $b['id']];
                }
                // same person, overlapping papers, not in one shared room (two papers with no room count as two places)
                if ($a['invigilator_id'] && (int) $a['invigilator_id'] === (int) $b['invigilator_id'] && !($a['room_id'] && (int) $a['room_id'] === (int) $b['room_id'])) {
                    $out[] = ['bad', $a['invigilator_name'] . ' is invigilating two rooms at once: ' . $fmt($a) . ' and ' . $fmt($b) . '.', (int) $b['id']];
                }
            }
        }
        // Rooms: every class sitting in a room at overlapping times must fit in its seats (one class alone too)
        $seen = [];
        foreach ($papers as $a) {
            if (!$a['room_id'] || !$a['room_capacity']) {
                continue;
            }
            $classes = [];
            foreach ($papers as $b) {
                if ((int) $b['room_id'] === (int) $a['room_id'] && $b['exam_date'] === $a['exam_date'] && $a['start_time'] < $b['end_time'] && $b['start_time'] < $a['end_time']) {
                    $classes[(int) $b['class_id']] = true;
                }
            }
            ksort($classes);
            $key = $a['room_id'] . '|' . $a['exam_date'] . '|' . implode(',', array_keys($classes));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $pupils = array_sum(array_map(fn($cid) => $rolls[$cid] ?? 0, array_keys($classes)));
            if ($pupils > (int) $a['room_capacity']) {
                $out[] = ['bad', $a['room_name'] . ' on ' . date('D j M', strtotime($a['exam_date'])) . ' at ' . substr($a['start_time'], 0, 5) . ': '
                    . (count($classes) > 1 ? count($classes) . ' classes (' . $pupils . ' students)' : $a['class_name'] . ' (' . $pupils . ' students)') . ' but only ' . (int) $a['room_capacity'] . ' seats.', (int) $a['id']];
            }
        }
        return $out;
    }

    /** Plain-text timetable for the notice board. */
    public static function noticeBody(array $series, array $papers): string
    {
        $lines = [$series['name'] . ' — ' . date('j M', strtotime($series['start_date'])) . ' to ' . date('j M Y', strtotime($series['end_date'])) . '.', ''];
        if (trim((string) $series['instructions']) !== '') {
            $lines[] = trim((string) $series['instructions']);
            $lines[] = '';
        }
        $day = null;
        foreach ($papers as $p) {
            if ($p['exam_date'] !== $day) {
                $day = $p['exam_date'];
                $lines[] = strtoupper(date('l j F', strtotime($day)));
            }
            $lines[] = '  ' . $p['class_name'] . ': ' . $p['subject_name'] . ($p['paper'] ? ' ' . $p['paper'] : '') . ' — ' . substr($p['start_time'], 0, 5) . ' to ' . substr((string) $p['end_time'], 0, 5);
        }
        return implode("\n", $lines);
    }
}
