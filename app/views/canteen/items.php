<?php $ct = 'canteen/items'; ?>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="xgrid-box" data-xgrid="canteen-items" data-xgrid-file="canteen-items">
<div class="page-head">
    <div><h1>Items &amp; stock</h1></div>
    <div class="actions"><?= export_grid_menu() ?><button type="button" class="btn btn-primary" data-help="new-item" aria-expanded="false">+ Add an item</button></div>
</div>
<form id="new-item" method="post" action="<?= e(url('canteen/items')) ?>" class="card form list-form" hidden><?= csrf_field() ?>
    <h2>Add an item</h2>
    <div class="grid-2"><label>Name<input name="name" required maxlength="80"></label><label>Price (GH₵)<input name="price" inputmode="decimal" required></label>
    <label>Category<select name="category"><?php foreach (Canteen::CATEGORIES as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?></select></label>
    <label>Allergens<input name="allergens" placeholder="e.g. groundnut, milk, egg, wheat, fish"></label><label>Reorder at<input type="number" name="reorder_level" value="0"></label></div>
    <label style="font-weight:400"><input type="checkbox" name="track_stock" value="1"> Count stock (packaged snacks and drinks)</label>
    <div class="gate-actions"><button class="btn btn-primary" type="submit">Add item</button><button type="button" class="btn btn-ghost" data-help="new-item">Cancel</button></div>
</form>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?php foreach (['item' => ['Item', 'text', ''], 'category' => ['Category', 'text', ''], 'sale' => ['On sale', 'text', ''], 'price' => ['Price (GH₵)', 'num', 'num'], 'allergens' => ['Allergens', 'text', ''], 'stock' => ['Stock', 'num', 'num']] as $ck => [$cl, $cy, $cc]): /* no sorting: each row has its edit row below it */ ?><th scope="col" data-c="<?= $ck ?>" data-type="<?= $cy ?>"<?= $cc ? ' class="' . $cc . '"' : '' ?>><?= e($cl) ?></th><?php endforeach; ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($items as $i): $low = (int) $i['track_stock'] && (int) $i['stock_qty'] <= (int) $i['reorder_level']; $ek = 'edit-' . (int) $i['id']; ?>
    <tr<?= (int) $i['is_active'] ? '' : ' style="opacity:.55"' ?>>
        <td data-c="item"><strong><?= e($i['name']) ?></strong></td>
        <td data-c="category"><?= e(Canteen::CATEGORIES[$i['category']]) ?></td>
        <td data-c="sale"><?= (int) $i['is_active'] ? 'Yes' : 'Not sold' ?></td>
        <td data-c="price" class="num"><?= e(number_format((float) $i['price'], 2)) ?></td>
        <td data-c="allergens"><?= e($i['allergens'] ?? '—') ?></td>
        <td data-c="stock" class="num" data-s="<?= (int) $i['track_stock'] ? (int) $i['stock_qty'] : '' ?>"><?= (int) $i['track_stock'] ? ($low ? badge((int) $i['stock_qty'] . ' (reorder)', 'warn') : (int) $i['stock_qty']) : '<span class="muted">not counted</span>' ?></td>
        <td class="xg-act"><button type="button" class="btn btn-sm" data-help="<?= $ek ?>" aria-expanded="false">Edit</button></td>
    </tr>
    <tr id="<?= $ek ?>" class="xg-edit" hidden>
        <td colspan="7" style="white-space:normal">
            <div class="grid-2" style="position:sticky;left:.8rem;max-width:calc(100vw - 3rem);align-items:start">
                <form method="post" action="<?= e(url('canteen/items/' . $i['id'] . '/stock')) ?>" class="form"><?= csrf_field() ?>
                    <h3 style="margin:0 0 .5rem">Change stock</h3>
                    <label>Kind of change<select name="kind"><option value="in">Received</option><option value="adjust">Adjust</option></select></label>
                    <label>Quantity<input type="number" name="qty"></label>
                    <label>Note<input name="note" placeholder="Note"></label>
                    <div class="gate-actions"><button class="btn btn-primary" type="submit">Save</button><button type="button" class="btn btn-ghost" data-help="<?= $ek ?>">Cancel</button></div>
                </form>
                <form method="post" action="<?= e(url('canteen/items')) ?>" class="form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $i['id'] ?>">
                    <h3 style="margin:0 0 .5rem">Edit item</h3>
                    <div class="grid-2"><label>Name<input name="name" value="<?= e($i['name']) ?>" required></label><label>Price<input name="price" value="<?= e($i['price']) ?>" required></label>
                    <label>Category<select name="category"><?php foreach (Canteen::CATEGORIES as $k => $l): ?><option value="<?= $k ?>"<?= selected($i['category'], $k) ?>><?= $l ?></option><?php endforeach; ?></select></label>
                    <label>Allergens<input name="allergens" value="<?= e($i['allergens'] ?? '') ?>" placeholder="e.g. groundnut, egg"></label><label>Reorder at<input type="number" name="reorder_level" value="<?= (int) $i['reorder_level'] ?>"></label></div>
                    <label style="font-weight:400"><input type="checkbox" name="track_stock" value="1"<?= (int) $i['track_stock'] ? ' checked' : '' ?>> Count stock</label>
                    <label style="font-weight:400"><input type="checkbox" name="is_active" value="1"<?= (int) $i['is_active'] ? ' checked' : '' ?>> On sale</label>
                    <div class="gate-actions"><button class="btn btn-primary" type="submit">Save</button><button type="button" class="btn btn-ghost" data-help="<?= $ek ?>">Cancel</button></div>
                </form>
            </div>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $items ? ' hidden' : '' ?> data-xg-empty>No items yet.</p>
</div>
</div>

<div class="xgrid-box" data-xgrid="canteen-stock-moves" data-xgrid-file="canteen-stock-changes" style="margin-top:1.2rem">
<div class="page-head">
    <div><h2>Recent stock changes</h2></div>
    <div class="actions"><?= export_grid_menu() ?></div>
</div>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['date' => ['Date', 'date'], 'item' => ['Item'], 'qty' => ['Change', 'num', 'num'], 'note' => ['Note'], 'by' => ['By']]) ?></tr></thead>
    <tbody>
    <?php foreach ($moves as $m): ?>
    <tr>
        <td data-c="date" data-s="<?= e($m['created_at']) ?>"><?= e(fmt_date($m['created_at'], 'j M')) ?></td>
        <td data-c="item"><?= e($m['name']) ?></td>
        <td data-c="qty" class="num" data-s="<?= (int) $m['qty'] ?>"><?= (int) $m['qty'] > 0 ? '+' : '' ?><?= (int) $m['qty'] ?></td>
        <td data-c="note"><?= e($m['note'] ?? '') ?></td>
        <td data-c="by"><?= e($m['by_name'] ?? '') ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $moves ? ' hidden' : '' ?> data-xg-empty>None yet.</p>
</div>
</div>
