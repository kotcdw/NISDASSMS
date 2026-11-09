<?php
$st = fn($s) => badge(Registers::LEAVE_STATUS[$s][0] ?? $s, Registers::LEAVE_STATUS[$s][1] ?? 'neutral');
$tabs = [];
foreach (['' => 'All', 'pending' => 'Waiting', 'approved' => 'Approved', 'declined' => 'Declined'] as $k => $l) { $tabs[$k] = [$l, url('staff-leave', $k ? ['status' => $k] : [])]; }
$askOpen = old('start_date') !== '';
$askBtn = '<button type="button" class="btn btn-primary" data-help="new-leave" aria-expanded="' . ($askOpen ? 'true' : 'false') . '">+ Ask for leave</button>';
ob_start(); ?>
<div id="new-leave" class="card"<?= $askOpen ? '' : ' hidden' ?>>
    <h2>Ask for leave</h2>
    <form method="post" action="<?= e(url('staff-leave')) ?>" class="form" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <p class="muted" style="font-size:.9rem;margin-top:0">Annual leave this year: <strong><?= max(0, (int) $balance['left']) ?></strong> of <?= (int) $balance['allowance'] ?> days left<?= $balance['waiting'] ? ' (' . (int) $balance['waiting'] . ' waiting for approval)' : '' ?>.</p>
        <label class="required">Kind of leave<select name="leave_type" required><option value="">Choose…</option><?php foreach (Registers::LEAVE_TYPES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(old('leave_type'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
        <div class="grid-2">
            <label class="required">First day<input type="date" name="start_date" required value="<?= e(old('start_date')) ?>"></label>
            <label class="required">Last day<input type="date" name="end_date" required value="<?= e(old('end_date')) ?>"></label>
        </div>
        <label>Reason<textarea name="reason" rows="3" maxlength="500"><?= e(old('reason')) ?></textarea></label>
        <label>Who will take your classes / duties<input name="relief" maxlength="150" value="<?= e(old('relief')) ?>"></label>
        <label>Medical note or letter (PDF or photo)<input type="file" name="attachment" accept="application/pdf,image/jpeg,image/png"></label>
        <div class="gate-actions"><button class="btn btn-primary" type="submit">Send request</button><button type="button" class="btn btn-ghost" data-help="new-leave">Cancel</button></div>
    </form>
</div>
<?php $askCard = ob_get_clean(); ?>
<?php if ($approver && $today): ?><div class="alert alert-info">On leave today: <?= e(implode(', ', array_map(fn($uid, $d) => (string) db()->value('SELECT name FROM users WHERE id = ?', [$uid]) . ' (' . reset($d) . ')', array_keys($today), $today))) ?></div><?php endif; ?>

<?php if ($approver): ?>
<div class="xgrid-box" data-xgrid="staff-leave" data-xgrid-file="staff-leave">
<div class="page-head">
    <div><h1>Staff leave</h1></div>
    <div class="actions"><?= export_grid_menu() ?><?= $askBtn ?></div>
</div>
<?= $askCard ?>
<?= card_tabs($tabs, isset($tabs[$status]) ? $status : '', 4, 'Leave to show') ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['name' => ['Staff'], 'ref' => ['Ref no.'], 'kind' => ['Leave'], 'from' => ['From', 'date'], 'to' => ['To', 'date'], 'days' => ['Days', 'num', 'num'], 'reason' => ['Reason'], 'relief' => ['Relief'], 'file' => ['Attachment'], 'status' => ['Status'], 'by' => ['Decided by'], 'note' => ['Note']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($all as $l): ?>
    <tr>
        <td data-c="name"><strong><?= e($l['name']) ?></strong></td>
        <td data-c="ref"><?= e($l['ref_no']) ?></td>
        <td data-c="kind"><?= e(Registers::LEAVE_TYPES[$l['leave_type']] ?? '') ?></td>
        <td data-c="from" data-s="<?= e($l['start_date']) ?>" style="white-space:nowrap"><?= e(fmt_date($l['start_date'])) ?></td>
        <td data-c="to" data-s="<?= e($l['end_date']) ?>" style="white-space:nowrap"><?= e(fmt_date($l['end_date'])) ?></td>
        <td data-c="days" class="num"><?= (int) $l['days'] ?></td>
        <td data-c="reason"><?= e((string) $l['reason']) ?></td>
        <td data-c="relief"><?= e((string) $l['relief']) ?></td>
        <td data-c="file"><?= $l['attachment'] ? '<a href="' . e(url('staff-leave/' . $l['id'] . '/file')) . '" target="_blank">Attachment</a>' : '' ?></td>
        <td data-c="status"><?= $st($l['status']) ?></td>
        <td data-c="by"><?= e((string) $l['decided_name']) ?></td>
        <td data-c="note"><?= e((string) $l['decision_note']) ?></td>
        <td class="xg-act"><?php if ($l['status'] === 'pending' && (int) $l['user_id'] !== (int) Auth::id()): ?>
            <form method="post" action="<?= e(url('staff-leave/' . $l['id'] . '/decide')) ?>" class="form" style="min-width:180px;margin:0"><?= csrf_field() ?>
                <input name="note" maxlength="255" placeholder="Note (needed to decline)" aria-label="Note" style="margin:0">
                <div style="display:flex;gap:.4rem;margin-top:.3rem"><button class="btn btn-sm btn-primary" name="decision" value="approve">Approve</button><button class="btn btn-sm" name="decision" value="decline">Decline</button></div></form>
            <?php elseif ($l['status'] === 'approved' && (int) $l['user_id'] !== (int) Auth::id()): ?>
            <form method="post" action="<?= e(url('staff-leave/' . $l['id'] . '/cancel')) ?>" data-confirm="Cancel this approved leave? The staff member is told."><?= csrf_field() ?><button class="btn btn-sm btn-ghost">Cancel</button></form><?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $all ? ' hidden' : '' ?> data-xg-empty>No leave requests.</p>
</div>
</div>
<?php endif; ?>

<div class="xgrid-box" data-xgrid="my-leave" data-xgrid-file="my-leave"<?= $approver ? ' style="margin-top:1.25rem"' : '' ?>>
<div class="page-head">
    <div><?= $approver ? '<h2 style="margin:0">My leave</h2>' : '<h1>Staff leave</h1>' ?></div>
    <div class="actions"><?= export_grid_menu() ?><?= $approver ? '' : $askBtn ?></div>
</div>
<?= $approver ? '' : $askCard ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['kind' => ['Leave'], 'ref' => ['Ref no.'], 'from' => ['From', 'date'], 'to' => ['To', 'date'], 'days' => ['Days', 'num', 'num'], 'status' => ['Status'], 'note' => ['Note']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($mine as $l): ?>
    <tr>
        <td data-c="kind"><strong><?= e(Registers::LEAVE_TYPES[$l['leave_type']] ?? '') ?></strong></td>
        <td data-c="ref"><?= e($l['ref_no']) ?></td>
        <td data-c="from" data-s="<?= e($l['start_date']) ?>" style="white-space:nowrap"><?= e(fmt_date($l['start_date'])) ?></td>
        <td data-c="to" data-s="<?= e($l['end_date']) ?>" style="white-space:nowrap"><?= e(fmt_date($l['end_date'])) ?></td>
        <td data-c="days" class="num"><?= (int) $l['days'] ?></td>
        <td data-c="status"><?= $st($l['status']) ?></td>
        <td data-c="note"><?= e((string) $l['decision_note']) ?></td>
        <td class="xg-act"><?php if ($l['status'] === 'pending' || ($l['status'] === 'approved' && $l['start_date'] > date('Y-m-d'))): ?><form method="post" action="<?= e(url('staff-leave/' . $l['id'] . '/cancel')) ?>" data-confirm="Cancel this leave request?"><?= csrf_field() ?><button class="btn btn-sm btn-ghost">Cancel</button></form><?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $mine ? ' hidden' : '' ?> data-xg-empty>You have not asked for leave yet.</p>
</div>
</div>
