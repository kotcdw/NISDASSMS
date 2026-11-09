<?php
$manage = can('exams.manage');
$wantStatus = (string) ($_GET['status'] ?? '');
if ($wantStatus !== '') {
    $rows = array_values(array_filter($rows, fn($r) => ($r['status'] ?? 'draft') === $wantStatus));
}
$overview = $manage && !$classId && $wantStatus === '';
$base = ['term' => $term['id'] ?? null] + ($classId ? ['class' => $classId] : []);
$tabs = [];
foreach (['' => 'All', 'submitted' => 'Waiting for approval', 'draft' => 'Being entered', 'approved' => 'Approved'] as $k => $l) {
    $tabs[$k === '' ? 'all' : $k] = [$l, url('exams', $base + ($k !== '' ? ['status' => $k] : []))];
}
$byClass = [];
if ($overview) {
    foreach ($rows as $r) {
        $c = &$byClass[$r['class_id']];
        $c['name'] = $r['class_name'];
        $c['n'] = ($c['n'] ?? 0) + 1;
        $st = $r['status'] ?? 'draft';
        $c[$st] = ($c[$st] ?? 0) + 1;
        $c['entered'] = ($c['entered'] ?? 0) + ((int) $r['pupils'] && (int) $r['exam_entered'] >= (int) $r['pupils'] ? 1 : 0);
        unset($c);
    }
}
$waiting = array_sum(array_map(fn($c) => $c['submitted'] ?? 0, $byClass));
$hasNote = (bool) array_filter($rows, fn($r) => !empty($r['return_note']));
?>
<?php if ($waiting): ?><div class="alert alert-info"><strong><?= $waiting ?> score sheet<?= $waiting > 1 ? 's are' : ' is' ?> waiting for your approval.</strong> <a href="<?= e(url('exams', ['term' => $term['id'], 'status' => 'submitted'])) ?>">Show them</a></div><?php endif; ?>
<div class="xgrid-box" data-xgrid="<?= $overview ? 'exam-classes' : 'score-sheets' ?>" data-xgrid-file="<?= $overview ? 'score-sheets-by-class' : 'score-sheets' ?>">
<div class="page-head">
    <div><h1>Score sheets</h1></div>
    <div class="actions">
        <form method="get" action="<?= e(url('exams')) ?>" class="list-pick" data-autosubmit>
            <select name="term" aria-label="Term">
                <?php foreach ($terms as $t): ?><option value="<?= (int) $t['id'] ?>"<?= selected($t['id'], $term['id'] ?? 0) ?>><?= e($t['year_name'] . ' · ' . $t['name']) ?></option><?php endforeach; ?>
            </select>
            <?php if ($classId): ?><input type="hidden" name="class" value="<?= (int) $classId ?>"><?php endif; ?>
            <?php if ($wantStatus !== ''): ?><input type="hidden" name="status" value="<?= e($wantStatus) ?>"><?php endif; ?>
        </form>
        <?php if ($manage && $classId): ?><a class="btn btn-ghost" href="<?= e(url('exams', ['term' => $term['id'] ?? null])) ?>">All classes</a><?php endif; ?>
        <?= export_grid_menu() ?>
    </div>
</div>
<?php if ($manage): ?><?= card_tabs($tabs, $wantStatus === '' ? 'all' : $wantStatus, 4, 'Score sheets to show') ?><?php endif; ?>
<?php if ($overview): ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['class' => ['Class'], 'subjects' => ['Subjects', 'num', 'num'], 'complete' => ['Exam scores complete', 'num'], 'draft' => ['Being entered', 'num', 'num'], 'submitted' => ['Waiting for approval', 'num', 'num'], 'approved' => ['Approved', 'num', 'num'], 'pct' => ['Approved %', 'num', 'num']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($byClass as $cid => $c): $pct = $c['n'] ? round(($c['approved'] ?? 0) * 100 / $c['n']) : 0; $done = $c['n'] ? round($c['entered'] * 100 / $c['n']) : 0; $href = e(url('exams', ['term' => $term['id'], 'class' => $cid])); ?>
    <tr>
        <td data-c="class"><a href="<?= $href ?>"><strong><?= e($c['name']) ?></strong></a></td>
        <td data-c="subjects" class="num"><?= (int) $c['n'] ?></td>
        <td data-c="complete" data-s="<?= $done ?>"><div style="display:flex;gap:.4rem;align-items:center"><div class="meter"><span style="width:<?= $done ?>%"></span></div><small><?= (int) $c['entered'] ?>/<?= (int) $c['n'] ?></small></div></td>
        <td data-c="draft" class="num" data-s="<?= (int) ($c['draft'] ?? 0) ?>"><?= !empty($c['draft']) ? badge((string) $c['draft'], 'warn') : '<span class="muted">—</span>' ?></td>
        <td data-c="submitted" class="num" data-s="<?= (int) ($c['submitted'] ?? 0) ?>"><?= !empty($c['submitted']) ? badge((string) $c['submitted'], 'info') : '<span class="muted">—</span>' ?></td>
        <td data-c="approved" class="num" data-s="<?= (int) ($c['approved'] ?? 0) ?>"><?= !empty($c['approved']) ? badge((string) $c['approved'], $pct === 100 ? 'good' : 'neutral') : '<span class="muted">—</span>' ?></td>
        <td data-c="pct" class="num" data-s="<?= $pct ?>"><?= $pct ?>%</td>
        <td class="xg-act"><a class="btn btn-sm" href="<?= $href ?>">Open</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $byClass ? ' hidden' : '' ?> data-xg-empty>No subjects yet.</p>
</div>
<?php else: ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['class' => ['Class'], 'subject' => ['Subject'], 'teacher' => ['Teacher'], 'scores' => ['Exam scores', 'num'], 'status' => ['Status']] + ($hasNote ? ['note' => ['Returned because']] : [])) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): [$sl, $st] = ExamController::SHEET_STATUS[$r['status'] ?? 'draft']; $n = (int) $r['pupils']; $draft = ($r['status'] ?? 'draft') === 'draft'; $done = $n ? min(100, round((int) $r['exam_entered'] * 100 / $n)) : 0; ?>
    <tr>
        <td data-c="class"><?= e($r['class_name']) ?></td>
        <td data-c="subject"><strong><?= e($r['subject_name']) ?></strong></td>
        <td data-c="teacher"><?= e($r['teacher_name'] ?? '—') ?></td>
        <td data-c="scores" data-s="<?= $n ? $done : '' ?>"><?php if ($n): ?><div style="display:flex;gap:.4rem;align-items:center"><div class="meter"><span style="width:<?= $done ?>%"></span></div><small><?= (int) $r['exam_entered'] ?>/<?= $n ?></small></div><?php else: ?><span class="muted">no students</span><?php endif; ?></td>
        <td data-c="status"><?= badge($sl, $st) ?></td>
        <?php if ($hasNote): ?><td data-c="note"><?= $r['return_note'] ? '<small style="color:var(--bad)">' . e($r['return_note']) . '</small>' : '' ?></td><?php endif; ?>
        <td class="xg-act"><a class="btn btn-sm<?= $draft ? ' btn-primary' : '' ?>" href="<?= e(url('exams/sheet/' . $r['id'], ['term' => $term['id']])) ?>"><?= $draft ? 'Enter scores' : 'Open' ?></a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty><?= $wantStatus !== '' ? 'No score sheets here.' : 'No subjects assigned to you.' ?></p>
</div>
<?php endif; ?>
</div>
