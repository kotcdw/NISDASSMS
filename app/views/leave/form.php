<div class="page-center">
<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('leave')) ?>">Leave</a> /</div><h1><?= Auth::role() === 'parent' ? 'Ask for leave' : 'Record leave for a student' ?></h1>
    <?php if (Auth::role() !== 'parent'): ?><?php endif; ?></div>
</div>
<form method="post" action="<?= e(url('leave')) ?>" enctype="multipart/form-data" class="form card" style="max-width:720px">
    <?= csrf_field() ?>
    <label class="required">Child<select name="pupil_id" required><option value="">— choose —</option><?php foreach ($pupils as $p): ?><option value="<?= (int) $p['id'] ?>"<?= selected(old('pupil_id', $pupilId ?: (count($pupils) === 1 ? $p['id'] : '')), $p['id']) ?>><?= e($p['first_name'] . ' ' . $p['last_name'] . (!empty($p['class_name']) ? ' (' . $p['class_name'] . ')' : '')) ?></option><?php endforeach; ?></select></label>
    <div class="grid-2">
        <label class="required">First day<input type="date" name="from_date" required value="<?= e(old('from_date', date('Y-m-d'))) ?>"></label>
        <label>Last day<input type="date" name="to_date" value="<?= e(old('to_date')) ?>"><small>Leave empty for one day.</small></label>
    </div>
    <label class="required">Reason<select name="kind" required><?php foreach (LeaveController::KINDS as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(old('kind'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
    <label class="required">Explanation<textarea name="reason" rows="3" maxlength="500" required><?= e(old('reason')) ?></textarea></label>
    <label>Doctor's note or letter (PDF, JPG or PNG, optional)<input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png"></label>
    <button class="btn btn-primary" type="submit"><?= Auth::role() === 'parent' ? 'Send request' : 'Record leave' ?></button>
</form>
</div>
