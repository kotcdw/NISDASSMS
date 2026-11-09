<?php
declare(strict_types=1);

/**
 * Department budget caps: each department (Creche & KG, JHS, Kitchen …) gets a spending cap for the term.
 * Spent = money paid out for the department this term (cash book, requisitions, supplier bills, petty cash).
 * Committed = approved requisitions not yet paid, purchase orders not yet invoiced and supplier bills not yet paid.
 * A requisition that would take a department over what is left is stopped until the Head raises the cap or moves
 * money from another department. Departments without a cap are not limited.
 */
final class DeptBudget
{
    private static ?bool $ready = null;

    public static function ready(): bool
    {
        if (self::$ready === null) {
            try {
                db()->value('SELECT 1 FROM fin_dept_caps LIMIT 1');
                self::$ready = true;
            } catch (PDOException $e) {
                self::$ready = false;
            }
        }
        return self::$ready;
    }

    public static function term(?int $termId = null): ?array
    {
        $t = $termId ? db()->one('SELECT t.*, y.name AS year_name FROM terms t JOIN academic_years y ON y.id = t.academic_year_id WHERE t.id = ?', [$termId]) : null;
        if (!$t && ($cur = current_term())) {
            $t = db()->one('SELECT t.*, y.name AS year_name FROM terms t JOIN academic_years y ON y.id = t.academic_year_id WHERE t.id = ?', [$cur['id']]);
        }
        return $t;
    }

    /** @return array<string,float> department => cap */
    public static function caps(int $termId): array
    {
        $out = [];
        foreach (db()->all('SELECT department, amount FROM fin_dept_caps WHERE term_id = ?', [$termId]) as $r) {
            $out[$r['department']] = (float) $r['amount'];
        }
        return $out;
    }

    /** @return array<string,float> department => spent in the term (money out less money back) */
    public static function spent(array $term): array
    {
        $out = [];
        foreach (db()->all(
            "SELECT department, SUM(CASE WHEN kind = 'refund' THEN -amount ELSE amount END) AS amt FROM fin_transactions
             WHERE status = 'posted' AND kind IN ('expense','refund') AND department IS NOT NULL AND txn_date BETWEEN ? AND ? GROUP BY department",
            [$term['start_date'], $term['end_date']]
        ) as $r) {
            $out[$r['department']] = round((float) $r['amt'], 2);
        }
        return $out;
    }

    /** @return array<string,float> department => committed (promised, not yet paid) */
    public static function committed(?int $excludeReq = null): array
    {
        $out = [];
        $po = Purchasing::ready();
        foreach (db()->all(
            "SELECT department, SUM(COALESCE(approved_amount, amount)) AS amt FROM requisitions
             WHERE status IN ('approved_l1','approved') AND department IS NOT NULL AND supplier_bill_id IS NULL" . ($po ? ' AND purchase_order_id IS NULL' : '') . ($excludeReq ? ' AND id <> ' . (int) $excludeReq : '') . '
             GROUP BY department'
        ) as $r) {
            $out[$r['department']] = (float) $r['amt'];
        }
        foreach (Purchasing::openValueByDepartment() as $dept => $amt) {
            $out[$dept] = ($out[$dept] ?? 0) + $amt;
        }
        // unpaid supplier bills (a requisition's bill takes the requisition's department)
        foreach (db()->all(
            "SELECT COALESCE(b.department, r.department) AS dept, SUM(b.amount - b.paid_gross) AS amt FROM supplier_bills b LEFT JOIN requisitions r ON r.id = b.requisition_id
             WHERE b.status IN ('unpaid','part') GROUP BY COALESCE(b.department, r.department)"
        ) as $r) {
            if ($r['dept'] !== null) {
                $out[$r['dept']] = ($out[$r['dept']] ?? 0) + (float) $r['amt'];
            }
        }
        return array_map(fn($v) => round($v, 2), $out);
    }

