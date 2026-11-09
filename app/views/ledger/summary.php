<?php $bad = array_filter($controls, fn($c) => !$c[1]); $t = $bs['totals']; ?>
<div class="page-head">
    <div><h1>The books</h1></div>
    <div class="actions"></div>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="alert <?= $balanced && !$bad ? 'alert-success' : 'alert-error' ?>" role="status">
    <?= $balanced ? ($bad ? count($bad) . ' check' . (count($bad) > 1 ? 's do' : ' does') . ' not agree — see below.' : '✓ The books balance and agree with Fees, the cash book and Bills to pay.') : 'The books do not balance. Tell the system administrator.' ?>
</div>
<?php if ($pending): ?><div class="alert alert-warning"><?= $pending ?> correction<?= $pending > 1 ? 's are' : ' is' ?> waiting for the Head's approval. <a href="<?= e(url('ledger/journals')) ?>">Open</a></div><?php endif; ?>
<div class="cols">
    <div class="card" style="padding:0">
        <div class="table-wrap"><table data-nopage><tbody>
            <tr><td>What the school owns <small class="muted">(money, fees owed by parents, buildings and equipment after wear)</small></td><td class="num"><strong><?= e(Fees::money($t['asset'])) ?></strong></td></tr>
            <tr><td>What the school owes <small class="muted">(supplier bills, family credit, tax to pay)</small></td><td class="num"><strong><?= e(Fees::money($t['liability'])) ?></strong></td></tr>
            <tr class="group-row"><td><strong>What is left — the school fund</strong></td><td class="num"><strong><?= e(Fees::money($t['asset'] - $t['liability'])) ?></strong></td></tr>
        </tbody></table></div>
        <p class="hint" style="padding:0 1rem 1rem"><a href="<?= e(url('ledger/balance-sheet')) ?>">Balance sheet</a> · <a href="<?= e(url('ledger/accounts')) ?>">All accounts</a></p>
    </div>
    <div class="card">
        <h2>Checks</h2>
        <div class="table-wrap"><table data-nopage><tbody>
        <?php foreach ($controls as [$label, $ok, $books, $module, $href]): ?>
            <tr><td style="width:1.6rem"><?= $ok ? '<span style="color:var(--good)">✓</span>' : '<span style="color:var(--bad)">!</span>' ?></td>
                <td><?= e($label) ?><?php if (!$ok): ?><br><small>Books <?= e(number_format($books, 2)) ?> · module <?= e(number_format($module, 2)) ?> · <a href="<?= e(url($href)) ?>">open</a></small><?php endif; ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </div>
</div>
