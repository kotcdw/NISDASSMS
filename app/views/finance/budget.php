<?php $edit = can('finance.budget'); $exp = array_filter($rows, fn($r) => $r['kind'] === 'expense'); $inc = array_filter($rows, fn($r) => $r['kind'] === 'income'); ?>
<div class="page-head">
    <div><h1>Budget</h1></div>
    <?php if (count($years) > 1 || ($edit && $year)): ?>
    <div class="actions">
        <?php if (count($years) > 1): ?>
        <form method="get" action="<?= e(url('finance/budget')) ?>" class="list-pick" data-autosubmit>
            <select name="year" aria-label="School year"><?php foreach ($years as $y): ?><option value="<?= (int) $y['id'] ?>"<?= selected($year['id'] ?? 0, $y['id']) ?>><?= e($y['name']) ?></option><?php endforeach; ?></select>
        </form>
        <?php endif; ?>
        <?php if ($edit && $year): ?><button type="button" class="btn btn-primary" data-help="budget-edit" aria-expanded="false">Edit amounts</button><?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>
<?php if (DeptBudget::ready()) { require __DIR__ . '/_budget_seg.php'; } ?>
<?php if ($year): ?>
<?php
// The budget tables, drawn twice: read-only (shown first) and with boxes to type in (behind "Edit amounts").
$budgetTables = function (bool $ed) use ($exp, $inc) {
    foreach (['Expenditure' => $exp, 'Income' => $inc] as $heading => $list): ?>
    <div class="table-wrap"><table>
        <thead><tr><th><?= e($heading) ?></th><th style="text-align:right">Budget (GH₵)</th><th style="text-align:right">Actual</th><?php if ($heading === 'Expenditure'): ?><th style="text-align:right" title="Approved requisitions and supplier bills not yet paid">Committed</th><th style="text-align:right">Left</th><?php endif; ?><th style="min-width:140px">Used</th></tr></thead>
        <tbody>
        <?php $tb = 0; $ta = 0; foreach ($list as $r): $tb += (float) $r['budget']; $ta += $r['actual']; $pct = (int) ($r['pct'] ?? 0); ?>
        <tr>
            <td><?= e($r['name']) ?></td>
            <td style="text-align:right"><?php if ($ed): ?><input name="amount[<?= (int) $r['id'] ?>]" inputmode="decimal" value="<?= $r['budget'] !== null ? e(number_format($r['budget'], 2, '.', '')) : '' ?>" style="width:120px;text-align:right" aria-label="Budget for <?= e($r['name']) ?>"><?php else: ?><?= $r['budget'] !== null ? e(number_format($r['budget'], 2)) : '—' ?><?php endif; ?></td>
            <td style="text-align:right"><?= e(number_format($r['actual'], 2)) ?></td>
            <?php if ($heading === 'Expenditure'): ?>
            <td style="text-align:right"><?= $r['committed'] ? e(number_format($r['committed'], 2)) : '' ?></td>
            <td style="text-align:right"><?= $r['budget'] !== null ? '<span' . (($r['budget'] - $r['actual'] - $r['committed']) < 0 ? ' style="color:var(--bad);font-weight:600"' : '') . '>' . e(number_format($r['budget'] - $r['actual'] - $r['committed'], 2)) . '</span>' : '' ?></td>
            <?php endif; ?>
            <td><?php if ($r['budget']): ?><div style="display:flex;gap:.4rem;align-items:center"><div class="meter<?= $heading === 'Expenditure' && $pct >= 90 ? ' low' : ($pct < 100 ? ' mid' : '') ?>"><span style="width:<?= min(100, $pct) ?>%"></span></div><small><?= $pct ?>%</small></div><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
        <tr><td><strong>Total</strong></td><td style="text-align:right"><strong><?= e(number_format($tb, 2)) ?></strong></td><td style="text-align:right"><strong><?= e(number_format($ta, 2)) ?></strong></td><?php if ($heading === 'Expenditure'): ?><td></td><td></td><?php endif; ?><td></td></tr>
        </tbody>
    </table></div>
    <?php endforeach;
}; ?>
<?php if ($edit): ?>
<style>#budget-edit:not([hidden]) + #budget-view { display: none; }</style>
<form id="budget-edit" method="post" action="<?= e(url('finance/budget')) ?>" class="card" style="padding:0" hidden>
    <?= csrf_field() ?><input type="hidden" name="year" value="<?= (int) $year['id'] ?>">
    <?php $budgetTables(true); ?>
    <div class="gate-actions" style="padding:1rem;margin:0"><button class="btn btn-primary" type="submit">Save budget</button><button type="button" class="btn btn-ghost" data-help="budget-edit">Cancel</button></div>
</form>
<?php endif; ?>
<div id="budget-view" class="card" style="padding:0">
    <?php $budgetTables(false); ?>
</div>
<?php endif; ?>
