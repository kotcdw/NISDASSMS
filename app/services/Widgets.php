<?php
declare(strict_types=1);

/**
 * School figures by name (the Analytic Dashboard reuses them, and each chart can be printed or downloaded on
 * its own at dashboard/widget/{slug}). Every widget has the same shape:
 *   KPI card  → ['value', 'sub', 'trend' => [direction -1|0|1, text, upIsGood] | null, 'href']
 *   Chart card → ['chart' (SVG/HTML), 'note', 'cols', 'rows' (table view, CSV and print), 'href', 'report' (School report tab) ]
 * Each person only gets widgets their permissions allow.
 */
final class Widgets
{
    public const AREAS = [
        'academics'  => 'Students & academics',
        'finance'    => 'Fees & finance',
        'staff'      => 'Staff & HR',
        'operations' => 'Operations & services',
    ];

    /** key => [title, area, kind kpi|chart, permissions (any of), shown by default, wide by default] — 'mine' = shown by default only to staff without School reports. */
    public const CATALOG = [
        // headline numbers
        'k_pupils'       => ['Students on roll', 'academics', 'kpi', ['analytics.view', 'pupils.view'], true, false],
        'k_attendance'   => ['Student attendance this term', 'academics', 'kpi', ['attendance.view'], true, false],
        'k_average'      => ['School average', 'academics', 'kpi', ['reports.view'], true, false],
        'k_admissions'   => ['Applications this year', 'academics', 'kpi', ['admission.view'], true, false],
        'k_fees'         => ['Fees collected this term', 'finance', 'kpi', ['fees.view'], true, false],
        'k_outstanding'  => ['Fees outstanding', 'finance', 'kpi', ['fees.view'], true, false],
        'k_cash'         => ['Cash & bank balance', 'finance', 'kpi', ['finance.view'], true, false],
        'k_runway'       => ['Cash runway', 'finance', 'kpi', ['finance.view'], true, false],
        'k_breakeven'    => ['Students needed to cover spending', 'finance', 'kpi', ['finance.view'], true, false],
        'k_requisitions' => ['Requisitions waiting', 'finance', 'kpi', ['requisitions.approve', 'requisitions.pay', 'requisitions.view_all'], true, false],
        'k_staff'        => ['Staff signed in today', 'staff', 'kpi', ['staff_att.view'], true, false],
        'k_enquiries'    => ['Open enquiries', 'operations', 'kpi', ['crm.manage'], false, false],
        'k_sms'          => ['Messages sent this month', 'operations', 'kpi', ['messages.send'], false, false],
        'k_welfare'      => ['Open welfare cases', 'academics', 'kpi', ['welfare.manage'], true, false],
        // pupils & academics
        'c_my_day'       => ['My lessons today', 'academics', 'chart', ['staff'], 'mine', false],
        'c_my_registers' => ['My class registers', 'academics', 'chart', ['attendance.take'], 'mine', false],
        'c_enrolment'    => ['Enrolment by level', 'academics', 'chart', ['analytics.view', 'pupils.view'], true, false],
        'c_admissions'   => ['Admissions pipeline', 'academics', 'chart', ['admission.view'], true, false],
        'c_att_trend'    => ['Attendance trend', 'academics', 'chart', ['attendance.view'], true, false],
        'c_att_class'    => ['Attendance by class', 'academics', 'chart', ['attendance.view'], false, false],
        'c_at_risk'      => ['Students below 85% attendance', 'academics', 'chart', ['attendance.view'], true, false],
        'c_academic'     => ['Class averages', 'academics', 'chart', ['reports.view'], true, true],
        'c_subjects'     => ['Subject performance', 'academics', 'chart', ['reports.view'], false, false],
        'c_top'          => ['Top students this term', 'academics', 'chart', ['reports.view'], false, false],
        'c_behaviour'    => ['Behaviour this term', 'academics', 'chart', ['welfare.manage'], true, false],
        'c_welfare'      => ['Open welfare cases by type', 'academics', 'chart', ['welfare.manage'], false, false],
        'c_census'       => ['School census (EMIS)', 'academics', 'chart', ['analytics.view'], false, true],
        'c_upcoming'     => ['Coming up', 'academics', 'chart', ['calendar.view'], 'mine', false],
        // fees & finance
        'c_fee_weeks'    => ['Fees received each week', 'finance', 'chart', ['fees.view'], true, false],
        'c_fee_class'    => ['Fee collection by class', 'finance', 'chart', ['fees.view'], true, false],
        'c_fee_method'   => ['How parents pay', 'finance', 'chart', ['fees.view'], false, false],
        'c_accounts'     => ['Balance by account', 'finance', 'chart', ['finance.view'], true, false],
        'c_spending'     => ['Spending by category this term', 'finance', 'chart', ['finance.view'], true, false],
        'c_requisitions' => ['Requisitions by stage', 'finance', 'chart', ['requisitions.approve', 'requisitions.pay', 'requisitions.view_all'], false, false],
        'c_payroll'      => ['Payroll cost by month', 'finance', 'chart', ['payroll.view', 'payroll.manage'], false, false],
        // staff & HR
        'c_staff_trend'  => ['Staff attendance trend', 'staff', 'chart', ['staff_att.view'], true, false],
        'c_staff_late'   => ['Late arrivals this term', 'staff', 'chart', ['staff_att.view'], false, false],
        'c_hr_alerts'    => ['HR follow-ups', 'staff', 'chart', ['payroll.manage', 'appraisal.manage', 'timesheets.manage', 'setup.manage'], true, false],
        // operations & services
        'c_canteen'      => ['Canteen sales each week', 'operations', 'chart', ['canteen.manage'], false, false],
        'c_shop'         => ['School shop orders', 'operations', 'chart', ['shop.manage'], false, false],
        'c_library'      => ['Library loans', 'operations', 'chart', ['library.view', 'library.manage'], false, false],
        'c_transport'    => ['Bus riders by route', 'operations', 'chart', ['transport.view'], false, false],
        'c_crm'          => ['Enquiries by stage', 'operations', 'chart', ['crm.manage'], false, false],
        'c_sms'          => ['Messages sent each week', 'operations', 'chart', ['messages.send'], false, false],
        'c_health'       => ['Sick bay visits each week', 'operations', 'chart', ['health.view'], false, false],
        'c_documents'    => ['Documents expiring', 'operations', 'chart', ['documents.manage'], false, false],
    ];

    /** School-wide figures that cannot be narrowed to a teacher's own classes. */
    private const WHOLE_SCHOOL = ['c_subjects', 'c_census'];

    /** Widgets this person may use, in catalogue order. */
    public static function allowed(): array
    {
        $scoped = Auth::classScope() !== null;
        return array_filter(self::CATALOG, fn($w, $k) => (bool) array_filter($w[3], fn($p) => can($p)) && !ModuleSwitch::permsOff($w[3]) && !($scoped && in_array($k, self::WHOLE_SCHOOL, true)), ARRAY_FILTER_USE_BOTH);
    }

