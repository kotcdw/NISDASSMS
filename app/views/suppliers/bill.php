<?php
[$sl, $st] = Suppliers::STATUS[$b['status']];
$bal = Suppliers::balance($b);
$whtLeft = round((float) $b['wht_amount'] - array_sum(array_map(fn($p) => $p['status'] === 'posted' ? (float) $p['wht'] : 0, $payments)), 2);
$newPv = (int) ($_GET['pv'] ?? 0);
$match = !empty($b['po_id']) ? Purchasing::match((int) $b['id']) : null;
$canPayNow = $match ? max(0.0, min($bal, round($match['payable'] - (float) $b['paid_gross'], 2))) : $bal;
$waitApproval = Suppliers::needsApproval($b) && empty($b['pay_approved_at']) && $bal > 0;
?>
<div class="page-head">
    <div><p class="breadcrumb"><a href="<?= e(url('suppliers')) ?>">Bills to pay</a> › <?= e($b['bill_no']) ?></p>
        <h1><?= e($b['supplier_name']) ?> <?= badge($sl, $st) ?></h1>
        <p class="muted"><?= e($b['description']) ?> · <?= e($b['category_name'] ?? '') ?> · dated <?= e(fmt_date($b['bill_date'])) ?><?= $b['due_date'] ? ' · due ' . e(fmt_date($b['due_date'])) : '' ?></p></div>
    <div class="actions"><details class="menu-btn"><summary class="btn">Tools ▾</summary><div>
        <?php if ($b['attachment']): ?><a href="<?= e(url('suppliers/bills/' . $b['id'] . '/file')) ?>" target="_blank" rel="noopener">Supplier's invoice</a><?php endif; ?>
        <?php if ($b['requisition_id']): ?><a href="<?= e(url('requisitions/' . $b['requisition_id'])) ?>">Requisition <?= e($b['req_no']) ?></a><?php endif; ?>
        <?php if (!empty($b['po_id'])): ?><a href="<?= e(url('purchasing/' . $b['po_id'])) ?>">Purchase order <?= e($b['po_no'] ?? '') ?></a><?php endif; ?>
        <a href="<?= e(url('suppliers/list', ['edit' => $b['supplier_id']])) ?>">Supplier details</a>
        <?php if (can('finance.manage') && (float) $b['paid_gross'] <= 0 && $b['status'] !== 'cancelled'): ?><a href="#" data-help="cancel-bill" class="danger">Cancel bill</a><?php endif; ?>
    </div></details></div>
</div>
<?php require dirname(__DIR__) . '/purchasing/_nav.php'; ?>
<?php if ($newPv): ?><div class="alert alert-success">Payment saved. <a class="btn btn-sm" href="<?= e(url('suppliers/payments/' . $newPv . '/voucher')) ?>" target="_blank" rel="noopener">Print payment voucher</a><?php if ($whtLeft >= 0 && db()->value('SELECT wht FROM supplier_payments WHERE id = ?', [$newPv]) > 0): ?> <a class="btn btn-sm" href="<?= e(url('suppliers/payments/' . $newPv . '/voucher', ['doc' => 'wht'])) ?>" target="_blank" rel="noopener">Print WHT certificate</a><?php endif; ?></div><?php endif; ?>
<?php if ($b['status'] === 'cancelled'): ?><div class="alert alert-info">Cancelled: <?= e($b['cancel_reason']) ?></div><?php endif; ?>
<div id="cancel-bill" class="help-box form-center" hidden>
    <form method="post" action="<?= e(url('suppliers/bills/' . $b['id'] . '/cancel')) ?>" class="card form" data-confirm="Cancel this bill?"><?= csrf_field() ?>
        <h2>Cancel bill</h2><label class="required">Reason<input name="reason" required maxlength="255"></label>
        <div class="vf-foot"><span></span><span style="display:flex;gap:.5rem"><button type="button" class="btn btn-ghost" data-help="cancel-bill">Close</button><button class="btn btn-danger" type="submit">Cancel bill</button></span></div>
    </form>
