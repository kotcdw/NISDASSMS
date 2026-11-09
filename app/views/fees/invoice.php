<?php
[$sl, $st] = Fees::STATUS[$inv['status']];
$credit = $gid ? Family::freeCredit((int) $gid) : 0.0;
$bal = Fees::balance($inv);
$open = $inv['status'] !== 'cancelled';
$newReceipt = (int) ($_GET['receipt'] ?? 0);
?>
<div class="page-head">
    <div>
        <p class="breadcrumb"><a href="<?= e(url('fees/invoices')) ?>">Bills</a> › <?= e($inv['invoice_no']) ?></p>
        <h1><?= e($inv['title']) ?> <?= badge($sl, $st) ?></h1>
        <p class="muted"><a href="<?= e(url('fees/pupil/' . $inv['pupil_id'])) ?>"><?= e(full_name($inv)) ?></a> · <?= e($inv['admission_no']) ?> · <?= e($inv['class_name'] ?? '') ?> · issued <?= e(fmt_date($inv['issue_date'])) ?><?= $inv['due_date'] ? ' · due ' . e(fmt_date($inv['due_date'])) : '' ?></p>
    </div>
    <div class="actions">
        <details class="menu-btn"><summary class="btn">Tools ▾</summary><div>
            <a href="<?= e(url('fees/invoices/' . $inv['id'] . '/print')) ?>" target="_blank" rel="noopener">Print bill</a>
            <?php if ($open && $bal > 0): ?><a href="#" data-help="link-box">Parent payment link</a><?php endif; ?>
            <?php if (can('fees.manage') && $open && $bal > 0 && in_array($inv['status'], ['unpaid', 'part'], true)): ?><a href="#" data-help="plan-box">Payment plan (instalments)</a><?php endif; ?>
            <?php if (can('fees.manage') && $open && $bal > 0): ?><form method="post" action="<?= e(url('fees/invoices/' . $inv['id'] . '/sms')) ?>"><?= csrf_field() ?><button class="menu-item" type="submit" title="<?= e($guardian ? $guardian['name'] . ' ' . $guardian['phone'] : 'No guardian') ?>">SMS bill to parent</button></form><?php endif; ?>
            <a href="<?= e(url('fees/pupil/' . $inv['pupil_id'])) ?>">Student fee statement</a>
            <?php if ($gid): ?><a href="<?= e(url('fees/family/' . $gid)) ?>">Family account<?= $credit > 0.004 ? ' (credit ' . e(number_format($credit, 2)) . ')' : '' ?></a><?php endif; ?>
            <?php if (can('fees.manage') && $open && $gid): ?><a href="#" data-help="cn-form">Credit note (reduce this bill)</a><?php endif; ?>
            <?php if (can('fees.manage') && $open): ?><a href="#" data-help="line-form">Add or remove a line</a><?php endif; ?>
            <?php if (can('fees.manage') && $open && !array_filter($payments, fn($p) => $p['status'] === 'completed')): ?><a href="#" data-help="cancel-form" class="danger">Cancel bill</a><?php endif; ?>
        </div></details>
    </div>
</div>
<?php if ($newReceipt): ?><div class="alert alert-success">Payment recorded. <a class="btn btn-sm btn-primary" href="<?= e(url('fees/receipts/' . $newReceipt)) ?>" target="_blank" rel="noopener">Print receipt</a></div><?php endif; ?>
<?php if ($inv['notes']): ?><div class="alert alert-info"><?= e($inv['notes']) ?></div><?php endif; ?>
<?php if ($open && (float) $inv['paid'] > (float) $inv['total'] + 0.004): ?><div class="alert alert-warning"><strong>Overpaid by <?= e(Fees::money((float) $inv['paid'] - (float) $inv['total'])) ?>.</strong> Keep the extra as family credit — it is used on the next bill, or can be refunded.
    <?php if (can('fees.manage') && $gid): ?><form method="post" action="<?= e(url('fees/invoices/' . $inv['id'] . '/to-credit')) ?>" class="inline"><?= csrf_field() ?><button class="btn btn-sm" type="submit">Move to family credit</button></form><?php endif; ?></div><?php endif; ?>
