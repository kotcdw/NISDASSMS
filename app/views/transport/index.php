<?php if ($term && $unbilled && can('fees.manage')): ?>
<form method="post" action="<?= e(url('transport/bill')) ?>" class="alert alert-info form" data-confirm="Create bus fee bills for <?= (int) $unbilled ?> student(s) for <?= e($term['name']) ?>?" style="display:flex;gap:.8rem;align-items:flex-end;flex-wrap:wrap">
    <?= csrf_field() ?>
    <span><?= (int) $unbilled ?> bus rider(s) have no bus fee bill for <?= e($term['name']) ?> yet.</span>
    <label style="margin:0">Due date<input type="date" name="due_date"></label>
    <button class="btn btn-primary btn-sm" type="submit">Bill bus fees</button>
</form>
<?php endif; ?>
<div class="xgrid-box" data-xgrid="transport-routes" data-xgrid-file="bus-routes">
<div class="page-head">
    <div><h1>Transport</h1></div>
    <div class="actions"><?= export_grid_menu() ?><?php if (can('transport.manage')): ?><a class="btn btn-primary" href="<?= e(url('transport/routes/create')) ?>">+ New route</a><?php endif; ?></div>
</div>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['route' => ['Route'], 'running' => ['Running'], 'stops' => ['Stops'], 'bus' => ['Bus'], 'driver' => ['Driver'], 'phone' => ['Driver phone'], 'riders' => ['Riders', 'num', 'num'], 'seats' => ['Seats', 'num', 'num'], 'full' => ['Full'], 'fee' => ['Fee per term', 'num', 'num']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($routes as $r): $full = (int) $r['riders'] >= (int) $r['capacity']; ?>
    <tr<?= (int) $r['is_active'] ? '' : ' class="muted"' ?>>
        <td data-c="route"><a href="<?= e(url('transport/routes/' . $r['id'])) ?>"><strong><?= e($r['name']) ?></strong></a></td>
        <td data-c="running"><?= (int) $r['is_active'] ? 'Yes' : badge('Not running') ?></td>
        <td data-c="stops"><?= $r['stops'] ? e(mb_strimwidth($r['stops'], 0, 90, '…')) : '' ?></td>
        <td data-c="bus"><?= e($r['vehicle'] ?? '—') ?></td>
        <td data-c="driver"><?= e($r['driver_name'] ?? '—') ?></td>
        <td data-c="phone"><?= e($r['driver_phone'] ?? '') ?></td>
        <td data-c="riders" class="num"><?= (int) $r['riders'] ?></td>
        <td data-c="seats" class="num"><?= (int) $r['capacity'] ?></td>
        <td data-c="full"><?= $full ? badge('Full', 'warn') : '' ?></td>
        <td data-c="fee" class="num" data-s="<?= e((string) (float) $r['fee_per_term']) ?>"><?= e(Fees::money($r['fee_per_term'])) ?></td>
        <td class="xg-act" style="white-space:nowrap"><a class="btn btn-sm" href="<?= e(url('transport/routes/' . $r['id'])) ?>">Open</a> <a class="btn btn-sm btn-ghost" href="<?= e(url('transport/routes/' . $r['id'] . '/print')) ?>" target="_blank">Print list</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $routes ? ' hidden' : '' ?> data-xg-empty>No bus routes yet.</p>
</div>
</div>
