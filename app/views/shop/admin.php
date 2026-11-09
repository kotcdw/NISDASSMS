<?php $ct = 'shop/admin'; ?>
<div class="page-head"><div><h1>School shop</h1></div>
    <div class="actions"><a class="btn btn-primary" href="<?= e(url('shop/admin/sell')) ?>">Sell at the counter</a></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<nav class="seg">
    <?php foreach (Shop::STATUS as $k => [$l]): ?><a href="<?= e(url('shop/admin', ['status' => $k])) ?>"<?= $status === $k ? ' class="active"' : '' ?>><?= e($l) ?></a><?php endforeach; ?>
</nav>
<div class="cols">
<div class="card" style="padding:0"><div class="table-wrap"><table>
    <tr><th>Order</th><th>Student</th><th class="num">Items</th><th class="num">Total</th><th>How</th></tr>
    <?php foreach ($rows as $o): ?><tr><td><a href="<?= e(url('shop/admin/orders/' . $o['id'])) ?>"><strong><?= e($o['order_no']) ?></strong></a><br><small class="muted"><?= e(fmt_date($o['created_at'], 'j M H:i')) ?></small></td>
        <td><?= e($o['last_name'] . ', ' . $o['first_name']) ?><br><small class="muted"><?= e($o['admission_no']) ?></small></td><td class="num"><?= (int) $o['items'] ?></td><td class="num"><?= e(number_format((float) $o['total'], 2)) ?></td><td><small><?= $o['channel'] === 'online' ? 'Online' : 'Counter' ?></small></td></tr><?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="5" class="muted">No orders here.</td></tr><?php endif; ?>
</table></div></div>
<div class="card"><h2>Low stock</h2>
    <?php foreach ($low as $p): ?><p style="margin:0 0 .35rem;display:flex;justify-content:space-between"><span><?= e($p['name']) ?></span><strong<?= (int) $p['stock_qty'] <= 0 ? ' style="color:#b42318"' : '' ?>><?= (int) $p['stock_qty'] ?></strong></p><?php endforeach; ?>
    <?php if (!$low): ?><p class="muted">All items above their reorder level.</p><?php endif; ?>
</div>
</div>
<?= pager_links($pager) ?>
