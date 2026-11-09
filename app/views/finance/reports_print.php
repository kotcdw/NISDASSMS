<?php $docTitle = strtoupper(FinanceReportController::TABS[$tab]); $docSub = in_array($tab, ['cashflow', 'income', 'tax'], true) ? fmt_date($from) . ' – ' . fmt_date($to) : 'As at ' . fmt_date(date('Y-m-d')); ?>
<div class="card-page doc-page">
    <?php require dirname(__DIR__) . '/fees/_doc_head.php'; ?>
    <?php require __DIR__ . '/_reports_body.php'; ?>
    <div class="rc-sign"><div><span></span>Bursar</div><div><span></span>Head Teacher</div></div>
</div>
