<?php $ct = 'canteen/report'; $sum = array_sum(array_map(fn($r) => (float) $r['total'], $byDay)); ?>
<div class="page-head"><div><h1>Canteen report</h1></div>
    <div class="actions">
        <?= export_grid_menu('Export', 'canteen-items') ?>
        <details class="menu-btn"><summary class="btn">More ▾</summary><div>
            <?= export_links(url('canteen/report', ['from' => $from, 'to' => $to, 'csv' => 1]), 'Sales per day —') ?>
        </div></details>
    </div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<?php if (count($byDay) > 1): ?><div class="card"><h2>Sales per day</h2><?= Charts::bars(array_map(fn($r) => [date('j M', strtotime($r['d'])), (float) $r['total'], date('D j M', strtotime($r['d'])) . ': ' . Fees::money($r['total'])], $byDay), ['format' => fn($v) => Fees::money($v), 'label' => 'Canteen sales per day', 'height' => 170]) ?></div><?php endif; ?>
<div class="xgrid-box" data-xgrid="canteen-items" data-xgrid-file="canteen-sales-by-item-<?= e($from) ?>-to-<?= e($to) ?>">
<div class="page-head"><div><h2>By item</h2></div></div>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['item' => ['Item'], 'sold' => ['Sold', 'num', 'num'], 'amount' => ['Amount', 'num', 'num']]) ?></tr></thead>
    <tbody>
    <?php foreach ($byItem as $r): ?>
    <tr>
        <td data-c="item"><?= e($r['name']) ?></td>
        <td data-c="sold" class="num"><?= (int) $r['qty'] ?></td>
        <td data-c="amount" class="num" data-s="<?= e((string) (float) $r['amount']) ?>"><?= e(number_format((float) $r['amount'], 2)) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $byItem ? ' hidden' : '' ?> data-xg-empty>No sales in this period.</p>
</div>
</div>
