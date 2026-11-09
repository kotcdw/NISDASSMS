<?php $docTitle = 'DAILY CASH REPORT'; $docSub = date('D j M Y', strtotime($date)); ?>
<div class="card-page doc-page">
    <?php require __DIR__ . '/_doc_head.php'; ?>
    <?php require __DIR__ . '/_cash_table.php'; ?>
    <table class="rc-info" style="width:60%">
        <?php foreach ($byMethod as $m => $amt): ?><tr><th><?= e(Fees::PAID_BY[$m] ?? $m) ?></th><td style="text-align:right"><?= e(number_format($amt, 2)) ?></td></tr><?php endforeach; ?>
        <tr><th>Total</th><td style="text-align:right"><strong><?= e(number_format($total, 2)) ?></strong></td></tr>
    </table>
    <div class="rc-sign"><div><span></span>Prepared by (Bursar)</div><div><span></span>Cash counted by</div><div><span></span>Head Teacher</div></div>
</div>
