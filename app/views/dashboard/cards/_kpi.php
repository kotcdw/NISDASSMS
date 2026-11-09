<?php
/* One headline figure. $d: label, value, sub, trend [dir, text, upIsGood]?, tone?, href?, meter? (0–100), alerts? */
$tone = $d['tone'] ?? null;
$tag = !empty($d['href']) ? 'a' : 'div';
?>
<<?= $tag ?> class="an-kpi<?= $tone ? ' is-' . e($tone) : '' ?>" data-card="<?= e($key) ?>"<?= $tag === 'a' ? ' href="' . e(url($d['href'])) . '"' : '' ?>>
    <span class="an-kpi-l"><?= e($d['label'] ?? '') ?></span>
    <?php if (!empty($d['empty'])): ?>
        <span class="an-kpi-v">—</span><span class="an-kpi-s"><?= e($d['empty']) ?></span>
    <?php else: ?>
        <span class="an-kpi-v"><?= e((string) $d['value']) ?></span>
        <?php if (isset($d['meter']) && $d['meter'] !== null): ?><span class="an-meter" role="img" aria-label="<?= (int) $d['meter'] ?>% collected"><span style="width:<?= max(0, min(100, (int) $d['meter'])) ?>%"></span></span><?php endif; ?>
        <span class="an-kpi-s"><?= e((string) $d['sub']) ?></span>
        <?php if (!empty($d['trend'])): [$dir, $txt, $upGood] = $d['trend']; $good = $dir === 0 ? null : (($dir > 0) === (bool) $upGood); ?>
        <span class="an-trend<?= $good === null ? '' : ($good ? ' up' : ' down') ?>"><?= $dir > 0 ? '▲' : ($dir < 0 ? '▼' : '●') ?> <?= e($txt) ?></span>
        <?php endif; ?>
    <?php endif; ?>
</<?= $tag ?>>
