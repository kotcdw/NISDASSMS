<?php $docTitle = $p['status'] === 'void' ? 'PAYMENT VOUCHER — CANCELLED' : 'PAYMENT VOUCHER'; $docSub = $p['pv_no']; $docType = 'pv'; $docHalf = true; ob_start(); ?>
    <?php require dirname(__DIR__) . '/fees/_doc_head.php'; ?>
    <table class="rc-info">
        <tr><th>Pay to</th><td colspan="3"><?= e($b['supplier_name']) ?><?= $b['tin'] ? ' · TIN ' . e($b['tin']) : '' ?><?= $b['pay_details'] ? ' · ' . e($b['pay_details']) : '' ?></td></tr>
        <tr><th>Being payment for</th><td colspan="3"><?= e($b['description']) ?> — bill <?= e($b['bill_no']) ?><?= $b['supplier_ref'] ? ' (supplier\'s invoice ' . e($b['supplier_ref']) . ')' : '' ?></td></tr>
        <tr><th>Category</th><td><?= e($b['category_name'] ?? '') ?></td><th>Date</th><td><?= e(fmt_date($p['pay_date'])) ?></td></tr>
        <tr><th>Paid from</th><td><?= e($p['account_name']) ?></td><th>Reference</th><td><?= e($p['reference'] ?? '') ?></td></tr>
        <?php if ($b['req_no']): ?><tr><th>Requisition</th><td colspan="3"><?= e($b['req_no']) ?></td></tr><?php endif; ?>
    </table>
    <table class="rc-info" style="width:60%">
        <tr><th>Amount settled on the bill</th><td style="text-align:right"><?= e(number_format((float) $p['gross'], 2)) ?></td></tr>
        <tr><th>Less withholding tax (<?= e(rtrim(rtrim(number_format((float) $b['wht_rate'], 2), '0'), '.')) ?>%)</th><td style="text-align:right">(<?= e(number_format((float) $p['wht'], 2)) ?>)</td></tr>
        <tr><th>Amount paid</th><td style="text-align:right"><strong><?= e(Fees::money($p['net'])) ?></strong></td></tr>
    </table>
    <p class="doc-words" style="text-align:left"><?= e(money_words($p['net'])) ?></p>
    <div class="rc-sign"><div><span></span>Prepared by: <?= e($p['by_name'] ?? '') ?></div><div><span></span>Approved by (Head Teacher)</div><div><span></span>Received by (supplier)</div></div>
    <p class="rc-key">Printed <?= e(date('d M Y H:i')) ?>.</p>
<?php $docBody = ob_get_clean(); require dirname(__DIR__) . '/fees/_copies.php'; ?>
