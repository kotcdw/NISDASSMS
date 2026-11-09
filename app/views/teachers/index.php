<?php
$pctTone = fn(?float $p) => $p === null ? 'neutral' : ($p >= 90 ? 'good' : ($p >= 75 ? 'warn' : 'bad'));
$opened = count(array_filter($rows, fn($r) => $r['appraisal']));
$done = count(array_filter($rows, fn($r) => ($r['appraisal']['status'] ?? '') === 'completed'));
?>
<div class="xgrid-box" data-xgrid="teachers" data-xgrid-file="teachers<?= $term ? '-' . e(strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $term['name']))) : '' ?>">
<div class="page-head">
    <div><h1>Teachers</h1></div>
    <div class="actions">
        <?php if ($terms): ?>
        <form method="get" action="<?= e(url('teachers')) ?>" class="list-pick" data-autosubmit>
            <select name="term" aria-label="Term"><?php foreach ($terms as $t): ?><option value="<?= (int) $t['id'] ?>"<?= selected($t['id'], $term['id'] ?? 0) ?>><?= e($t['year_name'] . ' · ' . $t['name']) ?></option><?php endforeach; ?></select>
        </form>
        <?php endif; ?>
        <?php if (can('appraisal.manage')): ?><a class="btn btn-ghost" href="<?= e(url('teachers/criteria')) ?>">Appraisal criteria</a><?php endif; ?>
        <?= export_grid_menu() ?>
        <?php if (can('appraisal.manage') && $term): ?>
        <form method="post" action="<?= e(url('teachers/appraisals/open')) ?>" class="inline" data-confirm="Open the <?= e($term['name']) ?> appraisal for every teacher? Each one gets a notice to do the self-assessment.">
            <?= csrf_field() ?><input type="hidden" name="term" value="<?= (int) $term['id'] ?>">
            <button class="btn btn-primary" type="submit">Open appraisals for <?= e($term['name']) ?></button>
        </form>
        <?php endif; ?>
    </div>
</div>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th([
        'name' => ['Teacher'], 'role' => ['Role'], 'classes' => ['Class teacher of'], 'subjects' => ['Subjects', 'num', 'num'], 'lessons' => ['Lessons / week', 'num', 'num'],
        'att' => ['Attendance %', 'num', 'num'], 'present' => ['Days present', 'num', 'num'], 'late' => ['Days late', 'num', 'num'],
        'avg' => ['Class results avg', 'num', 'num'], 'cov' => ['Scheme covered', 'num'], 'appr' => ['Appraisal'], 'score' => ['Score /5', 'num', 'num'],
    ]) ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $st = $r['appraisal']['status'] ?? null; $pct = $r['att']['pct'] ?? null; $covOn = $r['cov'] && $r['cov']['planned']; ?>
    <tr>
        <td data-c="name"><a href="<?= e(url('teachers/' . $r['id'], ['term' => $term['id'] ?? null])) ?>"><strong><?= e($r['name']) ?></strong></a></td>
        <td data-c="role"><?= e(Auth::roleLabel($r['role'])) ?></td>
        <td data-c="classes"><?= $r['classes'] ? e(implode(', ', $r['classes'])) : '<span class="muted">—</span>' ?></td>
        <td data-c="subjects" class="num"><?= (int) $r['subjects'] ?></td>
        <td data-c="lessons" class="num"><?= (int) $r['lessons'] ?></td>
        <td data-c="att" class="num" data-s="<?= $pct === null ? '' : e((string) $pct) ?>"><?= $pct !== null ? badge(Results::fmt($pct) . '%', $pctTone($pct)) : '<span class="muted">—</span>' ?></td>
        <td data-c="present" class="num" data-s="<?= $pct !== null ? (int) $r['att']['present'] : '' ?>"><?= $pct !== null ? (int) $r['att']['present'] . ' / ' . (int) $r['att']['days'] : '' ?></td>
        <td data-c="late" class="num"><?= $pct !== null ? (int) $r['att']['late'] : '' ?></td>
        <td data-c="avg" class="num" data-s="<?= $r['avg'] === null ? '' : e((string) $r['avg']) ?>"><?= $r['avg'] === null ? '<span class="muted">—</span>' : Results::fmt($r['avg']) ?></td>
        <td data-c="cov" data-s="<?= $covOn ? e((string) $r['cov']['pct']) : '' ?>"><?php if ($covOn): ?><?= Results::fmt($r['cov']['pct']) ?>%<span class="pct-bar"><span style="width:<?= (float) $r['cov']['pct'] ?>%"></span></span><?php else: ?><span class="muted">Not planned</span><?php endif; ?></td>
        <td data-c="appr"><?= $st ? badge(Teachers::STATUS[$st][0], Teachers::STATUS[$st][1]) : '<span class="muted">Not opened</span>' ?></td>
        <td data-c="score" class="num"><?= $st === 'completed' && $r['appraisal']['overall'] !== null ? e(Results::fmt((float) $r['appraisal']['overall'])) : '' ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No teachers yet.</p>
</div>
</div>
