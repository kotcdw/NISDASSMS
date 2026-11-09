<div class="page-head">
    <div>
        <div class="breadcrumb"><a href="<?= e(url('attendance')) ?>">Attendance</a> /</div>
        <h1><?= e($class['name']) ?> — term attendance</h1>
        
    </div>
    <div class="actions">
        <form method="get" class="filters" style="margin:0">
            <select name="term" data-autosubmit aria-label="Term">
                <?php foreach ($terms as $t): ?><option value="<?= (int) $t['id'] ?>"<?= selected($t['id'], $term['id'] ?? 0) ?>><?= e($t['year_name'] . ' · ' . $t['name']) ?></option><?php endforeach; ?>
            </select>
        </form>
        <a class="btn" href="<?= e(url('attendance/class/' . $class['id'] . '/month')) ?>" target="_blank">Monthly register</a> <?php if ($term): ?><?= export_menu(url('attendance/class/' . $class['id'] . '/export', ['term' => $term['id']])) ?><?php endif; ?>
        <button class="btn btn-ghost" type="button" data-print>Print</button>
    </div>
</div>
<?php if (!$report): ?>
    <div class="alert alert-info">No term set up yet.</div>
<?php else: $missing = count(array_diff_key(array_flip($report['days']), $report['taken'])); ?>
<p class="muted"><?= count($report['days']) ?> school days so far this term (weekends and closed days excluded) · registers taken on <?= count($report['taken']) ?><?= $missing ? ' · ' . badge("$missing days not taken", 'warn') : '' ?></p>
<div class="card" style="padding:0">
    <div class="table-wrap"><table>
        <tr><th>Student</th><th class="num">Present</th><th class="num">Absent</th><th class="num">Late</th><th class="num">Excused/sick</th><th>Attendance</th></tr>
        <?php foreach ($report['rows'] as $r): ?>
        <tr>
            <td><a href="<?= e(url('pupils/' . $r['pupil']['id'])) ?>"><?= e($r['pupil']['last_name'] . ', ' . $r['pupil']['first_name']) ?></a></td>
            <td class="num"><?= $r['present'] ?></td><td class="num"><?= $r['absent'] ?></td><td class="num"><?= $r['late'] ?></td><td class="num"><?= $r['excused'] ?></td>
            <td><?= Attendance::meter($r['pct']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table></div>
</div>
<?php if ($report['days']): ?>
<h2>Daily marks</h2>
<div class="card table-wrap">
    <table class="att-grid">
        <tr><th></th><?php foreach ($report['days'] as $d): ?><th title="<?= e(fmt_date($d, 'D j M')) ?>"><?= e(date('j/n', strtotime($d))) ?></th><?php endforeach; ?></tr>
        <?php foreach ($report['rows'] as $r): ?>
        <tr>
            <td class="name"><?= e($r['pupil']['last_name'] . ' ' . mb_substr($r['pupil']['first_name'], 0, 1) . '.') ?></td>
            <?php foreach ($report['days'] as $d): $m = $r['marks'][$d] ?? null; ?>
                <td class="<?= $m ? 'mk-' . $m : (isset($report['taken'][$d]) ? '' : 'mk-closed') ?>"><?= $m ? Attendance::STATUSES[$m][1] : (isset($report['taken'][$d]) ? '' : '·') ?></td>
            <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
    </table>
    <p class="hint" style="margin-top:.5rem">P present · L late · A absent · E excused · S sick · grey = register not taken</p>
</div>
<?php endif; ?>
<?php endif; ?>
