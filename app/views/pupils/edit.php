<?php $v = fn(string $k) => old($k, $pupil[$k] ?? ''); ?>
<div class="page-center wide">
<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('pupils')) ?>">Students</a> / <a href="<?= e(url('pupils/' . $pupil['id'])) ?>"><?= e($pupil['admission_no']) ?></a> /</div><h1>Edit <?= e(full_name($pupil)) ?></h1></div></div>
<form method="post" action="<?= e(url('pupils/' . $pupil['id'])) ?>" enctype="multipart/form-data" class="form">
    <?= csrf_field() ?>
    <section class="form-section" aria-labelledby="fs-name">
        <h2 id="fs-name">Name and birth</h2>
        <p class="section-note">As written on the birth certificate.</p>
        <div class="field-grid field-grid-3">
            <label class="required">First name <input name="first_name" required value="<?= e($v('first_name')) ?>" autocomplete="off"><?= field_error('first_name') ?></label>
            <label>Middle name <input name="middle_name" value="<?= e($v('middle_name')) ?>" autocomplete="off"></label>
            <label class="required">Surname <input name="last_name" required value="<?= e($v('last_name')) ?>" autocomplete="off"><?= field_error('last_name') ?></label>
        </div>
        <div class="field-grid field-grid-3">
            <label class="required">Gender
                <select name="gender"><option value="male"<?= selected('male', $v('gender')) ?>>Male</option><option value="female"<?= selected('female', $v('gender')) ?>>Female</option></select>
            </label>
            <label class="required">Date of birth <input type="date" name="date_of_birth" required value="<?= e($v('date_of_birth')) ?>"><?= field_error('date_of_birth') ?></label>
            <label>House <input name="house" value="<?= e($v('house')) ?>"></label>
        </div>
    </section>

    <section class="form-section" aria-labelledby="fs-bg">
        <h2 id="fs-bg">Background and address</h2>
        <div class="field-grid field-grid-3">
            <label>Nationality <input name="nationality" value="<?= e($v('nationality')) ?>"></label>
            <label>Hometown <input name="hometown" value="<?= e($v('hometown')) ?>"></label>
            <label>Denomination <input name="denomination" value="<?= e($v('denomination')) ?>"></label>
        </div>
        <div class="field-grid">
            <label>Address <input name="address" value="<?= e($v('address')) ?>"></label>
            <label>Previous school <input name="previous_school" value="<?= e($v('previous_school')) ?>"></label>
        </div>
    </section>

    <section class="form-section" aria-labelledby="fs-health">
        <h2 id="fs-health">Health and support</h2>
        <div class="field-grid">
            <label>Allergies <input name="allergies" value="<?= e($v('allergies')) ?>"></label>
            <?php if (can('pupils.sensitive')): ?><label>Medical notes <input name="medical_notes" value="<?= e($v('medical_notes')) ?>"></label><?php endif; ?>
        </div>
        <?php if (can('pupils.sensitive')): ?>
        <div class="field-grid">
            <label>Special educational needs
                <select name="sen_category"><option value="">None</option><?php foreach (Admission::SEN as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, (string) $v('sen_category')) ?>><?= e($l) ?></option><?php endforeach; ?></select><?= field_error('sen_category') ?>
            </label>
            <label>Support the child needs <input name="sen_support" maxlength="500" placeholder="e.g. sits in front, large print, hearing aid" value="<?= e($v('sen_support')) ?>"><?= field_error('sen_support') ?></label>
        </div>
        <?php endif; ?>
    </section>

    <section class="form-section" aria-labelledby="fs-photo">
        <h2 id="fs-photo">Photo</h2>
        <div class="field-grid">
            <label>Photo (JPG/PNG) <input type="file" name="photo" accept=".jpg,.jpeg,.png"></label>
        </div>
    </section>

    <div class="form-foot">
        <a class="btn btn-ghost" href="<?= e(url('pupils/' . $pupil['id'])) ?>">Cancel</a>
        <button class="btn btn-primary" type="submit">Save</button>
    </div>
</form>
</div>
