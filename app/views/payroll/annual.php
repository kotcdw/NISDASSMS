<?php $print = isset($_GET['print']); $sum = fn($k) => array_sum(array_column($rows, $k)); $f = fn($v) => number_format((float) $v, 2); ?>
<?php if ($print): $docTitle = 'PAYROLL SUMMARY'; $docSub = 'January – December ' . $year; ?><div class="card-page doc-page landscape print-tables"><?php require dirname(__DIR__) . '/fees/_doc_head.php'; ?>
<table>
    <tr><th>Staff</th><th>SSNIT no.</th><th class="rpt-num">Months</th><th class="rpt-num">Gross</th><th class="rpt-num">SSNIT staff</th><th class="rpt-num">SSNIT school</th><th class="rpt-num">PAYE</th><th class="rpt-num">Other ded.</th><th class="rpt-num">Net</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr><td><?= e($r['name']) ?> <small class="muted"><?= e($r['staff_no']) ?></small></td><td><?= e($r['ssnit_no'] ?? '') ?></td><td class="rpt-num"><?= (int) $r['months'] ?></td><td class="rpt-num"><?= $f($r['gross']) ?></td><td class="rpt-num"><?= $f($r['ssnit_e']) ?></td><td class="rpt-num"><?= $f($r['ssnit_r']) ?></td><td class="rpt-num"><?= $f($r['paye']) ?></td><td class="rpt-num"><?= $f($r['ded']) ?></td><td class="rpt-num"><strong><?= $f($r['net']) ?></strong></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="9" class="muted">No paid payroll in <?= (int) $year ?>.</td></tr><?php else: ?>
    <tr><th colspan="3">Total</th><th class="rpt-num"><?= $f($sum('gross')) ?></th><th class="rpt-num"><?= $f($sum('ssnit_e')) ?></th><th class="rpt-num"><?= $f($sum('ssnit_r')) ?></th><th class="rpt-num"><?= $f($sum('paye')) ?></th><th class="rpt-num"><?= $f($sum('ded')) ?></th><th class="rpt-num"><?= $f($sum('net')) ?></th></tr><?php endif; ?>
</table>
<div class="rc-sign"><div><span></span>Bursar</div><div><span></span>Head Teacher</div></div></div>
<?php else: ?>
<div class="xgrid-box" data-xgrid="payroll-year" data-xgrid-file="payroll-summary-<?= (int) $year ?>">
<div class="page-head">
    <div><h1>Payroll summary <?= (int) $year ?></h1></div>
    <div class="actions">
        <form method="get" action="<?= e(url('payroll/annual')) ?>" class="list-pick" data-autosubmit><select name="year" aria-label="Year"><?php for ($y = (int) date('Y'); $y >= (int) date('Y') - 5; $y--): ?><option<?= selected($year, $y) ?>><?= $y ?></option><?php endfor; ?></select></form>
        <a class="btn btn-ghost" href="<?= e(url('payroll/annual', ['year' => $year, 'print' => 1])) ?>" target="_blank">Print</a>
        <?= export_grid_menu() ?>
    </div>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['staff' => ['Staff'], 'no' => ['Staff no.'], 'ssnit' => ['SSNIT no.'], 'months' => ['Months', 'num', 'num'], 'gross' => ['Gross', 'num', 'num'], 'se' => ['SSNIT staff', 'num', 'num'], 'sr' => ['SSNIT school', 'num', 'num'], 'paye' => ['PAYE', 'num', 'num'], 'ded' => ['Other ded.', 'num', 'num'], 'net' => ['Net', 'num', 'num']]) ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
    <tr>
        <td data-c="staff"><?= e($r['name']) ?></td>
        <td data-c="no"><?= e($r['staff_no']) ?></td>
        <td data-c="ssnit"><?= e($r['ssnit_no'] ?? '') ?></td>
        <td data-c="months" class="num"><?= (int) $r['months'] ?></td>
        <td data-c="gross" class="num" data-s="<?= (float) $r['gross'] ?>"><?= $f($r['gross']) ?></td>
        <td data-c="se" class="num" data-s="<?= (float) $r['ssnit_e'] ?>"><?= $f($r['ssnit_e']) ?></td>
        <td data-c="sr" class="num" data-s="<?= (float) $r['ssnit_r'] ?>"><?= $f($r['ssnit_r']) ?></td>
        <td data-c="paye" class="num" data-s="<?= (float) $r['paye'] ?>"><?= $f($r['paye']) ?></td>
        <td data-c="ded" class="num" data-s="<?= (float) $r['ded'] ?>"><?= $f($r['ded']) ?></td>
        <td data-c="net" class="num" data-s="<?= (float) $r['net'] ?>"><strong><?= $f($r['net']) ?></strong></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    <?php if ($rows): ?><tfoot><tr><td colspan="4"><strong>Total</strong></td><td class="num"><strong><?= $f($sum('gross')) ?></strong></td><td class="num"><strong><?= $f($sum('ssnit_e')) ?></strong></td><td class="num"><strong><?= $f($sum('ssnit_r')) ?></strong></td><td class="num"><strong><?= $f($sum('paye')) ?></strong></td><td class="num"><strong><?= $f($sum('ded')) ?></strong></td><td class="num"><strong><?= $f($sum('net')) ?></strong></td></tr></tfoot><?php endif; ?>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No paid payroll in <?= (int) $year ?>.</p>
</div>
</div>
<?php endif; ?>
