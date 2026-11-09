<?php $n = fn($v) => number_format((float) $v, 2); $neg = fn($v) => (float) $v < 0 ? '(' . number_format(-(float) $v, 2) . ')' : number_format((float) $v, 2); ?>
<?php if ($tab === 'cashflow'): ?>
<div class="table-wrap"><table data-nopage>
    <thead><tr><th>Cash flow <?= e(fmt_date($from)) ?> – <?= e(fmt_date($to)) ?></th><th class="num">GH₵</th></tr></thead>
    <tbody>
        <tr><td><strong>Cash at the start (all accounts)</strong></td><td class="num"><strong><?= e($n($opening)) ?></strong></td></tr>
        <?php foreach (['running' => 'Day-to-day running', 'assets' => 'Buildings, furniture & equipment'] as $k => $label): if (!$groups[$k]) continue; ?>
        <tr class="group-row"><td colspan="2"><strong><?= e($label) ?></strong></td></tr>
        <?php foreach ($groups[$k] as $r): ?><tr><td style="padding-left:1.5rem"><?= $r['in'] ? 'In' : 'Out' ?>: <?= e($r['name']) ?></td><td class="num"><?= e($neg($r['amount'])) ?></td></tr><?php endforeach; ?>
        <tr><td style="padding-left:1.5rem"><em>Net <?= e(strtolower($label)) ?></em></td><td class="num"><em><?= e($neg(array_sum(array_column($groups[$k], 'amount')))) ?></em></td></tr>
        <?php endforeach; ?>
        <tr><td><strong>Change in cash</strong></td><td class="num"><strong><?= e($neg($net)) ?></strong></td></tr>
        <tr><td><strong>Cash at the end (all accounts)</strong></td><td class="num"><strong><?= e($n($closing)) ?></strong></td></tr>
    </tbody>
</table></div>
<?php elseif ($tab === 'income'): ?>
<?php if (!empty($noLedger)): ?><p class="muted" style="padding:1rem">Update the database to see this report.</p><?php else: ?>
<div class="table-wrap"><table data-nopage>
    <tbody>
        <tr class="group-row"><td colspan="3"><strong>Income</strong></td></tr>
        <?php foreach ($inc as $a): ?><tr><td style="width:5rem"><code><?= e($a['code']) ?></code></td><td><?= e($a['name']) ?></td><td class="num"><?= e($neg($a['amount'])) ?></td></tr><?php endforeach; ?>
        <tr><td></td><td><strong>Total income</strong></td><td class="num"><strong><?= e($n($ti)) ?></strong></td></tr>
        <tr class="group-row"><td colspan="3"><strong>Spending</strong></td></tr>
        <?php foreach ($exp as $a): ?><tr><td><code><?= e($a['code']) ?></code></td><td><?= e($a['name']) ?></td><td class="num"><?= e($neg($a['amount'])) ?></td></tr><?php endforeach; ?>
        <tr><td></td><td><strong>Total spending</strong></td><td class="num"><strong><?= e($n($te)) ?></strong></td></tr>
        <tr><td></td><td><strong><?= $ti - $te >= 0 ? 'Surplus' : 'Deficit' ?></strong></td><td class="num"><strong style="color:<?= $ti - $te >= 0 ? 'var(--good)' : 'var(--bad)' ?>"><?= e($neg($ti - $te)) ?></strong></td></tr>
    </tbody>
</table></div>
<?php endif; ?>
<?php elseif ($tab === 'budget'): ?>
<?php if (!$year): ?><p class="muted" style="padding:1rem">Set up the academic year first.</p><?php else: ?>
<div class="table-wrap"><table data-nopage>
    <thead><tr><th><?= e($year['name']) ?></th><th class="num">Budget</th><th class="num">Expected by now</th><th class="num">Actual</th><th class="num">Difference</th><th></th></tr></thead>
    <tbody><?php foreach (['income' => 'Income', 'expense' => 'Spending'] as $k => $label): ?>
        <tr class="group-row"><td colspan="6"><strong><?= e($label) ?></strong></td></tr>
        <?php foreach (array_filter($rows, fn($r) => $r['kind'] === $k) as $r): ?>
        <tr><td><?= e($r['name']) ?></td><td class="num"><?= $r['budget'] !== null ? e($n($r['budget'])) : '—' ?></td><td class="num"><?= $r['expected'] !== null ? e($n($r['expected'])) : '' ?></td><td class="num"><?= e($n($r['actual'])) ?></td>
            <td class="num"><?= $r['variance'] !== null ? e($neg($r['variance'])) : '' ?></td>
            <td><?= $r['flag'] ? badge(['over' => 'over budget', 'under' => 'under budget', 'behind' => 'behind plan', 'ahead' => 'ahead of plan'][$r['flag']], in_array($r['flag'], ['over', 'behind'], true) ? 'bad' : 'good') : '' ?></td></tr>
        <?php endforeach; ?>
    <?php endforeach; ?></tbody>
