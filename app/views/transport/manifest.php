<?php $docTitle = 'BUS LIST'; $docSub = $r['name']; ?>
<div class="card-page doc-page">
    <?php require dirname(__DIR__) . '/fees/_doc_head.php'; ?>
    <p style="font-size:.9rem">Bus: <?= e($r['vehicle'] ?? '—') ?> · Driver: <?= e($r['driver_name'] ?? '—') ?> <?= e($r['driver_phone'] ?? '') ?> · Attendant: <?= e($r['attendant_name'] ?? '—') ?> <?= e($r['attendant_phone'] ?? '') ?></p>
    <table class="lines" style="width:100%">
        <tr><th>#</th><th style="text-align:left">Stop</th><th style="text-align:left">Student</th><th style="text-align:left">Class</th><th style="text-align:left">Trips</th><th style="text-align:left">Parent / phone</th><th>AM</th><th>PM</th></tr>
        <?php foreach ($riders as $i => $p): ?>
        <tr><td><?= $i + 1 ?></td><td><?= e($p['stop'] ?? '') ?></td><td><?= e($p['first_name'] . ' ' . $p['last_name']) ?></td><td><?= e($p['class_name'] ?? '') ?></td>
            <td><?= e($p['trips'] === 'both' ? 'Both' : ucfirst($p['trips'])) ?></td><td><?= e($p['guardian_name'] ?? '') ?> <?= e($p['guardian_phone'] ?? '') ?></td><td style="width:2.2rem;border:1px solid #999"></td><td style="width:2.2rem;border:1px solid #999"></td></tr>
        <?php endforeach; ?>
        <?php if (!$riders): ?><tr><td colspan="8">No riders.</td></tr><?php endif; ?>
    </table>
    <p style="font-size:.8rem;color:#555">Tick AM/PM as each child boards. Never release a child at a stop to anyone other than the parent or a person they have named.</p>
</div>
