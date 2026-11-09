<?php /* Receive / issue / correct form. Expects $all (items) or $item (one item). */ $one = $item ?? null; ?>
<form method="post" action="<?= e(url('stores/moves')) ?>" class="card form" style="align-self:start">
    <?= csrf_field() ?>
    <h2>Receive or issue</h2>
    <?php if ($one): ?><input type="hidden" name="item_id" value="<?= (int) $one['id'] ?>"><input type="hidden" name="back" value="item">
    <?php else: ?><label class="required">Item<select name="item_id" required><option value="">Choose…</option><?php foreach ($all as $i): ?><option value="<?= (int) $i['id'] ?>"<?= selected(old('item_id'), $i['id']) ?>><?= e($i['name']) ?> (<?= e($i['unit']) ?>)</option><?php endforeach; ?></select></label><?php endif; ?>
    <div class="grid-2">
        <label class="required">What<select name="kind"><?php foreach (Registers::STORE_KINDS as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(old('kind', 'issue'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
        <label class="required">Quantity<input type="number" name="qty" step="0.01" required value="<?= e(old('qty')) ?>"></label>
        <label class="required">Date<input type="date" name="moved_on" max="<?= e(date('Y-m-d')) ?>" value="<?= e(old('moved_on', date('Y-m-d'))) ?>" required></label>
        <label>Reference<input name="reference" maxlength="60" value="<?= e(old('reference')) ?>" placeholder="GRN, PO or SRV no."></label>
        <label>Received from / issued to<input name="party" maxlength="150" value="<?= e(old('party')) ?>" placeholder="Supplier, or person / class"></label>
        <label>Unit cost (GH₵)<input type="number" name="unit_cost" min="0" step="0.01" value="<?= e(old('unit_cost')) ?>"></label>
    </div>
    <label>Note<input name="note" maxlength="255" value="<?= e(old('note')) ?>" placeholder="Needed for a correction"></label>
    <button class="btn btn-primary" type="submit">Record</button>
</form>