    /** Build one widget for a term. Never throws: a widget whose module is not set up shows a short message. */
    /** Chart drawing width: wide cards get a wider drawing instead of a stretched one. */
    private static int $w = 600;

    public static function build(string $key, ?array $term, bool $wide = false): array
    {
        self::$w = $wide ? 1200 : 600;
        if (!$term && !in_array($key, ['k_cash', 'k_runway', 'c_accounts', 'c_my_day', 'c_my_registers', 'c_upcoming', 'k_enquiries', 'c_crm', 'c_documents'], true)) {
            return ['empty' => 'Create an academic year and terms first.'];
        }
        try {
            return self::{$key}($term ?? []);
        } catch (PDOException $e) {
            return ['empty' => 'Not available yet — click "Update database now".'];
        }
    }

    // ------------------------------------------------------------------ helpers

    public static function report(string $tab, array $term): array
    {
        static $memo = [];
        return $memo[$tab . $term['id']] ??= (new AnalyticsController())->build($tab, (int) $term['id'])['d'];
    }

    /** Teachers who only see their own classes: [" AND col IN (…)", ids]; everyone else: ['', []]. */
    public static function scope(string $col): array
    {
        $sc = Auth::classScope();
        if ($sc === null) {
            return ['', []];
        }
        $sc = $sc ?: [0];
        return [' AND ' . $col . ' IN (' . implode(',', array_fill(0, count($sc), '?')) . ')', $sc];
    }

    /** Keep only rows for the person's own classes (rows with 'id' = class id, or a class name field). */
    public static function own(array $rows, string $field = 'id'): array
    {
        $sc = Auth::classScope();
        if ($sc === null) {
            return $rows;
        }
        if ($field !== 'id') {
            $names = $sc ? array_column(db()->all('SELECT name FROM classes WHERE id IN (' . implode(',', array_map('intval', $sc)) . ')'), 'name') : [];
            return array_values(array_filter($rows, fn($r) => in_array($r[$field], $names, true)));
        }
        return array_values(array_filter($rows, fn($r) => in_array((int) $r['id'], $sc, true)));
    }

    /** Whole cedis for headline cards (GH₵ 108,010). */
    private static function cedis(float|string|null $v): string
    {
        return 'GH₵ ' . number_format((float) $v, 0);
    }

    private static function to(array $term): string
    {
        return min($term['end_date'], date('Y-m-d'));
    }

    private static function pct(float $a, float $b): ?float
    {
        return $b > 0 ? round($a * 100 / $b, 1) : null;
    }

    private static function trend(float $now, float $before, string $text, bool $upIsGood = true): array
    {
        $dir = abs($now - $before) < 0.05 ? 0 : ($now > $before ? 1 : -1);
        return [$dir, $text, $upIsGood];
    }

    /** Weekly totals from term start to today: [[label, value, tooltip], …] from rows [week_start_date => amount]. */
    private static function weeks(array $term, array $byDate, callable $fmt): array
    {
        $start = strtotime('monday this week', strtotime($term['start_date']));
        $end = strtotime(self::to($term));
        $out = [];
        for ($t = $start, $i = 1; $t <= $end && $i <= 20; $t += 7 * 86400, $i++) {
            $v = 0.0;
            for ($d = 0; $d < 7; $d++) {
                $v += (float) ($byDate[date('Y-m-d', $t + $d * 86400)] ?? 0);
            }
            $out[] = ['Wk ' . $i, $v, 'Week ' . $i . ' (from ' . date('j M', $t) . '): ' . $fmt($v)];
        }
        return $out;
    }

    private static function weekTable(array $w, string $col): array
    {
        return ['cols' => ['Week', $col], 'rows' => array_map(fn($r) => [$r[2] ? explode(':', $r[2])[0] : $r[0], $r[1]], $w)];
    }

    // ------------------------------------------------------------------ KPI cards

    private static function k_pupils(array $term): array
    {
        $yid = (int) $term['academic_year_id'];
        $q = "SELECT COUNT(*) AS n, SUM(p.gender = 'male') AS b, SUM(p.gender = 'female') AS g FROM enrolments e JOIN pupils p ON p.id = e.pupil_id
              WHERE e.academic_year_id = ? AND e.status = 'active' AND p.status = 'active' AND p.deleted_at IS NULL";
        [$w, $wp] = self::scope('e.class_id');
        $r = db()->one($q . $w, array_merge([$yid], $wp));
        $prevYear = db()->value('SELECT id FROM academic_years WHERE start_date < (SELECT start_date FROM academic_years WHERE id = ?) ORDER BY start_date DESC LIMIT 1', [$yid]);
        $prev = $prevYear && !$wp ? (int) db()->value("SELECT COUNT(*) FROM enrolments WHERE academic_year_id = ? AND status IN ('active','promoted','repeated','completed')", [$prevYear]) : null;
        return [
            'value' => number_format((int) $r['n']), 'sub' => (int) $r['b'] . ' boys · ' . (int) $r['g'] . ' girls',
            'trend' => $prev ? self::trend((float) $r['n'], (float) $prev, sprintf('%+d on last year', (int) $r['n'] - $prev)) : null,
            'href' => 'pupils',
        ];
    }

    private static function k_attendance(array $term): array
    {
        [$w, $wp] = self::scope('r.class_id');
        $r = db()->one("SELECT SUM(a.status IN ('present','late')) AS p, COUNT(a.id) AS n FROM attendance_registers r JOIN attendance_records a ON a.register_id = r.id WHERE r.date BETWEEN ? AND ?" . $w, array_merge([$term['start_date'], self::to($term)], $wp));
        $days = array_column(db()->all('SELECT DISTINCT r.date FROM attendance_registers r WHERE r.date <= ?' . $w . ' ORDER BY r.date DESC LIMIT 10', array_merge([self::to($term)], $wp)), 'date');
        $rate = fn(array $d) => $d ? db()->one("SELECT SUM(a.status IN ('present','late')) AS p, COUNT(a.id) AS n FROM attendance_registers r JOIN attendance_records a ON a.register_id = r.id WHERE r.date IN (" . implode(',', array_fill(0, count($d), '?')) . ')' . $w, array_merge($d, $wp)) : ['p' => 0, 'n' => 0];
        $a = $rate(array_slice($days, 0, 5));
        $b = $rate(array_slice($days, 5, 5));
        $ra = self::pct((float) $a['p'], (float) $a['n']);
        $rb = self::pct((float) $b['p'], (float) $b['n']);
        $absent = (int) db()->value("SELECT COUNT(*) FROM attendance_registers r JOIN attendance_records a ON a.register_id = r.id WHERE r.date = CURDATE() AND a.status = 'absent'" . $w, $wp);
        $v = self::pct((float) $r['p'], (float) $r['n']);
        return [
            'value' => $v === null ? '—' : $v . '%', 'sub' => $absent . ' absent today · target 90%',
            'trend' => ($ra !== null && $rb !== null) ? self::trend($ra, $rb, sprintf('%+.1f pts, last 5 days', $ra - $rb)) : null,
            'tone' => $v !== null && $v < 90 ? 'warn' : null, 'href' => 'attendance',
        ];
    }

