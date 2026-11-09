<?php
$v = fn($k) => old($k, (string) ($n[$k] ?? ''));
$week = (int) $n['week_no'];
$comp = array_map('trim', explode(',', (string) ($n['core_competencies'] ?? '')));
[$sl, $st] = LessonNotes::STATUS[$n['id'] ? $n['status'] : 'none'];
?>
<div class="page-center">
<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('lesson-notes', ['term' => $term['id'], 'week' => $week])) ?>">Lesson notes</a> / Week <?= $week ?> · <?= e(LessonNotes::weekRange($term, $week)) ?></div>
    <h1><?= e($cs['class_name']) ?> · <?= e($cs['subject_name']) ?> <?= badge($sl, $st) ?></h1></div>
    <?php if ($n['id']): ?><div class="actions"><a class="btn btn-ghost" href="<?= e(url('lesson-notes/' . $n['id'] . '/print')) ?>" target="_blank" rel="noopener">Print</a></div><?php endif; ?>
</div>
<?php if (($n['status'] ?? '') === 'returned' && $n['review_comment']): ?><div class="alert alert-warning"><strong>Returned<?= !empty($n['reviewer_name']) ? ' by ' . e($n['reviewer_name']) : '' ?>:</strong> <?= e($n['review_comment']) ?></div><?php endif; ?>
<?php if (!$n['id'] && $planned): ?><div class="alert alert-info">Started from your scheme of work for week <?= $week ?>: <?= e(implode('; ', array_column($planned, 'topic'))) ?></div><?php endif; ?>

<form method="post" action="<?= e(url('lesson-notes/save')) ?>" class="form ln-form">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $n['id'] ?>"><input type="hidden" name="cs" value="<?= (int) $cs['id'] ?>"><input type="hidden" name="term" value="<?= (int) $term['id'] ?>"><input type="hidden" name="week" value="<?= $week ?>">

    <div class="card">
        <h2><span class="ln-step">1</span> The lesson</h2>
        <label class="required">Topic<input name="topic" maxlength="200" required value="<?= e($v('topic')) ?>" placeholder="e.g. Adding fractions with different denominators"></label>
        <div class="grid-2">
            <label>Strand / sub-strand<select name="strand_id"><option value="">—</option><?php foreach ($tree as $s): ?><optgroup label="<?= e($s['name']) ?>"><option value="<?= (int) $s['id'] ?>"<?= selected($s['id'], old('strand_id', $n['strand_id'] ?? '')) ?>><?= e($s['name']) ?> (whole strand)</option><?php foreach ($s['subs'] as $sub): ?><option value="<?= (int) $sub['id'] ?>"<?= selected($sub['id'], old('strand_id', $n['strand_id'] ?? '')) ?>><?= e($sub['name']) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></label>
            <label>Content standard<input name="content_standard" maxlength="255" value="<?= e($v('content_standard')) ?>" placeholder="e.g. B4.1.4.1"></label>
        </div>
        <label>Indicator(s)<input name="indicators" maxlength="500" value="<?= e($v('indicators')) ?>" placeholder="What learners will be able to do"></label>
        <fieldset class="ln-chips"><legend>Core competencies</legend>
            <?php foreach (LessonNotes::COMPETENCIES as $c): ?><label><input type="checkbox" name="competencies[]" value="<?= e($c) ?>"<?= in_array($c, $comp, true) ? ' checked' : '' ?>><span><?= e($c) ?></span></label><?php endforeach; ?>
        </fieldset>
        <label>Teaching &amp; learning resources<input name="resources" maxlength="500" value="<?= e($v('resources')) ?>" placeholder="e.g. Fraction strips, counters, textbook p. 34"></label>
    </div>

    <div class="card">
        <h2><span class="ln-step">2</span> Lesson phases</h2>
        <label>Phase 1 · Starter (preparing the brain for learning)<textarea name="starter" rows="3" maxlength="5000"><?= e($v('starter')) ?></textarea></label>
        <label class="required">Phase 2 · Main activities (new learning)<textarea name="main_activities" rows="6" maxlength="5000"><?= e($v('main_activities')) ?></textarea></label>
        <label>Phase 3 · Plenary / reflection<textarea name="plenary" rows="3" maxlength="5000"><?= e($v('plenary')) ?></textarea></label>
    </div>

    <div class="card">
        <h2><span class="ln-step">3</span> Assessment</h2>
        <label class="required">How learning will be checked<textarea name="assessment" rows="3" maxlength="5000" placeholder="e.g. Learners solve 5 problems in pairs; exit question"><?= e($v('assessment')) ?></textarea></label>
        <label>References<input name="references_text" maxlength="500" value="<?= e($v('references_text')) ?>" placeholder="e.g. NaCCA Mathematics Curriculum B4; Teacher's guide p. 21"></label>
        <label>After the lesson · teacher's reflection (optional)<textarea name="reflection" rows="2" maxlength="5000"><?= e($v('reflection')) ?></textarea></label>
    </div>

    <div class="ln-actions">
        <button class="btn" type="submit" name="action" value="save">Save draft</button>
        <button class="btn btn-primary" type="submit" name="action" value="submit" data-confirm="Submit this lesson note for vetting? You cannot change it while it is being vetted." data-confirm-ok="Submit">Submit for vetting</button>
    </div>
</form>
</div>
