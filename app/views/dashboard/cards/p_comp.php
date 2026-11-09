<?php
/* Compliance & welfare: status rows, each linking to its register. */
$title = 'Compliance & welfare';
$href = null;
$widget = null;
$icons = ['good' => '✓', 'warn' => '!', 'bad' => '✕'];
ob_start(); ?>
    <ul class="an-status">
        <?php foreach ($d['rows'] as [$label, $value, $tone, $link, $detail]): ?>
        <?php $open = can_open(url($link)); ?>
        <li class="is-<?= e($tone) ?>"><<?= $open ? 'a href="' . e(url($link)) . '"' : 'div' ?> class="an-srow">
            <span class="an-dot" aria-hidden="true"><?= $icons[$tone] ?? '' ?></span>
            <span class="an-sl"><?= e($label) ?><small><?= e($detail) ?></small></span>
            <span class="an-sv"><?= e($value) ?><span class="sr-only"> (<?= $tone === 'good' ? 'fine' : ($tone === 'warn' ? 'check soon' : 'needs action') ?>)</span></span>
        </<?= $open ? 'a' : 'div' ?>></li>
        <?php endforeach; ?>
    </ul>
<?php $body = ob_get_clean();
$note = null;
require __DIR__ . '/_panel.php';
