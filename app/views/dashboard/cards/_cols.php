<?php
/* Year columns with the value on top (up to five years; the latest is the solid one). Expects $cols = [[label, value, display, href?], …], optional $colsLabel. */
$cmax = max(1, ...array_map(fn($c) => (float) $c[1], $cols));
?>
<div class="an-cols" role="img" aria-label="<?= e($colsLabel ?? 'By year') ?>: <?= e(implode(', ', array_map(fn($c) => $c[0] . ' ' . $c[2], $cols))) ?>">
    <?php foreach ($cols as $i => $c): $last = $i === count($cols) - 1; ?>
    <div class="an-col<?= $last ? ' is-now' : '' ?>" title="<?= e($c[0] . ': ' . $c[2]) ?>">
        <span class="an-col-v"><?= e($c[2]) ?></span>
        <span class="an-col-b"><span style="height:<?= max(2, round((float) $c[1] * 100 / $cmax)) ?>%"></span></span>
        <span class="an-col-l"><?= e($c[0]) ?></span>
    </div>
    <?php endforeach; ?>
</div>
