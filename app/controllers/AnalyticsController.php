<?php
declare(strict_types=1);

/**
 * School reports for the Head, Academic Coordinator and Bursar: enrolment census, attendance,
 * academic performance and fee collection — per class, for a chosen term, with print and CSV.
 */
class AnalyticsController extends Controller
{
    public const TABS = [
        'enrolment' => ['Enrolment', 'analytics.view'],
        'attendance' => ['Attendance', 'attendance.view'],
        'academic' => ['Academic performance', 'reports.view'],
        'fees' => ['Fee collection', 'fees.view'],
        'money' => ['Money position', 'finance.view'],
        'census' => ['School census preparation', 'analytics.view'],
    ];

    /** Printable School reports (and their CSV), linked from the foot of the Analytic Dashboard. */
    public function index(): void
    {
        $vars = $this->build((string) ($_GET['tab'] ?? 'enrolment'), isset($_GET['term']) ? (int) $_GET['term'] : null);
        if (isset($_GET['export'])) {
            $this->csv($vars['tab'], $vars['term'], $vars['d']);
        }
        if ($vars['term']) {
            $this->view('analytics/print', $vars + ['title' => 'School reports — ' . self::TABS[$vars['tab']][0]], 'layouts/print');
            return;
        }
        flash('warning', 'Create an academic year and terms first.');
        redirect('dashboard');
    }

    /** Data for one tab and term (the term defaults to the current one): [tab, terms, term, d]. */
    public function build(string $tab, ?int $termId): array
    {
        if (!isset(self::TABS[$tab]) || !can(self::TABS[$tab][1])) {
            $tab = 'enrolment';
        }
        $terms = db()->all('SELECT t.*, y.name AS year_name FROM terms t JOIN academic_years y ON y.id = t.academic_year_id ORDER BY t.start_date DESC');
        $tid = $termId ?: (int) (current_term()['id'] ?? ($terms[0]['id'] ?? 0));
        $term = null;
        foreach ($terms as $t) {
            if ((int) $t['id'] === $tid) {
                $term = $t;
            }
        }
        return ['tab' => $tab, 'terms' => $terms, 'term' => $term, 'd' => $term ? $this->{$tab}($term) : ['rows' => [], 'cols' => []]];
    }

    // ------------------------------------------------------------------ tabs

    private function enrolment(array $term): array
    {
        $yid = (int) $term['academic_year_id'];
        $year = db()->one('SELECT * FROM academic_years WHERE id = ?', [$yid]);
        $rows = db()->all(
            "SELECT c.id, c.name, c.capacity, l.category, u.name AS teacher,
                    SUM(p.gender = 'male') AS boys, SUM(p.gender = 'female') AS girls, COUNT(p.id) AS total,
                    SUM(p.admitted_on BETWEEN ? AND ?) AS new_pupils,
                    SUM(p.denomination LIKE '%Adventist%' OR p.denomination LIKE 'SDA%') AS sda
             FROM classes c JOIN levels l ON l.id = c.level_id LEFT JOIN users u ON u.id = c.class_teacher_id
             LEFT JOIN enrolments e ON e.class_id = c.id AND e.academic_year_id = ? AND e.status = 'active'
             LEFT JOIN pupils p ON p.id = e.pupil_id AND p.status = 'active' AND p.deleted_at IS NULL
             WHERE c.is_active = 1 GROUP BY c.id ORDER BY l.sort_order, c.name",
            [$year['start_date'], $year['end_date'], $yid]
        );
        $left = db()->all(
            "SELECT p.status, COUNT(*) AS n FROM pupils p JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ?
             WHERE p.status IN ('withdrawn','transferred') AND p.left_on BETWEEN ? AND ? GROUP BY p.status",
            [$yid, $year['start_date'], $year['end_date']]
        );
        $ages = db()->all(
            "SELECT TIMESTAMPDIFF(YEAR, p.date_of_birth, ?) AS age, COUNT(*) AS n FROM enrolments e JOIN pupils p ON p.id = e.pupil_id
             WHERE e.academic_year_id = ? AND e.status = 'active' AND p.status = 'active' AND p.deleted_at IS NULL GROUP BY age ORDER BY age",
            [$year['start_date'], $yid]
        );
        return [
            'year' => $year, 'rows' => $rows, 'left' => array_column($left, 'n', 'status'), 'ages' => $ages,
            'cols' => ['Class', 'Class teacher', 'Boys', 'Girls', 'Total', 'Capacity', 'New this year', 'SDA'],
            'csv' => fn($r) => [$r['name'], $r['teacher'], (int) $r['boys'], (int) $r['girls'], (int) $r['total'], (int) $r['capacity'], (int) $r['new_pupils'], (int) $r['sda']],
        ];
    }

