<?php
$isToday = $date === date('Y-m-d');
$totals = ['present' => 0, 'absent' => 0, 'late' => 0, 'taken' => 0];
foreach ($classes as $c) {
    if (isset($stats[$c['id']])) {
        $totals['taken']++;
        $totals['present'] += (int) $stats[$c['id']]['present'];
        $totals['absent'] += (int) $stats[$c['id']]['absent'];
        $totals['late'] += (int) $stats[$c['id']]['late'];
    }
}
?>
<div class="xgrid-box" data-xgrid="attendance-day" data-xgrid-file="attendance-<?= e($date) ?>">
<div class="page-head">
    <div><h1>Attendance</h1></div>
    <div class="actions">
        <form method="get" action="<?= e(url('attendance')) ?>" class="list-pick" data-autosubmit><input type="date" name="date" value="<?= e($date) ?>" max="<?= e(date('Y-m-d')) ?>" aria-label="Day"></form>
        <a class="btn btn-ghost" href="<?= e(url('attendance/absentees', ['date' => $date])) ?>">Absentees</a>
        <?= export_grid_menu() ?>
    </div>
</div>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['class' => ['Class'], 'teacher' => ['Class teacher'], 'n' => ['Students', 'num', 'num'], 'reg' => ['Register'], 'present' => ['Present', 'num', 'num'], 'absent' => ['Absent', 'num', 'num'], 'late' => ['Late', 'num', 'num']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($classes as $c): $s = $stats[$c['id']] ?? null; $n = (int) ($rosterCounts[$c['id']] ?? 0); ?>
    <tr>
        <td data-c="class"><strong><?= e($c['name']) ?></strong></td>
        <td data-c="teacher"><?= e($c['teacher_name'] ?? '—') ?></td>
        <td data-c="n" class="num"><?= $n ?></td>
        <td data-c="reg"><?= $s ? badge('Taken ' . substr($s['taken_at'], 11, 5), 'good') : ($blocked || !$n ? '—' : badge('Not taken', 'warn')) ?></td>
        <td data-c="present" class="num"><?= $s ? (int) $s['present'] : '' ?></td>
        <td data-c="absent" class="num"><?= $s ? (int) $s['absent'] : '' ?></td>
        <td data-c="late" class="num"><?= $s ? (int) $s['late'] : '' ?></td>
        <td class="xg-act" style="white-space:nowrap">
            <?php if (!$blocked && $n && can('attendance.take')): ?><a class="btn btn-sm<?= $s ? '' : ' btn-primary' ?>" href="<?= e(url('attendance/register/' . $c['id'], ['date' => $date])) ?>"><?= $s ? 'Edit' : 'Take register' ?></a><?php endif; ?>
            <a class="btn btn-sm btn-ghost" href="<?= e(url('attendance/class/' . $c['id'])) ?>">Term report</a>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $classes ? ' hidden' : '' ?> data-xg-empty>You are not class teacher of any class yet.</p>
</div>
</div>
