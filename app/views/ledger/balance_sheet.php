<div class="page-head">
    <div><h1>Balance sheet</h1></div>
    <div class="actions"><details class="menu-btn"><summary class="btn">Tools ▾</summary><div>
        <a href="<?= e(url('ledger/balance-sheet', ['as_at' => $asAt, 'print' => 1])) ?>" target="_blank" rel="noopener">Print</a>
        <?= export_links(url('ledger/balance-sheet', ['as_at' => $asAt, 'export' => 1])) ?>
    </div></details></div>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="card" style="padding:0"><?php require __DIR__ . '/_bs_table.php'; ?></div>
