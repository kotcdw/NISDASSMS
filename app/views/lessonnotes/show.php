<?php [$sl, $st] = LessonNotes::STATUS[$n['status']]; $week = (int) $n['week_no']; ?>
<div class="page-center">
<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('lesson-notes', ['term' => $n['term_id'], 'week' => $week])) ?>">Lesson notes</a> / Week <?= $week ?> · <?= e(LessonNotes::weekRange($term, $week)) ?></div>
    <h1><?= e($n['class_name']) ?> · <?= e($n['subject_name']) ?> <?= badge($sl, $st) ?></h1></div>
    <div class="actions">
        <?php if ($canEdit): ?><a class="btn" href="<?= e(url('lesson-notes/' . $n['id'])) ?>">Edit</a><?php endif; ?>
        <a class="btn btn-ghost" href="<?= e(url('lesson-notes/' . $n['id'] . '/print')) ?>" target="_blank" rel="noopener">Print</a>
    </div>
</div>
<?php if ($n['review_comment'] && in_array($n['status'], ['approved', 'returned'], true)): ?><div class="alert alert-<?= $n['status'] === 'approved' ? 'success' : 'warning' ?>"><strong><?= $n['status'] === 'approved' ? 'Approved' : 'Returned' ?> by <?= e($n['reviewer_name'] ?? '') ?>:</strong> <?= e($n['review_comment']) ?></div><?php endif; ?>

<?php if ($canVet): ?>
<form method="post" action="<?= e(url('lesson-notes/' . $n['id'] . '/vet')) ?>" class="card form vet-card">
    <?= csrf_field() ?><input type="hidden" name="next" value="1">
    <h2>Vet this note</h2>
    <label>Comment for <?= e($n['teacher_name'] ?? 'the teacher') ?> (needed when returning)<textarea name="comment" rows="2" maxlength="500" placeholder="e.g. Good activities. Add an exit question to the assessment."></textarea></label>
    <div class="ln-actions" style="justify-content:flex-start;margin:0">
        <button class="btn btn-good" type="submit" name="decision" value="approve">✓ Approve</button>
        <button class="btn btn-danger" type="submit" name="decision" value="return">Return for correction</button>
    </div>
</form>
<?php endif; ?>

<div class="card ln-view">
    <dl class="ln-meta">
        <div><dt>Teacher</dt><dd><?= e($n['teacher_name'] ?? 'Class teacher') ?></dd></div>
        <div><dt>Week</dt><dd><?= $week ?> · <?= e(LessonNotes::weekRange($term, $week)) ?></dd></div>
        <div><dt>Submitted</dt><dd><?= $n['submitted_at'] ? e(fmt_date($n['submitted_at'], 'd M Y H:i')) : '—' ?></dd></div>
        <?php if ($n['strand_name']): ?><div><dt>Strand / sub-strand</dt><dd><?= e($n['strand_name']) ?></dd></div><?php endif; ?>
        <?php if ($n['content_standard']): ?><div><dt>Content standard</dt><dd><?= e($n['content_standard']) ?></dd></div><?php endif; ?>
    </dl>
    <?php foreach ([
        'topic' => 'Topic', 'indicators' => 'Indicator(s)', 'core_competencies' => 'Core competencies', 'resources' => 'Teaching & learning resources',
        'starter' => 'Phase 1 · Starter', 'main_activities' => 'Phase 2 · Main activities', 'plenary' => 'Phase 3 · Plenary / reflection',
        'assessment' => 'Assessment', 'references_text' => 'References', 'reflection' => "Teacher's reflection after the lesson",
    ] as $k => $label): if (trim((string) $n[$k]) === '') continue; ?>
        <section><h3><?= e($label) ?></h3><p><?= nl2br(e((string) $n[$k])) ?></p></section>
    <?php endforeach; ?>
</div>
</div>
