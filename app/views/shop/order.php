<?php $ct = 'shop/admin'; [$sl, $st] = Shop::STATUS[$o['status']]; ?>
<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('shop/admin', ['status' => $o['status']])) ?>">Shop orders</a> /</div><h1><?= e($o['order_no']) ?></h1><p><?= badge($sl, $st) ?></p></div></div>
<div class="cols">
<div>
    <div class="card" style="padding:0"><div class="table-wrap"><table>
        <tr><th>Item</th><th>Size</th><th class="num">Qty</th><th class="num">Price</th><th class="num">Total</th></tr>
        <?php foreach ($lines as $l): ?><tr><td><?= e($l['name']) ?></td><td><?= e($l['size'] ?? '') ?></td><td class="num"><?= (int) $l['qty'] ?></td><td class="num"><?= e(number_format((float) $l['price'], 2)) ?></td><td class="num"><?= e(number_format($l['qty'] * (float) $l['price'], 2)) ?></td></tr><?php endforeach; ?>
        <tr><th colspan="4">Total</th><th class="num">GH₵ <?= e(number_format((float) $o['total'], 2)) ?></th></tr>
    </table></div></div>
    <div class="card"><dl class="details">
        <dt>Student</dt><dd><?= e($o['first_name'] . ' ' . $o['last_name']) ?> (<?= e($o['admission_no']) ?>)</dd>
        <dt>Ordered</dt><dd><?= e(fmt_date($o['created_at'], 'D j M Y, H:i')) ?> · <?= $o['channel'] === 'online' ? 'online by ' . e($o['ordered_name'] ?? 'parent') : 'at the counter' ?></dd>
        <?php if ($guardian): ?><dt>Parent</dt><dd><?= e($guardian['name']) ?> · <?= e($guardian['phone']) ?></dd><?php endif; ?>
        <dt>Bill</dt><dd><?= $o['invoice_id'] ? '<a href="' . e(url('fees/invoices/' . $o['invoice_id'])) . '">' . e($o['invoice_no']) . '</a> — paid GH₵ ' . e(number_format((float) $o['inv_paid'], 2)) : '—' ?></dd>
        <?php if ($o['paid_at']): ?><dt>Paid</dt><dd><?= e(fmt_date($o['paid_at'], 'D j M, H:i')) ?></dd><?php endif; ?>
        <?php if ($o['collected_at']): ?><dt>Collected</dt><dd><?= e(fmt_date($o['collected_at'], 'D j M, H:i')) ?> by <?= e($o['collected_by']) ?></dd><?php endif; ?>
        <?php if ($o['cancel_reason']): ?><dt>Cancelled</dt><dd><?= e($o['cancel_reason']) ?></dd><?php endif; ?>
    </dl></div>
</div>
<div>
    <?php if ($o['status'] === 'paid'): ?><form method="post" action="<?= e(url('shop/admin/orders/' . $o['id'])) ?>" class="card"><?= csrf_field() ?><input type="hidden" name="action" value="ready"><h2>Prepared?</h2><button class="btn btn-primary" type="submit">Mark ready and tell the parent</button></form><?php endif; ?>
    <?php if (in_array($o['status'], ['paid', 'ready'], true)): ?><form method="post" action="<?= e(url('shop/admin/orders/' . $o['id'])) ?>" class="card form"><?= csrf_field() ?><input type="hidden" name="action" value="collected"><h2>Hand over</h2>
        <label class="required">Collected by<input name="collected_by" maxlength="120" required placeholder="e.g. Mother — Mrs Frimpong"></label><button class="btn" type="submit">Handed over</button></form><?php endif; ?>
    <?php if (!in_array($o['status'], ['cancelled', 'collected'], true)): ?><form method="post" action="<?= e(url('shop/admin/orders/' . $o['id'])) ?>" class="card form" data-confirm="Cancel this order?"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><h2>Cancel</h2>
        <label>Reason<input name="reason" maxlength="255" required></label><button class="btn btn-ghost" type="submit">Cancel order</button>
        <?php if ((float) $o['inv_paid'] > 0): ?><small class="muted">Money was paid: reverse the receipt under Fees first.</small><?php endif; ?></form><?php endif; ?>
</div>
</div>
