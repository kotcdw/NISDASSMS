<?php $ct = 'shop/admin/products'; $openForm = old('name') !== ''; ?>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="xgrid-box" data-xgrid="shop-products" data-xgrid-file="shop-products">
<div class="page-head">
    <div><h1>Products &amp; stock</h1></div>
    <div class="actions"><?= export_grid_menu() ?><button type="button" class="btn btn-primary" data-help="new-product" aria-expanded="<?= $openForm ? 'true' : 'false' ?>">+ Add a product</button></div>
</div>
<form id="new-product" method="post" action="<?= e(url('shop/admin/products')) ?>" class="card form list-form"<?= $openForm ? '' : ' hidden' ?>><?= csrf_field() ?><h2>Add a product</h2>
    <div class="grid-2">
        <label class="required">Name<input name="name" maxlength="120" required value="<?= e(old('name')) ?>"></label>
        <label>Category<select name="category"><?php foreach (Shop::CATEGORIES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(old('category'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
        <label class="required">Price (GH₵)<input name="price" inputmode="decimal" required value="<?= e(old('price')) ?>"></label>
        <label>Sizes (comma separated)<input name="sizes" placeholder="e.g. 6-7,8-9,10-11" value="<?= e(old('sizes')) ?>"></label>
        <label>Description<input name="description" maxlength="255" value="<?= e(old('description')) ?>"></label>
        <label>Reorder when stock is at<input type="number" name="reorder_level" value="5" min="0"></label>
    </div>
    <label style="font-weight:400"><input type="checkbox" name="track_stock" value="1" checked> Count stock (add the stock after saving)</label>
    <div class="gate-actions"><button class="btn btn-primary" type="submit">Add product</button><button type="button" class="btn btn-ghost" data-help="new-product">Cancel</button></div>
</form>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['product' => ['Product'], 'category' => ['Category'], 'sizes' => ['Sizes'], 'shown' => ['In the shop'], 'price' => ['Price (GH₵)', 'num', 'num'], 'stock' => ['Stock', 'num', 'num']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $p): ?>
    <tr<?= (int) $p['is_active'] ? '' : ' style="opacity:.55"' ?>>
        <td data-c="product"><strong><?= e($p['name']) ?></strong></td>
        <td data-c="category"><?= e(Shop::CATEGORIES[$p['category']]) ?></td>
        <td data-c="sizes"><?= e($p['sizes'] ?? '') ?></td>
        <td data-c="shown"><?= (int) $p['is_active'] ? 'Shown' : badge('Hidden', 'neutral') ?></td>
        <td data-c="price" class="num"><?= e(number_format((float) $p['price'], 2)) ?></td>
        <td data-c="stock" class="num" data-s="<?= (int) $p['track_stock'] ? (int) $p['stock_qty'] : '' ?>"><?= (int) $p['track_stock'] ? ((int) $p['stock_qty'] <= (int) $p['reorder_level'] ? badge((string) (int) $p['stock_qty'], 'warn') : (int) $p['stock_qty']) : '<span class="muted">not counted</span>' ?></td>
        <td class="xg-act" style="white-space:nowrap;text-align:left">
            <details><summary class="btn btn-sm btn-ghost">Stock</summary><form method="post" action="<?= e(url('shop/admin/products/' . $p['id'] . '/stock')) ?>" style="display:flex;gap:.3rem;margin-top:.3rem;flex-wrap:wrap"><?= csrf_field() ?><input type="number" name="qty" placeholder="+20 or -2" required style="width:6rem" aria-label="Quantity"><input name="reason" placeholder="Reason" required style="width:9rem" aria-label="Reason"><button class="btn btn-sm" type="submit">OK</button></form></details>
            <details><summary class="btn btn-sm btn-ghost">Edit</summary><form method="post" action="<?= e(url('shop/admin/products')) ?>" class="form" style="margin-top:.3rem;min-width:260px;white-space:normal"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <label>Name<input name="name" value="<?= e($p['name']) ?>" maxlength="120" required></label>
                <label>Category<select name="category"><?php foreach (Shop::CATEGORIES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($p['category'], $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
                <label>Price (GH₵)<input name="price" value="<?= e($p['price']) ?>" inputmode="decimal" required></label>
                <label>Sizes (comma separated)<input name="sizes" value="<?= e($p['sizes'] ?? '') ?>"></label>
                <label>Description<input name="description" value="<?= e($p['description'] ?? '') ?>" maxlength="255"></label>
                <label>Reorder when stock is at<input type="number" name="reorder_level" value="<?= (int) $p['reorder_level'] ?>" min="0"></label>
                <label style="font-weight:400"><input type="checkbox" name="track_stock" value="1"<?= (int) $p['track_stock'] ? ' checked' : '' ?>> Count stock</label>
                <label style="font-weight:400"><input type="checkbox" name="is_active" value="1"<?= (int) $p['is_active'] ? ' checked' : '' ?>> Show in the shop</label>
                <button class="btn btn-sm btn-primary" type="submit">Save</button></form></details>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No products yet.</p>
</div>
</div>