    private static function k_average(array $term): array
    {
        $rows = array_filter(self::own(self::report('academic', $term)['rows']), fn($r) => $r['average'] !== null);
        $n = array_sum(array_column($rows, 'scored'));
        $avg = $n ? round(array_sum(array_map(fn($r) => $r['average'] * $r['scored'], $rows)) / $n, 1) : null;
        $pass = $n ? round(array_sum(array_map(fn($r) => $r['pass'] * $r['scored'], $rows)) / $n, 1) : null;
        return ['value' => $avg === null ? '—' : $avg . '%', 'sub' => $avg === null ? 'No approved scores yet' : 'Pass rate ' . $pass . '% · ' . $n . ' students scored', 'trend' => null, 'href' => 'exams'];
    }

    private static function k_admissions(array $term): array
    {
        $r = db()->one("SELECT COUNT(*) AS n, SUM(stage = 'enrolled') AS enrolled, SUM(stage IN ('submitted','screening','interview')) AS waiting FROM applications WHERE academic_year_id = ?", [$term['academic_year_id']]);
        return ['value' => number_format((int) $r['n']), 'sub' => (int) $r['enrolled'] . ' enrolled · ' . (int) $r['waiting'] . ' awaiting a decision', 'trend' => null, 'href' => 'admission'];
    }

    private static function k_fees(array $term): array
    {
        $f = db()->one("SELECT COALESCE(SUM(total), 0) AS billed, COALESCE(SUM(paid), 0) AS paid FROM invoices WHERE term_id = ? AND status <> 'cancelled'", [$term['id']]);
        $wk = fn(int $ago) => (float) db()->value(
            "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed' AND paid_on BETWEEN ? AND ?",
            [date('Y-m-d', strtotime("monday this week -$ago week")), date('Y-m-d', strtotime("sunday this week -$ago week"))]
        );
        $now = $wk(0);
        $before = $wk(1);
        $p = self::pct((float) $f['paid'], (float) $f['billed']);
        return [
            'value' => $p === null ? '—' : round($p) . '%', 'sub' => self::cedis($f['paid']) . ' of ' . self::cedis($f['billed']),
            'trend' => self::trend($now, $before, Fees::money($now) . ' this week'), 'href' => 'fees',
        ];
    }

    private static function k_outstanding(array $term): array
    {
        $o = (float) db()->value("SELECT COALESCE(SUM(GREATEST(total - paid, 0)), 0) FROM invoices WHERE term_id = ? AND status <> 'cancelled'", [$term['id']]);
        $arrears = (float) self::report('fees', $term)['arrears'];
        $overdue = (int) db()->value("SELECT COUNT(*) FROM invoices WHERE status IN ('unpaid','part') AND due_date < CURDATE()");
        return ['value' => self::cedis($o), 'sub' => $overdue . ' overdue bills · ' . Fees::money($arrears) . ' from earlier terms', 'trend' => null, 'tone' => $overdue ? 'warn' : null, 'href' => 'fees/debtors'];
    }

    private static function k_cash(array $term): array
    {
        $acc = Finance::accounts();
        $total = array_sum(array_map(fn($a) => Finance::balance((int) $a['id']), $acc));
        return ['value' => self::cedis($total), 'sub' => count($acc) . ' accounts (cash, bank, MoMo)', 'trend' => null, 'tone' => $total < 0 ? 'bad' : null, 'href' => 'finance'];
    }

    /** How many months the cash would last at the average spending of the last 3 months. */
    private static function k_runway(array $term): array
    {
        $cash = array_sum(array_map(fn($a) => Finance::balance((int) $a['id']), Finance::accounts()));
        $spent = (float) db()->value("SELECT COALESCE(SUM(CASE WHEN kind = 'refund' THEN -amount ELSE amount END), 0) FROM fin_transactions WHERE status = 'posted' AND kind IN ('expense','refund') AND txn_date > (CURDATE() - INTERVAL 3 MONTH)");
        $monthly = $spent / 3;
        if ($monthly <= 0) {
            return ['value' => '—', 'sub' => 'No spending recorded in the last 3 months', 'trend' => null, 'href' => 'finance/reports'];
        }
        $m = round($cash / $monthly, 1);
        $min = (float) setting('reserve_min_months', '3');
        return ['value' => rtrim(rtrim(number_format($m, 1), '0'), '.') . ' months', 'sub' => 'Cash ÷ ' . self::cedis($monthly) . ' average monthly spending', 'trend' => null, 'tone' => $min > 0 && $m < $min ? 'bad' : null, 'href' => 'finance/budget/reserves'];
    }

    /** Fee-paying students needed to cover this term's spending so far (spending ÷ average term bill). */
    private static function k_breakeven(array $term): array
    {
        $to = min(date('Y-m-d'), $term['end_date']);
        $spent = (float) db()->value("SELECT COALESCE(SUM(CASE WHEN kind = 'refund' THEN -amount ELSE amount END), 0) FROM fin_transactions WHERE status = 'posted' AND kind IN ('expense','refund') AND txn_date BETWEEN ? AND ?", [$term['start_date'], $to]);
        $b = db()->one("SELECT COALESCE(SUM(total), 0) AS billed, COUNT(DISTINCT pupil_id) AS n FROM invoices WHERE term_id = ? AND status <> 'cancelled' AND kind = 'term'", [$term['id']]);
        if ((int) $b['n'] === 0 || (float) $b['billed'] <= 0) {
            return ['value' => '—', 'sub' => 'No term bills yet', 'trend' => null, 'href' => 'fees'];
        }
        $avg = (float) $b['billed'] / (int) $b['n'];
        $need = (int) ceil($spent / $avg);
        return ['value' => (string) $need, 'sub' => 'of ' . (int) $b['n'] . ' billed · ' . self::cedis($spent) . ' spent ÷ ' . self::cedis($avg) . ' average bill', 'trend' => null, 'tone' => $need > (int) $b['n'] ? 'bad' : null, 'href' => 'finance/reports'];
    }

    private static function k_requisitions(array $term): array
    {
        $r = db()->one("SELECT SUM(status IN ('submitted','approved_l1')) AS approve, COALESCE(SUM(CASE WHEN status IN ('submitted','approved_l1') THEN amount END), 0) AS amt, SUM(status = 'approved') AS pay FROM requisitions");
        return ['value' => (string) (int) $r['approve'], 'sub' => Fees::money($r['amt']) . ' awaiting approval · ' . (int) $r['pay'] . ' to pay', 'trend' => null, 'tone' => (int) $r['approve'] ? 'warn' : null, 'href' => 'requisitions'];
    }

