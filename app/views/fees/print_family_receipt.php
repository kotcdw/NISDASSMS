<?php
$docTitle = 'OFFICIAL RECEIPT'; $docSub = $no;
$total = array_sum(array_map(fn($p) => $p['status'] === 'completed' ? (float) $p['amount'] : 0, $pays)) + ($adv && $adv['status'] === 'posted' ? (float) $adv['amount'] : 0);
$first = $pays[0] ?? $adv;
$date = $pays ? $pays[0]['paid_on'] : $adv['entry_date'];
$method = $pays ? $pays[0]['method'] : $adv['method'];
$ref = $pays ? $pays[0]['reference'] : $adv['reference'];
$docType = 'family'; $docHalf = count($pays) <= 3;
ob_start();
?>
    <?php require __DIR__ . '/_doc_head.php'; ?>
    <table class="rc-info">
        <tr><th>Received from</th><td colspan="3"><?= e($pays[0]['payer_name'] ?? ($g['name'] ?? 'Parent / guardian')) ?><?= $g ? ' · ' . e($g['phone']) : '' ?></td></tr>
        <tr><th>Date</th><td><?= e(fmt_date($date)) ?></td><th>Paid by</th><td><?= e(Fees::PAID_BY[$method] ?? $method) ?><?= $ref ? ' · ' . e($ref) : '' ?></td></tr>
    </table>
    <table class="rc-scores">
        <thead><tr><th class="l">Student</th><th>Bill</th><th class="l">For</th><th>Receipt</th><th>Amount (GH₵)</th></tr></thead>
        <tbody>
        <?php foreach ($pays as $p): ?><tr<?= $p['status'] !== 'completed' ? ' style="text-decoration:line-through"' : '' ?>><td class="l"><?= e($p['first_name'] . ' ' . $p['last_name']) ?> (<?= e($p['admission_no']) ?>)</td><td><?= e($p['invoice_no']) ?></td><td class="l"><?= e($p['title']) ?></td><td><?= e($p['receipt_no']) ?></td><td style="text-align:right"><?= e(number_format((float) $p['amount'], 2)) ?></td></tr><?php endforeach; ?>
        <?php if ($adv): ?><tr<?= $adv['status'] !== 'posted' ? ' style="text-decoration:line-through"' : '' ?>><td class="l" colspan="4">Advance — kept as family credit for the next bill</td><td style="text-align:right"><?= e(number_format((float) $adv['amount'], 2)) ?></td></tr><?php endif; ?>
        </tbody>
    </table>
    <div class="doc-amount">Total received: <strong><?= e(Fees::money($total)) ?></strong></div>
    <p class="doc-words"><?= e(money_words($total)) ?></p>
    <?php if ($gid): $kidIds = array_map(fn($k) => (int) $k['id'], Family::children((int) $gid)); $owing = (float) db()->value("SELECT COALESCE(SUM(total - paid), 0) FROM invoices WHERE status <> 'cancelled' AND pupil_id IN (" . implode(',', $kidIds ?: [0]) . ')'); ?>
    <table class="rc-info"><tr><th>Still owed for all the children</th><td><strong><?= e(Fees::money(max(0, $owing))) ?></strong></td><th>Family credit now</th><td><?= e(Fees::money(Family::credit((int) $gid))) ?></td></tr></table>
    <?php endif; ?>
    <div class="rc-sign"><div><span></span>Received by: <?= e($pays[0]['received_by_name'] ?? ($adv['received_by_name'] ?? '')) ?></div><div></div><div><span></span>School stamp</div></div>
    <p class="rc-key">Printed <?= e(date('d M Y H:i')) ?>. Please keep this receipt.<?= setting('school_phone') ? ' Enquiries: ' . e(setting('school_phone')) : '' ?></p>
<?php $docBody = ob_get_clean(); require __DIR__ . '/_copies.php'; ?>
