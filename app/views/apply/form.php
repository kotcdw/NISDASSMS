<h1>Apply for admission<?= $year ? ' · ' . e($year['name']) : '' ?></h1>
<?php if (!$open): ?>
    <div class="card"><p>Online admission is not open at the moment. Please contact the school office<?= setting('school_phone') ? ' on ' . e(setting('school_phone')) : '' ?>.</p></div>
<?php else: ?>
<p class="muted">Creche to JHS 3. Fill in the form below; you will receive a reference number to follow your application. Fields marked <span style="color:var(--bad)">*</span> are required.</p>
<div class="card">
    <form method="post" action="<?= e(url('apply')) ?>" enctype="multipart/form-data" class="form">
        <?= csrf_field() ?>
        <div style="position:absolute;left:-5000px" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
        <?php $app = null; require BASE_PATH . '/app/views/admission/_fields.php'; ?>
        <fieldset>
            <legend>Documents (optional now — you can bring them to the office)</legend>
            
            <div class="grid-3">
                <label>Birth certificate <input type="file" name="birth_certificate" accept=".pdf,.jpg,.jpeg,.png"><?= field_error('birth_certificate') ?></label>
                <label>Passport photo <input type="file" name="passport_photo" accept=".jpg,.jpeg,.png"><?= field_error('passport_photo') ?></label>
                <label>Last school report <input type="file" name="previous_report" accept=".pdf,.jpg,.jpeg,.png"><?= field_error('previous_report') ?></label>
            </div>
        </fieldset>
        <label><input type="checkbox" name="declaration" value="1"<?= checked(old('declaration')) ?>> I confirm that the information given is correct, and I agree to the school storing it to process this application, as explained in the <a href="<?= e(url('privacy')) ?>" target="_blank">privacy notice</a>.</label>
        <?= field_error('declaration') ?>
        <button class="btn btn-primary" type="submit">Submit application</button>
    </form>
</div>
<?php endif; ?>
