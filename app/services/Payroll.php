<?php
declare(strict_types=1);

/**
 * Monthly staff payroll (Ghana): SSNIT on basic salary, PAYE on taxable pay after the
 * employee's SSNIT, using bands kept in settings so the Bursar can update them when GRA does.
 */
final class Payroll
{
    public const EMPLOYMENT = ['permanent' => 'Permanent', 'contract' => 'Contract', 'casual' => 'Casual', 'national_service' => 'National Service'];
    public const CATEGORY = ['teaching' => 'Teaching', 'non_teaching' => 'Non-teaching'];
    public const PAY_METHOD = ['bank' => 'Bank', 'momo' => 'Mobile Money', 'cash' => 'Cash'];
    public const GENDER = ['male' => 'Male', 'female' => 'Female'];

    /** Highest qualification => [label, professionally trained teacher?] (trained/untrained is reported in the school census). */
    public const QUALIFICATION = [
        'basic'    => ['BECE / MSLC', false],
        'wassce'   => ['WASSCE / SSSCE', false],
        'cert_a'   => ["Teacher's Certificate 'A'", true],
        'dbe'      => ['Diploma in Basic Education', true],
        'diploma'  => ['Other diploma', false],
        'bed'      => ['B.Ed (Bachelor of Education)', true],
        'degree'   => ["Other bachelor's degree", false],
        'pgde'     => ['Bachelor\'s + PGDE / PGCE', true],
        'masters'  => ["Master's degree", false],
        'med'      => ['M.Ed / M.Phil (Education)', true],
        'other'    => ['Other', false],
    ];

    /** Days before expiry when the NTC licence is flagged. */
    public const NTC_WARN_DAYS = 60;

    /** NTC licence state for a staff row: null (not a teacher), 'missing', 'expired', 'expiring' or 'valid'. */
    public static function ntcStatus(array $s): ?string
    {
        if (($s['category'] ?? '') !== 'teaching' || !(int) ($s['is_active'] ?? 1)) {
            return null;
        }
        if (empty($s['ntc_licence_no']) || empty($s['ntc_expiry'])) {
            return 'missing';
        }
        if ($s['ntc_expiry'] < date('Y-m-d')) {
            return 'expired';
        }
        return $s['ntc_expiry'] <= date('Y-m-d', strtotime('+' . self::NTC_WARN_DAYS . ' days')) ? 'expiring' : 'valid';
    }

    public static function ntcBadge(array $s): string
    {
        return match (self::ntcStatus($s)) {
            'missing'  => badge('NTC licence not recorded', 'warn'),
            'expired'  => badge('NTC licence expired', 'bad'),
            'expiring' => badge('NTC licence expires ' . date('j M Y', strtotime($s['ntc_expiry'])), 'warn'),
            'valid'    => badge('NTC licensed', 'good'),
            default    => '',
        };
    }

    /** Teaching staff whose NTC licence is missing, expired or expiring soon. */
    public static function ntcProblems(): int
    {
        try {
            return (int) db()->value(
                "SELECT COUNT(*) FROM staff WHERE is_active = 1 AND category = 'teaching'
                 AND (ntc_licence_no IS NULL OR ntc_expiry IS NULL OR ntc_expiry <= (CURDATE() + INTERVAL ? DAY))",
                [self::NTC_WARN_DAYS]
            );
        } catch (PDOException $e) {
            return 0; // database not updated yet (migration 010) — the "Update database now" banner is showing
        }
    }

    /** Daily: SMS a teacher 60 and 14 days before the NTC licence expires (once per stage per expiry date). */
    public static function ntcReminders(): int
    {
        if (!db()->value("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'staff' AND column_name = 'ntc_expiry'")) {
            return 0; // migration 010 not applied yet
        }
        $today = date('Y-m-d');
        if (setting('ntc_last_check') === $today) {
            return 0;
        }
        setting_set('ntc_last_check', $today);
        $sent = 0;
        $rows = db()->all(
            "SELECT *, DATEDIFF(ntc_expiry, CURDATE()) AS days_left FROM staff
             WHERE is_active = 1 AND category = 'teaching' AND phone IS NOT NULL AND ntc_licence_no IS NOT NULL
               AND ntc_expiry BETWEEN CURDATE() AND (CURDATE() + INTERVAL 60 DAY)"
        );
        foreach ($rows as $s) {
            $stage = (int) $s['days_left'] <= 14 ? 14 : 60; // one SMS per stage, never both at once
            $event = 'ntc_expiring_' . $stage;
            if (db()->value(
                "SELECT 1 FROM notification_outbox WHERE event_key = ? AND entity = 'staff' AND entity_id = ? AND created_at > (? - INTERVAL ? DAY) AND status <> 'cancelled' LIMIT 1",
                [$event, $s['id'], $s['ntc_expiry'], $stage + 5]
            )) {
                continue;
            }
            $body = Notifier::render('ntc_expiring', ['name' => $s['name'], 'licence' => $s['ntc_licence_no'], 'date' => date('j M Y', strtotime($s['ntc_expiry']))]);
            if ($body && Notifier::sms($s['phone'], $body, ['name' => $s['name'], 'event' => $event, 'entity' => 'staff', 'entity_id' => $s['id']])) {
                $sent++;
            }
        }
        return $sent;
    }

