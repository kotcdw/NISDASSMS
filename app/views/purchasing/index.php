<?php
$tabs = [];
foreach (['open' => 'Still open', 'closed' => 'Closed', 'cancelled' => 'Cancelled', 'all' => 'All orders'] as $k => $l) { $tabs[$k] = [$l, url('purchasing', ['status' => $k])]; }
?>
<div class="xgrid-box" data-xgrid="purchase-orders" data-xgrid-file="purchase-orders">
<div class="page-head">
    <div><h1>Orders</h1></div>
    <div class="actions"><?= export_grid_menu() ?></div>
</div>
<?php require __DIR__ . '/_nav.php'; ?>
<?= card_tabs($tabs, isset($tabs[$status]) ? $status : 'all', 4, 'Orders to show') ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['order' => ['Order'], 'date' => ['Date', 'date'], 'supplier' => ['Supplier'], 'req' => ['Request'], 'dept' => ['Department'], 'total' => ['Amount (GH₵)', 'num', 'num'], 'got' => ['Received', 'num'], 'status' => ['Status'], 'bill' => ['Invoice']]) ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $p): $pct = (float) $p['q'] > 0 ? (int) round((float) $p['rq'] * 100 / (float) $p['q']) : 0; ?>
    <tr>
        <td data-c="order"><a href="<?= e(url('purchasing/' . $p['id'])) ?>"><?= e($p['po_no']) ?></a></td>
        <td data-c="date" data-s="<?= e((string) $p['order_date']) ?>"><?= e(fmt_date($p['order_date'])) ?></td>
        <td data-c="supplier"><?= e($p['supplier_name']) ?></td>
        <td data-c="req"><?= e($p['req_no'] ?? '') ?></td>
        <td data-c="dept"><?= e($p['department'] ?? '') ?></td>
        <td data-c="total" class="num" data-s="<?= (float) $p['total'] ?>"><?= e(number_format((float) $p['total'], 2)) ?></td>
        <td data-c="got" data-s="<?= $pct ?>"><div style="display:flex;gap:.4rem;align-items:center"><div class="bar-meter"><span style="width:<?= min(100, $pct) ?>%"></span></div><small><?= $pct ?>%</small></div></td>
        <td data-c="status"><?= Purchasing::badge($p['status']) ?></td>
        <td data-c="bill"><?= (int) $p['bills'] ? 'Recorded' : '' ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No purchase orders here.</p>
</div>
</div>
<?php if ($ready): ?>
<div class="xgrid-box" data-xgrid="ready-to-order" data-xgrid-file="ready-to-order">
<div class="page-head">
    <div><h2>Approved — ready to order</h2></div>
    <div class="actions"><?= export_grid_menu() ?></div>
</div>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['no' => ['Request'], 'title' => ['Title'], 'dept' => ['Department'], 'amt' => ['Amount (GH₵)', 'num', 'num']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($ready as $r): ?>
    <tr>
        <td data-c="no"><a href="<?= e(url('requisitions/' . $r['id'])) ?>"><?= e($r['req_no']) ?></a></td>
        <td data-c="title"><?= e($r['title']) ?></td>
        <td data-c="dept"><?= e($r['department'] ?? '') ?></td>
        <td data-c="amt" class="num" data-s="<?= (float) $r['amt'] ?>"><?= e(number_format((float) $r['amt'], 2)) ?></td>
        <td class="xg-act"><a class="btn btn-sm btn-primary" href="<?= e(url('purchasing/new', ['req' => $r['id']])) ?>">Make order</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
</div>
<?php endif; ?>
