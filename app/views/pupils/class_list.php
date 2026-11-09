<?php $docTitle = 'CLASS LIST'; $docSub = $class['name'] . ' · ' . (current_year()['name'] ?? ''); $b = count(array_filter($pupils, fn($p) => $p['gender'] === 'male')); ?>
<div class="card-page doc-page">
    <?php require dirname(__DIR__) . '/fees/_doc_head.php'; ?>
    <p style="font-size:.9rem">Class teacher: <strong><?= e($class['teacher'] ?? '—') ?></strong> · <?= count($pupils) ?> students (<?= $b ?> boys, <?= count($pupils) - $b ?> girls)</p>
    <table class="lines" style="width:100%;font-size:.8rem">
        <tr><th>#</th><th style="text-align:left">Name</th><th style="text-align:left">Adm. no.</th><th>Sex</th><th>Date of birth</th><th>Age</th>
            <?php if ($blank): for ($i = 0; $i < $blank; $i++): ?><th style="width:2.2rem"></th><?php endfor; else: ?><th style="text-align:left">Parent / guardian</th><th style="text-align:left">Phone</th><?php endif; ?></tr>
        <?php foreach ($pupils as $i => $p): ?>
        <tr><td><?= $i + 1 ?></td><td><?= e(strtoupper($p['last_name']) . ' ' . $p['first_name'] . ($p['middle_name'] ? ' ' . $p['middle_name'] : '')) ?></td><td><?= e($p['admission_no']) ?></td>
            <td style="text-align:center"><?= $p['gender'] === 'male' ? 'M' : 'F' ?></td><td style="text-align:center"><?= e(date('d/m/Y', strtotime($p['date_of_birth']))) ?></td><td style="text-align:center"><?= e(age_from($p['date_of_birth'])) ?></td>
            <?php if ($blank): for ($j = 0; $j < $blank; $j++): ?><td></td><?php endfor; else: ?><td><?= e($p['guardian_name'] ?? '') ?></td><td><?= e($p['guardian_phone'] ?? '') ?></td><?php endif; ?></tr>
        <?php endforeach; ?>
        <?php if (!$pupils): ?><tr><td colspan="8">No students in this class.</td></tr><?php endif; ?>
    </table>
</div>
