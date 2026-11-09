<?php
$docTitle = 'FINANCE REPORT'; $docSub = date('F Y', strtotime($from)) . ($closed ? ' · month closed' : ' · month NOT closed');
$n = fn($v) => number_format((float) $v, 2);
$inc = array_filter($cats, fn($c) => $c['kind'] === 'income'); $exp = array_filter($cats, fn($c) => $c['kind'] === 'expense');
$sum = fn(array $list, array $vals) => array_sum(array_map(fn($c) => $vals[(int) $c['id']] ?? 0, $list));
?>
<div class="card-page doc-page board-pack" style="max-width:none">
    <?php require dirname(__DIR__) . '/fees/_doc_head.php'; ?>
    <p class="rc-key" style="margin-top:0">Period <?= e(fmt_date($from)) ?> – <?= e(fmt_date($to)) ?> · year to date from <?= e(fmt_date($ytdFrom)) ?> (<?= e($year['name'] ?? '') ?>)</p>

    <h3>1. Cash and bank</h3>
    <table class="rc-scores">
        <thead><tr><th class="l">Account</th><th>Opening</th><th>Money in</th><th>Money out</th><th>Closing</th><th>Reconciled to</th></tr></thead>
        <tbody><?php $t = [0, 0, 0, 0]; foreach ($accounts as $a): $t[0] += $a['open']; $t[1] += $a['in']; $t[2] += $a['out']; $t[3] += $a['close']; ?><tr>
            <td class="l"><?= e($a['name']) ?> <small>(<?= e(Finance::ACCOUNT_KINDS[$a['kind']] ?? $a['kind']) ?>)</small></td><td style="text-align:right"><?= $n($a['open']) ?></td><td style="text-align:right"><?= $n($a['in']) ?></td><td style="text-align:right"><?= $n($a['out']) ?></td><td style="text-align:right"><strong><?= $n($a['close']) ?></strong></td>
            <td><?= in_array($a['kind'], ['bank', 'momo', 'online'], true) ? ($a['rec'] ? e(fmt_date($a['rec'], 'd/m/Y')) . ($a['rec'] < $to ? ' ⚠' : '') : 'Never ⚠') : '—' ?></td>
        </tr><?php endforeach; ?></tbody>
        <tfoot><tr><td class="l"><strong>Total</strong></td><?php foreach ($t as $v): ?><td style="text-align:right"><strong><?= $n($v) ?></strong></td><?php endforeach; ?><td></td></tr></tfoot>
    </table>

    <h3>2. Income and expenditure</h3>
    <table class="rc-scores">
        <thead><tr><th class="l">Category</th><th><?= e(date('M Y', strtotime($from))) ?></th><th>Year to date</th><th>Budget for the year</th><th>Budget used</th></tr></thead>
        <tbody>
            <tr><td class="l" colspan="5"><strong>Income</strong></td></tr>
            <?php foreach ($inc as $c): $mv = $month[(int) $c['id']] ?? 0; $yv = $ytd[(int) $c['id']] ?? 0; $bv = $budgets[(int) $c['id']] ?? null; if (!$mv && !$yv && !$bv) continue; ?><tr><td class="l"><?= e($c['name']) ?></td><td style="text-align:right"><?= $n($mv) ?></td><td style="text-align:right"><?= $n($yv) ?></td><td style="text-align:right"><?= $bv !== null ? $n($bv) : '—' ?></td><td style="text-align:right"><?= $bv ? round($yv * 100 / $bv) . '%' : '—' ?></td></tr><?php endforeach; ?>
            <tr><td class="l"><strong>Total income</strong></td><td style="text-align:right"><strong><?= $n($sum($inc, $month)) ?></strong></td><td style="text-align:right"><strong><?= $n($sum($inc, $ytd)) ?></strong></td><td colspan="2"></td></tr>
            <tr><td class="l" colspan="5"><strong>Expenditure</strong></td></tr>
            <?php foreach ($exp as $c): $mv = $month[(int) $c['id']] ?? 0; $yv = $ytd[(int) $c['id']] ?? 0; $bv = $budgets[(int) $c['id']] ?? null; if (!$mv && !$yv && !$bv) continue; $over = $bv && $yv > $bv; ?><tr><td class="l"><?= e($c['name']) ?></td><td style="text-align:right"><?= $n($mv) ?></td><td style="text-align:right"><?= $n($yv) ?></td><td style="text-align:right"><?= $bv !== null ? $n($bv) : '—' ?></td><td style="text-align:right<?= $over ? ';font-weight:700' : '' ?>"><?= $bv ? round($yv * 100 / $bv) . '%' . ($over ? ' ⚠' : '') : '—' ?></td></tr><?php endforeach; ?>
            <tr><td class="l"><strong>Total expenditure</strong></td><td style="text-align:right"><strong><?= $n($sum($exp, $month)) ?></strong></td><td style="text-align:right"><strong><?= $n($sum($exp, $ytd)) ?></strong></td><td colspan="2"></td></tr>
        </tbody>
        <tfoot><tr><td class="l"><strong>Surplus / (deficit)</strong></td><td style="text-align:right"><strong><?= $n($sum($inc, $month) - $sum($exp, $month)) ?></strong></td><td style="text-align:right"><strong><?= $n($sum($inc, $ytd) - $sum($exp, $ytd)) ?></strong></td><td colspan="2"></td></tr></tfoot>
    </table>

    <h3>3. School fees</h3>
    <table class="rc-info" style="width:70%">
        <?php if ($term): ?><tr><th><?= e($term['name']) ?> billed</th><td style="text-align:right"><?= $n($fees['billed']) ?></td></tr>
        <tr><th>Collected so far</th><td style="text-align:right"><?= $n($fees['paid']) ?> (<?= $fees['billed'] > 0 ? round($fees['paid'] * 100 / $fees['billed']) : 0 ?>%)</td></tr><?php endif; ?>
        <?php foreach ($aging as $k => $v): ?><tr><th>Owed — <?= e($k) ?></th><td style="text-align:right"><?= $n($v) ?></td></tr><?php endforeach; ?>
        <tr><th>Total owed by parents</th><td style="text-align:right"><strong><?= $n(array_sum($aging)) ?></strong></td></tr>
        <?php if ($x): ?><tr><th>Family credit held (paid ahead)</th><td style="text-align:right"><?= $n($x['credit']) ?></td></tr>
        <tr><th>Credit notes approved this month</th><td style="text-align:right"><?= (int) $x['cn']['n'] ?> · <?= $n($x['cn']['amt']) ?></td></tr>
        <tr><th>Refunds paid this month</th><td style="text-align:right"><?= (int) $x['refunds']['n'] ?> · <?= $n($x['refunds']['amt']) ?></td></tr><?php endif; ?>
    </table>

    <h3>4. What the school owes</h3>
    <table class="rc-info" style="width:70%">
        <?php if ($x): ?><tr><th>Supplier bills not yet paid</th><td style="text-align:right"><?= $n($x['owed']) ?></td></tr>
        <tr><th>…of which overdue</th><td style="text-align:right"><?= $n($x['overdue']) ?></td></tr>
        <tr><th>Withholding tax to pay to GRA</th><td style="text-align:right"><?= $n($x['wht']) ?></td></tr><?php endif; ?>
        <tr><th>Approved requisitions not yet paid</th><td style="text-align:right"><?= (int) $reqOpen['n'] ?> · <?= $n($reqOpen['amt']) ?></td></tr>
    </table>

    <?php if ($x): ?>
    <?php if ($x['petty']): ?>
    <h3>5. Petty cash</h3>
    <table class="rc-scores">
        <thead><tr><th class="l">Box</th><th class="l">Held by</th><th>Float</th><th>Cash in box</th><th>Vouchers to check</th></tr></thead>
        <tbody><?php foreach ($x['petty'] as $p): ?><tr><td class="l"><?= e($p['name']) ?></td><td class="l"><?= e($p['custodian_name'] ?? '') ?></td><td style="text-align:right"><?= $n($p['float_amount']) ?></td><td style="text-align:right"><?= $n($p['balance']) ?></td><td style="text-align:right"><?= $n($p['unclaimed']) ?></td></tr><?php endforeach; ?></tbody>
    </table>
    <?php endif; ?>
    <h3><?= $x['petty'] ? '6' : '5' ?>. Fixed assets</h3>
    <?php $cost = 0; $nbv = 0; foreach ($x['assets'] as $a) { if (in_array($a['status'], ['disposed', 'lost'], true) && $a['disposed_on'] <= $to) continue; $cost += (float) $a['cost']; $nbv += Assets::value($a, $to)['nbv']; } ?>
    <table class="rc-info" style="width:70%">
        <tr><th>Assets in use — cost</th><td style="text-align:right"><?= $n($cost) ?></td></tr>
        <tr><th>Value after wear at <?= e(fmt_date($to)) ?></th><td style="text-align:right"><?= $n($nbv) ?></td></tr>
        <tr><th>Added this month</th><td style="text-align:right"><?= count($x['newAssets']) ?> · <?= $n(array_sum(array_column($x['newAssets'], 'cost'))) ?></td></tr>
        <tr><th>Disposed / lost this month</th><td style="text-align:right"><?= count($x['gone']) ?></td></tr>
    </table>
    <?php endif; ?>
    <div class="rc-sign"><div><span></span>Bursar / Accountant</div><div><span></span>Head Teacher</div><div><span></span>School Manager / Board Chair</div></div>
    <p class="rc-key">Printed <?= e(date('d M Y H:i')) ?>.</p>
</div>