    /** "GHA-123456789-0" from what was typed, or null if it is not a Ghana Card number. */
    public static function normaliseGhanaCard(string $v): ?string
    {
        $d = preg_replace('/[^0-9]/', '', strtoupper($v));
        return strlen($d) === 10 ? 'GHA-' . substr($d, 0, 9) . '-' . substr($d, 9) : null;
    }
    public const STATUS = ['draft' => ['Draft', 'neutral'], 'approved' => ['Approved — ready to pay', 'info'], 'paid' => ['Paid', 'good']];

    /** @return array<int,array{0:float,1:float}> [band width, rate %]; width 0 = the rest */
    public static function bands(?string $raw = null): array
    {
        $raw = $raw ?? setting('paye_bands', '588:0,80:5,100:10,2900:17.5,16000:25,30332:30,0:35');
        $out = [];
        foreach (explode(',', $raw) as $pair) {
            $p = explode(':', trim($pair));
            if (count($p) === 2 && is_numeric($p[0]) && is_numeric($p[1])) {
                $out[] = [max(0.0, (float) $p[0]), max(0.0, min(100.0, (float) $p[1]))];
            }
        }
        return $out;
    }

    public static function validBands(string $raw): bool
    {
        $b = self::bands($raw);
        return count($b) >= 2 && count($b) === count(explode(',', $raw)) && end($b)[0] == 0.0;
    }

    /** Monthly PAYE on chargeable income. */
    public static function paye(float $chargeable, ?array $bands = null): float
    {
        $bands = $bands ?? self::bands();
        $left = max(0.0, $chargeable);
        $tax = 0.0;
        foreach ($bands as $i => [$width, $rate]) {
            $last = $i === count($bands) - 1 || $width <= 0;
            $slice = $last ? $left : min($left, $width);
            $tax += $slice * $rate / 100;
            $left -= $slice;
            if ($left <= 0 || $last) {
                break;
            }
        }
        return round($tax, 2);
    }

    /**
     * Work out one payslip from a staff record and their standing pay items.
     * $days = [days worked, days in the month] for someone who joined or left during the month:
     * basic salary and allowances are split by calendar days; deductions stay as they are.
     */
    public static function compute(array $staff, array $items, ?array $days = null): array
    {
        $part = $days && $days[0] < $days[1] ? $days[0] / $days[1] : 1.0;
        $basic = round((float) $staff['basic_salary'] * $part, 2);
        $taxAllow = 0.0;
        $freeAllow = 0.0;
        $ded = 0.0;
        $lines = [];
        foreach ($items as $it) {
            $amt = round((float) $it['amount'] * ($it['kind'] === 'allowance' ? $part : 1.0), 2);
            if ($amt <= 0) {
                continue;
            }
            if ($it['kind'] === 'allowance') {
                if ((int) $it['taxable']) {
                    $taxAllow += $amt;
                } else {
                    $freeAllow += $amt;
                }
            } else {
                $ded += $amt;
            }
            $lines[] = ['kind' => $it['kind'], 'name' => $it['name'], 'amount' => $amt, 'taxable' => (int) $it['taxable']];
        }
        $ssnitE = (int) $staff['pays_ssnit'] ? round($basic * (float) setting('ssnit_employee_rate', '5.5') / 100, 2) : 0.0;
        $ssnitR = (int) $staff['pays_ssnit'] ? round($basic * (float) setting('ssnit_employer_rate', '13') / 100, 2) : 0.0;
        $taxable = max(0.0, round($basic + $taxAllow - $ssnitE, 2));
        $paye = (int) $staff['pays_paye'] ? self::paye($taxable) : 0.0;
        $gross = round($basic + $taxAllow + $freeAllow, 2);
        return [
            'basic' => $basic, 'allowances' => round($taxAllow + $freeAllow, 2), 'gross' => $gross,
            'ssnit_employee' => $ssnitE, 'ssnit_employer' => $ssnitR, 'taxable' => $taxable, 'paye' => $paye,
            'deductions' => round($ded, 2), 'net' => round($gross - $ssnitE - $paye - $ded, 2),
            'breakdown' => json_encode($lines),
            'days_paid' => $part < 1 ? $days[0] : null, 'days_in_month' => $part < 1 ? $days[1] : null,
        ];
    }

