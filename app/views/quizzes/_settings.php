<?php $v = fn($k, $d = '') => $q[$k] ?? $d; $dt = fn($x) => $x ? str_replace(' ', 'T', substr($x, 0, 16)) : ''; ?>
<label>Title<input name="title" required maxlength="150" value="<?= e($v('title')) ?>" placeholder="e.g. Fractions — weekly test"></label>
<label>Class &amp; subject<select name="class_subject_id" required><?php foreach ($css as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected($v('class_subject_id'), $c['id']) ?>><?= e($c['class_name'] . ' — ' . $c['subject_name']) ?></option><?php endforeach; ?></select></label>
<label>Instructions (optional)<textarea name="instructions" rows="2" maxlength="2000"><?= e($v('instructions')) ?></textarea></label>
<div class="grid-3">
    <label>Time limit (minutes, 0 = none)<input type="number" name="time_limit" min="0" max="300" value="<?= (int) $v('time_limit', 20) ?>"></label>
    <label>Opens<input type="datetime-local" name="opens_at" value="<?= e($dt($v('opens_at'))) ?>"></label>
    <label>Closes<input type="datetime-local" name="closes_at" value="<?= e($dt($v('closes_at'))) ?>"></label>
</div>
<div class="grid-3">
    <label>Attempts allowed<input type="number" name="max_attempts" min="1" max="10" value="<?= (int) $v('max_attempts', 1) ?>"></label>
    <label>Show correct answers<select name="show_answers"><?php foreach (Quiz::SHOW as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($v('show_answers', 'after_close'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
    <label style="font-weight:400;align-self:end"><input type="checkbox" name="shuffle" value="1"<?= checked((bool) $v('shuffle', 1)) ?>> Shuffle question order for each student</label>
</div>
