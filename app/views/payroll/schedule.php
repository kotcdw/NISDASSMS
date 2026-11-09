<?php $label = Payroll::periodLabel($run['period']); $docTitle = $kind === 'bank' ? 'SALARY PAYMENT SCHEDULE' : 'SSNIT & PAYE SCHEDULE'; $docSub = $label; ?>
<div class="card-page doc-page">
    <?php require dirname(__DIR__) . '/fees/_doc_head.php'; ?>
    <?php if ($kind === 'bank'): $groups = []; foreach ($rows as $r) { $groups[Payroll::PAY_METHOD[$r['pay_method']]][] = $r; } ?>
        <?php foreach ($groups as $g => $list): ?>
        <h3 style="margin:.8rem 0 .3rem"><?= e($g) ?></h3>
        <table class="lines" style="width:100%">
            <tr><th>#</th><th style="text-align:left">Name</th><th style="text-align:left"><?= $g === 'Bank' ? 'Bank / branch' : '' ?></th><th style="text-align:left"><?= $g === 'Bank' ? 'Account no.' : ($g === 'Mobile Money' ? 'MoMo no.' : 'Signature') ?></th><th style="text-align:right">Net pay (GH₵)</th></tr>
            <?php foreach ($list as $i => $r): ?>
            <tr><td><?= $i + 1 ?></td><td><?= e($r['name']) ?></td><td><?= $g === 'Bank' ? e(trim($r['bank_name'] . ' ' . $r['bank_branch'])) : '' ?></td>
                <td><?= $g === 'Bank' ? e($r['account_no']) : ($g === 'Mobile Money' ? e($r['momo_no']) : '') ?></td><td style="text-align:right"><?= e(number_format((float) $r['net'], 2)) ?></td></tr>
            <?php endforeach; ?>
            <tr><th colspan="4" style="text-align:right">Total</th><th style="text-align:right"><?= e(number_format(array_sum(array_column($list, 'net')), 2)) ?></th></tr>
        </table>
        <?php endforeach; ?>
        <p><strong>Grand total: GH₵ <?= e(number_format((float) $run['net'], 2)) ?></strong></p>
    <?php else: ?>
        <table class="lines" style="width:100%">
            <tr><th>#</th><th style="text-align:left">Name</th><th style="text-align:left">SSNIT no.</th><th style="text-align:right">Basic</th><th style="text-align:right">Staff <?= e(setting('ssnit_employee_rate', '5.5')) ?>%</th><th style="text-align:right">School <?= e(setting('ssnit_employer_rate', '13')) ?>%</th><th style="text-align:right">Total SSNIT</th><th style="text-align:right">Taxable</th><th style="text-align:right">PAYE</th></tr>
            <?php $i = 0; foreach ($rows as $r): if ((float) $r['ssnit_employee'] + (float) $r['paye'] <= 0) continue; $i++; ?>
            <tr><td><?= $i ?></td><td><?= e($r['name']) ?></td><td><?= e($r['ssnit_no']) ?></td><td style="text-align:right"><?= e(number_format((float) $r['basic'], 2)) ?></td>
                <td style="text-align:right"><?= e(number_format((float) $r['ssnit_employee'], 2)) ?></td><td style="text-align:right"><?= e(number_format((float) $r['ssnit_employer'], 2)) ?></td>
                <td style="text-align:right"><?= e(number_format((float) $r['ssnit_employee'] + (float) $r['ssnit_employer'], 2)) ?></td>
                <td style="text-align:right"><?= e(number_format((float) $r['taxable'], 2)) ?></td><td style="text-align:right"><?= e(number_format((float) $r['paye'], 2)) ?></td></tr>
            <?php endforeach; ?>
            <tr><th colspan="4" style="text-align:right">Totals</th><th style="text-align:right"><?= e(number_format((float) $run['ssnit_employee'], 2)) ?></th><th style="text-align:right"><?= e(number_format((float) $run['ssnit_employer'], 2)) ?></th>
                <th style="text-align:right"><?= e(number_format((float) $run['ssnit_employee'] + (float) $run['ssnit_employer'], 2)) ?></th><th></th><th style="text-align:right"><?= e(number_format((float) $run['paye'], 2)) ?></th></tr>
        </table>
        <p style="font-size:.8rem;color:#555">SSNIT is due by the 14th of the following month and PAYE by the 15th. Percentages shown are those set in Payroll → Tax &amp; SSNIT rates.</p>
    <?php endif; ?>
    <div class="rc-sign"><div><span></span>Prepared by (Bursar)</div><div><span></span>Approved by (Head Teacher)</div></div>
</div>
