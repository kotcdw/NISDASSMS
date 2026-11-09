<?php if ($staffView && !$hasAvailability): ?><div class="alert alert-info">You do not offer any appointment times yet. <a href="<?= e(url('appointments/availability')) ?>">Add the times parents may book</a>.</div><?php endif; ?>
<div class="xgrid-box" data-xgrid="appointments" data-xgrid-file="appointments">
<div class="page-head">
    <div><h1>Appointments</h1></div>
    <div class="actions">
        <?php if ($staffView || can('appointments.manage')): ?><details class="menu-btn"><summary class="btn btn-ghost">Settings ▾</summary><div>
            <?php if ($staffView): ?><a href="<?= e(url('appointments/availability')) ?>">My availability</a><?php endif; ?>
            <?php if (can('appointments.manage')): ?><a href="<?= e(url('appointments/types')) ?>">Appointment types</a><?php endif; ?>
        </div></details><?php endif; ?>
        <?= export_grid_menu() ?>
        <?php if (can('appointments.book')): ?><a class="btn btn-primary" href="<?= e(url('appointments/book')) ?>">+ Book</a><?php endif; ?>
    </div>
</div>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['date' => ['Date', 'date'], 'time' => ['Time'], 'type' => ['Appointment'], 'who' => [$staffView ? 'Parent' : 'With']] + ($staffView ? ['phone' => ['Phone']] : []) + ['child' => ['Child'], 'about' => ['About'], 'note' => ['Note'], 'status' => ['Status']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $a): [$sl, $st] = AppointmentController::STATUS[$a['status']]; $past = $a['start_at'] < date('Y-m-d H:i:s'); ?>
    <tr<?= $a['status'] === 'cancelled' ? ' style="opacity:.55"' : '' ?>>
        <td data-c="date" data-s="<?= e($a['start_at']) ?>" style="white-space:nowrap"><strong><?= e(date('D j M', strtotime($a['start_at']))) ?></strong></td>
        <td data-c="time" style="white-space:nowrap"><?= e(date('H:i', strtotime($a['start_at']))) ?>–<?= e(date('H:i', strtotime($a['end_at']))) ?></td>
        <td data-c="type"><?= e($a['type_name']) ?></td>
        <td data-c="who"><?= e($staffView ? $a['booked_name'] : $a['staff_name']) ?></td>
        <?php if ($staffView): ?><td data-c="phone"><?= e((string) ($a['booked_phone'] ?? '')) ?></td><?php endif; ?>
        <td data-c="child"><?= $a['first_name'] ? e($a['first_name'] . ' ' . $a['last_name']) : '' ?></td>
        <td data-c="about" style="max-width:280px"><?= e($a['answer'] ?? '') ?></td>
        <td data-c="note" style="max-width:220px"><?= e((string) ($a['staff_note'] ?? '')) ?></td>
        <td data-c="status"><?= badge($sl, $st) ?></td>
        <td class="xg-act" style="white-space:nowrap">
            <?php if ($a['status'] === 'booked' && !$past): ?>
                <details><summary class="btn btn-sm btn-ghost">Cancel</summary><form method="post" action="<?= e(url('appointments/' . $a['id'] . '/cancel')) ?>" style="display:flex;gap:.3rem;margin-top:.3rem"><?= csrf_field() ?><input name="note" placeholder="<?= $staffView ? 'Reason (required)' : 'Reason (optional)' ?>" style="width:10rem"<?= $staffView ? ' required' : '' ?>><button class="btn btn-sm" type="submit">OK</button></form></details>
            <?php elseif ($staffView && $a['status'] === 'booked' && $past): ?>
                <form method="post" action="<?= e(url('appointments/' . $a['id'] . '/status')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="to" value="done"><button class="btn btn-sm btn-good" type="submit">Held</button></form>
                <form method="post" action="<?= e(url('appointments/' . $a['id'] . '/status')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="to" value="no_show"><button class="btn btn-sm btn-ghost" type="submit">Did not come</button></form>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No appointments yet.</p>
</div>
</div>
