<?php $docTitle = $t['status'] === 'void' ? 'PETTY CASH VOUCHER — CANCELLED' : 'PETTY CASH VOUCHER'; $docSub = $t['txn_no']; $docType = 'pcv'; $docHalf = true; ob_start(); ?>
    <?php require dirname(__DIR__) . '/fees/_doc_head.php'; ?>
    <table class="rc-info">
        <tr><th>Box</th><td><?= e($box['name']) ?></td><th>Date</th><td><?= e(fmt_date($t['txn_date'])) ?></td></tr>
        <tr><th>Paid to</th><td><?= e($t['party'] ?? '') ?></td><th>Receipt no.</th><td><?= e($t['reference'] ?? '') ?></td></tr>
        <tr><th>For</th><td colspan="3"><?= e($t['description']) ?> (<?= e($t['category_name'] ?? '') ?>)</td></tr>
    </table>
    <div class="doc-amount">Amount: <strong><?= e(Fees::money($t['amount'])) ?></strong></div>
    <p class="doc-words"><?= e(money_words($t['amount'])) ?></p>
    <div class="rc-sign"><div><span></span>Paid by: <?= e($t['by_name'] ?? '') ?></div><div><span></span>Received by</div><div><span></span>Approved</div></div>
<?php $docBody = ob_get_clean(); require dirname(__DIR__) . '/fees/_copies.php'; ?>
