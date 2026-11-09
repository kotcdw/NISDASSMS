<div class="card" style="max-width:560px;margin:0 auto;text-align:center">
    <?php if ($result === 'success'): ?>
        <h1 style="color:var(--good)">✓ Payment received</h1>
        <p>Thank you. <?= $receipt ? e(Fees::money($receipt['amount'])) . ' has been credited — receipt <strong>' . e($receipt['receipt_no']) . '</strong>.' : '' ?></p>
        <p class="muted">A receipt has been sent to you by SMS.</p>
    <?php elseif ($result === 'failed'): ?>
        <h1 style="color:var(--bad)">Payment not completed</h1>
        <p>The payment was declined or cancelled. No money was taken. You can try again.</p>
    <?php else: ?>
        <h1>Waiting for confirmation…</h1>
        <p>We have not yet received confirmation from the payment provider. If you approved the payment on your phone, it will be credited automatically within a few minutes and you will receive an SMS receipt.</p>
        <p><a class="btn" href="<?= e(url('pay/' . $inv['public_key'] . '/return', ['ref' => $op['reference']])) ?>">Check again</a></p>
    <?php endif; ?>
    <p><a class="btn btn-primary" href="<?= e(url('pay/' . $inv['public_key'])) ?>">Back to the bill</a></p>
    <p class="muted"><small>Reference <?= e($op['reference']) ?></small></p>
</div>
