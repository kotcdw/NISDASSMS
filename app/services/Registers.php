<?php
declare(strict_types=1);

/**
 * GES school records (migration 039): Head's log book, visitors' book, staff movement book, staff leave,
 * staff training (CPD), store ledger, licences & permits, staff contract end dates and the data-request log.
 * Lists of choices, small calculations and the daily reminders live here; the pages are in RegisterController,
 * StaffLeaveController, StoreController, PermitController and PrivacyController.
 */
final class Registers
{
    public const LOG_CATEGORIES = [
        'event' => 'School event', 'visit' => 'Official visit', 'inspection' => 'Inspection / supervision', 'incident' => 'Incident',
        'meeting' => 'Meeting (staff, PTA, SMC)', 'staff' => 'Staff matter', 'pupils' => 'Students', 'premises' => 'Buildings & premises', 'other' => 'Other',
    ];
    public const VISIT_PURPOSES = [
        'parent' => 'Parent / guardian', 'official' => 'Official (GES, district, inspector)', 'vendor' => 'Supplier / vendor',
        'interview' => 'Job interview', 'delivery' => 'Delivery', 'other' => 'Other',
    ];
    public const MOVE_PURPOSES = ['official' => 'Official duty', 'personal' => 'Personal', 'medical' => 'Medical / hospital', 'other' => 'Other'];
    public const LEAVE_TYPES = [
        'annual' => 'Annual leave', 'sick' => 'Sick leave', 'casual' => 'Casual leave', 'maternity' => 'Maternity leave',
        'paternity' => 'Paternity leave', 'study' => 'Study leave', 'compassionate' => 'Compassionate / bereavement leave',
    ];
    public const LEAVE_STATUS = ['pending' => ['Waiting', 'warn'], 'approved' => ['Approved', 'good'], 'declined' => ['Declined', 'bad'], 'cancelled' => ['Cancelled', 'neutral']];
    public const CPD_KINDS = ['workshop' => 'Workshop', 'inset' => 'INSET (in-service training)', 'course' => 'Course', 'conference' => 'Conference', 'online' => 'Online course', 'other' => 'Other'];
    public const STORE_CATEGORIES = [
        'stationery' => 'Stationery', 'cleaning' => 'Cleaning materials', 'teaching' => 'Teaching & learning materials', 'maintenance' => 'Maintenance',
        'kitchen' => 'Kitchen', 'sports' => 'Sports', 'medical' => 'First aid', 'other' => 'Other',
    ];
    public const STORE_KINDS = ['receive' => 'Received', 'issue' => 'Issued', 'adjust' => 'Stock count correction'];
    /** Common permits for a private basic school in Ghana (suggestions; the school types its own). */
    public const PERMIT_SUGGESTIONS = [
        'NaSIA operating licence', 'GES registration / approval', 'Fire certificate (GNFS)', 'Environmental health permit',
        'District Assembly business operating permit', 'Food handlers\' health certificates (kitchen staff)', 'Data Protection Commission registration',
        'Vehicle roadworthy and insurance (school buses)', 'Building / structural safety certificate',
    ];
    public const DR_TYPES = ['access' => 'See their records (access)', 'correction' => 'Correct their records', 'deletion' => 'Delete records', 'objection' => 'Object to a use of their data', 'complaint' => 'Complaint'];
    public const DR_RELATIONS = ['parent' => 'Parent / guardian', 'student' => 'Student', 'former_student' => 'Former student', 'staff' => 'Staff member', 'other' => 'Other'];

    /** True once migration 039 is in (pages answer "update the database" until then). */
    public static function ready(): bool
    {
        static $r = null;
        return $r ??= (bool) db()->value("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'head_logbook'");
    }

