<?php $tin = array_sum(array_column($rows, 'in')); $tout = array_sum(array_column($rows, 'out')); $closing = $opening + $tin - $tout;
$docTitle = 'CASH BOOK'; $docSub = ($acct['name'] ?? '') . ' · ' . fmt_date($from) . ' – ' . fmt_date($to); ?>
<div class="card-page doc-page">
    <?php require dirname(__DIR__) . '/fees/_doc_head.php'; ?>
    <?php require __DIR__ . '/_cashbook_table.php'; ?>
    <div class="rc-sign"><div><span></span>Bursar</div><div><span></span>Checked by</div><div><span></span>Head Teacher</div></div>
</div>
