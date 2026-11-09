<?php
$class = $R['class']; $term = $R['term']; $scheme = $R['scheme']; $q = ['term' => $term['id']];
$byPos = $R['pupils'];
usort($byPos, fn($a, $b) => ($R['summary'][$a['id']]['position'] ?? 999) <=> ($R['summary'][$b['id']]['position'] ?? 999));
?>
<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('reports', $q)) ?>">Report cards</a> / <?= e($term['year_name'] . ' · ' . $term['name']) ?></div>
    <h1>Broadsheet — <?= e($class['name']) ?></h1></div>
    <div class="actions">
        <?= export_menu(url('reports/class/' . $class['id'] . '/broadsheet/export', $q), 'Export', 'btn') ?>
        <button class="btn btn-ghost" type="button" data-print>Print</button>
    </div>
</div>
<?php $pending = array_filter($R['subjects'], fn($s) => $s['sheet_status'] !== 'approved'); if ($pending): ?>
<div class="alert alert-warning">Not yet approved: <?= e(implode(', ', array_column($pending, 'name'))) ?>. Figures may still change.</div>
<?php endif; ?>
<div class="card table-wrap" style="padding:.5rem">
    <table class="bs-table">
        <tr>
            <th>Pos.</th><th class="name">Student</th>
            <?php foreach ($R['subjects'] as $s): ?><th title="<?= e($s['name']) ?>"><?= e($s['code']) ?></th><?php endforeach; ?>
            <th>Total</th><th>Avg</th><?= $scheme['use_aggregate'] ? '<th>Agg.</th>' : '' ?>
        </tr>
        <?php foreach ($byPos as $p): $pid = (int) $p['id']; $sm = $R['summary'][$pid]; ?>
        <tr>
            <td><strong><?= e(Results::ordinal($sm['position'])) ?></strong></td>
            <td class="name"><a href="<?= e(url('reports/class/' . $class['id'] . '/print', $q + ['pupil' => $pid])) ?>" target="_blank" rel="noopener"><?= e($p['last_name'] . ', ' . $p['first_name']) ?></a></td>
            <?php foreach ($R['subjects'] as $s): $sc = $R['scores'][$pid][$s['id']] ?? null; ?>
                <td title="<?= e($s['name'] . ': ' . ($sc['remark'] ?? '')) ?>"><?= !empty($sc['absent']) ? 'ABS' : Results::fmt($sc['total'] ?? null) ?><?= ($sc['grade'] ?? '') !== '' ? '<br><small>' . e($sc['grade']) . '</small>' : '' ?></td>
            <?php endforeach; ?>
            <td><?= Results::fmt($sm['total']) ?></td>
            <td><strong><?= Results::fmt($sm['average']) ?></strong></td>
            <?= $scheme['use_aggregate'] ? '<td>' . e($sm['aggregate'] ?? '—') . '</td>' : '' ?>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<h2>Subject analysis</h2>
<div class="card" style="padding:0">
    <div class="table-wrap"><table>
        <tr><th>Subject</th><th>Teacher</th><th class="num">Scored</th><th class="num">Average</th><th class="num">Highest</th><th class="num">Lowest</th><th>Pass rate (≥<?= Results::fmt((float) $scheme['pass_mark']) ?>)</th><th>Status</th></tr>
        <?php foreach ($R['subjects'] as $s): $st = $R['subjectStats'][$s['id']]; [$sl, $tone] = ExamController::SHEET_STATUS[$s['sheet_status'] ?? 'draft']; ?>
        <tr>
            <td><?= e($s['name']) ?></td>
            <td><?= e($s['teacher_name'] ?? 'Class teacher') ?></td>
            <td class="num"><?= (int) $st['count'] ?></td>
            <td class="num"><?= Results::fmt($st['average']) ?></td>
            <td class="num"><?= Results::fmt($st['highest']) ?></td>
            <td class="num"><?= Results::fmt($st['lowest']) ?></td>
            <td><?= $st['count'] ? Attendance::meter(round($st['passed'] * 100 / $st['count'], 1)) : '—' ?></td>
            <td><?= badge($sl, $tone) ?></td>
        </tr>
        <?php endforeach; ?>
    </table></div>
</div>
