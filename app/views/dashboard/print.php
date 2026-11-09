<?php $docSub = ($term['year_name'] ?? '') . ' · ' . ($term['name'] ?? ''); ?>
<div class="card-page doc-page">
    <?php require dirname(__DIR__) . '/fees/_doc_head.php'; ?>
    <?php if (!empty($w['empty'])): ?><p><?= e($w['empty']) ?></p><?php endif; ?>
    <?php if (!empty($w['note'])): ?><p style="font-size:.9rem"><?= e($w['note']) ?></p><?php endif; ?>
    <div class="print-tables">
        <table>
            <tr><?php foreach ($w['cols'] ?? [] as $c): ?><th><?= e($c) ?></th><?php endforeach; ?></tr>
            <?php foreach ($w['rows'] ?? [] as $r): ?><tr><?php foreach ($r as $v): ?><td><?= e($v === null ? '—' : (string) $v) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
            <?php if (empty($w['rows'])): ?><tr><td colspan="<?= max(1, count($w['cols'] ?? [])) ?>">Nothing to show.</td></tr><?php endif; ?>
        </table>
    </div>
    <p style="font-size:.8rem;color:#555">Printed <?= e(date('d M Y H:i')) ?> by <?= e(auth()['name']) ?></p>
</div>
