<?php $v = fn($k, $d = '') => old($k, $d); $sd = $ev ? substr($ev['start_at'], 0, 10) : ''; $stt = $ev && substr($ev['start_at'], 11, 5) !== '00:00' ? substr($ev['start_at'], 11, 5) : ''; ?>
<div class="page-center">
<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('events')) ?>">Events</a> /</div><h1><?= e($title) ?></h1></div></div>
<form method="post" action="<?= e(url($ev ? 'events/' . $ev['id'] : 'events')) ?>" class="form card" style="max-width:820px">
    <?= csrf_field() ?>
    <label class="required">Title<input name="title" maxlength="150" required value="<?= e($v('title', $ev['title'] ?? '')) ?>" placeholder="e.g. Inter-house sports day"></label>
    <label>Description<textarea name="description" rows="4"><?= e($v('description', $ev['description'] ?? '')) ?></textarea></label>
    <div class="grid-3">
        <label class="required">Date<input type="date" name="start_date" required value="<?= e($v('start_date', $sd)) ?>"></label>
        <label>Starts at<input type="time" name="start_time" value="<?= e($v('start_time', $stt)) ?>"></label>
        <label>Venue<input name="venue" maxlength="120" value="<?= e($v('venue', $ev['venue'] ?? '')) ?>"></label>
        <label>Ends (date)<input type="date" name="end_date" value="<?= e($v('end_date', $ev && $ev['end_at'] ? substr($ev['end_at'], 0, 10) : '')) ?>"></label>
        <label>Ends at<input type="time" name="end_time" value="<?= e($v('end_time', $ev && $ev['end_at'] ? substr($ev['end_at'], 11, 5) : '')) ?>"></label>
    </div>
    <div class="grid-3">
        <label>For<select name="audience"><?php foreach (EventController::AUDIENCE as $k => $l): ?><option value="<?= $k ?>"<?= selected($v('audience', $ev['audience'] ?? 'all'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
        <label>Level (if one level)<select name="level_id"><option value="">—</option><?php foreach ($levels as $l): ?><option value="<?= (int) $l['id'] ?>"<?= selected($v('level_id', $ev['level_id'] ?? ''), $l['id']) ?>><?= e($l['name']) ?></option><?php endforeach; ?></select></label>
        <label>Places (empty = no limit)<input name="capacity" inputmode="numeric" value="<?= e($v('capacity', $ev['capacity'] ?? '')) ?>"></label>
        <label>Fee per child (GH₵)<input name="fee" inputmode="decimal" value="<?= e($v('fee', $ev['fee'] ?? '0')) ?>"><small>Added to the child's bills when registered.</small></label>
        <label>Register by<input type="date" name="reg_deadline" value="<?= e($v('reg_deadline', $ev['reg_deadline'] ?? '')) ?>"></label>
    </div>
    <label style="font-weight:400"><input type="checkbox" name="no_registration" value="1"<?= $ev && !(int) $ev['registration'] ? ' checked' : '' ?>> No registration needed (just an announcement)</label>
    <div class="actions" style="justify-content:flex-start">
        <button class="btn btn-primary" type="submit" name="status" value="published"><?= $ev && $ev['status'] !== 'draft' ? 'Save' : 'Publish' ?></button>
        <?php if (!$ev || $ev['status'] === 'draft'): ?><button class="btn" type="submit" name="status" value="draft">Save as draft</button><?php endif; ?>
    </div>
</form>
</div>
