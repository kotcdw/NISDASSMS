<?php $n = array_sum(array_column($cart, 'qty')); ?>
<div class="page-head">
    <div><h1>School shop</h1></div>
    <div class="actions"><a class="btn btn-primary" href="<?= e(url('shop/cart')) ?>">Basket<?= $n ? " ($n)" : '' ?></a> <a class="btn" href="<?= e(url('shop/orders')) ?>">My orders</a></div>
</div>
<nav class="seg">
    <a<?= $cat === '' ? ' class="active"' : '' ?> href="<?= e(url('shop')) ?>">All</a>
    <?php foreach (Shop::CATEGORIES as $k => $l): ?><a<?= $cat === $k ? ' class="active"' : '' ?> href="<?= e(url('shop', ['category' => $k])) ?>"><?= e($l) ?></a><?php endforeach; ?>
</nav>
<?php if (!$children): ?><div class="alert alert-warning">Your account is not linked to a child at school yet. Please contact the office.</div><?php endif; ?>
<div class="shop-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:1rem">
<?php foreach ($products as $p): $out = (int) $p['track_stock'] && (int) $p['stock_qty'] <= 0; $sizes = Shop::sizes($p); ?>
    <form method="post" action="<?= e(url('shop/cart')) ?>" class="card" style="display:flex;flex-direction:column;gap:.5rem;margin:0"><?= csrf_field() ?>
        <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="category" value="<?= e($cat) ?>">
        <div style="display:flex;justify-content:space-between;gap:.5rem"><small class="muted"><?= e(Shop::CATEGORIES[$p['category']]) ?></small><?= $out ? badge('Out of stock', 'bad') : ((int) $p['track_stock'] && (int) $p['stock_qty'] <= 3 ? badge('Only ' . (int) $p['stock_qty'] . ' left', 'warn') : '') ?></div>
        <strong style="font-size:1.05rem"><?= e($p['name']) ?></strong>
        <?php if ($p['description']): ?><small class="muted"><?= e($p['description']) ?></small><?php endif; ?>
        <div style="font-size:1.25rem;font-weight:700">GH₵ <?= e(number_format((float) $p['price'], 2)) ?></div>
        <div style="display:flex;gap:.4rem;margin-top:auto">
            <?php if ($sizes): ?><select name="size" required aria-label="Size" style="flex:1"><option value="">Size</option><?php foreach ($sizes as $s): ?><option value="<?= e($s) ?>"><?= e($s) ?></option><?php endforeach; ?></select><?php endif; ?>
            <input type="number" name="qty" value="1" min="1" max="<?= Shop::MAX_QTY ?>" aria-label="Quantity" style="width:4.5rem">
            <button class="btn btn-primary" type="submit"<?= $out || !$children ? ' disabled' : '' ?>>Add</button>
        </div>
    </form>
<?php endforeach; ?>
</div>
<?php if (!$products): ?><div class="card"><p class="muted">Nothing in this section yet.</p></div><?php endif; ?>
