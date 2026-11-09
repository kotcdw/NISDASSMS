<div class="page-head"><div><h1>Student quizzes (preview)</h1></div></div>
<form method="get" action="<?= e(url('learn')) ?>" class="filters" data-autosubmit>
    <label>Class<select name="class"><option value="">Choose…</option><?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected($classId, $c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></label>
    <button class="btn" type="submit">Show students</button>
</form>
<?php if ($classId): ?>
<div class="card" style="padding:0"><div class="table-wrap"><table>
    <tr><th>Student</th><th>Admission no.</th><th></th></tr>
    <?php foreach ($pupils as $p): ?><tr><td><?= e($p['last_name'] . ' ' . $p['first_name']) ?></td><td><?= e($p['admission_no']) ?></td><td><a class="btn btn-sm" href="<?= e(url('learn', ['pupil' => $p['id']])) ?>">View as this student</a></td></tr><?php endforeach; ?>
    <?php if (!$pupils): ?><tr><td colspan="3" class="muted">No students in this class this year.</td></tr><?php endif; ?>
</table></div></div>
<?php endif; ?>
