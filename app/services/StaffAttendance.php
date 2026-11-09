<?php
declare(strict_types=1);

/**
 * Staff sign-in summaries: late arrivals, days present/absent, % and usual arrival time over a period,
 * counted against school days (Mon–Fri, inside a term, not closed on the calendar, not after today).
 */
final class StaffAttendance
{
    private static ?array $shifts = null;

    /** Late-arrival time "HH:MM": the start of the person's shift, else the school setting (staff_late_after, default 07:30). */
    public static function lateAfter(?int $userId = null): string
    {
        if ($userId && ($s = self::shiftOf($userId))) {
            return substr((string) $s['start_time'], 0, 5);
        }
        $t = setting('staff_late_after', '07:30');
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t) ? $t : '07:30';
    }

    public static function isLate(?string $timeIn, ?int $userId = null): bool
    {
        return $timeIn !== null && $timeIn !== '' && substr($timeIn, 0, 5) > self::lateAfter($userId);
    }

    /** The shift a staff member is assigned to (null = the normal school day). */
    public static function shiftOf(int $userId): ?array
    {
        if (self::$shifts === null) {
            self::$shifts = [];
            try {
                foreach (db()->all('SELECT a.user_id, s.* FROM staff_shift_assign a JOIN staff_shifts s ON s.id = a.shift_id WHERE s.is_active = 1') as $r) {
                    self::$shifts[(int) $r['user_id']] = $r;
                }
            } catch (PDOException $e) {
                // migration 017 not applied yet
            }
        }
        return self::$shifts[$userId] ?? null;
    }

    public static function forgetShifts(): void
    {
        self::$shifts = null;
    }

    /** School days in a range: weekdays inside a term, not closed, not after today. */
    public static function schoolDays(string $from, string $to): array
    {
        $days = Attendance::schoolDays($from, $to);
        if (!$days) {
            return [];
        }
        $terms = db()->all('SELECT start_date, end_date FROM terms WHERE start_date <= ? AND end_date >= ?', [end($days), $days[0]]);
        return array_values(array_filter($days, function ($d) use ($terms) {
            foreach ($terms as $t) {
                if ($d >= $t['start_date'] && $d <= $t['end_date']) {
                    return true;
                }
            }
            return false;
        }));
    }

    /** Active staff (everyone who is not a parent or pupil), optionally one person. */
    public static function staff(?int $userId = null): array
    {
        return db()->all(
            "SELECT id, name, role FROM users WHERE is_active = 1 AND role NOT IN ('parent','pupil'" . ($userId ? '' : ",'super_admin'") . ')' . ($userId ? ' AND id = ?' : '') . ' ORDER BY name',
            $userId ? [$userId] : []
        );
    }

    /**
     * Summary per person for the period, plus the day-by-day marks.
     * @return array{days:list<string>, rows:list<array>}
     */
    public static function summary(string $from, string $to, ?int $userId = null): array
    {
        $days = self::schoolDays($from, $to);
        $dayset = array_flip($days);
        $today = date('Y-m-d');
        $marks = [];
        foreach (db()->all(
            'SELECT user_id, date, time_in, time_out FROM staff_attendance WHERE date BETWEEN ? AND ?' . ($userId ? ' AND user_id = ?' : ''),
            $userId ? [$from, $to, $userId] : [$from, $to]
        ) as $r) {
            $marks[(int) $r['user_id']][$r['date']] = $r;
        }
        $leave = Registers::leaveMap($from, $to, $userId); // approved staff leave: "On leave", not absent
        $rows = [];
        foreach (self::staff($userId) as $u) {
            $late = self::lateAfter((int) $u['id']);
            $onLeave = $leave[(int) $u['id']] ?? [];
            $leaveN = 0;
            $m = $marks[(int) $u['id']] ?? [];
            $present = $lateN = $noOut = 0;
            $mins = [];
            $grid = [];
            foreach ($days as $d) {
                $r = $m[$d] ?? null;
                if ($r && $r['time_in']) {
                    $present++;
                    $isLate = substr($r['time_in'], 0, 5) > $late;
                    $lateN += $isLate ? 1 : 0;
                    $mins[] = (int) substr($r['time_in'], 0, 2) * 60 + (int) substr($r['time_in'], 3, 2);
                    if (!$r['time_out'] && $d < $today) {
                        $noOut++;
                    }
                    $grid[$d] = $isLate ? 'L' : 'P';
                } elseif (isset($onLeave[$d])) {
                    $leaveN++;
                    $grid[$d] = 'V';
                } else {
                    $grid[$d] = 'A';
                }
            }
            $extra = count(array_filter(array_keys($m), fn($d) => !isset($dayset[$d])));
            $avg = $mins ? (int) round(array_sum($mins) / count($mins)) : null;
            $rows[] = $u + [
                'present' => $present, 'absent' => count($days) - $present - $leaveN, 'late' => $lateN, 'no_out' => $noOut, 'extra' => $extra, 'leave' => $leaveN,
                'pct' => count($days) - $leaveN > 0 ? round($present / (count($days) - $leaveN) * 100, 1) : null,
                'avg_in' => $avg === null ? null : sprintf('%02d:%02d', intdiv($avg, 60), $avg % 60),
                'grid' => $grid,
            ];
        }
        return ['days' => $days, 'rows' => $rows];
    }

    /** Resolve a period choice to [from, to, label]. */
    public static function period(string $p, ?string $from, ?string $to): array
    {
        $today = date('Y-m-d');
        if ($p === 'custom' && Validator::isDate((string) $from) && Validator::isDate((string) $to) && $from <= $to) {
            return [$from, $to, date('j M', strtotime($from)) . ' – ' . date('j M Y', strtotime($to))];
        }
        if ($p === 'month') {
            return [date('Y-m-01'), $today, date('F Y')];
        }
        if ($p === 'term' && ($t = current_term())) {
            return [$t['start_date'], min($today, $t['end_date']), $t['year_name'] . ' · ' . $t['name']];
        }
        $mon = date('Y-m-d', strtotime('monday this week'));
        return [$mon, $today, 'This week (from ' . date('j M', strtotime($mon)) . ')'];
    }
}
