<?php $docTitle = 'PURCHASE ORDER'; $docSub = $po['po_no'] . ' · ' . fmt_date($po['order_date']); $q = fn($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.'); ?>
<div class="card-page doc-page">
    <?php require dirname(__DIR__) . '/fees/_doc_head.php'; ?>
    <table class="rc-info">
        <tr><th>To (supplier)</th><td><?= e($po['supplier_name']) ?><?= $po['supplier_address'] ? ', ' . e($po['supplier_address']) : '' ?><?= $po['supplier_phone'] ? ' · ' . e($po['supplier_phone']) : '' ?><?= $po['tin'] ? ' · TIN ' . e($po['tin']) : '' ?></td></tr>
        <tr><th>Deliver to</th><td><?= e($po['deliver_to'] ?: school_name()) ?><?= $po['expected_date'] ? ' · by ' . e(fmt_date($po['expected_date'])) : '' ?></td></tr>
        <tr><th>Our reference</th><td><?= e($po['po_no']) ?><?= $po['req_no'] ? ' (requisition ' . e($po['req_no']) . ')' : '' ?> — please quote it on your invoice and delivery note</td></tr>
    </table>
    <table class="rc-info" style="margin-top:.6rem">
        <tr><th>Item</th><th style="text-align:right">Qty</th><th style="text-align:right">Price each (GH₵)</th><th style="text-align:right">Amount (GH₵)</th></tr>
        <?php foreach ($lines as $l): ?><tr><td><?= e($l['description']) ?><?= $l['unit'] ? ' (' . e($l['unit']) . ')' : '' ?></td><td style="text-align:right"><?= e($q($l['qty'])) ?></td><td style="text-align:right"><?= e(number_format((float) $l['unit_price'], 2)) ?></td><td style="text-align:right"><?= e(number_format((float) $l['qty'] * (float) $l['unit_price'], 2)) ?></td></tr><?php endforeach; ?>
        <tr><th colspan="3" style="text-align:right">Total</th><th style="text-align:right">GH₵ <?= e(number_format((float) $po['total'], 2)) ?></th></tr>
    </table>
    <p style="font-style:italic;text-align:center"><?= e(money_words((float) $po['total'])) ?></p>
    <?php if ($po['notes']): ?><p><strong>Notes:</strong> <?= e($po['notes']) ?></p><?php endif; ?>
    <p style="font-size:.85rem">We pay only for goods delivered and signed for, at the prices on this order. Withholding tax is deducted where the law requires.</p>
    <div class="rc-sign"><div><span></span>Bursar</div><div><span></span>Head Teacher</div><div><span></span>Supplier (received the order)</div></div>
</div>
