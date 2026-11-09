<div class="xgrid-box" data-xgrid="goods-received" data-xgrid-file="goods-received">
<div class="page-head">
    <div><h1>Goods received</h1></div>
    <div class="actions"><?= export_grid_menu() ?></div>
</div>
<?php require __DIR__ . '/_nav.php'; ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['note' => ['Note'], 'date' => ['Date', 'date'], 'order' => ['Order'], 'supplier' => ['Supplier'], 'by' => ['Signed by'], 'bill' => ['Invoice'], 'state' => ['Note status']]) ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $g): ?>
    <tr<?= $g['status'] === 'void' ? ' style="opacity:.55"' : '' ?>>
        <td data-c="note"><a href="<?= e(url('purchasing/grn/' . $g['id'])) ?>" target="_blank" rel="noopener"><?= e($g['grn_no']) ?></a></td>
        <td data-c="date" data-s="<?= e((string) $g['received_date']) ?>"><?= e(fmt_date($g['received_date'])) ?></td>
        <td data-c="order"><a href="<?= e(url('purchasing/' . $g['po_id'])) ?>"><?= e($g['po_no']) ?></a></td>
        <td data-c="supplier"><?= e($g['supplier_name']) ?></td>
        <td data-c="by"><?= e($g['by_name'] ?? '') ?></td>
        <td data-c="bill"><?= (int) $g['bills'] ? badge('recorded', 'good') : badge('not yet', 'warn') ?></td>
        <td data-c="state"><?= $g['status'] === 'void' ? badge('cancelled', 'neutral') : '' ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>Nothing received yet.</p>
</div>
</div>
<?php if ($waiting): ?>
<div class="xgrid-box" data-xgrid="waiting-delivery" data-xgrid-file="waiting-for-delivery">
<div class="page-head">
    <div><h2>Waiting for delivery</h2></div>
    <div class="actions"><?= export_grid_menu() ?></div>
</div>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['order' => ['Order'], 'supplier' => ['Supplier'], 'expected' => ['Expected', 'date'], 'status' => ['Status']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($waiting as $p): $late = $p['expected_date'] && $p['expected_date'] < date('Y-m-d'); ?>
    <tr>
        <td data-c="order"><a href="<?= e(url('purchasing/' . $p['id'])) ?>"><?= e($p['po_no']) ?></a></td>
        <td data-c="supplier"><?= e($p['supplier_name']) ?></td>
        <td data-c="expected" data-s="<?= e((string) ($p['expected_date'] ?? '')) ?>"><?= $p['expected_date'] ? e(fmt_date($p['expected_date'])) : '' ?></td>
        <td data-c="status"><?= Purchasing::badge($p['status']) ?><?= $late ? '<span hidden> · </span> ' . badge('late', 'bad') : '' ?></td>
        <td class="xg-act"><?php if (can('purchasing.receive')): ?><a class="btn btn-sm btn-primary" href="<?= e(url('purchasing/' . $p['id'])) ?>">Sign for goods</a><?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
</div>
<?php endif; ?>