    /**
     * Local school census preparation summary. The report is not validated against a GES/EMIS template.
     */
    private function census(array $term): array
    {
        $yid = (int) $term['academic_year_id'];
        $year = db()->one('SELECT * FROM academic_years WHERE id = ?', [$yid]);
        $prev = db()->one('SELECT id FROM academic_years WHERE start_date < ? ORDER BY start_date DESC LIMIT 1', [$year['start_date']]);
        $rows = db()->all(
            "SELECT l.id, l.name, l.category, COUNT(DISTINCT c.id) AS streams,
                    SUM(p.gender = 'male') AS boys, SUM(p.gender = 'female') AS girls, COUNT(p.id) AS total,
                    SUM(p.gender = 'male' AND pe.status = 'repeated') AS rep_boys, SUM(p.gender = 'female' AND pe.status = 'repeated') AS rep_girls,
                    SUM(p.gender = 'male' AND p.sen_category IS NOT NULL) AS sen_boys, SUM(p.gender = 'female' AND p.sen_category IS NOT NULL) AS sen_girls
             FROM levels l JOIN classes c ON c.level_id = l.id AND c.is_active = 1
             LEFT JOIN enrolments e ON e.class_id = c.id AND e.academic_year_id = ? AND e.status = 'active'
             LEFT JOIN pupils p ON p.id = e.pupil_id AND p.status = 'active' AND p.deleted_at IS NULL
             LEFT JOIN enrolments pe ON pe.pupil_id = p.id AND pe.academic_year_id = ?
             GROUP BY l.id ORDER BY l.sort_order",
            [$yid, (int) ($prev['id'] ?? 0)]
        );
        // Age (completed years at the start of the school year) by level category and gender
        $ageRows = db()->all(
            "SELECT LEAST(GREATEST(TIMESTAMPDIFF(YEAR, p.date_of_birth, ?), 2), 17) AS age, l.category, p.gender, COUNT(*) AS n
             FROM enrolments e JOIN pupils p ON p.id = e.pupil_id JOIN classes c ON c.id = e.class_id JOIN levels l ON l.id = c.level_id
             WHERE e.academic_year_id = ? AND e.status = 'active' AND p.status = 'active' AND p.deleted_at IS NULL
             GROUP BY age, l.category, p.gender ORDER BY age",
            [$year['start_date'], $yid]
        );
        $ages = [];
        foreach ($ageRows as $a) {
            $ages[(int) $a['age']][$a['category']][$a['gender']] = (int) $a['n'];
        }
        ksort($ages);
        $sen = db()->all(
            "SELECT p.sen_category, SUM(p.gender = 'male') AS boys, SUM(p.gender = 'female') AS girls
             FROM enrolments e JOIN pupils p ON p.id = e.pupil_id
             WHERE e.academic_year_id = ? AND e.status = 'active' AND p.status = 'active' AND p.deleted_at IS NULL AND p.sen_category IS NOT NULL
             GROUP BY p.sen_category",
            [$yid]
        );
        $staffRows = db()->all("SELECT * FROM staff WHERE is_active = 1");
        $staff = ['teaching' => [], 'non_teaching' => []];
        foreach ($staffRows as $s) {
            $g = $s['gender'] ?: 'unknown';
            $k = &$staff[$s['category']];
            $k[$g] = ($k[$g] ?? 0) + 1;
            $k['total'] = ($k['total'] ?? 0) + 1;
            if ($s['category'] === 'teaching') {
                $trained = $s['qualification'] && (Payroll::QUALIFICATION[$s['qualification']][1] ?? false);
                $k[$trained ? 'trained' : ($s['qualification'] ? 'untrained' : 'qual_unknown')] = ($k[$trained ? 'trained' : ($s['qualification'] ? 'untrained' : 'qual_unknown')] ?? 0) + 1;
                $st = Payroll::ntcStatus($s);
                $k['ntc_' . $st] = ($k['ntc_' . $st] ?? 0) + 1;
            }
            unset($k);
        }
        $licenceList = array_values(array_filter($staffRows, fn($s) => in_array(Payroll::ntcStatus($s), ['missing', 'expired', 'expiring'], true)));
        return [
            'year' => $year, 'rows' => $rows, 'ages' => $ages, 'sen' => $sen, 'staff' => $staff, 'licenceList' => $licenceList,
            'cols' => ['Level', 'Streams', 'Boys', 'Girls', 'Total', 'Repeaters (boys)', 'Repeaters (girls)', 'Special needs (boys)', 'Special needs (girls)'],
            'csv' => fn($r) => [$r['name'], (int) $r['streams'], (int) $r['boys'], (int) $r['girls'], (int) $r['total'], (int) $r['rep_boys'], (int) $r['rep_girls'], (int) $r['sen_boys'], (int) $r['sen_girls']],
        ];
    }

