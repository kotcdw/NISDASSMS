<?php $docTitle = $inv['status'] === 'cancelled' ? 'BILL (CANCELLED)' : 'FEE BILL'; $docSub = $inv['invoice_no']; $bal = Fees::balance($inv); $bf = $inv['status'] === 'cancelled' ? 0.0 : Fees::arrearsBefore($inv); ?>
<div class="card-page doc-page">
    <?php require __DIR__ . '/_doc_head.php'; ?>
    <table class="rc-info">
        <tr><th>Student</th><td><?= e(full_name($inv)) ?></td><th>Admission no.</th><td><?= e($inv['admission_no']) ?></td></tr>
        <tr><th>Class</th><td><?= e($inv['class_name'] ?? '') ?></td><th>Bill for</th><td><?= e($inv['title']) ?></td></tr>
        <tr><th>Parent / guardian</th><td><?= e($guardian['name'] ?? '') ?></td><th>Issued · Due</th><td><?= e(fmt_date($inv['issue_date'])) ?> · <?= e(fmt_date($inv['due_date'])) ?></td></tr>
    </table>
    <table class="rc-scores">
        <thead><tr><th class="l">Item</th><th>Amount (GH₵)</th><th>Discount</th><th>Net (GH₵)</th></tr></thead>
        <tbody>
        <?php foreach ($lines as $l): ?>
            <tr><td class="l"><?= e($l['description']) ?><?= $l['discount_note'] ? ' <small>(' . e($l['discount_note']) . ')</small>' : '' ?></td><td><?= e(number_format((float) $l['amount'], 2)) ?></td><td><?= (float) $l['discount'] > 0 ? e(number_format((float) $l['discount'], 2)) : '' ?></td><td><?= e(number_format((float) $l['amount'] - (float) $l['discount'], 2)) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr><td class="l" colspan="3"><strong>Total</strong></td><td><strong><?= e(number_format((float) $inv['total'], 2)) ?></strong></td></tr>
            <?php foreach ($payments as $p): ?><tr><td class="l" colspan="3">Paid <?= e(fmt_date($p['paid_on'])) ?> — receipt <?= e($p['receipt_no']) ?></td><td>−<?= e(number_format((float) $p['amount'], 2)) ?></td></tr><?php endforeach; ?>
            <tr><td class="l" colspan="3"><strong><?= $bf > 0 ? 'Balance on this bill' : 'Balance to pay' ?></strong></td><td><strong><?= e(number_format(max(0, $bal), 2)) ?></strong></td></tr>
            <?php if ($bf > 0): ?>
            <tr><td class="l" colspan="3">Balance brought forward (earlier bills not yet paid)</td><td><?= e(number_format($bf, 2)) ?></td></tr>
            <tr><td class="l" colspan="3"><strong>Total to pay now</strong></td><td><strong><?= e(number_format(max(0, $bal) + $bf, 2)) ?></strong></td></tr>
            <?php endif; ?>
        </tfoot>
    </table>
    <div class="doc-note">
        <strong>How to pay</strong>
        <ul>
            <li>At the school accounts office (cash) — always collect an official receipt.</li>
            <?php if (setting('bursar_momo_number')): ?><li>Mobile Money to <strong><?= e(setting('bursar_momo_number')) ?></strong> — use <strong><?= e($inv['invoice_no']) ?></strong> as the reference.</li><?php endif; ?>
            <?php if (setting('bank_details')): ?><li>Bank: <?= e(setting('bank_details')) ?> — write the student's name and <?= e($inv['admission_no']) ?> on the slip.</li><?php endif; ?>
            <?php if (PaymentGateway::active() !== 'none' && $bal > 0): ?><li>Online by Mobile Money or card: <strong><?= e(Fees::payUrl($inv)) ?></strong></li><?php endif; ?>
        </ul>
    </div>
    <div class="rc-sign"><div><span></span>Bursar</div><div></div><div><span></span>Head Teacher</div></div>
</div>
