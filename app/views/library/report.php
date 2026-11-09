<?php $docTitle = strtoupper($title); $docSub = date('d M Y'); ?>
<div class="card-page doc-page">
    <?php require dirname(__DIR__) . '/fees/_doc_head.php'; ?>
    <table class="lines" style="width:100%">
        <tr><th style="text-align:left">Class</th><th style="text-align:left">Borrower</th><th style="text-align:left">Book</th><th>Lent</th><th>Due</th><th>Days late</th></tr>
        <?php foreach ($loans as $l): $late = max(0, (int) ((strtotime(date('Y-m-d')) - strtotime($l['due_on'])) / 86400)); ?>
        <tr><td><?= e($l['class_name'] ?? ($l['pupil_id'] ? '' : 'Staff')) ?></td><td><?= e($l['borrower']) ?> <small><?= e($l['admission_no'] ?? '') ?></small></td><td><?= e($l['title']) ?></td>
            <td><?= e(fmt_date($l['issued_on'], 'd M')) ?></td><td><?= e(fmt_date($l['due_on'], 'd M')) ?></td><td style="text-align:center"><?= $late ?: '' ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$loans): ?><tr><td colspan="6">None.</td></tr><?php endif; ?>
    </table>
</div>
