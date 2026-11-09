<?php $s = $edit ?? []; $o = fn($k, $d = '') => old($k, $s[$k] ?? $d); ?>
<div class="xgrid-box" data-xgrid="suppliers" data-xgrid-file="suppliers">
<div class="page-head">
    <div><h1>Suppliers</h1></div>
    <div class="actions"><?= export_grid_menu() ?><?php if (can('finance.manage')): ?><button type="button" class="btn btn-primary" data-help="sup-form" aria-expanded="<?= $open ? 'true' : 'false' ?>">+ Add supplier</button><?php endif; ?></div>
</div>
<?php require dirname(__DIR__) . '/purchasing/_nav.php'; ?>
<?php if (can('finance.manage')): ?>
<div id="sup-form" class="card"<?= $open ? '' : ' hidden' ?>>
    <form method="post" action="<?= e(url('suppliers/list' . ($edit ? '/' . $edit['id'] : ''))) ?>" class="form">
        <?= csrf_field() ?>
        <h2><?= $edit ? 'Edit ' . e($edit['name']) : 'Add a supplier' ?></h2>
        <div class="grid-2">
            <label class="required">Name<input name="name" required maxlength="150" value="<?= e($o('name')) ?>"></label>
            <label>Contact person<input name="contact_person" maxlength="120" value="<?= e($o('contact_person')) ?>"></label>
            <label>Phone<input name="phone" maxlength="20" value="<?= e($o('phone')) ?>"></label>
            <label>Email<input type="email" name="email" maxlength="190" value="<?= e($o('email')) ?>"></label>
            <label>GRA TIN / Ghana Card no.<input name="tin" maxlength="20" value="<?= e($o('tin')) ?>" placeholder="e.g. C0012345678"></label>
            <label>Usually supplies<select name="supply_type"><?php foreach (Suppliers::TYPES as $k => $l): ?><option value="<?= $k ?>"<?= selected($o('supply_type', 'goods'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
            <label>Usual category<select name="category_id"><option value="">—</option><?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected($o('category_id'), $c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></label>
            <label>Pay to (bank / MoMo details)<input name="pay_details" maxlength="150" value="<?= e($o('pay_details')) ?>" placeholder="e.g. GCB 1234567890 / MoMo 024…"></label>
        </div>
        <label>Address<input name="address" maxlength="255" value="<?= e($o('address')) ?>"></label>
        <label>Notes<input name="notes" maxlength="255" value="<?= e($o('notes')) ?>"></label>
        <?php if ($edit): ?><label style="font-weight:400"><input type="checkbox" name="is_active" value="1"<?= checked($o('is_active', 1)) ?>> Active</label><?php else: ?><label style="font-weight:400"><input type="checkbox" name="then_bill" value="1"> Enter a bill next</label><?php endif; ?>
        <div class="gate-actions"><button class="btn btn-primary" type="submit">Save supplier</button><?php if ($edit): ?><a class="btn btn-ghost" href="<?= e(url('suppliers/list')) ?>">Cancel</a><?php else: ?><button type="button" class="btn btn-ghost" data-help="sup-form">Cancel</button><?php endif; ?></div>
    </form>
</div>
<?php endif; ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['name' => ['Supplier'], 'contact' => ['Contact person'], 'phone' => ['Phone'], 'tin' => ['TIN'], 'type' => ['Supplies'], 'owing' => ['Owed (GH₵)', 'num', 'num'], 'paid' => ['Paid this year (GH₵)', 'num', 'num'], 'state' => ['Status']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
    <tr<?= $r['is_active'] ? '' : ' style="opacity:.55"' ?>>
        <td data-c="name"><strong><?= e($r['name']) ?></strong></td>
        <td data-c="contact"><?= e($r['contact_person'] ?? '') ?></td>
        <td data-c="phone"><?= e($r['phone'] ?? '') ?></td>
        <td data-c="tin"><?= e($r['tin'] ?? '') ?></td>
        <td data-c="type"><?= e(ucfirst($r['supply_type'])) ?></td>
        <td data-c="owing" class="num" data-s="<?= (float) $r['owing'] ?>"><?= (float) $r['owing'] > 0 ? e(number_format((float) $r['owing'], 2)) : '' ?></td>
        <td data-c="paid" class="num" data-s="<?= (float) $r['paid_year'] ?>"><?= (float) $r['paid_year'] > 0 ? e(number_format((float) $r['paid_year'], 2)) : '' ?></td>
        <td data-c="state"><?= $r['is_active'] ? 'Active' : 'Not in use' ?></td>
        <td class="xg-act"><?php if (can('finance.manage')): ?><details class="rowmenu"><summary aria-label="Actions">⋯</summary><div>
            <a href="<?= e(url('suppliers/bills/new', ['supplier' => $r['id']])) ?>">New bill</a><a href="<?= e(url('suppliers/list', ['edit' => $r['id']])) ?>">Edit</a>
        </div></details><?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No suppliers yet.</p>
</div>
</div>