    private function attendance(array $term): array
    {
        $to = min($term['end_date'], date('Y-m-d'));
        $rows = db()->all(
            "SELECT c.id, c.name, COUNT(DISTINCT r.id) AS days,
                    SUM(a.status IN ('present','late')) AS present, SUM(a.status = 'absent') AS absent, SUM(a.status = 'late') AS late,
                    SUM(a.status IN ('excused','sick')) AS excused, COUNT(a.id) AS marks
             FROM classes c JOIN levels l ON l.id = c.level_id
             LEFT JOIN attendance_registers r ON r.class_id = c.id AND r.date BETWEEN ? AND ?
             LEFT JOIN attendance_records a ON a.register_id = r.id
             WHERE c.is_active = 1 GROUP BY c.id ORDER BY l.sort_order, c.name",
            [$term['start_date'], $to]
        );
        foreach ($rows as &$r) {
            $r['rate'] = (int) $r['marks'] ? round($r['present'] * 100 / $r['marks'], 1) : null;
        }
        unset($r);
        $chronic = db()->all(
            "SELECT p.id, p.admission_no, p.first_name, p.last_name, c.name AS class_name, COUNT(a.id) AS marks,
                    SUM(a.status = 'absent') AS absent, ROUND(SUM(a.status IN ('present','late')) * 100 / COUNT(a.id), 1) AS rate,
                    (SELECT g.phone FROM pupil_guardian pg JOIN guardians g ON g.id = pg.guardian_id WHERE pg.pupil_id = p.id ORDER BY pg.is_primary DESC LIMIT 1) AS phone
             FROM attendance_records a JOIN attendance_registers r ON r.id = a.register_id JOIN pupils p ON p.id = a.pupil_id JOIN classes c ON c.id = r.class_id
             WHERE r.date BETWEEN ? AND ? AND p.status = 'active' GROUP BY p.id, c.name
             HAVING marks >= 5 AND rate < 85 ORDER BY rate, absent DESC LIMIT 100",
            [$term['start_date'], $to]
        );
        return [
            'rows' => $rows, 'chronic' => $chronic,
            'cols' => ['Class', 'Days marked', 'Present/late', 'Absent', 'Late', 'Excused/sick', 'Attendance %'],
            'csv' => fn($r) => [$r['name'], (int) $r['days'], (int) $r['present'], (int) $r['absent'], (int) $r['late'], (int) $r['excused'], $r['rate']],
        ];
    }

