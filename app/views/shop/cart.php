<?php $total = array_sum(array_column($lines, 'total')); ?>
<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('shop')) ?>">School shop</a> /</div><h1>Your basket</h1></div></div>
<?php if (!$lines): ?><div class="card"><p class="muted">Your basket is empty. <a href="<?= e(url('shop')) ?>">Go to the shop</a></p></div><?php else: ?>
<div class="cols">
<form method="post" action="<?= e(url('shop/cart')) ?>" class="card" style="padding:0"><?= csrf_field() ?><div class="table-wrap"><table>
    <tr><th>Item</th><th class="num">Price</th><th>Qty</th><th class="num">Total</th></tr>
    <?php foreach ($lines as $l): ?><tr><td><strong><?= e($l['p']['name']) ?></strong><?= $l['size'] !== '' ? '<br><small class="muted">Size ' . e($l['size']) . '</small>' : '' ?></td>
        <td class="num"><?= e(number_format((float) $l['p']['price'], 2)) ?></td><td><input type="number" name="qty[<?= e($l['key']) ?>]" value="<?= (int) $l['qty'] ?>" min="0" max="<?= Shop::MAX_QTY ?>" style="width:4.5rem" aria-label="Quantity"></td><td class="num"><?= e(number_format($l['total'], 2)) ?></td></tr><?php endforeach; ?>
    <tr><th colspan="3">Total</th><th class="num">GH₵ <?= e(number_format($total, 2)) ?></th></tr>
</table></div><div style="padding:.8rem 1rem"><button class="btn btn-sm" type="submit">Update basket</button> <small class="muted">Set a quantity to 0 to remove it.</small></div></form>
<form method="post" action="<?= e(url('shop/checkout')) ?>" class="card form"><?= csrf_field() ?><h2>Place the order</h2>
    <label class="required">For which child?<select name="pupil_id" required><?php if (count($children) !== 1): ?><option value="">— choose —</option><?php endif; ?><?php foreach ($children as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['first_name'] . ' ' . $c['last_name'] . ($c['class_name'] ? ' (' . $c['class_name'] . ')' : '')) ?></option><?php endforeach; ?></select></label>
    <p class="muted">Next you pay GH₵ <?= e(number_format($total, 2)) ?> online with Mobile Money. The items are kept for you for <?= Shop::HOLD_DAYS ?> days; you get an SMS when the order is ready to collect.</p>
    <button class="btn btn-primary" type="submit"<?= $children ? '' : ' disabled' ?>>Order and pay</button>
</form>
</div>
<?php endif; ?>
