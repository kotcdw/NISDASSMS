<?php $docTitle = 'FEE STATEMENT'; $docSub = date('d M Y'); ?>
<div class="card-page doc-page">
    <?php require __DIR__ . '/_doc_head.php'; ?>
    <table class="rc-info">
        <tr><th>Student</th><td><?= e(full_name($pupil)) ?></td><th>Admission no.</th><td><?= e($pupil['admission_no']) ?></td></tr>
        <tr><th>Class</th><td><?= e($pupil['class_name'] ?? '') ?></td><th>Parent / guardian</th><td><?= e(($guardian['name'] ?? '') . ' ' . ($guardian['phone'] ?? '')) ?></td></tr>
    </table>
    <table class="rc-scores">
        <thead><tr><th>Date</th><th class="l">Details</th><th>Billed</th><th>Paid</th><th>Balance</th></tr></thead>
        <tbody>
        <?php foreach ($ledger as $l): ?>
            <tr><td><?= e(fmt_date($l['date'])) ?></td><td class="l"><?= e($l['text']) ?></td><td><?= $l['debit'] ? e(number_format($l['debit'], 2)) : '' ?></td><td><?= $l['credit'] ? e(number_format($l['credit'], 2)) : '' ?></td><td><?= e(number_format($l['balance'], 2)) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="4" class="l"><strong><?= $balance < 0 ? 'Credit' : 'Balance owed' ?></strong></td><td><strong><?= e(Fees::money(abs($balance))) ?></strong></td></tr></tfoot>
    </table>
    <div class="rc-sign"><div><span></span>Bursar</div><div></div><div><span></span>School stamp</div></div>
</div>
