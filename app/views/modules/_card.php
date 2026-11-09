<?php /** One module card: $k (key), $m (module row with 'route'). */ ?>
<div class="mod-card">
    <a class="mod-cover" href="<?= e(url($m['route'])) ?>" aria-label="Open <?= e($m[1]) ?>"></a>
    <div class="mod-top"><?= Modules::icon($m[3], $m[4]) ?><span class="mod-chev" aria-hidden="true">›</span></div>
    <h3><?= e($m[1]) ?></h3>
    <p><?= e($m[2]) ?></p>
    <a class="mod-more" href="<?= e(url('modules/' . $k)) ?>">Learn more →</a>
</div>
