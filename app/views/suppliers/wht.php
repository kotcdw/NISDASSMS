<?php $total = array_sum(array_map(fn($p) => (float) $p['wht'], $owed)); $canPay = $owed && can('finance.manage'); ?>
<div class="xgrid-box" data-xgrid="wht-owed" data-xgrid-file="wht-to-pay">
<div class="page-head">
    <div><h1>Tax kept back (WHT)</h1></div>
    <div class="actions">
        <?php if (can('app.configure')): ?><button type="button" class="btn btn-ghost" data-help="wht-rates" aria-expanded="false">Rates</button><?php endif; ?>
        <?= export_grid_menu() ?>
        <?php if ($canPay): ?><button type="button" class="btn btn-primary" data-help="pay-gra" aria-expanded="false">Pay GRA</button><?php endif; ?>
    </div>
</div>
<?php require dirname(__DIR__) . '/purchasing/_nav.php'; ?>
<?php if (can('app.configure')): ?>
<div id="wht-rates" class="card" hidden>
    <form method="post" action="<?= e(url('suppliers/wht/rates')) ?>" class="form"><?= csrf_field() ?>
        <h2>Withholding tax rates</h2>
        <div class="grid-2">
            <label>Goods (%)<input name="goods" inputmode="decimal" value="<?= e(Suppliers::rate('goods')) ?>"></label>
            <label>Works (%)<input name="works" inputmode="decimal" value="<?= e(Suppliers::rate('works')) ?>"></label>
            <label>Services (%)<input name="services" inputmode="decimal" value="<?= e(Suppliers::rate('services')) ?>"></label>
            <label>Only on bills above (GH₵)<input name="threshold" inputmode="decimal" value="<?= e(Suppliers::threshold()) ?>"></label>
        </div>
        <div class="gate-actions"><button class="btn btn-primary" type="submit">Save rates</button><button type="button" class="btn btn-ghost" data-help="wht-rates">Cancel</button></div>
    </form>
</div>
<?php endif; ?>
<?php if ($canPay): ?>
<div id="pay-gra" class="card" hidden>
    <form method="post" action="<?= e(url('suppliers/wht/remit')) ?>" class="form" data-confirm="Record paying <?= e(Fees::money($total)) ?> to GRA?"><?= csrf_field() ?>
        <h2>Pay GRA</h2>
        <p class="fp-sum">Amount: <strong><?= e(Fees::money($total)) ?></strong></p>
        <div class="grid-2">
            <label class="required">Paid from<select name="account_id" required><?php foreach ($accounts as $a): ?><option value="<?= (int) $a['id'] ?>"><?= e($a['name']) ?></option><?php endforeach; ?></select></label>
            <label>Date<input type="date" name="remit_date" value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>"></label>
            <label>GRA receipt / ref.<input name="reference" maxlength="100"></label>
        </div>
        <div class="gate-actions"><button class="btn btn-primary" type="submit">Record payment to GRA</button><button type="button" class="btn btn-ghost" data-help="pay-gra">Cancel</button></div>
    </form>
</div>
<?php endif; ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['date' => ['Date', 'date'], 'voucher' => ['Voucher'], 'supplier' => ['Supplier'], 'tin' => ['TIN'], 'rate' => ['Rate (%)', 'num', 'num'], 'wht' => ['WHT (GH₵)', 'num', 'num']]) ?></tr></thead>
    <tbody>
    <?php foreach ($owed as $p): ?>
    <tr>
        <td data-c="date" data-s="<?= e((string) $p['pay_date']) ?>"><?= e(fmt_date($p['pay_date'], 'j M Y')) ?></td>
        <td data-c="voucher"><a href="<?= e(url('suppliers/bills/' . $p['bill_id'])) ?>"><?= e($p['pv_no']) ?></a></td>
        <td data-c="supplier"><?= e($p['supplier_name']) ?></td>
        <td data-c="tin"><?= $p['tin'] ? e($p['tin']) : '<span style="color:var(--bad)">missing</span>' ?></td>
        <td data-c="rate" class="num" data-s="<?= (float) $p['wht_rate'] ?>"><?= e(rtrim(rtrim(number_format((float) $p['wht_rate'], 2), '0'), '.')) ?></td>
        <td data-c="wht" class="num" data-s="<?= (float) $p['wht'] ?>"><?= e(number_format((float) $p['wht'], 2)) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    <?php if ($owed): ?><tfoot><tr><td colspan="5"><strong>Total to pay GRA</strong></td><td class="num"><strong><?= e(number_format($total, 2)) ?></strong></td></tr></tfoot><?php endif; ?>
</table>
<p class="xg-empty muted"<?= $owed ? ' hidden' : '' ?> data-xg-empty>Nothing to pay to GRA.</p>
</div>
</div>
<div class="xgrid-box" data-xgrid="wht-paid" data-xgrid-file="wht-paid-to-gra">
<div class="page-head">
    <div><h2>Paid to GRA</h2></div>
    <div class="actions"><?= export_grid_menu() ?></div>
</div>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['no' => ['Receipt'], 'date' => ['Date', 'date'], 'n' => ['Payments', 'num', 'num'], 'amount' => ['Amount (GH₵)', 'num', 'num']]) ?></tr></thead>
    <tbody>
    <?php foreach ($remits as $w): ?>
    <tr>
        <td data-c="no"><a href="<?= e(url('suppliers/wht/' . $w['id'] . '/print')) ?>" target="_blank" rel="noopener"><?= e($w['wr_no']) ?></a></td>
        <td data-c="date" data-s="<?= e((string) $w['remit_date']) ?>"><?= e(fmt_date($w['remit_date'], 'j M Y')) ?></td>
        <td data-c="n" class="num"><?= (int) $w['n'] ?></td>
        <td data-c="amount" class="num" data-s="<?= (float) $w['amount'] ?>"><?= e(number_format((float) $w['amount'], 2)) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $remits ? ' hidden' : '' ?> data-xg-empty>Nothing paid yet.</p>
</div>
</div>
