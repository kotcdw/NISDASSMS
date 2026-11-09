<?php [$sl, $st] = Payroll::STATUS[$run['status']]; $label = Payroll::periodLabel($run['period']); $stat = (float) $run['ssnit_employee'] + (float) $run['ssnit_employer'] + (float) $run['paye']; ?>
<div class="page-head">
    <div><p class="breadcrumb"><a href="<?= e(url('payroll')) ?>">Payroll</a></p><h1>Payroll — <?= e($label) ?> <?= badge($sl, $st) ?></h1>
    <p class="muted">Prepared by <?= e($run['created_name'] ?? '—') ?><?= $run['approved_name'] ? ' · approved by ' . e($run['approved_name']) . ' on ' . e(fmt_date($run['approved_at'])) : '' ?><?= $run['paid_on'] ? ' · paid ' . e(fmt_date($run['paid_on'])) . ' from ' . e($run['account_name'] ?? '') : '' ?></p></div>
    <div class="actions">
        <details class="menu-btn"><summary class="btn">Print ▾</summary><div>
            <a href="<?= e(url('payroll/runs/' . $run['id'] . '/slips')) ?>" target="_blank">Payslips</a>
            <a href="<?= e(url('payroll/runs/' . $run['id'] . '/schedule')) ?>" target="_blank">Bank / MoMo schedule</a>
            <a href="<?= e(url('payroll/runs/' . $run['id'] . '/schedule', ['kind' => 'statutory'])) ?>" target="_blank">SSNIT &amp; PAYE schedule</a>
            <?= export_links(url('payroll/runs/' . $run['id'] . '/schedule', ['csv' => 1])) ?>
            <?php if ($run['status'] === 'approved' && can('payroll.approve')): ?><a href="#" data-help="sendback-box">Send back to draft…</a><?php endif; ?>
        </div></details>
    </div>
</div>
<p class="figs"><span>Gross pay · <?= (int) $run['staff_count'] ?> staff <strong><?= e(Fees::money($run['gross'])) ?></strong></span><span>Net pay to staff <strong><?= e(Fees::money($run['net'])) ?></strong></span><span>SSNIT (staff <?= e(Fees::money($run['ssnit_employee'])) ?> + school <?= e(Fees::money($run['ssnit_employer'])) ?>) <strong><?= e(Fees::money((float) $run['ssnit_employee'] + (float) $run['ssnit_employer'])) ?></strong></span><span>PAYE to GRA <strong><?= e(Fees::money($run['paye'])) ?></strong></span></p>
<?php if ($run['status'] === 'draft'): ?>
<?php if ($noTs = Payroll::dailyWithoutTimesheet($run['period'])): ?><div class="alert alert-warning">Paid by the day but no approved timesheet for <?= e(Payroll::periodLabel($run['period'])) ?>: <strong><?= e(implode(', ', $noTs)) ?></strong>. They get nothing until the Head approves their timesheet; then click Recalculate.</div><?php endif; ?>
<?php if ($neg = Payroll::negativeSlips((int) $run['id'])): ?><div class="alert alert-error">Take-home pay is below zero for <strong><?= e(implode(', ', $neg)) ?></strong>. Reduce their deductions and recalculate — the payroll cannot be approved like this.</div><?php endif; ?>
<div class="card">
    <h2>Next step: approval</h2>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
        <?php if (can('payroll.manage')): ?>
            <form method="post" action="<?= e(url('payroll/runs/' . $run['id'] . '/rebuild')) ?>"><?= csrf_field() ?><button class="btn" type="submit">Recalculate</button></form>
            <form method="post" action="<?= e(url('payroll/runs/' . $run['id'] . '/delete')) ?>" data-confirm="Delete this draft payroll?"><?= csrf_field() ?><button class="btn btn-ghost" type="submit">Delete draft</button></form>
        <?php endif; ?>
        <?php if (can('payroll.approve')): ?>
            <form method="post" action="<?= e(url('payroll/runs/' . $run['id'] . '/approve')) ?>" data-confirm="Approve net pay of <?= e(Fees::money($run['net'])) ?> for <?= e($label) ?>?"><?= csrf_field() ?><button class="btn btn-good" type="submit">Approve payroll</button></form>
        <?php endif; ?>
    </div>
</div>
<?php elseif ($run['status'] === 'approved'): ?>
<div class="cols">
    <?php if (can('payroll.manage')): ?>
    <form method="post" action="<?= e(url('payroll/runs/' . $run['id'] . '/pay')) ?>" class="card form" data-confirm="Record that the salaries (<?= e(Fees::money($run['net'])) ?>) have been paid?">
        <?= csrf_field() ?>
        <h2>Pay the salaries</h2>
        
        <div class="grid-2">
            <label>Paid from<select name="account_id"<?= QuickAddController::attr('fin_account') ?>><?php foreach ($accounts as $a): ?><option value="<?= (int) $a['id'] ?>"<?= selected(setting('payroll_account'), (string) $a['id']) ?>><?= e($a['name']) ?></option><?php endforeach; ?></select></label>
            <label>Date paid<input type="date" name="paid_on" value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>" required></label>
        </div>
        <label style="font-weight:400"><input type="checkbox" name="sms" value="1"> Send each staff member an SMS that their salary has been paid</label>
        <label style="font-weight:400"><input type="checkbox" name="allow_overdraw" value="1"> Allow overdraft</label>
        <button class="btn btn-primary" type="submit">Mark as paid</button>
    </form>
    <?php endif; ?>