</table></div>
<?php if ($depts): ?>
<div class="table-wrap"><table data-nopage>
    <thead><tr><th>Departments — <?= e($termName) ?></th><th class="num">Cap</th><th class="num">Spent</th><th class="num">Promised</th><th class="num">Left</th></tr></thead>
    <tbody><?php foreach ($depts as $r): ?><tr><td><?= e($r['department']) ?></td><td class="num"><?= $r['cap'] !== null ? e($n($r['cap'])) : '—' ?></td><td class="num"><?= e($n($r['spent'])) ?></td><td class="num"><?= e($n($r['committed'])) ?></td><td class="num"><?= $r['left'] !== null ? e($neg($r['left'])) : '' ?></td></tr><?php endforeach; ?></tbody>
</table></div>
<?php endif; ?>
<?php endif; ?>
<?php elseif ($tab === 'debtors'): $tot = array_fill_keys(array_keys($buckets), 0.0); ?>
<div class="table-wrap"><table data-nopage>
    <thead><tr><th>Class</th><th class="num">Students</th><?php foreach ($buckets as $b): ?><th class="num"><?= e($b) ?></th><?php endforeach; ?><th class="num">Total</th></tr></thead>
    <tbody><?php foreach ($rows as $r): $t = 0; ?>
        <tr><td><?= e($r['class_name']) ?></td><td class="num"><?= (int) $r['pupils'] ?></td>
        <?php foreach (array_keys($buckets) as $b): $t += (float) $r[$b]; $tot[$b] += (float) $r[$b]; ?><td class="num"<?= in_array($b, ['d90', 'd90p'], true) && (float) $r[$b] > 0 ? ' style="color:var(--bad)"' : '' ?>><?= (float) $r[$b] ? e($n($r[$b])) : '' ?></td><?php endforeach; ?>
        <td class="num"><strong><?= e($n($t)) ?></strong></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="8" class="muted">No fees owed.</td></tr><?php endif; ?></tbody>
    <tfoot><tr><td><strong>Total</strong></td><td></td><?php foreach ($tot as $v): ?><td class="num"><strong><?= e($n($v)) ?></strong></td><?php endforeach; ?><td class="num"><strong><?= e($n(array_sum($tot))) ?></strong></td></tr></tfoot>
</table></div>
<?php else: ?>
<div class="table-wrap"><table data-nopage><tbody>
    <tr class="group-row"><td colspan="2"><strong>Withholding tax (suppliers)</strong></td></tr>
    <tr><td>Kept back from supplier payments in these dates</td><td class="num"><?= e($n($wht['withheld'])) ?></td></tr>
    <tr><td>Paid to GRA in these dates</td><td class="num"><?= e($n($wht['paid'])) ?></td></tr>
    <tr><td><strong>Still to pay to GRA (all dates)</strong></td><td class="num"><strong<?= $wht['owed'] > 0 ? ' style="color:var(--bad)"' : '' ?>><?= e($n($wht['owed'])) ?></strong></td></tr>
</tbody></table></div>
<div class="table-wrap"><table data-nopage>
    <thead><tr><th>Payroll month</th><th class="num">Gross pay</th><th class="num">PAYE (GRA)</th><th class="num">SSNIT staff 5.5%</th><th class="num">SSNIT school 13%</th><th>Status</th></tr></thead>
    <tbody><?php foreach ($runs as $r): ?><tr><td><?= e(date('F Y', strtotime($r['period'] . '-01'))) ?></td><td class="num"><?= e($n($r['gross'])) ?></td><td class="num"><?= e($n($r['paye'])) ?></td><td class="num"><?= e($n($r['ssnit_employee'])) ?></td><td class="num"><?= e($n($r['ssnit_employer'])) ?></td>
        <td><?= e(ucfirst($r['status'])) ?><?= $r['statutory_paid_on'] ? ' · paid to GRA/SSNIT ' . e(fmt_date($r['statutory_paid_on'])) : '' ?></td></tr><?php endforeach; ?>
    <?php if (!$runs): ?><tr><td colspan="6" class="muted">No payroll in these dates.</td></tr><?php endif; ?></tbody>
</table></div>
<?php endif; ?>
