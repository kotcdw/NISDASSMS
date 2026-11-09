<?php $ti = array_sum(array_column($income, 'amount')); $te = array_sum(array_column($expense, 'amount')); $net = $ti - $te; ?>
<table class="rc-scores">
    <thead><tr><th class="l">Income</th><th style="text-align:right">GH₵</th></tr></thead>
    <tbody>
    <?php foreach ($income as $c): ?><tr><td class="l"><?= e($c['name']) ?></td><td style="text-align:right"><?= e(number_format($c['amount'], 2)) ?></td></tr><?php endforeach; ?>
    <?php if (!$income): ?><tr><td class="l" colspan="2">No income in this period.</td></tr><?php endif; ?>
    </tbody>
    <tfoot><tr><td class="l"><strong>Total income</strong></td><td style="text-align:right"><strong><?= e(number_format($ti, 2)) ?></strong></td></tr></tfoot>
</table>
<table class="rc-scores">
    <thead><tr><th class="l">Expenditure</th><th style="text-align:right">GH₵</th></tr></thead>
    <tbody>
    <?php foreach ($expense as $c): ?><tr><td class="l"><?= e($c['name']) ?></td><td style="text-align:right"><?= e(number_format($c['amount'], 2)) ?></td></tr><?php endforeach; ?>
    <?php if (!$expense): ?><tr><td class="l" colspan="2">No spending in this period.</td></tr><?php endif; ?>
    </tbody>
    <tfoot><tr><td class="l"><strong>Total expenditure</strong></td><td style="text-align:right"><strong><?= e(number_format($te, 2)) ?></strong></td></tr></tfoot>
</table>
<table class="rc-info"><tr><th><?= $net >= 0 ? 'Surplus (income more than spending)' : 'Deficit (spending more than income)' ?></th><td style="text-align:right"><strong style="font-size:1.1em"><?= e(Fees::money(abs($net))) ?></strong></td></tr></table>
