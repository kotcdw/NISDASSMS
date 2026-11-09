<?php
[$sl, $st] = ExamController::SHEET_STATUS[$sheet['status']];
$classComps = array_values(array_filter($comps, fn($c) => $c['kind'] === 'class'));
$examComps = array_values(array_filter($comps, fn($c) => $c['kind'] === 'exam'));
$q = ['term' => $term['id']];
$cellVal = function (array $s = null): string {
    if (!$s) return '';
    if ((int) $s['is_absent']) return 'a';
    return $s['score'] === null ? '' : Results::fmt((float) $s['score']);
};
?>
<div class="page-head">
    <div>
        <div class="breadcrumb"><a href="<?= e(url('exams', $q)) ?>">Exams</a> / <?= e($term['year_name'] . ' · ' . $term['name']) ?></div>
        <h1><?= e($cs['subject_name']) ?> — <?= e($cs['class_name']) ?> <?= badge($sl, $st) ?></h1>
        <p class="muted">Teacher: <?= e($cs['teacher_name'] ?? 'class teacher') ?> · Class score <?= (int) $scheme['class_weight'] ?>% + exam <?= (int) $scheme['exam_weight'] ?>% · <?= e($scheme['name']) ?></p>
    </div>
</div>

<?php if ($sheet['return_note'] && $sheet['status'] === 'draft'): ?><div class="alert alert-warning">Returned by the Academic Coordinator: <?= e($sheet['return_note']) ?></div><?php endif; ?>
<?php if (!empty($released)): ?><div class="alert alert-warning"><strong>Reports released to parents.</strong> These scores are locked. To correct them, withdraw the release in <a href="<?= e(url('reports', $q)) ?>">Report cards</a>, make the change, then release again — parents get a “corrected report” SMS.</div>
<?php elseif ($locked): ?><div class="alert alert-info">This sheet is <?= e(strtolower($sl)) ?> and locked<?= $sheet['status'] === 'submitted' ? ' while it waits for approval' : '' ?>.</div><?php endif; ?>

<?php if (!$pupils): ?>
    <div class="alert alert-info">No students are enrolled in this class.</div>
<?php else: ?>
<form method="post" action="<?= e(url('exams/sheet/' . $cs['id'], $q)) ?>" id="score-form">
    <?= csrf_field() ?>
    <div class="card" style="padding:0">
        <div class="table-wrap"><table class="score-table">
            <tr>
                <th>#</th><th>Student</th>
                <?php foreach ($classComps as $c): ?><th class="num" title="Class item"><?= e($c['title']) ?><br><small>/<?= Results::fmt((float) $c['max_mark']) ?></small></th><?php endforeach; ?>
                <th class="num" style="background:#eef3fb">Class<br><small>/<?= (int) $scheme['class_weight'] ?></small></th>
                <?php foreach ($examComps as $c): ?><th class="num"><?= e($c['title']) ?><br><small>/<?= Results::fmt((float) $c['max_mark']) ?></small></th><?php endforeach; ?>
                <th class="num" style="background:#eef3fb">Exam<br><small>/<?= (int) $scheme['exam_weight'] ?></small></th>
                <th class="num">Total<br><small>/100</small></th><th>Grade</th><th class="num">Pos.</th>
            </tr>
            <?php foreach ($pupils as $i => $p): $pid = (int) $p['id']; $r = $computed[$pid]; [$g, $rem] = $r['absent'] ? ['ABS', 'Absent from exam'] : Results::grade($scheme, $r['total']); ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td style="white-space:nowrap"><strong><?= e($p['last_name'] . ', ' . $p['first_name']) ?></strong><br><small><?= e($p['admission_no']) ?></small></td>
                <?php foreach ($classComps as $c): ?>
                    <td class="num"><input class="score" name="s[<?= (int) $c['id'] ?>][<?= $pid ?>]" value="<?= e($cellVal($scores[$pid][$c['id']] ?? null)) ?>" inputmode="decimal" maxlength="6" aria-label="<?= e($c['title'] . ' for ' . $p['first_name']) ?>"<?= $locked ? ' disabled' : '' ?>></td>
                <?php endforeach; ?>
                <td class="num" style="background:#f6f9fd"><?= Results::fmt($r['class']) ?></td>
                <?php foreach ($examComps as $c): ?>
                    <td class="num"><input class="score" name="s[<?= (int) $c['id'] ?>][<?= $pid ?>]" value="<?= e($cellVal($scores[$pid][$c['id']] ?? null)) ?>" inputmode="decimal" maxlength="6" aria-label="<?= e($c['title'] . ' for ' . $p['first_name']) ?>"<?= $locked ? ' disabled' : '' ?>></td>
                <?php endforeach; ?>
                <td class="num" style="background:#f6f9fd"><?= Results::fmt($r['exam']) ?></td>
                <td class="num"><strong><?= $r['absent'] ? 'ABS' : Results::fmt($r['total']) ?></strong></td>
                <td title="<?= e($rem) ?>"><?= $r['absent'] ? badge('ABS', 'warn') : ($g !== '' ? badge($g, $r['total'] >= (float) $scheme['pass_mark'] ? 'good' : 'bad') : '') ?></td>
                <td class="num"><?= e(Results::ordinal($ranks[$pid])) ?></td>
            </tr>
            <?php endforeach; ?>
        </table></div>
    </div>
    <?php if (!$locked): ?>
        <button class="btn btn-primary" type="submit">Save scores</button>
        <span class="hint">Type “a” for absent (the report shows ABS and the student gets no position). Class score and exam are converted to <?= (int) $scheme['class_weight'] ?> and <?= (int) $scheme['exam_weight'] ?> automatically when you save.</span>
    <?php endif; ?>
