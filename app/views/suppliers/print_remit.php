<?php $docTitle = 'WITHHOLDING TAX SCHEDULE'; $docSub = $w['wr_no'] . ' · ' . fmt_date($w['remit_date']); ?>
<div class="card-page doc-page">
    <?php require dirname(__DIR__) . '/fees/_doc_head.php'; ?>
    <table class="rc-scores">
        <thead><tr><th>Date</th><th class="l">Supplier</th><th>TIN</th><th>Voucher</th><th>Rate</th><th>Gross (GH₵)</th><th>WHT (GH₵)</th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?><tr><td><?= e(fmt_date($r['pay_date'], 'd/m/Y')) ?></td><td class="l"><?= e($r['supplier_name']) ?></td><td><?= e($r['tin'] ?? '') ?></td><td><?= e($r['pv_no']) ?></td><td><?= e(rtrim(rtrim(number_format((float) $r['wht_rate'], 2), '0'), '.')) ?>%</td><td style="text-align:right"><?= e(number_format((float) $r['gross'], 2)) ?></td><td style="text-align:right"><?= e(number_format((float) $r['wht'], 2)) ?></td></tr><?php endforeach; ?></tbody>
        <tfoot><tr><td colspan="6" class="l"><strong>Total paid to GRA</strong></td><td style="text-align:right"><strong><?= e(number_format((float) $w['amount'], 2)) ?></strong></td></tr></tfoot>
    </table>
    <p>Paid from <?= e($w['account_name']) ?><?= $w['reference'] ? ' · GRA ref. ' . e($w['reference']) : '' ?> · by <?= e($w['by_name'] ?? '') ?></p>
    <div class="rc-sign"><div><span></span>Bursar / Accountant</div><div><span></span>Head Teacher</div><div></div></div>
</div>
