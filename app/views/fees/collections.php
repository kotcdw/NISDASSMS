<div class="xgrid-box" data-xgrid="fee-collections" data-xgrid-file="money-collected-by-class">
<div class="page-head">
    <div><h1>Money collected</h1></div>
    <div class="actions">
        <?php if ($terms): ?>
        <form method="get" action="<?= e(url('fees/collections')) ?>" class="list-pick" data-autosubmit>
            <select name="term" aria-label="Term"><?php foreach ($terms as $t): ?><option value="<?= (int) $t['id'] ?>"<?= selected($term['id'] ?? 0, $t['id']) ?>><?= e($t['name'] . ' · ' . $t['year_name']) ?></option><?php endforeach; ?></select>
        </form>
        <?php endif; ?>
        <?= export_grid_menu() ?>
    </div>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['class' => ['Class'], 'bills' => ['Bills', 'num', 'num'], 'billed' => ['Billed (GH₵)', 'num', 'num'], 'paid' => ['Collected (GH₵)', 'num', 'num'], 'out' => ['Outstanding (GH₵)', 'num', 'num'], 'pct' => ['Collected %', 'num', 'num']]) ?></tr></thead>
    <tbody>
    <?php foreach ($byClass as $c): $cp = $c['billed'] > 0 ? round($c['paid'] * 100 / $c['billed']) : 0; ?>
    <tr>
        <td data-c="class"><?= e($c['name']) ?></td>
        <td data-c="bills" class="num"><?= (int) $c['bills'] ?></td>
        <td data-c="billed" class="num" data-s="<?= e((string) $c['billed']) ?>"><?= e(number_format((float) $c['billed'], 2)) ?></td>
        <td data-c="paid" class="num" data-s="<?= e((string) $c['paid']) ?>"><?= e(number_format((float) $c['paid'], 2)) ?></td>
        <td data-c="out" class="num" data-s="<?= e((string) $c['outstanding']) ?>"><?= e(number_format((float) $c['outstanding'], 2)) ?></td>
        <td data-c="pct" class="num" data-s="<?= $cp ?>"><?= $cp ?>%</td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $byClass ? ' hidden' : '' ?> data-xg-empty>No bills for this term.</p>
</div>
</div>
<div class="cols" style="margin-top:1rem">
    <div class="card">
        <h2>Collected by method</h2>
        <?php foreach ($byMethod as $m): ?><p style="display:flex;justify-content:space-between;margin:0 0 .4rem"><span><?= e(Fees::PAID_BY[$m['method']] ?? $m['method']) ?> <small class="muted">(<?= (int) $m['n'] ?>)</small></span><strong><?= e(Fees::money($m['amt'])) ?></strong></p><?php endforeach; ?>
        <?php if (!$byMethod): ?><p class="muted">No payments yet.</p><?php endif; ?>
    </div>
    <div class="card">
        <h2>Billed by item</h2>
        <?php foreach ($byItem as $i): ?><p style="display:flex;justify-content:space-between;margin:0 0 .4rem"><span><?= e($i['description']) ?></span><span><?= e(number_format((float) $i['gross'] - (float) $i['discount'], 2)) ?><?= (float) $i['discount'] > 0 ? ' <small class="muted">(−' . e(number_format((float) $i['discount'], 2)) . ')</small>' : '' ?></span></p><?php endforeach; ?>
        <?php if (!$byItem): ?><p class="muted">Nothing billed yet.</p><?php endif; ?>
    </div>
</div>
