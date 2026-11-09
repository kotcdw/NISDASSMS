<?php [$sl, $st] = EventController::STATUS[$ev['status']]; $manage = can('events.manage');
$reg = array_filter($regs, fn($r) => $r['status'] === 'registered'); $att = count(array_filter($reg, fn($r) => (int) $r['attended']));
$heads = array_sum(array_map(fn($r) => 1 + (int) $r['guests'], $reg)); $paid = count(array_filter($reg, fn($r) => $r['inv_status'] === 'paid')); ?>
<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('events')) ?>">Events</a> /</div><h1><?= e($ev['title']) ?> <?= badge($sl, $st) ?></h1>
    <p class="muted"><?= e(date('l j F Y', strtotime($ev['start_at']))) ?><?= substr($ev['start_at'], 11, 5) !== '00:00' ? ' · ' . e(substr($ev['start_at'], 11, 5)) : '' ?><?= $ev['end_at'] ? ' – ' . e(date(substr($ev['end_at'], 0, 10) !== substr($ev['start_at'], 0, 10) ? 'D j M H:i' : 'H:i', strtotime($ev['end_at']))) : '' ?><?= $ev['venue'] ? ' · ' . e($ev['venue']) : '' ?> · <?= e(EventController::AUDIENCE[$ev['audience']]) ?><?= $ev['level_name'] ? ': ' . e($ev['level_name']) : '' ?></p></div>
    <?php if ($manage): ?><div class="actions">
        <a class="btn" href="<?= e(url('events/' . $ev['id'] . '/edit')) ?>">Edit</a>
        <?= export_menu(url('events/' . $ev['id'] . '/export'), 'Export', 'btn') ?>
        <?php foreach (['draft' => ['published' => 'Publish'], 'published' => ['completed' => 'Mark finished', 'cancelled' => 'Cancel event'], 'completed' => [], 'cancelled' => ['published' => 'Re-open']][$ev['status']] as $to => $lab): ?>
            <form method="post" action="<?= e(url('events/' . $ev['id'] . '/status')) ?>" class="inline"<?= $to === 'cancelled' ? ' data-confirm="Cancel this event? Unpaid event bills are cancelled and families are told."' : '' ?>><?= csrf_field() ?><input type="hidden" name="to" value="<?= $to ?>"><button class="btn<?= $to === 'cancelled' ? ' btn-ghost' : '' ?>" type="submit"><?= e($lab) ?></button></form>
        <?php endforeach; ?>
    </div><?php endif; ?>
</div>
<?php if ($manage): ?>
<p class="figs"><span>Registered<?= $ev['capacity'] ? ' · ' . (int) $left . ' places left' : '' ?> <strong><?= count($reg) ?></strong></span><span>Expected people (with guests) <strong><?= $heads ?></strong></span><span>Paid<?= (float) $ev['fee'] > 0 ? ' (' . e(Fees::money($ev['fee'])) . ' each)' : ' (free event)' ?> <strong><?= (float) $ev['fee'] > 0 ? $paid . ' / ' . count($reg) : '—' ?></strong></span><span>Checked in <strong><?= $att ?></strong></span></p>
<?php endif; ?>
<div class="cols">
<div>
    <?php if ($ev['description']): ?><div class="card"><p style="white-space:pre-line;margin:0"><?= e($ev['description']) ?></p></div><?php endif; ?>
    <?php if ($manage): ?>
    <div class="card" style="padding:0"><h2 style="padding:1rem 1rem 0">Registrations</h2><div class="table-wrap"><table>
        <tr><th>Who</th><th>Class</th><th>Registered by</th><th>Guests</th><th>Bill</th><th>Status</th><th></th></tr>
        <?php foreach ($regs as $r): ?><tr id="r<?= (int) $r['id'] ?>"<?= $r['status'] === 'cancelled' ? ' style="opacity:.5"' : '' ?>>
            <td><strong><?= $r['pupil_id'] ? e($r['last_name'] . ', ' . $r['first_name']) : e($r['user_name'] ?? '') . ' <small class="muted">(staff)</small>' ?></strong></td>
            <td><?= e($r['class_name'] ?? '') ?></td><td><small><?= e($r['user_name'] ?? '') ?><br><?= e($r['phone'] ?? '') ?></small></td><td><?= (int) $r['guests'] ?></td>
            <td><?= $r['invoice_no'] ? e($r['invoice_no']) . ' ' . badge(ucfirst($r['inv_status']), $r['inv_status'] === 'paid' ? 'good' : ($r['inv_status'] === 'cancelled' ? 'neutral' : 'warn')) : '' ?></td>
            <td><?= badge(['registered' => 'Registered', 'waitlist' => 'Waiting list', 'cancelled' => 'Cancelled'][$r['status']], ['registered' => 'good', 'waitlist' => 'warn', 'cancelled' => 'neutral'][$r['status']]) ?></td>
            <td style="white-space:nowrap"><?php if ($r['status'] === 'registered'): ?><form method="post" action="<?= e(url('events/' . $ev['id'] . '/checkin/' . $r['id'])) ?>" class="inline"><?= csrf_field() ?><button class="btn btn-sm<?= (int) $r['attended'] ? ' btn-good' : '' ?>" type="submit"><?= (int) $r['attended'] ? '✓ Here' : 'Check in' ?></button></form><?php endif; ?>
                <?php if ($r['status'] !== 'cancelled'): ?><form method="post" action="<?= e(url('events/' . $ev['id'] . '/registrations/' . $r['id'] . '/cancel')) ?>" class="inline" data-confirm="Cancel this registration?"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" type="submit">✕</button></form><?php endif; ?></td></tr><?php endforeach; ?>
        <?php if (!$regs): ?><tr><td colspan="7" class="muted">No registrations yet.</td></tr><?php endif; ?>
    </table></div></div>
    <?php endif; ?>
