<?php $cats = ['creche' => 'Creche', 'kg' => 'Kindergarten', 'primary' => 'Basic 1–6', 'jhs' => 'JHS']; $hm = fn($t) => substr((string) $t, 0, 5); $fg = 'display:grid;grid-template-columns:repeat(auto-fit,minmax(8.5rem,1fr));gap:.6rem;align-items:end'; ?>
<div class="page-head">
    <div><h1>Bell schedule</h1></div>
    <div class="actions">
        <form method="get" action="<?= e(url('timetable/periods')) ?>" class="list-pick" data-autosubmit>
            <select name="cat" aria-label="Section"><?php foreach ($cats as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $cat) ?>><?= e($l) ?></option><?php endforeach; ?></select>
        </form>
        <button type="button" class="btn btn-primary" data-help="new-period" aria-expanded="false">+ Add a row</button>
    </div>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>
<form method="post" action="<?= e(url('timetable/periods')) ?>" data-confirm="Save the bell schedule? Changing a lesson row into a break removes lessons placed in it.">
    <?= csrf_field() ?>
    <input type="hidden" name="cat" value="<?= e($cat) ?>">
    <div id="new-period" class="card form" hidden>
        <h2>Add to the <?= e($cats[$cat]) ?> bell schedule</h2>
        <?php for ($i = 1; $i <= 2; $i++): ?>
        <div style="<?= $fg ?>;margin-bottom:.6rem">
            <label style="margin:0">Name<input name="p[new<?= $i ?>][label]" placeholder="e.g. Period <?= $i === 1 ? '8' : '9' ?>" maxlength="40"></label>
            <label style="margin:0">Starts<input type="time" name="p[new<?= $i ?>][start]"></label>
            <label style="margin:0">Ends<input type="time" name="p[new<?= $i ?>][end]"></label>
            <label style="margin:0">Type<select name="p[new<?= $i ?>][kind]"><?php foreach (Timetable::KINDS as $kk => $kl): ?><option value="<?= $kk ?>"><?= e($kl) ?></option><?php endforeach; ?></select></label>
        </div>
        <?php endfor; ?>
        <p class="hint" style="margin:0 0 .6rem">Leave the second line empty if you are adding only one row.</p>
        <div class="gate-actions"><button class="btn btn-primary" type="submit">Save</button><button type="button" class="btn btn-ghost" data-help="new-period">Cancel</button></div>
    </div>
    <div class="card" style="padding:0">
        <div class="table-wrap"><table>
            <thead><tr><th>Name</th><th>Starts</th><th>Ends</th><th>Type</th><th class="xg-act"></th></tr></thead>
            <tbody>
            <?php foreach ($periods as $p): $k = (int) $p['id']; ?>
            <tr>
                <td><?= e($p['label']) ?></td>
                <td><?= e($hm($p['start_time'])) ?></td>
                <td><?= e($hm($p['end_time'])) ?></td>
                <td><?= e(Timetable::KINDS[$p['kind']] ?? $p['kind']) ?></td>
                <td class="xg-act"><button type="button" class="btn btn-sm" data-help="edit-<?= $k ?>" aria-expanded="false">Edit</button></td>
            </tr>
            <tr id="edit-<?= $k ?>" class="xg-edit" hidden>
                <td colspan="5">
                    <div style="<?= $fg ?>">
                        <label style="margin:0">Name<input name="p[<?= $k ?>][label]" value="<?= e($p['label']) ?>" maxlength="40"></label>
                        <label style="margin:0">Starts<input type="time" name="p[<?= $k ?>][start]" value="<?= e($hm($p['start_time'])) ?>"></label>
                        <label style="margin:0">Ends<input type="time" name="p[<?= $k ?>][end]" value="<?= e($hm($p['end_time'])) ?>"></label>
                        <label style="margin:0">Type<select name="p[<?= $k ?>][kind]"><?php foreach (Timetable::KINDS as $kk => $kl): ?><option value="<?= $kk ?>"<?= selected($kk, $p['kind']) ?>><?= e($kl) ?></option><?php endforeach; ?></select></label>
                    </div>
                    <label style="font-weight:400;margin:.6rem 0"><input type="checkbox" name="p[<?= $k ?>][delete]" value="1"> Remove this row</label>
                    <div class="gate-actions"><button class="btn btn-primary" type="submit">Save</button><button type="button" class="btn btn-ghost" data-help="edit-<?= $k ?>">Cancel</button></div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php if (!$periods): ?><p class="muted" style="padding:1rem;margin:0">No bell schedule for <?= e($cats[$cat]) ?> yet. Use “+ Add a row”.</p><?php endif; ?>
    </div>
</form>
