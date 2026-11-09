<?php $ct = 'shop/admin/report'; $sum = array_sum(array_column($rows, 'amount')); ?>
<div class="page-head"><div><h1>Shop sales</h1></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="card" style="padding:0"><div class="table-wrap"><table>
    <tr><th>Item</th><th class="num">Sold</th><th class="num">GH₵</th></tr>
    <?php foreach ($rows as $r): ?><tr><td><?= e($r['name']) ?></td><td class="num"><?= (int) $r['qty'] ?></td><td class="num"><?= e(number_format((float) $r['amount'], 2)) ?></td></tr><?php endforeach; ?>
    <tr><th colspan="2">Total</th><th class="num"><?= e(number_format($sum, 2)) ?></th></tr>
</table></div></div>
