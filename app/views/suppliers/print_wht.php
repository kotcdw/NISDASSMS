<?php $docTitle = 'WITHHOLDING TAX CERTIFICATE'; $docSub = $p['pv_no']; $docType = 'wht'; $docHalf = true; ob_start(); ?>
    <?php require dirname(__DIR__) . '/fees/_doc_head.php'; ?>
    <p>This is to certify that the tax below was withheld from a payment to the supplier named, under the Income Tax Act, 2015 (Act 896) as amended, and is payable to the Ghana Revenue Authority by the school.</p>
    <table class="rc-info">
        <tr><th>Withholding agent</th><td colspan="3"><?= e(school_name()) ?><?= setting('school_tin') ? ' · TIN ' . e(setting('school_tin')) : '' ?></td></tr>
        <tr><th>Supplier</th><td colspan="3"><?= e($b['supplier_name']) ?><?= $b['supplier_address'] ? ', ' . e($b['supplier_address']) : '' ?></td></tr>
        <tr><th>Supplier TIN</th><td><?= e($b['tin'] ?? '—') ?></td><th>Date of payment</th><td><?= e(fmt_date($p['pay_date'])) ?></td></tr>
        <tr><th>Nature of supply</th><td><?= e(Suppliers::TYPES[$b['supply_type']]) ?></td><th>Rate</th><td><?= e(rtrim(rtrim(number_format((float) $b['wht_rate'], 2), '0'), '.')) ?>%</td></tr>
        <tr><th>Gross amount</th><td><?= e(Fees::money($p['gross'])) ?></td><th>Tax withheld</th><td><strong><?= e(Fees::money($p['wht'])) ?></strong></td></tr>
        <tr><th>Tax withheld in words</th><td colspan="3"><em><?= e(money_words($p['wht'])) ?></em></td></tr>
        <tr><th>Invoice</th><td colspan="3"><?= e($b['supplier_ref'] ?: $b['bill_no']) ?> — <?= e($b['description']) ?></td></tr>
    </table>
    <div class="rc-sign"><div><span></span>Bursar / Accountant</div><div><span></span>Head Teacher</div><div><span></span>School stamp</div></div>
    <p class="rc-key">Printed <?= e(date('d M Y H:i')) ?>.</p>
<?php $docBody = ob_get_clean(); require dirname(__DIR__) . '/fees/_copies.php'; ?>
