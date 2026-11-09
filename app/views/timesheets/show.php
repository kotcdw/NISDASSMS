<?php $label = date('F Y', strtotime($period . '-01')); $t = $m['tot']; $ok = ($appr['status'] ?? '') === 'approved'; $manage = !$mine && can('timesheets.manage'); ?>
<div class="page-head">
    <div><?php if (!$mine): ?><div class="breadcrumb"><a href="<?= e(url('timesheets', ['period' => $period])) ?>">Timesheets</a> /</div><?php endif; ?>
    <h1><?= e($mine ? 'My timesheet' : $u['name']) ?> <?= $ok ? badge('Approved', 'good') : badge('Not yet approved', 'neutral') ?></h1>
    <p class="muted"><?= e($label) ?> · <?= e($m['shift'] ? $m['shift']['name'] . ' ' . substr($m['shift']['start_time'], 0, 5) . '–' . substr($m['shift']['end_time'], 0, 5) : 'School day') ?> · late after <?= e($m['late_after']) ?><?= $daily ? ' · paid by the day (' . e(Fees::money($daily['daily_rate'] ?? 0)) . ')' : '' ?></p></div>
    <div class="actions"><button class="btn btn-ghost" type="button" data-print>Print</button></div>
</div>
<p class="figs"><span>Days worked <strong><?= (int) $t['days'] ?> / <?= (int) $t['expected'] ?></strong></span><span>Hours <strong><?= e(number_format($t['hours'], 1)) ?></strong></span><span>Late arrivals <strong><?= (int) $t['late'] ?></strong></span><span>Days absent<?= $t['no_out'] ? ' · ' . (int) $t['no_out'] . ' without sign-out' : '' ?> <strong><?= (int) $t['absent'] ?></strong></span></p>
<div class="card" style="padding:0"><div class="table-wrap"><table>
    <tr><th>Day</th><th>In</th><th>Out</th><th class="num">Hours</th><th>Notes</th><?php if ($manage && !$ok): ?><th class="no-print">Correct</th><?php endif; ?></tr>
    <?php foreach ($m['rows'] as $r): ?>
    <tr>
        <td style="white-space:nowrap"><?= e(date('D j M', strtotime($r['date']))) ?><?= !$r['expected'] ? ' <small class="muted">(extra day)</small>' : '' ?></td>
        <td><?= $r['in'] ? e(substr($r['in'], 0, 5)) . ($r['late'] ? ' ' . badge('Late', 'warn') : '') : badge('Absent', 'bad') ?></td>
        <td><?= $r['out'] ? e(substr($r['out'], 0, 5)) . ($r['early'] ? ' ' . badge('Early', 'neutral') : '') : ($r['no_out'] ? badge('No sign-out', 'neutral') : '') ?></td>
        <td class="num"><?= $r['hours'] !== null ? e(number_format($r['hours'], 2)) : '—' ?></td>
        <td><small><?= e($r['note'] ?? '') ?><?= $r['corrected'] ? ' ' . badge('Corrected', 'info') : '' ?></small></td>
        <?php if ($manage && !$ok): ?>
        <td class="no-print">
            <form method="post" action="<?= e(url('timesheets/' . $u['id'] . '/correct')) ?>" style="display:flex;gap:.3rem;flex-wrap:nowrap;align-items:center">
                <?= csrf_field() ?><input type="hidden" name="date" value="<?= e($r['date']) ?>">
                <input type="time" name="time_in" value="<?= e($r['in'] ? substr($r['in'], 0, 5) : '') ?>" aria-label="In" style="width:8.8rem;flex:0 0 auto">
                <input type="time" name="time_out" value="<?= e($r['out'] ? substr($r['out'], 0, 5) : '') ?>" aria-label="Out" style="width:8.8rem;flex:0 0 auto">
                <input name="note" maxlength="160" placeholder="Reason" style="width:9rem;flex:0 0 auto" required>
                <button class="btn btn-sm" type="submit">Save</button>
            </form>
        </td>
        <?php endif; ?>
    </tr>
    <?php endforeach; ?>
    <?php if (!$m['rows']): ?><tr><td colspan="6" class="muted">No working days in this month yet.</td></tr><?php endif; ?>
</table></div></div>
<?php if ($manage && (int) $u['id'] !== Auth::id()): ?>
<form method="post" action="<?= e(url('timesheets/' . $u['id'] . '/approve')) ?>" class="card form no-print" style="max-width:620px">
    <?= csrf_field() ?><input type="hidden" name="period" value="<?= e($period) ?>">
    <?php if ($ok): ?>
        <p>Approved by <?= e($appr['approved_name'] ?? '') ?> on <?= e(fmt_date($appr['approved_at'], 'j M Y')) ?>: <?= e((string) (float) $appr['days_worked']) ?> days, <?= e((string) (float) $appr['hours']) ?> hours.</p>
        <button class="btn" type="submit" name="action" value="undo">Remove approval to correct</button>
    <?php else: ?>
        <label>Note (optional)<input name="note" maxlength="255"></label>
        <button class="btn btn-primary" type="submit">Approve <?= e($label) ?></button>
    <?php endif; ?>
</form>
<?php endif; ?>
