<?php
$val = fn($k, $d = '') => old($k, $r[$k] ?? $d);
$oldDesc = old('item_desc', null);
if (is_array($oldDesc)) {
    $rowsIn = [];
    foreach ($oldDesc as $i => $d) { $rowsIn[] = ['description' => $d, 'quantity' => old('item_qty')[$i] ?? '', 'unit' => old('item_unit')[$i] ?? '', 'unit_cost' => old('item_cost')[$i] ?? '']; }
} else {
    $rowsIn = $items;
}
$target = min(12, max(5, count($rowsIn) + 2));
while (count($rowsIn) < $target) { $rowsIn[] = ['description' => '', 'quantity' => '', 'unit' => '', 'unit_cost' => '']; }
$quoteAbove = (float) setting('req_require_quote_above', '1000');
?>
<div class="page-head">
    <div><p class="breadcrumb"><a href="<?= e(url('requisitions')) ?>">Requisitions</a> › <?= e($r ? $r['req_no'] : 'New') ?></p><h1><?= e($title) ?></h1></div>
</div>
<form method="post" action="<?= e(url($r ? 'requisitions/' . $r['id'] : 'requisitions')) ?>" enctype="multipart/form-data" class="form">
    <?= csrf_field() ?>
    <div class="cols">
        <div>
            <div class="card">
                <label>What is it for? (title)<input name="title" required maxlength="150" value="<?= e($val('title')) ?>" placeholder="e.g. Science lab materials for JHS 2 practicals"></label>
                <div class="grid-3">
                    <?php $depts = Finance::departments(); $curDept = (string) $val('department'); if ($curDept !== '' && !in_array($curDept, $depts, true)) { $depts[] = $curDept; } ?>
                    <label>Department<select name="department"><option value="">— choose —</option><?php foreach ($depts as $dn): ?><option value="<?= e($dn) ?>"<?= selected($curDept, $dn) ?>><?= e($dn) ?></option><?php endforeach; ?></select></label>
                    <label>Type of expense<select name="category_id" required<?= QuickAddController::attr('fin_category', ['category_kind' => 'expense']) ?>><option value="">— choose —</option><?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected($val('category_id'), $c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></label>
                    <label>Needed by<input type="date" name="needed_by" value="<?= e($val('needed_by')) ?>"></label>
                </div>
                <label>Why is it needed?<textarea name="purpose" rows="3" maxlength="2000"><?= e($val('purpose')) ?></textarea></label>
            </div>
            <div class="card" style="padding:0">
                <div class="table-wrap"><table class="req-items" data-req-items>
                    <thead><tr><th>Item</th><th style="width:90px">Qty</th><th style="width:90px">Unit</th><th style="width:120px">Unit cost (GH₵)</th><th style="width:110px;text-align:right">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($rowsIn as $it): ?>
                    <tr>
                        <td><input name="item_desc[]" maxlength="150" value="<?= e($it['description']) ?>"></td>
                        <td><input name="item_qty[]" inputmode="decimal" value="<?= e($it['quantity'] !== '' ? rtrim(rtrim((string) $it['quantity'], '0'), '.') : '') ?>"></td>
                        <td><input name="item_unit[]" maxlength="20" value="<?= e($it['unit'] ?? '') ?>" placeholder="pcs"></td>
                        <td><input name="item_cost[]" inputmode="decimal" value="<?= e($it['unit_cost'] !== '' ? $it['unit_cost'] : '') ?>"></td>
                        <td style="text-align:right" data-line-total></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr><td colspan="4"><strong>Total requested (GH₵)</strong></td><td style="text-align:right"><strong data-req-total>0.00</strong></td></tr></tfoot>
                </table></div>
            </div>
        </div>
        <div>
            <div class="card">
                <h2>Quotation</h2>
                <label>Quote / pro-forma<input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png"></label>
                            </div>
            <div class="card">
                <button class="btn btn-primary" type="submit" name="submit_now" value="1">Save &amp; send for approval</button>
                <button class="btn" type="submit">Save as draft</button>
                
            </div>
        </div>
    </div>
</form>
