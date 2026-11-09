<?php $edit = can('app.configure'); $freq = ['termly' => 'Every term', 'yearly' => 'Once a year (Term 1)', 'once' => 'On admission only']; ?>
<div class="page-head">
    <div><h1>Fee schedule</h1></div>
    <?php if ($edit): ?><div class="actions"><button type="button" class="btn btn-primary" data-help="fee-grid-edit" aria-expanded="false">Edit amounts</button></div><?php endif; ?>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>
<?php
// One table, drawn twice: read-only (shown first) and with boxes to type in (behind "Edit amounts").
$feeGrid = function (bool $ed) use ($items, $levels, $grid, $freq) { ?>
    <div class="table-wrap"><table class="score-table">
        <thead><tr><th>Level</th>
            <?php foreach ($items as $it): if (!$it['is_active']) continue; ?>
                <th style="text-align:center"><?= e($it['name']) ?><br><small class="muted" style="font-weight:400"><?= e($freq[$it['frequency']]) ?><?= $it['discountable'] ? ' · discountable' : '' ?></small></th>
            <?php endforeach; ?>
            <th style="text-align:right">Term 1 total</th><th style="text-align:right">Term 2/3 total</th>
        </tr></thead>
        <tbody>
        <?php foreach ($levels as $l): $t1 = 0; $t2 = 0; ?>
        <tr>
            <td><strong><?= e($l['name']) ?></strong></td>
            <?php foreach ($items as $it): if (!$it['is_active']) continue; $v = (float) ($grid[$l['id']][$it['id']] ?? 0);
                if ($it['frequency'] !== 'once') { $t1 += $v; } if ($it['frequency'] === 'termly') { $t2 += $v; } ?>
                <td style="text-align:center">
                    <?php if ($ed): ?><input type="text" inputmode="decimal" name="amount[<?= (int) $l['id'] ?>][<?= (int) $it['id'] ?>]" value="<?= e($v > 0 ? number_format($v, 2, '.', '') : '0') ?>" style="width:90px;text-align:right" aria-label="<?= e($l['name'] . ' – ' . $it['name']) ?>">
                    <?php else: ?><?= e(number_format($v, 2)) ?><?php endif; ?>
                </td>
            <?php endforeach; ?>
            <td style="text-align:right"><?= e(number_format($t1, 2)) ?></td>
            <td style="text-align:right"><?= e(number_format($t2, 2)) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
<?php }; ?>
<?php if ($edit): ?>
<style>#fee-grid-edit:not([hidden]) + #fee-grid-view { display: none; }</style>
<form id="fee-grid-edit" method="post" action="<?= e(url('fees/schedule')) ?>" class="card" style="padding:0" hidden>
    <?= csrf_field() ?>
    <?php $feeGrid(true); ?>
    <div style="padding:1rem;display:flex;gap:1rem;align-items:center;flex-wrap:wrap">
        <label style="margin:0;font-weight:400"><input type="checkbox" name="confirm_schedule" value="1"<?= checked((bool) setting('fee_schedule_confirmed')) ?>> These are the approved amounts</label>
        <span class="gate-actions" style="margin:0 0 0 auto"><button class="btn btn-primary" type="submit">Save fee schedule</button><button type="button" class="btn btn-ghost" data-help="fee-grid-edit">Cancel</button></span>
    </div>
</form>
<?php endif; ?>
<div id="fee-grid-view" class="card" style="padding:0">
    <?php $feeGrid(false); ?>
</div>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;flex-wrap:wrap"><h2 style="margin:0">Fee items</h2><?php if ($edit): ?><button type="button" class="btn btn-sm btn-primary" data-help="item-new">+ Add fee item</button><?php endif; ?></div>
    <?php $ei = $edit && !empty($_GET['item']) ? current(array_filter($items, fn($i) => (int) $i['id'] === (int) $_GET['item'])) : null; ?>
    <?php if ($edit): ?>
    <div id="item-new" class="help-box form-center"<?= $ei ? '' : ' hidden' ?>>
        <form method="post" action="<?= e(url('fees/items' . ($ei ? '/' . $ei['id'] : ''))) ?>" class="card form" style="box-shadow:none;border:1px solid var(--line)"><?= csrf_field() ?>
            <h3><?= $ei ? 'Edit ' . e($ei['name']) : 'Add a fee item' ?></h3>
            <div class="grid-2">
                <label class="required">Name<input name="name" maxlength="80" required value="<?= e($ei['name'] ?? '') ?>" placeholder="e.g. Sports levy"></label>
                <label class="required">Code<input name="code" maxlength="12" required value="<?= e($ei['code'] ?? '') ?>" placeholder="SPORT"></label>
                <label>Charged<select name="frequency"><?php foreach ($freq as $k => $v): ?><option value="<?= e($k) ?>"<?= selected($ei['frequency'] ?? 'termly', $k) ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
                <label>Order in bills<input name="sort_order" type="number" value="<?= (int) ($ei['sort_order'] ?? 10) ?>"></label>
            </div>
            
            <div class="vf-foot"><span style="display:flex;gap:1rem"><label style="font-weight:400;margin:0"><input type="checkbox" name="discountable" value="1"<?= checked((bool) ($ei['discountable'] ?? false)) ?>> Discountable</label><?php if ($ei): ?><label style="font-weight:400;margin:0"><input type="checkbox" name="is_active" value="1"<?= checked((bool) $ei['is_active']) ?>> Active</label><?php else: ?><input type="hidden" name="is_active" value="1"><?php endif; ?></span>
                <span style="display:flex;gap:.5rem"><a class="btn btn-ghost" href="<?= e(url('fees/schedule')) ?>">Cancel</a><button class="btn btn-primary" type="submit"><?= $ei ? 'Save' : 'Add item' ?></button></span></div>
        </form>
    </div>
    <?php endif; ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Name</th><th>Code</th><th>Charged</th><th>Discountable</th><th class="num">Order</th><th>Status</th><?php if ($edit): ?><th></th><?php endif; ?></tr></thead>
        <tbody><?php foreach ($items as $it): ?><tr<?= $it['is_active'] ? '' : ' style="opacity:.55"' ?>>
            <td><?= e($it['name']) ?></td><td><code><?= e($it['code']) ?></code></td><td><?= e($freq[$it['frequency']]) ?></td><td><?= $it['discountable'] ? 'Yes' : 'No' ?></td><td class="num"><?= (int) $it['sort_order'] ?></td><td><?= $it['is_active'] ? 'Active' : 'Inactive' ?></td>
            <?php if ($edit): ?><td class="xg-act"><a class="btn btn-sm" href="<?= e(url('fees/schedule', ['item' => $it['id']])) ?>#item-new">Edit</a></td><?php endif; ?>
        </tr><?php endforeach; ?></tbody>
    </table></div>
</div>
