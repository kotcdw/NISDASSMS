<?php
$cPct = setting('reserve_contingency_pct', ''); $kPct = setting('reserve_capital_pct', ''); $mMonths = (int) setting('reserve_min_months', '3');
$base = $plan > 0 ? $plan : $income;
$minCash = round($avg * $mMonths, 2);
$edit = can('finance.budget');
?>
<div class="page-head">
    <div><h1>Budget</h1></div>
    <div class="actions"></div>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>
<?php require __DIR__ . '/_budget_seg.php'; ?>
<div class="cols">
    <form method="post" action="<?= e(url('finance/budget/reserves')) ?>" class="card form"><?= csrf_field() ?>
        <h2>Reserves for <?= e($year['name'] ?? 'this year') ?></h2>
        <div class="table-wrap"><table data-nopage><tbody>
            <tr><td>Contingency (emergencies)</td><td style="width:8rem"><input name="reserve_contingency_pct" inputmode="decimal" value="<?= e($cPct) ?>" placeholder="%" aria-label="Contingency percent of income"<?= $edit ? '' : ' disabled' ?>></td><td>% of income</td><td class="num"><?= $cPct !== '' && $base > 0 ? e(Fees::money(round($base * (float) $cPct / 100, 2))) : '' ?></td></tr>
            <tr><td>Capital (buildings, bus, computers)</td><td><input name="reserve_capital_pct" inputmode="decimal" value="<?= e($kPct) ?>" placeholder="%" aria-label="Capital percent of income"<?= $edit ? '' : ' disabled' ?>></td><td>% of income</td><td class="num"><?= $kPct !== '' && $base > 0 ? e(Fees::money(round($base * (float) $kPct / 100, 2))) : '' ?></td></tr>
            <tr><td>Minimum cash to keep</td><td><input name="reserve_min_months" inputmode="numeric" value="<?= (int) $mMonths ?>" aria-label="Months of spending"<?= $edit ? '' : ' disabled' ?>></td><td>months of spending</td><td class="num"><?= e(Fees::money($minCash)) ?></td></tr>
        </tbody></table></div>
        <?php if ($edit): ?><button class="btn btn-primary" type="submit">Save reserves</button><?php endif; ?>
    </form>
    <div class="card">
        <h2>Cash now</h2>
        <p style="margin:0 0 .3rem">All money accounts: <strong><?= e(Fees::money($cash)) ?></strong></p>
        <p style="margin:0">Minimum to keep: <strong><?= e(Fees::money($minCash)) ?></strong></p>
        <?php if ($minCash > 0): ?><p class="<?= $cash < $minCash ? 'alert alert-error' : 'alert alert-success' ?>" style="margin-top:.6rem"><?= $cash < $minCash ? 'Cash is below the minimum by ' . e(Fees::money($minCash - $cash)) . '.' : '✓ Cash is above the minimum.' ?></p><?php endif; ?>
    </div>
</div>