</div>
<div class="cols">
    <div>
        <div class="card" style="padding:0"><div class="table-wrap"><table>
            <tr><th>Bill</th><td><?= e($b['bill_no']) ?><?= $b['supplier_ref'] ? ' · supplier\'s invoice ' . e($b['supplier_ref']) : '' ?></td></tr>
            <tr><th>Supplier</th><td><?= e($b['supplier_name']) ?><?= $b['tin'] ? ' · TIN ' . e($b['tin']) : '' ?><?= $b['pay_details'] ? '<br><small class="muted">' . e($b['pay_details']) . '</small>' : '' ?></td></tr>
            <tr><th>Amount</th><td><strong><?= e(Fees::money($b['amount'])) ?></strong></td></tr>
            <tr><th>Withholding tax</th><td><?= (float) $b['wht_amount'] > 0 ? e(Fees::money($b['wht_amount'])) . ' (' . e(rtrim(rtrim(number_format((float) $b['wht_rate'], 2), '0'), '.')) . '% — ' . e(Suppliers::TYPES[$b['supply_type']]) . ')' : 'None' ?></td></tr>
            <tr><th>Paid so far</th><td><?= e(Fees::money($b['paid_gross'])) ?></td></tr>
            <tr><th>Balance</th><td><strong style="color:<?= $bal > 0 ? 'var(--bad)' : 'var(--good)' ?>"><?= e(Fees::money($bal)) ?></strong></td></tr>
        </table></div></div>
        <?php if ($match): ?>
        <div class="card" style="padding:0">
            <div style="padding:1rem 1rem 0"><h2>Check against the order and the goods received</h2></div>
            <div class="table-wrap"><table data-nopage>
                <thead><tr><th>Item</th><th class="num">Ordered</th><th class="num">Received</th><th class="num">Invoiced</th><th class="num">Order price</th><th class="num">Invoice price</th><th>Check</th></tr></thead>
                <tbody><?php foreach ($match['lines'] as $l): $q = fn($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.'); ?>
                <tr><td><?= e($l['description']) ?></td><td class="num"><?= e($q($l['ordered'])) ?></td>
                    <td class="num"<?= $l['received'] - $l['earlier'] + 0.004 < (float) $l['qty'] ? ' style="color:var(--bad);font-weight:700"' : '' ?>><?= e($q($l['received'])) ?></td>
                    <td class="num"><?= e($q($l['qty'])) ?></td><td class="num"><?= e(number_format($l['po_price'], 2)) ?></td>
                    <td class="num"<?= (float) $l['unit_price'] > $l['po_price'] + 0.004 ? ' style="color:var(--bad);font-weight:700"' : '' ?>><?= e(number_format((float) $l['unit_price'], 2)) ?></td>
                    <td><?= $l['issues'] ? '<span style="color:var(--bad);font-weight:600">' . e(implode(', ', $l['issues'])) . '</span>' : '<span style="color:var(--good);font-weight:600">✓ match</span>' ?></td></tr>
                <?php endforeach; ?></tbody>
            </table></div>
            <?php if (!$match['ok']): ?><div class="alert alert-error" style="margin:1rem"><strong>Not ready to pay in full.</strong> <?= e(implode('; ', $match['problems'])) ?>. <?= $canPayNow > 0 ? 'You can pay up to ' . e(Fees::money($canPayNow)) . ' for what was received, or wait for the rest.' : 'Wait until the goods are received.' ?></div>
            <?php else: ?><p class="hint" style="padding:0 1rem 1rem">✓ Ordered, received and invoiced agree.</p><?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="card">
            <h2>Payments</h2>
            <?php if (!$payments): ?><p class="muted">Nothing paid yet.</p><?php else: ?>
            <div class="table-wrap"><table>
                <thead><tr><th>Voucher</th><th>Date</th><th>From</th><th class="num">Settled</th><th class="num">WHT kept</th><th class="num">Paid out</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($payments as $p): $void = $p['status'] === 'void'; ?><tr<?= $void ? ' style="opacity:.55;text-decoration:line-through"' : '' ?>>
                    <td><?= e($p['pv_no']) ?><?= $p['reference'] ? '<br><small class="muted">' . e($p['reference']) . '</small>' : '' ?></td>
                    <td><?= e(fmt_date($p['pay_date'], 'j M Y')) ?></td><td><small><?= e($p['account_name']) ?></small></td>
                    <td class="num"><?= e(number_format((float) $p['gross'], 2)) ?></td>
                    <td class="num"><?= (float) $p['wht'] > 0 ? e(number_format((float) $p['wht'], 2)) . ($p['wr_no'] ? '<br><small class="muted">paid ' . e($p['wr_no']) . '</small>' : '') : '—' ?></td>
                    <td class="num"><?= e(number_format((float) $p['net'], 2)) ?></td>
                    <td class="xg-act"><details class="rowmenu"><summary aria-label="Actions">⋯</summary><div>
                        <a href="<?= e(url('suppliers/payments/' . $p['id'] . '/voucher')) ?>" target="_blank" rel="noopener">Payment voucher</a>
                        <?php if ((float) $p['wht'] > 0): ?><a href="<?= e(url('suppliers/payments/' . $p['id'] . '/voucher', ['doc' => 'wht'])) ?>" target="_blank" rel="noopener">WHT certificate</a><?php endif; ?>
                        <?php if (!$void && !$p['wht_remit_id'] && can('finance.void')): ?><form method="post" action="<?= e(url('suppliers/payments/' . $p['id'] . '/void')) ?>" data-confirm="Cancel payment <?= e($p['pv_no']) ?>? Its cash book entry is voided."><?= csrf_field() ?><input name="reason" required maxlength="255" placeholder="Reason" style="margin:.3rem .4rem;width:calc(100% - .8rem)"><button type="submit" class="danger">Cancel payment</button></form><?php endif; ?>
                    </div></details></td>
                </tr><?php endforeach; ?>
                </tbody>
            </table></div>
            <?php endif; ?>
        </div>
    </div>
    <div>
        <?php if (Suppliers::needsApproval($b)): ?>
        <div class="card"<?= $waitApproval ? ' style="border-color:var(--warn)"' : '' ?>>
            <h2>Approval to pay</h2>
            <?php if (!empty($b['pay_approved_at'])): ?><p style="margin:0">✓ Approved for payment by <?= e($b['approved_name'] ?? '—') ?> on <?= e(fmt_date($b['pay_approved_at'], 'd M Y')) ?>.</p>
            <?php else: ?>
                
                <?php if ($bal > 0 && can('purchasing.approve')): ?><form method="post" action="<?= e(url('suppliers/bills/' . $b['id'] . '/approve')) ?>" data-confirm="Approve <?= e($b['bill_no']) ?> (<?= e(Fees::money($b['amount'])) ?>) for payment?"><?= csrf_field() ?><button class="btn btn-primary" type="submit">Approve for payment</button></form><?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if ($bal > 0 && can('finance.manage') && !$waitApproval && $canPayNow > 0): ?>
        <form method="post" action="<?= e(url('suppliers/bills/' . $b['id'] . '/pay')) ?>" class="card form" data-confirm="Record this payment to <?= e($b['supplier_name']) ?>?" data-sp-pay data-amount="<?= e($b['amount']) ?>" data-wht="<?= e($b['wht_amount']) ?>" data-left="<?= e($bal) ?>" data-wht-left="<?= e($whtLeft) ?>">
            <?= csrf_field() ?>
            <h2>Pay this bill</h2>
            <label class="required">Amount to settle (GH₵)<input name="amount" inputmode="decimal" required value="<?= e(number_format($canPayNow, 2, '.', '')) ?>" data-sp-gross></label>
            <p class="fp-sum">WHT kept for GRA: <strong data-sp-wht>0.00</strong><br>Pay the supplier: <strong data-sp-net>0.00</strong></p>
            <label class="required">Paid from<select name="account_id" required><?php foreach ($accounts as $a): ?><option value="<?= (int) $a['id'] ?>"><?= e($a['name']) ?> (<?= e(number_format(Finance::balance((int) $a['id']), 2)) ?>)</option><?php endforeach; ?></select></label>
            <div class="grid-2">
                <label>Date<input type="date" name="pay_date" value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>"></label>
                <label>Cheque / MoMo / transfer ref.<input name="reference" maxlength="100"></label>
            </div>
            <button class="btn btn-primary" type="submit">Record payment</button>
        </form>
        <?php endif; ?>
        <?php if ($b['status'] !== 'cancelled' && can('assets.manage') && db()->value("SELECT 1 FROM fin_categories WHERE id = ? AND code = 'ASSET'", [(int) $b['category_id']])): ?>
        <div class="card"><h2>Asset register</h2>
            <a class="btn" href="<?= e(url('fixed-assets/new', ['bill' => $b['id']])) ?>">+ Add to the asset register</a></div>
        <?php endif; ?>
        <p class="hint">Entered by <?= e($b['created_name'] ?? '') ?> on <?= e(fmt_date(substr($b['created_at'], 0, 10))) ?>.</p>
    </div>
</div>
