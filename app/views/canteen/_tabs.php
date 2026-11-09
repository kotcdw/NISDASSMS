<?php $ct = $ct ?? ''; $tabs = ['canteen' => ['Counter', 'canteen.sell'], 'canteen/wallets' => ['Wallets', 'canteen.sell'], 'canteen/menu' => ['Menu', 'canteen.sell'], 'canteen/served' => ['Meals served', 'canteen.sell'], 'canteen/plans' => ['Meal plans', 'canteen.manage'], 'canteen/items' => ['Items & stock', 'canteen.manage'], 'canteen/report' => ['Report', 'canteen.manage']]; ?>
<nav class="seg" data-pagenav>
<?php foreach ($tabs as $r => [$l, $perm]): if (!can($perm)) continue; ?><a<?= $ct === $r ? ' class="active"' : '' ?> href="<?= e(url($r)) ?>"><?= e($l) ?></a><?php endforeach; ?>
</nav>
