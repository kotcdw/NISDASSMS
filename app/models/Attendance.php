<?php
declare(strict_types=1);

final class Attendance
{
    public const STATUSES = [
        'present' => ['Present', 'P'],
        'late'    => ['Late', 'L'],
        'absent'  => ['Absent', 'A'],
        'excused' => ['Excused', 'E'],
        'sick'    => ['Sick', 'S'],
    ];


    /** Dates (Y-m-d => event title) in a range when the calendar says school is closed. */
    public static function closedDates(string $from, string $to): array
    {
        $out = [];
        foreach (db()->all('SELECT title, start_date, end_date FROM calendar_events WHERE is_school_closed = 1 AND start_date <= ? AND end_date >= ?', [$to, $from]) as $ev) {
            $d = new DateTime(max($ev['start_date'], $from));
            $end = new DateTime(min($ev['end_date'], $to));
            while ($d <= $end) {
                $out[$d->format('Y-m-d')] = $ev['title'];
                $d->modify('+1 day');
            }
        }
        return $out;
    }

    /** Why a register cannot be taken on this date, or null if it can. */
    public static function blockedReason(string $date): ?string
    {
        if ($date > date('Y-m-d')) {
            return 'You cannot take a register for a future date.';
        }
        $dow = (int) date('N', strtotime($date));
        if ($dow === 6) {
            return 'Saturday is the Sabbath — no register is taken.';
        }
        if ($dow === 7) {
            return 'Sunday — no school.';
        }
        $closed = self::closedDates($date, $date);
        if ($closed) {
            return 'School is closed: ' . reset($closed) . '.';
        }
        if (!db()->value('SELECT id FROM terms WHERE ? BETWEEN start_date AND end_date', [$date])) {
            return 'This date is outside every term (vacation).';
        }
        return null;
    }

    /** School days (Mon–Fri, not closed) between two dates, inclusive, never beyond today. */
    public static function schoolDays(string $from, string $to): array
    {
        $to = min($to, date('Y-m-d'));
        if ($to < $from) {
            return [];
        }
        $closed = self::closedDates($from, $to);
        $days = [];
        $d = new DateTime($from);
        $end = new DateTime($to);
        while ($d <= $end) {
            $k = $d->format('Y-m-d');
            if ((int) $d->format('N') <= 5 && !isset($closed[$k])) {
                $days[] = $k;
            }
            $d->modify('+1 day');
        }
        return $days;
    }

    /** Classes the current user may take registers for. */
    public static function classesForUser(): array
    {
        $sql = 'SELECT c.*, l.name AS level_name, u.name AS teacher_name FROM classes c JOIN levels l ON l.id = c.level_id LEFT JOIN users u ON u.id = c.class_teacher_id WHERE c.is_active = 1';
        $p = [];
        if (!can('attendance.all')) {
            $sql .= ' AND c.class_teacher_id = ?';
            $p[] = Auth::id();
        }
        return db()->all($sql . ' ORDER BY l.sort_order, c.name', $p);
    }

    public static function canAccessClass(array $class): bool
    {
        return can('attendance.all') || (int) $class['class_teacher_id'] === Auth::id();
    }

    /** Active pupils in a class for the current academic year. */
    public static function roster(int $classId, ?string $onDate = null): array
    {
        $yid = current_year()['id'] ?? 0;
        $sql = "SELECT p.id, p.admission_no, p.first_name, p.middle_name, p.last_name, p.gender, p.photo_path
                FROM enrolments e JOIN pupils p ON p.id = e.pupil_id
                WHERE e.class_id = ? AND e.academic_year_id = ? AND e.status = 'active' AND p.status = 'active' AND p.deleted_at IS NULL";
        $params = [$classId, $yid];
        if ($onDate) {
            $sql .= ' AND e.enrolled_on <= ?';
            $params[] = $onDate;
        }
        return db()->all($sql . ' ORDER BY p.last_name, p.first_name', $params);
    }

    public static function percent(int $present, int $marked): ?float
    {
        return $marked > 0 ? round($present * 100 / $marked, 1) : null;
    }

    public static function meter(?float $pct): string
    {
        if ($pct === null) {
            return '<span class="muted">—</span>';
        }
        $cls = $pct < 80 ? 'low' : ($pct < 90 ? 'mid' : '');
        return '<div style="display:flex;gap:.4rem;align-items:center"><div class="meter ' . $cls . '"><span style="width:' . (float) $pct . '%"></span></div><small>' . e($pct) . '%</small></div>';
    }
}
