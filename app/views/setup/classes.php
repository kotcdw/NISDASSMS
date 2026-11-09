<?php $addOpen = !empty($GLOBALS['__old']) || !empty($GLOBALS['__errors']); ?>
<div class="page-head"><div><h1>Classes</h1></div><div class="actions"><button type="button" class="btn btn-primary" data-help="add-class" aria-expanded="<?= $addOpen ? 'true' : 'false' ?>">+ Add a class</button></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>

<div id="add-class" class="card form-center"<?= $addOpen ? '' : ' hidden' ?>>
    <h2>Add a class / stream</h2>
    <form method="post" action="<?= e(url('setup/classes')) ?>" class="form">
        <?= csrf_field() ?>
        <div class="grid-3">
            <label class="required">Level
                <select name="level_id" required>
                    <?php foreach ($levels as $l): ?><option value="<?= (int) $l['id'] ?>"<?= selected(old('level_id'), $l['id']) ?>><?= e($l['name']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label class="required">Class name <input name="name" placeholder="Basic 4B" required value="<?= e(old('name')) ?>"></label>
            <label>Section <input name="section" placeholder="B" maxlength="10" value="<?= e(old('section')) ?>"></label>
        </div>
        <div class="grid-2">
            <label>Class teacher
                <select name="class_teacher_id"<?= QuickAddController::attr('staff', ['role' => 'class_teacher']) ?>><option value="">— none —</option>
                    <?php foreach ($teachers as $t): ?><option value="<?= (int) $t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label class="required">Capacity <input type="number" name="capacity" min="1" max="200" value="40"></label>
        </div>
        <div class="gate-actions"><button class="btn btn-primary" type="submit">Save</button><button type="button" class="btn btn-ghost" data-help="add-class">Cancel</button></div>
    </form>
</div>

<div class="card" style="padding:0">
    <div class="table-wrap"><table>
        <thead><tr><th>Level</th><th>Class name</th><th>Class teacher</th><th class="num">Capacity</th><th class="num">Students</th><th>Active</th><th class="xg-act"></th></tr></thead>
        <tbody>
        <?php foreach ($classes as $c): $fid = 'cls' . (int) $c['id']; ?>
        <tr>
            <td><?= e($c['level_name']) ?></td>
            <td><?= e($c['name']) ?></td>
            <td><?= $c['teacher_name'] ? e($c['teacher_name']) : '<span class="muted">—</span>' ?></td>
            <td class="num"><?= (int) $c['capacity'] ?></td>
            <td class="num"><?= (int) $c['pupils'] ?></td>
            <td><?= $c['is_active'] ? badge('Active', 'good') : badge('Inactive', 'neutral') ?></td>
            <td class="xg-act"><button type="button" class="btn btn-sm" data-help="edit-<?= $fid ?>" aria-expanded="false">Edit</button></td>
        </tr>
        <tr id="edit-<?= $fid ?>" class="xg-edit" hidden><td colspan="7">
            <form method="post" action="<?= e(url('setup/classes/' . $c['id'])) ?>" class="form" style="margin:0"><?= csrf_field() ?>
                <input type="hidden" name="level_id" value="<?= (int) $c['level_id'] ?>">
                <div class="grid-3">
                    <label class="required">Class name <input name="name" value="<?= e($c['name']) ?>" required></label>
                    <label>Section <input name="section" value="<?= e($c['section'] ?? '') ?>" maxlength="10"></label>
                    <label class="required">Capacity <input type="number" min="1" max="200" name="capacity" value="<?= (int) $c['capacity'] ?>"></label>
                </div>
                <div class="grid-2">
                    <label>Class teacher
                        <select name="class_teacher_id"<?= QuickAddController::attr('staff', ['role' => 'class_teacher']) ?>>
                            <option value="">— none —</option>
                            <?php foreach ($teachers as $t): ?><option value="<?= (int) $t['id'] ?>"<?= selected($t['id'], $c['class_teacher_id']) ?>><?= e($t['name']) ?></option><?php endforeach; ?>
                        </select>
                    </label>
                    <label style="align-self:end;font-weight:400"><input type="checkbox" name="is_active" value="1"<?= checked($c['is_active']) ?>> Active</label>
                </div>
                <div class="gate-actions"><button class="btn btn-primary" type="submit">Save</button><button type="button" class="btn btn-ghost" data-help="edit-<?= $fid ?>">Cancel</button></div>
            </form>
        </td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
