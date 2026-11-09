<div class="table-wrap"><table data-nopage>
    <thead><tr><th style="width:5rem">Code</th><th>Account</th><th style="text-align:right">Debit (GH₵)</th><th style="text-align:right">Credit (GH₵)</th></tr></thead>
    <tbody><?php foreach ($rows as $r): ?>
        <tr><td><code><?= e($r['code']) ?></code></td><td><?= e($r['name']) ?></td>
            <td style="text-align:right"><?= $r['tb_dr'] ? e(number_format($r['tb_dr'], 2)) : '' ?></td>
            <td style="text-align:right"><?= $r['tb_cr'] ? e(number_format($r['tb_cr'], 2)) : '' ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="4" class="muted">Nothing in the books yet.</td></tr><?php endif; ?></tbody>
    <tfoot><tr><td></td><td><strong>Total</strong></td><td style="text-align:right"><strong><?= e(number_format($td, 2)) ?></strong></td><td style="text-align:right"><strong><?= e(number_format($tc, 2)) ?></strong></td></tr></tfoot>
</table></div>
