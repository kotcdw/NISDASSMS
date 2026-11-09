<div class="page-head">
    <div><h1>Money in &amp; out by category</h1></div>
    <div class="actions"><a class="btn" href="<?= e(url('finance/statement', array_filter(['term' => $term['id'] ?? null, 'from' => $term ? null : $from, 'to' => $term ? null : $to, 'print' => 1]))) ?>" target="_blank" rel="noopener">Print</a></div>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="card" style="max-width:760px"><div class="table-wrap"><?php require __DIR__ . '/_statement_table.php'; ?></div></div>
