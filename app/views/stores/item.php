<?php $fmt = fn($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.'); $mg = can('stores.manage'); ?>
<div class="page-head"><div><p class="breadcrumb"><a href="<?= e(url('stores')) ?>">Store ledger</a></p><h1><?= e($item['name']) ?></h1>
    <p class="muted"><?= e(Registers::STORE_CATEGORIES[$item['category']] ?? '') ?> · in stock <strong><?= e($fmt($balance) . ' ' . $item['unit']) ?></strong><?= $item['is_active'] ? '' : ' · no longer used' ?></p></div>
    <div class="actions"><a class="btn" href="<?= e(url('stores/items/' . $item['id'], ['print' => 1])) ?>" target="_blank">Print ledger card</a></div></div>
<div class="cols">
<div class="card" style="padding:0"><div class="table-wrap"><table>
    <tr><th>Date</th><th>Ref</th><th>From / to</th><th class="num">Received</th><th class="num">Issued</th><th class="num">Balance</th><th>By</th></tr>
    <?php foreach ($moves as $m): $in = $m['kind'] === 'receive' || ($m['kind'] === 'adjust' && (float) $m['qty'] > 0); ?>
    <tr><td><?= e(fmt_date($m['moved_on'])) ?></td><td><?= e($m['reference'] ?? '') ?></td><td><?= e($m['party'] ?? '') ?><?= $m['kind'] === 'adjust' ? ' <small class="muted">correction: ' . e($m['note'] ?? '') . '</small>' : '' ?></td>
        <td class="num"><?= $in ? e($fmt(abs((float) $m['qty']))) : '' ?></td><td class="num"><?= !$in ? e($fmt(abs((float) $m['qty']))) : '' ?></td><td class="num"><strong><?= e($fmt($m['balance'])) ?></strong></td><td><?= e($m['by_name'] ?? '') ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$moves): ?><tr><td colspan="7" class="muted">No entries yet.</td></tr><?php endif; ?>
</table></div></div>
<div>
    <?php if ($mg && $item['is_active']): require __DIR__ . '/_move_form.php'; endif; ?>
    <?php if ($mg): ?>
    <form method="post" action="<?= e(url('stores/items')) ?>" class="card form">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
        <h2>Item details</h2>
        <label class="required">Name<input name="name" maxlength="150" required value="<?= e($item['name']) ?>"></label>
        <div class="grid-2">
            <label>Category<select name="category"><?php foreach (Registers::STORE_CATEGORIES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($item['category'], $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
            <label>Unit<input name="unit" maxlength="30" value="<?= e($item['unit']) ?>"></label>
            <label>Re-order at<input type="number" name="reorder_level" min="0" step="0.01" value="<?= e($fmt($item['reorder_level'])) ?>"></label>
            <label>Kept at<input name="location" maxlength="80" value="<?= e($item['location'] ?? '') ?>"></label>
        </div>
        <label><input type="checkbox" name="is_active" value="1"<?= checked((bool) $item['is_active']) ?>> Still in use</label>
        <button class="btn" type="submit">Save</button>
    </form>
    <?php endif; ?>
</div>
</div>
