<?php $ct = $ct ?? ''; $tabs = ['shop/admin' => 'Orders', 'shop/admin/sell' => 'Sell at the counter', 'shop/admin/products' => 'Products & stock', 'shop/admin/report' => 'Sales report']; ?>
<nav class="seg" data-pagenav>
<?php foreach ($tabs as $r => $l): ?><a<?= $ct === $r ? ' class="active"' : '' ?> href="<?= e(url($r)) ?>"><?= e($l) ?></a><?php endforeach; ?>
</nav>
