<?php
/**
 * Prints a money document twice (e.g. Parent's copy + School copy) — two halves of one A4 sheet, or two full pages for
 * long documents ($docHalf = false). Needs $docType, $docBody (the document's HTML) and $doc (Docs::mark() result).
 */
$labels = Docs::TYPES[$docType][1] ?? ['Copy 1', 'Copy 2'];
$half = $docHalf ?? true;
$status = $doc ? ($doc['copy'] ? 'COPY — reprint ' . ($doc['n'] - 1) . ' · original printed ' . date('d M Y H:i', strtotime((string) $doc['first_at'])) . ($doc['first_by'] ? ' by ' . $doc['first_by'] : '') : 'ORIGINAL') : '';
$vurl = $doc ? Docs::verifyUrl($doc['token']) : '';
?>
<div class="doc-sheet<?= $half ? ' halves' : '' ?>">
<?php foreach ($labels as $ci => $label): ?>
    <div class="card-page doc-page doc-copy<?= $half ? ' doc-half' : '' ?>">
        <div class="doc-copy-band"><strong><?= e(mb_strtoupper($label)) ?></strong><?php if ($status): ?><span<?= $doc['copy'] ? ' class="is-copy"' : '' ?>><?= e($status) ?></span><?php endif; ?></div>
        <?= $docBody ?>
        <?php if ($doc): ?><div class="doc-verify"><div class="doc-qr" data-doc-qr="<?= e($vurl) ?>" aria-label="QR code to check this document"></div><small>Scan the code (or open <?= e(preg_replace('#^https?://#', '', absolute_url('verify'))) ?> and type <strong><?= e(strtoupper(substr($doc['token'], 0, 10))) ?></strong>) to check this document is genuine.</small></div><?php endif; ?>
    </div>
    <?php if ($half && $ci === 0): ?><div class="doc-cut" aria-hidden="true"><span>✂ cut here</span></div><?php endif; ?>
<?php endforeach; ?>
</div>
