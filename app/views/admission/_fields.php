<?php
/** Shared applicant fields. Expects $levels and optional $app (existing row). */
$v = fn(string $k, $d = '') => old($k, $app[$k] ?? $d);
?>
<fieldset>
    <legend>Child</legend>
    <label class="required">Class applying for
        <select name="level_id" required>
            <option value="">— choose —</option>
            <?php foreach ($levels as $l): ?><option value="<?= (int) $l['id'] ?>"<?= selected($l['id'], $v('level_id')) ?>><?= e($l['name']) ?></option><?php endforeach; ?>
        </select><?= field_error('level_id') ?>
    </label>
    <div class="grid-3">
        <label class="required">First name <input name="first_name" required maxlength="80" value="<?= e($v('first_name')) ?>"><?= field_error('first_name') ?></label>
        <label>Middle name <input name="middle_name" maxlength="80" value="<?= e($v('middle_name')) ?>"></label>
        <label class="required">Surname <input name="last_name" required maxlength="80" value="<?= e($v('last_name')) ?>"><?= field_error('last_name') ?></label>
    </div>
    <div class="grid-3">
        <label class="required">Gender
            <select name="gender" required>
                <option value="">—</option>
                <option value="male"<?= selected('male', $v('gender')) ?>>Male</option>
                <option value="female"<?= selected('female', $v('gender')) ?>>Female</option>
            </select><?= field_error('gender') ?>
        </label>
        <label class="required">Date of birth <input type="date" name="date_of_birth" required max="<?= date('Y-m-d') ?>" value="<?= e($v('date_of_birth')) ?>"><?= field_error('date_of_birth') ?></label>
        <label>Nationality <input name="nationality" maxlength="60" value="<?= e($v('nationality', 'Ghanaian')) ?>"></label>
    </div>
    <div class="grid-3">
        <label>Hometown <input name="hometown" maxlength="80" value="<?= e($v('hometown')) ?>"></label>
        <label>Church / denomination <input name="denomination" maxlength="80" placeholder="e.g. Seventh-day Adventist" value="<?= e($v('denomination')) ?>"></label>
        <label>Previous school <input name="previous_school" maxlength="150" value="<?= e($v('previous_school')) ?>"></label>
    </div>
    <div class="grid-2">
        <label>Allergies <input name="allergies" maxlength="255" placeholder="None" value="<?= e($v('allergies')) ?>"></label>
        <label>Medical notes <input name="medical_notes" maxlength="500" placeholder="Conditions the school should know about" value="<?= e($v('medical_notes')) ?>"></label>
    </div>
    <div class="grid-2">
        <label>Special educational needs
            <select name="sen_category"><option value="">None</option><?php foreach (Admission::SEN as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, (string) $v('sen_category')) ?>><?= e($l) ?></option><?php endforeach; ?></select><?= field_error('sen_category') ?>
        </label>
        <label>Support the child needs <input name="sen_support" maxlength="500" placeholder="e.g. sits in front, large print, hearing aid" value="<?= e($v('sen_support')) ?>"><?= field_error('sen_support') ?></label>
    </div>
</fieldset>
<fieldset>
    <legend>Parent / guardian</legend>
    <div class="grid-2">
        <label class="required">Full name <input name="guardian_name" required maxlength="150" value="<?= e($v('guardian_name')) ?>"><?= field_error('guardian_name') ?></label>
        <label class="required">Relationship to child
            <select name="guardian_relationship" required>
                <?php foreach (Admission::RELATIONSHIPS as $r): ?><option<?= selected($r, $v('guardian_relationship', 'Mother')) ?>><?= e($r) ?></option><?php endforeach; ?>
            </select>
        </label>
    </div>
    <div class="grid-3">
        <label class="required">Phone (MoMo / WhatsApp) <input name="guardian_phone" required placeholder="0244123456" value="<?= e($v('guardian_phone')) ?>"><?= field_error('guardian_phone') ?></label>
        <label>Other phone <input name="guardian_phone_alt" value="<?= e($v('guardian_phone_alt')) ?>"><?= field_error('guardian_phone_alt') ?></label>
        <label>Email <input type="email" name="guardian_email" value="<?= e($v('guardian_email')) ?>"><?= field_error('guardian_email') ?></label>
    </div>
    <div class="grid-2">
        <label>Occupation <input name="guardian_occupation" maxlength="100" value="<?= e($v('guardian_occupation')) ?>"></label>
        <label>Residential / digital address <input name="guardian_address" maxlength="255" placeholder="e.g. GA-123-4567" value="<?= e($v('guardian_address')) ?>"></label>
    </div>
</fieldset>
