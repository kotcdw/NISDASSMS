<?php $print = true; $docTitle = strtoupper(AnalyticsController::TABS[$tab][0]) . ' REPORT'; $docSub = ($term['year_name'] ?? '') . ' · ' . ($term['name'] ?? ''); ?>
<div class="card-page doc-page">
    <?php require dirname(__DIR__) . '/fees/_doc_head.php'; ?>
    <div class="print-tables"><?php require __DIR__ . '/_body.php'; ?></div>
    <p style="font-size:.8rem;color:#555">Printed <?= e(date('d M Y H:i')) ?></p>
    <div class="rc-sign"><div><span></span>Prepared by</div><div><span></span>Head Teacher</div></div>
</div>