    /** Working days in a leave period: Monday–Friday, without days the calendar marks "school closed" (public holidays). */
    public static function leaveDays(string $from, string $to): int
    {
        if ($to < $from) {
            return 0;
        }
        $closed = [];
        foreach (db()->all('SELECT start_date, end_date FROM calendar_events WHERE is_school_closed = 1 AND start_date <= ? AND end_date >= ?', [$to, $from]) as $c) {
            for ($d = max($c['start_date'], $from); $d <= min($c['end_date'], $to); $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
                $closed[$d] = true;
            }
        }
        $n = 0;
        for ($d = $from; $d <= $to; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
            if ((int) date('N', strtotime($d)) <= 5 && !isset($closed[$d])) {
                $n++;
            }
        }
        return $n;
    }

    /** Annual leave this calendar year for one person: [allowance, taken (approved), waiting (pending), left]. */
    public static function annualBalance(int $userId, ?int $year = null): array
    {
        $year = $year ?? (int) date('Y');
        $allow = max(0, (int) setting('staff_leave_annual_days', '15'));
        $sum = fn(string $st) => (int) db()->value(
            "SELECT COALESCE(SUM(days), 0) FROM staff_leave WHERE user_id = ? AND leave_type = 'annual' AND status = ? AND YEAR(start_date) = ?",
            [$userId, $st, $year]
        );
        $taken = $sum('approved');
        $waiting = $sum('pending');
        return ['allowance' => $allow, 'taken' => $taken, 'waiting' => $waiting, 'left' => $allow - $taken];
    }

    /** Approved leave by person and day for a period: [user_id => [date => leave type label]]. */
    public static function leaveMap(string $from, string $to, ?int $userId = null): array
    {
        if (!self::ready()) {
            return [];
        }
        $out = [];
        $rows = db()->all(
            "SELECT user_id, leave_type, start_date, end_date FROM staff_leave WHERE status = 'approved' AND start_date <= ? AND end_date >= ?" . ($userId ? ' AND user_id = ?' : ''),
            $userId ? [$to, $from, $userId] : [$to, $from]
        );
        foreach ($rows as $r) {
            for ($d = max($r['start_date'], $from); $d <= min($r['end_date'], $to); $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
                $out[(int) $r['user_id']][$d] = self::LEAVE_TYPES[$r['leave_type']] ?? 'Leave';
            }
        }
        return $out;
    }

    /** Staff who are out of school right now (movement book): [user_id => row]. */
    public static function outNow(): array
    {
        if (!self::ready()) {
            return [];
        }
        $out = [];
        foreach (db()->all('SELECT m.*, u.name FROM staff_movements m JOIN users u ON u.id = m.user_id WHERE m.back_at IS NULL AND DATE(m.out_at) = CURDATE() ORDER BY m.out_at') as $r) {
            $out[(int) $r['user_id']] = $r;
        }
        return $out;
    }

    /** Next reference number, e.g. LV-2026-0007 or DR-2026-0002. */
    public static function nextRef(string $prefix): string
    {
        return db()->transaction(function (Database $d) use ($prefix) {
            $name = strtolower($prefix) . '_' . date('Y');
            $d->run('INSERT IGNORE INTO number_sequences (name, next_value) VALUES (?, 1)', [$name]);
            $k = (int) $d->value('SELECT next_value FROM number_sequences WHERE name = ? FOR UPDATE', [$name]);
            $d->run('UPDATE number_sequences SET next_value = next_value + 1 WHERE name = ?', [$name]);
            return $prefix . '-' . date('Y') . '-' . str_pad((string) $k, 4, '0', STR_PAD_LEFT);
        });
    }

    /** Store balances: item_id => quantity in stock. */
    public static function stock(?int $itemId = null): array
    {
        $out = [];
        foreach (db()->all(
            "SELECT item_id, SUM(CASE kind WHEN 'receive' THEN qty WHEN 'issue' THEN -qty ELSE qty END) AS bal FROM store_moves" . ($itemId ? ' WHERE item_id = ?' : '') . ' GROUP BY item_id',
            $itemId ? [$itemId] : []
        ) as $r) {
            $out[(int) $r['item_id']] = (float) $r['bal'];
        }
        return $out;
    }

