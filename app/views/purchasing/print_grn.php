<?php $docTitle = 'GOODS RECEIVED NOTE'; $docSub = $g['grn_no'] . ' · ' . fmt_date($g['received_date']); $q = fn($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.'); ?>
<div class="card-page doc-page">
    <?php require dirname(__DIR__) . '/fees/_doc_head.php'; ?>
    <table class="rc-info">
        <tr><th>Supplier</th><td><?= e($po['supplier_name']) ?></td><th>Order</th><td><?= e($po['po_no']) ?></td></tr>
        <tr><th>Delivery note</th><td><?= e($g['delivery_note'] ?: '—') ?></td><th>Received</th><td><?= e(fmt_date($g['received_date'])) ?></td></tr>
    </table>
    <table class="rc-info" style="margin-top:.6rem">
        <tr><th>Item</th><th style="text-align:right">Ordered</th><th style="text-align:right">Received now</th></tr>
        <?php foreach ($lines as $l): ?><tr><td><?= e($l['description']) ?><?= $l['unit'] ? ' (' . e($l['unit']) . ')' : '' ?></td><td style="text-align:right"><?= e($q($l['ordered'])) ?></td><td style="text-align:right"><strong><?= e($q($l['qty'])) ?></strong></td></tr><?php endforeach; ?>
    </table>
    <?php if ($g['note']): ?><p><strong>Note:</strong> <?= e($g['note']) ?></p><?php endif; ?>
    <?php if ($g['status'] === 'void'): ?><p style="color:#b42318;font-weight:700">CANCELLED: <?= e($g['void_reason']) ?></p><?php endif; ?>
    <div class="rc-sign"><div><span></span>Received by: <?= e($g['by_name'] ?? '') ?> (Storekeeper)</div><div><span></span>Delivered by (supplier)</div></div>
</div>
