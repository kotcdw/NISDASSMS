<?php if (!$s): ?>
<div class="page-head"><div><h1>My payslips</h1></div></div>
<?php if (Auth::role() === 'super_admin'): ?>
<div class="card"><p style="margin:0">As Super Admin you can see every payslip under <a href="<?= e(url('payroll')) ?>">Payroll</a>. Your own payslips appear here once your login is linked to a pay record in Payroll → Staff records.</p></div>
<?php else: ?>
<div class="card"><p class="muted" style="margin:0">Your login is not linked to a staff pay record yet. Ask the Bursar to link it under Payroll → Staff records.</p></div>
<?php endif; ?>
<?php else: ?>
<div class="xgrid-box" data-xgrid="my-payslips" data-xgrid-file="my-payslips">
<div class="page-head">
    <div><h1>My payslips</h1></div>
    <div class="actions"><?= export_grid_menu() ?></div>
</div>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['month' => ['Month', 'date'], 'gross' => ['Gross (GH₵)', 'num', 'num'], 'ded' => ['Deductions (GH₵)', 'num', 'num'], 'net' => ['Net pay (GH₵)', 'num', 'num'], 'status' => ['Status']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($slips as $p): $ded = (float) $p['gross'] - (float) $p['net']; ?>
    <tr>
        <td data-c="month" data-s="<?= e($p['period']) ?>"><?= e(Payroll::periodLabel($p['period'])) ?></td>
        <td data-c="gross" class="num" data-s="<?= (float) $p['gross'] ?>"><?= e(number_format((float) $p['gross'], 2)) ?></td>
        <td data-c="ded" class="num" data-s="<?= $ded ?>"><?= e(number_format($ded, 2)) ?></td>
        <td data-c="net" class="num" data-s="<?= (float) $p['net'] ?>"><strong><?= e(number_format((float) $p['net'], 2)) ?></strong></td>
        <td data-c="status"><?= badge(Payroll::STATUS[$p['status']][0], Payroll::STATUS[$p['status']][1]) ?></td>
        <td class="xg-act"><a class="btn btn-sm" href="<?= e(url('my/payslips/' . $p['id'])) ?>" target="_blank">View / print</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $slips ? ' hidden' : '' ?> data-xg-empty>No payslips yet.</p>
</div>
</div>
<?php endif; ?>
