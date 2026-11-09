<?php $canSubmit = $a['status'] === 'published' && (int) $a['submit_online'] && !($sub && in_array($sub['status'], ['marked', 'returned'], true)); ?>
<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('homework')) ?>">My homework</a> /</div>
    <h1><?= e($a['title']) ?></h1>
    <p class="muted"><?= e($a['subject_name'] ?? '') ?> · <?= e($a['teacher_name'] ?? '') ?> · due <strong><?= e(fmt_date($a['due_on'], 'l j M')) ?></strong></p></div>
</div>
<div class="cols">
<div>
    <div class="card"><h2>What to do</h2>
        <?= $a['instructions'] ? '<p style="white-space:pre-line">' . e($a['instructions']) . '</p>' : '<p class="muted">See your teacher’s instructions in class.</p>' ?>
        <?php if ($a['attachment']): ?><p><a href="<?= e(url('assignments/' . $a['id'] . '/file')) ?>">📎 <?= e($a['attachment_name']) ?></a></p><?php endif; ?>
    </div>
    <?php if ($canSubmit): ?>
    <form method="post" action="<?= e(url('homework/' . $a['id'] . '/submit')) ?>" enctype="multipart/form-data" class="card form">
        <?= csrf_field() ?>
        <h2><?= $sub && $sub['status'] !== 'missing' ? 'Change my work' : 'Hand in my work' ?></h2>
        <label>My answer<textarea name="body" rows="8" maxlength="20000"><?= e($sub['body'] ?? '') ?></textarea></label>
        <label>Photo or PDF of my work (optional)<input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" capture="environment"></label>
        <button class="btn btn-primary" type="submit">Hand in</button>
    </form>
    <?php elseif (!(int) $a['submit_online']): ?>
        <div class="alert alert-info">Hand this in on paper to your teacher.</div>
    <?php endif; ?>
</div>
<div>
    <div class="card"><h2>My work</h2>
        <?php if (!$sub || $sub['status'] === 'missing'): ?><p><?= badge(...Assignments::pupilState(['sub_status' => $sub['status'] ?? null, 'due_on' => $a['due_on']])) ?></p>
        <?php else: ?>
            <p><?= badge(...Assignments::SUB_STATUS[$sub['status']]) ?> <small class="muted"><?= $sub['submitted_at'] ? e(fmt_date($sub['submitted_at'], 'j M H:i')) : '' ?></small></p>
            <?php if ($sub['file']): ?><p><a href="<?= e(url('assignments/' . $a['id'] . '/work/' . $sub['id'])) ?>">📎 <?= e($sub['file_name']) ?></a></p><?php endif; ?>
            <?php if ($sub['status'] === 'returned'): ?>
                <?php if ($sub['score'] !== null): ?><p style="font-size:1.4rem;margin:.3rem 0"><strong><?= e(Results::fmt((float) $sub['score'])) ?></strong><?= $a['max_score'] !== null ? ' / ' . e(Results::fmt((float) $a['max_score'])) : '' ?></p><?php endif; ?>
                <?php if ($sub['feedback']): ?><p style="white-space:pre-line"><strong>Teacher:</strong> <?= e($sub['feedback']) ?></p><?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
</div>