<?php if (can('fees.manage') && $open && $bal > 0.004 && $credit > 0.004): ?><div class="alert alert-info">This family has <strong><?= e(Fees::money($credit)) ?></strong> of credit.
    <form method="post" action="<?= e(url('fees/invoices/' . $inv['id'] . '/use-credit')) ?>" class="inline" data-confirm="Use <?= e(Fees::money(min($credit, $bal))) ?> of family credit on this bill?"><?= csrf_field() ?><button class="btn btn-sm" type="submit">Use it on this bill</button></form></div><?php endif; ?>

<?php if (can('fees.manage') && $open): ?>
<div id="cn-form" class="help-box form-center" hidden>
    <form method="post" action="<?= e(url('fees/invoices/' . $inv['id'] . '/credit-note')) ?>" class="card form">
        <?= csrf_field() ?>
        <h2>Credit note — reduce this bill</h2>
        
        <div class="grid-2">
            <label class="required">Why<select name="reason_kind"><?php foreach (Family::CN_REASONS as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?></select></label>
            <label class="required">Amount (GH₵)<input name="amount" inputmode="decimal" required placeholder="max <?= e(number_format((float) $inv['total'], 2)) ?>"></label>
        </div>
        <label class="required">Details<input name="reason" required maxlength="200" placeholder="e.g. Left on 10 Oct — feeding for 6 weeks not used"></label>
        <div class="vf-foot"><span></span><span style="display:flex;gap:.5rem"><button type="button" class="btn btn-ghost" data-help="cn-form">Close</button><button class="btn btn-primary" type="submit">Send for approval</button></span></div>
    </form>
</div>
<div id="line-form" class="help-box form-center" hidden>
    <form method="post" action="<?= e(url('fees/invoices/' . $inv['id'] . '/lines')) ?>" class="card form">
        <?= csrf_field() ?>
        <h2>Add a line</h2>
        <div class="grid-2">
            <label>Fee item<select name="fee_item_id"<?= QuickAddController::attr('fee_item') ?>><option value="0">— Other (type below) —</option><?php foreach ($items as $it): ?><option value="<?= (int) $it['id'] ?>"><?= e($it['name']) ?></option><?php endforeach; ?></select></label>
            <label>Description<input name="description" maxlength="150" placeholder="e.g. Excursion to Cape Coast"></label>
            <label class="required">Amount<input name="amount" inputmode="decimal" required></label>
            <label>Discount<input name="discount" inputmode="decimal" value="0"></label>
        </div>
        <label>Discount note<input name="discount_note" maxlength="120"></label>
        <div class="vf-foot"><span></span><span style="display:flex;gap:.5rem"><button type="button" class="btn btn-ghost" data-help="line-form">Close</button><button class="btn btn-primary" type="submit">Add line</button></span></div>
    </form>
    <?php $removable = array_filter($lines, fn($l) => (float) $l['amount'] >= 0); if ($removable): ?>
    <div class="card"><h2>Remove a line</h2>
        <div class="table-wrap"><table data-nopage><tbody><?php foreach ($removable as $l): ?>
            <tr><td><?= e($l['description']) ?></td><td class="num"><?= e(number_format((float) $l['amount'] - (float) $l['discount'], 2)) ?></td>
                <td class="xg-act"><form method="post" action="<?= e(url('fees/invoices/' . $inv['id'] . '/lines/' . $l['id'] . '/delete')) ?>" data-confirm="Remove “<?= e($l['description']) ?>” from the bill?"><?= csrf_field() ?><button class="btn btn-sm btn-danger" type="submit" aria-label="Remove <?= e($l['description']) ?>">Remove</button></form></td></tr>
        <?php endforeach; ?></tbody></table></div>
    </div>
    <?php endif; ?>
</div>
<div id="cancel-form" class="help-box form-center" hidden>
    <form method="post" action="<?= e(url('fees/invoices/' . $inv['id'] . '/cancel')) ?>" class="card form" data-confirm="Cancel this bill?">
        <?= csrf_field() ?>
        <h2>Cancel bill</h2>
        <label class="required">Reason<input name="reason" required maxlength="200" placeholder="e.g. Student withdrew before term"></label>
        <div class="vf-foot"><span></span><span style="display:flex;gap:.5rem"><button type="button" class="btn btn-ghost" data-help="cancel-form">Close</button><button class="btn btn-danger" type="submit">Cancel bill</button></span></div>
    </form>
</div>
<?php endif; ?>
<?php if ($open && $bal > 0): ?>
<div id="link-box" class="help-box form-center" hidden>
    <div class="card form">
        <h2>Parent payment link</h2>
        
        <input readonly value="<?= e(Fees::payUrl($inv)) ?>" aria-label="Parent payment link" style="width:100%;font-size:.85rem">
        <div class="vf-foot"><span></span><button type="button" class="btn btn-ghost" data-help="link-box">Close</button></div>
    </div>
</div>
<?php endif; ?>

<div class="cols">
    <div>
        <div class="card" style="padding:0">
            <div class="table-wrap"><table>
                <tr><th>Item</th><th style="text-align:right">Amount</th><th style="text-align:right">Discount</th><th style="text-align:right">Net</th></tr>
                <?php foreach ($lines as $l): ?>
                <tr>
                    <td><?= e($l['description']) ?><?= $l['discount_note'] ? '<br><small class="muted">' . e($l['discount_note']) . '</small>' : '' ?></td>
                    <td style="text-align:right"><?= e(number_format((float) $l['amount'], 2)) ?></td>
                    <td style="text-align:right"><?= (float) $l['discount'] > 0 ? '−' . e(number_format((float) $l['discount'], 2)) : '' ?></td>
                    <td style="text-align:right"><?= e(number_format((float) $l['amount'] - (float) $l['discount'], 2)) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr><td><strong>Total</strong></td><td style="text-align:right"><?= e(number_format((float) $inv['subtotal'], 2)) ?></td><td style="text-align:right"><?= (float) $inv['discount'] > 0 ? '−' . e(number_format((float) $inv['discount'], 2)) : '' ?></td><td style="text-align:right"><strong><?= e(Fees::money($inv['total'])) ?></strong></td></tr>
                <tr><td colspan="3">Paid</td><td style="text-align:right"><?= e(Fees::money($inv['paid'])) ?></td></tr>
                <tr><td colspan="3"><strong><?= $bal < 0 ? 'Credit (overpaid)' : 'Balance' ?></strong></td><td style="text-align:right"><strong style="color:<?= $bal > 0 ? 'var(--bad)' : 'var(--good)' ?>"><?= e(Fees::money(abs($bal))) ?></strong></td></tr>
                <?php $bf = $inv['status'] === 'cancelled' ? 0.0 : Fees::arrearsBefore($inv); if ($bf > 0): ?><tr><td colspan="3">Balance brought forward (earlier bills)</td><td style="text-align:right"><?= e(Fees::money($bf)) ?></td></tr>
                <tr><td colspan="3"><strong>Total to pay now</strong></td><td style="text-align:right"><strong><?= e(Fees::money(max(0, $bal) + $bf)) ?></strong></td></tr><?php endif; ?>
            </table></div>
        </div>

        <?php $manage = can('fees.manage'); require __DIR__ . '/_plan.php'; ?>
        <div class="card">
            <h2>Payments</h2>
            <?php if (!$payments): ?><p class="muted">No payments yet.</p><?php else: ?>
            <div class="table-wrap"><table>
                <tr><th>Receipt</th><th>Date</th><th>Method</th><th>Reference</th><th style="text-align:right">Amount</th><th>By</th><th></th></tr>
                <?php foreach ($payments as $p): ?>
                <tr<?= $p['status'] === 'reversed' ? ' style="opacity:.6"' : '' ?>>
                    <td><a href="<?= e(url('fees/receipts/' . $p['id'])) ?>" target="_blank" rel="noopener"><?= e($p['receipt_no']) ?></a></td>
                    <td><?= e(fmt_date($p['paid_on'])) ?></td>
                    <td><?= e(Fees::PAID_BY[$p['method']] ?? $p['method']) ?></td>
                    <td><?= e($p['reference'] ?? '') ?><?= $p['payer_name'] ? '<br><small>' . e($p['payer_name']) . '</small>' : '' ?></td>
                    <td style="text-align:right"><?= e(number_format((float) $p['amount'], 2)) ?></td>
                    <td><small><?= e($p['method'] === 'online' ? 'Online' : ($p['received_by_name'] ?? '')) ?></small></td>
                    <td>
                        <?php if ($p['status'] === 'reversed'): ?>
                            <?= badge('Reversed', 'bad') ?><br><small><?= e($p['reversal_reason']) ?> — <?= e($p['reversed_by_name'] ?? '') ?></small>
                        <?php elseif (can('fees.reverse')): ?>
                            <details class="rowmenu"><summary aria-label="Actions for <?= e($p['receipt_no']) ?>">⋯</summary><div>
                                <a href="<?= e(url('fees/receipts/' . $p['id'])) ?>" target="_blank" rel="noopener">Print receipt</a>
                                <form method="post" action="<?= e(url('fees/payments/' . $p['id'] . '/reverse')) ?>" data-confirm="Reverse receipt <?= e($p['receipt_no']) ?>? The amount will be added back to the balance."><?= csrf_field() ?><input name="reason" required maxlength="255" placeholder="Reason for reversing" aria-label="Reason for reversing" style="margin:.3rem .4rem;width:calc(100% - .8rem)"><button type="submit" class="danger">Reverse receipt</button></form>
                            </div></details>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table></div>
            <?php endif; ?>
        </div>

        <?php if ($online): ?>
        <div class="card">
            <h2>Online payment attempts</h2>
            <div class="table-wrap"><table>
                <tr><th>Reference</th><th>When</th><th>Phone</th><th style="text-align:right">Amount</th><th>Status</th><th></th></tr>
                <?php foreach ($online as $o): ?>
                <tr>
                    <td><code><?= e($o['reference']) ?></code><br><small class="muted"><?= e(PaymentGateway::GATEWAYS[$o['gateway']] ?? $o['gateway']) ?></small></td>
                    <td><?= e(date('d M H:i', strtotime($o['created_at']))) ?></td>
                    <td><?= e($o['payer_phone'] ?? '') ?></td>
                    <td style="text-align:right"><?= e(number_format((float) $o['amount'], 2)) ?></td>
                    <td><?= badge(['initiated' => 'Waiting', 'success' => 'Paid', 'failed' => 'Failed'][$o['status']], ['initiated' => 'warn', 'success' => 'good', 'failed' => 'bad'][$o['status']]) ?><?= $o['gateway_message'] ? '<br><small>' . e($o['gateway_message']) . '</small>' : '' ?></td>
                    <td><?php if ($o['status'] === 'initiated'): ?><form method="post" action="<?= e(url('fees/online/' . $o['id'] . '/check')) ?>"><?= csrf_field() ?><button class="btn btn-sm" type="submit">Check now</button></form><?php endif; ?></td>
                </tr>
                <?php endforeach; ?>
            </table></div>
        </div>
        <?php endif; ?>
    </div>

    <div>
        <?php if (can('fees.manage') && $open && $bal > 0): ?>
        <div class="card">
            <h2>Record a payment</h2>
            <form method="post" action="<?= e(url('fees/invoices/' . $inv['id'] . '/pay')) ?>" class="form">
                <?= csrf_field() ?>
                <label>Amount (GH₵)<input name="amount" inputmode="decimal" required value="<?= e(number_format($bal, 2, '.', '')) ?>"></label>
                <label>Paid by<select name="method" required>
                    <?php foreach (Fees::METHODS as $k => $v): if ($k === 'online') continue; ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
                </select></label>
                <label>Transaction ID / slip / cheque no.<input name="reference" maxlength="100" placeholder="Required for MoMo, bank and cheque"></label>
                <div class="grid-2">
                    <label>Date<input type="date" name="paid_on" value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>"></label>
                    <label>Payer phone<input name="payer_phone" maxlength="20" value="<?= e($guardian['phone'] ?? '') ?>"></label>
                </div>
                <label>Paid in by (name)<input name="payer_name" maxlength="150" value="<?= e($guardian['name'] ?? '') ?>"></label>
                <label style="font-weight:400"><input type="checkbox" name="sms" value="1" checked> SMS the receipt to the parent</label>
                <button class="btn btn-primary" type="submit">Save payment</button>
            </form>
        </div>
        <?php endif; ?>


        <?php if ($creditNotes): ?>
        <div class="card">
            <h2>Credit notes</h2>
            <?php foreach ($creditNotes as $c): [$cl, $ct] = Family::STATUS[$c['status']]; ?>
                <p style="margin:0 0 .6rem"><a href="<?= e(url('fees/credit-notes/' . $c['id'] . '/print')) ?>" target="_blank" rel="noopener"><?= e($c['cn_no']) ?></a> <?= badge($cl, $ct) ?><br><strong><?= e(Fees::money($c['amount'])) ?></strong> <small class="muted"><?= e($c['reason']) ?></small></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <p class="hint">Created by <?= e($inv['created_by_name'] ?? 'system') ?> on <?= e(fmt_date(substr($inv['created_at'], 0, 10))) ?>.</p>
    </div>
</div>
