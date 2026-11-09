<?php
declare(strict_types=1);

/**
 * Analytic Dashboard (one dashboard for every staff role). Six headline figures, six panels and a "Today" strip,
 * each tied to a module: a card is shown only when the person holds its permission and the module is switched on
 * (School setup → Modules on/off). Teachers who only see their own classes get their classes' figures.
 * Cards refresh in place every 60 seconds (DashboardController@live). School-wide figures are cached for
 * 60 seconds and shared, so many people refreshing at once stays cheap.
 */
final class Analytics
{
    public const KPIS = ['k_roll', 'k_att', 'k_fees', 'k_cash', 'k_avg', 'k_alerts'];
    public const PANELS = ['p_att', 'p_fees', 'p_acad', 'p_adm', 'p_budget', 'p_comp'];
    public const PASS_MARK = 50;
    private const TTL = 60;

    /** Cards this person may see, in display order (Today strip last). */
    public static function cards(): array
    {
        $staff = can('staff');
        $ok = [
            'k_roll'   => can('pupils.view') || can('analytics.view'),
            'k_att'    => can('attendance.view') && module_on('attendance'),
            'k_fees'   => can('fees.view') && module_on('fees'),
            'k_cash'   => can('finance.view') && module_on('finance'),
            'k_avg'    => can('reports.view') && module_on('exams'),
            'k_alerts' => $staff,
            'p_att'    => can('attendance.view') && module_on('attendance'),
            'p_fees'   => can('fees.view') && module_on('fees'),
            'p_acad'   => can('reports.view') && module_on('exams'),
            'p_adm'    => can('admission.view') && module_on('admission'),
            'p_budget' => can('finance.view') && module_on('finance'),
            'p_comp'   => (bool) self::compRows(),
            'today'    => $staff,
        ];
        return array_keys(array_filter($ok));
    }

    /** Rendered HTML of each card the person may see (the live refresh sends exactly this). */
    public static function render(?array $term, string $tab = 'today'): array
    {
        $out = [];
        foreach ($tab === 'growth' ? self::growthCards() : self::cards() as $k) {
            try {
                $d = self::{$k}($term);
            } catch (PDOException $e) {
                $d = ['empty' => 'Not available yet — click "Update database now".'];
            }
            $out[$k] = self::view($k, $d + ['term' => $term]);
        }
        return $out;
    }

    private static function view(string $key, array $d): string
    {
        ob_start();
        extract(['d' => $d, 'key' => $key], EXTR_SKIP);
        require BASE_PATH . '/app/views/dashboard/cards/' . (str_starts_with($key, 'k_') || str_starts_with($key, 'gk_') ? '_kpi' : $key) . '.php';
        return (string) ob_get_clean();
    }

    // ------------------------------------------------------------------ cache

    /** Shared 60-second cache for school-wide figures; teachers' own-class figures get their own key. */
    private static function cached(string $name, ?array $term, callable $fn, int $ttl = self::TTL): array
    {
        $sc = Auth::classScope();
        $key = $name . '-' . (int) ($term['id'] ?? 0) . '-' . ($sc === null ? 'all' : md5(implode(',', $sc)));
        $dir = BASE_PATH . '/storage/cache';
        $file = $dir . '/ana-' . md5((string) config('db.name') . (is_demo() ? '-demo' : '') . $key) . '.json';
        if (is_file($file) && filemtime($file) > time() - $ttl) {
            $v = json_decode((string) @file_get_contents($file), true);
            if (is_array($v)) {
                return $v;
            }
        }
        $v = $fn();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($file . '.tmp', json_encode($v), LOCK_EX) && @rename($file . '.tmp', $file);
        return $v;
    }

    /** Forget cached figures (after a change someone expects to see straight away). */
    public static function flush(): void
    {
        foreach (glob(BASE_PATH . '/storage/cache/ana-*.json') ?: [] as $f) {
            @unlink($f);
        }
    }

    // ------------------------------------------------------------------ headline figures

    private static function k_roll(?array $term): array
    {
        if (!$term) {
            return ['empty' => 'Create an academic year and terms first.'];
        }
        $w = self::cached('roll', $term, fn() => Widgets::build('k_pupils', $term));
        return ['label' => Auth::classScope() !== null ? 'Students in my classes' : 'Students on roll', 'value' => $w['value'] ?? '—', 'sub' => $w['sub'] ?? '', 'trend' => $w['trend'] ?? null, 'href' => 'pupils'];
    }

