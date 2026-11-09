<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('resources')) ?>">Rooms &amp; resources</a> /</div>
    <h1><?= e($room['name']) ?></h1>
    <p class="muted"><?= e(Resources::KINDS[$room['kind']] ?? $room['kind']) ?> · <?= (int) $room['capacity'] ?> seats<?= $room['class_name'] ? ' · ' . e($room['class_name']) . ' (' . (int) $roll . ' students)' : '' ?></p></div>
</div>

<?php if ($room['class_id'] && $roll > (int) $room['capacity'] && $room['capacity']): ?><div class="alert alert-warning"><?= e($room['class_name']) ?> has <?= (int) $roll ?> students but the room seats <?= (int) $room['capacity'] ?>.</div><?php endif; ?>

<?php if ($edit): ?><form method="post" action="<?= e(url('resources/rooms/' . $room['id'] . '/items/save')) ?>"><?= csrf_field() ?><?php endif; ?>
<div class="card" style="padding:0"><div class="table-wrap"><table>
    <thead><tr><th>Item</th><th>Kind</th><th class="num">Good</th><th class="num">Needs repair</th><th class="num">Broken</th><th class="num">Needed<br><small>per student / fixed</small></th><th>Short</th><th>Notes</th><?= $edit ? '<th>Remove</th>' : '' ?></tr></thead>
    <tbody>
    <?php foreach ($items as $i): $id = (int) $i['id']; ?>
        <tr>
            <td><strong><?= e($i['name']) ?></strong></td>
            <td><?= e(Resources::CATEGORIES[$i['category']] ?? $i['category']) ?></td>
            <?php if ($edit): ?>
                <td><input type="number" min="0" name="it[<?= $id ?>][qty_good]" value="<?= (int) $i['qty_good'] ?>" style="width:80px;margin:0" aria-label="Good"></td>
                <td><input type="number" min="0" name="it[<?= $id ?>][qty_repair]" value="<?= (int) $i['qty_repair'] ?>" style="width:80px;margin:0" aria-label="Needs repair"></td>
                <td><input type="number" min="0" name="it[<?= $id ?>][qty_bad]" value="<?= (int) $i['qty_bad'] ?>" style="width:80px;margin:0" aria-label="Broken"></td>
                <td style="white-space:nowrap"><input type="number" step="0.01" min="0" name="it[<?= $id ?>][per_pupil]" value="<?= e((string) ($i['per_pupil'] !== null ? rtrim(rtrim((string) $i['per_pupil'], '0'), '.') : '')) ?>" placeholder="per pupil" style="width:85px;margin:0;display:inline-block" aria-label="Per student">
                    <input type="number" min="0" name="it[<?= $id ?>][needed]" value="<?= e((string) ($i['needed'] ?? '')) ?>" placeholder="fixed" style="width:75px;margin:0;display:inline-block" aria-label="Fixed number needed">
                    <input type="hidden" name="it[<?= $id ?>][subject_id]" value="<?= (int) $i['subject_id'] ?>"></td>
            <?php else: ?>
                <td class="num"><?= (int) $i['qty_good'] ?></td><td class="num"><?= (int) $i['qty_repair'] ?></td><td class="num"><?= (int) $i['qty_bad'] ?></td>
                <td class="num"><?= $i['need'] === null ? '—' : (int) $i['need'] ?></td>
            <?php endif; ?>
            <td><?= $i['need'] === null ? '<span class="muted">—</span>' : ($i['short'] ? badge($i['short'] . ' short of ' . $i['need'], 'bad') : badge('OK (' . $i['need'] . ')', 'good')) ?></td>
            <td><?= $edit ? '<input name="it[' . $id . '][notes]" value="' . e((string) $i['notes']) . '" maxlength="255" style="margin:0" aria-label="Notes">' : e((string) $i['notes']) ?></td>
            <?php if ($edit): ?><td><input type="checkbox" name="delete[]" value="<?= $id ?>" aria-label="Remove"></td><?php endif; ?>
        </tr>
    <?php endforeach; ?>
    <?php if (!$items): ?><tr><td colspan="9" class="muted">Nothing recorded for this room yet.</td></tr><?php endif; ?>
    </tbody>
</table></div></div>
<?php if ($edit && $items): ?><button class="btn btn-primary" type="submit">Save quantities</button> <span class="muted" style="font-size:.85rem">"Per student" works out what is needed from the class size (e.g. 1 desk each, or 0.5 for dual desks); "fixed" is for things like one cupboard.</span><?php endif; ?>
<?php if ($edit): ?></form><?php endif; ?>

<?php if ($edit): ?>
<div class="grid-2" style="margin-top:1.25rem">
    <form method="post" action="<?= e(url('resources/rooms/' . $room['id'] . '/items')) ?>" class="form card">
        <?= csrf_field() ?>
        <h2>Add an item</h2>
        <div class="grid-2">
            <label class="required">Item<input name="name" maxlength="120" required placeholder="e.g. Ceiling fan"></label>
            <label>Kind<select name="category"><?php foreach (Resources::CATEGORIES as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?></select></label>
            <label>Subject (textbooks)<select name="subject_id"><option value="">—</option><?php foreach ($subjects as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></label>
            <label>Good<input type="number" name="qty_good" min="0" value="0"></label>
            <label>Needs repair<input type="number" name="qty_repair" min="0" value="0"></label>
            <label>Broken<input type="number" name="qty_bad" min="0" value="0"></label>
            <label>Needed per student<input type="number" step="0.01" min="0" name="per_pupil" placeholder="e.g. 1"></label>
            <label>Or a fixed number needed<input type="number" min="0" name="needed"></label>
        </div>
        <button class="btn" type="submit">Add item</button>
    </form>
    <div>
        <form method="post" action="<?= e(url('resources/rooms/' . $room['id'] . '/standard')) ?>" class="card">
            <?= csrf_field() ?>
            <h2>Standard classroom items</h2>
            <button class="btn" type="submit">Add standard items</button>
        </form>
        <form method="post" action="<?= e(url('resources/rooms/' . $room['id'])) ?>" class="form card">
            <?= csrf_field() ?>
            <h2>Room details</h2>
            <label class="required">Name<input name="name" maxlength="60" value="<?= e($room['name']) ?>" required></label>
            <div class="grid-2">
                <label>Kind<select name="kind"><?php foreach (Resources::KINDS as $k => $l): ?><option value="<?= $k ?>"<?= selected($k, $room['kind']) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
                <label>Seats (capacity)<input type="number" name="capacity" min="0" max="2000" value="<?= (int) $room['capacity'] ?>"></label>
            </div>
            <label>Class using it<select name="class_id"><option value="">— none —</option><?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected($c['id'], $room['class_id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></label>
            <label>Notes<input name="notes" maxlength="255" value="<?= e((string) $room['notes']) ?>"></label>
            <label><input type="checkbox" name="is_active" value="1"<?= (int) $room['is_active'] ? ' checked' : '' ?>> In use</label>
            <button class="btn" type="submit">Save room</button>
        </form>
        <form method="post" action="<?= e(url('resources/rooms/' . $room['id'] . '/delete')) ?>" data-confirm="Delete this room and everything recorded in it?"><?= csrf_field() ?><button class="btn btn-danger btn-sm" type="submit">Delete room</button></form>
    </div>
</div>
<?php endif; ?>
