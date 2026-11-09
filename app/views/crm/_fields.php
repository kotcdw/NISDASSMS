<div class="grid-2">
    <label class="required">Parent's name<input name="parent_name" maxlength="150" required value="<?= e(old('parent_name', $e['parent_name'] ?? '')) ?>"></label>
    <label class="required">Phone<input name="phone" maxlength="20" required inputmode="tel" value="<?= e(old('phone', $e['phone'] ?? '')) ?>" placeholder="024 123 4567"></label>
</div>
<div class="grid-2">
    <label>Child's name<input name="child_name" maxlength="150" value="<?= e(old('child_name', $e['child_name'] ?? '')) ?>"></label>
    <label>Child's date of birth<input type="date" name="child_dob" value="<?= e(old('child_dob', $e['child_dob'] ?? '')) ?>"></label>
</div>
<div class="grid-2">
    <label>Class wanted<select name="level_id"><option value="">—</option><?php foreach ($levels as $l): ?><option value="<?= (int) $l['id'] ?>"<?= selected(old('level_id', $e['level_id'] ?? ''), $l['id']) ?>><?= e($l['name']) ?></option><?php endforeach; ?></select></label>
    <label class="required">How they heard about us<select name="source"><?php foreach (Crm::SOURCES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(old('source', $e['source'] ?? 'phone'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
</div>
<div class="grid-2">
    <label>Email<input type="email" name="email" maxlength="150" value="<?= e(old('email', $e['email'] ?? '')) ?>"></label>
    <label>Follow up by<select name="assigned_to"><option value="">—</option><?php foreach ($staff as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected(old('assigned_to', $e['assigned_to'] ?? ''), $s['id']) ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></label>
</div>
<?php if (!$e): ?><label>First follow-up<input type="date" name="next_follow_up" min="<?= e(date('Y-m-d')) ?>" value="<?= e(old('next_follow_up', date('Y-m-d', strtotime('+1 day')))) ?>"></label><?php endif; ?>
<label>Notes<textarea name="notes" rows="2" maxlength="1000"><?= e(old('notes', $e['notes'] ?? '')) ?></textarea></label>
