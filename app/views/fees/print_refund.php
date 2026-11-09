<?php $docTitle = $r['status'] === 'paid' ? 'REFUND VOUCHER' : 'REFUND REQUEST — ' . strtoupper(Family::STATUS[$r['status']][0]); $docSub = $r['rf_no']; $docType = 'refund'; $docHalf = true; ob_start(); ?>
    <?php require __DIR__ . '/_doc_head.php'; ?>
    <table class="rc-info">
        <tr><th>Pay to</th><td colspan="3"><?= e($r['pay_to']) ?><?= $r['pay_details'] ? ' · ' . e($r['pay_details']) : '' ?></td></tr>
        <tr><th>Family</th><td><?= e($r['guardian_name']) ?> · <?= e($r['phone']) ?></td><th>Children</th><td><?= e(implode(', ', array_map(fn($k) => $k['first_name'] . ' ' . $k['last_name'], $kids))) ?></td></tr>
        <tr><th>Reason</th><td colspan="3"><?= e($r['reason']) ?></td></tr>
        <tr><th>Paid by</th><td><?= e(Family::REFUND_METHODS[$r['method']]) ?><?= $r['reference'] ? ' · ' . e($r['reference']) : '' ?></td><th>From account</th><td><?= e($r['account_name'] ?? '—') ?></td></tr>
        <tr><th>Date paid</th><td colspan="3"><?= $r['paid_on'] ? e(fmt_date($r['paid_on'])) : 'Not paid yet' ?></td></tr>
    </table>
    <div class="doc-amount">Amount: <strong><?= e(Fees::money($r['amount'])) ?></strong></div>
    <p class="doc-words"><?= e(money_words($r['amount'])) ?></p>
    <div class="rc-sign"><div><span></span>Requested by: <?= e($r['requested_name'] ?? '') ?></div><div><span></span>Approved by: <?= e($r['decided_name'] ?? '') ?></div><div><span></span>Paid by: <?= e($r['paid_name'] ?? '') ?></div></div>
    <div class="rc-sign"><div><span></span>Received by (parent's signature)</div><div></div><div><span></span>Date</div></div>
    <p class="rc-key">Printed <?= e(date('d M Y H:i')) ?>.</p>
<?php $docBody = ob_get_clean(); require __DIR__ . '/_copies.php'; ?>
