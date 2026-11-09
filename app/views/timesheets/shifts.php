<?php
$tab = 'shifts'; $dn = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
$shiftName = array_column($shifts, 'name', 'id');
$daysRow = function (array $checked) use ($dn): string {
    $h = '<div>';
    foreach ($dn as $k => $d) {
        $h .= '<label style="display:inline;font-weight:400;margin-right:.4rem"><input type="checkbox" name="days[]" value="' . $k . '"' . (in_array((string) $k, $checked, true) ? ' checked' : '') . '> ' . $d . '</label>';
    }
    return $h . '</div>';
};
?>
<div class="page-head">
    <div><h1>Staff shifts</h1></div>
    <div class="actions"><button type="button" class="btn btn-primary" data-help="new-shift" aria-expanded="false">+ Add a shift</button></div>
</div>
<?php require dirname(__DIR__) . '/attendance/_staff_tabs.php'; ?>
<div id="new-shift" class="card" hidden>
    <h2>Add a shift</h2>
    <form method="post" action="<?= e(url('timesheets/shifts')) ?>" class="form"><?= csrf_field() ?>
        <div class="grid-3"><label class="required">Name<input name="name" maxlength="60" required placeholder="e.g. Afternoon cleaning"></label><label class="required">Start<input type="time" name="start_time" required></label><label class="required">End<input type="time" name="end_time" required></label></div>
        <?= $daysRow(['1', '2', '3', '4', '5']) ?>
        <div class="gate-actions"><button class="btn btn-primary" type="submit">Save</button><button type="button" class="btn btn-ghost" data-help="new-shift">Cancel</button></div>
    </form>
</div>
<div class="card" style="padding:0">
    <h2 style="padding:1rem 1rem 0">Shifts</h2>
    <div class="table-wrap"><table>
        <thead><tr><th>Shift</th><th>Hours</th><th>Days</th><th class="num">Staff</th><th class="xg-act"></th></tr></thead>
        <tbody>
        <?php foreach ($shifts as $s): $sid = (int) $s['id']; $sd = explode(',', $s['days']); ?>
        <tr>
            <td><strong><?= e($s['name']) ?></strong><?= (int) $s['is_active'] ? '' : ' ' . badge('Not used', 'neutral') ?></td>
            <td><?= e(substr($s['start_time'], 0, 5)) ?>–<?= e(substr($s['end_time'], 0, 5)) ?></td>
            <td><?= e(implode(' ', array_map(fn($d) => $dn[(int) $d] ?? '', $sd))) ?></td>
            <td class="num"><?= (int) $s['n'] ?></td>
            <td class="xg-act"><button type="button" class="btn btn-sm" data-help="edit-shift-<?= $sid ?>" aria-expanded="false">Edit</button></td>
        </tr>
        <tr id="edit-shift-<?= $sid ?>" class="xg-edit" hidden>
            <td colspan="5">
                <form method="post" action="<?= e(url('timesheets/shifts')) ?>" class="form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $sid ?>">
                    <div class="grid-3"><label class="required">Name<input name="name" value="<?= e($s['name']) ?>" maxlength="60" required></label><label class="required">Start<input type="time" name="start_time" value="<?= e(substr($s['start_time'], 0, 5)) ?>" required></label><label class="required">End<input type="time" name="end_time" value="<?= e(substr($s['end_time'], 0, 5)) ?>" required></label></div>
                    <?= $daysRow($sd) ?>
                    <label style="font-weight:400"><input type="checkbox" name="is_active" value="1"<?= (int) $s['is_active'] ? ' checked' : '' ?>> In use</label>
                    <div class="gate-actions"><button class="btn btn-primary" type="submit">Save</button><button type="button" class="btn btn-ghost" data-help="edit-shift-<?= $sid ?>">Cancel</button></div>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php if (!$shifts): ?><p class="muted" style="padding:1rem;margin:0">No shifts yet. Everyone works the normal school day. Use “+ Add a shift”.</p><?php endif; ?>
</div>
<div class="card" style="padding:0">
    <h2 style="padding:1rem 1rem 0">Who works which shift</h2>
    <div class="table-wrap"><table>
        <thead><tr><th>Staff</th><th>Role</th><th>Shift</th><th class="xg-act"></th></tr></thead>
        <tbody>
        <?php foreach ($staff as $p): $uid = (int) $p['id']; ?>
        <tr>
            <td><?= e($p['name']) ?></td>
            <td><?= e(Auth::roleLabel($p['role'])) ?></td>
            <td><?= $p['shift_id'] && isset($shiftName[$p['shift_id']]) ? e($shiftName[$p['shift_id']]) : '<span class="muted">School day (normal)</span>' ?></td>
            <td class="xg-act"><button type="button" class="btn btn-sm" data-help="edit-<?= $uid ?>" aria-expanded="false">Edit</button></td>
        </tr>
        <tr id="edit-<?= $uid ?>" class="xg-edit" hidden>
            <td colspan="4">
                <form method="post" action="<?= e(url('timesheets/shifts/assign')) ?>" class="form"><?= csrf_field() ?>
                    <label>Shift for <?= e($p['name']) ?><select name="shift[<?= $uid ?>]" style="max-width:20rem"><option value="0">School day (normal)</option><?php foreach ($shifts as $s): if (!(int) $s['is_active']) continue; ?><option value="<?= (int) $s['id'] ?>"<?= selected($p['shift_id'], $s['id']) ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></label>
                    <div class="gate-actions"><button class="btn btn-primary" type="submit">Save</button><button type="button" class="btn btn-ghost" data-help="edit-<?= $uid ?>">Cancel</button></div>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php if (!$staff): ?><p class="muted" style="padding:1rem;margin:0">No staff accounts yet.</p><?php endif; ?>
</div>
