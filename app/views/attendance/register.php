<div class="page-head">
    <div>
        <div class="breadcrumb"><a href="<?= e(url('attendance', ['date' => $date])) ?>">Attendance</a> /</div>
        <h1><?= e($class['name']) ?> register</h1>
        
    </div>
</div>
<?php if ($blocked): ?>
    <div class="alert alert-warning"><?= e($blocked) ?></div>
<?php elseif (!$pupils): ?>
    <div class="alert alert-info">No students are enrolled in this class yet.</div>
<?php else: ?>
<form method="post" action="<?= e(url('attendance/register/' . $class['id'])) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="date" value="<?= e($date) ?>">
    <div class="filters">
        <button class="btn btn-sm" type="button" data-mark-all="present">Mark all present</button>
        <?php if ($date === date('Y-m-d') && setting('notify_absent', '1') === '1'): ?><span class="hint" style="margin:0">Parents of students marked <strong>absent</strong> get an SMS when you save.</span><?php endif; ?>
    </div>
    <div class="card" style="padding:0">
        <div class="table-wrap"><table class="att-table">
            <tr><th>#</th><th>Student</th><th>Status</th><th>Note</th></tr>
            <?php foreach ($pupils as $i => $p): $leave = $onLeave[(int) $p['id']] ?? null; $cur = $existing[$p['id']]['status'] ?? ($leave ? 'excused' : 'present'); ?>
            <tr<?= $leave ? ' data-on-leave' : '' ?>>
                <td><?= $i + 1 ?></td>
                <td><strong><?= e($p['last_name'] . ', ' . $p['first_name']) ?></strong><br><small><?= e($p['admission_no']) ?></small><?= $leave ? '<br>' . badge('On approved leave: ' . $leave, 'info') : '' ?></td>
                <td>
                    <div class="att-options" role="radiogroup" aria-label="Status for <?= e($p['first_name']) ?>">
                        <?php foreach (Attendance::STATUSES as $k => [$label]): ?>
                            <label class="st-<?= $k ?>"><input type="radio" name="status[<?= (int) $p['id'] ?>]" value="<?= $k ?>"<?= checked($cur === $k) ?>><?= e($label) ?></label>
                        <?php endforeach; ?>
                    </div>
                </td>
                <td><input name="note[<?= (int) $p['id'] ?>]" maxlength="160" value="<?= e($existing[$p['id']]['note'] ?? ($leave && !isset($existing[$p['id']]) ? 'Approved leave' : '')) ?>" placeholder="Optional" style="min-width:140px"></td>
            </tr>
            <?php endforeach; ?>
        </table></div>
    </div>
    <button class="btn btn-primary" type="submit">Save register</button>
    <a class="btn btn-ghost" href="<?= e(url('attendance', ['date' => $date])) ?>">Cancel</a>
</form>
<?php endif; ?>
