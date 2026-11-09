<?php
/* Parent portal → Pick-up. Expects $kids, $people, $requests, $codes, $newCode. */
$names = [];
foreach ($kids as $k) { $names[(int) $k['id']] = $k['first_name']; }
$waiting = array_filter($requests, fn($r) => $r['status'] === 'waiting');
?>
<div class="page-head"><div><h1>Pick-up</h1></div>
    <div class="actions"><button type="button" class="btn btn-primary" data-help="pk-add">+ Add a trusted person</button></div></div>

<?php if ($newCode): ?>
<div class="card pk-code"><span>Your one-time pick-up code</span><strong><?= e(chunk_split($newCode, 3, ' ')) ?></strong><small>Give this code to the person collecting your child. It works once. Security types it at the gate.</small></div>
<?php endif; ?>

<?php foreach ($waiting as $w): ?>
<div class="card pk-waiting">
    <div><strong><?= e($w['collector_name']) ?></strong> is at the gate to collect <strong><?= e($w['first_name']) ?></strong> (<?= e($w['relationship'] ?: 'relationship not given') ?>, <?= e(date('g:ia', strtotime($w['created_at']))) ?>).</div>
    <form method="post" action="<?= e(url('pickup/' . $w['id'] . '/decide')) ?>" class="pk-buttons"><?= csrf_field() ?>
        <button class="btn btn-good" name="decision" value="approve" type="submit">Allow</button><button class="btn btn-danger" name="decision" value="refuse" type="submit">Refuse</button></form>
</div>
<?php endforeach; ?>

<form id="pk-add" method="post" action="<?= e(url('pickup/people')) ?>" enctype="multipart/form-data" class="card form form-center"<?= old('name') !== '' ? '' : ' hidden' ?>>
    <?= csrf_field() ?>
    <h2>Add a trusted person</h2>
    <fieldset class="ln-chips"><legend>May collect</legend>
        <?php foreach ($kids as $k): ?><label><input type="checkbox" name="pupils[]" value="<?= (int) $k['id'] ?>" checked><span><?= e($k['first_name']) ?></span></label><?php endforeach; ?>
    </fieldset>
    <div class="grid-2"><label class="required">Full name<input name="name" required maxlength="120" value="<?= e(old('name')) ?>"></label><label class="required">Phone<input name="phone" required maxlength="20" inputmode="tel" value="<?= e(old('phone')) ?>"></label></div>
    <div class="grid-2"><label>Relationship<input name="relationship" maxlength="60" placeholder="e.g. Grandmother, driver" value="<?= e(old('relationship')) ?>"></label>
        <label>ID card<select name="id_type"><option value="">—</option><?php foreach (Pickups::ID_TYPES as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?></select></label></div>
    <div class="grid-2"><label>ID number<input name="id_number" maxlength="40"></label><label>Photo (helps Security recognise them)<input type="file" name="photo" accept="image/jpeg,image/png"></label></div>
    <div class="gate-actions"><button class="btn btn-primary" type="submit">Save</button><button type="button" class="btn btn-ghost" data-help="pk-add">Cancel</button></div>
</form>

<div class="card ptabs" data-ptabs style="padding:0">
    <div class="ptab-bar" role="tablist" aria-label="Pick-up">
        <button type="button" role="tab" aria-selected="true" data-ptab="people">Trusted people <span class="ptab-n"><?= count($people) ?></span></button>
        <button type="button" role="tab" aria-selected="false" tabindex="-1" data-ptab="code">One-time code</button>
        <button type="button" role="tab" aria-selected="false" tabindex="-1" data-ptab="history">Pick-ups <span class="ptab-n"><?= count($requests) ?></span></button>
    </div>
    <div class="ptab-panel" data-ppanel="people">
        <?php if ($people): ?><ul class="gate-list pk-people">
            <?php foreach ($people as $p): $for = array_filter(array_map(fn($i) => $names[(int) $i] ?? null, explode(',', (string) $p['pupil_ids']))); ?>
            <li><?php if ($p['photo']): ?><img src="<?= e(url('gate/person/' . $p['id'] . '/photo')) ?>" alt=""><?php else: ?><span class="gate-init sm"><?= e(mb_substr($p['name'], 0, 1)) ?></span><?php endif; ?>
                <span><strong><?= e($p['name']) ?></strong><small><?= e($p['relationship'] ?: 'Trusted person') ?> · <?= e($p['phone']) ?> · may collect <?= e(implode(', ', $for)) ?></small></span>
                <form method="post" action="<?= e(url('pickup/people/' . $p['id'] . '/remove')) ?>" data-confirm="Remove <?= e($p['name']) ?>?"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit">Remove</button></form></li>
            <?php endforeach; ?>
        </ul><?php else: ?><p class="muted fee-none">Nobody added yet. Use <strong>+ Add a trusted person</strong>.</p><?php endif; ?>
    </div>
    <div class="ptab-panel" data-ppanel="code" hidden>
        <form method="post" action="<?= e(url('pickup/codes')) ?>" class="form pk-codeform"><?= csrf_field() ?>
            <div class="grid-3">
                <label>Child<select name="pupil_id"><?php foreach ($kids as $k): ?><option value="<?= (int) $k['id'] ?>"><?= e($k['first_name']) ?></option><?php endforeach; ?></select></label>
                <label>Person's name<input name="for_name" maxlength="120" placeholder="Who is coming"></label>
                <label>Valid for<select name="valid"><option value="today">Today</option><option value="tomorrow">Today and tomorrow</option></select></label>
            </div>
            <button class="btn btn-primary" type="submit">Make a code</button>
        </form>
        <?php if ($codes): ?><ul class="pk-codes"><?php foreach ($codes as $c): ?><li><?= e($c['first_name']) ?> — <?= e($c['for_name'] ?: 'no name') ?> · <?= $c['used_at'] ? 'used ' . e(date('j M g:ia', strtotime($c['used_at']))) : ($c['expires_at'] > date('Y-m-d H:i:s') ? 'valid until ' . e(date('j M g:ia', strtotime($c['expires_at']))) : 'expired') ?></li><?php endforeach; ?></ul><?php endif; ?>
    </div>
    <div class="ptab-panel" data-ppanel="history" hidden>
        <?php if ($requests): ?><div class="table-wrap"><table>
            <thead><tr><th>When</th><th>Child</th><th>Collected by</th><th>Status</th></tr></thead>
            <?php foreach ($requests as $r): [$l, $t] = Pickups::STATUS[$r['status']] ?? [$r['status'], '']; ?>
            <tr><td><?= e(date('D j M, g:ia', strtotime($r['created_at']))) ?></td><td><?= e($r['first_name']) ?></td><td><?= e($r['collector_name']) ?></td><td><?= badge($r['status'] === 'approved' ? 'Approved' : $l, $t ?: 'neutral') ?></td></tr>
            <?php endforeach; ?>
        </table></div><?php else: ?><p class="muted fee-none">No pick-ups recorded yet.</p><?php endif; ?>
    </div>
</div>
