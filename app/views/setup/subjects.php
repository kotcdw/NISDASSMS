<?php $groups = ['creche' => 'Creche', 'kg' => 'KG', 'primary' => 'Basic 1–6', 'jhs' => 'JHS']; ?>
<div class="page-head"><div><h1>Subjects</h1></div><div class="actions"><button type="button" class="btn btn-primary" data-help="add-subject" aria-expanded="false">+ Add subject</button></div></div>

<div id="add-subject" class="card form-center" hidden>
    <h2>Add subject</h2>
    <form method="post" action="<?= e(url('setup/subjects')) ?>" class="form">
        <?= csrf_field() ?>
        <div class="grid-2">
            <label class="required">Name <input name="name" required></label>
            <label class="required">Code <input name="code" required maxlength="12"></label>
        </div>
        <p style="margin:0 0 .8rem">
            <?php foreach ($groups as $k => $label): ?>
                <label style="display:inline-block;margin-right:1rem;font-weight:400"><input type="checkbox" name="applies_to[]" value="<?= $k ?>"><?= e($label) ?></label>
            <?php endforeach; ?>
        </p>
        <div class="gate-actions"><button class="btn btn-primary" type="submit">Add subject</button><button type="button" class="btn btn-ghost" data-help="add-subject">Cancel</button></div>
    </form>
</div>

<div class="card" style="padding:0">
    <div class="table-wrap"><table>
        <thead><tr><th>Subject</th><th>Code</th><th>Taught in</th><th title="Counted in the JHS aggregate">Core</th><th>Active</th><th class="xg-act"></th></tr></thead>
        <tbody>
        <?php foreach ($subjects as $s): $fid = 'sub' . (int) $s['id']; $on = explode(',', $s['applies_to']); $taught = array_values(array_intersect_key($groups, array_flip($on))); ?>
        <tr>
            <td><?= e($s['name']) ?></td>
            <td><code><?= e($s['code']) ?></code></td>
            <td><?= $taught ? e(implode(', ', $taught)) : '<span class="muted">—</span>' ?></td>
            <td><?= !empty($s['is_core']) ? 'Yes' : '' ?></td>
            <td><?= $s['is_active'] ? badge('Active', 'good') : badge('Inactive', 'neutral') ?></td>
            <td class="xg-act"><button type="button" class="btn btn-sm" data-help="edit-<?= $fid ?>" aria-expanded="false">Edit</button></td>
        </tr>
        <tr id="edit-<?= $fid ?>" class="xg-edit" hidden><td colspan="6">
            <form method="post" action="<?= e(url('setup/subjects/' . $s['id'])) ?>" class="form" style="margin:0"><?= csrf_field() ?>
                <div class="grid-2">
                    <label class="required">Name <input name="name" value="<?= e($s['name']) ?>" required></label>
                    <label class="required">Code <input name="code" value="<?= e($s['code']) ?>" required maxlength="12"></label>
                </div>
                <p style="margin:0 0 .6rem"><strong style="font-size:.9rem;margin-right:.6rem">Taught in</strong>
                    <?php foreach ($groups as $k => $label): ?>
                        <label style="display:inline-block;margin:0 1rem 0 0;font-weight:400"><input type="checkbox" name="applies_to[]" value="<?= $k ?>"<?= checked(in_array($k, $on, true)) ?>><?= e($label) ?></label>
                    <?php endforeach; ?>
                </p>
                <p style="margin:0 0 .8rem">
                    <label style="display:inline-block;margin:0 1rem 0 0;font-weight:400"><input type="checkbox" name="is_core" value="1"<?= checked($s['is_core'] ?? 0) ?>> Core subject (counted in the JHS aggregate)</label>
                    <label style="display:inline-block;margin:0;font-weight:400"><input type="checkbox" name="is_active" value="1"<?= checked($s['is_active']) ?>> Active</label>
                </p>
                <div class="gate-actions"><button class="btn btn-primary" type="submit">Save</button><button type="button" class="btn btn-ghost" data-help="edit-<?= $fid ?>">Cancel</button></div>
            </form>
        </td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
