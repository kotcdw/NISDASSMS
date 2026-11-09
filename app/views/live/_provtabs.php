<?php $base = $base ?? 'live'; $extra = $extra ?? []; ?>
<nav class="seg" data-pagenav>
    <a<?= $prov === '' ? ' class="active"' : '' ?> href="<?= e(url($base, $extra)) ?>">All</a>
    <?php foreach (['zoom', 'meet', 'teams'] as $k): ?><a<?= $prov === $k ? ' class="active"' : '' ?> href="<?= e(url($base, $extra + ['provider' => $k])) ?>" style="display:inline-flex;gap:.35rem;align-items:center"><?= Engine::icon($k, 20) ?> <?= e(Learning::PROVIDERS[$k]) ?></a><?php endforeach; ?>
</nav>
