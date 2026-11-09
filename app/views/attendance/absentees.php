<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('attendance', ['date' => $date])) ?>">Attendance</a> /</div><h1>Absentees</h1></div>
</div>
<div class="card">
    <h2>Not in class on <?= e(fmt_date($date, 'l j F')) ?></h2>
    <?php if (!$today): ?><p class="muted">No absences recorded for this day.</p><?php else: ?>
    <div class="table-wrap"><table>
        <tr><th>Student</th><th>Class</th><th>Status</th><th>Note</th><th>Parent phone</th></tr>
        <?php foreach ($today as $a): ?>
        <tr>
            <td><a href="<?= e(url('pupils/' . $a['id'])) ?>"><?= e($a['last_name'] . ', ' . $a['first_name']) ?></a></td>
            <td><?= e($a['class_name']) ?></td>
            <td><?= badge(Attendance::STATUSES[$a['status']][0], $a['status'] === 'absent' ? 'bad' : ($a['status'] === 'late' ? 'warn' : 'info')) ?></td>
            <td><?= e($a['note']) ?></td>
            <td><a href="tel:<?= e($a['phone']) ?>"><?= e($a['phone']) ?></a></td>
        </tr>
        <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
</div>
<div class="card">
    <div class="page-head" style="margin-bottom:.5rem">
        <h2 style="margin:0">Below 80% attendance this term</h2>
    </div>
    <?php if (!$low): ?><p class="muted">No student is below 80% (students with at least 5 marked days).</p><?php else: ?>
    <div class="table-wrap"><table>
        <tr><th>Student</th><th>Class</th><th class="num">Present</th><th class="num">Absent</th><th>Attendance</th></tr>
        <?php foreach ($low as $l): ?>
        <tr>
            <td><a href="<?= e(url('pupils/' . $l['id'])) ?>"><?= e($l['last_name'] . ', ' . $l['first_name']) ?></a></td>
            <td><?= e($l['class_name']) ?></td>
            <td class="num"><?= (int) $l['present'] ?>/<?= (int) $l['marked'] ?></td>
            <td class="num"><?= (int) $l['absent'] ?></td>
            <td><?= Attendance::meter(Attendance::percent((int) $l['present'], (int) $l['marked'])) ?></td>
        </tr>
        <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
</div>
