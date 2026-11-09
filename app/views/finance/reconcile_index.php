<div class="xgrid-box" data-xgrid="fin-reconcile-accounts" data-xgrid-file="bank-accounts-to-check">
<div class="page-head">
    <div><h1>Check the bank statement</h1></div>
    <div class="actions"><?= export_grid_menu() ?></div>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['account' => ['Account'], 'no' => ['Account no.'], 'type' => ['Type'], 'bal' => ['Cash book balance (GH₵)', 'num', 'num'], 'upto' => ['Reconciled up to', 'date'], 'status' => ['Status'], 'next' => ['Next', 'date']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($accounts as $a): $late = !$a['last'] || $a['last']['period_end'] < date('Y-m-t', strtotime('first day of last month')); ?><tr>
        <td data-c="account"><strong><?= e($a['name']) ?></strong></td>
        <td data-c="no"><?= e($a['account_no'] ?? '') ?></td>
        <td data-c="type"><?= e(Finance::ACCOUNT_KINDS[$a['kind']] ?? $a['kind']) ?></td>
        <td data-c="bal" class="num" data-s="<?= (float) $a['balance'] ?>"><?= e(number_format((float) $a['balance'], 2)) ?></td>
        <td data-c="upto" data-s="<?= e($a['last']['period_end'] ?? '') ?>"><?= $a['last'] ? e(fmt_date($a['last']['period_end'])) : '<span class="muted">Never</span>' ?></td>
        <td data-c="status"><?= $late ? badge('Due', 'warn') : badge('Up to date', 'good') ?><?= $a['draft'] ? '<span hidden> · </span> ' . badge('Started', 'info') : '' ?></td>
        <td data-c="next" data-s="<?= e((string) $a['next']) ?>"><?= e(fmt_date($a['next'], 'M Y')) ?></td>
        <td class="xg-act"><?php if (can('finance.manage')): ?><a class="btn btn-sm btn-primary" href="<?= e(url('finance/reconcile/' . $a['id'], ['end' => $a['next']])) ?>"><?= $a['draft'] ? 'Continue' : 'Reconcile' ?></a><?php endif; ?></td>
    </tr><?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $accounts ? ' hidden' : '' ?> data-xg-empty>No bank or MoMo account.</p>
</div>
</div>

<div class="xgrid-box" data-xgrid="fin-reconcile-done" data-xgrid-file="reconciliations-done" style="margin-top:1rem">
<div class="page-head">
    <div><h2>Reconciliations done</h2></div>
    <div class="actions"><?= export_grid_menu() ?></div>
</div>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['end' => ['Month end', 'date'], 'account' => ['Account'], 'stmt' => ['Statement (GH₵)', 'num', 'num'], 'book' => ['Cash book (GH₵)', 'num', 'num'], 'status' => ['Status'], 'by' => ['By']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($history as $h): ?><tr>
        <td data-c="end" data-s="<?= e($h['period_end']) ?>"><?= e(fmt_date($h['period_end'])) ?></td>
        <td data-c="account"><?= e($h['account_name']) ?></td>
        <td data-c="stmt" class="num" data-s="<?= (float) $h['statement_balance'] ?>"><?= e(number_format((float) $h['statement_balance'], 2)) ?></td>
        <td data-c="book" class="num" data-s="<?= (float) $h['book_balance'] ?>"><?= e(number_format((float) $h['book_balance'], 2)) ?></td>
        <td data-c="status"><?= $h['status'] === 'completed' ? badge('Completed', 'good') : badge('Started', 'info') ?></td>
        <td data-c="by"><?= e($h['completed_name'] ?? '') ?></td>
        <td class="xg-act"><details class="rowmenu"><summary aria-label="Actions">⋯</summary><div>
            <?php if ($h['status'] === 'completed'): ?><a href="<?= e(url('finance/reconcile/report/' . $h['id'])) ?>" target="_blank" rel="noopener">Print statement</a><?php endif; ?>
            <?php if ($h['status'] === 'draft' && can('finance.manage')): ?><a href="<?= e(url('finance/reconcile/' . $h['account_id'], ['end' => $h['period_end']])) ?>">Continue</a><?php endif; ?>
            <?php if ($h['status'] === 'completed' && can('finance.manage')): ?><form method="post" action="<?= e(url('finance/reconcile/report/' . $h['id'] . '/reopen')) ?>" data-confirm="Reopen this reconciliation to correct it?"><?= csrf_field() ?><button type="submit" class="danger">Reopen</button></form><?php endif; ?>
        </div></details></td>
    </tr><?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $history ? ' hidden' : '' ?> data-xg-empty>None yet.</p>
</div>
</div>