    public static function periodLabel(string $period): string
    {
        return date('F Y', strtotime($period . '-01'));
    }

    /** (Re)build every payslip of a draft run from the current staff records. */
    public static function build(int $runId): void
    {
        db()->transaction(function (Database $d) use ($runId) {
            $run = $d->one('SELECT * FROM payroll_runs WHERE id = ? FOR UPDATE', [$runId]);
            if (!$run || $run['status'] !== 'draft') {
                throw new RuntimeException('not_draft');
            }
            $first = $run['period'] . '-01';
            $last = date('Y-m-t', strtotime($first));
            $d->run('DELETE FROM payslips WHERE run_id = ?', [$runId]);
            $staff = $d->all(
                'SELECT * FROM staff WHERE is_active = 1 AND (start_date IS NULL OR start_date <= ?) AND (end_date IS NULL OR end_date >= ?) ORDER BY name',
                [$last, $first]
            );
            $hasDays = (bool) $d->value("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'payslips' AND column_name = 'days_paid'");
            foreach ($staff as $s) {
                $items = $d->all('SELECT * FROM staff_pay_items WHERE staff_id = ? ORDER BY kind, name', [$s['id']]);
                $daily = ($s['pay_basis'] ?? 'monthly') === 'daily';
                if ($daily) {
                    // Paid by the day: approved timesheet days × daily rate (nothing until the timesheet is approved)
                    $days = $s['user_id'] ? Timesheets::approvedDays((int) $s['user_id'], $run['period']) : null;
                    $s['basic_salary'] = round((float) $days * (float) $s['daily_rate'], 2);
                }
                $slip = self::compute($s, $items, $hasDays && !$daily ? self::daysWorked($s, $first, $last) : null);
                if (!$hasDays) {
                    unset($slip['days_paid'], $slip['days_in_month']); // migration 016 not applied yet: pay a full month
                }
                $d->insert('payslips', ['run_id' => $runId, 'staff_id' => $s['id']] + $slip);
            }
            self::totals($d, $runId);
        });
    }

    public static function totals(Database $d, int $runId): void
    {
        $t = $d->one(
            'SELECT COUNT(*) n, COALESCE(SUM(gross),0) gross, COALESCE(SUM(net),0) net, COALESCE(SUM(ssnit_employee),0) se,
                    COALESCE(SUM(ssnit_employer),0) sr, COALESCE(SUM(paye),0) paye, COALESCE(SUM(deductions),0) ded
             FROM payslips WHERE run_id = ?',
            [$runId]
        );
        $d->update('payroll_runs', [
            'staff_count' => $t['n'], 'gross' => $t['gross'], 'net' => $t['net'], 'ssnit_employee' => $t['se'],
            'ssnit_employer' => $t['sr'], 'paye' => $t['paye'], 'deductions' => $t['ded'],
        ], 'id = ?', [$runId]);
    }

    /** Pay out: one cash book expense for the net salaries (Salaries & allowances). */
    public static function markPaid(int $runId, int $accountId, string $paidOn): void
    {
        db()->transaction(function (Database $d) use ($runId, $accountId, $paidOn) {
            $run = $d->one('SELECT * FROM payroll_runs WHERE id = ? FOR UPDATE', [$runId]);
            if (!$run || $run['status'] !== 'approved') {
                throw new RuntimeException('not_approved');
            }
            $cat = $d->value("SELECT id FROM fin_categories WHERE code = 'SAL'");
            Finance::post($d, [
                'kind' => 'expense', 'txn_date' => $paidOn, 'account_id' => $accountId, 'category_id' => $cat ?: null,
                'amount' => $run['net'], 'description' => 'Staff salaries (net) ' . self::periodLabel($run['period']) . ' — ' . $run['staff_count'] . ' staff',
                'party' => 'Staff payroll', 'reference' => 'PAYROLL ' . $run['period'], 'source' => 'payroll', 'source_id' => $runId,
            ]);
            $d->update('payroll_runs', ['status' => 'paid', 'paid_by' => Auth::id(), 'paid_at' => date('Y-m-d H:i:s'), 'paid_on' => $paidOn, 'account_id' => $accountId], 'id = ?', [$runId]);
        });
    }

