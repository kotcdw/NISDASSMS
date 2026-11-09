<?php $hm = fn($t) => substr((string) $t, 0, 5); ?>
<div class="page-head"><div><h1>Time table check</h1></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="card">
    <h2>Teacher clashes <?= $clashes ? badge(count($clashes), 'bad') : badge('None', 'good') ?></h2>
    <?php if ($clashes): ?>
    <div class="table-wrap"><table>
        <tr><th>Teacher</th><th>Day</th><th>Lesson 1</th><th>Lesson 2</th></tr>
        <?php foreach ($clashes as $c): ?>
        <tr>
            <td><?= e($c['a']['teacher_name']) ?></td><td><?= e(Timetable::DAYS[$c['a']['day']]) ?></td>
            <td><a href="<?= e(url('timetable/class/' . $c['a']['class_id'], ['edit' => 1])) ?>"><?= e($c['a']['class_name']) ?></a> <?= e($c['a']['subject_name']) ?> <small><?= e($hm($c['a']['start_time'])) ?>–<?= e($hm($c['a']['end_time'])) ?></small></td>
            <td><a href="<?= e(url('timetable/class/' . $c['b']['class_id'], ['edit' => 1])) ?>"><?= e($c['b']['class_name']) ?></a> <?= e($c['b']['subject_name']) ?> <small><?= e($hm($c['b']['start_time'])) ?>–<?= e($hm($c['b']['end_time'])) ?></small></td>
        </tr>
        <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
    <?php if ($unassigned): ?><p class="alert alert-warning"><?= (int) $unassigned ?> lesson(s) have no teacher. Assign a class teacher or subject teacher.</p><?php endif; ?>
</div>
<div class="cols">
    <div class="card">
        <h2>Lessons not matching the plan <?= $short ? badge(count($short), 'warn') : badge('All match', 'good') ?></h2>
        <?php if ($short): ?>
        <div class="table-wrap"><table>
            <tr><th>Class</th><th>Subject</th><th class="num">Planned</th><th class="num">Placed</th></tr>
            <?php foreach ($short as $s): ?>
                <tr><td><a href="<?= e(url('timetable/class/' . $s['class_id'])) ?>"><?= e($s['class_name']) ?></a></td><td><?= e($s['name']) ?></td><td class="num"><?= (int) $s['planned'] ?></td><td class="num"><?= (int) $s['scheduled'] ?></td></tr>
            <?php endforeach; ?>
        </table></div>
        <?php endif; ?>
    </div>
    <div class="card">
        <h2>Teacher workload</h2>
        <?php foreach ($load as $l): ?>
            <p style="margin:0 0 .35rem;font-size:.9rem"><a href="<?= e(url('timetable/teacher/' . $l['id'])) ?>"><?= e($l['name']) ?></a> — <strong><?= (int) $l['lessons'] ?></strong> lessons/week</p>
        <?php endforeach; ?>
    </div>
</div>