    /** Rows for the caps page: department, cap, spent, committed, left, pct. */
    public static function rows(array $term): array
    {
        $caps = self::caps((int) $term['id']);
        $spent = self::spent($term);
        $com = self::committed();
        $names = Finance::departments(false);
        foreach (array_keys($caps + $spent) as $n) {
            if (!in_array($n, $names, true)) {
                $names[] = $n;
            }
        }
        $rows = [];
        foreach ($names as $n) {
            $cap = $caps[$n] ?? null;
            $s = $spent[$n] ?? 0.0;
            $c = $com[$n] ?? 0.0;
            $rows[] = ['department' => $n, 'cap' => $cap, 'spent' => $s, 'committed' => $c,
                'left' => $cap !== null ? round($cap - $s - $c, 2) : null, 'pct' => $cap ? (int) round(($s + $c) * 100 / $cap) : null];
        }
        return $rows;
    }

    /**
     * Would spending $amount for $dept go over its cap this term? Returns null (fine) or the reason it is stopped.
     */
    public static function check(?string $dept, float $amount, ?int $excludeReq = null, ?string $what = null): ?string
    {
        if (!self::ready() || !$dept || !($term = self::term())) {
            return null;
        }
        $cap = self::caps((int) $term['id'])[$dept] ?? null;
        if ($cap === null) {
            return null;
        }
        $left = round($cap - (self::spent($term)[$dept] ?? 0) - (self::committed($excludeReq)[$dept] ?? 0), 2);
        if ($amount <= $left + 0.004) {
            return null;
        }
        return 'Stopped: ' . ($what ? $what . ' (' . Fees::money($amount) . ')' : 'this request') . ' would take ' . $dept . ' over its ' . $term['name'] . ' cap by '
            . Fees::money(round($amount - max(0.0, $left), 2)) . ' (' . Fees::money(max(0.0, $left)) . ' left of ' . Fees::money($cap) . '). The Head can raise the cap or move money from another department.';
    }

    public static function setCap(int $termId, string $dept, float $amount, string $kind, string $note): void
    {
        $old = db()->value('SELECT amount FROM fin_dept_caps WHERE term_id = ? AND department = ?', [$termId, $dept]);
        if ($old !== null && abs((float) $old - $amount) < 0.005) {
            return;
        }
        db()->run('INSERT INTO fin_dept_caps (term_id, department, amount) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE amount = VALUES(amount)', [$termId, $dept, round($amount, 2)]);
        db()->insert('fin_dept_cap_log', ['term_id' => $termId, 'department' => $dept, 'kind' => $kind, 'old_amount' => $old, 'new_amount' => round($amount, 2), 'note' => mb_substr($note, 0, 255) ?: null, 'user_id' => Auth::id()]);
    }

    public static function removeCap(int $termId, string $dept): void
    {
        $old = db()->value('SELECT amount FROM fin_dept_caps WHERE term_id = ? AND department = ?', [$termId, $dept]);
        if ($old === null) {
            return;
        }
        db()->run('DELETE FROM fin_dept_caps WHERE term_id = ? AND department = ?', [$termId, $dept]);
        db()->insert('fin_dept_cap_log', ['term_id' => $termId, 'department' => $dept, 'kind' => 'set', 'old_amount' => $old, 'new_amount' => 0, 'note' => 'Cap removed', 'user_id' => Auth::id()]);
    }

    /** Move part of one department's cap to another. */
    public static function move(int $termId, string $from, string $to, float $amount, string $note): ?string
    {
        $caps = self::caps($termId);
        $term = self::term($termId);
        if (!isset($caps[$from])) {
            return $from . ' has no cap this term.';
        }
        if ($from === $to) {
            return 'Choose two different departments.';
        }
        $row = array_values(array_filter(self::rows($term), fn($r) => $r['department'] === $from))[0] ?? null;
        $left = $row['left'] ?? 0.0;
        if ($amount <= 0 || $amount > $left + 0.004) {
            return 'You can move up to ' . Fees::money(max(0.0, $left)) . ' from ' . $from . ' (what it has not spent or promised).';
        }
        db()->transaction(function () use ($termId, $from, $to, $amount, $note, $caps) {
            self::setCap($termId, $from, $caps[$from] - $amount, 'move_out', 'To ' . $to . ': ' . $note);
            self::setCap($termId, $to, ($caps[$to] ?? 0) + $amount, 'move_in', 'From ' . $from . ': ' . $note);
        });
        return null;
    }
}
