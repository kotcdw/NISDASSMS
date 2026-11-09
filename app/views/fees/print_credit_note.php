<?php $docTitle = $cn['status'] === 'approved' ? 'CREDIT NOTE' : 'CREDIT NOTE — ' . strtoupper(Family::STATUS[$cn['status']][0]); $docSub = $cn['cn_no']; $docType = 'credit'; $docHalf = true; ob_start(); ?>
    <?php require __DIR__ . '/_doc_head.php'; ?>
    <table class="rc-info">
        <tr><th>To</th><td colspan="3"><?= e($g['name'] ?? 'Parent / guardian') ?><?= $g ? ' · ' . e($g['phone']) : '' ?></td></tr>
        <tr><th>Student</th><td><?= e(full_name($cn)) ?></td><th>Admission no.</th><td><?= e($cn['admission_no']) ?></td></tr>
        <tr><th>Class</th><td><?= e($cn['class_name'] ?? '') ?></td><th>Date</th><td><?= e(fmt_date(substr((string) ($cn['decided_at'] ?? $cn['requested_at']), 0, 10))) ?></td></tr>
        <tr><th>Bill</th><td colspan="3"><?= e($cn['invoice_no']) ?> — <?= e($cn['title']) ?></td></tr>
        <tr><th>Reason</th><td colspan="3"><?= e($cn['reason']) ?></td></tr>
    </table>
    <div class="doc-amount">Amount credited: <strong><?= e(Fees::money($cn['amount'])) ?></strong></div>
    <p class="doc-words"><?= e(money_words($cn['amount'])) ?></p>
    <?php if ((float) $cn['to_credit'] > 0): ?><p>The bill had already been paid, so <?= e(Fees::money($cn['to_credit'])) ?> is kept as family credit for the next bill (or can be refunded).</p><?php endif; ?>
    <div class="rc-sign"><div><span></span>Prepared by: <?= e($cn['requested_name'] ?? '') ?></div><div><span></span>Approved by: <?= e($cn['decided_name'] ?? '') ?></div><div><span></span>School stamp</div></div>
    <p class="rc-key">Printed <?= e(date('d M Y H:i')) ?>.</p>
<?php $docBody = ob_get_clean(); require __DIR__ . '/_copies.php'; ?>
