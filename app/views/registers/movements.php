<?php $goOpen = old('destination') !== ''; ?>
<?php if ($all && $outNow): ?>
<div class="card"><h2 style="margin-top:0">Out of school now</h2>
    <?php foreach ($outNow as $m): $late = $m['expected_back'] && $m['expected_back'] < date('Y-m-d H:i:s'); ?>
    <p style="margin:0 0 .4rem"><strong><?= e($m['name']) ?></strong> — <?= e($m['destination']) ?> (<?= e(Registers::MOVE_PURPOSES[$m['purpose']] ?? '') ?>), left <?= e(substr($m['out_at'], 11, 5)) ?><?= $m['expected_back'] ? ', back by ' . e(substr($m['expected_back'], 11, 5)) : '' ?> <?= $late ? badge('Late back', 'warn') : '' ?></p>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php if ($mine): ?><div class="alert alert-info">You are out: left at <?= e(substr($mine['out_at'], 11, 5)) ?> for <?= e($mine['destination']) ?>.</div><?php endif; ?>
<div class="xgrid-box" data-xgrid="movements" data-xgrid-file="staff-movements-<?= e($period === 'custom' ? $from . '-to-' . $to : $period) ?>">
<div class="page-head">
    <div><h1>Staff movement book</h1></div>
    <div class="actions">
        <?= export_grid_menu() ?>
        <?php if ($mine): ?>
        <form method="post" action="<?= e(url('movements/' . $mine['id'] . '/back')) ?>" class="inline"><?= csrf_field() ?><button class="btn btn-primary" type="submit">I am back</button></form>
        <?php else: ?>
        <button type="button" class="btn btn-primary" data-help="go-out" aria-expanded="<?= $goOpen ? 'true' : 'false' ?>">+ I am going out</button>
        <?php endif; ?>
    </div>
</div>
<?php $route = 'movements'; require __DIR__ . '/_range.php'; ?>
<?php if (!$mine): ?>
<div id="go-out" class="card"<?= $goOpen ? '' : ' hidden' ?>>
    <h2>I am going out</h2>
    <form method="post" action="<?= e(url('movements')) ?>" class="form">
        <?= csrf_field() ?>
        <label class="required">Where to<input name="destination" maxlength="150" required value="<?= e(old('destination')) ?>" placeholder="e.g. District education office"></label>
        <div class="grid-2">
            <label class="required">Why<select name="purpose"><?php foreach (Registers::MOVE_PURPOSES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(old('purpose', 'official'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
            <label>Back by<input type="time" name="expected_back" value="<?= e(old('expected_back')) ?>"></label>
        </div>
        <label>Details<input name="details" maxlength="255" value="<?= e(old('details')) ?>"></label>
        <div class="gate-actions"><button class="btn btn-primary" type="submit">Record that I am going out</button><button type="button" class="btn btn-ghost" data-help="go-out">Cancel</button></div>
    </form>
</div>
<?php endif; ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['date' => ['Date', 'date']] + ($all ? ['name' => ['Staff']] : []) + ['where' => ['Where to'], 'why' => ['Why'], 'details' => ['Details'], 'out' => ['Out'], 'expected' => ['Expected back'], 'back' => ['Back']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $today = substr($r['out_at'], 0, 10) === date('Y-m-d'); ?>
    <tr>
        <td data-c="date" data-s="<?= e($r['out_at']) ?>" style="white-space:nowrap"><?= e(date('D j M', strtotime($r['out_at']))) ?></td>
        <?php if ($all): ?><td data-c="name"><strong><?= e($r['name']) ?></strong></td><?php endif; ?>
        <td data-c="where"><?= e($r['destination']) ?></td>
        <td data-c="why"><?= e(Registers::MOVE_PURPOSES[$r['purpose']] ?? '') ?></td>
        <td data-c="details"><?= e((string) $r['details']) ?></td>
        <td data-c="out"><?= e(substr($r['out_at'], 11, 5)) ?></td>
        <td data-c="expected"><?= $r['expected_back'] ? e(substr($r['expected_back'], 11, 5)) : '' ?></td>
        <td data-c="back"><?php if ($r['back_at']): ?><?= e(substr($r['back_at'], 11, 5)) ?><?php elseif (!$today): ?><span class="muted">not recorded</span><?php endif; ?></td>
        <td class="xg-act"><?php if (!$r['back_at'] && $today): ?><form method="post" action="<?= e(url('movements/' . $r['id'] . '/back')) ?>"><?= csrf_field() ?><button class="btn btn-sm" type="submit">Back now</button></form><?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No movements in this period.</p>
</div>
</div>