</div>
<div>
    <?php if ($myRegs): ?><div class="card"><h2>Registered</h2>
        <?php foreach ($myRegs as $r): ?><p style="margin:0 0 .5rem"><strong><?= $r['pupil_id'] ? e($r['first_name'] . ' ' . $r['last_name']) : 'You' ?></strong> — <?= $r['status'] === 'waitlist' ? badge('Waiting list', 'warn') : badge('Registered', 'good') ?>
            <?php if ($r['public_key'] && $r['inv_status'] !== 'paid' && $r['inv_status'] !== 'cancelled'): ?> <a class="btn btn-sm btn-primary" href="<?= e(url('pay/' . $r['public_key'])) ?>">Pay <?= e(Fees::money($ev['fee'])) ?></a><?php elseif ($r['inv_status'] === 'paid'): ?> <?= badge('Paid', 'good') ?><?php endif; ?>
            <?php if (substr($ev['start_at'], 0, 10) >= date('Y-m-d')): ?><form method="post" action="<?= e(url('events/' . $ev['id'] . '/registrations/' . $r['id'] . '/cancel')) ?>" class="inline" data-confirm="Cancel this registration?"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" type="submit">Cancel</button></form><?php endif; ?></p><?php endforeach; ?>
    </div><?php endif; ?>
    <?php if ($canRegister && (Auth::role() === 'parent' ? $children : (can('staff') && in_array($ev['audience'], ['all', 'staff'], true) && !$myRegs))): ?>
    <form method="post" action="<?= e(url('events/' . $ev['id'] . '/register')) ?>" class="card form"><?= csrf_field() ?>
        <h2>Register</h2>
        <?php if ($ev['capacity']): ?><p class="hint"><?= (int) $left ?> place(s) left<?= $ev['reg_deadline'] ? ' · register by ' . e(date('j M', strtotime($ev['reg_deadline']))) : '' ?>.</p><?php endif; ?>
        <?php foreach ($children as $c): ?><label style="font-weight:400"><input type="checkbox" name="pupils[]" value="<?= (int) $c['id'] ?>"> <?= e($c['first_name'] . ' ' . $c['last_name']) ?> <small class="muted">(<?= e($c['class_name'] ?? '') ?>)</small></label><?php endforeach; ?>
        <label>Extra guests coming (parents, siblings)<input type="number" name="guests" min="0" max="10" value="0"></label>
        <label>Note (optional)<input name="note" maxlength="255"></label>
        <button class="btn btn-primary" type="submit">Register<?= (float) $ev['fee'] > 0 ? ' — ' . e(Fees::money($ev['fee'])) . ' per child' : '' ?></button>
    </form>
    <?php elseif (!$canRegister && (int) $ev['registration'] && $ev['status'] === 'published'): ?><div class="alert alert-info">Registration is closed.</div><?php endif; ?>
</div>
</div>
