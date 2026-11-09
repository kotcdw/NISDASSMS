<?php
$docTitle = 'BANK RECONCILIATION'; $docSub = $r['account_name'] . ' · ' . fmt_date($r['period_end']);
$in = 0.0; $outAmt = 0.0; foreach ($out as $t) { $t['effect'] > 0 ? $in += $t['effect'] : $outAmt -= $t['effect']; }
?>
<div class="card-page doc-page">
    <?php require dirname(__DIR__) . '/fees/_doc_head.php'; ?>
    <table class="rc-info">
        <tr><th>Account</th><td><?= e($r['account_name']) ?><?= $r['account_no'] ? ' · ' . e($r['account_no']) : '' ?></td><th>Statement date</th><td><?= e(fmt_date($r['period_end'])) ?></td></tr>
    </table>
    <table class="rc-info" style="width:70%">
        <tr><th>Balance as per statement</th><td style="text-align:right"><?= e(number_format((float) $r['statement_balance'], 2)) ?></td></tr>
        <tr><th>Add: money in not yet on the statement</th><td style="text-align:right"><?= e(number_format($in, 2)) ?></td></tr>
        <tr><th>Less: money out not yet on the statement (e.g. unpresented cheques)</th><td style="text-align:right">(<?= e(number_format($outAmt, 2)) ?>)</td></tr>
        <tr><th>Adjusted balance</th><td style="text-align:right"><strong><?= e(number_format((float) $r['statement_balance'] + $in - $outAmt, 2)) ?></strong></td></tr>
        <tr><th>Balance as per cash book</th><td style="text-align:right"><strong><?= e(number_format((float) $r['book_balance'], 2)) ?></strong></td></tr>
    </table>
    <?php if ($out): ?>
    <h3>Items not yet on the statement</h3>
    <table class="rc-scores">
        <thead><tr><th>Date</th><th>No.</th><th class="l">Details</th><th>In</th><th>Out</th></tr></thead>
        <tbody><?php foreach ($out as $t): ?><tr><td><?= e(fmt_date($t['txn_date'], 'd/m/Y')) ?></td><td><?= e($t['txn_no']) ?></td><td class="l"><?= e($t['description']) ?></td><td style="text-align:right"><?= $t['effect'] > 0 ? e(number_format($t['effect'], 2)) : '' ?></td><td style="text-align:right"><?= $t['effect'] < 0 ? e(number_format(-$t['effect'], 2)) : '' ?></td></tr><?php endforeach; ?></tbody>
    </table>
    <?php endif; ?>
    <?php if ($r['notes']): ?><p>Notes: <?= e($r['notes']) ?></p><?php endif; ?>
    <div class="rc-sign"><div><span></span>Prepared by: <?= e($r['prepared_name'] ?? '') ?></div><div><span></span>Checked by: <?= e($r['completed_name'] ?? '') ?></div><div><span></span>Head Teacher</div></div>
    <p class="rc-key">Printed <?= e(date('d M Y H:i')) ?>.</p>
</div>
