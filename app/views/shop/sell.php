<?php $ct = 'shop/admin/sell'; ?>
<div class="page-head"><div><h1>Sell at the counter</h1></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<?php if (!$pupil): ?>
<form method="get" action="<?= e(url('shop/admin/sell')) ?>" class="card" style="max-width:640px"><h2>Find the student</h2>
    <div style="display:flex;gap:.5rem"><input name="q" value="<?= e($q) ?>" placeholder="Name or admission number" autofocus><button class="btn btn-primary" type="submit">Search</button></div>
    <?php foreach ($found as $f): ?><p style="margin:.5rem 0 0"><a href="<?= e(url('shop/admin/sell', ['pupil' => $f['id']])) ?>"><strong><?= e($f['last_name'] . ', ' . $f['first_name']) ?></strong></a> <small class="muted"><?= e($f['admission_no']) ?> · <?= e($f['class_name'] ?? '') ?></small></p><?php endforeach; ?>
    <?php if ($q !== '' && !$found): ?><p class="muted">No student found.</p><?php endif; ?>
</form>
<?php else: ?>
<form method="post" action="<?= e(url('shop/admin/sell')) ?>" class="card form"><?= csrf_field() ?><input type="hidden" name="pupil_id" value="<?= (int) $pupil['id'] ?>">
    <h2><?= e($pupil['first_name'] . ' ' . $pupil['last_name']) ?> <small class="muted"><?= e($pupil['class_name'] ?? '') ?></small> <a class="btn btn-sm btn-ghost" href="<?= e(url('shop/admin/sell')) ?>">Change</a></h2>
    <div class="table-wrap"><table>
        <tr><th>Item</th><th class="num">Price</th><th>Size</th><th>Qty</th><th class="num">In stock</th></tr>
        <?php foreach ($products as $p): $sizes = Shop::sizes($p); ?><tr><td><?= e($p['name']) ?></td><td class="num"><?= e(number_format((float) $p['price'], 2)) ?></td>
            <td><?php if ($sizes): ?><select name="size[<?= (int) $p['id'] ?>]"><option value="">—</option><?php foreach ($sizes as $s): ?><option value="<?= e($s) ?>"><?= e($s) ?></option><?php endforeach; ?></select><?php endif; ?></td>
            <td><input type="number" name="qty[<?= (int) $p['id'] ?>]" min="0" max="<?= Shop::MAX_QTY ?>" value="0" style="width:4.5rem" aria-label="Quantity"></td><td class="num"><?= (int) $p['track_stock'] ? (int) $p['stock_qty'] : '—' ?></td></tr><?php endforeach; ?>
    </table></div>
    <div class="grid-2"><label class="required">Paid by<select name="method"><option value="cash">Cash</option><option value="momo">MoMo</option></select></label><label>Paid by (name)<input name="payer" maxlength="120"></label></div>
    <button class="btn btn-primary" type="submit">Sell and print receipt</button>
</form>
<?php endif; ?>
