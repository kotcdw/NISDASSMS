<div class="page-head">
    <div><h1>Make term bills</h1></div>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>
<?php if (!$selTerm): ?><p class="muted">Set up academic years and terms first.</p><?php else: ?>
<form method="post" action="<?= e(url('fees/generate')) ?>" class="card" data-confirm="Create bills for the selected classes?">
    <?= csrf_field() ?>
    <input type="hidden" name="term_id" value="<?= (int) $selTerm['id'] ?>">
    <p><strong><?= e($selTerm['name'] . ' · ' . $selTerm['year_name']) ?></strong></p>
    <div class="table-wrap"><table>
        <tr><th><input type="checkbox" data-check-all="classes[]" aria-label="All"></th><th>Class</th><th>Students</th><th>Already billed</th><th style="text-align:right">Fees per student (before discounts)</th></tr>
        <?php foreach ($classes as $c): $todo = (int) $c['pupils'] - (int) $c['billed']; ?>
        <tr>
            <td><input type="checkbox" name="classes[]" value="<?= (int) $c['id'] ?>"<?= $todo > 0 ? '' : ' disabled' ?>></td>
            <td><?= e($c['name']) ?> <small class="muted"><?= e($c['level_name']) ?></small></td>
            <td><?= (int) $c['pupils'] ?></td>
            <td><?= (int) $c['billed'] ?><?= $todo > 0 && $c['billed'] ? ' <small class="muted">(' . $todo . ' to bill)</small>' : '' ?></td>
            <td style="text-align:right"><?= e(Fees::money($c['per_pupil'])) ?></td>
        </tr>
        <?php endforeach; ?>
    </table></div>
    <div class="grid-3" style="margin-top:1rem">
        <label>Due date<input type="date" name="due_date" value="<?= e(date('Y-m-d', strtotime('+' . (int) setting('fee_due_days', '21') . ' days'))) ?>"></label>
        <label style="font-weight:400;align-self:end"><input type="checkbox" name="sibling" value="1" checked> Apply sibling discount automatically</label>
        <label style="font-weight:400;align-self:end"><input type="checkbox" name="sms" value="1"> SMS each bill to the parent with a pay link</label>
    </div>
    <button class="btn btn-primary" type="submit">Generate bills</button>
</form>
<?php endif; ?>