    private function academic(array $term): array
    {
        $classes = db()->all(
            "SELECT DISTINCT c.id, c.name, l.sort_order FROM classes c JOIN levels l ON l.id = c.level_id JOIN enrolments e ON e.class_id = c.id AND e.academic_year_id = ?
             WHERE c.is_active = 1 AND l.category IN ('primary','jhs','kg') ORDER BY l.sort_order, c.name",
            [$term['academic_year_id']]
        );
        $rows = [];
        $subjects = [];
        $top = [];
        foreach ($classes as $c) {
            $R = Results::classResults((int) $c['id'], (int) $term['id']);
            $avgs = array_filter(array_map(fn($s) => $s['average'], $R['summary']), fn($v) => $v !== null);
            if (!$avgs) {
                $rows[] = ['id' => $c['id'], 'name' => $c['name'], 'pupils' => count($R['pupils']), 'scored' => 0, 'average' => null, 'pass' => null, 'best' => null, 'sheets' => $this->sheetCounts($R)];
                continue;
            }
            $pass = (float) $R['scheme']['pass_mark'];
            arsort($avgs);
            $bestId = array_key_first($avgs);
            $names = array_column($R['pupils'], null, 'id');
            $rows[] = [
                'id' => $c['id'], 'name' => $c['name'], 'pupils' => count($R['pupils']), 'scored' => count($avgs),
                'average' => round(array_sum($avgs) / count($avgs), 1),
                'pass' => round(count(array_filter($avgs, fn($v) => $v >= $pass)) * 100 / count($avgs), 1),
                'best' => $names[$bestId]['first_name'] . ' ' . $names[$bestId]['last_name'] . ' (' . $avgs[$bestId] . ')',
                'sheets' => $this->sheetCounts($R),
            ];
            foreach ($avgs as $pid => $v) {
                $top[] = ['name' => $names[$pid]['first_name'] . ' ' . $names[$pid]['last_name'], 'class' => $c['name'], 'average' => $v, 'id' => $pid];
            }
            foreach ($R['subjects'] as $s) {
                $st = $R['subjectStats'][$s['id']];
                if (!$st['count']) {
                    continue;
                }
                $k = $s['name'];
                $subjects[$k] = $subjects[$k] ?? ['name' => $k, 'n' => 0, 'sum' => 0.0, 'passed' => 0, 'classes' => []];
                $subjects[$k]['n'] += $st['count'];
                $subjects[$k]['sum'] += $st['average'] * $st['count'];
                $subjects[$k]['passed'] += $st['passed'];
                $subjects[$k]['classes'][] = $c['name'] . ': ' . $st['average'];
            }
        }
        foreach ($subjects as &$s) {
            $s['average'] = round($s['sum'] / $s['n'], 1);
            $s['pass'] = round($s['passed'] * 100 / $s['n'], 1);
        }
        unset($s);
        usort($subjects, fn($a, $b) => $b['average'] <=> $a['average']);
        usort($top, fn($a, $b) => $b['average'] <=> $a['average']);
        return [
            'rows' => $rows, 'subjects' => $subjects, 'top' => array_slice($top, 0, 10),
            'cols' => ['Class', 'Students', 'With scores', 'Class average %', 'Pass rate %', 'Best student (average)', 'Sheets approved'],
            'csv' => fn($r) => [$r['name'], $r['pupils'], $r['scored'], $r['average'], $r['pass'], $r['best'], $r['sheets']['approved'] . '/' . $r['sheets']['total']],
        ];
    }

    private function sheetCounts(array $R): array
    {
        $st = array_count_values(array_map(fn($s) => (string) ($s['sheet_status'] ?? 'none'), $R['subjects']));
        return ['total' => count($R['subjects']), 'approved' => $st['approved'] ?? 0, 'submitted' => $st['submitted'] ?? 0];
    }

    private function fees(array $term): array
    {
        $rows = db()->all(
            "SELECT c.id, c.name, COUNT(i.id) AS bills, COALESCE(SUM(i.total), 0) AS billed, COALESCE(SUM(i.paid), 0) AS paid,
                    COALESCE(SUM(GREATEST(i.total - i.paid, 0)), 0) AS outstanding, SUM(i.status = 'paid') AS fully_paid
             FROM classes c JOIN levels l ON l.id = c.level_id
             LEFT JOIN invoices i ON i.class_id = c.id AND i.term_id = ? AND i.status <> 'cancelled'
             WHERE c.is_active = 1 GROUP BY c.id ORDER BY l.sort_order, c.name",
            [$term['id']]
        );
        foreach ($rows as &$r) {
            $r['pct'] = (float) $r['billed'] > 0 ? round($r['paid'] * 100 / $r['billed'], 1) : null;
        }
        unset($r);
        $methods = db()->all(
            "SELECT pay.method, COUNT(*) AS n, SUM(pay.amount) AS amt FROM payments pay JOIN invoices i ON i.id = pay.invoice_id
             WHERE pay.status = 'completed' AND i.term_id = ? GROUP BY pay.method ORDER BY amt DESC",
            [$term['id']]
        );
        $arrears = (float) db()->value(
            "SELECT COALESCE(SUM(GREATEST(i.total - i.paid, 0)), 0) FROM invoices i LEFT JOIN terms t ON t.id = i.term_id
             WHERE i.status <> 'cancelled' AND (t.start_date < ? OR (i.term_id IS NULL AND i.issue_date < ?))",
            [$term['start_date'], $term['start_date']]
        );
        return [
            'rows' => $rows, 'methods' => $methods, 'arrears' => $arrears,
            'cols' => ['Class', 'Bills', 'Billed (GHS)', 'Paid (GHS)', 'Outstanding (GHS)', '% collected', 'Fully paid'],
            'csv' => fn($r) => [$r['name'], (int) $r['bills'], number_format((float) $r['billed'], 2, '.', ''), number_format((float) $r['paid'], 2, '.', ''), number_format((float) $r['outstanding'], 2, '.', ''), $r['pct'], (int) $r['fully_paid']],
        ];
    }

