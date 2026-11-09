<?php $school = school_name(); ?>
<div class="idc-sheet">
<?php foreach ($pupils as $p): ?>
    <div class="idc">
        <div class="idc-head">
            <img src="<?= e(logo_url()) ?>" alt="">
            <div><strong><?= e(strtoupper($school)) ?></strong><small>STUDENT IDENTITY CARD</small></div>
        </div>
        <div class="idc-body">
            <div class="idc-photo"><?php if ($p['photo_path']): ?><img src="<?= e(url('pupils/' . $p['id'] . '/photo')) ?>" alt=""><?php else: ?><span>Photo</span><?php endif; ?></div>
            <div class="idc-info">
                <div class="idc-name"><?= e(strtoupper($p['last_name']) . ' ' . $p['first_name']) ?></div>
                <div><span>Adm. no.</span> <?= e($p['admission_no']) ?></div>
                <div><span>Class</span> <?= e($p['class_name']) ?></div>
                <div><span>Year</span> <?= e($year['name'] ?? '') ?></div>
                <div><span>Parent</span> <?= e($p['guardian_phone'] ?? '') ?></div>
            </div>
        </div>
        <div class="idc-foot">If found, please call <?= e(setting('school_phone') ?: 'the school') ?><?= setting('school_address') ? ' · ' . e(setting('school_address')) : '' ?></div>
    </div>
<?php endforeach; ?>
</div>
