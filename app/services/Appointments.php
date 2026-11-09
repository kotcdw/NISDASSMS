<?php
declare(strict_types=1);

/** Free appointment times for a staff member, from their weekly availability minus bookings and closed days. */
final class Appointments
{
    /** Free start times "HH:MM" on a date (only from 2 hours ahead, at most 60 days ahead). */
    public static function slots(int $staffId, array $type, string $date): array
    {
        if (!Validator::isDate($date) || $date < date('Y-m-d') || $date > date('Y-m-d', strtotime('+60 days'))) {
            return [];
        }
        if (Attendance::closedDates($date, $date) || !db()->value("SELECT 1 FROM users WHERE id = ? AND is_active = 1 AND role NOT IN ('parent','pupil')", [$staffId])) {
            return [];
        }
        $dur = max(5, (int) $type['duration_min']) * 60;
        $wd = (int) date('N', strtotime($date));
        $booked = db()->all("SELECT start_at, end_at FROM appointments WHERE staff_id = ? AND status = 'booked' AND DATE(start_at) = ?", [$staffId, $date]);
        $earliest = time() + 2 * 3600;
        $out = [];
        foreach (db()->all('SELECT start_time, end_time FROM appointment_availability WHERE user_id = ? AND type_id = ? AND weekday = ? ORDER BY start_time', [$staffId, $type['id'], $wd]) as $w) {
            for ($t = strtotime("$date {$w['start_time']}"); $t + $dur <= strtotime("$date {$w['end_time']}"); $t += $dur) {
                if ($t < $earliest) {
                    continue;
                }
                $free = true;
                foreach ($booked as $b) {
                    if ($t < strtotime($b['end_at']) && $t + $dur > strtotime($b['start_at'])) {
                        $free = false;
                        break;
                    }
                }
                if ($free) {
                    $out[] = date('H:i', $t);
                }
            }
        }
        return array_values(array_unique($out));
    }
}