    private static function k_att(?array $term): array
    {
        $r = self::cached('att-today', $term, function () use ($term) {
            [$w, $wp] = Widgets::scope('r.class_id');
            $t = db()->one("SELECT SUM(a.status IN ('present','late')) AS p, SUM(a.status = 'absent') AS ab, COUNT(a.id) AS n, COUNT(DISTINCT r.id) AS regs
                            FROM attendance_registers r JOIN attendance_records a ON a.register_id = r.id WHERE r.date = CURDATE()" . $w, $wp);
            $avg = $term ? db()->one("SELECT SUM(a.status IN ('present','late')) AS p, COUNT(a.id) AS n FROM attendance_registers r JOIN attendance_records a ON a.register_id = r.id
                                      WHERE r.date BETWEEN ? AND ?" . $w, array_merge([$term['start_date'], min(date('Y-m-d'), $term['end_date'])], $wp)) : ['p' => 0, 'n' => 0];
            [$cw, $cp] = Widgets::scope('c.id');
            $classes = (int) db()->value('SELECT COUNT(*) FROM classes c WHERE c.is_active = 1' . $cw, $cp);
            return ['p' => (int) $t['p'], 'ab' => (int) $t['ab'], 'n' => (int) $t['n'], 'regs' => (int) $t['regs'], 'classes' => $classes,
                'termPct' => (int) $avg['n'] ? round($avg['p'] * 100 / $avg['n'], 1) : null];
        });
        $today = $r['n'] ? round($r['p'] * 100 / $r['n'], 1) : null;
        $sub = $today === null ? 'No registers taken yet today' : $r['ab'] . ' absent · ' . $r['regs'] . ' of ' . $r['classes'] . ' registers taken';
        $trend = ($today !== null && $r['termPct'] !== null) ? [abs($today - $r['termPct']) < 0.05 ? 0 : ($today > $r['termPct'] ? 1 : -1), sprintf('%+.1f pts vs term (%s%%)', $today - $r['termPct'], self::num($r['termPct'])), true] : null;
        return ['label' => 'Attendance today', 'value' => $today === null ? ($r['termPct'] === null ? '—' : self::num($r['termPct']) . '%') : self::num($today) . '%',
            'sub' => $today === null && $r['termPct'] !== null ? 'Term average · no registers yet today' : $sub, 'trend' => $trend,
            'tone' => $today !== null && $today < 90 ? 'warn' : null, 'href' => 'attendance'];
    }

    private static function k_fees(?array $term): array
    {
        if (!$term) {
            return ['empty' => 'Create an academic year and terms first.'];
        }
        $f = self::cached('fees-term', $term, fn() => db()->one("SELECT COALESCE(SUM(total), 0) AS billed, COALESCE(SUM(paid), 0) AS paid FROM invoices WHERE term_id = ? AND status <> 'cancelled'", [$term['id']]));
        $pct = (float) $f['billed'] > 0 ? round((float) $f['paid'] * 100 / (float) $f['billed']) : null;
        return ['label' => 'Fees collected this term', 'value' => self::cedis($f['paid']), 'sub' => ($pct === null ? 'No bills yet' : $pct . '% of ' . self::cedis($f['billed']) . ' billed'),
            'meter' => $pct, 'tone' => $pct !== null && $pct < 50 ? 'warn' : null, 'href' => 'fees'];
    }

    private static function k_cash(?array $term): array
    {
        $l = self::cached('liquidity', null, fn() => Board::liquidity());
        $min = (float) setting('reserve_min_months', '3');
        $run = $l['runway'];
        return ['label' => 'Cash & runway', 'value' => self::cedis($l['total']),
            'sub' => $run === null ? 'Cash, bank and MoMo · no spending yet' : self::num((float) $run) . ' months of spending covered',
            'tone' => $l['total'] < 0 || ($run !== null && $min > 0 && $run < $min) ? 'bad' : null, 'href' => 'finance'];
    }

    private static function k_avg(?array $term): array
    {
        if (!$term) {
            return ['empty' => 'Create an academic year and terms first.'];
        }
        $w = self::cached('avg', $term, fn() => Widgets::build('k_average', $term));
        return ['label' => Auth::classScope() !== null ? 'My classes average' : 'School average', 'value' => $w['value'] ?? '—', 'sub' => $w['sub'] ?? '', 'href' => 'exams',
            'tone' => isset($w['value']) && (float) $w['value'] > 0 && (float) $w['value'] < self::PASS_MARK ? 'warn' : null];
    }

    private static function k_alerts(?array $term): array
    {
        $items = Attention::items();
        $n = array_sum(array_map(fn($i) => (int) $i[0], $items));
        $top = $items[0] ?? null;
        $bad = array_filter($items, fn($i) => $i[3] === 'bad');
        $more = count($items) - 1;
        return ['label' => 'Needs attention', 'value' => (string) count($items), 'sub' => $top ? (int) $top[0] . ' ' . $top[1] . ($more > 0 ? ' · and ' . $more . ' more (⚠ menu)' : '') : 'Nothing waiting for you',
            'tone' => $bad ? 'bad' : ($items ? 'warn' : 'good'), 'href' => $top ? $top[2] : null, 'alerts' => array_slice($items, 0, 4), 'count' => $n];
    }

    // ------------------------------------------------------------------ panels

    private static function p_att(?array $term): array
    {
        if (!$term) {
            return ['empty' => 'Create an academic year and terms first.'];
        }
        return self::cached('p-att', $term, function () use ($term) {
            [$w, $wp] = Widgets::scope('r.class_id');
            $pts = [];
            foreach (array_reverse(db()->all(
                "SELECT r.date, SUM(a.status IN ('present','late')) AS p, COUNT(a.id) AS n FROM attendance_registers r JOIN attendance_records a ON a.register_id = r.id
                 WHERE r.date <= ?" . $w . " GROUP BY r.date ORDER BY r.date DESC LIMIT 15",
                array_merge([min(date('Y-m-d'), $term['end_date'])], $wp)
            )) as $d) {
                $rate = (int) $d['n'] ? round($d['p'] * 100 / $d['n'], 1) : null;
                $pts[] = [date('j M', strtotime($d['date'])), $rate, date('D j M', strtotime($d['date'])) . ': ' . $rate . '% present'];
            }
            $vals = array_filter(array_column($pts, 1), fn($v) => $v !== null);
            $min = $vals ? max(0, min(80, (int) floor(min($vals) / 10) * 10 - 10)) : 0;
            $risk = Widgets::own(Widgets::report('attendance', $term)['chronic'], 'class_name');
            return [
                'chart' => Charts::line($pts, ['max' => 100, 'min' => $min, 'target' => 90, 'format' => fn($v) => $v . '%', 'label' => 'Attendance, last ' . count($pts) . ' school days', 'height' => 130, 'width' => 560, 'labelEvery' => 3]),
                'days' => count($pts), 'latest' => $vals ? end($vals) : null,
                'risk' => array_map(fn($p) => ['id' => (int) $p['id'], 'name' => $p['first_name'] . ' ' . $p['last_name'], 'class' => $p['class_name'], 'rate' => $p['rate']], array_slice($risk, 0, 5)),
                'riskCount' => count($risk),
            ];
        });
    }

    private static function p_fees(?array $term): array
    {
        if (!$term) {
            return ['empty' => 'Create an academic year and terms first.'];
        }
        return self::cached('p-fees', $term, function () use ($term) {
            $rep = Widgets::report('fees', $term);
            $rows = array_values(array_filter($rep['rows'], fn($r) => $r['pct'] !== null));
            $d = db()->one("SELECT COUNT(*) AS n, COALESCE(SUM(total - paid), 0) AS owed, SUM(due_date < CURDATE()) AS overdue FROM invoices WHERE term_id = ? AND status IN ('unpaid','part')", [$term['id']]);
            return [
                'rows' => array_map(fn($r) => ['name' => $r['name'], 'pct' => round((float) $r['pct']), 'owed' => (float) $r['outstanding']], $rows),
                'debtors' => (int) $d['n'], 'owed' => (float) $d['owed'], 'overdue' => (int) $d['overdue'], 'arrears' => (float) $rep['arrears'],
            ];
        });
    }

    private static function p_acad(?array $term): array
    {
        if (!$term) {
            return ['empty' => 'Create an academic year and terms first.'];
        }
        return self::cached('p-acad', $term, function () use ($term) {
            $rep = Widgets::report('academic', $term);
            $rows = array_values(array_filter(Widgets::own($rep['rows']), fn($r) => $r['average'] !== null));
            $subj = Auth::classScope() === null ? array_values(array_filter($rep['subjects'], fn($s) => $s['average'] !== null)) : [];
            usort($subj, fn($a, $b) => $b['average'] <=> $a['average']);
            return [
                'rows' => array_map(fn($r) => ['name' => $r['name'], 'avg' => (float) $r['average'], 'pass' => $r['pass']], $rows),
                'best' => $subj[0] ?? null, 'weak' => count($subj) > 1 ? end($subj) : null,
            ];
        });
    }

    private static function p_adm(?array $term): array
    {
        if (!$term) {
            return ['empty' => 'Create an academic year and terms first.'];
        }
        return self::cached('p-adm', $term, function () use ($term) {
            $c = array_column(db()->all('SELECT stage, COUNT(*) AS n FROM applications WHERE academic_year_id = ? GROUP BY stage', [$term['academic_year_id']]), 'n', 'stage');
            $total = array_sum(array_map('intval', $c));
            // Funnel: each stage counts everyone who reached it (later stages included)
            $flow = Admission::FLOW;
            $rows = [];
            foreach ($flow as $i => $s) {
                $reached = 0;
                foreach (array_slice($flow, $i) as $later) {
                    $reached += (int) ($c[$later] ?? 0);
                }
                $rows[] = ['stage' => $s, 'label' => Admission::label($s), 'now' => (int) ($c[$s] ?? 0), 'reached' => $reached];
            }
            $other = $total - array_sum(array_column($rows, 'now')); // not admitted, withdrawn…
            return ['rows' => $rows, 'total' => $total, 'enrolled' => (int) ($c['enrolled'] ?? 0), 'closed' => max(0, $other),
                'conv' => $total ? round(((int) ($c['enrolled'] ?? 0)) * 100 / $total) : null];
        });
    }

    private static function p_budget(?array $term): array
    {
        $year = $term ? db()->one('SELECT * FROM academic_years WHERE id = ?', [$term['academic_year_id']]) : current_year();
        if (!$year) {
            return ['empty' => 'Create an academic year first.'];
        }
        $b = self::cached('budget-' . (int) $year['id'], null, fn() => Board::budget($year));
        return $b + ['year' => $year['name']];
    }

    /** Compliance & welfare rows this person may see: [label, value text, tone good|warn|bad, link, detail]. */
    private static function compRows(?array $term = null): array
    {
        $rows = [];
        $term = $term ?? current_term();
        if ((can('analytics.view') || can('payroll.view')) && (module_on('teachers') || module_on('payroll'))) {
            $n = self::cached('ntc', null, fn() => Board::ntc());
            $rows[] = ['NTC licences (teachers)', $n['pct'] === null ? '—' : self::num((float) $n['pct']) . '%', $n['pct'] === null ? 'warn' : ($n['pct'] >= 100 ? 'good' : ($n['pct'] >= 90 ? 'warn' : 'bad')),
                can('payroll.manage') ? 'payroll/staff' : 'teachers', $n['ok'] . ' of ' . $n['total'] . ' current' . ($n['expiring'] ? ' · ' . $n['expiring'] . ' expiring soon' : '')];
        }
        $reg = Registers::ready() ? self::cached('registers', null, fn() => Registers::attention()) : null;
        if ($reg && can('permits.view') && module_on('permits')) {
            $rows[] = ['Licences & permits', $reg['permits'] ? $reg['permits'] . ' to renew' : 'Up to date', $reg['permits'] ? 'bad' : 'good', 'permits', 'Expired or expiring within 60 days'];
        }
        if ($reg && (can('payroll.manage') || can('staffleave.approve'))) {
            $rows[] = ['Staff contracts', $reg['contracts'] ? $reg['contracts'] . ' ending' : 'None ending', $reg['contracts'] ? 'warn' : 'good', can('payroll.manage') ? 'payroll/staff' : 'staff-leave', 'Contracts or probations ending soon'];
        }
        if ($term && can('welfare.manage') && module_on('welfare')) {
            $w = self::cached('welfare', $term, fn() => Welfare::summary($term));
            if (!empty($w['ready'])) {
                $rows[] = ['Welfare cases', (int) $w['open'] . ' open', $w['overdue'] ? 'warn' : 'good', 'welfare', (int) $w['overdue'] . ' follow-up(s) overdue · ' . (int) $w['incidents'] . ' incident(s) this term'];
                if (can('welfare.safeguarding')) {
                    $rows[] = ['Safeguarding', (int) $w['safeguarding'] . ' open', $w['safeguarding'] ? 'bad' : 'good', 'welfare', 'Seen only by the safeguarding lead'];
                }
            }
        }
        if (((can('analytics.view') && can('finance.view')) || can('governance.view')) && module_on('governance')) {
            $g = self::cached('gov', $term, fn() => Governance::summary($term));
            if (!empty($g['ready'])) {
                $rows[] = ['Policy reviews', $g['pol_overdue'] ? $g['pol_overdue'] . ' overdue' : 'Up to date', $g['pol_overdue'] ? 'bad' : ($g['pol_soon'] ? 'warn' : 'good'), 'governance', $g['pol_ok'] . ' of ' . $g['pol_total'] . ' current · ' . $g['pol_soon'] . ' due in 30 days'];
                $rows[] = ['Risk register', $g['risk_open'] . ' open', $g['risk_overdue'] || $g['risk_high'] ? ($g['risk_high'] ? 'bad' : 'warn') : 'good', 'governance', $g['risk_high'] . ' high · ' . $g['risk_overdue'] . ' review(s) overdue'];
            }
        }
        if ($reg && can('setup.manage')) {
            $rows[] = ['Data requests (Act 843)', $reg['requests'] ? $reg['requests'] . ' due' : 'None due', $reg['requests'] ? 'bad' : 'good', 'setup/privacy/requests', 'Due within 3 days or overdue'];
        }
        return array_values(array_filter($rows, fn($r) => ModuleSwitch::blocked($r[3]) === null));
    }

    private static function p_comp(?array $term): array
    {
        return ['rows' => self::compRows($term)];
    }

    /** Today strip: events, staff away, visitors on site, my lessons and registers. */
    private static function today(?array $term): array
    {
        $out = [];
        if (can('calendar.view') && module_on('calendar')) {
            $ev = db()->all('SELECT id, title, start_time FROM calendar_events WHERE start_date <= CURDATE() AND end_date >= CURDATE() ORDER BY start_time IS NULL, start_time, title LIMIT 4');
            $out['events'] = array_map(fn($e) => ['text' => ($e['start_time'] ? substr($e['start_time'], 0, 5) . ' ' : '') . $e['title'], 'href' => 'calendar/' . (int) $e['id']], $ev);
        }
        if (Registers::ready() && (can('movement.view') || can('staffleave.approve'))) {
            $leave = Registers::leaveMap(date('Y-m-d'), date('Y-m-d'));
            $out['away'] = ['leave' => count($leave), 'out' => count(Registers::outNow())];
        }
        if (Registers::ready() && can('visitors.record') && module_on('visitors')) {
            $out['visitors'] = (int) db()->value('SELECT COUNT(*) FROM visitors WHERE time_out IS NULL AND DATE(time_in) = CURDATE()');
        }
        if (module_on('timetable') && (int) date('N') <= 5) {
            $out['lessons'] = array_map(fn($l) => substr($l['start_time'], 0, 5) . ' ' . ($l['subject_name'] ?? '') . ' · ' . $l['class_name'], db()->all(
                'SELECT p.start_time, c.name AS class_name, s.name AS subject_name FROM timetable_slots ts JOIN timetable_periods p ON p.id = ts.period_id JOIN classes c ON c.id = ts.class_id
                 LEFT JOIN class_subjects cs ON cs.id = ts.class_subject_id LEFT JOIN subjects s ON s.id = cs.subject_id WHERE ts.teacher_id = ? AND ts.day = ? ORDER BY p.start_time LIMIT 8',
                [Auth::id(), (int) date('N')]
            ));
        }
        if (can('attendance.take') && module_on('attendance') && !Attendance::blockedReason(date('Y-m-d'))) {
            $out['registers'] = db()->all('SELECT c.id, c.name, (SELECT COUNT(*) FROM attendance_registers r WHERE r.class_id = c.id AND r.date = CURDATE()) AS taken FROM classes c WHERE c.class_teacher_id = ? AND c.is_active = 1 ORDER BY c.name', [Auth::id()]);
        }
        return $out;
    }

    // ------------------------------------------------------------------ formatting

    public static function cedis(float|string|null $v): string
    {
        return 'GH₵ ' . number_format((float) $v, 0);
    }

    public static function num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 1), '0'), '.');
    }

    // ================================================================== School growth tab
    // Five headline growth figures and one card per module (SIS, Financial, Online Admission, Subject Manager),
    // over the last five academic years. History changes rarely, so it is cached for 10 minutes;
    // this year's figures follow the normal 60-second cache.

    public const GROWTH_KPIS = ['gk_enrol', 'gk_apps', 'gk_income', 'gk_collect', 'gk_cover'];
    public const GROWTH_PANELS = ['g_sis', 'g_money', 'g_adm', 'g_subj'];
    private const HISTORY_TTL = 600;

    /** Growth cards this person may see. School-wide only: teachers limited to their own classes do not get the tab. */
    public static function growthCards(): array
    {
        if (Auth::classScope() !== null || !can('staff')) {
            return [];
        }
        $sis = can('pupils.view') || can('analytics.view');
        $fees = can('fees.view') && module_on('fees');
        $money = $fees || (can('finance.view') && module_on('finance'));
        $adm = can('admission.view') && module_on('admission');
        $subj = (can('scheme.plan') || can('curriculum.manage') || can('exams.manage')) && module_on('curriculum');
        $ok = [
            'gk_enrol' => $sis, 'gk_apps' => $adm, 'gk_income' => $fees, 'gk_collect' => $fees, 'gk_cover' => $subj,
            'g_sis' => $sis, 'g_money' => $money, 'g_adm' => $adm, 'g_subj' => $subj,
        ];
        return array_keys(array_filter($ok));
    }

    /** The academic year of the chosen term and up to four years before it (oldest first). */
    private static function years(?array $term): array
    {
        if (!$term) {
            return [];
        }
        return self::cached('g-years', $term, function () use ($term) {
            $cur = db()->one('SELECT * FROM academic_years WHERE id = ?', [$term['academic_year_id']]);
            if (!$cur) {
                return [];
            }
            return array_reverse(db()->all('SELECT id, name, start_date, end_date FROM academic_years WHERE start_date <= ? ORDER BY start_date DESC LIMIT 5', [$cur['start_date']]));
        }, self::HISTORY_TTL);
    }

    private static function pct(float $a, float $b): ?float
    {
        return $b > 0 ? round($a * 100 / $b, 1) : null;
    }

    private static function growth(?float $now, ?float $before): ?float
    {
        return $before !== null && $before > 0 && $now !== null ? round(($now - $before) * 100 / $before, 1) : null;
    }

    /** A figure box that links to its page when this person may open it, otherwise plain (never a dead link). */
    public static function box(string $route, string $inner, string $class = '', string $tag = 'div'): string
    {
        $cls = $class !== '' ? ' class="' . e($class) . '"' : '';
        return can_open($route) ? '<a href="' . e(url($route)) . '"' . $cls . '>' . $inner . '</a>' : '<' . $tag . $cls . '>' . $inner . '</' . $tag . '>';
    }

    /** Opening / closing tag of a figure box: a link when this person may open the page, otherwise a plain box. */
    public static function open(string $route, array $q = [], string $class = ''): string
    {
        $cls = $class !== '' ? ' class="' . e($class) . '"' : '';
        return can_open(url($route, $q)) ? '<a href="' . e(url($route, $q)) . '"' . $cls . '>' : '<div' . $cls . '>';
    }

    public static function close(string $route, array $q = []): string
    {
        return can_open(url($route, $q)) ? '</a>' : '</div>';
    }

    public static function signed(?float $v, string $unit = '%'): string
    {
        return $v === null ? '—' : ($v > 0 ? '+' : ($v < 0 ? '−' : '')) . self::num(abs($v)) . $unit;
    }

    /** Students enrolled in each year, new students, leavers and enrolment by level (this year against last). */
    private static function sisData(?array $term): array
    {
        $years = self::years($term);
        if (!$years) {
            return ['empty' => 'Create an academic year and terms first.'];
        }
        $cur = end($years);
        return self::cached('g-sis', $term, function () use ($years, $cur) {
            $ids = array_column($years, 'id');
            $in = implode(',', array_map('intval', $ids));
            $counts = array_column(db()->all("SELECT e.academic_year_id AS y, COUNT(DISTINCT e.pupil_id) AS n FROM enrolments e JOIN pupils p ON p.id = e.pupil_id
                WHERE p.deleted_at IS NULL AND e.academic_year_id IN ($in) GROUP BY e.academic_year_id"), 'n', 'y');
            $rows = array_map(fn($y) => ['id' => (int) $y['id'], 'name' => $y['name'], 'n' => (int) ($counts[$y['id']] ?? 0)], $years);
            $prev = count($years) > 1 ? $years[count($years) - 2] : null;
            $new = (int) db()->value('SELECT COUNT(DISTINCT e.pupil_id) FROM enrolments e JOIN pupils p ON p.id = e.pupil_id
                WHERE p.deleted_at IS NULL AND e.academic_year_id = ? AND NOT EXISTS (SELECT 1 FROM enrolments e2 JOIN academic_years y2 ON y2.id = e2.academic_year_id
                WHERE e2.pupil_id = e.pupil_id AND y2.start_date < ?)', [$cur['id'], $cur['start_date']]);
            $left = (int) db()->value("SELECT COUNT(*) FROM pupils WHERE deleted_at IS NULL AND status IN ('withdrawn','transferred','graduated') AND left_on BETWEEN ? AND ?",
                [$cur['start_date'], $cur['end_date']]);
            $lv = [];
            foreach (db()->all('SELECT l.id, l.name, e.academic_year_id AS y, COUNT(DISTINCT e.pupil_id) AS n FROM levels l
                LEFT JOIN classes c ON c.level_id = l.id LEFT JOIN enrolments e ON e.class_id = c.id AND e.academic_year_id IN (?, ?)
                LEFT JOIN pupils p ON p.id = e.pupil_id AND p.deleted_at IS NULL GROUP BY l.id, e.academic_year_id ORDER BY l.sort_order', [$cur['id'], $prev['id'] ?? 0]) as $r) {
                $lv[(int) $r['id']] ??= ['name' => $r['name'], 'now' => 0, 'before' => 0];
                if ((int) $r['y'] === (int) $cur['id']) {
                    $lv[(int) $r['id']]['now'] = (int) $r['n'];
                } elseif ($prev && (int) $r['y'] === (int) $prev['id']) {
                    $lv[(int) $r['id']]['before'] = (int) $r['n'];
                }
            }
            $now = (float) end($rows)['n'];
            $before = $prev ? (float) $rows[count($rows) - 2]['n'] : null;
            return ['rows' => $rows, 'year' => $cur['name'], 'prevYear' => $prev['name'] ?? null, 'now' => (int) $now, 'before' => $before === null ? null : (int) $before,
                'growth' => self::growth($now, $before), 'new' => $new, 'left' => $left, 'levels' => array_values(array_filter($lv, fn($l) => $l['now'] || $l['before'])), 'hasPrev' => $prev !== null];
        }, self::TTL);
    }

    /** Fees billed and collected per year, income against spending per year, this year term by term, income per student. */
    private static function moneyData(?array $term): array
    {
        $years = self::years($term);
        if (!$years) {
            return ['empty' => 'Create an academic year and terms first.'];
        }
        $cur = end($years);
        $fees = can('fees.view') && module_on('fees');
        $cash = can('finance.view') && module_on('finance');
        return self::cached('g-money-' . ($fees ? 'f' : '') . ($cash ? 'c' : ''), $term, function () use ($years, $cur, $fees, $cash) {
            $out = ['year' => $cur['name'], 'fees' => $fees, 'cash' => $cash];
            $in = implode(',', array_map('intval', array_column($years, 'id')));
            if ($fees) {
                $by = [];
                foreach (db()->all("SELECT t.academic_year_id AS y, COALESCE(SUM(i.total), 0) AS billed, COALESCE(SUM(i.paid), 0) AS paid FROM invoices i JOIN terms t ON t.id = i.term_id
                    WHERE i.status <> 'cancelled' AND t.academic_year_id IN ($in) GROUP BY t.academic_year_id") as $r) {
                    $by[(int) $r['y']] = $r;
                }
                $out['feeYears'] = array_map(fn($y) => ['name' => $y['name'], 'billed' => (float) ($by[$y['id']]['billed'] ?? 0), 'paid' => (float) ($by[$y['id']]['paid'] ?? 0),
                    'rate' => self::pct((float) ($by[$y['id']]['paid'] ?? 0), (float) ($by[$y['id']]['billed'] ?? 0))], $years);
                $out['terms'] = array_map(fn($t) => $t + ['rate' => self::pct((float) $t['paid'], (float) $t['billed'])], db()->all(
                    "SELECT t.id, t.name, COALESCE(SUM(i.total), 0) AS billed, COALESCE(SUM(i.paid), 0) AS paid FROM terms t
                     LEFT JOIN invoices i ON i.term_id = t.id AND i.status <> 'cancelled' WHERE t.academic_year_id = ? GROUP BY t.id ORDER BY t.start_date",
                    [$cur['id']]
                ));
                // Fee income so far this year against the same point last year (receipts, not bills)
                $prev = count($years) > 1 ? $years[count($years) - 2] : null;
                $isCurrent = date('Y-m-d') <= $cur['end_date'];
                $upto = $isCurrent ? min(date('Y-m-d'), $cur['end_date']) : $cur['end_date'];
                $sum = fn(string $a, string $b) => (float) db()->value("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed' AND paid_on BETWEEN ? AND ?", [$a, $b]);
                $out['ytd'] = $sum($cur['start_date'], $upto);
                $out['ytdPrev'] = null;
                if ($prev) {
                    $days = (int) round((strtotime($upto) - strtotime($cur['start_date'])) / 86400);
                    $out['ytdPrev'] = $sum($prev['start_date'], min($prev['end_date'], date('Y-m-d', strtotime($prev['start_date'] . " +$days days"))));
                }
                $out['ytdGrowth'] = $out['ytdPrev'] ? self::growth($out['ytd'], $out['ytdPrev']) : null;
                $out['sameDate'] = $isCurrent;
                $students = (int) db()->value('SELECT COUNT(DISTINCT e.pupil_id) FROM enrolments e JOIN pupils p ON p.id = e.pupil_id WHERE p.deleted_at IS NULL AND e.academic_year_id = ?', [$cur['id']]);
                $last = end($out['feeYears']);
                $out['perStudent'] = $students ? $last['paid'] / $students : null;
                $out['rate'] = $last['rate'];
                $out['billed'] = $last['billed'];
                $out['paid'] = $last['paid'];
            }
            if ($cash) {
                $out['cashYears'] = array_map(function ($y) {
                    $r = db()->one("SELECT COALESCE(SUM(CASE WHEN kind = 'income' THEN amount WHEN kind = 'refund' THEN -amount ELSE 0 END), 0) AS income,
                                           COALESCE(SUM(CASE WHEN kind = 'expense' THEN amount ELSE 0 END), 0) AS spent
                                    FROM fin_transactions WHERE status = 'posted' AND txn_date BETWEEN ? AND ?", [$y['start_date'], $y['end_date']]);
                    return ['name' => $y['name'], 'income' => (float) $r['income'], 'spent' => (float) $r['spent']];
                }, $years);
            }
            return $out;
        }, self::TTL);
    }

    /** Applications per year, how many became students, where they came from, and this year against last year at the same date. */
    private static function admData(?array $term): array
    {
        $years = self::years($term);
        if (!$years) {
            return ['empty' => 'Create an academic year and terms first.'];
        }
        $cur = end($years);
        return self::cached('g-adm', $term, function () use ($years, $cur) {
            $in = implode(',', array_map('intval', array_column($years, 'id')));
            $by = [];
            foreach (db()->all("SELECT academic_year_id AS y, COUNT(*) AS n, SUM(stage = 'enrolled' OR pupil_id IS NOT NULL) AS enrolled FROM applications WHERE academic_year_id IN ($in) GROUP BY academic_year_id") as $r) {
                $by[(int) $r['y']] = $r;
            }
            $rows = array_map(fn($y) => ['name' => $y['name'], 'n' => (int) ($by[$y['id']]['n'] ?? 0), 'enrolled' => (int) ($by[$y['id']]['enrolled'] ?? 0),
                'conv' => self::pct((float) ($by[$y['id']]['enrolled'] ?? 0), (float) ($by[$y['id']]['n'] ?? 0))], $years);
            $src = array_column(db()->all('SELECT source, COUNT(*) AS n FROM applications WHERE academic_year_id = ? GROUP BY source', [$cur['id']]), 'n', 'source');
            $prev = count($years) > 1 ? $years[count($years) - 2] : null;
            // Same date last year: applications for last year's intake received up to one year before today
            $isCurrent = date('Y-m-d') <= $cur['end_date'];
            $now = (int) end($rows)['n'];
            $before = $prev ? (int) db()->value('SELECT COUNT(*) FROM applications WHERE academic_year_id = ?' . ($isCurrent ? ' AND created_at <= ?' : ''),
                $isCurrent ? [$prev['id'], date('Y-m-d H:i:s', strtotime('-1 year'))] : [$prev['id']]) : null;
            return ['rows' => $rows, 'year' => $cur['name'], 'now' => $now, 'before' => $before, 'sameDate' => $isCurrent, 'growth' => self::growth((float) $now, $before === null ? null : (float) $before),
                'online' => (int) ($src['online'] ?? 0), 'walkin' => (int) ($src['walk_in'] ?? 0), 'conv' => end($rows)['conv'], 'enrolled' => end($rows)['enrolled']];
        }, self::TTL);
    }

    /** Scheme-of-work coverage, lesson notes on time and vetted, subjects with a teacher, and the school average term by term. */
    private static function subjData(?array $term): array
    {
        if (!$term) {
            return ['empty' => 'Create an academic year and terms first.'];
        }
        $years = self::years($term);
        $live = self::cached('g-subj', $term, function () use ($term) {
            $week = Curriculum::currentWeek($term);
            $weeks = $week ?? (date('Y-m-d') > $term['end_date'] ? Curriculum::weeks($term) : 0);
            $cov = db()->one('SELECT COUNT(*) AS planned, SUM(taught_on IS NOT NULL) AS taught FROM scheme_items WHERE term_id = ? AND week_no <= ?', [$term['id'], max(0, $weeks)]);
            $cs = db()->one('SELECT COUNT(*) AS n, SUM(cs.teacher_id IS NOT NULL) AS staffed FROM class_subjects cs JOIN classes c ON c.id = cs.class_id WHERE c.is_active = 1');
            $past = $week !== null ? $week - 1 : $weeks; // weeks whose notes are due by now
            $notes = ['expected' => 0, 'submitted' => 0, 'ontime' => 0, 'vetted' => 0];
            if (LessonNotes::ready() && $past > 0) {
                $notes['expected'] = (int) $cs['n'] * $past;
                $r = db()->one("SELECT SUM(status IN ('submitted','approved')) AS submitted, SUM(status = 'approved') AS vetted,
                        SUM(status IN ('submitted','approved') AND submitted_at IS NOT NULL AND DATE(submitted_at) <= DATE_ADD(?, INTERVAL week_no * 7 - 1 DAY)) AS ontime
                        FROM lesson_notes WHERE term_id = ? AND week_no <= ?", [$term['start_date'], $term['id'], $past]);
                $notes = ['expected' => $notes['expected'], 'submitted' => (int) $r['submitted'], 'ontime' => (int) $r['ontime'], 'vetted' => (int) $r['vetted']];
            }
            return ['week' => $week, 'weeks' => $weeks, 'planned' => (int) $cov['planned'], 'taught' => (int) $cov['taught'], 'coverage' => self::pct((float) $cov['taught'], (float) $cov['planned']),
                'subjects' => (int) $cs['n'], 'staffed' => (int) $cs['staffed'], 'staffedPct' => self::pct((float) $cs['staffed'], (float) $cs['n']),
                'notes' => $notes, 'notesPct' => self::pct((float) $notes['submitted'], (float) $notes['expected']), 'ontimePct' => self::pct((float) $notes['ontime'], (float) $notes['expected']),
                'vettedPct' => self::pct((float) $notes['vetted'], (float) $notes['expected'])];
        }, self::TTL);
        // School average term by term over the same years (worked out from the scores; cached 10 minutes)
        $live['trend'] = self::cached('g-avg-trend', $term, function () use ($years, $term) {
            if (!$years) {
                return [];
            }
            $ids = implode(',', array_map('intval', array_column($years, 'id')));
            $out = [];
            foreach (db()->all("SELECT t.*, y.name AS year_name FROM terms t JOIN academic_years y ON y.id = t.academic_year_id
                                WHERE t.academic_year_id IN ($ids) AND t.start_date <= ? ORDER BY t.start_date", [min(date('Y-m-d'), $term['end_date'])]) as $t) {
                $v = Widgets::build('k_average', $t)['value'] ?? '—';
                if (is_numeric(rtrim((string) $v, '%'))) {
                    $out[] = ['label' => 'T' . (int) $t['term_no'] . ' ' . substr((string) $t['year_name'], 2, 2) . '/' . substr((string) $t['year_name'], -2), 'name' => $t['year_name'] . ' · ' . $t['name'], 'avg' => (float) rtrim((string) $v, '%')];
                }
            }
            return $out;
        }, self::HISTORY_TTL);
        return $live;
    }

    // ---- growth headline figures (small cards drawn with _kpi)

    private static function gk_enrol(?array $term): array
    {
        $d = self::sisData($term);
        if (!empty($d['empty'])) {
            return ['label' => 'Enrolment growth'] + $d;
        }
        return ['label' => 'Enrolment growth', 'value' => $d['hasPrev'] ? self::signed($d['growth']) : number_format($d['now']), 'href' => 'pupils',
            'sub' => $d['hasPrev'] ? number_format($d['now']) . ' students vs ' . number_format((int) $d['before']) . ' in ' . $d['prevYear'] : 'students this year · growth shows from next year',
            'tone' => $d['growth'] !== null && $d['growth'] < 0 ? 'warn' : null];
    }

    private static function gk_apps(?array $term): array
    {
        $d = self::admData($term);
        if (!empty($d['empty'])) {
            return ['label' => 'Applications'] + $d;
        }
        return ['label' => 'Applications', 'value' => number_format($d['now']), 'href' => 'admission',
            'sub' => $d['before'] === null ? 'this year · no earlier year to compare' : 'vs ' . number_format($d['before']) . ($d['sameDate'] ? ' by this date last year' : ' last year'),
            'trend' => $d['growth'] === null ? null : [$d['growth'] <=> 0, self::signed($d['growth']), true]];
    }

    private static function gk_income(?array $term): array
    {
        $d = self::moneyData($term);
        if (!empty($d['empty']) || empty($d['fees'])) {
            return ['label' => 'Fee income growth', 'empty' => $d['empty'] ?? 'No fee records'];
        }
        return ['label' => 'Fee income growth', 'value' => $d['ytdGrowth'] === null ? self::cedis($d['ytd']) : self::signed($d['ytdGrowth']), 'href' => 'fees',
            'sub' => $d['ytdGrowth'] === null ? 'received this year · growth shows from next year' : self::cedis($d['ytd']) . ' received vs ' . self::cedis((float) $d['ytdPrev']) . ($d['sameDate'] ? ' by this date last year' : ' last year'),
            'tone' => $d['ytdGrowth'] !== null && $d['ytdGrowth'] < 0 ? 'warn' : null];
    }

    private static function gk_collect(?array $term): array
    {
        $d = self::moneyData($term);
        if (!empty($d['empty']) || empty($d['fees'])) {
            return ['label' => 'Collection rate', 'empty' => $d['empty'] ?? 'No fee records'];
        }
        if ($d['rate'] === null) {
            return ['label' => 'Collection rate', 'empty' => 'No bills yet this year', 'href' => 'fees'];
        }
        return ['label' => 'Collection rate', 'value' => self::num($d['rate']) . '%', 'meter' => $d['rate'], 'href' => 'fees/debtors',
            'sub' => self::cedis($d['paid']) . ' of ' . self::cedis($d['billed']) . ' billed in ' . $d['year'], 'tone' => $d['rate'] < 50 ? 'warn' : null];
    }

    private static function gk_cover(?array $term): array
    {
        $d = self::subjData($term);
        if (!empty($d['empty'])) {
            return ['label' => 'Curriculum coverage'] + $d;
        }
        if ($d['coverage'] === null) {
            return ['label' => 'Curriculum coverage', 'empty' => 'No scheme of work planned yet', 'href' => 'curriculum'];
        }
        return ['label' => 'Curriculum coverage', 'value' => self::num($d['coverage']) . '%', 'meter' => $d['coverage'], 'href' => 'curriculum',
            'sub' => number_format($d['taught']) . ' of ' . number_format($d['planned']) . ' planned topics taught' . ($d['week'] ? ' (to week ' . $d['week'] . ')' : ''),
            'tone' => $d['coverage'] < 60 ? 'warn' : null];
    }

    // ---- growth cards (one per module)

    private static function g_sis(?array $term): array
    {
        return self::sisData($term);
    }

    private static function g_money(?array $term): array
    {
        return self::moneyData($term);
    }

    private static function g_adm(?array $term): array
    {
        return self::admData($term);
    }

    private static function g_subj(?array $term): array
    {
        return self::subjData($term);
    }
}
