<?php $docTitle = 'PETTY CASH TOP-UP CLAIM'; $docSub = $t['txn_no'] . ' · ' . fmt_date($t['txn_date']); $sum = array_sum(array_map(fn($r) => (float) $r['amount'], $rows)); ?>
<div class="card-page doc-page">
    <?php require dirname(__DIR__) . '/fees/_doc_head.php'; ?>
    <table class="rc-info"><tr><th>Box</th><td><?= e($box['name']) ?> (held by <?= e($box['custodian_name'] ?? '') ?>)</td><th>Float</th><td><?= e(Fees::money($box['float_amount'])) ?></td></tr></table>
    <table class="rc-scores">
        <thead><tr><th>Voucher</th><th>Date</th><th class="l">For</th><th class="l">Category</th><th>Amount (GH₵)</th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?><tr><td><?= e($r['txn_no']) ?></td><td><?= e(fmt_date($r['txn_date'], 'd/m/Y')) ?></td><td class="l"><?= e($r['description']) ?></td><td class="l"><?= e($r['category_name'] ?? '') ?></td><td style="text-align:right"><?= e(number_format((float) $r['amount'], 2)) ?></td></tr><?php endforeach; ?></tbody>
        <tfoot><tr><td colspan="4" class="l"><strong>Total vouchers</strong></td><td style="text-align:right"><strong><?= e(number_format($sum, 2)) ?></strong></td></tr></tfoot>
    </table>
    <p>Topped up <strong><?= e(Fees::money($t['amount'])) ?></strong> from <?= e($t['from_name']) ?> by <?= e($t['by_name'] ?? '') ?>.</p>
    <div class="rc-sign"><div><span></span>Box holder</div><div><span></span>Checked by (Bursar)</div><div><span></span>Head Teacher</div></div>
</div>
