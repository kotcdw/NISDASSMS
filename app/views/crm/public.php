<h1>Enquire about admission</h1>
<p class="muted">Creche to JHS 3. Leave your details and the school will call you back<?= setting('school_phone') ? ', or call us on ' . e(setting('school_phone')) : '' ?>.</p>
<div class="card" style="max-width:640px">
    <form method="post" action="<?= e(url('enquire')) ?>" class="form"><?= csrf_field() ?>
        <div style="position:absolute;left:-5000px" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
        <label class="required">Your name<input name="parent_name" maxlength="150" required value="<?= e(old('parent_name')) ?>"></label>
        <label class="required">Phone number<input name="phone" maxlength="20" required inputmode="tel" value="<?= e(old('phone')) ?>" placeholder="024 123 4567"></label>
        <label>Email (optional)<input type="email" name="email" maxlength="150" value="<?= e(old('email')) ?>"></label>
        <div class="grid-2">
            <label>Child's name<input name="child_name" maxlength="150" value="<?= e(old('child_name')) ?>"></label>
            <label>Class<select name="level_id"><option value="">—</option><?php foreach ($levels as $l): ?><option value="<?= (int) $l['id'] ?>"<?= selected(old('level_id'), $l['id']) ?>><?= e($l['name']) ?></option><?php endforeach; ?></select></label>
        </div>
        <label>Your question (optional)<textarea name="message" rows="3" maxlength="1000"><?= e(old('message')) ?></textarea></label>
        <p class="hint">We use these details only to answer your enquiry (<a href="<?= e(url('privacy')) ?>" target="_blank">privacy notice</a>).</p>
        <button class="btn btn-primary" type="submit">Send</button>
    </form>
</div>
<p><a href="<?= e(url('apply')) ?>">Ready to apply? Fill in the online application form →</a></p>
