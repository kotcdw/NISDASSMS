<div class="page-center">
<div class="page-head">
    <div><h1>Subject teachers</h1></div>
    <div class="actions">
        <form method="get" class="filters" style="margin:0">
            <select name="class" data-autosubmit aria-label="Class">
                <?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected($c['id'], $current['id'] ?? 0) ?>><?= e($c['name']) ?></option><?php endforeach; ?>
            </select>
        </form>
    </div>
</div>
<?php if ($current): ?>
<form method="post" action="<?= e(url('exams/allocations/' . $current['id'])) ?>">
    <?= csrf_field() ?>
    <div class="card" style="padding:0">
        <div class="table-wrap"><table>
            <tr><th>Subject</th><th>Core (aggregate)</th><th>Subject teacher</th><th>Remove</th></tr>
            <?php if (!$rows): ?><tr><td colspan="4" class="muted">No subjects yet — add them below.</td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td><strong><?= e($r['name']) ?></strong> <small><?= e($r['code']) ?></small></td>
                <td><?= $r['is_core'] ? badge('Core', 'info') : '' ?></td>
                <td>
                    <select name="teacher[<?= (int) $r['id'] ?>]"<?= QuickAddController::attr('staff', ['role' => 'subject_teacher']) ?> aria-label="Teacher for <?= e($r['name']) ?>">
                        <option value="">— class teacher —</option>
                        <?php foreach ($teachers as $t): ?><option value="<?= (int) $t['id'] ?>"<?= selected($t['id'], $r['teacher_id']) ?>><?= e($t['name']) ?></option><?php endforeach; ?>
                    </select>
                </td>
                <td><input type="checkbox" name="remove[]" value="<?= (int) $r['id'] ?>" aria-label="Remove <?= e($r['name']) ?>"></td>
            </tr>
            <?php endforeach; ?>
        </table></div>
    </div>
    <div class="filters">
        <?php if ($missing): ?>
        <label>Add subject
            <select name="add_subject"<?= QuickAddController::attr('subject') ?>><option value="">—</option><?php foreach ($missing as $m): ?><option value="<?= (int) $m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach; ?></select>
        </label>
        <?php endif; ?>
        <button class="btn btn-primary" type="submit">Save <?= e($current['name']) ?></button>
    </div>
    
</form>
<?php endif; ?>
</div>
