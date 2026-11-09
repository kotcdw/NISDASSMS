<?php $tools = array_filter([
    ['setup/wizard', 'Configure the school', 'setup.manage'], ['promotion', 'Year-end promotion', 'promotion.manage'], ['imports', 'Import from Excel', 'imports.manage'],
    ['golive', 'Go-live checklist', 'golive.manage'], ['practice', 'Practice copy', 'users.manage'],
    ['portal', 'Parent portal (preview)', Auth::role() === 'super_admin' ? 'portal.parent' : '-'], ['learn', 'Student quizzes (preview)', Auth::role() === 'super_admin' ? 'quiz.take' : '-'],
], fn($t) => $t[2] !== '-' && can($t[2]));
$sp = SetupWizard::progress(); ?>
<div class="page-head">
    <div><h1>School setup</h1></div>
    <div class="actions">
        
        <?php if ($tools): ?>
        <details class="menu-btn"><summary class="btn">Tools ▾</summary><div>
            <?php foreach ($tools as [$r, $l]): ?><a href="<?= e(url($r)) ?>"><?= e($l) ?><?= $r === 'setup/wizard' && $sp && $sp[0] < $sp[1] ? ' <small class="muted">(' . (int) $sp[0] . ' of ' . (int) $sp[1] . ' done)</small>' : '' ?></a><?php endforeach; ?>
        </div></details>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="card form-center">
    <form method="post" action="<?= e(url('setup/school')) ?>" class="form">
        <?= csrf_field() ?>
        <label class="required">School name <input name="school_name" required value="<?= e(old('school_name', $s['school_name'] ?? '')) ?>"><?= field_error('school_name') ?></label>
        <label>Motto <input name="school_motto" value="<?= e(old('school_motto', $s['school_motto'] ?? '')) ?>"></label>
        <label>Postal / digital address <input name="school_address" placeholder="e.g. P.O. Box …, GA-123-4567" value="<?= e(old('school_address', $s['school_address'] ?? '')) ?>"></label>
        <div class="grid-2">
            <label>Phone <input name="school_phone" value="<?= e(old('school_phone', $s['school_phone'] ?? '')) ?>"></label>
            <label>Email <input type="email" name="school_email" value="<?= e(old('school_email', $s['school_email'] ?? '')) ?>"><?= field_error('school_email') ?></label>
        </div>
        <label>GRA TIN<input name="school_tin" maxlength="20" placeholder="e.g. C0012345678" value="<?= e(old('school_tin', $s['school_tin'] ?? '')) ?>"><?= field_error('school_tin') ?></label>
        <label class="required">Admission number prefix <input name="admission_prefix" maxlength="8" value="<?= e(old('admission_prefix', $s['admission_prefix'] ?? 'NIS')) ?>"><?= field_error('admission_prefix') ?></label>
        <label><input type="checkbox" name="online_admission_open" value="1"<?= checked(($s['online_admission_open'] ?? '1') === '1') ?>> Online applications</label>
        <label><input type="checkbox" name="sabbath_guard" value="1"<?= checked(($s['sabbath_guard'] ?? '1') === '1') ?>> Sabbath event warning</label>
        <button class="btn btn-primary" type="submit">Save</button>
    </form>
</div>

<div class="card form-center">
    <h2>School logo / crest</h2>
    <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap">
        <img src="<?= e(logo_url()) ?>" alt="School crest" style="width:80px;height:80px;object-fit:contain;border:1px solid var(--line);border-radius:10px;background:#fff">
        <form method="post" action="<?= e(url('setup/logo')) ?>" enctype="multipart/form-data" class="filters" style="margin:0">
            <?= csrf_field() ?>
            <label>New crest <input type="file" name="logo" accept=".png,.jpg,.jpeg" required></label>
            <button class="btn btn-primary" type="submit">Upload</button>
        </form>
        <?php if (setting('school_logo')): ?>
        <form method="post" action="<?= e(url('setup/logo')) ?>"><?= csrf_field() ?><input type="hidden" name="remove" value="1"><button class="btn btn-ghost" type="submit">Use the built-in crest again</button></form>
        <?php endif; ?>
    </div>
</div>

<div class="card form-center">
    <h2>Background picture</h2>
    <?php $bgm = setting('bg_mode', 'school'); ?>
    <form method="post" action="<?= e(url('setup/background')) ?>" enctype="multipart/form-data" class="form">
        <?= csrf_field() ?>
        <div style="display:flex;gap:1.25rem;flex-wrap:wrap;align-items:flex-start">
            <?php if (bg_art_url()): ?><img src="<?= e(bg_art_url()) ?>" alt="Current background picture" style="width:220px;max-width:100%;border-radius:10px;border:1px solid var(--line)"><?php endif; ?>
            <div style="flex:1 1 260px">
                <label style="font-weight:400"><input type="radio" name="bg_mode" value="school"<?= checked($bgm === 'school') ?>> School photo</label>
                <label style="font-weight:400"><input type="radio" name="bg_mode" value="illustration"<?= checked($bgm === 'illustration') ?>> Illustration</label>
                <label style="font-weight:400"><input type="radio" name="bg_mode" value="photo"<?= checked($bgm === 'photo') ?><?= setting('bg_photo') ? '' : ' disabled' ?>> Our own photo</label>
                <label style="font-weight:400"><input type="radio" name="bg_mode" value="off"<?= checked($bgm === 'off') ?>> None</label>
                <label>Upload photo<input type="file" name="bg_photo" accept=".png,.jpg,.jpeg"></label>
                <button class="btn btn-primary" type="submit">Save</button>
            </div>
        </div>
    </form>
</div>
