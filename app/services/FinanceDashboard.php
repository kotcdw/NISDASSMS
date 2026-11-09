<?php
declare(strict_types=1);

/** Figures for Finance → Overview → Finance dashboard, for one term and (optionally) one money account. */
final class FinanceDashboard
{
    public static function data(?array $term, int $accountId = 0): array
    {
        $today = date('Y-m-d');
        $from = $term['start_date'] ?? date('Y-m-d', strtotime('-90 days'));
        $to = min($today, $term['end_date'] ?? $today);
        $tid = (int) ($term['id'] ?? 0);
        $d = ['from' => $from, 'to' => $to, 'account' => $accountId];

        // Money held: every active account, or the one chosen
        $accounts = [];
        foreach (Finance::accounts(true, true) as $a) {
            $accounts[] = ['id' => (int) $a['id'], 'name' => $a['name'], 'kind' => $a['kind'], 'balance' => Finance::balance((int) $a['id'])];
        }
        $d['accounts'] = $accounts;
        $shown = $accountId ? array_values(array_filter($accounts, fn($a) => $a['id'] === $accountId)) : $accounts;
        $d['held'] = array_sum(array_column($shown, 'balance'));
        usort($shown, fn($a, $b) => $b['balance'] <=> $a['balance']);
        $d['heldTop'] = array_slice($shown, 0, 2);
        $d['heldCount'] = count($shown);

        // Fees this term
        $d['fees'] = $tid && can('fees.view') ? db()->one(
            "SELECT COUNT(*) AS bills, COALESCE(SUM(total), 0) AS billed, COALESCE(SUM(paid), 0) AS paid,
                    COALESCE(SUM(GREATEST(total - paid, 0)), 0) AS owed, COALESCE(SUM(status IN ('unpaid','part')), 0) AS owing
             FROM invoices WHERE term_id = ? AND status <> 'cancelled'", [$tid]) : null;

        // Money collected each paying day this term, and by channel
        $d['daily'] = can('fees.view') ? db()->all(
            "SELECT paid_on AS day, SUM(amount) AS amt FROM payments WHERE status = 'completed' AND paid_on BETWEEN ? AND ? GROUP BY paid_on ORDER BY paid_on",
            [$from, $to]) : [];
        $d['channels'] = can('fees.view') ? db()->all(
            "SELECT method, SUM(amount) AS amt FROM payments WHERE status = 'completed' AND paid_on BETWEEN ? AND ? GROUP BY method ORDER BY amt DESC",
            [$from, $to]) : [];

        // Who owes the most (this term's bills)
        $d['owes'] = $tid && can('fees.view') ? db()->all(
            "SELECT p.id, p.first_name, p.last_name, c.name AS class_name, SUM(i.total - i.paid) AS owed, MIN(i.due_date) AS due
             FROM invoices i JOIN pupils p ON p.id = i.pupil_id LEFT JOIN classes c ON c.id = i.class_id
             WHERE i.term_id = ? AND i.status IN ('unpaid','part') GROUP BY p.id, p.first_name, p.last_name, c.name
             ORDER BY owed DESC, due LIMIT 5", [$tid]) : [];

        // Income and spending by month across the term
        $acc = $accountId ? ' AND account_id = ' . $accountId : '';
        $d['months'] = db()->all(
            "SELECT DATE_FORMAT(txn_date, '%Y-%m') AS ym,
                    COALESCE(SUM(CASE WHEN kind = 'income' THEN amount END), 0) AS income,
                    COALESCE(SUM(CASE WHEN kind IN ('expense','refund') THEN amount END), 0) AS spent
             FROM fin_transactions WHERE status = 'posted' AND txn_date BETWEEN ? AND ?$acc GROUP BY ym ORDER BY ym",
            [$from, $to]);

        // Budget used this academic year
        $year = $tid ? db()->one('SELECT y.* FROM academic_years y JOIN terms t ON t.academic_year_id = y.id WHERE t.id = ?', [$tid]) : current_year();
        $d['budget'] = $year ? (float) db()->value('SELECT COALESCE(SUM(amount), 0) FROM fin_budgets WHERE academic_year_id = ?', [$year['id']]) : 0.0;
        $d['spent'] = $year ? (float) db()->value(
            "SELECT COALESCE(SUM(amount), 0) FROM fin_transactions WHERE status = 'posted' AND kind = 'expense' AND txn_date BETWEEN ? AND ?",
            [$year['start_date'], min($today, $year['end_date'])]) : 0.0;

        // Recent large transactions
        $d['large_from'] = max(0, (int) setting('finance_large_amount', '1000'));
        $d['large'] = db()->all(
            "SELECT t.id, t.txn_date, t.txn_no, t.kind, t.amount, t.description, t.cleared_on, c.name AS category, a.name AS account
             FROM fin_transactions t LEFT JOIN fin_categories c ON c.id = t.category_id LEFT JOIN fin_accounts a ON a.id = t.account_id
             WHERE t.status = 'posted' AND t.amount >= ?" . ($accountId ? ' AND (t.account_id = ? OR t.to_account_id = ?)' : '') . '
             ORDER BY t.txn_date DESC, t.id DESC LIMIT 8',
            $accountId ? [$d['large_from'], $accountId, $accountId] : [$d['large_from']]);
        return $d;
    }

    /** A smooth daily line with a soft fill, as SVG paths (viewBox 320 × 110). */
    public static function sparkline(array $values, int $w = 320, int $h = 110): array
    {
        $n = count($values);
        if ($n < 2) {
            return ['', ''];
        }
        $max = max($values) * 1.12 ?: 1;
        $pts = [];
        foreach (array_values($values) as $i => $v) {
            $pts[] = [round(6 + $i * ($w - 12) / ($n - 1), 1), round($h - 6 - $v / $max * ($h - 12), 1)];
        }
        $line = '';
        foreach ($pts as $i => [$x, $y]) {
            $line .= ($i ? ' L' : 'M') . $x . ' ' . $y;
        }
        $area = $line . ' L' . $pts[$n - 1][0] . ' ' . $h . ' L' . $pts[0][0] . ' ' . $h . ' Z';
        return [$line, $area];
    }

    /** A round axis maximum for bar charts (e.g. 128,000 → 140,000). */
    public static function niceMax(float $v): float
    {
        if ($v <= 0) {
            return 1000.0;
        }
        $mag = 10 ** floor(log10($v));
        foreach ([1, 1.2, 1.5, 2, 2.5, 3, 4, 5, 6, 8, 10] as $m) {
            if ($m * $mag >= $v) {
                return $m * $mag;
            }
        }
        return 10 * $mag;
    }
}
