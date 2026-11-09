<?php $docTitle = $pay['status'] === 'reversed' ? 'RECEIPT — REVERSED' : 'OFFICIAL RECEIPT'; $docSub = $pay['receipt_no']; $docType = 'receipt'; ob_start(); ?>
    <?php require __DIR__ . '/_doc_head.php'; ?>
    <?php if ($pay['status'] === 'reversed'): ?><p class="doc-void">This receipt was reversed on <?= e(fmt_date(substr((string) $pay['reversed_at'], 0, 10))) ?>: <?= e($pay['reversal_reason']) ?></p><?php endif; ?>
    <table class="rc-info">
        <tr><th>Received from</th><td colspan="3"><?= e($pay['payer_name'] ?: ($guardian['name'] ?? 'Parent / guardian')) ?><?= ($pay['payer_phone'] ?: ($guardian['phone'] ?? '')) ? ' · ' . e($pay['payer_phone'] ?: $guardian['phone']) : '' ?></td></tr>
        <tr><th>Student</th><td><?= e(full_name($pay)) ?></td><th>Admission no.</th><td><?= e($pay['admission_no']) ?></td></tr>
        <tr><th>Class</th><td><?= e($pay['class_name'] ?? '') ?></td><th>Date</th><td><?= e(fmt_date($pay['paid_on'])) ?></td></tr>
        <tr><th>Being payment for</th><td colspan="3"><?= e($pay['title']) ?> (bill <?= e($pay['invoice_no']) ?>)</td></tr>
        <tr><th>Paid by</th><td><?= e(Fees::PAID_BY[$pay['method']] ?? $pay['method']) ?></td><th>Reference</th><td><?= e($pay['reference'] ?? '') ?></td></tr>
    </table>
    <div class="doc-amount">Amount received: <strong><?= e(Fees::money($pay['amount'])) ?></strong></div>
    <p class="doc-words"><?= e(money_words($pay['amount'])) ?></p>
    <table class="rc-info">
        <tr><th>Bill total</th><td><?= e(Fees::money($pay['total'])) ?></td><th>Balance on this bill</th><td><?= e(Fees::money(max(0, (float) $pay['total'] - (float) $pay['inv_paid']))) ?></td></tr>
        <?php if ($fam && $fam['kids'] > 1): ?>
        <tr><th>Owed for this child</th><td><?= e(Fees::money(max(0, $pupilBalance))) ?></td><th>Owed for all <?= (int) $fam['kids'] ?> children</th><td><strong><?= e(Fees::money(max(0, $fam['owing']))) ?></strong></td></tr>
        <?php else: ?>
        <tr><th>Total owed (all bills, with earlier terms)</th><td<?= $fam ? '' : ' colspan="3"' ?>><strong><?= e(Fees::money(max(0, $pupilBalance))) ?></strong></td><?php if ($fam): ?><th>Family credit</th><td><?= e(Fees::money($fam['credit'])) ?></td><?php endif; ?></tr>
        <?php endif; ?>
        <?php if ($fam && $fam['kids'] > 1): ?><tr><th>Family credit (paid ahead)</th><td colspan="3"><?= e(Fees::money($fam['credit'])) ?></td></tr><?php endif; ?>
    </table>
    <div class="rc-sign"><div><span></span>Received by: <?= e($pay['method'] === 'online' ? 'Online payment' : ($pay['received_by_name'] ?? '')) ?></div><div></div><div><span></span>School stamp</div></div>
    <p class="rc-key">Printed <?= e(date('d M Y H:i')) ?>. Please keep this receipt.<?= setting('school_phone') ? ' Enquiries: ' . e(setting('school_phone')) : '' ?></p>
<?php $docBody = ob_get_clean(); require __DIR__ . '/_copies.php'; ?>
