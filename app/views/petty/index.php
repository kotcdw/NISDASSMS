<div class="xgrid-box" data-xgrid="petty-boxes" data-xgrid-file="petty-cash-boxes">
<div class="page-head">
    <div><h1>Petty cash</h1></div>
    <div class="actions">
        <?= export_grid_menu() ?>
        <?php if (can('finance.manage')): ?><button type="button" class="btn btn-primary" data-help="box-form" aria-expanded="<?= $open ? 'true' : 'false' ?>">+ New petty cash box</button><?php endif; ?>
    </div>
</div>
<?php if (can('finance.manage')): ?>
<form id="box-form" method="post" action="<?= e(url('petty-cash')) ?>" class="card form list-form"<?= $open ? '' : ' hidden' ?>><?= csrf_field() ?>
    <h2>New petty cash box</h2>
    <div class="grid-2">
        <label class="required">Name<input name="name" required maxlength="80" value="<?= e(old('name')) ?>" placeholder="e.g. Petty cash — Head's office"></label>
        <label class="required">Held by<select name="custodian_id" required><option value="">Choose…</option><?php foreach ($staff as $u): ?><option value="<?= (int) $u['id'] ?>"<?= selected(old('custodian_id'), $u['id']) ?>><?= e($u['name']) ?></option><?php endforeach; ?></select></label>
        <label class="required">Float (GH₵)<input name="float_amount" inputmode="decimal" required value="<?= e(old('float_amount', '500')) ?>"></label>
        <label>Most for one voucher (GH₵)<input name="spend_limit" inputmode="decimal" value="<?= e(old('spend_limit', '200')) ?>"></label>
        <label class="required">Take the float from<select name="from_account" required><?php foreach ($accounts as $a): ?><option value="<?= (int) $a['id'] ?>"<?= selected(old('from_account'), $a['id']) ?>><?= e($a['name']) ?> (<?= e(number_format(Finance::balance((int) $a['id']), 2)) ?>)</option><?php endforeach; ?></select></label>
    </div>
    <div class="gate-actions"><button class="btn btn-primary" type="submit">Set up box</button><button type="button" class="btn btn-ghost" data-help="box-form">Cancel</button></div>
</form>
<?php endif; ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['box' => ['Box'], 'held' => ['Held by'], 'float' => ['Float (GH₵)', 'num', 'num'], 'cash' => ['Cash in box (GH₵)', 'num', 'num'], 'topup' => ['Spent, to top up (GH₵)', 'num', 'num'], 'status' => ['Status']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($boxes as $b): $low = $b['float_amount'] && $b['balance'] < 0.25 * (float) $b['float_amount']; ?><tr<?= $b['is_active'] ? '' : ' style="opacity:.55"' ?>>
        <td data-c="box"><a href="<?= e(url('petty-cash/' . $b['id'])) ?>"><strong><?= e($b['name']) ?></strong></a></td>
        <td data-c="held"><?= e($b['custodian_name'] ?? '') ?></td>
        <td data-c="float" class="num" data-s="<?= (float) $b['float_amount'] ?>"><?= e(number_format((float) $b['float_amount'], 2)) ?></td>
        <td data-c="cash" class="num" data-s="<?= (float) $b['balance'] ?>"<?= $low ? ' style="color:var(--bad);font-weight:600"' : '' ?>><?= e(number_format($b['balance'], 2)) ?></td>
        <td data-c="topup" class="num" data-s="<?= (float) $b['unclaimed'] ?>"><?= $b['unclaimed'] > 0 ? e(number_format($b['unclaimed'], 2)) : '' ?></td>
        <td data-c="status"><?= !$b['is_active'] ? badge('Closed') : ($low ? badge('Low', 'warn') : badge('In use', 'good')) ?></td>
        <td class="xg-act"><a class="btn btn-sm" href="<?= e(url('petty-cash/' . $b['id'])) ?>">Open</a></td>
    </tr><?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $boxes ? ' hidden' : '' ?> data-xg-empty>No petty cash box yet.</p>
</div>
</div>
