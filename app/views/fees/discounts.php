<?php
$edit = can('app.configure');
$ed = $edit && !empty($_GET['edit']) ? current(array_filter($discounts, fn($d) => (int) $d['id'] === (int) $_GET['edit'])) : null;
?>
<div class="xgrid-box" data-xgrid="fee-discounts" data-xgrid-file="discount-types">
<div class="page-head">
    <div><h1>Discounts &amp; scholarships</h1></div>
    <div class="actions">
        <?= export_grid_menu() ?>
        <?php if ($edit): ?><button type="button" class="btn btn-primary" data-help="disc-form" aria-expanded="<?= $ed ? 'true' : 'false' ?>">+ New discount</button><?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>
<?php if ($edit): ?>
<form id="disc-form" method="post" action="<?= e(url('fees/discounts' . ($ed ? '/' . $ed['id'] : ''))) ?>" class="card form list-form"<?= $ed ? '' : ' hidden' ?>><?= csrf_field() ?>
    <h2><?= $ed ? 'Edit ' . e($ed['name']) : 'New discount' ?></h2>
    <div class="grid-2">
        <label class="required">Name<input name="name" maxlength="80" required value="<?= e($ed['name'] ?? '') ?>" placeholder="e.g. Pastor's child"></label>
        <label class="required">Value<span style="display:flex;gap:.4rem"><input name="value" inputmode="decimal" required value="<?= e($ed ? rtrim(rtrim($ed['value'], '0'), '.') : '') ?>"><select name="kind" style="width:6rem" aria-label="Kind of value"><option value="percent"<?= selected($ed['kind'] ?? 'percent', 'percent') ?>>%</option><option value="fixed"<?= selected($ed['kind'] ?? '', 'fixed') ?>>GH₵</option></select></span></label>
    </div>
    <div style="display:flex;gap:1rem;flex-wrap:wrap">
        <label style="font-weight:400;margin:0" title="Given automatically to the 2nd, 3rd… child of the same guardian"><input type="checkbox" name="is_sibling" value="1"<?= checked((bool) ($ed['is_sibling'] ?? false)) ?>> Sibling (automatic)</label>
        <?php if ($ed): ?><label style="font-weight:400;margin:0"><input type="checkbox" name="is_active" value="1"<?= checked((bool) $ed['is_active']) ?>> Active</label><?php endif; ?>
    </div>
    <div class="gate-actions"><button class="btn btn-primary" type="submit"><?= $ed ? 'Save' : 'Add' ?></button><?php if ($ed): ?><a class="btn btn-ghost" href="<?= e(url('fees/discounts')) ?>">Cancel</a><?php else: ?><button type="button" class="btn btn-ghost" data-help="disc-form">Cancel</button><?php endif; ?></div>
</form>
<?php endif; ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['name' => ['Discount'], 'value' => ['Value'], 'pupils' => ['Students'], 'status' => ['Status']]) ?><?php if ($edit): ?><th class="xg-act"></th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($discounts as $d): ?><tr<?= $d['is_active'] ? '' : ' style="opacity:.55"' ?>>
        <td data-c="name"><?= e($d['name']) ?></td>
        <td data-c="value" data-s="<?= e((string) (float) $d['value']) ?>"><?= $d['kind'] === 'percent' ? e((float) $d['value'] . '%') : e(Fees::money($d['value'])) ?></td>
        <td data-c="pupils"><?= $d['is_sibling'] ? 'Automatic (siblings)' : (int) $d['pupils'] ?></td>
        <td data-c="status"><?= $d['is_active'] ? badge('Active', 'good') : badge('Not in use') ?></td>
        <?php if ($edit): ?><td class="xg-act"><a class="btn btn-sm" href="<?= e(url('fees/discounts', ['edit' => $d['id']])) ?>">Edit</a></td><?php endif; ?>
    </tr><?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $discounts ? ' hidden' : '' ?> data-xg-empty>No discounts yet.</p>
</div>
</div>

<div class="xgrid-box" data-xgrid="fee-discount-students" data-xgrid-file="students-with-a-discount" style="margin-top:1rem">
<div class="page-head">
    <div><h2>Students with a discount</h2></div>
    <div class="actions"><?= export_grid_menu() ?></div>
</div>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['student' => ['Student'], 'adm' => ['Admission no.'], 'discount' => ['Discount'], 'note' => ['Note']]) ?></tr></thead>
    <tbody>
    <?php foreach ($assigned as $a): ?><tr>
        <td data-c="student"><a href="<?= e(url('fees/pupil/' . $a['pupil_id'])) ?>"><?= e($a['first_name'] . ' ' . $a['last_name']) ?></a></td>
        <td data-c="adm"><?= e($a['admission_no']) ?></td>
        <td data-c="discount"><?= e($a['discount_name']) ?></td>
        <td data-c="note"><?= e($a['note'] ?? '') ?></td>
    </tr><?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $assigned ? ' hidden' : '' ?> data-xg-empty>No student has a discount yet.</p>
</div>
</div>
