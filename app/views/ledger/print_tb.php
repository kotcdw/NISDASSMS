<?php $docTitle = 'TRIAL BALANCE'; $docSub = 'As at ' . fmt_date($asAt); ?>
<div class="card-page doc-page">
    <?php require dirname(__DIR__) . '/fees/_doc_head.php'; ?>
    <?php require __DIR__ . '/_tb_table.php'; ?>
    <div class="rc-sign"><div><span></span>Prepared by (Bursar)</div><div><span></span>Checked by (Head Teacher)</div></div>
</div>