    /** Counts for the top-bar alerts: [permits expiring ≤60 days or expired, contracts ending ≤60 days, data requests overdue, leave waiting]. */
    public static function attention(): array
    {
        if (!self::ready()) {
            return ['permits' => 0, 'contracts' => 0, 'requests' => 0, 'leave' => 0, 'outlate' => 0];
        }
        return [
            'permits' => (int) db()->value('SELECT COUNT(*) FROM school_permits WHERE expires_on IS NOT NULL AND expires_on <= CURDATE() + INTERVAL 60 DAY'),
            'contracts' => (int) db()->value('SELECT COUNT(*) FROM staff WHERE is_active = 1 AND end_date IS NULL AND ((contract_end IS NOT NULL AND contract_end <= CURDATE() + INTERVAL 60 DAY) OR (probation_end IS NOT NULL AND probation_end BETWEEN CURDATE() - INTERVAL 7 DAY AND CURDATE() + INTERVAL 30 DAY))'),
            'requests' => (int) db()->value("SELECT COUNT(*) FROM data_requests WHERE status = 'open' AND due_on <= CURDATE() + INTERVAL 3 DAY"),
            'leave' => (int) db()->value("SELECT COUNT(*) FROM staff_leave WHERE status = 'pending'"),
            'outlate' => (int) db()->value('SELECT COUNT(*) FROM staff_movements WHERE back_at IS NULL AND expected_back IS NOT NULL AND expected_back < NOW() AND DATE(out_at) = CURDATE()'),
        ];
    }

    /**
     * Scheduled task (daily): bell alerts for licences and permits within 60 days of expiry and staff contracts ending
     * within 60 days — once every 30 days per record, so nobody is flooded.
     */
    public static function daily(): int
    {
        if (!self::ready()) {
            return 0;
        }
        $n = 0;
        if (ModuleSwitch::isOn('permits')) {
            foreach (db()->all("SELECT * FROM school_permits WHERE expires_on IS NOT NULL AND expires_on <= CURDATE() + INTERVAL 60 DAY
                                AND (alerted_on IS NULL OR alerted_on <= CURDATE() - INTERVAL 30 DAY)") as $p) {
                $late = $p['expires_on'] < date('Y-m-d');
                Notifier::inappPermission('permits.manage', ($late ? 'Expired: ' : 'Renew soon: ') . $p['name'],
                    ($late ? 'Expired on ' : 'Expires on ') . date('j M Y', strtotime($p['expires_on'])) . ($p['authority'] ? ' · ' . $p['authority'] : ''), 'permits');
                db()->update('school_permits', ['alerted_on' => date('Y-m-d')], 'id = ?', [$p['id']]);
                $n++;
            }
        }
        foreach (db()->all("SELECT * FROM staff WHERE is_active = 1 AND end_date IS NULL AND contract_end IS NOT NULL AND contract_end <= CURDATE() + INTERVAL 60 DAY
                            AND (contract_alerted_on IS NULL OR contract_alerted_on <= CURDATE() - INTERVAL 30 DAY)") as $s) {
            $title = 'Contract ' . ($s['contract_end'] < date('Y-m-d') ? 'ended' : 'ends') . ': ' . $s['name'];
            $body = date('j M Y', strtotime($s['contract_end'])) . ' · ' . $s['position'] . ' — renew it or record the date they leave.';
            Notifier::inappPermission('payroll.manage', $title, $body, 'payroll/staff/' . (int) $s['id']);
            Notifier::inappPermission('staffleave.approve', $title, $body, 'payroll/staff');
            db()->update('staff', ['contract_alerted_on' => date('Y-m-d')], 'id = ?', [$s['id']]);
            $n++;
        }
        return $n;
    }
}
