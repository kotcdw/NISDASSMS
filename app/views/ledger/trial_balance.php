<div class="page-head">
    <div><h1>Trial balance</h1></div>
    <div class="actions"><details class="menu-btn"><summary class="btn">Tools ▾</summary><div>
        <a href="<?= e(url('ledger/trial-balance', ['as_at' => $asAt, 'print' => 1])) ?>" target="_blank" rel="noopener">Print</a>
        <?= export_links(url('ledger/trial-balance', ['as_at' => $asAt, 'export' => 1])) ?>
    </div></details></div>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="alert <?= abs($td - $tc) < 0.01 ? 'alert-success' : 'alert-error' ?>"><?= abs($td - $tc) < 0.01 ? '✓ Trial balance agrees: debits = credits = GH₵ ' . e(number_format($td, 2)) : 'Debits and credits differ by ' . e(number_format(abs($td - $tc), 2)) ?></div>
<div class="card" style="padding:0"><?php require __DIR__ . '/_tb_table.php'; ?></div>
