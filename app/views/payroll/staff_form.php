<?php $v = fn($k, $d = '') => old($k, $s[$k] ?? $d); ?>
<div class="page-head"><div><p class="breadcrumb"><a href="<?= e(url('payroll/staff')) ?>">Staff records</a></p><h1><?= e($title) ?></h1><?= $s ? '<p class="muted">' . e($s['staff_no']) . ' · ' . e($s['position']) . '</p>' : '' ?></div>
<?php if ($s): ?><div class="actions"><a class="btn" href="<?= e(url('payroll/staff')) ?>">Back to list</a></div><?php endif; ?></div>
<div class="cols">
<form method="post" action="<?= e(url($s ? 'payroll/staff/' . $s['id'] : 'payroll/staff')) ?>" class="card form">
    <?= csrf_field() ?>
    <fieldset><legend>Person</legend>
        <div class="grid-2">
            <label class="required">Full name<input name="name" required maxlength="150" value="<?= e($v('name')) ?>"><?= field_error('name') ?></label>
            <label>Phone<input name="phone" value="<?= e($v('phone')) ?>" placeholder="0244123456"><?= field_error('phone') ?></label>
            <label>Gender<select name="gender"><option value="">—</option><?php foreach (Payroll::GENDER as $k => $l): ?><option value="<?= e($k) ?>"<?= selected((string) $v('gender'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select><?= field_error('gender') ?></label>
            <label>Ghana Card no.<input name="ghana_card_no" value="<?= e($v('ghana_card_no')) ?>" placeholder="GHA-123456789-0"><?= field_error('ghana_card_no') ?></label>
            <label class="required">Position<input name="position" required maxlength="80" value="<?= e($v('position')) ?>" placeholder="e.g. Class teacher, Cook, Driver"><?= field_error('position') ?></label>
            <label>Category<select name="category"><?php foreach (Payroll::CATEGORY as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($v('category', 'teaching'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
            <label>Employment<select name="employment"><?php foreach (Payroll::EMPLOYMENT as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($v('employment', 'permanent'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
            <label>System login (optional)<select name="user_id"<?= QuickAddController::attr('staff') ?>><option value="">— none —</option><?php foreach ($users as $u): ?><option value="<?= (int) $u['id'] ?>"<?= selected((string) $v('user_id'), (string) $u['id']) ?>><?= e($u['name']) ?> (<?= e(Auth::roleLabel($u['role'])) ?>)</option><?php endforeach; ?></select><?= field_error('user_id') ?></label>
            <label>Started<input type="date" name="start_date" value="<?= e($v('start_date')) ?>"></label>
            <label>Date left<input type="date" name="end_date" value="<?= e($v('end_date')) ?>"><?= field_error('end_date') ?></label>
            <?php if (Registers::ready()): ?>
            <label>Contract ends<input type="date" name="contract_end" value="<?= e($v('contract_end')) ?>"><small class="muted">Reminder 60 days before</small><?= field_error('contract_end') ?></label>
            <label>Probation ends<input type="date" name="probation_end" value="<?= e($v('probation_end')) ?>"><?= field_error('probation_end') ?></label>
            <?php endif; ?>
        </div>
        
    </fieldset>
    <fieldset><legend>Qualification &amp; NTC licence</legend>
        <?php if ($s && ($nb = Payroll::ntcBadge($s))): ?><p style="margin:0 0 .5rem"><?= $nb ?></p><?php endif; ?>
        <div class="grid-2">
            <label>Highest qualification<select name="qualification"><option value="">—</option><?php foreach (Payroll::QUALIFICATION as $k => [$l, $trained]): ?><option value="<?= e($k) ?>"<?= selected((string) $v('qualification'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select><?= field_error('qualification') ?></label>
            <span></span>
            <label>NTC licence number<input name="ntc_licence_no" maxlength="30" value="<?= e($v('ntc_licence_no')) ?>"><?= field_error('ntc_licence_no') ?></label>
            <label>Licence expires<input type="date" name="ntc_expiry" value="<?= e($v('ntc_expiry')) ?>"><?= field_error('ntc_expiry') ?></label>
        </div>
        
    </fieldset>
    <fieldset><legend>Pay</legend>
        <div class="grid-2">
            <label class="required">Monthly basic salary (GH₵)<input name="basic_salary" inputmode="decimal" required value="<?= e($v('basic_salary', '0')) ?>"><?= field_error('basic_salary') ?></label>
            <?php if (Payroll::hasDailyPay()): ?>
            <label>Pay basis<select name="pay_basis"><option value="monthly"<?= selected($v('pay_basis', 'monthly'), 'monthly') ?>>Monthly salary</option><option value="daily"<?= selected($v('pay_basis', 'monthly'), 'daily') ?>>By the day (from the approved timesheet)</option></select><?= field_error('pay_basis') ?></label>
            <label>Daily rate (GH₵)<input name="daily_rate" inputmode="decimal" value="<?= e($v('daily_rate')) ?>" placeholder="e.g. 45"><?= field_error('daily_rate') ?></label>
            <?php endif; ?>
            <label>Paid by<select name="pay_method"><?php foreach (Payroll::PAY_METHOD as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($v('pay_method', 'bank'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select><?= field_error('pay_method') ?></label>
            <label>Bank<input name="bank_name" value="<?= e($v('bank_name')) ?>" placeholder="e.g. GCB, Ecobank, ADB"></label>
            <label>Branch<input name="bank_branch" value="<?= e($v('bank_branch')) ?>"></label>
            <label>Account number<input name="account_no" value="<?= e($v('account_no')) ?>"><?= field_error('account_no') ?></label>
            <label>MoMo number<input name="momo_no" value="<?= e($v('momo_no')) ?>"><?= field_error('momo_no') ?></label>
            <label>SSNIT number<input name="ssnit_no" value="<?= e($v('ssnit_no')) ?>"></label>
            <label>TIN<input name="tin" value="<?= e($v('tin')) ?>"></label>
        </div>
        <label style="font-weight:400"><input type="checkbox" name="pays_ssnit" value="1"<?= checked((bool) $v('pays_ssnit', 1)) ?>> Pays SSNIT</label>
        <label style="font-weight:400"><input type="checkbox" name="pays_paye" value="1"<?= checked((bool) $v('pays_paye', 1)) ?>> PAYE income tax is deducted</label>
        <?php if ($s): ?><label style="font-weight:400"><input type="checkbox" name="is_active" value="1"<?= checked((int) $s['is_active'] === 1) ?>> Still on the payroll</label><?php endif; ?>
        <label>Notes<input name="notes" maxlength="255" value="<?= e($v('notes')) ?>"></label>
    </fieldset>
    <button class="btn btn-primary" type="submit">Save</button>
</form>
<div>
<?php if ($s): ?>
    <div class="card" style="padding:0">
        <h2 style="padding:1rem 1rem 0">Allowances &amp; deductions (every month)</h2>
        <div class="table-wrap"><table>
            <?php foreach ($items as $it): ?>
            <tr><td><?= e($it['name']) ?><br><small class="muted"><?= $it['kind'] === 'allowance' ? ((int) $it['taxable'] ? 'Allowance (taxed)' : 'Allowance (tax-free)') : 'Deduction' ?></small></td>
                <td style="text-align:right"><?= $it['kind'] === 'deduction' ? '−' : '' ?><?= e(Fees::money($it['amount'])) ?></td>
                <td><form method="post" action="<?= e(url('payroll/staff/' . $s['id'] . '/items/' . $it['id'] . '/delete')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" type="submit" data-confirm-click="Remove this item?">Remove</button></form></td></tr>
            <?php endforeach; ?>
            <?php if (!$items): ?><tr><td class="muted">None.</td></tr><?php endif; ?>
        </table></div>
        <form method="post" action="<?= e(url('payroll/staff/' . $s['id'] . '/items')) ?>" class="form" style="padding:1rem">
            <?= csrf_field() ?>
            <div class="grid-2">
                <label>Type<select name="kind"><option value="allowance">Allowance</option><option value="deduction">Deduction</option></select></label>
                <label>Amount (GH₵)<input name="amount" inputmode="decimal" required></label>
            </div>
            <label>Name<input name="name" required maxlength="80" placeholder="e.g. Transport allowance, Staff welfare, Loan repayment"></label>
            <label style="font-weight:400"><input type="checkbox" name="taxable" value="1" checked> Allowance is taxable (PAYE)</label>
            <button class="btn" type="submit">Add</button>
        </form>
    </div>
    <div class="card">
        <h2>This month's pay at a glance</h2>
        <table>
            <tr><td>Basic salary</td><td style="text-align:right"><?= e(Fees::money($preview['basic'])) ?></td></tr>
            <tr><td>Allowances</td><td style="text-align:right"><?= e(Fees::money($preview['allowances'])) ?></td></tr>
            <tr><td><strong>Gross</strong></td><td style="text-align:right"><strong><?= e(Fees::money($preview['gross'])) ?></strong></td></tr>
            <tr><td>SSNIT (staff share)</td><td style="text-align:right">−<?= e(Fees::money($preview['ssnit_employee'])) ?></td></tr>
            <tr><td>PAYE on <?= e(Fees::money($preview['taxable'])) ?></td><td style="text-align:right">−<?= e(Fees::money($preview['paye'])) ?></td></tr>
            <tr><td>Other deductions</td><td style="text-align:right">−<?= e(Fees::money($preview['deductions'])) ?></td></tr>
            <tr><td><strong>Net pay</strong></td><td style="text-align:right"><strong><?= e(Fees::money($preview['net'])) ?></strong></td></tr>
            <tr><td class="muted">School's SSNIT share (extra cost)</td><td style="text-align:right" class="muted"><?= e(Fees::money($preview['ssnit_employer'])) ?></td></tr>
        </table>
    </div>
<?php else: ?>
    <div class="card"></div>
<?php endif; ?>
</div>
</div>