    private static function k_staff(array $term): array
    {
        $staff = StaffAttendance::staff();
        $in = 0;
        $late = 0;
        $marks = array_column(db()->all('SELECT user_id, time_in FROM staff_attendance WHERE date = CURDATE() AND time_in IS NOT NULL'), 'time_in', 'user_id');
        foreach ($staff as $u) {
            if (isset($marks[$u['id']])) {
                $in++;
                $late += StaffAttendance::isLate($marks[$u['id']], (int) $u['id']) ? 1 : 0;
            }
        }
        return ['value' => $in . ' / ' . count($staff), 'sub' => $late . ' late today', 'trend' => null, 'tone' => $late ? 'warn' : null, 'href' => 'attendance/staff'];
    }

    private static function k_enquiries(array $term): array
    {
        $r = db()->one("SELECT SUM(stage IN ('new','contacted','visit_booked','visited')) AS open, SUM(stage IN ('new','contacted','visit_booked','visited') AND next_follow_up <= CURDATE()) AS due, SUM(stage IN ('applied','enrolled')) AS won FROM crm_enquiries");
        return ['value' => (string) (int) $r['open'], 'sub' => (int) $r['due'] . ' follow-ups due · ' . (int) $r['won'] . ' applied or enrolled', 'trend' => null, 'href' => 'crm'];
    }