    /** Cash, cash runway, fees collected and the students needed to cover the term's spending. */
    private function money(array $term): array
    {
        $rows = [];
        $cash = 0.0;
        foreach (Finance::accounts() as $a) {
            $b = Finance::balance((int) $a['id']);
            $cash += $b;
            $rows[] = ['name' => $a['name'], 'kind' => Finance::ACCOUNT_KINDS[$a['kind']] ?? $a['kind'], 'balance' => $b];
        }
        $spent3 = (float) db()->value("SELECT COALESCE(SUM(CASE WHEN kind = 'refund' THEN -amount ELSE amount END), 0) FROM fin_transactions WHERE status = 'posted' AND kind IN ('expense','refund') AND txn_date > (CURDATE() - INTERVAL 3 MONTH)");
        $monthly = round($spent3 / 3, 2);
        $to = min(date('Y-m-d'), $term['end_date']);
        $termSpend = (float) db()->value("SELECT COALESCE(SUM(CASE WHEN kind = 'refund' THEN -amount ELSE amount END), 0) FROM fin_transactions WHERE status = 'posted' AND kind IN ('expense','refund') AND txn_date BETWEEN ? AND ?", [$term['start_date'], $to]);
        $bill = db()->one("SELECT COALESCE(SUM(total), 0) AS billed, COALESCE(SUM(paid), 0) AS paid, COUNT(DISTINCT pupil_id) AS pupils FROM invoices WHERE term_id = ? AND status <> 'cancelled' AND kind = 'term'", [$term['id']]);
        $avgFee = (int) $bill['pupils'] > 0 ? (float) $bill['billed'] / (int) $bill['pupils'] : 0.0;
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = date('Y-m', strtotime("first day of -$i months"));
            $r = db()->one("SELECT COALESCE(SUM(CASE WHEN kind = 'income' THEN amount ELSE 0 END), 0) AS inn, COALESCE(SUM(CASE WHEN kind = 'expense' THEN amount WHEN kind = 'refund' THEN -amount ELSE 0 END), 0) AS outt FROM fin_transactions WHERE status = 'posted' AND txn_date BETWEEN ? AND ?", [$m . '-01', date('Y-m-t', strtotime($m . '-01'))]);
            $months[] = ['month' => $m, 'in' => (float) $r['inn'], 'out' => (float) $r['outt']];
        }
        return [
            'rows' => $rows, 'cash' => round($cash, 2), 'monthly' => $monthly, 'runway' => $monthly > 0 ? round($cash / $monthly, 1) : null,
            'billed' => (float) $bill['billed'], 'paid' => (float) $bill['paid'], 'pupils' => (int) $bill['pupils'], 'termSpend' => round($termSpend, 2),
            'breakEven' => $avgFee > 0 ? (int) ceil($termSpend / $avgFee) : null, 'avgFee' => round($avgFee, 2), 'months' => $months,
            'cols' => ['Account', 'Kind', 'Balance (GHS)'],
            'csv' => fn($r) => [$r['name'], $r['kind'], number_format((float) $r['balance'], 2, '.', '')],
        ];
    }

    private function csv(string $tab, ?array $term, array $d): void
    {
        $filename = $tab . '-' . preg_replace('/[^A-Za-z0-9]+/', '-', ($term['year_name'] ?? '') . '-' . ($term['name'] ?? '')) . '.csv';
        if ($tab === 'census') {
            $head = ['Section', 'Item', 'Category', 'Male / boys', 'Female / girls', 'Total', 'Repeaters (boys)', 'Repeaters (girls)', 'Special needs (boys)', 'Special needs (girls)', 'Streams', 'Notes'];
            $out = csv_start($filename, $head, 'School census preparation');
            csv_row($out, ['Report', 'School census preparation', '', '', '', '', '', '', '', '', '', 'Local NISDAS summary; not a validated GES/EMIS return. Verify the current official template before submission.']);
            foreach ($d['rows'] as $r) {
                csv_row($out, ['Enrolment by level', $r['name'], $r['category'], (int) $r['boys'], (int) $r['girls'], (int) $r['total'], (int) $r['rep_boys'], (int) $r['rep_girls'], (int) $r['sen_boys'], (int) $r['sen_girls'], (int) $r['streams'], '']);
            }
            foreach ($d['ages'] as $age => $byCat) {
                $ageLabel = $age <= 2 ? '2 or under' : ($age >= 17 ? '17 or over' : (string) $age);
                foreach (['creche', 'kg', 'primary', 'jhs'] as $category) {
                    $boys = (int) ($byCat[$category]['male'] ?? 0);
                    $girls = (int) ($byCat[$category]['female'] ?? 0);
                    if ($boys || $girls) {
                        csv_row($out, ['Enrolment by age', $ageLabel, $category, $boys, $girls, $boys + $girls, '', '', '', '', '', 'Age in completed years at school-year start']);
                    }
                }
            }
            if (!$d['ages']) {
                csv_row($out, ['Enrolment by age', 'No records', '', 0, 0, 0, '', '', '', '', '', 'No students enrolled this year']);
            }
            foreach ($d['sen'] as $s) {
                $boys = (int) $s['boys'];
                $girls = (int) $s['girls'];
                csv_row($out, ['Learners with special needs', Admission::SEN[$s['sen_category']] ?? $s['sen_category'], '', $boys, $girls, $boys + $girls, '', '', '', '', '', '']);
            }
            if (!$d['sen']) {
                csv_row($out, ['Learners with special needs', 'None recorded', '', 0, 0, 0, '', '', '', '', '', '']);
            }
            foreach (['Teaching staff' => $d['staff']['teaching'], 'Non-teaching staff' => $d['staff']['non_teaching']] as $label => $staff) {
                csv_row($out, ['Staff', $label, '', (int) ($staff['male'] ?? 0), (int) ($staff['female'] ?? 0), (int) ($staff['total'] ?? 0), '', '', '', '', '', 'Gender not recorded: ' . (int) ($staff['unknown'] ?? 0)]);
                if ($label === 'Teaching staff') {
                    csv_row($out, ['Staff qualifications', 'Qualification summary', '', '', '', (int) ($staff['total'] ?? 0), '', '', '', '', '', 'Trained: ' . (int) ($staff['trained'] ?? 0) . '; untrained: ' . (int) ($staff['untrained'] ?? 0) . '; qualification not recorded: ' . (int) ($staff['qual_unknown'] ?? 0)]);
                    csv_row($out, ['NTC licence status', 'Licence summary', '', '', '', (int) ($staff['total'] ?? 0), '', '', '', '', '', 'Valid: ' . (int) ($staff['ntc_valid'] ?? 0) . '; expiring: ' . (int) ($staff['ntc_expiring'] ?? 0) . '; expired: ' . (int) ($staff['ntc_expired'] ?? 0) . '; missing: ' . (int) ($staff['ntc_missing'] ?? 0)]);
                }
            }
            audit('analytics_exported', 'terms', (int) ($term['id'] ?? 0), ['tab' => $tab]);
            csv_end($out);
            return;
        }
        $out = csv_start($filename, $d['cols'] ?? []);
        foreach ($d['rows'] ?? [] as $r) {
            csv_row($out, ($d['csv'])($r));
        }
        audit('analytics_exported', 'terms', (int) ($term['id'] ?? 0), ['tab' => $tab]);
        csv_end($out);
    }
}
