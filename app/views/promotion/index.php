<div class="page-head">
    <div><h1>Year-end promotion</h1></div>
</div>
<?php if (!$from): ?><div class="alert alert-warning">Set the current academic year in School setup first.</div>
<?php elseif (!$to): ?><div class="alert alert-warning">Create next academic year (and its terms) in <a href="<?= e(url('setup/years')) ?>">School setup → Academic years</a> before promoting students. Keep <?= e($from['name']) ?> as the current year until promotion is finished.</div>
<?php else: ?>
<form method="get" action="<?= e(url('promotion')) ?>" class="filters" data-autosubmit>
    <label>From<input value="<?= e($from['name']) ?> (current)" disabled></label>
    <label>Into<select name="to"><?php foreach ($years as $y): ?><option value="<?= (int) $y['id'] ?>"<?= selected($to['id'], $y['id']) ?>><?= e($y['name']) ?></option><?php endforeach; ?></select></label>
</form>
<div class="card" style="padding:0">
    <div class="table-wrap"><table>
        <tr><th>Class</th><th>Students still to process</th><th>Done</th><th></th></tr>
        <?php foreach ($classes as $c): ?>
        <tr>
            <td><strong><?= e($c['name']) ?></strong> <small class="muted"><?= e($c['level_name']) ?></small></td>
            <td><?= (int) $c['waiting'] ? (int) $c['waiting'] : badge('All done', 'good') ?></td>
            <td><?= (int) $c['processed'] ?></td>
            <td><a class="btn btn-sm<?= (int) $c['waiting'] ? ' btn-primary' : '' ?>" href="<?= e(url('promotion/class/' . $c['id'], ['to' => $to['id']])) ?>"><?= (int) $c['waiting'] ? 'Review & promote' : 'View' ?></a></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$classes): ?><tr><td colspan="4" class="muted">No students enrolled in <?= e($from['name']) ?>.</td></tr><?php endif; ?>
    </table></div>
</div>
<?php endif; ?>
