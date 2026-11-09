<?php
$me = Auth::id();
$mine = (int) $r['requested_by'] === $me;
$level = $r['status'] === 'submitted' ? 1 : ($r['status'] === 'approved_l1' ? 2 : 0);
$canDecide = $level && !$mine && can($level === 1 ? 'requisitions.approve' : 'requisitions.approve2') && !($level === 2 && (int) $r['approved_by'] === $me);
$sbill = !empty($r['supplier_bill_id']) ? db()->one('SELECT id, bill_no, status FROM supplier_bills WHERE id = ?', [$r['supplier_bill_id']]) : null;
$canPay = $r['status'] === 'approved' && can('requisitions.pay') && !$mine && !$sbill && empty($po);
$canPo = $r['status'] === 'approved' && !$sbill && empty($po) && can('purchasing.order') && Purchasing::ready();
$canRetire = $r['status'] === 'paid' && can('requisitions.pay') && !$mine;
$actionLabels = ['created' => 'Created', 'edited' => 'Edited', 'submitted' => 'Sent for approval', 'approve' => 'Approved by Head Teacher', 'approve_l2' => 'Approved by School Manager', 'reject' => 'Rejected', 'reject_l2' => 'Rejected by School Manager', 'return' => 'Returned for changes', 'return_l2' => 'Returned by School Manager', 'paid' => 'Paid out', 'retired' => 'Receipts checked — closed', 'cancelled' => 'Cancelled'];
?>
<div class="page-head">
    <div>
        <p class="breadcrumb"><a href="<?= e(url('requisitions')) ?>">Requisitions</a> › <?= e($r['req_no']) ?></p>
        <h1><?= e($r['title']) ?> <?= Requisition::badge($r['status']) ?></h1>
        <p class="muted"><?= e($r['requester']) ?> · <?= e(Auth::roleLabel($r['requester_role'])) ?><?= $r['department'] ? ' · ' . e($r['department']) : '' ?> · <?= e($r['category_name'] ?? '') ?><?= $r['needed_by'] ? ' · needed by ' . e(fmt_date($r['needed_by'])) : '' ?></p>
    </div>
    <div class="actions">
        <a class="btn" href="<?= e(url('requisitions/' . $r['id'] . '/print')) ?>" target="_blank" rel="noopener">Print form</a>
        <?php if (Requisition::editable($r)): ?>
            <a class="btn" href="<?= e(url('requisitions/' . $r['id'] . '/edit')) ?>">Edit</a>
            <form method="post" action="<?= e(url('requisitions/' . $r['id'] . '/submit')) ?>" class="inline"><?= csrf_field() ?><button class="btn btn-primary" type="submit">Send for approval</button></form>
        <?php endif; ?>
    </div>
</div>
<?php if ($r['status'] === 'returned'): $lastNote = end($log); ?><div class="alert alert-warning">Returned for changes<?= $lastNote && $lastNote['note'] ? ': ' . e($lastNote['note']) : '' ?>. Edit it and send it again.</div><?php endif; ?>

