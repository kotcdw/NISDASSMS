<?php $tin = array_sum(array_column($rows, 'in')); $tout = array_sum(array_column($rows, 'out')); $closing = $opening + $tin - $tout; ?>
<div class="xgrid-box" data-xgrid="fin-cashbook" data-xgrid-file="cash-book">
<div class="page-head">
    <div><h1>Cash book</h1></div>
    <div class="actions">
        <form method="get" action="<?= e(url('finance/cashbook')) ?>" class="list-pick" data-autosubmit>
            <select name="account" aria-label="Account"><?php foreach ($accounts as $a): ?><option value="<?= (int) $a['id'] ?>"<?= selected($acct['id'] ?? 0, $a['id']) ?>><?= e($a['name']) ?></option><?php endforeach; ?></select>
        </form>
        <?php if ($acct): ?><a class="btn btn-ghost" href="<?= e(url('finance/cashbook', ['account' => $acct['id'], 'from' => $from, 'to' => $to, 'print' => 1])) ?>" target="_blank" rel="noopener">Print</a><?php endif; ?>
        <?= export_grid_menu() ?>
    </div>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['date' => ['Date', 'date'], 'no' => ['No.'], 'details' => ['Details'], 'party' => ['Paid to / from'], 'category' => ['Category'], 'in' => ['Money in', 'num', 'num'], 'out' => ['Money out', 'num', 'num'], 'run' => ['Balance', 'num', 'num']]) ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $t): $void = $t['status'] === 'void'; ?>
    <tr<?= $void ? ' style="opacity:.5;text-decoration:line-through"' : '' ?>>
        <td data-c="date" data-s="<?= e($t['txn_date']) ?>"><?= e(fmt_date($t['txn_date'])) ?></td>
        <td data-c="no"><a href="<?= e(url('finance/transactions/' . $t['id'])) ?>"><?= e($t['txn_no']) ?></a></td>
        <td data-c="details" class="audit-details"><?= e($t['description']) ?></td>
        <td data-c="party"><?= e($t['party'] ?? '') ?></td>
        <td data-c="category"><?= e($t['kind'] === 'transfer' ? 'Transfer ' . ($t['in'] > 0 ? 'from ' . $t['account_name'] : 'to ' . $t['to_account_name']) : ($t['category_name'] ?? '')) ?></td>
        <td data-c="in" class="num" data-s="<?= $t['in'] ? (float) $t['in'] : '' ?>"><?= $t['in'] ? e(number_format($t['in'], 2)) : '' ?></td>
        <td data-c="out" class="num" data-s="<?= $t['out'] ? (float) $t['out'] : '' ?>"><?= $t['out'] ? e(number_format($t['out'], 2)) : '' ?></td>
        <td data-c="run" class="num" data-s="<?= (float) $t['run'] ?>"><?= e(number_format($t['run'], 2)) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr><td colspan="5"><strong>Balance brought forward</strong> <span class="muted">(<?= e(fmt_date(date('Y-m-d', strtotime($from . ' -1 day')))) ?>)</span></td><td></td><td></td><td class="num"><strong><?= e(number_format($opening, 2)) ?></strong></td></tr>
        <tr><td colspan="5"><strong>Totals · balance carried forward</strong></td><td class="num"><strong><?= e(number_format($tin, 2)) ?></strong></td><td class="num"><strong><?= e(number_format($tout, 2)) ?></strong></td><td class="num"><strong><?= e(number_format($closing, 2)) ?></strong></td></tr>
    </tfoot>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No entries in this account for these dates.</p>
</div>
</div>
