<div class="page-head"><div><h1>Finance reports</h1></div></div>
<div class="report-menu">
    <?php foreach (FinanceReportController::TABS as $k => $label): ?>
    <a class="card report-tile" href="<?= e(url('finance/reports', ['tab' => $k, 'period' => in_array($k, ['cashflow', 'income', 'tax'], true) ? 'term' : null])) ?>">
        <strong><?= e($label) ?></strong><span><?= e(FinanceReportController::ABOUT[$k]) ?></span>
    </a>
    <?php endforeach; ?>
    <a class="card report-tile" href="<?= e(url('finance/board-pack')) ?>" target="_blank" rel="noopener"><strong>Board pack</strong><span>Last month's finance report for the Board, ready to print.</span></a>
    <?php if (Ledger::ready()): ?>
    <a class="card report-tile" href="<?= e(url('ledger/balance-sheet')) ?>"><strong>Balance sheet</strong><span>What the school owns, what it owes, and the school fund.</span></a>
    <a class="card report-tile" href="<?= e(url('ledger/trial-balance')) ?>"><strong>Trial balance</strong><span>Every account's balance, for the accountant or auditor.</span></a>
    <?php endif; ?>
</div>
