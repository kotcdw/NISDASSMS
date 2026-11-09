<?php $cur = $s ? ($s['class_subject_id'] ? 'cs:' . $s['class_subject_id'] : 'c:' . $s['class_id']) : ''; ?>
<div class="page-center">
<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('live')) ?>">Live classes</a> /</div><h1><?= $s ? 'Change live class' : 'Schedule a live class' ?></h1></div>
</div>
<?php if (!$targets): ?><div class="card"><p class="muted">You have no classes or subjects assigned yet.</p></div><?php else: ?>
<form method="post" action="<?= e(url($s ? 'live/' . $s['id'] : 'live')) ?>" class="form card" style="max-width:780px"><?= csrf_field() ?>
    <div class="grid-2">
        <label class="required">Class<select name="target" required><option value="">— choose —</option><?php foreach ($targets as $t): ?><option value="<?= e($t['key']) ?>"<?= selected(old('target', $cur), $t['key']) ?>><?= e($t['label']) ?></option><?php endforeach; ?></select></label>
        <label class="required">Meeting app<select name="provider"><?php foreach (Learning::PROVIDERS as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(old('provider', $prov ?: 'zoom'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
    </div>
    <label class="required">Title<input name="title" maxlength="150" required value="<?= e(old('title', $s['title'] ?? '')) ?>" placeholder="e.g. Revision: Integrated Science, Unit 3"></label>
    <label class="required">Meeting link<input name="join_url" maxlength="500" required value="<?= e(old('join_url', $s['join_url'] ?? '')) ?>" placeholder="https://us05web.zoom.us/j/… · https://meet.google.com/… · https://teams.microsoft.com/…"></label>
    <div class="grid-2">
        <label>Meeting ID / code<input name="meeting_code" maxlength="60" value="<?= e(old('meeting_code', $s['meeting_code'] ?? '')) ?>"></label>
        <label>Passcode<input name="passcode" maxlength="40" value="<?= e(old('passcode', $s['passcode'] ?? '')) ?>"><small>Shown only to the class and their parents, from 15 minutes before the start.</small></label>
    </div>
    <div class="grid-3" style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.8rem">
        <label class="required">Date<input type="date" name="date" required min="<?= e(date('Y-m-d')) ?>" value="<?= e(old('date', $s ? substr($s['starts_at'], 0, 10) : date('Y-m-d'))) ?>"></label>
        <label class="required">Start<input type="time" name="time" required value="<?= e(old('time', $s ? substr($s['starts_at'], 11, 5) : '')) ?>"></label>
        <label class="required">Minutes<input type="number" name="duration_min" min="10" max="240" required value="<?= e(old('duration_min', $s['duration_min'] ?? 40)) ?>"></label>
    </div>
    <label>Notes for students<textarea name="notes" rows="2" maxlength="500"><?= e(old('notes', $s['notes'] ?? '')) ?></textarea></label>
    <label style="font-weight:400"><input type="checkbox" name="sms" value="1"<?= old('sms') ? ' checked' : '' ?>> Also text parents (SMS) <?= $s ? 'about the change' : '' ?></label>
    <button class="btn btn-primary" type="submit"><?= $s ? 'Save' : 'Schedule' ?></button>
</form>
<?php endif; ?>
</div>
