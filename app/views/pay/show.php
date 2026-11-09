<?php [$sl, $st] = Fees::STATUS[$inv['status']]; ?>
<div class="card" style="max-width:640px;margin:0 auto">
    <p class="muted" style="margin:0">Bill <?= e($inv['invoice_no']) ?> · issued <?= e(fmt_date($inv['issue_date'])) ?></p>
    <h1 style="margin:.2rem 0"><?= e($inv['title']) ?> <?= badge($sl, $st) ?></h1>
    <p><strong><?= e(full_name($inv)) ?></strong> · <?= e($inv['class_name'] ?? '') ?> · <?= e($inv['admission_no']) ?></p>
    <div class="table-wrap"><table>
        <?php foreach ($lines as $l): ?>
        <tr><td><?= e($l['description']) ?><?= (float) $l['discount'] > 0 ? '<br><small class="muted">' . e($l['discount_note'] ?: 'Discount') . ' −' . e(number_format((float) $l['discount'], 2)) . '</small>' : '' ?></td><td style="text-align:right"><?= e(number_format((float) $l['amount'] - (float) $l['discount'], 2)) ?></td></tr>
        <?php endforeach; ?>
        <tr><td><strong>Total</strong></td><td style="text-align:right"><strong><?= e(Fees::money($inv['total'])) ?></strong></td></tr>
        <?php foreach ($payments as $p): ?><tr><td><small>Paid <?= e(fmt_date($p['paid_on'])) ?> · receipt <?= e($p['receipt_no']) ?></small></td><td style="text-align:right"><small>−<?= e(number_format((float) $p['amount'], 2)) ?></small></td></tr><?php endforeach; ?>
        <tr><td><strong>Balance</strong></td><td style="text-align:right"><strong style="font-size:1.2rem;color:<?= $balance > 0 ? 'var(--bad)' : 'var(--good)' ?>"><?= e(Fees::money(max(0, $balance))) ?></strong></td></tr>
    </table></div>
    <?php $manage = false; require dirname(__DIR__) . '/fees/_plan.php'; ?>
    <?php if ($inv['due_date'] && $balance > 0): ?><p class="muted">Due by <?= e(fmt_date($inv['due_date'], 'l j F Y')) ?>.</p><?php endif; ?>

    <?php if ($inv['status'] === 'cancelled'): ?>
        <div class="alert alert-info">This bill has been cancelled. Please contact the school office.</div>
    <?php elseif ($balance <= 0): ?>
        <div class="alert alert-success">This bill is fully paid. Thank you!</div>
    <?php elseif ($gateway !== 'none'): ?>
        <h2>Pay with Mobile Money</h2>
        <?php if ($gateway === 'test'): ?><div class="alert alert-warning">Test mode — no real money will be taken.</div><?php endif; ?>
        <form method="post" action="<?= e(url('pay/' . $inv['public_key'])) ?>" class="form">
            <?= csrf_field() ?>
            <div class="grid-2">
                <label>Amount to pay (GH₵)<input name="amount" inputmode="decimal" required value="<?= e(number_format($balance, 2, '.', '')) ?>"></label>
                <label>Your MoMo number<input name="phone" inputmode="tel" required maxlength="20" placeholder="024 123 4567"></label>
            </div>
            <?php $gwPct = (float) setting('fee_gateway_pct', '0'); if ($gwPct > 0 && setting('fee_gateway_payer') === 'parent'): ?><p class="hint">An online payment charge of <?= e(rtrim(rtrim(number_format($gwPct, 2), '0'), '.')) ?>% is added to the amount you pay. Paying at the school has no charge.</p><?php endif; ?>
            <button class="btn btn-primary" type="submit">Continue to secure payment</button>
            
        </form>
    <?php endif; ?>
    <?php if ($balance > 0 && $inv['status'] !== 'cancelled'): ?>
        <h3>Other ways to pay</h3>
        <ul style="padding-left:1.1rem">
            <li>At the school accounts office — always collect an official receipt.</li>
            <?php if (setting('bursar_momo_number')): ?><li>MoMo to <strong><?= e(setting('bursar_momo_number')) ?></strong>, reference <strong><?= e($inv['invoice_no']) ?></strong>.</li><?php endif; ?>
            <?php if (setting('bank_details')): ?><li>Bank: <?= e(setting('bank_details')) ?></li><?php endif; ?>
        </ul>
    <?php endif; ?>
</div>
