<?php $ct = 'canteen'; ?>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="xgrid-box" data-xgrid="canteen-sales" data-xgrid-file="canteen-recent-sales">
<div class="page-head">
    <div><h1>Canteen</h1></div>
    <div class="actions"><?= export_grid_menu() ?><a class="btn btn-primary" href="<?= e(url('canteen/sell')) ?>">Cash sale (no student)</a></div>
</div>
<form method="get" action="<?= e(url('canteen')) ?>" class="card form list-form">
    <h2>Find a student</h2>
    <div style="display:flex;gap:.5rem"><input name="q" value="<?= e($q) ?>" placeholder="Name or admission number" aria-label="Name or admission number" autofocus><button class="btn btn-primary" type="submit">Search</button></div>
    <?php if ($q !== ''): ?>
    <div class="table-wrap" style="margin-top:.8rem"><table>
        <?php foreach ($found as $f): ?><tr><td><strong><?= e($f['last_name'] . ', ' . $f['first_name']) ?></strong></td><td><?= e($f['admission_no']) ?></td><td><?= e($f['class_name']) ?></td><td class="num"><?= e(Fees::money($f['balance'] ?? 0)) ?></td>
            <td style="white-space:nowrap"><a class="btn btn-sm btn-primary" href="<?= e(url('canteen/sell', ['pupil' => $f['id']])) ?>">Sell</a> <a class="btn btn-sm" href="<?= e(url('canteen/wallets/' . $f['id'])) ?>">Top up</a></td></tr><?php endforeach; ?>
        <?php if (!$found): ?><tr><td class="muted">No student found.</td></tr><?php endif; ?>
    </table></div>
    <?php endif; ?>
</form>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['no' => ['Sale no.'], 'time' => ['Time', 'date'], 'student' => ['Student'], 'paid' => ['Paid by'], 'total' => ['Total (GH₵)', 'num', 'num'], 'status' => ['Status']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($recent as $s): $void = $s['status'] === 'void'; ?>
    <tr<?= $void ? ' style="opacity:.55"' : '' ?>>
        <td data-c="no"><?= e($s['sale_no']) ?></td>
        <td data-c="time" data-s="<?= e($s['created_at']) ?>"><?= e(date('j M H:i', strtotime($s['created_at']))) ?></td>
        <td data-c="student"><?= $s['pupil_id'] ? e($s['first_name'] . ' ' . $s['last_name']) : '<span class="muted">Walk-in</span>' ?></td>
        <td data-c="paid"><?= e(ucfirst($s['paid_by'])) ?></td>
        <td data-c="total" class="num"><?= e(number_format((float) $s['total'], 2)) ?></td>
        <td data-c="status"><?= $void ? badge('Cancelled', 'neutral') : 'Sold' ?></td>
        <td class="xg-act"><?php if (!$void && can('canteen.manage')): ?><details><summary class="btn btn-sm btn-ghost">Cancel</summary><form method="post" action="<?= e(url('canteen/sales/' . $s['id'] . '/void')) ?>" style="display:flex;gap:.3rem;margin-top:.3rem"><?= csrf_field() ?><input name="reason" placeholder="Reason" required style="width:9rem" aria-label="Reason"><button class="btn btn-sm" type="submit">OK</button></form></details><?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $recent ? ' hidden' : '' ?> data-xg-empty>No sales yet.</p>
</div>
</div>
<div class="grid-2" style="margin-top:1.2rem">
    <div class="card"><h2>Today's menu</h2>
        <?php foreach (Canteen::MEALS as $mk => $ml): if (empty($menu[$mk])) continue; ?><p style="margin:0 0 .4rem"><strong><?= e($ml) ?>:</strong> <?= e(implode(', ', array_column($menu[$mk], 'name'))) ?></p><?php endforeach; ?>
        <?php if (!$menu): ?><p class="muted">No menu set for today. <a href="<?= e(url('canteen/menu')) ?>">Plan the menu</a></p><?php endif; ?>
    </div>
    <div class="card"><h2>Low stock</h2>
        <?php foreach ($low as $l): ?><p style="margin:0 0 .3rem"><?= e($l['name']) ?> — <strong><?= (int) $l['stock_qty'] ?></strong> left</p><?php endforeach; ?>
        <?php if (!$low): ?><p class="muted">Nothing below its reorder level.</p><?php endif; ?>
    </div>
</div>
