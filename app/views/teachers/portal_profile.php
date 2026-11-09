<div class="page-head">
    <div><p class="breadcrumb"><a href="<?= e(url(Auth::role() === 'pupil' ? 'student' : 'portal')) ?>"><?= Auth::role() === 'pupil' ? 'My learning' : (Auth::role() === 'parent' ? 'My children' : 'Parent portal (preview)') ?></a></p><h1><?= e($teacher['name']) ?></h1></div>
    <div class="actions"><a class="btn" href="<?= e(url('conversations/create')) ?>">Send a message</a></div>
</div>
<div class="cols">
    <div class="card">
        <h2>Teacher profile</h2>
        <p><strong>Position:</strong> <?= e($teacher['position'] ?: 'Teacher') ?></p>
        <?php if (!empty($teacher['qualification'])): ?><p><strong>Qualification:</strong> <?= e(Payroll::QUALIFICATION[$teacher['qualification']][0] ?? $teacher['qualification']) ?></p><?php endif; ?>
        <?php if ($classNames): ?><p><strong>Class teacher for:</strong> <?= e(implode(', ', $classNames)) ?></p><?php endif; ?>
    </div>
    <div class="card">
        <h2>Subjects and classes</h2>
        <?php foreach ($assignments as $assignment): ?><p style="margin:0 0 .45rem"><strong><?= e($assignment['subject_name']) ?></strong><br><small class="muted"><?= e($assignment['class_name']) ?></small></p><?php endforeach; ?>
        <?php if (!$assignments && !$classNames): ?><p class="muted">No subject details are available for this class.</p><?php endif; ?>
    </div>
</div>
