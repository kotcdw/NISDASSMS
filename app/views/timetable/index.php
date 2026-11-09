<?php require __DIR__ . '/_tabs.php'; ?>
<?php if ($clashCount): ?><div class="alert alert-error"><?= (int) $clashCount ?> teacher clash(es) found. <a href="<?= e(url('timetable/check')) ?>">See the check page</a>.</div><?php endif; ?>
<div class="xgrid-box" data-xgrid="timetable-classes" data-xgrid-file="timetable-classes">
<div class="page-head">
    <div><h1>Time table</h1></div>
    <div class="actions">
        <a class="btn btn-ghost" href="<?= e(url('timetable/print')) ?>" target="_blank" rel="noopener">Print all classes</a>
        <?= export_grid_menu() ?>
        <?php if (can('timetable.manage')): ?><button type="button" class="btn btn-primary" data-help="tt-generate" aria-expanded="false">Build automatically</button><?php endif; ?>
    </div>
</div>
<?php if (can('timetable.manage')): ?>
<div id="tt-generate" class="card" hidden>
    <h2>Build automatically</h2>
    <form method="post" action="<?= e(url('timetable/generate')) ?>" class="form" data-confirm="Generate the time table for the selected classes?">
        <?= csrf_field() ?>
        <div style="max-height:220px;overflow:auto;border:1px solid var(--line);border-radius:8px;padding:.5rem;margin-bottom:.8rem">
            <label style="font-weight:600;margin-bottom:.3rem"><input type="checkbox" data-check-all="classes[]"> All classes</label>
            <?php foreach ($classes as $c): ?>
                <label style="font-weight:400;margin-bottom:.2rem"><input type="checkbox" name="classes[]" value="<?= (int) $c['id'] ?>"> <?= e($c['name']) ?></label>
            <?php endforeach; ?>
        </div>
        <label>Existing lessons
            <select name="mode">
                <option value="keep_manual">Keep lessons placed by hand, redo the rest</option>
                <option value="fill">Keep everything, only fill empty periods</option>
                <option value="replace">Start from scratch</option>
            </select>
        </label>
        <div class="gate-actions"><button class="btn btn-primary" type="submit">Generate</button><button type="button" class="btn btn-ghost" data-help="tt-generate">Cancel</button></div>
    </form>
</div>
<?php endif; ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['class' => ['Class'], 'teacher' => ['Class teacher'], 'placed' => ['Lessons placed', 'num', 'num'], 'planned' => ['Lessons planned', 'num', 'num'], 'periods' => ['Periods a week', 'num', 'num'], 'done' => ['Done', 'num'], 'check' => ['Check']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($classes as $c): $pct = $c['planned'] ? min(100, round($c['scheduled'] * 100 / $c['planned'])) : 0; ?>
    <tr>
        <td data-c="class"><a href="<?= e(url('timetable/class/' . $c['id'])) ?>"><strong><?= e($c['name']) ?></strong></a></td>
        <td data-c="teacher"><?= e($c['teacher_name'] ?? '—') ?></td>
        <td data-c="placed" class="num"><?= (int) $c['scheduled'] ?></td>
        <td data-c="planned" class="num"><?= (int) $c['planned'] ?></td>
        <td data-c="periods" class="num"><?= (int) $c['capacity'] ?></td>
        <td data-c="done" data-s="<?= (int) $pct ?>"><div style="display:flex;gap:.4rem;align-items:center"><div class="meter<?= $pct < 100 ? ' mid' : '' ?>"><span style="width:<?= $pct ?>%"></span></div><small><?= (int) $pct ?>%</small></div></td>
        <td data-c="check"><?= $c['planned'] > $c['capacity'] ? badge('More lessons than periods', 'bad') : '' ?></td>
        <td class="xg-act" style="white-space:nowrap">
            <a class="btn btn-sm" href="<?= e(url('timetable/class/' . $c['id'])) ?>">View</a>
            <?php if (can('timetable.manage')): ?><a class="btn btn-sm btn-ghost" href="<?= e(url('timetable/class/' . $c['id'], ['edit' => 1])) ?>">Edit</a><?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $classes ? ' hidden' : '' ?> data-xg-empty>No classes yet.</p>
</div>
</div>

<div class="xgrid-box" data-xgrid="timetable-teachers" data-xgrid-file="teacher-lessons" style="margin-top:1.25rem">
<div class="page-head">
    <div><h2 style="margin:0">Teachers</h2></div>
    <div class="actions"><?= export_grid_menu() ?></div>
</div>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['teacher' => ['Teacher'], 'lessons' => ['Lessons a week', 'num', 'num']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($teachers as $t): ?>
    <tr>
        <td data-c="teacher"><a href="<?= e(url('timetable/teacher/' . $t['id'])) ?>"><?= e($t['name']) ?></a></td>
        <td data-c="lessons" class="num"><?= (int) $t['lessons'] ?></td>
        <td class="xg-act"><a class="btn btn-sm btn-ghost" href="<?= e(url('timetable/teacher/' . $t['id'])) ?>">Time table</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $teachers ? ' hidden' : '' ?> data-xg-empty>No teacher accounts yet.</p>
</div>
</div>
