<?php $ol = (array) old('desc', []); $rows = $ol ? array_map(null, $ol, (array) old('unit', []), (array) old('qty', []), (array) old('price', [])) : array_map(fn($i) => [$i['description'], $i['unit'], $i['quantity'], $i['unit_cost']], $items); ?>
<div class="page-center">
<div class="page-head"><div><p class="breadcrumb"><a href="<?= e(url('purchasing')) ?>">Purchase orders</a> › New</p><h1>Purchase order for <?= e($r['req_no']) ?></h1>
    <p class="muted"><?= e($r['title']) ?><?= $r['department'] ? ' · ' . e($r['department']) : '' ?> · approved <?= e(Fees::money($r['amt'])) ?></p></div></div>
<?php if ($need > $quotes): ?><div class="alert alert-warning">Orders of this size need <?= $need ?> quotation<?= $need > 1 ? 's' : '' ?> attached to the requisition (<?= $quotes ?> attached). You can save the draft now, but it cannot be sent until they are attached.</div><?php endif; ?>
<form method="post" action="<?= e(url('purchasing')) ?>" class="card form"><?= csrf_field() ?>
    <input type="hidden" name="requisition_id" value="<?= (int) $r['id'] ?>">
    <div class="grid-2">
        <label class="required">Supplier<select name="supplier_id" required><option value="">Choose…</option><?php foreach ($suppliers as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected(old('supplier_id'), $s['id']) ?>><?= e($s['name']) ?></option><?php endforeach; ?></select><small class="hint"><a href="<?= e(url('suppliers/list')) ?>">Add a supplier</a> if they are not listed.</small></label>
        <label>Expected delivery<input type="date" name="expected_date" value="<?= e(old('expected_date', $r['needed_by'] ?? '')) ?>"></label>
        <label>Deliver to<input name="deliver_to" maxlength="150" value="<?= e(old('deliver_to', 'School stores')) ?>"></label>
        <label>Notes for the supplier<input name="notes" maxlength="500" value="<?= e(old('notes')) ?>"></label>
    </div>
    <div class="table-wrap"><table class="lines" data-nopage>
        <thead><tr><th>Item</th><th style="width:6rem">Unit</th><th style="width:7rem">Quantity</th><th style="width:9rem">Price each (GH₵)</th></tr></thead>
        <tbody><?php for ($i = 0; $i < max(count($rows) + 2, 4); $i++): $x = $rows[$i] ?? ['', '', '', '']; ?>
            <tr><td><input name="desc[]" maxlength="150" value="<?= e($x[0] ?? '') ?>" aria-label="Item <?= $i + 1 ?>"></td>
                <td><input name="unit[]" maxlength="20" value="<?= e($x[1] ?? '') ?>" aria-label="Unit <?= $i + 1 ?>"></td>
                <td><input name="qty[]" inputmode="decimal" value="<?= e(($x[2] ?? '') !== '' ? rtrim(rtrim((string) $x[2], '0'), '.') : '') ?>" aria-label="Quantity <?= $i + 1 ?>" style="text-align:right"></td>
                <td><input name="price[]" inputmode="decimal" value="<?= e($x[3] ?? '') ?>" aria-label="Price <?= $i + 1 ?>" style="text-align:right"></td></tr>
        <?php endfor; ?></tbody>
    </table></div>
    <div class="vf-foot"><span></span><span style="display:flex;gap:.5rem"><a class="btn btn-ghost" href="<?= e(url('requisitions/' . $r['id'])) ?>">Cancel</a><button class="btn btn-primary" type="submit">Save draft order</button></span></div>
</form>
</div>
