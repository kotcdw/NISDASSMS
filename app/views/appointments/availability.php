<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('appointments')) ?>">Appointments</a> /</div><h1>My availability</h1></div></div>
<div class="cols">
<div class="card" style="padding:0"><div class="table-wrap"><table>
    <tr><th>Day</th><th>Time</th><th>Appointment type</th><th></th></tr>
    <?php foreach ($rows as $r): ?><tr><td><?= e(AppointmentController::DAYS[(int) $r['weekday']]) ?></td><td><?= e(substr($r['start_time'], 0, 5)) ?>–<?= e(substr($r['end_time'], 0, 5)) ?></td><td><?= e($r['type_name']) ?></td>
        <td><form method="post" action="<?= e(url('appointments/availability/' . $r['id'] . '/delete')) ?>" class="inline"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" type="submit">Remove</button></form></td></tr><?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="4" class="muted">No times yet.</td></tr><?php endif; ?>
</table></div></div>
<form method="post" action="<?= e(url('appointments/availability')) ?>" class="card form"><?= csrf_field() ?>
    <h2>Add a time</h2>
    <label>Appointment type<select name="type_id"><?php foreach ($types as $t): ?><option value="<?= (int) $t['id'] ?>"><?= e($t['name']) ?> (<?= (int) $t['duration_min'] ?> min)</option><?php endforeach; ?></select></label>
    <label>Day<select name="weekday"><?php foreach (AppointmentController::DAYS as $k => $d): if ($k > 5) continue; ?><option value="<?= $k ?>"><?= $d ?></option><?php endforeach; ?></select></label>
    <div class="grid-2"><label>From<input type="time" name="start_time" value="14:00" required></label><label>To<input type="time" name="end_time" value="15:00" required></label></div>
    <button class="btn btn-primary" type="submit">Add</button>
</form>
</div>