</div>
<?php if (can('payroll.approve')): ?>
<div id="sendback-box" class="help-box form-center" hidden>
    <form method="post" action="<?= e(url('payroll/runs/' . $run['id'] . '/reopen')) ?>" class="card form">
        <?= csrf_field() ?>
        <h2>Send back to draft</h2>
        <label>Reason<input name="reason" maxlength="200" placeholder="e.g. New teacher missing"></label>
        <div class="vf-foot"><span></span><span style="display:flex;gap:.5rem"><button type="button" class="btn btn-ghost" data-help="sendback-box">Close</button><button class="btn" type="submit">Send back to draft</button></span></div>
    </form>
</div>
<?php endif; ?>
<?php else: ?>
<div class="card">
    <h2>Paid</h2>
    <p class="hint" style="margin-bottom:.6rem">Salaries recorded in the cash book<?= $txn ? ' as <a href="' . e(url('finance/transactions', ['q' => $txn['txn_no']])) . '">' . e($txn['txn_no']) . '</a>' : '' ?>.
    <?= $run['statutory_paid_on'] ? 'SSNIT and PAYE (' . e(Fees::money($stat)) . ') paid on ' . e(fmt_date($run['statutory_paid_on'])) . '.' : '' ?></p>
    <?php if (!$run['statutory_paid_on'] && $stat > 0 && can('payroll.manage')): ?>
    <button type="button" class="btn btn-primary" data-help="stat-box" aria-expanded="false">Record SSNIT &amp; PAYE payment</button>
    <form id="stat-box" hidden method="post" action="<?= e(url('payroll/runs/' . $run['id'] . '/statutory')) ?>" class="form help-box" style="max-width:560px;margin-top:.8rem">
        <?= csrf_field() ?>
        
        <div class="grid-2">
            <label>Paid from<select name="account_id"<?= QuickAddController::attr('fin_account') ?>><?php foreach ($accounts as $a): ?><option value="<?= (int) $a['id'] ?>"<?= selected(setting('payroll_account'), (string) $a['id']) ?>><?= e($a['name']) ?></option><?php endforeach; ?></select></label>
            <label>Date paid<input type="date" name="paid_on" value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>" required></label>
        </div>
        <label style="font-weight:400"><input type="checkbox" name="allow_overdraw" value="1"> Allow overdraft</label>
        <button class="btn" type="submit">Record SSNIT &amp; PAYE payment</button>
    </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="card" style="padding:0"><div class="table-wrap"><table>
    <tr><th>Staff <small class="muted">(click a name for the payslip)</small></th><th style="text-align:right">Basic</th><th style="text-align:right">Allowances</th><th style="text-align:right">Gross</th><th style="text-align:right">SSNIT</th><th style="text-align:right">PAYE</th><th style="text-align:right">Other ded.</th><th style="text-align:right">Take-home</th></tr>
    <?php foreach ($slips as $p): ?>
    <tr>
        <td><a href="<?= e(url('payroll/runs/' . $run['id'] . '/slips', ['staff' => $p['staff_id']])) ?>" target="_blank"><strong><?= e($p['name']) ?></strong></a><?= !empty($p['days_paid']) ? ' ' . badge((int) $p['days_paid'] . ' of ' . (int) $p['days_in_month'] . ' days', 'info') : '' ?><br><small class="muted"><?= e($p['staff_no']) ?> · <?= e($p['position']) ?> · <?= e(Payroll::PAY_METHOD[$p['pay_method']]) ?></small></td>
        <td style="text-align:right"><?= e(number_format((float) $p['basic'], 2)) ?></td>
        <td style="text-align:right"><?= e(number_format((float) $p['allowances'], 2)) ?></td>
        <td style="text-align:right"><?= e(number_format((float) $p['gross'], 2)) ?></td>
        <td style="text-align:right"><?= e(number_format((float) $p['ssnit_employee'], 2)) ?></td>
        <td style="text-align:right"><?= e(number_format((float) $p['paye'], 2)) ?></td>
        <td style="text-align:right"><?= e(number_format((float) $p['deductions'], 2)) ?></td>
        <td style="text-align:right"><strong><?= e(number_format((float) $p['net'], 2)) ?></strong></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$slips): ?><tr><td colspan="8" class="muted">No staff were on the payroll for this month.</td></tr><?php endif; ?>
</table></div></div>
