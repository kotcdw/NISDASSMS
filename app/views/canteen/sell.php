<?php $ct = 'canteen'; $oldQty = (array) old('qty', []); $onMenu = array_map('intval', array_column($menuToday, 'item_id')); ?>
<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('canteen')) ?>">Canteen</a> /</div><h1><?= $p ? e($p['first_name'] . ' ' . $p['last_name']) : 'Cash sale' ?></h1>
    <?php if ($p): ?><p class="muted"><?= e($p['admission_no']) ?> · <?= e($p['class_name'] ?? '') ?> · wallet <strong><?= e(Fees::money($p['balance'] ?? 0)) ?></strong><?= $p['daily_limit'] !== null ? ' · daily limit ' . e(Fees::money($p['daily_limit'])) . ' (spent ' . e(Fees::money($spent)) . ' today)' : '' ?></p><?php endif; ?></div>
    <?php if ($p): ?><div class="actions"><a class="btn" href="<?= e(url('canteen/wallets/' . $p['id'])) ?>">Top up wallet</a></div><?php endif; ?>
</div>
<?php if ($p && trim((string) $p['allergies']) !== ''): ?><div class="alert alert-error"><strong>Allergies:</strong> <?= e($p['allergies']) ?></div><?php endif; ?>
<form method="post" action="<?= e(url('canteen/sell')) ?>" class="card form">
    <?= csrf_field() ?><input type="hidden" name="pupil_id" value="<?= (int) ($p['id'] ?? 0) ?>">
    <div class="table-wrap"><table>
        <tr><th>Item</th><th class="num">Price</th><th>Allergens</th><th>Stock</th><th style="width:7rem">Qty</th></tr>
        <?php foreach ($items as $i): $clash = $p ? Canteen::allergyClash($p['allergies'], $i['allergens']) : []; ?>
        <tr<?= $clash ? ' style="background:var(--bad-soft)"' : '' ?>>
            <td><strong><?= e($i['name']) ?></strong><?= in_array((int) $i['id'], $onMenu, true) ? ' ' . badge('On today\'s menu', 'info') : '' ?><br><small class="muted"><?= e(Canteen::CATEGORIES[$i['category']]) ?></small></td>
            <td class="num"><?= e(number_format((float) $i['price'], 2)) ?></td>
            <td><small><?= e($i['allergens'] ?? '') ?></small><?= $clash ? ' ' . badge('Allergy!', 'bad') : '' ?></td>
            <td><?= (int) $i['track_stock'] ? (int) $i['stock_qty'] : '<span class="muted">—</span>' ?></td>
            <td><input type="number" name="qty[<?= (int) $i['id'] ?>]" min="0" max="50" value="<?= (int) ($oldQty[$i['id']] ?? 0) ?>" style="width:5.5rem" aria-label="Quantity of <?= e($i['name']) ?>"></td>
        </tr>
        <?php endforeach; ?>
    </table></div>
    <div class="grid-3">
        <label>Paid by<select name="paid_by"><?php if ($p): ?><option value="wallet"<?= selected(old('paid_by', 'wallet'), 'wallet') ?>>Wallet</option><?php endif; ?><option value="cash"<?= selected(old('paid_by'), 'cash') ?>>Cash</option><option value="momo"<?= selected(old('paid_by'), 'momo') ?>>MoMo</option></select></label>
        <label>MoMo transaction ID<input name="reference" maxlength="100" value="<?= e(old('reference')) ?>"></label>
    </div>
    <?php if ($p && trim((string) $p['allergies']) !== ''): ?><label style="font-weight:400"><input type="checkbox" name="allergy_ok" value="1"> The parent has agreed this child may have the items marked “Allergy!”</label><?php endif; ?>
    <button class="btn btn-primary" type="submit">Sell</button>
</form>
