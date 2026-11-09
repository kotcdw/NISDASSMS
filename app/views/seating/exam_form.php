<?php $oldClasses = array_map('intval', (array) old('classes', [])); ?>
<div class="page-center">
<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e($seriesId ? url('exam-timetable/' . $seriesId) : url('seating')) ?>"><?= $seriesId ? 'Exam timetable' : 'Seating plans' ?></a> /</div>
    <h1>New exam seating plan</h1></div>
</div>
<form method="post" action="<?= e(url('seating/exam')) ?>" class="form card">
    <?= csrf_field() ?>
    <div class="grid-3">
        <label class="required">Name<input name="title" maxlength="120" value="<?= e(old('title', 'Exam hall')) ?>" required></label>
        <label>Exam<select name="series_id"><option value="">—</option><?php foreach ($series as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected($s['id'], old('series_id', $seriesId)) ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></label>
        <label>Room<select name="room_id"><option value="">—</option><?php foreach ($rooms as $r): ?><option value="<?= (int) $r['id'] ?>"<?= selected($r['id'], old('room_id')) ?>><?= e($r['name']) ?> (<?= (int) $r['capacity'] ?> seats)</option><?php endforeach; ?></select></label>
    </div>
    <fieldset><legend>Classes in this room</legend>
        <div class="grid-4">
        <?php foreach ($classes as $c): ?><label style="font-weight:400;margin:0 0 .4rem"><input type="checkbox" name="classes[]" value="<?= (int) $c['id'] ?>"<?= in_array((int) $c['id'], $oldClasses, true) ? ' checked' : '' ?>> <?= e($c['name']) ?> <small class="muted">(<?= (int) ($rolls[(int) $c['id']] ?? 0) ?>)</small></label><?php endforeach; ?>
        </div>
    </fieldset>
    <?php $rows = 8; $cols = 8; $method = 'alpha'; $exam = true; require __DIR__ . '/_grid_form.php'; ?>
    <button class="btn btn-primary" type="submit">Make the plan</button>
</form>
</div>