</form>
<?php endif; ?>

<div class="cols" style="margin-top:1.25rem">
    <div>
        <?php if (!$locked): ?>
        <div class="card">
            <h2>Score items</h2>
            
            <?php foreach ($comps as $c): ?>
                <form method="post" action="<?= e(url('exams/sheet/' . $cs['id'] . '/assessments/' . $c['id'], $q)) ?>" class="filters" style="margin-bottom:.5rem">
                    <?= csrf_field() ?>
                    <?= badge($c['kind'] === 'exam' ? 'Exam' : 'Class', $c['kind'] === 'exam' ? 'info' : 'neutral') ?>
                    <input name="title" value="<?= e($c['title']) ?>" maxlength="80" style="max-width:200px" aria-label="Item name">
                    <input name="max_mark" type="number" step="0.5" min="1" max="1000" value="<?= e((float) $c['max_mark']) ?>" style="max-width:90px;min-width:0" aria-label="Maximum mark">
                    <button class="btn btn-sm" type="submit">Save</button>
                    <button class="btn btn-sm btn-ghost" type="submit" name="delete" value="1" data-confirm-click="Remove this item and all its scores?">Remove</button>
                </form>
            <?php endforeach; ?>
            <form method="post" action="<?= e(url('exams/sheet/' . $cs['id'] . '/assessments', $q)) ?>" class="filters" style="margin-top:1rem">
                <?= csrf_field() ?>
                <select name="kind" aria-label="Type"><option value="class">Class item</option><option value="exam">Exam paper</option></select>
                <input name="title" placeholder="e.g. Class test 1" maxlength="80" required aria-label="New item name">
                <input name="max_mark" type="number" step="0.5" min="1" max="1000" placeholder="Max" required style="max-width:90px;min-width:0" aria-label="Maximum">
                <button class="btn btn-sm btn-primary" type="submit">Add item</button>
            </form>
        </div>
        <div class="card">
            <h2>Paste from Excel</h2>
            
            <form method="post" action="<?= e(url('exams/sheet/' . $cs['id'] . '/paste', $q)) ?>">
                <?= csrf_field() ?>
                <textarea name="data" rows="4" placeholder="NIS/2026/0001&#9;24&#9;68"></textarea>
                <button class="btn btn-sm" type="submit" style="margin-top:.5rem">Import</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
    <div>
        <div class="card">
            <h2>Approval</h2>
            <?php if ($sheet['status'] === 'draft'): ?>
                <form method="post" action="<?= e(url('exams/sheet/' . $cs['id'] . '/submit', $q)) ?>" data-confirm="Submit these scores for approval? You will not be able to edit them afterwards.">
                    <?= csrf_field() ?>
                    <label style="font-size:.85rem"><input type="checkbox" name="confirm_missing" value="1"> Submit even if some exam scores are missing</label>
                    <button class="btn btn-good" type="submit">Submit for approval</button>
                </form>
                <?php if (can('exams.manage')): ?>
                <form method="post" action="<?= e(url('exams/sheet/' . $cs['id'] . '/approve', $q)) ?>" style="margin-top:.75rem" data-confirm="Approve without the teacher submitting? Every student needs an exam score (or “a” for absent)." data-confirm-ok="Approve"><?= csrf_field() ?><button class="btn btn-sm" type="submit">Approve directly</button></form>
                <?php endif; ?>
            <?php elseif ($sheet['status'] === 'submitted'): ?>
                <p class="muted" style="font-size:.9rem">Submitted <?= e(fmt_date($sheet['submitted_at'], 'd M Y H:i')) ?>.</p>
                <?php if (can('exams.manage')): ?>
                <form method="post" action="<?= e(url('exams/sheet/' . $cs['id'] . '/approve', $q)) ?>" class="form">
                    <?= csrf_field() ?>
                    <button class="btn btn-good" type="submit">Approve</button>
                    <label style="margin-top:1rem">Or return with a note <input name="note" maxlength="255" placeholder="What needs correcting?"></label>
                    <button class="btn btn-sm btn-danger" type="submit" name="return" value="1">Return to teacher</button>
                </form>
                <?php endif; ?>
            <?php else: ?>
                <p class="muted" style="font-size:.9rem">Approved <?= e(fmt_date($sheet['approved_at'], 'd M Y H:i')) ?>. These scores appear on the report cards.</p>
                <?php if (can('exams.manage') && empty($released)): ?>
                <form method="post" action="<?= e(url('exams/sheet/' . $cs['id'] . '/reopen', $q)) ?>" data-confirm="Re-open for editing?"><?= csrf_field() ?><button class="btn btn-sm" type="submit">Re-open for editing</button></form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
