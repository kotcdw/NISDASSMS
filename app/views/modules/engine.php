<?php /** NISDAS APPS: the picture tiles, then every other app this person may open, in one grid. $tiles = key => [label, href, icon]. */ ?>
<div class="eng-board">
    <h1 class="sr-only">NISDAS APPS</h1>
    <div class="eng-board-grid">
        <?php foreach ($tiles as $k => [$label, $href, $icon]): ?>
        <a class="eng-cell" href="<?= e($href) ?>" data-engine="<?= e($k) ?>">
            <span class="eng-cell-ico"><?= Engine::icon($icon, 72) ?></span>
            <span class="eng-cell-label"><?= e($label) ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</div>
