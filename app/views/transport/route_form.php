<?php $v = fn($k, $d = '') => old($k, $r[$k] ?? $d); $manage = can('transport.manage'); ?>
<div class="page-head"><div><p class="breadcrumb"><a href="<?= e(url('transport')) ?>">Transport</a></p><h1><?= e($title) ?></h1></div>
<?php if ($r): ?><div class="actions"><a class="btn" href="<?= e(url('transport/routes/' . $r['id'] . '/print')) ?>" target="_blank">Print bus list</a></div><?php endif; ?></div>
<div class="cols">
<div>
<?php if ($r): ?>
<div class="card" style="padding:0">
    <h2 style="padding:1rem 1rem 0">Riders this year (<?= count($riders) ?> / <?= (int) $r['capacity'] ?>)</h2>
    <div class="table-wrap"><table>
        <tr><th>Student</th><th>Class</th><th>Stop</th><th>Trips</th><th>Parent</th><th></th></tr>
        <?php foreach ($riders as $p): ?>
        <tr><td><?= e($p['first_name'] . ' ' . $p['last_name']) ?><br><small class="muted"><?= e($p['admission_no']) ?></small></td><td><?= e($p['class_name'] ?? '') ?></td>
            <td><?= e($p['stop'] ?? '') ?></td><td><?= e(TransportController::TRIPS[$p['trips']]) ?></td>
            <td><?= e($p['guardian_name'] ?? '') ?><br><small class="muted"><?= e($p['guardian_phone'] ?? '') ?></small></td>
            <td><?php if ($manage): ?><form method="post" action="<?= e(url('transport/assignments/' . $p['id'] . '/end')) ?>" data-confirm="Take this student off the bus?"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" type="submit">Remove</button></form><?php endif; ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$riders): ?><tr><td colspan="6" class="muted">No students on this route yet.</td></tr><?php endif; ?>
    </table></div>
    <?php if ($manage && (int) $r['is_active']): ?>
    <form method="post" action="<?= e(url('transport/routes/' . $r['id'] . '/assign')) ?>" class="form" style="padding:1rem;border-top:1px solid var(--line)">
        <?= csrf_field() ?>
        <div class="grid-3">
            <label>Admission no.<input name="admission_no" required placeholder="NIS/2026/0001"></label>
            <label>Stop<input name="stop" maxlength="80" list="stops"></label>
            <label>Trips<select name="trips"><?php foreach (TransportController::TRIPS as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select></label>
        </div>
        <datalist id="stops"><?php foreach (array_filter(array_map('trim', explode(',', (string) $r['stops']))) as $s): ?><option value="<?= e($s) ?>"><?php endforeach; ?></datalist>
        <button class="btn" type="submit">Add student to this bus</button>
    </form>
    <?php endif; ?>
</div>
<?php endif; ?>
</div>
<div>
<?php if ($manage): ?>
<form method="post" action="<?= e(url($r ? 'transport/routes/' . $r['id'] : 'transport/routes')) ?>" class="card form">
    <?= csrf_field() ?>
    <h2>Route details</h2>
    <label class="required">Route name<input name="name" required maxlength="80" value="<?= e($v('name')) ?>" placeholder="e.g. Route A — Kasoa"><?= field_error('name') ?></label>
    <label>Stops (separated by commas)<textarea name="stops" rows="2" maxlength="500"><?= e($v('stops')) ?></textarea></label>
    <div class="grid-2">
        <label>Bus / registration no.<input name="vehicle" maxlength="60" value="<?= e($v('vehicle')) ?>"></label>
        <label class="required">Seats<input type="number" name="capacity" min="1" max="200" value="<?= e($v('capacity', '30')) ?>"><?= field_error('capacity') ?></label>
        <label>Driver<input name="driver_name" maxlength="100" value="<?= e($v('driver_name')) ?>"></label>
        <label>Driver's phone<input name="driver_phone" value="<?= e($v('driver_phone')) ?>"><?= field_error('driver_phone') ?></label>
        <?php if (TransportController::hasPapers()): $exp = fn($k) => ($d = $v($k)) && $d <= date('Y-m-d', strtotime('+30 days')) ? ' ' . badge($d < date('Y-m-d') ? 'Expired' : 'Expires soon', $d < date('Y-m-d') ? 'bad' : 'warn') : ''; ?>
        <label>Driver's licence no.<input name="driver_licence_no" value="<?= e($v('driver_licence_no')) ?>" maxlength="40"></label>
        <label>Licence expires<?= $exp('driver_licence_expiry') ?><input type="date" name="driver_licence_expiry" value="<?= e($v('driver_licence_expiry')) ?>"></label>
        <label>Roadworthy certificate expires<?= $exp('roadworthy_expiry') ?><input type="date" name="roadworthy_expiry" value="<?= e($v('roadworthy_expiry')) ?>"></label>
        <label>Insurance expires<?= $exp('insurance_expiry') ?><input type="date" name="insurance_expiry" value="<?= e($v('insurance_expiry')) ?>"></label>
        <?php endif; ?>
        <label>Attendant<input name="attendant_name" maxlength="100" value="<?= e($v('attendant_name')) ?>"></label>
        <label>Attendant's phone<input name="attendant_phone" value="<?= e($v('attendant_phone')) ?>"><?= field_error('attendant_phone') ?></label>
    </div>
    <label>Bus fee per term (GH₵)<input name="fee_per_term" inputmode="decimal" value="<?= e($v('fee_per_term', '0')) ?>"><?= field_error('fee_per_term') ?></label>
    <?php if ($r): ?><label style="font-weight:400"><input type="checkbox" name="is_active" value="1"<?= checked((int) $r['is_active'] === 1) ?>> Route is running</label><?php endif; ?>
    <button class="btn btn-primary" type="submit">Save route</button>
</form>
<?php elseif ($r): ?>
<div class="card"><p><strong><?= e($r['vehicle'] ?? '') ?></strong></p><p class="hint">Driver: <?= e($r['driver_name'] ?? '—') ?> <?= e($r['driver_phone'] ?? '') ?><br>Stops: <?= e($r['stops'] ?? '—') ?></p></div>
<?php endif; ?>
</div>
</div>
