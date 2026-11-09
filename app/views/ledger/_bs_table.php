<div class="table-wrap"><table data-nopage>
    <tbody>
    <?php foreach (['asset' => 'What the school owns', 'liability' => 'Less: what the school owes'] as $k => $label): ?>
        <tr class="group-row"><td colspan="3"><strong><?= e($label) ?></strong></td></tr>
        <?php foreach ($bs['groups'][$k] as $a): ?><tr><td style="width:5rem"><code><?= e($a['code']) ?></code></td><td><?= e($a['name']) ?></td><td style="text-align:right"><?= e(number_format($a['balance'], 2)) ?></td></tr><?php endforeach; ?>
        <tr><td></td><td><strong>Total</strong></td><td style="text-align:right"><strong><?= e(number_format($bs['totals'][$k], 2)) ?></strong></td></tr>
    <?php endforeach; ?>
        <tr><td></td><td><strong>Net worth (owns − owes)</strong></td><td style="text-align:right"><strong><?= e(number_format($bs['totals']['asset'] - $bs['totals']['liability'], 2)) ?></strong></td></tr>
        <tr class="group-row"><td colspan="3"><strong>School fund</strong></td></tr>
        <?php foreach ($bs['groups']['equity'] as $a): ?><tr><td><code><?= e($a['code']) ?></code></td><td><?= e($a['name']) ?></td><td style="text-align:right"><?= e(number_format($a['balance'], 2)) ?></td></tr><?php endforeach; ?>
        <tr><td></td><td>Surplus / (deficit) to date (income less spending)</td><td style="text-align:right"><?= e(number_format($bs['surplus'], 2)) ?></td></tr>
        <tr><td></td><td><strong>Total school fund</strong></td><td style="text-align:right"><strong><?= e(number_format($bs['totals']['equity'] + $bs['surplus'], 2)) ?></strong></td></tr>
    </tbody>
</table></div>
<p class="hint" style="padding:0 1rem 1rem"><?= $bs['balanced'] ? '✓ Net worth equals the school fund.' : 'Net worth and the school fund differ — tell the system administrator.' ?></p>
