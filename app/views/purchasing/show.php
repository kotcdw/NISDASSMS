<?php
$q = fn($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
$st = $po['status'];
$open = in_array($st, ['sent', 'part'], true);
$canReceive = $open && can('purchasing.receive') && (Auth::role() === 'super_admin' || !in_array((int) Auth::id(), [(int) $po['created_by'], (int) $po['sent_by']], true));
$billable = in_array($st, ['sent', 'part', 'received'], true) && can('purchasing.order')
    && (bool) array_filter($lines, fn($l) => (float) $l['qty'] - (float) ($billed[(int) $l['id']] ?? 0) > 0.004);
$hasBill = (bool) array_filter($bills, fn($b) => $b['status'] !== 'cancelled');
$step = fn(bool $done, bool $now) => $done ? 'done' : ($now ? 'now' : '');
$received = (bool) array_filter($grns, fn($g) => $g['status'] === 'posted');
$paid = $hasBill && !array_filter($bills, fn($b) => in_array($b['status'], ['unpaid', 'part'], true));
?>
<div class="page-head">
    <div><p class="breadcrumb"><a href="<?= e(url('purchasing')) ?>">Purchase orders</a> › <?= e($po['po_no']) ?></p>
        <h1><?= e($po['po_no']) ?> · <?= e($po['supplier_name']) ?> <?= Purchasing::badge($st) ?></h1>
        <p class="muted"><?= $po['req_no'] ? 'For ' . e($po['req_no']) . ' — ' . e($po['req_title']) . ' · ' : '' ?><?= $po['department'] ? e($po['department']) . ' · ' : '' ?><?= e($po['category_name'] ?? '') ?> · ordered <?= e(fmt_date($po['order_date'])) ?><?= $po['expected_date'] ? ' · expected ' . e(fmt_date($po['expected_date'])) : '' ?></p></div>
    <div class="actions">
        <?php if ($st !== 'draft'): ?><a class="btn" href="<?= e(url('purchasing/' . $po['id'] . '/print')) ?>" target="_blank" rel="noopener">Print order</a><?php endif; ?>
        <?php if (can('purchasing.order') && !in_array($st, ['closed', 'cancelled'], true)): ?><details class="menu-btn"><summary class="btn">Tools ▾</summary><div>
            <?php if ($po['requisition_id']): ?><a href="<?= e(url('requisitions/' . $po['requisition_id'])) ?>">Requisition <?= e($po['req_no']) ?></a><?php endif; ?>
            <?php if (!$received && !$hasBill): ?><a href="#" data-help="po-cancel" class="danger">Cancel order</a><?php endif; ?>
            <?php if ($received): ?><a href="#" data-help="po-close">Close order (nothing more expected)</a><?php endif; ?>
        </div></details><?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/_nav.php'; ?>
<ol class="po-steps" aria-label="Steps of this purchase">
    <li class="done"><b>1 · Requisition ✓</b><?= e($po['req_no'] ?? '—') ?></li>
    <li class="done"><b>2 · Approved ✓</b>Within the approved amount</li>
    <li class="<?= $step($st !== 'draft' && $st !== 'cancelled', $st === 'draft') ?>"><b>3 · Order <?= $st !== 'draft' ? 'sent ✓' : '(draft)' ?></b><?= e($po['supplier_name']) ?></li>
    <li class="<?= $step(in_array($st, ['received', 'closed'], true), $st === 'part' || $st === 'sent') ?>"><b>4 · Goods received<?= $st === 'part' ? ' !' : (in_array($st, ['received', 'closed'], true) ? ' ✓' : '') ?></b><?= count(array_filter($grns, fn($g) => $g['status'] === 'posted')) ?> delivery note(s)</li>
    <li class="<?= $step($hasBill, $received && !$hasBill) ?>"><b>5 · Supplier invoice<?= $hasBill ? ' ✓' : '' ?></b><?= $hasBill ? 'Recorded' : 'Not yet' ?></li>
    <li class="<?= $step($paid, $hasBill && !$paid) ?>"><b>6 · Pay<?= $paid ? ' ✓' : '' ?></b><?= $paid ? 'Paid' : ($hasBill ? 'Waiting for a match / payment' : '—') ?></li>
</ol>
<?php if ($st === 'cancelled'): ?><div class="alert alert-info">Cancelled: <?= e($po['cancel_reason']) ?></div><?php endif; ?>
<div id="po-cancel" class="help-box form-center" hidden>
    <form method="post" action="<?= e(url('purchasing/' . $po['id'] . '/cancel')) ?>" class="card form" data-confirm="Cancel <?= e($po['po_no']) ?>?"><?= csrf_field() ?>
        <h2>Cancel order</h2><label class="required">Reason<input name="reason" required maxlength="255"></label>
        <div class="vf-foot"><span></span><span style="display:flex;gap:.5rem"><button type="button" class="btn btn-ghost" data-help="po-cancel">Close</button><button class="btn btn-danger" type="submit">Cancel order</button></span></div>
    </form>
</div>
<div id="po-close" class="help-box form-center" hidden>
    <form method="post" action="<?= e(url('purchasing/' . $po['id'] . '/close')) ?>" class="card form"><?= csrf_field() ?>
        <h2>Close order</h2><label class="required">Why<input name="note" required maxlength="200" placeholder="e.g. Supplier has no more stock; 28 of 30 accepted"></label>
        <div class="vf-foot"><span></span><span style="display:flex;gap:.5rem"><button type="button" class="btn btn-ghost" data-help="po-close">Back</button><button class="btn btn-primary" type="submit">Close order</button></span></div>
    </form>
</div>
<div class="cols">
    <div>
        <div class="card" style="padding:0">
            <div class="table-wrap"><table data-nopage>
                <thead><tr><th>Item</th><th class="num">Ordered</th><th class="num">Received</th><th class="num">Invoiced</th><th class="num">Price each</th><th class="num">Value</th></tr></thead>
                <tbody><?php foreach ($lines as $l): $short = (float) $l['received_qty'] + 0.004 < (float) $l['qty']; ?>
                <tr><td><?= e($l['description']) ?><?= $l['unit'] ? ' <small class="muted">(' . e($l['unit']) . ')</small>' : '' ?></td>
                    <td class="num"><?= e($q($l['qty'])) ?></td><td class="num"<?= $short && $st !== 'draft' ? ' style="color:var(--bad);font-weight:700"' : '' ?>><?= e($q($l['received_qty'])) ?></td>
                    <td class="num"><?= e($q($billed[(int) $l['id']] ?? 0)) ?></td>
                    <td class="num"><?= e(number_format((float) $l['unit_price'], 2)) ?></td><td class="num"><?= e(number_format((float) $l['qty'] * (float) $l['unit_price'], 2)) ?></td></tr>
                <?php endforeach; ?></tbody>
                <tfoot><tr><td colspan="5"><strong>Order total</strong></td><td class="num"><strong><?= e(number_format((float) $po['total'], 2)) ?></strong></td></tr></tfoot>
            </table></div>
        </div>
        <?php if ($grns): ?>
        <div class="card"><h2>Goods received</h2>
            <?php foreach ($grns as $g): $void = $g['status'] === 'void'; ?>
            <div style="border-top:1px solid var(--line);padding:.5rem 0<?= $void ? ';opacity:.55' : '' ?>">
                <strong><?= e($g['grn_no']) ?></strong> · <?= e(fmt_date($g['received_date'])) ?> · signed by <?= e($g['by_name'] ?? '—') ?><?= $g['delivery_note'] ? ' · delivery note ' . e($g['delivery_note']) : '' ?><?= $void ? ' · ' . badge('cancelled', 'neutral') . ' ' . e($g['void_reason']) : '' ?>
                <details class="rowmenu" style="float:right"><summary aria-label="Actions for <?= e($g['grn_no']) ?>">⋯</summary><div>
                    <a href="<?= e(url('purchasing/grn/' . $g['id'])) ?>" target="_blank" rel="noopener">Print</a>
                    <?php if (!$void && can('purchasing.order')): ?><form method="post" action="<?= e(url('purchasing/grn/' . $g['id'] . '/void')) ?>" data-confirm="Cancel <?= e($g['grn_no']) ?>? Use this only if it was recorded wrongly."><?= csrf_field() ?><input name="reason" required maxlength="255" placeholder="Reason" aria-label="Reason" style="margin:.3rem .4rem;width:calc(100% - .8rem)"><button type="submit" class="danger">Cancel this note</button></form><?php endif; ?>
                </div></details>
                <br><small><?= e(implode(' · ', array_map(fn($x) => $q($x['qty']) . ' × ' . $x['description'], $grnLines[(int) $g['id']] ?? []))) ?></small>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if ($bills): ?>
        <div class="card"><h2>Supplier invoices</h2>
            <div class="table-wrap"><table data-nopage><tbody><?php foreach ($bills as $b): [$bl, $bt] = Suppliers::STATUS[$b['status']]; ?>
                <tr><td><a href="<?= e(url('suppliers/bills/' . $b['id'])) ?>"><?= e($b['bill_no']) ?></a><?= $b['supplier_ref'] ? ' <small class="muted">' . e($b['supplier_ref']) . '</small>' : '' ?></td><td><?= e(fmt_date($b['bill_date'])) ?></td><td class="num"><?= e(number_format((float) $b['amount'], 2)) ?></td><td><?= badge($bl, $bt) ?></td></tr>
            <?php endforeach; ?></tbody></table></div>
        </div>
        <?php endif; ?>
    </div>
    <div>
        <?php if ($st === 'draft' && can('purchasing.order')): ?>
        <div class="card" style="border-color:var(--brand)">
            <h2>Send to the supplier</h2>
            <?php if ($need > $quotes): ?><p class="hint" style="color:var(--bad)">Needs <?= $need ?> quotation<?= $need > 1 ? 's' : '' ?> on the requisition (<?= $quotes ?> attached). <a href="<?= e(url('requisitions/' . $po['requisition_id'])) ?>">Attach them</a>.</p><?php endif; ?>
            <form method="post" action="<?= e(url('purchasing/' . $po['id'] . '/send')) ?>" data-confirm="Send <?= e($po['po_no']) ?> to <?= e($po['supplier_name']) ?>?"><?= csrf_field() ?><button class="btn btn-primary" type="submit">Mark as sent</button></form>
            
        </div>
        <?php endif; ?>
        <?php if ($canReceive): ?>
        <form method="post" action="<?= e(url('purchasing/' . $po['id'] . '/receive')) ?>" class="card form" data-confirm="Record these goods as received?"><?= csrf_field() ?>
            <h2>Sign for goods received</h2>
            <div class="table-wrap"><table class="lines" data-nopage><tbody><?php foreach ($lines as $l): $left = (float) $l['qty'] - (float) $l['received_qty']; if ($left <= 0.004) continue; ?>
                <tr><td><?= e($l['description']) ?><br><small class="muted"><?= e($q($left)) ?> still expected</small></td><td style="width:6.5rem"><input name="qty[<?= (int) $l['id'] ?>]" inputmode="decimal" value="<?= e($q($left)) ?>" aria-label="Received: <?= e($l['description']) ?>" style="text-align:right"></td></tr>
            <?php endforeach; ?></tbody></table></div>
            <div class="grid-2">
                <label class="required">Date received<input type="date" name="received_date" required value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>"></label>
                <label>Supplier's delivery note no.<input name="delivery_note" maxlength="60"></label>
            </div>
            <label>Note (condition, damages …)<input name="note" maxlength="255"></label>
            <button class="btn btn-primary" type="submit">Record goods received</button>
        </form>
        <?php elseif ($open && can('purchasing.receive')): ?>
        <div class="card"><p class="muted" style="margin:0">You made or sent this order, so someone else (the storekeeper) must sign for the goods.</p></div>
        <?php endif; ?>
        <?php if ($billable): $billOpen = !$canReceive; ?>
        <?php if (!$billOpen): ?><p><button type="button" class="btn btn-ghost" data-help="bill-box" aria-expanded="false">Record the supplier's invoice…</button></p><?php endif; ?>
        <form id="bill-box"<?= $billOpen ? '' : ' hidden' ?> method="post" action="<?= e(url('purchasing/' . $po['id'] . '/bill')) ?>" enctype="multipart/form-data" class="card form"><?= csrf_field() ?>
            <h2>Record the supplier's invoice</h2>
            
            <div class="table-wrap"><table class="lines" data-nopage>
                <thead><tr><th>Item</th><th style="width:5.5rem">Qty</th><th style="width:7rem">Price</th></tr></thead>
                <tbody><?php foreach ($lines as $l): $left = (float) $l['qty'] - (float) ($billed[(int) $l['id']] ?? 0); if ($left <= 0.004) continue; ?>
                <tr><td><?= e($l['description']) ?></td><td><input name="bq[<?= (int) $l['id'] ?>]" inputmode="decimal" value="<?= e($q($left)) ?>" aria-label="Invoiced quantity: <?= e($l['description']) ?>" style="text-align:right"></td>
                    <td><input name="bp[<?= (int) $l['id'] ?>]" inputmode="decimal" value="<?= e(number_format((float) $l['unit_price'], 2, '.', '')) ?>" aria-label="Invoiced price: <?= e($l['description']) ?>" style="text-align:right"></td></tr>
                <?php endforeach; ?></tbody>
            </table></div>
            <div class="grid-2">
                <label class="required">Invoice date<input type="date" name="bill_date" required value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>"></label>
                <label>Pay by<input type="date" name="due_date" value="<?= e(date('Y-m-d', strtotime('+30 days'))) ?>"></label>
                <label>Supplier's invoice no.<input name="supplier_ref" maxlength="60"></label>
                <label>Invoice (PDF, JPG, PNG)<input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png"></label>
            </div>
            <label style="font-weight:400"><input type="checkbox" name="no_wht" value="1"> WHT exempt</label>
            <button class="btn btn-primary" type="submit">Record invoice</button>
        </form>
        <?php endif; ?>
        <p class="hint">Made by <?= e($po['created_name'] ?? '—') ?><?= $po['sent_at'] ? ' · sent by ' . e($po['sent_name'] ?? '—') . ' on ' . e(fmt_date($po['sent_at'], 'd M Y')) : '' ?><?= $po['deliver_to'] ? ' · deliver to ' . e($po['deliver_to']) : '' ?>.</p>
    </div>
</div>