    private static function k_sms(array $term): array
    {
        $r = db()->one("SELECT SUM(status = 'sent') AS sent, SUM(status = 'failed') AS failed, SUM(status = 'queued') AS queued FROM notification_outbox WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
        return ['value' => number_format((int) $r['sent']), 'sub' => (int) $r['failed'] . ' failed · ' . (int) $r['queued'] . ' waiting', 'trend' => null, 'tone' => (int) $r['failed'] ? 'warn' : null, 'href' => 'messages/outbox'];
    }

    // ------------------------------------------------------------------ pupils & academics

    private static function c_my_day(array $term): array
    {
        $dow = (int) date('N');
        $rows = $dow <= 5 ? db()->all(
            'SELECT p.start_time, p.end_time, c.name AS class_name, s.name AS subject_name, ts.room
             FROM timetable_slots ts JOIN timetable_periods p ON p.id = ts.period_id JOIN classes c ON c.id = ts.class_id
             LEFT JOIN class_subjects cs ON cs.id = ts.class_subject_id LEFT JOIN subjects s ON s.id = cs.subject_id
             WHERE ts.teacher_id = ? AND ts.day = ? ORDER BY p.start_time',
            [Auth::id(), $dow]
        ) : [];
        return [
            'chart' => $rows ? '' : '<p class="muted">' . ($dow > 5 ? 'Weekend — no lessons.' : 'No lessons on your timetable today.') . '</p>',
            'cols' => ['Time', 'Subject', 'Class', 'Room'], 'showTable' => true,
            'rows' => array_map(fn($l) => [substr($l['start_time'], 0, 5) . '–' . substr($l['end_time'], 0, 5), $l['subject_name'] ?? '', $l['class_name'], $l['room'] ?? ''], $rows),
            'href' => 'timetable/my',
        ];
    }

    private static function c_my_registers(array $term): array
    {
        $blocked = Attendance::blockedReason(date('Y-m-d'));
        $rows = db()->all('SELECT c.id, c.name, (SELECT COUNT(*) FROM attendance_registers r WHERE r.class_id = c.id AND r.date = CURDATE()) AS taken FROM classes c WHERE c.class_teacher_id = ? AND c.is_active = 1 ORDER BY c.name', [Auth::id()]);
        $html = $blocked ? '<p class="muted">' . e($blocked) . '</p>' : '';
        if (!$blocked) {
            foreach ($rows as $c) {
                $html .= '<a class="btn' . ($c['taken'] ? '' : ' btn-primary') . '" style="margin:0 .4rem .4rem 0" href="' . e(url('attendance/register/' . $c['id'])) . '">' . ($c['taken'] ? '✓ ' : '') . e($c['name']) . ' register</a>';
            }
            $html = $html ?: '<p class="muted">You are not a class teacher.</p>';
        }
        return ['chart' => $html, 'cols' => ['Class', 'Register today'], 'rows' => array_map(fn($c) => [$c['name'], $c['taken'] ? 'Taken' : 'Not taken'], $rows), 'href' => 'attendance'];
    }

    private static function c_enrolment(array $term): array
    {
        $rows = db()->all(
            "SELECT l.name, SUM(p.gender = 'male') AS boys, SUM(p.gender = 'female') AS girls, COUNT(p.id) AS n
             FROM levels l LEFT JOIN classes c ON c.level_id = l.id
             LEFT JOIN enrolments e ON e.class_id = c.id AND e.academic_year_id = ? AND e.status = 'active'" . self::scope('e.class_id')[0] . "
             LEFT JOIN pupils p ON p.id = e.pupil_id AND p.status = 'active' AND p.deleted_at IS NULL
             GROUP BY l.id, l.name, l.sort_order ORDER BY l.sort_order",
            array_merge([$term['academic_year_id']], self::scope('e.class_id')[1])
        );
        if (Auth::classScope() !== null) {
            $rows = array_values(array_filter($rows, fn($r) => (int) $r['n'] > 0));
        }
        return [
            'chart' => Charts::bars(array_map(fn($r) => [$r['name'], (int) $r['n'], $r['name'] . ': ' . (int) $r['n'] . ' students (' . (int) $r['boys'] . ' boys, ' . (int) $r['girls'] . ' girls)'], $rows), ['label' => 'Students per level', 'height' => 210, 'width' => self::$w]),
            'note' => array_sum(array_column($rows, 'n')) . ' students · ' . array_sum(array_column($rows, 'boys')) . ' boys, ' . array_sum(array_column($rows, 'girls')) . ' girls',
            'cols' => ['Level', 'Boys', 'Girls', 'Total'], 'rows' => array_map(fn($r) => [$r['name'], (int) $r['boys'], (int) $r['girls'], (int) $r['n']], $rows),
            'href' => 'pupils', 'report' => 'enrolment',
        ];
    }

    private static function c_admissions(array $term): array
    {
        $counts = array_column(db()->all('SELECT stage, COUNT(*) AS n FROM applications WHERE academic_year_id = ? GROUP BY stage', [$term['academic_year_id']]), 'n', 'stage');
        $rows = array_map(fn($s) => [Admission::label($s), (int) ($counts[$s] ?? 0), url('admission', ['stage' => $s])], Admission::FLOW);
        return ['chart' => Charts::hbars($rows), 'cols' => ['Stage', 'Applications'], 'rows' => array_map(fn($r) => [$r[0], $r[1]], $rows), 'href' => 'admission', 'note' => 'Online form: ' . absolute_url('apply')];
    }

    private static function c_att_trend(array $term): array
    {
        $pts = [];
        foreach (array_reverse(db()->all(
            "SELECT r.date, SUM(a.status IN ('present','late')) AS p, COUNT(a.id) AS n FROM attendance_registers r JOIN attendance_records a ON a.register_id = r.id
             WHERE r.date <= ?" . self::scope('r.class_id')[0] . " GROUP BY r.date ORDER BY r.date DESC LIMIT 15",
            array_merge([self::to($term)], self::scope('r.class_id')[1])
        )) as $d) {
            $rate = self::pct((float) $d['p'], (float) $d['n']);
            $pts[] = [date('j M', strtotime($d['date'])), $rate, date('D j M', strtotime($d['date'])) . ': ' . $rate . '% present (' . (int) $d['p'] . ' of ' . (int) $d['n'] . ')'];
        }
        $vals = array_filter(array_column($pts, 1), fn($v) => $v !== null);
        $min = $vals ? max(0, min(80, (int) floor(min($vals) / 10) * 10 - 10)) : 0;
        return [
            'chart' => Charts::line($pts, ['max' => 100, 'min' => $min, 'target' => 90, 'format' => fn($v) => $v . '%', 'label' => 'Attendance rate, last school days', 'height' => 210, 'width' => self::$w]),
            'note' => '% of students present or late, last ' . count($pts) . ' school days · dashed line = 90% target',
            'cols' => ['Day', 'Attendance %'], 'rows' => array_map(fn($p) => [explode(':', $p[2])[0], $p[1]], $pts), 'href' => 'attendance',
        ];
    }

    private static function c_att_class(array $term): array
    {
        $rows = array_values(array_filter(self::own(self::report('attendance', $term)['rows']), fn($r) => $r['rate'] !== null));
        return [
            'chart' => Charts::hbars(array_map(fn($r) => [$r['name'], (float) $r['rate'], null, $r['rate'] . '%'], $rows), ['max' => 100, 'target' => 90]),
            'note' => 'Marker = 90% target',
            'cols' => ['Class', 'Days marked', 'Absent', 'Late', 'Attendance %'], 'rows' => array_map(fn($r) => [$r['name'], (int) $r['days'], (int) $r['absent'], (int) $r['late'], $r['rate']], $rows),
            'href' => 'attendance', 'report' => 'attendance',
        ];
    }

    private static function c_at_risk(array $term): array
    {
        $rows = self::own(self::report('attendance', $term)['chronic'], 'class_name');
        $list = '';
        foreach (array_slice($rows, 0, 6) as $p) {
            $list .= '<li><a href="' . e(url('pupils/' . $p['id'])) . '">' . e($p['first_name'] . ' ' . $p['last_name']) . '</a> <small class="muted">' . e($p['class_name']) . '</small><span class="w-pill bad">' . e((string) $p['rate']) . '%</span></li>';
        }
        return [
            'chart' => $rows ? '<ul class="w-list">' . $list . '</ul>' . (count($rows) > 6 ? '<p class="muted" style="margin:.4rem 0 0">…and ' . (count($rows) - 6) . ' more (see table view)</p>' : '') : '<p class="w-ok">✓ No student is below 85% this term.</p>',
            'cols' => ['Student', 'Admission no.', 'Class', 'Absent days', 'Attendance %', 'Parent phone'],
            'rows' => array_map(fn($p) => [$p['first_name'] . ' ' . $p['last_name'], $p['admission_no'], $p['class_name'], (int) $p['absent'], $p['rate'], $p['phone']], $rows),
            'href' => 'attendance', 'report' => 'attendance', 'count' => count($rows),
        ];
    }

    private static function c_academic(array $term): array
    {
        $rows = self::own(self::report('academic', $term)['rows']);
        $with = array_values(array_filter($rows, fn($r) => $r['average'] !== null));
        return [
            'chart' => Charts::bars(array_map(fn($r) => [$r['name'], (float) $r['average'], $r['name'] . ': average ' . $r['average'] . '%, pass rate ' . $r['pass'] . '%'], $with), ['max' => 100, 'target' => 50, 'format' => fn($v) => $v . '%', 'label' => 'Class averages', 'height' => 210, 'width' => self::$w]),
            'note' => 'Dashed line = pass mark (50%). Uses subjects with scores entered so far.',
            'cols' => ['Class', 'Students', 'Average %', 'Pass rate %', 'Best student', 'Score sheets approved'],
            'rows' => array_map(fn($r) => [$r['name'], $r['pupils'], $r['average'], $r['pass'], $r['best'], $r['sheets']['approved'] . ' of ' . $r['sheets']['total']], $rows),
            'href' => 'exams', 'report' => 'academic',
        ];
    }

    private static function c_subjects(array $term): array
    {
        $s = self::report('academic', $term)['subjects'];
        return [
            'chart' => Charts::hbars(array_map(fn($r) => [$r['name'], (float) $r['average'], null, $r['average'] . '%'], array_slice($s, 0, 10)), ['max' => 100, 'target' => 50]),
            'note' => 'Average % across all classes · marker = pass mark',
            'cols' => ['Subject', 'Average %', 'Pass rate %'], 'rows' => array_map(fn($r) => [$r['name'], $r['average'], $r['pass']], $s),
            'href' => 'exams', 'report' => 'academic',
        ];
    }

    private static function c_top(array $term): array
    {
        $t = array_slice(self::own(self::report('academic', $term)['top'], 'class'), 0, 10);
        $list = '';
        foreach ($t as $i => $p) {
            $list .= '<li><span class="w-rank">' . ($i + 1) . '</span><a href="' . e(url('pupils/' . $p['id'])) . '">' . e($p['name']) . '</a> <small class="muted">' . e($p['class']) . '</small><span class="w-pill good">' . e((string) $p['average']) . '%</span></li>';
        }
        return ['chart' => $t ? '<ol class="w-list">' . $list . '</ol>' : '<p class="muted">No approved scores yet.</p>', 'cols' => ['#', 'Student', 'Class', 'Average %'], 'rows' => array_map(fn($p, $i) => [$i + 1, $p['name'], $p['class'], $p['average']], $t, array_keys($t)), 'href' => 'exams', 'report' => 'academic'];
    }

    private static function c_census(array $term): array
    {
        $d = self::report('census', $term);
        $T = $d['staff']['teaching'];
        return [
            'chart' => '<div class="w-mini">'
                . '<div><b>' . (int) array_sum(array_column($d['rows'], 'total')) . '</b><span>Students on roll</span></div>'
                . '<div><b>' . ((int) array_sum(array_column($d['rows'], 'sen_boys')) + (int) array_sum(array_column($d['rows'], 'sen_girls'))) . '</b><span>Special needs</span></div>'
                . '<div><b>' . (int) ($T['total'] ?? 0) . '</b><span>Teachers (' . (int) ($T['trained'] ?? 0) . ' trained)</span></div>'
                . '<div><b>' . count($d['licenceList']) . '</b><span>NTC licences missing / expiring</span></div></div>',
            'note' => 'For the GES annual school census (EMIS), ' . $d['year']['name'] . '. Print the full return with "Full report".',
            'cols' => ['Level', 'Streams', 'Boys', 'Girls', 'Total', 'Repeaters B', 'Repeaters G', 'Special needs B', 'Special needs G'],
            'rows' => array_map(fn($r) => [$r['name'], (int) $r['streams'], (int) $r['boys'], (int) $r['girls'], (int) $r['total'], (int) $r['rep_boys'], (int) $r['rep_girls'], (int) $r['sen_boys'], (int) $r['sen_girls']], $d['rows']),
            'report' => 'census', 'showTable' => true,
        ];
    }

    private static function c_upcoming(array $term): array
    {
        $ev = db()->all('SELECT * FROM calendar_events WHERE end_date >= CURDATE() ORDER BY start_date, start_time LIMIT 6');
        $list = '';
        foreach ($ev as $e) {
            $list .= '<li><a href="' . e(url('calendar/' . $e['id'])) . '">' . e($e['title']) . '</a><small class="muted">' . e(fmt_date($e['start_date'], 'D j M')) . ' · ' . e(CalendarEvent::label($e['category'])) . '</small></li>';
        }
        return ['chart' => $ev ? '<ul class="w-list stack">' . $list . '</ul>' : '<p class="muted">Nothing scheduled.</p>', 'cols' => ['Event', 'Starts', 'Ends', 'Type'], 'rows' => array_map(fn($e) => [$e['title'], $e['start_date'], $e['end_date'], CalendarEvent::label($e['category'])], $ev), 'href' => 'calendar'];
    }

    private static function k_welfare(array $term): array
    {
        $w = Welfare::summary($term);
        return ['value' => (string) ($w['open'] ?? 0), 'sub' => (int) ($w['overdue'] ?? 0) . ' follow-up(s) overdue · ' . (int) ($w['safeguarding'] ?? 0) . ' safeguarding',
            'trend' => null, 'tone' => !empty($w['overdue']) || !empty($w['safeguarding']) ? 'warn' : null, 'href' => 'welfare'];
    }

    private static function c_behaviour(array $term): array
    {
        $rows = db()->all("SELECT kind, category, COUNT(*) AS n FROM behaviour_records WHERE occurred_on BETWEEN ? AND ? GROUP BY kind, category ORDER BY kind, n DESC", [$term['start_date'], self::to($term)]);
        $con = array_values(array_filter($rows, fn($r) => $r['kind'] === 'concern'));
        $mer = array_sum(array_column(array_filter($rows, fn($r) => $r['kind'] === 'merit'), 'n'));
        return [
            'chart' => $con ? Charts::hbars(array_map(fn($r) => [$r['category'], (float) $r['n'], null, $r['n'] . ' concern(s)'], array_slice($con, 0, 8))) : '<p class="w-ok">✓ No behaviour concerns this term.</p>',
            'note' => 'Concerns by type · ' . $mer . ' merits and ' . array_sum(array_column($con, 'n')) . ' concerns this term',
            'cols' => ['Type', 'Record', 'Count'], 'rows' => array_map(fn($r) => [$r['kind'] === 'merit' ? 'Merit' : 'Concern', $r['category'], (int) $r['n']], $rows), 'href' => 'welfare/behaviour',
        ];
    }

    private static function c_welfare(array $term): array
    {
        [$w, $p] = Welfare::visible();
        $c = array_column(db()->all("SELECT w.kind, COUNT(*) AS n FROM welfare_cases w WHERE w.status <> 'closed' AND $w GROUP BY w.kind", $p), 'n', 'kind');
        $rows = array_map(fn($k, $v) => [$v[0], (float) ($c[$k] ?? 0), url('welfare/cases', ['kind' => $k])], array_keys(Welfare::KINDS), Welfare::KINDS);
        return ['chart' => Charts::hbars($rows), 'cols' => ['Case type', 'Open'], 'rows' => array_map(fn($r) => [$r[0], (int) $r[1]], $rows), 'href' => 'welfare/cases'];
    }

    // ------------------------------------------------------------------ fees & finance

    private static function c_fee_weeks(array $term): array
    {
        $by = array_column(db()->all("SELECT paid_on, SUM(amount) AS a FROM payments WHERE status = 'completed' AND paid_on BETWEEN ? AND ? GROUP BY paid_on", [date('Y-m-d', strtotime('monday this week', strtotime($term['start_date']))), self::to($term)]), 'a', 'paid_on');
        $w = self::weeks($term, $by, fn($v) => Fees::money($v));
        return ['chart' => Charts::bars($w, ['format' => fn($v) => Fees::money($v), 'label' => 'Fees received per week', 'height' => 210, 'width' => self::$w]), 'note' => 'All payment methods · total ' . Fees::money(array_sum(array_column($w, 1))), 'href' => 'fees'] + self::weekTable($w, 'Received (GHS)');
    }

    private static function c_fee_class(array $term): array
    {
        $rows = self::report('fees', $term)['rows'];
        $with = array_values(array_filter($rows, fn($r) => $r['pct'] !== null));
        return [
            'chart' => Charts::hbars(array_map(fn($r) => [$r['name'], (float) $r['pct'], null, round((float) $r['pct']) . '% · ' . Fees::money($r['outstanding']) . ' owed'], $with), ['max' => 100]),
            'note' => '% of this term\'s bills paid, per class',
            'cols' => ['Class', 'Billed (GHS)', 'Paid (GHS)', 'Outstanding (GHS)', '% collected'],
            'rows' => array_map(fn($r) => [$r['name'], round((float) $r['billed'], 2), round((float) $r['paid'], 2), round((float) $r['outstanding'], 2), $r['pct']], $rows),
            'href' => 'fees/debtors', 'report' => 'fees',
        ];
    }

    private static function c_fee_method(array $term): array
    {
        $m = self::report('fees', $term)['methods'];
        $names = ['cash' => 'Cash', 'momo' => 'Mobile Money', 'bank' => 'Bank', 'online' => 'Online (card / MoMo)', 'cheque' => 'Cheque'];
        $total = array_sum(array_column($m, 'amt'));
        return [
            'chart' => Charts::hbars(array_map(fn($r) => [$names[$r['method']] ?? ucfirst((string) $r['method']), (float) $r['amt'], null, Fees::money($r['amt']) . ' · ' . round($r['amt'] * 100 / max(1, $total)) . '%'], $m)),
            'cols' => ['Method', 'Payments', 'Amount (GHS)'], 'rows' => array_map(fn($r) => [$names[$r['method']] ?? $r['method'], (int) $r['n'], round((float) $r['amt'], 2)], $m),
            'href' => 'fees', 'report' => 'fees',
        ];
    }

    private static function c_accounts(array $term): array
    {
        $rows = array_map(fn($a) => [$a['name'], Finance::balance((int) $a['id'])], Finance::accounts());
        return [
            'chart' => Charts::hbars(array_map(fn($r) => [$r[0], max(0.0, $r[1]), null, Fees::money($r[1])], $rows)),
            'note' => 'Total ' . Fees::money(array_sum(array_column($rows, 1))) . ' · as at today',
            'cols' => ['Account', 'Balance (GHS)'], 'rows' => array_map(fn($r) => [$r[0], round($r[1], 2)], $rows), 'href' => 'finance', 'report' => 'money',
        ];
    }

    private static function c_spending(array $term): array
    {
        $rows = db()->all(
            "SELECT c.name, SUM(t.amount) AS amt FROM fin_transactions t JOIN fin_categories c ON c.id = t.category_id
             WHERE t.kind = 'expense' AND t.status <> 'void' AND t.txn_date BETWEEN ? AND ? GROUP BY c.id, c.name ORDER BY amt DESC",
            [$term['start_date'], self::to($term)]
        );
        $total = array_sum(array_column($rows, 'amt'));
        $income = (float) db()->value("SELECT COALESCE(SUM(amount), 0) FROM fin_transactions WHERE kind = 'income' AND status <> 'void' AND txn_date BETWEEN ? AND ?", [$term['start_date'], self::to($term)]);
        return [
            'chart' => Charts::hbars(array_map(fn($r) => [$r['name'], (float) $r['amt'], null, Fees::money($r['amt'])], array_slice($rows, 0, 8))),
            'note' => 'Spent ' . Fees::money($total) . ' · money in ' . Fees::money($income) . ' · net ' . Fees::money($income - $total),
            'cols' => ['Category', 'Spent (GHS)'], 'rows' => array_map(fn($r) => [$r['name'], round((float) $r['amt'], 2)], $rows), 'href' => 'finance',
        ];
    }

    private static function c_requisitions(array $term): array
    {
        $c = array_column(db()->all("SELECT status, COUNT(*) AS n, COALESCE(SUM(amount), 0) AS amt FROM requisitions WHERE status NOT IN ('draft','cancelled') AND created_at >= ? GROUP BY status", [$term['start_date']]), null, 'status');
        $rows = [];
        foreach (Requisition::STATUS as $k => [$label]) {
            if (isset($c[$k])) {
                $rows[] = [$label, (int) $c[$k]['n'], null, (int) $c[$k]['n'] . ' · ' . Fees::money($c[$k]['amt'])];
            }
        }
        return ['chart' => Charts::hbars($rows), 'note' => 'Raised this term', 'cols' => ['Stage', 'Requisitions'], 'rows' => array_map(fn($r) => [$r[0], $r[1]], $rows), 'href' => 'requisitions'];
    }

    private static function c_payroll(array $term): array
    {
        $runs = array_reverse(db()->all("SELECT period, gross, net, staff_count FROM payroll_runs WHERE status IN ('approved','paid') ORDER BY period DESC LIMIT 6"));
        return [
            'chart' => Charts::bars(array_map(fn($r) => [date('M y', strtotime($r['period'] . '-01')), (float) $r['gross'], date('F Y', strtotime($r['period'] . '-01')) . ': gross ' . Fees::money($r['gross']) . ', net ' . Fees::money($r['net'])], $runs), ['format' => fn($v) => Fees::money($v), 'label' => 'Gross pay by month', 'height' => 210, 'width' => self::$w]),
            'note' => 'Gross pay, approved and paid months',
            'cols' => ['Month', 'Staff', 'Gross (GHS)', 'Net (GHS)'], 'rows' => array_map(fn($r) => [$r['period'], (int) $r['staff_count'], round((float) $r['gross'], 2), round((float) $r['net'], 2)], $runs),
            'href' => 'payroll',
        ];
    }

    // ------------------------------------------------------------------ staff & HR

    private static function staffSummary(array $term): array
    {
        static $memo = [];
        $from = max($term['start_date'], date('Y-m-d', strtotime('-40 days', strtotime(self::to($term)))));
        return $memo[$term['id']] ??= StaffAttendance::summary($term['start_date'], self::to($term)) + ['recent' => $from];
    }

    private static function c_staff_trend(array $term): array
    {
        $s = self::staffSummary($term);
        $days = array_slice($s['days'], -15);
        $pts = [];
        foreach ($days as $d) {
            $p = $l = 0;
            foreach ($s['rows'] as $r) {
                $g = $r['grid'][$d] ?? 'A';
                $p += $g !== 'A' ? 1 : 0;
                $l += $g === 'L' ? 1 : 0;
            }
            $n = max(1, count($s['rows']));
            $pts[] = [date('j M', strtotime($d)), round($p * 100 / $n, 1), date('D j M', strtotime($d)) . ': ' . $p . ' of ' . count($s['rows']) . ' signed in, ' . $l . ' late', $l];
        }
        return [
            'chart' => Charts::line($pts, ['max' => 100, 'format' => fn($v) => $v . '%', 'label' => 'Staff signed in', 'height' => 210, 'width' => self::$w]),
            'note' => '% of staff who signed in, last ' . count($pts) . ' school days',
            'cols' => ['Day', 'Signed in %', 'Late'], 'rows' => array_map(fn($p) => [explode(':', $p[2])[0], $p[1], $p[3]], $pts), 'href' => 'attendance/staff',
        ];
    }

    private static function c_staff_late(array $term): array
    {
        $rows = array_values(array_filter(self::staffSummary($term)['rows'], fn($r) => $r['late'] > 0));
        usort($rows, fn($a, $b) => $b['late'] <=> $a['late']);
        return [
            'chart' => $rows ? Charts::hbars(array_map(fn($r) => [$r['name'], (float) $r['late'], null, $r['late'] . ' days'], array_slice($rows, 0, 8))) : '<p class="w-ok">✓ No late arrivals this term.</p>',
            'cols' => ['Staff', 'Days late', 'Days present', 'Absent', 'Average sign-in'], 'rows' => array_map(fn($r) => [$r['name'], $r['late'], $r['present'], $r['absent'], $r['avg_in']], $rows),
            'href' => 'attendance/staff',
        ];
    }

    private static function c_hr_alerts(array $term): array
    {
        $rows = [];
        if (can('payroll.manage') || can('teachers.view')) {
            $rows[] = ['NTC licences missing or expiring', Payroll::ntcProblems(), url(can('payroll.manage') ? 'payroll/staff' : 'teachers')];
        }
        if (can('appraisal.manage')) {
            $rows[] = ['Appraisals not completed this term', (int) db()->value("SELECT COUNT(*) FROM staff s WHERE s.is_active = 1 AND s.user_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM appraisals a WHERE a.user_id = s.user_id AND a.term_id = ? AND a.status = 'completed')", [$term['id']]), url('teachers')];
        }
        if (can('timesheets.manage')) {
            $last = date('Y-m', strtotime('first day of last month'));
            $rows[] = ['Timesheets for ' . date('F', strtotime($last . '-01')) . ' not yet approved', max(0, count(StaffAttendance::staff()) - (int) db()->value("SELECT COUNT(*) FROM timesheets WHERE period = ? AND status = 'approved'", [$last])), url('timesheets')];
        }
        if (can('payroll.manage')) {
            $rows[] = ['Staff without a salary set', (int) db()->value("SELECT COUNT(*) FROM staff WHERE is_active = 1 AND COALESCE(basic_salary, 0) = 0 AND COALESCE(daily_rate, 0) = 0"), url('payroll/staff')];
        }
        $html = '<ul class="w-list">';
        foreach ($rows as [$label, $n, $href]) {
            $html .= '<li><a href="' . e($href) . '">' . e($label) . '</a><span class="w-pill ' . ($n ? 'warn' : 'good') . '">' . ($n ?: '✓') . '</span></li>';
        }
        return ['chart' => $html . '</ul>', 'cols' => ['Follow-up', 'Count'], 'rows' => array_map(fn($r) => [$r[0], $r[1]], $rows)];
    }

    // ------------------------------------------------------------------ operations & services

    private static function c_canteen(array $term): array
    {
        $by = array_column(db()->all("SELECT DATE(created_at) AS d, SUM(total) AS a FROM canteen_sales WHERE status <> 'void' AND created_at >= ? GROUP BY d", [$term['start_date']]), 'a', 'd');
        $w = self::weeks($term, $by, fn($v) => Fees::money($v));
        $wallets = (float) db()->value('SELECT COALESCE(SUM(balance), 0) FROM canteen_wallets');
        return ['chart' => Charts::bars($w, ['format' => fn($v) => Fees::money($v), 'label' => 'Canteen sales per week', 'height' => 210, 'width' => self::$w]), 'note' => 'Total ' . Fees::money(array_sum(array_column($w, 1))) . ' · ' . Fees::money($wallets) . ' held in student wallets', 'href' => 'canteen'] + self::weekTable($w, 'Sales (GHS)');
    }

    private static function c_shop(array $term): array
    {
        $c = array_column(db()->all('SELECT status, COUNT(*) AS n, SUM(total) AS amt FROM shop_orders WHERE created_at >= ? GROUP BY status', [$term['start_date']]), null, 'status');
        $rows = [];
        foreach (Shop::STATUS as $k => [$label]) {
            $rows[] = [$label, (float) ($c[$k]['n'] ?? 0), null, (int) ($c[$k]['n'] ?? 0) . ' · ' . Fees::money($c[$k]['amt'] ?? 0)];
        }
        $sold = (float) array_sum(array_map(fn($k) => (float) ($c[$k]['amt'] ?? 0), ['paid', 'ready', 'collected']));
        return ['chart' => Charts::hbars($rows), 'note' => 'This term · paid sales ' . Fees::money($sold), 'cols' => ['Status', 'Orders'], 'rows' => array_map(fn($r) => [$r[0], (int) $r[1]], $rows), 'href' => 'shop/admin'];
    }

    private static function c_library(array $term): array
    {
        $r = db()->one(
            "SELECT SUM(status = 'out' AND due_on >= CURDATE()) AS onloan, SUM(status = 'out' AND due_on < CURDATE()) AS overdue,
                    SUM(issued_on >= ?) AS issued, SUM(returned_on >= ?) AS returned FROM library_loans",
            [$term['start_date'], $term['start_date']]
        );
        $rows = [['On loan (not yet due)', (float) $r['onloan']], ['Overdue', (float) $r['overdue']], ['Issued this term', (float) $r['issued']], ['Returned this term', (float) $r['returned']]];
        return ['chart' => Charts::hbars($rows), 'cols' => ['Loans', 'Books'], 'rows' => array_map(fn($x) => [$x[0], (int) $x[1]], $rows), 'href' => 'library'];
    }

    private static function c_transport(array $term): array
    {
        $rows = db()->all(
            'SELECT r.name, r.capacity, COUNT(a.id) AS riders FROM transport_routes r
             LEFT JOIN transport_assignments a ON a.route_id = r.id AND a.academic_year_id = ? AND a.ended_on IS NULL
             WHERE r.is_active = 1 GROUP BY r.id ORDER BY r.name',
            [$term['academic_year_id']]
        );
        return [
            'chart' => Charts::hbars(array_map(fn($r) => [$r['name'], (float) $r['riders'], null, $r['riders'] . ' of ' . (int) $r['capacity'] . ' seats'], $rows), ['max' => max(1, ...array_map(fn($r) => (int) $r['capacity'], $rows ?: [['capacity' => 1]]))]),
            'note' => 'Papers expiring: ' . TransportController::papersDue() . ' route(s)',
            'cols' => ['Route', 'Riders', 'Seats'], 'rows' => array_map(fn($r) => [$r['name'], (int) $r['riders'], (int) $r['capacity']], $rows), 'href' => 'transport',
        ];
    }

    private static function c_crm(array $term): array
    {
        $c = array_column(db()->all('SELECT stage, COUNT(*) AS n FROM crm_enquiries GROUP BY stage'), 'n', 'stage');
        $rows = array_map(fn($k, $s) => [$s[0], (float) ($c[$k] ?? 0), url('crm', ['stage' => $k])], array_keys(Crm::STAGES), Crm::STAGES);
        return ['chart' => Charts::hbars($rows), 'cols' => ['Stage', 'Enquiries'], 'rows' => array_map(fn($r) => [$r[0], (int) $r[1]], $rows), 'href' => 'crm'];
    }

    private static function c_sms(array $term): array
    {
        $by = array_column(db()->all("SELECT DATE(created_at) AS d, COUNT(*) AS n FROM notification_outbox WHERE status = 'sent' AND created_at >= ? GROUP BY d", [$term['start_date']]), 'n', 'd');
        $w = self::weeks($term, $by, fn($v) => number_format($v) . ' sent');
        $failed = (int) db()->value("SELECT COUNT(*) FROM notification_outbox WHERE status = 'failed' AND created_at >= ?", [$term['start_date']]);
        return ['chart' => Charts::bars($w, ['label' => 'Messages sent per week', 'height' => 210, 'width' => self::$w]), 'note' => 'SMS, WhatsApp and email · ' . $failed . ' failed this term', 'href' => 'messages/outbox'] + self::weekTable($w, 'Sent');
    }

    private static function c_health(array $term): array
    {
        $by = array_column(db()->all('SELECT DATE(seen_at) AS d, COUNT(*) AS n FROM health_visits WHERE seen_at >= ? GROUP BY d', [$term['start_date']]), 'n', 'd');
        $w = self::weeks($term, $by, fn($v) => (int) $v . ' visits');
        return ['chart' => Charts::bars($w, ['label' => 'Sick bay visits per week', 'height' => 210, 'width' => self::$w]), 'note' => 'Total ' . (int) array_sum(array_column($w, 1)) . ' visits this term', 'href' => 'health'] + self::weekTable($w, 'Visits');
    }

    private static function c_documents(array $term): array
    {
        $docs = Documents::expiring(Documents::REMIND_DAYS, can('documents.staff'));
        $list = '';
        foreach (array_slice($docs, 0, 6) as $d) {
            $past = $d['expires_on'] < date('Y-m-d');
            $list .= '<li>' . e($d['title']) . '<span class="w-pill ' . ($past ? 'bad' : 'warn') . '">' . e(fmt_date($d['expires_on'])) . '</span></li>';
        }
        return [
            'chart' => $docs ? '<ul class="w-list">' . $list . '</ul>' : '<p class="w-ok">✓ Nothing expiring in the next ' . Documents::REMIND_DAYS . ' days.</p>',
            'cols' => ['Document', 'Expires'], 'rows' => array_map(fn($d) => [$d['title'], $d['expires_on']], $docs), 'href' => 'documents/expiring',
        ];
    }
}
