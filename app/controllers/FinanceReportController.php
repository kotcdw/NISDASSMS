<?php
declare(strict_types=1);

/** Finance reports: cash flow, income & spending (from the books), budget vs actual, debtors by age, taxes. */
class FinanceReportController extends Controller
{
    public const TABS = ['cashflow' => 'Cash flow', 'income' => 'Income & spending', 'budget' => 'Budget vs actual', 'debtors' => 'Fees owed by age', 'tax' => 'Tax (WHT, PAYE, SSNIT)'];

    /** What each report shows, in plain words (the reports menu). */
    public const ABOUT = [
        'cashflow' => 'Money that came in and went out, and the cash left at the end.',
        'income' => 'Income and spending for the period, and whether the school made a surplus.',
        'budget' => 'What was planned against what has happened so far, and each department\'s limit.',
        'debtors' => 'Fees still owed, by class and by how late they are.',
        'tax' => 'Tax kept back from suppliers, and PAYE and SSNIT from payroll.',
    ];

    public function index(): void
    {
        if (!isset(self::TABS[$_GET['tab'] ?? ''])) {
            $this->view('finance/reports_menu', ['title' => 'Finance reports']);
            return;
        }
        $tab = $_GET['tab'];
        [$from, $to] = $this->range();
        $data = ['title' => 'Finance reports', 'tab' => $tab, 'from' => $from, 'to' => $to];
        $data += match ($tab) {
            'cashflow' => $this->cashflow($from, $to),
            'income' => $this->income($from, $to),
            'budget' => $this->budget(),
            'debtors' => $this->debtors(),
            'tax' => $this->tax($from, $to),
        };
        if (isset($_GET['export'])) {
            $this->export($tab, $data);
        }
        isset($_GET['print']) ? $this->view('finance/reports_print', $data, 'layouts/print') : $this->view('finance/reports', $data);
    }

