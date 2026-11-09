<?php $o = fn($k, $d = '') => old($k, $a[$k] ?? $d); ?>
<div class="grid-2">
    <label class="required">Name<input name="name" required maxlength="150" value="<?= e($o('name')) ?>" placeholder="e.g. Dual desk, HP laptop, School bus"></label>
    <label class="required">Category<select name="category" data-asset-cat><?php foreach (Assets::CATEGORIES as $k => [$l, $life]): ?><option value="<?= $k ?>" data-life="<?= $life ?>"<?= selected($o('category', 'furniture'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
    <label class="required">Bought / received on<input type="date" name="purchase_date" required max="<?= e(date('Y-m-d')) ?>" value="<?= e($o('purchase_date', date('Y-m-d'))) ?>"></label>
    <label class="required">Cost or value (GH₵ each)<input name="cost" inputmode="decimal" required value="<?= e($o('cost')) ?>"></label>
    <label>Useful life (years)<input name="life_years" inputmode="numeric" value="<?= e($o('life_years', '10')) ?>" data-asset-life></label>
    <label>Value left at the end (GH₵)<input name="residual" inputmode="decimal" value="<?= e($o('residual', '0')) ?>"></label>
    <label>Location<input name="location" maxlength="120" list="asset-locs" value="<?= e($o('location')) ?>" placeholder="e.g. JHS 1 classroom"></label>
    <label>Held by (staff)<select name="holder_id"><option value="">—</option><?php foreach ($staff as $u): ?><option value="<?= (int) $u['id'] ?>"<?= selected($o('holder_id'), $u['id']) ?>><?= e($u['name']) ?></option><?php endforeach; ?></select></label>
    <label>Serial / registration no.<input name="serial_no" maxlength="80" value="<?= e($o('serial_no')) ?>"></label>
    <label>Paid for by<select name="funded_by"><?php foreach (Assets::FUNDS as $k => $l): ?><option value="<?= $k ?>"<?= selected($o('funded_by', 'school'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
    <label>Supplier<select name="supplier_id"><option value="">—</option><?php foreach ($suppliers as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected($o('supplier_id'), $s['id']) ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></label>
    <label>Condition<select name="condition_state"><?php foreach (Assets::CONDITION as $k => [$l]): ?><option value="<?= $k ?>"<?= selected($o('condition_state', 'good'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
</div>
<label>Notes<input name="notes" maxlength="255" value="<?= e($o('notes')) ?>"></label>
<datalist id="asset-locs"><?php foreach ($locations as $l): ?><option value="<?= e($l) ?>"><?php endforeach; ?></datalist>
