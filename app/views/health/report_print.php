<?php $docTitle = strtoupper($title); $docSub = date('d M Y'); ?>
<div class="card-page doc-page">
    <?php require dirname(__DIR__) . '/fees/_doc_head.php'; ?>
    <table class="lines" style="width:100%">
        <?php if ($kind === 'nhis'): ?>
        <tr><th style="text-align:left">Class</th><th style="text-align:left">Student</th><th style="text-align:left">NHIS no.</th><th style="text-align:left">Expiry</th></tr>
        <?php foreach ($rows as $r): ?><tr><td><?= e($r['class_name']) ?></td><td><?= e($r['first_name'] . ' ' . $r['last_name']) ?></td><td><?= e($r['nhis_no'] ?: '—') ?></td><td><?= $r['nhis_expiry'] ? e(fmt_date($r['nhis_expiry'])) : 'Not recorded' ?></td></tr><?php endforeach; ?>
        <?php else: ?>
        <tr><th style="text-align:left">Class</th><th style="text-align:left">Student</th><th style="text-align:left">Allergies</th><th style="text-align:left">Conditions</th><th style="text-align:left">Medication</th><th style="text-align:left">Emergency contact</th></tr>
        <?php foreach ($rows as $r): ?><tr><td><?= e($r['class_name']) ?></td><td><?= e($r['first_name'] . ' ' . $r['last_name']) ?></td><td><?= e($r['allergies'] ?? '') ?></td><td><?= e(trim(($r['conditions'] ?? '') . (in_array($r['genotype'], ['SS', 'SC'], true) ? ' (' . $r['genotype'] . ')' : ''))) ?></td><td><?= e($r['medication'] ?? '') ?></td><td><?= e(trim(($r['emergency_name'] ?? '') . ' ' . ($r['emergency_phone'] ?? ''))) ?></td></tr><?php endforeach; ?>
        <?php endif; ?>
        <?php if (!$rows): ?><tr><td colspan="6">Nobody on this list.</td></tr><?php endif; ?>
    </table>
    <p style="font-size:.8rem;color:#555">Confidential — for staff use only.</p>
</div>
