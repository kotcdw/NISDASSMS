<div class="page-center">
<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('exam-timetable/' . $s['id'])) ?>"><?= e($s['name']) ?></a> /</div><h1>Edit paper · <?= e($p['class_name']) ?></h1></div>
</div>
<form method="post" action="<?= e(url('exam-timetable/papers/' . $p['id'])) ?>" class="form card">
    <?= csrf_field() ?>
    <div class="grid-2">
        <label class="required">Subject<select name="subject_id" required><?php foreach ($subjects as $sb): ?><option value="<?= (int) $sb['id'] ?>"<?= selected($sb['id'], old('subject_id', $p['subject_id'])) ?>><?= e($sb['name']) ?></option><?php endforeach; ?></select></label>
        <label>Paper<input name="paper" maxlength="40" value="<?= e(old('paper', (string) $p['paper'])) ?>"></label>
        <label class="required">Date<input type="date" name="exam_date" value="<?= e(old('exam_date', $p['exam_date'])) ?>" required></label>
        <label class="required">Start time<input type="time" name="start_time" value="<?= e(old('start_time', substr($p['start_time'], 0, 5))) ?>" required></label>
        <label class="required">Duration (minutes)<input type="number" name="duration_min" min="10" max="300" value="<?= e(old('duration_min', (string) $p['duration_min'])) ?>" required></label>
        <label>Room<select name="room_id"><option value="">—</option><?php foreach ($rooms as $r): ?><option value="<?= (int) $r['id'] ?>"<?= selected($r['id'], old('room_id', $p['room_id'])) ?>><?= e($r['name']) ?> (<?= (int) $r['capacity'] ?>)</option><?php endforeach; ?></select></label>
        <label>Invigilator<select name="invigilator_id"><option value="">—</option><?php foreach ($staff as $u): ?><option value="<?= (int) $u['id'] ?>"<?= selected($u['id'], old('invigilator_id', $p['invigilator_id'])) ?>><?= e($u['name']) ?></option><?php endforeach; ?></select></label>
    </div>
    <label>Notes<input name="notes" maxlength="200" value="<?= e(old('notes', (string) $p['notes'])) ?>"></label>
    <button class="btn btn-primary" type="submit">Save paper</button>
    <a class="btn btn-ghost" href="<?= e(url('exam-timetable/' . $s['id'])) ?>">Cancel</a>
</form>
</div>
