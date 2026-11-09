<?php $ct = 'marketing'; ?>
<div class="page-center">
<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('marketing')) ?>">Campaigns</a> /</div><h1>New campaign</h1></div></div>
<form method="post" action="<?= e(url('marketing')) ?>" class="form card" style="max-width:780px"><?= csrf_field() ?>
    <label class="required">Name (for staff)<input name="name" maxlength="120" required value="<?= e(old('name')) ?>" placeholder="e.g. Open day invitation, March"></label>
    <div class="grid-2">
        <label class="required">Send to<select name="audience" required><?php foreach (Crm::AUDIENCES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(old('audience'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
        <label>Stage / level (if needed)<select name="audience_value"><option value="">—</option><optgroup label="Enquiry stage"><?php foreach (Crm::STAGES as $k => [$l]): ?><option value="<?= e($k) ?>"<?= selected(old('audience_value'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></optgroup><optgroup label="Current parents of"><?php foreach (['creche' => 'Creche', 'kg' => 'KG', 'primary' => 'Primary', 'jhs' => 'JHS'] as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(old('audience_value'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></optgroup></select></label>
    </div>
    <label>Phone numbers (for "Phone numbers I type")<textarea name="custom_phones" rows="3" placeholder="0241234567, 0501234567 …"><?= e(old('custom_phones')) ?></textarea></label>
    <label class="required">Message<textarea name="message" rows="4" maxlength="480" required placeholder="e.g. NISDAS Open Day is Saturday 14 March, 9am. Come and see our classrooms, meet the teachers and apply for September."><?= e(old('message')) ?></textarea><small>The school name and a "to stop these messages call…" line are added at the end.</small></label>
    <button class="btn btn-primary" type="submit">Next: check who gets it</button>
</form>
</div>
