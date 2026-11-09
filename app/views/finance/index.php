<?php $total = array_sum(array_column($accounts, 'balance')); ?>
<div class="page-head">
    <div><h1>Cash book</h1></div>
    <?php require __DIR__ . '/_actions.php'; ?>
</div>
<?php if ($pendingPay && can('requisitions.pay')): ?><div class="alert alert-info"><?= $pendingPay ?> approved requisition(s) waiting to be paid. <a href="<?= e(url('requisitions', ['tab' => 'pay'])) ?>">Open them</a>.</div><?php endif; ?>

<div>
    <div class="finance-overview-content">
        <div class="card" style="padding:0">
            <h2 style="padding:1rem 1rem 0">Accounts</h2>
            <div class="table-wrap"><table>
                <tr><th>Account</th><th>Type</th><th style="text-align:right">Balance</th></tr>
                <?php foreach ($accounts as $a): ?>
                <tr>
                    <td><a href="<?= e(url('finance/cashbook', ['account' => $a['id']])) ?>"><strong><?= e($a['name']) ?></strong></a><?= $a['account_no'] ? '<br><small class="muted">' . e($a['account_no']) . '</small>' : '' ?></td>
                    <td><?= e(Finance::ACCOUNT_KINDS[$a['kind']]) ?></td>
                    <td style="text-align:right"><strong<?= $a['balance'] < 0 ? ' style="color:var(--bad)"' : '' ?>><?= e(Fees::money($a['balance'])) ?></strong></td>
                </tr>
                <?php endforeach; ?>
                <tr><td colspan="2"><strong>All accounts</strong></td><td style="text-align:right"><strong><?= e(Fees::money($total)) ?></strong></td></tr>
            </table></div>
        </div>
        <div class="card" style="padding:0">
            <h2 style="padding:1rem 1rem 0">Latest entries <a class="btn btn-sm btn-ghost" style="float:right" href="<?= e(url('finance/transactions')) ?>">See all</a></h2>
            <div class="table-wrap"><table>
                <tr><th>Date</th><th>No.</th><th>Details</th><th>Category</th><th>Account</th><th style="text-align:right">GH₵</th></tr>
                <?php foreach ($recent as $t) { require __DIR__ . '/_txn_row.php'; } ?>
                <?php if (!$recent): ?><tr><td colspan="6" class="muted">Nothing recorded yet.</td></tr><?php endif; ?>
            </table></div>
        </div>
    </div>
</div>