<div class="cols">
    <div>
        <div class="card" style="padding:0">
            <div class="table-wrap"><table>
                <tr><th>Item</th><th style="text-align:right">Qty</th><th>Unit</th><th style="text-align:right">Unit cost</th><th style="text-align:right">Total</th></tr>
                <?php foreach ($items as $it): ?>
                <tr><td><?= e($it['description']) ?></td><td style="text-align:right"><?= e(rtrim(rtrim($it['quantity'], '0'), '.')) ?></td><td><?= e($it['unit'] ?? '') ?></td><td style="text-align:right"><?= e(number_format((float) $it['unit_cost'], 2)) ?></td><td style="text-align:right"><?= e(number_format((float) $it['quantity'] * (float) $it['unit_cost'], 2)) ?></td></tr>
                <?php endforeach; ?>
                <tr><td colspan="4"><strong>Requested</strong></td><td style="text-align:right"><strong><?= e(Fees::money($r['amount'])) ?></strong></td></tr>
                <?php if ($r['approved_amount'] !== null): ?><tr><td colspan="4">Approved</td><td style="text-align:right"><strong style="color:var(--good)"><?= e(Fees::money($r['approved_amount'])) ?></strong></td></tr><?php endif; ?>
                <?php if ((float) $r['paid_amount'] > 0): ?><tr><td colspan="4">Paid out</td><td style="text-align:right"><?= e(Fees::money($r['paid_amount'])) ?></td></tr><?php endif; ?>
                <?php if ($r['spent_amount'] !== null): ?><tr><td colspan="4">Actually spent</td><td style="text-align:right"><?= e(Fees::money($r['spent_amount'])) ?></td></tr><?php endif; ?>
            </table></div>
        </div>
        <?php if ($r['purpose']): ?><div class="card"><h2>Why it is needed</h2><p style="white-space:pre-line;margin:0"><?= e($r['purpose']) ?></p></div><?php endif; ?>

        <div class="card">
            <h2>Documents</h2>
            <?php foreach ($files as $f): ?>
                <p style="margin:0 0 .4rem"><?= badge(ucfirst($f['kind']), $f['kind'] === 'receipt' ? 'good' : 'info') ?> <a href="<?= e(url('requisitions/' . $r['id'] . '/files/' . $f['id'])) ?>"><?= e($f['original_name']) ?></a> <small class="muted"><?= e($f['uploader'] ?? '') ?>, <?= e(date('d M', strtotime($f['uploaded_at']))) ?></small></p>
            <?php endforeach; ?>
            <?php if (!$files): ?><p class="muted">No documents attached.</p><?php endif; ?>
            <?php if (!in_array($r['status'], ['cancelled', 'rejected', 'retired'], true)): ?>
            <p style="margin:.6rem 0 0"><button type="button" class="btn btn-sm btn-ghost" data-help="attach-box" aria-expanded="false">+ Attach a document</button></p>
            <form id="attach-box" hidden method="post" action="<?= e(url('requisitions/' . $r['id'] . '/files')) ?>" enctype="multipart/form-data" class="filters" style="margin-top:.6rem">
                <?= csrf_field() ?>
                <select name="kind" style="min-width:120px"><option value="quote">Quotation</option><option value="receipt"<?= in_array($r['status'], ['paid'], true) ? ' selected' : '' ?>>Receipt</option><option value="other">Other</option></select>
                <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" required>
                <button class="btn btn-sm" type="submit">Attach</button>
            </form>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>History</h2>
            <ul class="trail">
                <?php foreach ($log as $l): ?>
                <li><strong><?= e($actionLabels[$l['action']] ?? $l['action']) ?></strong><small class="muted"><?= e($l['user_name'] ?? '') ?> · <?= e(date('d M Y H:i', strtotime($l['created_at']))) ?></small><?= $l['note'] ? '<br>' . e($l['note']) : '' ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div>
        <?php $showBudget = $budget && ($canDecide || can('requisitions.view_all')); $showDept = !empty($dept) && ($canDecide || can('requisitions.view_all') || $mine); $want = (float) ($r['approved_amount'] ?? $r['amount']); ?>
        <?php if ($showBudget || $showDept): ?>
        <div class="card">
            <h2>Money available</h2>
            <div class="table-wrap"><table data-nopage><tbody>
            <?php if ($showDept): ?>
                <tr><td><?= e($r['department']) ?> — left this term<br><small class="muted">Limit <?= e(Fees::money($dept['cap'])) ?> · spent <?= e(Fees::money($dept['spent'])) ?> · promised <?= e(Fees::money($dept['committed'])) ?></small></td>
                    <td class="num"><strong style="color:<?= $dept['left'] < ($level ? $want : 0) ? 'var(--bad)' : 'var(--good)' ?>"><?= e(Fees::money($dept['left'])) ?></strong></td></tr>
            <?php endif; ?>
            <?php if ($showBudget): ?>
                <tr><td><?= e($r['category_name']) ?> — left in the <?= e($budget['year']) ?> budget<?php if ($budget['budget'] !== null): ?><br><small class="muted">Budget <?= e(Fees::money($budget['budget'])) ?> · spent <?= e(Fees::money($budget['spent'])) ?> · promised <?= e(Fees::money($budget['committed'])) ?></small><?php endif; ?></td>
                    <td class="num"><?= $budget['budget'] === null ? '<span class="muted">no budget</span>' : '<strong style="color:' . ($budget['remaining'] < $want ? 'var(--bad)' : 'var(--good)') . '">' . e(Fees::money($budget['remaining'])) . '</strong>' ?></td></tr>
            <?php endif; ?>
            </tbody></table></div>
            <?php if ($level && $showBudget && $budget['budget'] !== null && $budget['remaining'] < $want): ?><p class="hint" style="color:var(--bad)">Approving this goes over the category budget.</p><?php endif; ?>
            <?php if ($showDept && can('budget.caps')): ?><p class="hint"><a href="<?= e(url('finance/budget/departments')) ?>">Raise the limit or move money</a></p><?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($canDecide): ?>
        <div class="card" style="border-color:var(--warn)">
            <h2><?= $level === 1 ? 'Head Teacher decision' : 'School Manager decision' ?></h2>
            <?php if ($level === 2): ?><p class="hint">Approved by <?= e($r['approver_name']) ?> for <?= e(Fees::money($r['approved_amount'])) ?>. Needs a second approval because it is above <?= e(Fees::money(Requisition::threshold())) ?>.</p><?php endif; ?>
            <form method="post" action="<?= e(url('requisitions/' . $r['id'] . '/decide')) ?>" class="form">
                <?= csrf_field() ?>
                <label>Approved amount (GH₵)<input name="approved_amount" inputmode="decimal" value="<?= e(number_format((float) ($r['approved_amount'] ?? $r['amount']), 2, '.', '')) ?>"></label>
                <label>Note (required to return or reject)<textarea name="note" rows="2" maxlength="500"></textarea></label>
                <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                    <button class="btn btn-good" type="submit" name="decision" value="approve">Approve</button>
                    <button class="btn" type="submit" name="decision" value="return">Return for changes</button>
                    <button class="btn btn-danger" type="submit" name="decision" value="reject">Reject</button>
                </div>
            </form>
        </div>
        <?php elseif ($level && $mine): ?>
        <div class="card"><p class="muted" style="margin:0">Waiting for <?= $level === 1 ? 'the Head Teacher' : 'the School Manager' ?>.</p></div>
        <?php endif; ?>

        <?php if ($sbill): ?>
        <div class="card"><h2>Paid to the supplier</h2><p style="margin:0">Supplier bill <a href="<?= e(url('suppliers/bills/' . $sbill['id'])) ?>"><?= e($sbill['bill_no']) ?></a> <?= badge(Suppliers::STATUS[$sbill['status']][0], Suppliers::STATUS[$sbill['status']][1]) ?></p></div>
        <?php endif; ?>
        <?php if (!empty($po)): ?>
        <div class="card"><h2>Being bought</h2><p style="margin:0">Purchase order <a href="<?= e(url('purchasing/' . $po['id'])) ?>"><?= e($po['po_no']) ?></a> <?= Purchasing::badge($po['status']) ?></p></div>
        <?php endif; ?>
        <?php if ($canPo): ?>
        <div class="card" style="border-color:var(--brand)">
            <h2>Next: buy it</h2>
            
            <a class="btn btn-primary" href="<?= e(url('purchasing/new', ['req' => $r['id']])) ?>">Make purchase order</a>
            <?php if ($canPay): ?><p class="hint" style="margin-top:.6rem">Small cash purchase by the requester instead? <a href="#" data-help="payout-box">Pay out cash</a></p><?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if ($canPay): ?>
        <div class="card" id="payout-box"<?= $canPo ? ' hidden' : '' ?> style="border-color:var(--brand)">
            <h2>Pay out</h2>
            <?php if (can('finance.manage') && Family::ready()): ?><p class="hint">Paying a supplier directly (with withholding tax)? <a href="<?= e(url('suppliers/bills/new', ['req' => $r['id']])) ?>">Turn this into a supplier bill</a>.</p><?php endif; ?>
            <form method="post" action="<?= e(url('requisitions/' . $r['id'] . '/pay')) ?>" class="form" data-confirm="Pay out this requisition? It will be recorded as an expense.">
                <?= csrf_field() ?>
                <label>Amount (GH₵)<input name="amount" inputmode="decimal" required value="<?= e(number_format((float) $r['approved_amount'], 2, '.', '')) ?>"></label>
                <label>Paid from<select name="account_id" required<?= QuickAddController::attr('fin_account') ?>><?php foreach ($accounts as $a): ?><option value="<?= (int) $a['id'] ?>"><?= e($a['name']) ?> (<?= e(number_format(Finance::balance((int) $a['id']), 2)) ?>)</option><?php endforeach; ?></select></label>
                <div class="grid-2">
                    <label>Date<input type="date" name="paid_on" value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>"></label>
                    <label>Cheque / MoMo ref.<input name="reference" maxlength="100"></label>
                </div>
                <label>Paid to<input name="party" maxlength="150" value="<?= e($r['requester']) ?>"></label>
                <label style="font-weight:400"><input type="checkbox" name="allow_overdraw" value="1"> Allow overdraft</label>
                <button class="btn btn-primary" type="submit">Record payment</button>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($canRetire): ?>
        <div class="card" style="border-color:var(--good)">
            <h2>Receipts &amp; change</h2>
            
            <form method="post" action="<?= e(url('requisitions/' . $r['id'] . '/retire')) ?>" enctype="multipart/form-data" class="form">
                <?= csrf_field() ?>
                <label>Amount actually spent (GH₵)<input name="spent" inputmode="decimal" required value="<?= e(number_format((float) $r['paid_amount'], 2, '.', '')) ?>"></label>
                <label>Change paid back into<select name="account_id"<?= QuickAddController::attr('fin_account') ?>><?php foreach ($accounts as $a): ?><option value="<?= (int) $a['id'] ?>"<?= selected($payment['account_id'] ?? 0, $a['id']) ?>><?= e($a['name']) ?></option><?php endforeach; ?></select></label>
                <label>Receipt (optional)<input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png"></label>
                <label>Note<input name="note" maxlength="255"></label>
                <button class="btn btn-good" type="submit">Close requisition</button>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($payment): ?>
        <div class="card"><h2>Payment</h2><p style="margin:0;font-size:.9rem"><?= e(Fees::money($payment['amount'])) ?> from <?= e($payment['account_name']) ?> on <?= e(fmt_date($payment['txn_date'])) ?><?= $r['paid_by_name'] ? ' by ' . e($r['paid_by_name']) : '' ?>.<?php if (can('finance.view')): ?> <a href="<?= e(url('finance/transactions/' . $payment['id'])) ?>">Cash book entry</a><?php endif; ?></p></div>
        <?php endif; ?>

        <?php if ($mine && in_array($r['status'], ['draft', 'submitted', 'returned', 'approved_l1', 'approved'], true)): ?>
        <form method="post" action="<?= e(url('requisitions/' . $r['id'] . '/cancel')) ?>" data-confirm="Cancel this requisition?"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit">Cancel requisition</button></form>
        <?php endif; ?>
    </div>
</div>
