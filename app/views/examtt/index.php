<div class="xgrid-box" data-xgrid="exam-timetables" data-xgrid-file="exam-timetables">
<div class="page-head">
    <div><h1>Exam timetable</h1></div>
    <div class="actions"><?= export_grid_menu() ?><?php if ($manage): ?><button type="button" class="btn btn-primary" data-help="new-exam" aria-expanded="<?= empty($GLOBALS['__old']) ? 'false' : 'true' ?>">+ New exam timetable</button><?php endif; ?></div>
</div>
<?php if ($manage): ?>
<div id="new-exam" class="card"<?= empty($GLOBALS['__old']) ? ' hidden' : '' ?>>
    <h2>New exam timetable</h2>
    <form method="post" action="<?= e(url('exam-timetable')) ?>" class="form">
        <?= csrf_field() ?>
        <div class="grid-2">
            <label class="required">Name<input name="name" maxlength="120" value="<?= e(old('name', $term ? 'End of ' . $term['name'] . ' Examination' : '')) ?>" required></label>
            <label>Term<select name="term_id"><?php foreach ($terms as $t): ?><option value="<?= (int) $t['id'] ?>"<?= selected($t['id'], old('term_id', $term['id'] ?? 0)) ?>><?= e($t['year_name'] . ' · ' . $t['name']) ?></option><?php endforeach; ?></select></label>
            <label class="required">First exam day<input type="date" name="start_date" value="<?= e(old('start_date')) ?>" required></label>
            <label class="required">Last exam day<input type="date" name="end_date" value="<?= e(old('end_date')) ?>" required></label>
        </div>
        <label>Instructions for students and parents<textarea name="instructions" rows="3" maxlength="3000" placeholder="e.g. Students should arrive by 7:30 with pens, pencils, a ruler and a mathematical set."><?= e(old('instructions')) ?></textarea></label>
        <div class="gate-actions"><button class="btn btn-primary" type="submit">Create</button><button type="button" class="btn btn-ghost" data-help="new-exam">Cancel</button></div>
    </form>
</div>
<?php endif; ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['exam' => ['Exam'], 'term' => ['Term'], 'from' => ['First day', 'date'], 'to' => ['Last day', 'date'], 'papers' => ['Papers', 'num', 'num'], 'status' => ['Status']]) ?></tr></thead>
    <tbody>
    <?php foreach ($series as $s): ?>
    <tr>
        <td data-c="exam"><a href="<?= e(url('exam-timetable/' . $s['id'])) ?>"><strong><?= e($s['name']) ?></strong></a></td>
        <td data-c="term"><?= e($s['year_name'] . ' · ' . $s['term_name']) ?></td>
        <td data-c="from" data-s="<?= e($s['start_date']) ?>"><?= e(date('D j M Y', strtotime($s['start_date']))) ?></td>
        <td data-c="to" data-s="<?= e($s['end_date']) ?>"><?= e(date('D j M Y', strtotime($s['end_date']))) ?></td>
        <td data-c="papers" class="num"><?= (int) $s['papers'] ?></td>
        <td data-c="status"><?= $s['status'] === 'published' ? badge('Published', 'good') : badge('Draft', 'warn') ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $series ? ' hidden' : '' ?> data-xg-empty>No exam timetable yet.</p>
</div>
</div>
