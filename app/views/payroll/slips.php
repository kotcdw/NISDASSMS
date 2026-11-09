<?php $label = Payroll::periodLabel($run['period']); ?>
<?php foreach ($slips as $i => $p): $docTitle = 'PAYSLIP'; $docSub = $label; $lines = Payroll::lines($p); ?>
<div class="card-page doc-page"<?= $i ? ' style="page-break-before:always"' : '' ?>>
    <?php require dirname(__DIR__) . '/fees/_doc_head.php'; ?>
    <table class="doc-meta" style="width:100%;margin:.6rem 0">
        <tr><td><strong><?= e($p['name']) ?></strong><br><?= e($p['position']) ?> · <?= e($p['staff_no']) ?></td>
            <td style="text-align:right">SSNIT no.: <?= e($p['ssnit_no'] ?: '—') ?><br>TIN: <?= e($p['tin'] ?: '—') ?><br>Paid by: <?= e(Payroll::PAY_METHOD[$p['pay_method']]) ?><?= $p['pay_method'] === 'bank' && $p['account_no'] ? ' · ' . e($p['bank_name']) . ' ••••' . e(substr((string) $p['account_no'], -4)) : '' ?><?= $p['pay_method'] === 'momo' && $p['momo_no'] ? ' · ' . e($p['momo_no']) : '' ?></td></tr>
    </table>
    <table class="lines" style="width:100%">
        <tr><th style="text-align:left">Earnings</th><th style="text-align:right">GH₵</th><th style="text-align:left;padding-left:1.5rem">Deductions</th><th style="text-align:right">GH₵</th></tr>
        <?php
        $earn = [['Basic salary', $p['basic']]];
        $ded = [['SSNIT (' . setting('ssnit_employee_rate', '5.5') . '%)', $p['ssnit_employee']], ['PAYE income tax', $p['paye']]];
        foreach ($lines as $l) {
            if ($l['kind'] === 'allowance') $earn[] = [$l['name'] . ((int) $l['taxable'] ? '' : ' (tax-free)'), $l['amount']];
            else $ded[] = [$l['name'], $l['amount']];
        }
        for ($r = 0; $r < max(count($earn), count($ded)); $r++): ?>
        <tr><td><?= e($earn[$r][0] ?? '') ?></td><td style="text-align:right"><?= isset($earn[$r]) ? e(number_format((float) $earn[$r][1], 2)) : '' ?></td>
            <td style="padding-left:1.5rem"><?= e($ded[$r][0] ?? '') ?></td><td style="text-align:right"><?= isset($ded[$r]) ? e(number_format((float) $ded[$r][1], 2)) : '' ?></td></tr>
        <?php endfor; ?>
        <tr><th style="text-align:left">Gross pay</th><th style="text-align:right"><?= e(number_format((float) $p['gross'], 2)) ?></th>
            <th style="text-align:left;padding-left:1.5rem">Total deductions</th><th style="text-align:right"><?= e(number_format((float) $p['gross'] - (float) $p['net'], 2)) ?></th></tr>
    </table>
    <?php if (!empty($p['days_paid'])): ?><p style="font-size:.9rem;margin:.6rem 0 0">Paid for <strong><?= (int) $p['days_paid'] ?> of <?= (int) $p['days_in_month'] ?> days</strong> this month (joined or left during the month). Basic salary and allowances are for those days only.</p><?php endif; ?>
    <p style="font-size:1.1rem;margin:.8rem 0"><strong>Net pay: GH₵ <?= e(number_format((float) $p['net'], 2)) ?></strong></p>
    <p style="font-size:.8rem;color:#555">Taxable pay <?= e(number_format((float) $p['taxable'], 2)) ?> · School's SSNIT contribution <?= e(number_format((float) $p['ssnit_employer'], 2)) ?> (not deducted from your pay)</p>
    <div class="rc-sign"><div><span></span>Bursar</div><div><span></span>Head Teacher</div></div>
</div>
<?php endforeach; ?>
<?php if (!$slips): ?><p>No payslips.</p><?php endif; ?>
