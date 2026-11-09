<?php
$waiting = array_filter($pupils, fn($p) => $p['enrol_status'] === 'active' && $p['pupil_status'] === 'active' && !$p['new_class_id']);
$labels = ['promoted' => 'Promoted', 'repeated' => 'Repeating', 'left' => 'Left'];
?>
<div class="page-head">
    <div><p class="breadcrumb"><a href="<?= e(url('promotion', ['to' => $to['id']])) ?>">Year-end promotion</a></p>
    <h1><?= e($class['name']) ?> → <?= e($to['name']) ?></h1></div>
</div>
<?php if ($next && !$nextClasses): ?><div class="alert alert-warning">There is no active class for <?= e($next['name']) ?>. Add one in School setup → Classes.</div><?php endif; ?>
<form method="post" action="<?= e(url('promotion/class/' . $class['id'])) ?>" data-confirm="Apply these decisions? Students will be enrolled in <?= e($to['name']) ?>.">
    <?= csrf_field() ?><input type="hidden" name="to" value="<?= (int) $to['id'] ?>">
    <div class="card" style="padding:0">
        <div class="table-wrap"><table>
            <tr><th>Student</th><th>Report card says</th><th>Fees owed</th><th>Decision for <?= e($to['name']) ?></th></tr>
            <?php foreach ($pupils as $p):
                $isWaiting = $p['enrol_status'] === 'active' && $p['pupil_status'] === 'active' && !$p['new_class_id'];
                $suggest = !$next ? 'graduate' : ($p['promotion'] === 'repeated' ? 'repeat:' . $class['id'] : ($defaultNext ? 'promote:' . $defaultNext : 'skip'));
            ?>
            <tr>
                <td><?= e(full_name($p)) ?><br><small class="muted"><?= e($p['admission_no']) ?></small></td>
                <td><?= $p['promotion'] ? e(['promoted' => 'Promoted', 'repeated' => 'Repeat', 'on_trial' => 'Promoted on trial'][$p['promotion']]) : '<span class="muted">—</span>' ?></td>
                <td><?= (float) $p['owing'] > 0 ? '<span style="color:var(--bad)">' . e(number_format((float) $p['owing'], 2)) . '</span>' : '' ?></td>
                <td>
                <?php if ($isWaiting): ?>
                    <select name="decision[<?= (int) $p['id'] ?>]">
                        <?php if ($next): ?><optgroup label="Promote to"><?php foreach ($nextClasses as $c): ?><option value="promote:<?= (int) $c['id'] ?>"<?= selected($suggest, 'promote:' . $c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?></optgroup><?php endif; ?>
                        <optgroup label="Repeat in"><?php foreach ($sameClasses as $c): ?><option value="repeat:<?= (int) $c['id'] ?>"<?= selected($suggest, 'repeat:' . $c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?></optgroup>
                        <optgroup label="Other">
                            <?php if (!$next): ?><option value="graduate"<?= selected($suggest, 'graduate') ?>>Completed JHS (leaves school)</option><?php endif; ?>
                            <option value="leave">Leaving the school</option>
                            <option value="skip"<?= selected($suggest, 'skip') ?>>Decide later</option>
                        </optgroup>
                    </select>
                <?php elseif ($p['new_class_id']): ?><?= badge($labels[$p['enrol_status']] ?? 'Moved', 'good') ?> → <?= e($p['new_class_name']) ?>
                <?php else: ?><?= badge($p['pupil_status'] === 'graduated' ? 'Completed JHS' : ($labels[$p['enrol_status']] ?? ucfirst($p['pupil_status']))) ?><?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table></div>
    </div>
    <?php if ($waiting): ?><button class="btn btn-primary" type="submit">Apply for <?= count($waiting) ?> student(s)</button><?php endif; ?>
</form>
<?php if (count($waiting) < count($pupils) && !(int) $to['is_current']): ?>
<form method="post" action="<?= e(url('promotion/class/' . $class['id'] . '/undo')) ?>" style="margin-top:1rem" data-confirm="Undo the promotion of this class? Students go back to <?= e($class['name']) ?> for <?= e($from['name']) ?>."><?= csrf_field() ?><input type="hidden" name="to" value="<?= (int) $to['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit">Undo promotion for this class</button></form>
<?php endif; ?>
