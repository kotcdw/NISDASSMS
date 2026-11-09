<?php $v = fn($k, $d = '') => old($k, $a[$k] ?? $d); ?>
<div class="page-center">
<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('assignments')) ?>">Assignments</a> /</div><h1><?= e($title) ?></h1></div>
</div>
<form method="post" action="<?= e(url($a ? 'assignments/' . $a['id'] : 'assignments')) ?>" enctype="multipart/form-data" class="form card" style="max-width:820px">
    <?= csrf_field() ?>
    <div class="grid-2">
        <label class="required">Class &amp; subject<select name="class_subject_id" required><option value="">— choose —</option>
            <?php $g = null; foreach ($choices as $c): if ($g !== $c['class_name']): if ($g !== null) echo '</optgroup>'; $g = $c['class_name']; ?><optgroup label="<?= e($g) ?>"><?php endif; ?>
                <option value="<?= (int) $c['id'] ?>"<?= selected($v('class_subject_id'), $c['id']) ?>><?= e($c['class_name'] . ' — ' . $c['subject_name']) ?></option>
            <?php endforeach; if ($g !== null) echo '</optgroup>'; ?>
        </select></label>
        <label class="required">Title<input name="title" maxlength="150" required value="<?= e($v('title')) ?>" placeholder="e.g. Fractions — exercise 4B"></label>
    </div>
    <label>Instructions<textarea name="instructions" rows="6" maxlength="10000" placeholder="What students should do, pages, questions…"><?= e($v('instructions')) ?></textarea></label>
    <div class="grid-3">
        <label>Set on<input type="date" name="set_on" value="<?= e($v('set_on', date('Y-m-d'))) ?>"></label>
        <label class="required">Due on<input type="date" name="due_on" required value="<?= e($v('due_on', date('Y-m-d', strtotime('+2 weekdays')))) ?>"></label>
        <label>Marked out of (optional)<input name="max_score" inputmode="decimal" value="<?= e($v('max_score') !== null ? (string) $v('max_score') : '') ?>" placeholder="e.g. 20"></label>
    </div>
    <label>Worksheet or picture (PDF, JPG or PNG)<input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png"><?php if ($a && $a['attachment']): ?><small>Now: <?= e($a['attachment_name']) ?> — choose a new file to replace it.</small><?php endif; ?></label>
    <label style="font-weight:400"><input type="checkbox" name="on_paper" value="1"<?= $a && !(int) $a['submit_online'] ? ' checked' : '' ?>> Hand in on paper only (students cannot hand in online; you tick who handed in)</label>
    <div class="actions" style="justify-content:flex-start">
        <button class="btn btn-primary" type="submit" name="status" value="published"><?= $a && $a['status'] !== 'draft' ? 'Save' : 'Publish to students &amp; parents' ?></button>
        <?php if (!$a || $a['status'] === 'draft'): ?><button class="btn" type="submit" name="status" value="draft">Save as draft</button><?php endif; ?>
        <a class="btn btn-ghost" href="<?= e(url($a ? 'assignments/' . $a['id'] : 'assignments')) ?>">Cancel</a>
    </div>
</form>
</div>
