<?php $q = ['tab' => $tab, 'from' => $from, 'to' => $to]; ?>
<div class="page-head">
    <div><p class="breadcrumb"><a href="<?= e(url('finance/reports')) ?>">Finance reports</a></p><h1><?= e(FinanceReportController::TABS[$tab]) ?></h1></div>
    <div class="actions"><details class="menu-btn"><summary class="btn">Print or export ▾</summary><div>
        <a href="<?= e(url('finance/reports', $q + ['print' => 1])) ?>" target="_blank" rel="noopener">Print</a>
        <?= export_links(url('finance/reports', $q + ['export' => 1])) ?>
        <a href="<?= e(url('finance/board-pack')) ?>" target="_blank" rel="noopener">Board pack (last month)</a>
        <?php if (Ledger::ready()): ?><a href="<?= e(url('ledger/trial-balance')) ?>">Trial balance</a><a href="<?= e(url('ledger/balance-sheet')) ?>">Balance sheet</a><?php endif; ?>
    </div></details></div>
</div>
<nav class="seg">
    <?php foreach (FinanceReportController::TABS as $k => $label): ?><a href="<?= e(url('finance/reports', ['tab' => $k, 'from' => $from, 'to' => $to])) ?>"<?= $tab === $k ? ' class="active"' : '' ?>><?= e($label) ?></a><?php endforeach; ?>
</nav>
<?php if (in_array($tab, ['cashflow', 'income', 'tax'], true)): ?>
<?php endif; ?>
<div class="card" style="padding:0"><?php require __DIR__ . '/_reports_body.php'; ?></div>
