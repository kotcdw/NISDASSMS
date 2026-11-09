<div class="card" style="padding:0">
    <div class="table-wrap"><table class="cash-table rc-scores">
        <thead><tr><th>Date</th><th>No.</th><th class="l">Details</th><th class="l">Category</th><th style="text-align:right">Money in</th><th style="text-align:right">Money out</th><th style="text-align:right">Balance</th></tr></thead>
        <tbody>
        <tr><td><?= e(fmt_date(date('Y-m-d', strtotime($from . ' -1 day')))) ?></td><td></td><td class="l"><strong>Balance brought forward</strong></td><td></td><td></td><td></td><td style="text-align:right"><strong><?= e(number_format($opening, 2)) ?></strong></td></tr>
        <?php foreach ($rows as $t): $void = $t['status'] === 'void'; ?>
        <tr<?= $void ? ' style="opacity:.5;text-decoration:line-through"' : '' ?>>
            <td><?= e(fmt_date($t['txn_date'])) ?></td><td><?= e($t['txn_no']) ?></td>
            <td class="l"><?= e($t['description']) ?><?= $t['party'] ? ' <small>(' . e($t['party']) . ')</small>' : '' ?></td>
            <td class="l"><small><?= e($t['kind'] === 'transfer' ? 'Transfer ' . ($t['in'] > 0 ? 'from ' . $t['account_name'] : 'to ' . $t['to_account_name']) : ($t['category_name'] ?? '')) ?></small></td>
            <td style="text-align:right"><?= $t['in'] ? e(number_format($t['in'], 2)) : '' ?></td>
            <td style="text-align:right"><?= $t['out'] ? e(number_format($t['out'], 2)) : '' ?></td>
            <td style="text-align:right"><?= e(number_format($t['run'], 2)) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="4" class="l"><strong>Totals · balance carried forward</strong></td><td style="text-align:right"><strong><?= e(number_format($tin, 2)) ?></strong></td><td style="text-align:right"><strong><?= e(number_format($tout, 2)) ?></strong></td><td style="text-align:right"><strong><?= e(number_format($closing, 2)) ?></strong></td></tr></tfoot>
    </table></div>
</div>
