<?php
/* Payroll: the Payroll engine (prepare a month) and the log of every payroll. Expects $runs, $staffCount, $monthly. */
$mg = can('payroll.manage');
$configure = can('app.configure');
$done = array_column($runs, 'period');
$months = [];
for ($i = -1; $i <= 11; $i++) { // next month, this month and the 11 before it
    $p = date('Y-m', strtotime(date('Y-m-01') . " -$i month"));
    $months[$p] = Payroll::periodLabel($p) . (in_array($p, $done, true) ? ' (prepared)' : '');
}
$sel = date('Y-m');
$last = $runs[0] ?? null;
?>
<?php require __DIR__ . '/_tabs.php'; ?>
<section class="card pr-engine">
    <div class="page-head pr-engine-head">
        <h1>Payroll engine</h1>
        <?php if ($configure): ?><button type="button" class="btn btn-ghost" data-help="rates-box" aria-expanded="false">Tax &amp; SSNIT rates</button><?php endif; ?>
    </div>
    <?php if ($mg): ?>
    <form method="post" action="<?= e(url('payroll/runs')) ?>" class="pr-run">
        <?= csrf_field() ?>
        <label class="pr-month"><span class="sr-only">Month</span><select name="period" aria-label="Month"><?php foreach ($months as $p => $l): ?><option value="<?= e($p) ?>"<?= selected($p, $sel) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
        <button class="btn btn-primary pr-go" type="submit">Prepare payroll</button>
    </form>
    <?php endif; ?>
    <div class="pr-stats">
        <div><span>Active staff</span><strong><?= (int) $staffCount ?></strong></div>
        <div><span>Monthly basic salaries</span><strong><?= e(Fees::money($monthly)) ?></strong></div>
        <div><span>Last payroll</span><strong><?= $last ? e(Payroll::periodLabel($last['period'])) : '—' ?></strong><?php if ($last): [$ls, $lt] = Payroll::STATUS[$last['status']]; ?><?= badge($ls, $lt) ?><?php endif; ?></div>
    </div>
</section>

<?php if ($configure): ?>
<form id="rates-box"<?= Payroll::bandsNeedCheck() ? '' : ' hidden' ?> method="post" action="<?= e(url('payroll/settings')) ?>" class="card form pr-rates">
    <?= csrf_field() ?>
    <h2>Tax &amp; SSNIT rates</h2>
    <label>PAYE monthly bands (width:rate %)<input name="paye_bands" value="<?= e(setting('paye_bands')) ?>"></label>
    <div class="grid-2">
        <label>SSNIT staff share %<input name="ssnit_employee_rate" value="<?= e(setting('ssnit_employee_rate', '5.5')) ?>"></label>
        <label>SSNIT school share %<input name="ssnit_employer_rate" value="<?= e(setting('ssnit_employer_rate', '13')) ?>"></label>
    </div>
    <div class="gate-actions"><button class="btn btn-primary" type="submit">Save rates</button><button type="button" class="btn btn-ghost" data-help="rates-box">Cancel</button></div>
</form>
<?php endif; ?>

<div class="xgrid-box" data-xgrid="payrolls" data-xgrid-file="payrolls">
<div class="page-head">
    <div><h1>Payrolls log</h1></div>
    <div class="actions"><?= export_grid_menu() ?></div>
</div>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['date' => ['Date', 'date'], 'month' => ['Month', 'date'], 'gross' => ['Gross total (GH₵)', 'num', 'num'], 'net' => ['Net pay (GH₵)', 'num', 'num'], 'staff' => ['Staff count', 'num', 'num'], 'status' => ['Status'], 'by' => ['Prepared by']]) ?></tr></thead>
    <tbody>
    <?php foreach ($runs as $r): [$sl, $st] = Payroll::STATUS[$r['status']]; $owed = $r['status'] === 'paid' && !$r['statutory_paid_on'] && ((float) $r['paye'] + (float) $r['ssnit_employee']) > 0; ?>
    <tr>
        <td data-c="date" data-s="<?= e($r['created_at']) ?>"><?= e(fmt_date($r['created_at'], 'j M Y')) ?></td>
        <td data-c="month" data-s="<?= e($r['period']) ?>"><a href="<?= e(url('payroll/runs/' . $r['id'])) ?>"><strong><?= e(Payroll::periodLabel($r['period'])) ?></strong></a></td>
        <td data-c="gross" class="num"><?= e(number_format((float) $r['gross'], 2)) ?></td>
        <td data-c="net" class="num"><strong><?= e(number_format((float) $r['net'], 2)) ?></strong></td>
        <td data-c="staff" class="num"><?= (int) $r['staff_count'] ?></td>
        <td data-c="status"><?= badge($sl, $st) ?><?= $owed ? '<span hidden> · </span> ' . badge('SSNIT/PAYE due', 'warn') : '' ?></td>
        <td data-c="by"><?= e($r['created_name'] ?? '—') ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $runs ? ' hidden' : '' ?> data-xg-empty>No payroll yet.</p>
</div>
</div>
