<?php $lvl = fn(string $s) => implode(', ', array_map(fn($k) => Curriculum::LEVELS[$k] ?? $k, array_filter(explode(',', $s)))); $nc = $edit ? 5 : 4; ?>
<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('curriculum')) ?>">Subject Management</a> /</div>
    <h1>Curriculum strands</h1></div>
    <div class="actions">
        <?php if ($subjects): ?>
        <form method="get" action="<?= e(url('curriculum/strands')) ?>" class="list-pick" data-autosubmit>
            <select name="subject" aria-label="Subject"><?php foreach ($subjects as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected((int) $s['id'], (int) ($subject['id'] ?? 0)) ?>><?= e($s['name']) ?></option><?php endforeach; ?></select>
        </form>
        <?php endif; ?>
        <?php if ($edit && $subject): ?>
            <?php if ($tree): ?><button type="button" class="btn btn-ghost" data-help="new-sub" aria-expanded="false">+ Add a sub-strand</button><?php endif; ?>
            <button type="button" class="btn btn-primary" data-help="new-strand" aria-expanded="false">+ Add a strand</button>
        <?php endif; ?>
    </div>
</div>

<?php if (!$subject): ?>
    <div class="alert alert-info">No subjects yet.</div>
<?php else: ?>
    <?php if ($edit): ?>
    <form id="new-strand" method="post" action="<?= e(url('curriculum/strands')) ?>" class="form card" hidden>
        <?= csrf_field() ?><input type="hidden" name="subject_id" value="<?= (int) $subject['id'] ?>"><input type="hidden" name="action" value="add_strand">
        <h2>Add a strand to <?= e($subject['name']) ?></h2>
        <label class="required">Strand name<input name="name" maxlength="150" required></label>
        <p style="margin:0 0 .8rem;font-size:.85rem">Taught at:
            <?php foreach (Curriculum::LEVELS as $k => $l): ?><label style="display:inline;margin-right:.8rem;font-weight:400"><input type="checkbox" name="levels[]" value="<?= $k ?>"<?= in_array($k, explode(',', $subject['applies_to']), true) ? ' checked' : '' ?>><?= e($l) ?></label><?php endforeach; ?></p>
        <div class="gate-actions"><button class="btn btn-primary" type="submit">Save</button><button type="button" class="btn btn-ghost" data-help="new-strand">Cancel</button></div>
    </form>
    <?php if ($tree): ?>
    <form id="new-sub" method="post" action="<?= e(url('curriculum/strands')) ?>" class="form card" hidden>
        <?= csrf_field() ?><input type="hidden" name="subject_id" value="<?= (int) $subject['id'] ?>"><input type="hidden" name="action" value="add_sub">
        <h2>Add a sub-strand</h2>
        <label>Under strand<select name="parent_id"><?php foreach ($tree as $st): ?><option value="<?= (int) $st['id'] ?>"><?= e($st['name'] . ' (' . $lvl($st['levels']) . ')') ?></option><?php endforeach; ?></select></label>
        <label class="required">Sub-strand name<input name="name" maxlength="150" required></label>
        <div class="gate-actions"><button class="btn btn-primary" type="submit">Save</button><button type="button" class="btn btn-ghost" data-help="new-sub">Cancel</button></div>
    </form>
    <?php endif; ?>
    <?php endif; ?>

    <?php if (!$tree): ?><div class="alert alert-info">No strands for <?= e($subject['name']) ?> yet.</div><?php else: ?>
    <?php if ($edit): ?><form method="post" action="<?= e(url('curriculum/strands')) ?>" class="form"><?= csrf_field() ?><input type="hidden" name="subject_id" value="<?= (int) $subject['id'] ?>"><input type="hidden" name="action" value="save"><?php endif; ?>
    <div class="card" style="padding:0">
        <div class="table-wrap"><table>
            <thead><tr><th>Strand</th><th>Taught at</th><th>Sub-strands</th><th>Status</th><?php if ($edit): ?><th class="xg-act"></th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($tree as $st): $sid = (int) $st['id']; $subs = array_filter($st['subs'], fn($x) => (int) $x['is_active']); ?>
            <tr>
                <td><strong><?= e($st['name']) ?></strong></td>
                <td><?= e($lvl($st['levels'])) ?></td>
                <td style="min-width:12rem"><?= $subs ? e(implode(', ', array_column($subs, 'name'))) : '<span class="muted">None</span>' ?></td>
                <td><?= (int) $st['is_active'] ? badge('In use', 'good') : badge('Not in use') ?></td>
                <?php if ($edit): ?><td class="xg-act"><button type="button" class="btn btn-sm" data-help="edit-<?= $sid ?>" aria-expanded="false">Edit</button></td><?php endif; ?>
            </tr>
            <?php if ($edit): ?>
            <tr id="edit-<?= $sid ?>" class="xg-edit" hidden>
                <td colspan="<?= $nc ?>">
                    <div style="position:sticky;left:0;max-width:calc(100vw - 3rem)">
                    <div style="display:grid;grid-template-columns:5rem minmax(0,1fr);gap:.6rem;align-items:end">
                        <label style="margin:0">Order<input type="number" name="s[<?= $sid ?>][sort_order]" value="<?= (int) $st['sort_order'] ?>"></label>
                        <label style="margin:0">Strand<input name="s[<?= $sid ?>][name]" value="<?= e($st['name']) ?>" maxlength="150" style="font-weight:600"></label>
                    </div>
                    <label style="font-weight:400;margin:.5rem 0"><input type="checkbox" name="s[<?= $sid ?>][is_active]" value="1"<?= (int) $st['is_active'] ? ' checked' : '' ?>> In use</label>
                    <p style="margin:.5rem 0;font-size:.85rem">Taught at:
                        <?php foreach (Curriculum::LEVELS as $k => $l): ?><label style="display:inline;margin-right:.8rem;font-weight:400"><input type="checkbox" name="s[<?= $sid ?>][levels][]" value="<?= $k ?>"<?= in_array($k, explode(',', $st['levels']), true) ? ' checked' : '' ?>><?= e($l) ?></label><?php endforeach; ?>
                    </p>
                    <?php if ($st['subs']): ?>
                    <table style="margin:.4rem 0 .8rem">
                        <thead><tr><th style="width:5rem">Order</th><th>Sub-strand</th><th style="width:4.5rem">In use</th></tr></thead>
                        <tbody>
                        <?php foreach ($st['subs'] as $sub): ?>
                            <tr><td><input type="number" name="s[<?= (int) $sub['id'] ?>][sort_order]" value="<?= (int) $sub['sort_order'] ?>" aria-label="Order" style="margin:0"></td>
                                <td><input name="s[<?= (int) $sub['id'] ?>][name]" value="<?= e($sub['name']) ?>" maxlength="150" aria-label="Sub-strand" style="margin:0"></td>
                                <td><input type="checkbox" name="s[<?= (int) $sub['id'] ?>][is_active]" value="1"<?= (int) $sub['is_active'] ? ' checked' : '' ?> aria-label="In use"></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                    <div class="gate-actions"><button class="btn btn-primary" type="submit">Save</button><button type="button" class="btn btn-ghost" data-help="edit-<?= $sid ?>">Cancel</button></div>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
    <?php if ($edit): ?></form><?php endif; ?>
    <?php endif; ?>

    <?php if ($edit && $tree): ?>
    <details class="sum card"><summary>Remove a strand or sub-strand</summary>
        <form method="post" action="<?= e(url('curriculum/strands')) ?>" class="form" style="margin-top:.6rem" data-confirm="Remove it? A strand's sub-strands are removed too. Topics already planned keep their text.">
            <?= csrf_field() ?><input type="hidden" name="subject_id" value="<?= (int) $subject['id'] ?>"><input type="hidden" name="action" value="delete">
            <label>Choose<select name="id"><?php foreach ($tree as $st): ?><option value="<?= (int) $st['id'] ?>"><?= e($st['name']) ?> (strand)</option><?php foreach ($st['subs'] as $sub): ?><option value="<?= (int) $sub['id'] ?>">&nbsp;&nbsp;— <?= e($sub['name']) ?></option><?php endforeach; ?><?php endforeach; ?></select></label>
            <button class="btn btn-danger btn-sm" type="submit">Remove</button>
        </form>
    </details>
    <?php endif; ?>
<?php endif; ?>
