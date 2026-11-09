<?php
$in = count(array_filter($rows, fn($r) => $r['time_in']));
$late = count(array_filter($rows, fn($r) => StaffAttendance::isLate($r['time_in'], (int) $r['id'])));
$past = $date < date('Y-m-d');
$tab = 'day';
?>
<?php require __DIR__ . '/_staff_tabs.php'; ?>
<?php if (!$schoolDay): ?><div class="alert alert-info">This is not a school day (weekend, Sabbath, holiday or vacation), so nobody is expected to sign in.</div><?php endif; ?>
<div class="xgrid-box" data-xgrid="staff-attendance" data-xgrid-file="staff-attendance-<?= e($date) ?>">
<div class="page-head">
    <div><h1>Staff attendance</h1></div>
    <div class="actions">
        <form method="get" action="<?= e(url('attendance/staff')) ?>" class="list-pick" data-autosubmit><input type="date" name="date" value="<?= e($date) ?>" max="<?= e(date('Y-m-d')) ?>" aria-label="Day"></form>
        <?php if (can('teachers.view')): ?><a class="btn btn-ghost" href="<?= e(url('teachers')) ?>">Teachers</a><?php endif; ?>
        <?php if (can('app.configure')): ?><button type="button" class="btn btn-ghost" data-help="late-time" aria-expanded="false">Late time</button><?php endif; ?>
        <?= export_grid_menu() ?>
    </div>
</div>
<div id="late-time" class="card" hidden>
    <?php if (can('app.configure')): ?><form method="post" action="<?= e(url('attendance/staff/settings')) ?>" class="form">
        <?= csrf_field() ?>
        <label>Late if signing in after<input type="time" name="staff_late_after" value="<?= e($lateAfter) ?>" style="max-width:160px"></label>
        <div class="gate-actions"><button class="btn btn-primary" type="submit">Save</button><button type="button" class="btn btn-ghost" data-help="late-time">Cancel</button></div>
    </form><?php endif; ?>
</div>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['name' => ['Name'], 'role' => ['Role'], 'in' => ['Signed in'], 'late' => ['Late'], 'out' => ['Signed out'], 'away' => ['Out of school now']]) ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $id = (int) $r['id']; $isLate = $r['time_in'] && StaffAttendance::isLate($r['time_in'], $id); ?>
    <tr>
        <td data-c="name"><strong><?= e($r['name']) ?></strong></td>
        <td data-c="role"><?= e(Auth::roleLabel($r['role'])) ?></td>
        <td data-c="in" data-s="<?= e((string) $r['time_in']) ?>"><?php if ($r['time_in']): ?><?= e(substr($r['time_in'], 0, 5)) ?><?php elseif (isset($leave[$id][$date])): ?><?= badge('On leave · ' . $leave[$id][$date], 'info') ?><?php else: ?><?= $schoolDay ? badge('Not signed in', 'bad') : '<span class="muted">—</span>' ?><?php endif; ?></td>
        <td data-c="late"><?= $isLate ? badge('Late', 'warn') : '' ?></td>
        <td data-c="out" data-s="<?= e((string) $r['time_out']) ?>"><?= $r['time_out'] ? e(substr($r['time_out'], 0, 5)) : ($r['time_in'] && $past ? badge('No sign-out', 'neutral') : '') ?></td>
        <td data-c="away"><?= isset($outNow[$id]) ? badge('Out: ' . $outNow[$id]['destination'], 'warn') : '' ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No staff yet.</p>
</div>
</div>
