<?php $edit = can('budget.caps'); $capped = array_filter($rows, fn($r) => $r['cap'] !== null); ?>
<div class="page-head">
    <div><h1>Budget</h1></div>
    <div class="actions"></div>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>
<?php require __DIR__ . '/_budget_seg.php'; ?>
<?php if (!$term): ?><div class="card"><p class="muted" style="margin:0">Set up the school terms first (School setup › Years &amp; terms).</p></div><?php else: ?>
<?php foreach (array_filter($capped, fn($r) => $r['left'] < 0) as $r): ?><div class="alert alert-error"><?= e($r['department']) ?> is over its cap by <?= e(Fees::money(-$r['left'])) ?>.</div><?php endforeach; ?>
<form method="post" action="<?= e(url('finance/budget/departments')) ?>" class="card" style="padding:0"><?= csrf_field() ?><input type="hidden" name="term" value="<?= (int) $term['id'] ?>">
    <div style="padding:1rem 1rem 0"><h2>Department caps — <?= e($term['name'] . ' ' . $term['year_name']) ?></h2></div>
    <div class="table-wrap"><table data-nopage>
        <thead><tr><th>Department</th><th class="num">Cap (GH₵)</th><th class="num">Spent</th><th class="num">Promised</th><th class="num">Left</th><th style="min-width:140px">Used</th></tr></thead>
        <tbody><?php foreach ($rows as $r): $pct = (int) ($r['pct'] ?? 0); ?>
        <tr>
            <td><?= e($r['department']) ?></td>
            <td class="num"><?php if ($edit): ?><input name="cap[<?= e($r['department']) ?>]" inputmode="decimal" value="<?= $r['cap'] !== null ? e(number_format($r['cap'], 2, '.', '')) : '' ?>" style="width:120px;text-align:right" aria-label="Cap for <?= e($r['department']) ?>"><?php else: ?><?= $r['cap'] !== null ? e(number_format($r['cap'], 2)) : '—' ?><?php endif; ?></td>
            <td class="num"><?= e(number_format($r['spent'], 2)) ?></td>
            <td class="num"><?= $r['committed'] ? e(number_format($r['committed'], 2)) : '' ?></td>
            <td class="num"><?= $r['left'] !== null ? '<span' . ($r['left'] < 0 ? ' style="color:var(--bad);font-weight:700"' : '') . '>' . e(number_format($r['left'], 2)) . '</span>' : '<span class="muted">no cap</span>' ?></td>
            <td><?php if ($r['cap']): ?><div style="display:flex;gap:.4rem;align-items:center"><div class="bar-meter<?= $pct > 100 ? ' over' : ($pct >= 80 ? ' near' : '') ?>"><span style="width:<?= min(100, $pct) ?>%"></span></div><small><?= $pct ?>%</small></div><?php endif; ?></td>
        </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php if ($edit): ?><div style="padding:1rem" class="form"><label>Reason for the change (kept in the history)<input name="note" maxlength="255" placeholder="e.g. Term 1 caps agreed at the management meeting"></label>
        <button class="btn btn-primary" type="submit">Save caps</button></div><?php endif; ?>
</form>
<div class="cols">
    <div>
        <?php if ($edit && count($capped) >= 1): ?>
        <form method="post" action="<?= e(url('finance/budget/departments/move')) ?>" class="card form"><?= csrf_field() ?><input type="hidden" name="term" value="<?= (int) $term['id'] ?>">
            <h2>Move money between departments</h2>
            <div class="grid-2">
                <label class="required">From<select name="from" required><?php foreach ($capped as $r): ?><option value="<?= e($r['department']) ?>"><?= e($r['department']) ?> (<?= e(number_format(max(0, $r['left']), 2)) ?> left)</option><?php endforeach; ?></select></label>
                <label class="required">To<select name="to" required><?php foreach ($rows as $r): ?><option value="<?= e($r['department']) ?>"><?= e($r['department']) ?></option><?php endforeach; ?></select></label>
                <label class="required">Amount (GH₵)<input name="amount" inputmode="decimal" required></label>
                <label class="required">Why<input name="note" maxlength="200" required placeholder="e.g. Science lab chemicals for JHS"></label>
            </div>
            <button class="btn btn-primary" type="submit">Move money</button>
        </form>
        <?php endif; ?>
        <?php if ($log): ?>
        <div class="card"><h2>History of changes</h2>
            <div class="table-wrap"><table><tbody><?php foreach ($log as $l): ?>
                <tr><td><small><?= e(fmt_date($l['created_at'], 'd M Y H:i')) ?></small></td><td><?= e($l['department']) ?></td>
                    <td class="num"><?= $l['old_amount'] !== null ? e(number_format((float) $l['old_amount'], 2)) . ' → ' : '' ?><?= e(number_format((float) $l['new_amount'], 2)) ?></td>
                    <td><small><?= e(['set' => 'Set', 'raise' => 'Raised', 'move_in' => 'Money in', 'move_out' => 'Money out'][$l['kind']] ?? $l['kind']) ?><?= $l['note'] ? ': ' . e($l['note']) : '' ?> · <?= e($l['by_name'] ?? '') ?></small></td></tr>
            <?php endforeach; ?></tbody></table></div>
        </div>
        <?php endif; ?>
    </div>
    <div>
        <?php if ($edit): ?>
        <div class="card"><h2>Departments</h2>
            <div class="table-wrap"><table data-nopage><tbody><?php foreach ($all as $d): ?>
                <tr><td<?= (int) $d['is_active'] ? '' : ' class="muted"' ?>><?= e($d['name']) ?></td><td class="xg-act">
                    <form method="post" action="<?= e(url('finance/budget/departments/list')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $d['id'] ?>"><input type="hidden" name="is_active" value="<?= (int) $d['is_active'] ? '0' : '1' ?>"><button class="btn btn-sm btn-ghost" type="submit" style="white-space:nowrap"><?= (int) $d['is_active'] ? 'Stop using' : 'Use again' ?></button></form></td></tr>
            <?php endforeach; ?></tbody></table></div>
            <form method="post" action="<?= e(url('finance/budget/departments/list')) ?>" class="form" style="margin-top:.6rem"><?= csrf_field() ?>
                <label>Add a department<input name="name" maxlength="80" placeholder="e.g. Library"></label><button class="btn" type="submit">Add</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
