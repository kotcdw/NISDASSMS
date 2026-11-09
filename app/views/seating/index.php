<div class="xgrid-box" data-xgrid="seating-classes" data-xgrid-file="class-seating-plans">
<div class="page-head">
    <div><h1>Seating plans</h1></div>
    <div class="actions"><?= export_grid_menu() ?></div>
</div>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['class' => ['Class'], 'teacher' => ['Class teacher'], 'pupils' => ['Students', 'num', 'num'], 'layout' => ['Plan'], 'seated' => ['Seated', 'num', 'num'], 'missing' => ['Not seated', 'num', 'num']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($classes as $c): $roll = (int) ($rolls[(int) $c['id']] ?? 0); $plan = (bool) $c['plan_id']; $miss = $plan ? max(0, $roll - (int) $c['seated']) : 0; ?>
    <tr>
        <td data-c="class"><strong><?= e($c['name']) ?></strong></td>
        <td data-c="teacher"><?= e($c['teacher_name'] ?? '—') ?></td>
        <td data-c="pupils" class="num"><?= $roll ?></td>
        <td data-c="layout"><?= $plan ? (int) $c['seat_rows'] . ' rows × ' . (int) $c['seat_cols'] . ' seats' : '<span class="muted">No plan yet</span>' ?></td>
        <td data-c="seated" class="num"><?= $plan ? (int) $c['seated'] : '' ?></td>
        <td data-c="missing" class="num" data-s="<?= $plan ? $miss : '' ?>"><?= $miss ? badge((string) $miss, 'warn') : '' ?></td>
        <td class="xg-act"><a class="btn btn-sm" href="<?= e(url('seating/class/' . $c['id'])) ?>"><?= $plan ? 'Open' : 'Set up' ?></a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $classes ? ' hidden' : '' ?> data-xg-empty>No classes for you here.</p>
</div>
</div>

<?php if (can('examtt.manage')): ?>
<div class="xgrid-box" data-xgrid="seating-exams" data-xgrid-file="exam-seating-plans" style="margin-top:1.5rem">
<div class="page-head">
    <div><h2>Exam seating</h2></div>
    <div class="actions"><?= export_grid_menu() ?><a class="btn btn-primary" href="<?= e(url('seating/exam/create')) ?>">+ New exam seating plan</a></div>
</div>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['plan' => ['Plan'], 'exam' => ['Exam'], 'room' => ['Room'], 'seated' => ['Seated', 'num', 'num']]) ?></tr></thead>
    <tbody>
    <?php foreach ($exams as $x): ?>
    <tr>
        <td data-c="plan"><a href="<?= e(url('seating/' . $x['id'])) ?>"><strong><?= e($x['title']) ?></strong></a></td>
        <td data-c="exam"><?= e($x['series_name'] ?? '—') ?></td>
        <td data-c="room"><?= e($x['room_name'] ?? '—') ?></td>
        <td data-c="seated" class="num"><?= (int) $x['seated'] ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $exams ? ' hidden' : '' ?> data-xg-empty>No exam seating plans yet.</p>
</div>
</div>
<?php endif; ?>
