<?php
$tabs = [];
foreach (['open' => 'Not yet paid', 'paid' => 'Paid', 'all' => 'All bills'] as $k => $l) { $tabs[$k] = [$l, url('suppliers', ['show' => $k])]; }
?>
<div class="xgrid-box" data-xgrid="supplier-bills" data-xgrid-file="supplier-bills">
<div class="page-head">
    <div><h1>Bills to pay</h1></div>
    <div class="actions"><?= export_grid_menu() ?><?php if (can('finance.manage')): ?><a class="btn btn-primary" href="<?= e(url('suppliers/bills/new')) ?>">+ New bill</a><?php endif; ?></div>
</div>
<?php require dirname(__DIR__) . '/purchasing/_nav.php'; ?>
<?= card_tabs($tabs, $show, 4, 'Bills to show') ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['no' => ['Bill'], 'supplier' => ['Supplier'], 'what' => ['For'], 'date' => ['Date', 'date'], 'due' => ['Due', 'date'], 'amount' => ['Amount (GH₵)', 'num', 'num'], 'wht' => ['WHT (GH₵)', 'num', 'num'], 'bal' => ['Balance (GH₵)', 'num', 'num'], 'status' => ['Status'], 'ref' => ['Supplier ref.'], 'cat' => ['Category']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $b): [$sl, $st] = Suppliers::STATUS[$b['status']]; $bal = Suppliers::balance($b); $late = $bal > 0 && $b['due_date'] && $b['due_date'] < date('Y-m-d'); ?>
    <tr>
        <td data-c="no"><a href="<?= e(url('suppliers/bills/' . $b['id'])) ?>"><?= e($b['bill_no']) ?></a></td>
        <td data-c="supplier"><?= e($b['supplier_name']) ?></td>
        <td data-c="what"><?= e($b['description']) ?></td>
        <td data-c="date" data-s="<?= e($b['bill_date']) ?>"><?= e(fmt_date($b['bill_date'], 'j M Y')) ?></td>
        <td data-c="due" data-s="<?= e($b['due_date'] ?? '') ?>"<?= $late ? ' style="color:var(--bad);font-weight:600"' : '' ?>><?= $b['due_date'] ? e(fmt_date($b['due_date'], 'j M Y')) : '' ?></td>
        <td data-c="amount" class="num" data-s="<?= (float) $b['amount'] ?>"><?= e(number_format((float) $b['amount'], 2)) ?></td>
        <td data-c="wht" class="num" data-s="<?= (float) $b['wht_amount'] ?>"><?= (float) $b['wht_amount'] > 0 ? e(number_format((float) $b['wht_amount'], 2)) : '' ?></td>
        <td data-c="bal" class="num" data-s="<?= $bal ?>"><?= $bal > 0 ? '<strong>' . e(number_format($bal, 2)) . '</strong>' : '' ?></td>
        <td data-c="status"><?= badge($sl, $st) ?></td>
        <td data-c="ref"><?= e($b['supplier_ref'] ?? '') ?></td>
        <td data-c="cat"><?= e($b['category_name'] ?? '') ?></td>
        <td class="xg-act"><?php if ($bal > 0 && can('finance.manage')): ?><a class="btn btn-sm btn-primary" href="<?= e(url('suppliers/bills/' . $b['id'])) ?>">Pay</a><?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty><?= $show === 'open' ? 'Nothing owed to suppliers.' : 'No bills.' ?></p>
</div>
</div>
