<?php
declare(strict_types=1);

/**
 * Monthly staff timesheets from dashboard sign-in/out: hours per day (night shifts that end after midnight
 * count correctly), late arrivals against each person's shift, early leaving and missing sign-outs.
 * The Head approves each month; staff paid by the day are paid from the approved days.
 */
final class Timesheets
{
    /** Days to show for a person in a month: school days (or their shift days) up to today, plus any day they signed in. */
    public static function month(int $userId, string $period): array
    {
        $first = $period . '-01';
        $last = min(date('Y-m-t', strtotime($first)), date('Y-m-d'));
        $shift = StaffAttendance::shiftOf($userId);
        $marks = [];
        foreach (db()->all('SELECT * FROM staff_attendance WHERE user_id = ? AND date BETWEEN ? AND ?', [$userId, $first, $last]) as $r) {
            $marks[$r['date']] = $r;
        }
        $expected = [];
        if ($first <= $last) {
            if ($shift) {
                $days = array_map('intval', explode(',', (string) $shift['days']));
                $closed = Attendance::closedDates($first, $last);
                for ($t = strtotime($first); $t <= strtotime($last); $t += 86400) {
                    if (in_array((int) date('N', $t), $days, true) && !isset($closed[date('Y-m-d', $t)])) {
                        $expected[] = date('Y-m-d', $t);
                    }
                }
            } else {
                $expected = StaffAttendance::schoolDays($first, $last);
            }
        }
        $dates = array_unique(array_merge($expected, array_keys($marks)));
        sort($dates);
        $late = StaffAttendance::lateAfter($userId);
        $end = $shift ? substr((string) $shift['end_time'], 0, 5) : null;
        $rows = [];
        $tot = ['days' => 0, 'hours' => 0.0, 'late' => 0, 'early' => 0, 'no_out' => 0, 'absent' => 0, 'expected' => count($expected)];
        foreach ($dates as $d) {
            $r = $marks[$d] ?? null;
            $in = $r['time_in'] ?? null;
            $out = $r['time_out'] ?? null;
            $hours = null;
            if ($in && $out) {
                $mins = self::mins($out) - self::mins($in);
                if ($mins < 0) {
                    $mins += 1440; // night shift ending after midnight
                }
                $hours = round($mins / 60, 2);
            }
            $isLate = $in && substr($in, 0, 5) > $late;
            $early = $in && $out && $end && !($shift && self::mins($shift['end_time']) < self::mins($shift['start_time'])) && substr($out, 0, 5) < $end;
            $noOut = $in && !$out && $d < date('Y-m-d');
            if ($in) {
                $tot['days']++;
                $tot['hours'] += (float) $hours;
                $tot['late'] += $isLate ? 1 : 0;
                $tot['early'] += $early ? 1 : 0;
                $tot['no_out'] += $noOut ? 1 : 0;
            } elseif (in_array($d, $expected, true)) {
                $tot['absent']++;
            }
            $rows[] = ['date' => $d, 'in' => $in, 'out' => $out, 'hours' => $hours, 'late' => $isLate, 'early' => $early, 'no_out' => $noOut,
                'expected' => in_array($d, $expected, true), 'note' => $r['note'] ?? null, 'corrected' => !empty($r['corrected_by'])];
        }
        $tot['hours'] = round($tot['hours'], 2);
        return ['rows' => $rows, 'tot' => $tot, 'shift' => $shift, 'late_after' => $late];
    }

    public static function approval(int $userId, string $period): ?array
    {
        try {
            return db()->one('SELECT t.*, u.name AS approved_name FROM timesheets t LEFT JOIN users u ON u.id = t.approved_by WHERE t.user_id = ? AND t.period = ?', [$userId, $period]);
        } catch (PDOException $e) {
            return null;
        }
    }

    /** Approved days worked for payroll (null when the month's timesheet is not approved). */
    public static function approvedDays(int $userId, string $period): ?float
    {
        $a = self::approval($userId, $period);
        return $a && $a['status'] === 'approved' ? (float) $a['days_worked'] : null;
    }

    public static function validPeriod(string $p): bool
    {
        return (bool) preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/', $p);
    }

    private static function mins(string $t): int
    {
        return (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);
    }
}