    private function range(): array
    {
        $p = (string) ($_GET['period'] ?? '');
        if ($p === 'term' && ($t = current_term())) {
            return [$t['start_date'], min($t['end_date'], date('Y-m-d'))];
        }
        if ($p === 'year' && ($y = current_year())) {
            return [$y['start_date'], min($y['end_date'], date('Y-m-d'))];
        }
        if ($p === 'last') {
            return [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('first day of last month'))];
        }
        $from = (string) ($_GET['from'] ?? '');
        $to = (string) ($_GET['to'] ?? '');
        return [Validator::isDate($from) ? $from : date('Y-m-01'), Validator::isDate($to) ? $to : date('Y-m-d')];
    }

    /** Cash flow from the cash book: money in and out of every account (petty cash included), grouped. */
    private function cashflow(string $from, string $to): array
    {
        $opening = 0.0;
        $closing = 0.0;
        $prev = date('Y-m-d', strtotime($from . ' -1 day'));
        foreach (Finance::accounts(false) as $a) {
            $opening += Finance::balance((int) $a['id'], $prev);
            $closing += Finance::balance((int) $a['id'], $to);
        }
        $groups = ['running' => [], 'assets' => []];
        foreach (db()->all(
            "SELECT t.kind, t.source, c.code, COALESCE(c.name, 'Not sorted') AS name, SUM(t.amount) AS amt FROM fin_transactions t LEFT JOIN fin_categories c ON c.id = t.category_id
             WHERE t.status = 'posted' AND t.kind <> 'transfer' AND t.txn_date BETWEEN ? AND ? GROUP BY t.kind, t.source, c.code, c.name ORDER BY t.kind, c.name",
            [$from, $to]
        ) as $r) {
            $g = ($r['code'] === 'ASSET' || $r['source'] === 'asset_disposal') ? 'assets' : 'running';
            $name = $r['source'] === 'asset_disposal' ? 'Assets sold' : ($r['source'] === 'family_advance' ? 'School fees' : $r['name']);
            $sign = in_array($r['kind'], ['income', 'refund'], true) ? 1 : -1;
            $key = ($sign > 0 && $r['kind'] === 'income' ? 'in' : 'out') . ':' . $name;
            $groups[$g][$key] = ['name' => $name, 'in' => $sign > 0 && $r['kind'] === 'income', 'amount' => round(($groups[$g][$key]['amount'] ?? 0) + $sign * (float) $r['amt'], 2)];
        }
        foreach ($groups as &$g) {
            uasort($g, fn($a, $b) => [$b['in'], abs($b['amount'])] <=> [$a['in'], abs($a['amount'])]);
        }
        unset($g);
        $net = round(array_sum(array_map(fn($g) => array_sum(array_column($g, 'amount')), $groups)), 2);
        return ['groups' => $groups, 'opening' => round($opening, 2), 'closing' => round($closing, 2), 'net' => $net];
    }

    private function income(string $from, string $to): array
    {
        if (!Ledger::ready()) {
            return ['inc' => [], 'exp' => [], 'ti' => 0.0, 'te' => 0.0, 'noLedger' => true];
        }
        Ledger::sync();
        [$inc, $exp, $ti, $te] = Ledger::incomeSpending($from, $to);
        return ['inc' => $inc, 'exp' => $exp, 'ti' => $ti, 'te' => $te];
    }

    private function budget(): array
    {
        $year = current_year();
        $rows = [];
        if ($year) {
            $budgets = array_column(db()->all('SELECT category_id, amount FROM fin_budgets WHERE academic_year_id = ?', [$year['id']]), 'amount', 'category_id');
            $actual = Finance::totalsByCategory($year['start_date'], $year['end_date']);
            $done = max(0.0, min(1.0, (strtotime(min(date('Y-m-d'), $year['end_date'])) - strtotime($year['start_date'])) / max(1, strtotime($year['end_date']) - strtotime($year['start_date']))));
            foreach (Finance::categories(null, false) as $c) {
                $b = isset($budgets[$c['id']]) ? (float) $budgets[$c['id']] : null;
                $a = $actual[(int) $c['id']] ?? 0.0;
                if ($b === null && abs($a) < 0.005) {
                    continue;
                }
                $expected = $b !== null ? round($b * $done, 2) : null;
                $var = $expected !== null ? round($a - $expected, 2) : null;
                $rows[] = $c + ['budget' => $b, 'expected' => $expected, 'actual' => $a, 'variance' => $var,
                    'flag' => $expected && abs($var) > 0.10 * $expected ? ($c['kind'] === 'expense' ? ($var > 0 ? 'over' : 'under') : ($var < 0 ? 'behind' : 'ahead')) : null];
            }
        }
        $depts = [];
        if (DeptBudget::ready() && ($t = DeptBudget::term())) {
            $depts = array_values(array_filter(DeptBudget::rows($t), fn($r) => $r['cap'] !== null || $r['spent'] > 0));
        }
        return ['year' => $year, 'rows' => $rows, 'depts' => $depts, 'termName' => isset($t) && $t ? $t['name'] : ''];
    }

    private function debtors(): array
    {
        $buckets = ['not_due' => 'Not yet due', 'd30' => '1–30 days late', 'd60' => '31–60 days late', 'd90' => '61–90 days late', 'd90p' => 'Over 90 days late'];
        $rows = db()->all(
            "SELECT COALESCE(c.name, 'No class') AS class_name, c.id AS class_id,
                    SUM(CASE WHEN i.due_date IS NULL OR i.due_date >= CURDATE() THEN i.total - i.paid ELSE 0 END) AS not_due,
                    SUM(CASE WHEN DATEDIFF(CURDATE(), i.due_date) BETWEEN 1 AND 30 THEN i.total - i.paid ELSE 0 END) AS d30,
                    SUM(CASE WHEN DATEDIFF(CURDATE(), i.due_date) BETWEEN 31 AND 60 THEN i.total - i.paid ELSE 0 END) AS d60,
                    SUM(CASE WHEN DATEDIFF(CURDATE(), i.due_date) BETWEEN 61 AND 90 THEN i.total - i.paid ELSE 0 END) AS d90,
                    SUM(CASE WHEN DATEDIFF(CURDATE(), i.due_date) > 90 THEN i.total - i.paid ELSE 0 END) AS d90p,
                    COUNT(DISTINCT i.pupil_id) AS pupils
             FROM invoices i LEFT JOIN classes c ON c.id = i.class_id LEFT JOIN levels l ON l.id = c.level_id
             WHERE i.status IN ('unpaid','part') GROUP BY c.id, c.name ORDER BY MIN(l.sort_order), c.name"
        );
        return ['buckets' => $buckets, 'rows' => $rows];
    }

    private function tax(string $from, string $to): array
    {
        $wht = ['owed' => 0.0, 'withheld' => 0.0, 'paid' => 0.0];
        if (Family::ready()) {
            $wht['owed'] = round(array_sum(array_map(fn($p) => (float) $p['wht'], Suppliers::whtOwed())), 2);
            $wht['withheld'] = (float) db()->value("SELECT COALESCE(SUM(wht), 0) FROM supplier_payments WHERE status = 'posted' AND pay_date BETWEEN ? AND ?", [$from, $to]);
            $wht['paid'] = (float) db()->value('SELECT COALESCE(SUM(amount), 0) FROM wht_remittances WHERE remit_date BETWEEN ? AND ?', [$from, $to]);
        }
        $runs = [];
        try {
            $runs = db()->all('SELECT * FROM payroll_runs WHERE period BETWEEN ? AND ? ORDER BY period', [substr($from, 0, 7), substr($to, 0, 7)]);
        } catch (PDOException $e) {
            $runs = [];
        }
        return ['wht' => $wht, 'runs' => $runs];
    }

    private function export(string $tab, array $d): void
    {
        $rows = [];
        switch ($tab) {
            case 'cashflow':
                $rows[] = ['Opening cash', $d['opening']];
                foreach (['running' => 'Day-to-day running', 'assets' => 'Buildings, furniture & equipment'] as $k => $label) {
                    $rows[] = [$label, ''];
                    foreach ($d['groups'][$k] as $r) {
                        $rows[] = ['  ' . ($r['in'] ? 'In: ' : 'Out: ') . $r['name'], $r['amount']];
                    }
                }
                $rows[] = ['Net change', $d['net']];
                $rows[] = ['Closing cash', $d['closing']];
                $head = ['Line', 'GH₵'];
                break;
            case 'income':
                $head = ['Code', 'Account', 'GH₵'];
                foreach ($d['inc'] as $a) {
                    $rows[] = [$a['code'], $a['name'], $a['amount']];
                }
                $rows[] = ['', 'Total income', $d['ti']];
                foreach ($d['exp'] as $a) {
                    $rows[] = [$a['code'], $a['name'], $a['amount']];
                }
                $rows[] = ['', 'Total spending', $d['te']];
                $rows[] = ['', 'Surplus / (deficit)', round($d['ti'] - $d['te'], 2)];
                break;
            case 'budget':
                $head = ['Category', 'Kind', 'Budget', 'Expected by now', 'Actual', 'Difference'];
                foreach ($d['rows'] as $r) {
                    $rows[] = [$r['name'], $r['kind'], $r['budget'] ?? '', $r['expected'] ?? '', $r['actual'], $r['variance'] ?? ''];
                }
                break;
            case 'debtors':
                $head = array_merge(['Class', 'Students'], array_values($d['buckets']), ['Total']);
                foreach ($d['rows'] as $r) {
                    $t = 0;
                    $line = [$r['class_name'], $r['pupils']];
                    foreach (array_keys($d['buckets']) as $b) {
                        $line[] = round((float) $r[$b], 2);
                        $t += (float) $r[$b];
                    }
                    $line[] = round($t, 2);
                    $rows[] = $line;
                }
                break;
            default:
                $head = ['Month', 'Gross pay', 'PAYE', 'SSNIT (staff)', 'SSNIT (school)', 'Status'];
                foreach ($d['runs'] as $r) {
                    $rows[] = [$r['period'], $r['gross'], $r['paye'], $r['ssnit_employee'], $r['ssnit_employer'], $r['status']];
                }
        }
        csv_download('finance-' . $tab . '-' . $d['from'] . '-' . $d['to'] . '.csv', $head, $rows, true);
    }
}
