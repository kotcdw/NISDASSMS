<?php /** Inputs for adding/editing a question. Expects $field (row or null). */ ?>
<label class="required">Question / heading <input name="label" required maxlength="190" value="<?= e($field['label'] ?? '') ?>"></label>
<div class="grid-2">
    <label>Type
        <select name="type">
            <?php foreach (FormDef::TYPES as $k => $label): ?><option value="<?= $k ?>"<?= selected($k, $field['type'] ?? 'text') ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
    </label>
    <label style="padding-top:1.6rem"><input type="checkbox" name="is_required" value="1"<?= checked($field['is_required'] ?? 0) ?>> Required</label>
</div>
<label>Options — one per line (dropdown, multiple choice, checkboxes) <textarea name="options" rows="3"><?= e($field['options'] ?? '') ?></textarea></label>
<label>Help text (optional) <input name="help_text" maxlength="255" value="<?= e($field['help_text'] ?? '') ?>"></label>