    /** SSNIT (both shares) and PAYE handed over to SSNIT / GRA: one cash book expense (SSNIT & statutory). */
    public static function markStatutoryPaid(int $runId, int $accountId, string $paidOn): void
    {
        db()->transaction(function (Database $d) use ($runId, $accountId, $paidOn) {
            $run = $d->one('SELECT * FROM payroll_runs WHERE id = ? FOR UPDATE', [$runId]);
            if (!$run || $run['status'] !== 'paid' || $run['statutory_paid_on']) {
                throw new RuntimeException('not_paid');
            }
            $amt = round((float) $run['ssnit_employee'] + (float) $run['ssnit_employer'] + (float) $run['paye'], 2);
            if ($amt > 0) {
                $cat = $d->value("SELECT id FROM fin_categories WHERE code = 'SSNIT'");
                Finance::post($d, [
                    'kind' => 'expense', 'txn_date' => $paidOn, 'account_id' => $accountId, 'category_id' => $cat ?: null, 'amount' => $amt,
                    'description' => 'SSNIT & PAYE for ' . self::periodLabel($run['period']),
                    'party' => 'SSNIT / GRA', 'reference' => 'STAT ' . $run['period'], 'source' => 'payroll_stat', 'source_id' => $runId,
                ]);
            }
            $d->update('payroll_runs', ['statutory_paid_on' => $paidOn], 'id = ?', [$runId]);
        });
    }

    /** [days worked, days in month] when the staff member started or left during the month, else null. */
    public static function daysWorked(array $s, string $first, string $last): ?array
    {
        $from = !empty($s['start_date']) && $s['start_date'] > $first ? $s['start_date'] : $first;
        $to = !empty($s['end_date']) && $s['end_date'] < $last ? $s['end_date'] : $last;
        $dim = (int) date('t', strtotime($first));
        $worked = (int) ((strtotime($to) - strtotime($from)) / 86400) + 1;
        return $worked < $dim ? [max(0, $worked), $dim] : null;
    }

    /** Daily-rate pay is available once migration 017 has added the columns. */
    public static function hasDailyPay(): bool
    {
        static $has = null;
        return $has ??= (bool) db()->value("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'staff' AND column_name = 'pay_basis'");
    }

    /** Daily-paid staff in a draft run whose timesheet for the month is not approved yet. */
    public static function dailyWithoutTimesheet(string $period): array
    {
        if (!self::hasDailyPay()) {
            return [];
        }
        $out = [];
        foreach (db()->all("SELECT name, user_id FROM staff WHERE is_active = 1 AND pay_basis = 'daily' ORDER BY name") as $s) {
            if (!$s['user_id'] || Timesheets::approvedDays((int) $s['user_id'], $period) === null) {
                $out[] = $s['name'];
            }
        }
        return $out;
    }

    /** True until the Bursar saves the tax bands after the 1 September 2026 change (Act 1178). */
    public static function bandsNeedCheck(): bool
    {
        return setting('paye_bands_confirmed', '') === '';
    }

    /** Names of staff whose net pay in a run is below zero. */
    public static function negativeSlips(int $runId): array
    {
        return array_column(db()->all('SELECT s.name FROM payslips ps JOIN staff s ON s.id = ps.staff_id WHERE ps.run_id = ? AND ps.net < 0 ORDER BY s.name', [$runId]), 'name');
    }

    public static function nextStaffNo(Database $d): string
    {
        $n = (int) $d->value("SELECT COALESCE(MAX(CAST(SUBSTRING(staff_no, 4) AS UNSIGNED)), 0) FROM staff WHERE staff_no LIKE 'STF%'");
        return 'STF' . str_pad((string) ($n + 1), 3, '0', STR_PAD_LEFT);
    }

    public static function lines(array $slip): array
    {
        $l = json_decode((string) ($slip['breakdown'] ?? ''), true);
        return is_array($l) ? $l : [];
    }
}
