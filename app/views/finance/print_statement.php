<?php $docTitle = 'INCOME & EXPENDITURE'; $docSub = ($term ? $term['name'] . ' ' . $term['year_name'] . ' · ' : '') . fmt_date($from) . ' – ' . fmt_date($to); ?>
<div class="card-page doc-page">
    <?php require dirname(__DIR__) . '/fees/_doc_head.php'; ?>
    <?php require __DIR__ . '/_statement_table.php'; ?>
    <div class="rc-sign"><div><span></span>Bursar</div><div><span></span>Head Teacher</div><div><span></span>Board / PTA Chair</div></div>
</div>
