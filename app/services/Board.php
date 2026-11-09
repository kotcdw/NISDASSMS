<?php
declare(strict_types=1);

/**
 * Board-level figures for the Analytic Dashboard (cash and runway, spending against budget, NTC licences),
 * worked out from the school's own records.
 */
final class Board
{

    /** Cash + bank + MoMo, and how many months it lasts at the average spending of the last 3 full months. */
    public static function liquidity(): array
    {
        $total = 0.0;
        foreach (Finance::accounts() as $a) {
            $total += Finance::balance((int) $a['id']);
        }
        $from = date('Y-m-01', strtotime('first day of -3 months'));
        $to = date('Y-m-t', strtotime('last day of previous month'));
        $spent = (float) db()->value("SELECT COALESCE(SUM(amount), 0) FROM fin_transactions WHERE kind = 'expense' AND status = 'posted' AND txn_date BETWEEN ? AND ?", [$from, $to]);
        $months = 3.0;
        $basis = 'average spending of the last 3 months';
        if ($spent <= 0) { // not enough history yet: use this month so far
            $spent = (float) db()->value("SELECT COALESCE(SUM(amount), 0) FROM fin_transactions WHERE kind = 'expense' AND status = 'posted' AND txn_date >= ?", [date('Y-m-01')]);
            $months = max(1 / 30, (int) date('j') / (int) date('t'));
            $basis = 'spending so far this month';
        }
        $monthly = $spent / $months;
        return ['total' => $total, 'monthly' => $monthly, 'runway' => $monthly > 0 ? round($total / $monthly, 1) : null, 'basis' => $basis];
    }

    /** Teaching staff holding a current NTC licence (expiring soon still counts as active). */
    public static function ntc(): array
    {
        $teach = db()->all("SELECT * FROM staff WHERE is_active = 1 AND category = 'teaching'");
        $ok = count(array_filter($teach, fn($s) => in_array(Payroll::ntcStatus($s), ['valid', 'expiring'], true)));
        $soon = count(array_filter($teach, fn($s) => Payroll::ntcStatus($s) === 'expiring'));
        return ['total' => count($teach), 'ok' => $ok, 'expiring' => $soon, 'pct' => $teach ? round($ok * 100 / count($teach), 1) : null];
    }

    /**
     * Spending budget (Finance → Budget, spread evenly over the months of the academic year) against actual spending,
     * cumulative month by month. The variance compares spending with the budget for the time that has passed.
     */
    public static function budget(array $year): array
    {
        $annual = (float) db()->value("SELECT COALESCE(SUM(b.amount), 0) FROM fin_budgets b JOIN fin_categories c ON c.id = b.category_id WHERE b.academic_year_id = ? AND c.kind = 'expense'", [$year['id']]);
        $start = strtotime(date('Y-m-01', strtotime($year['start_date'])));
        $end = strtotime(date('Y-m-01', strtotime($year['end_date'])));
        $months = [];
        for ($t = $start; $t <= $end && count($months) < 14; $t = strtotime('+1 month', $t)) {
            $months[] = $t;
        }
        $n = max(1, count($months));
        $spent = array_column(db()->all(
            "SELECT DATE_FORMAT(txn_date, '%Y-%m') AS m, SUM(amount) AS a FROM fin_transactions WHERE kind = 'expense' AND status = 'posted' AND txn_date BETWEEN ? AND ? GROUP BY m",
            [$year['start_date'], $year['end_date']]
        ), 'a', 'm');
        $labels = $budget = $actual = $var = [];
        $run = 0.0;
        $today = time();
        foreach ($months as $i => $t) {
            $labels[] = date('M', $t);
            $budget[] = $annual > 0 ? round($annual * ($i + 1) / $n, 2) : null;
            if ($t > $today) {
                $actual[] = null;
                $var[] = null;
                continue;
            }
            $run += (float) ($spent[date('Y-m', $t)] ?? 0);
            $actual[] = round($run, 2);
            $share = min(1.0, ($today - $t) / max(1, strtotime('+1 month', $t) - $t)); // part of this month that has passed
            $b = $annual > 0 ? $annual * ($i + $share) / $n : 0;
            $var[] = $b > 0 ? round(($run - $b) * 100 / $b, 1) : null;
        }
        $last = array_key_last(array_filter($actual, fn($v) => $v !== null));
        return ['labels' => $labels, 'budget' => $budget, 'actual' => $actual, 'variance' => $var, 'annual' => $annual, 'last' => $last,
            'ytd' => $last === null ? 0.0 : (float) $actual[$last], 'ytdVar' => $last === null ? null : $var[$last]];
    }

}
