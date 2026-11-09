<?php $r = current_route(); ?>
<nav class="seg" data-pagenav aria-label="Roles and security">
    <?php foreach (SecurityController::TABS as $route => $label): ?><a href="<?= e(url($route)) ?>"<?= $r === $route || ($route === 'security' && $r === 'security/role') ? ' class="active"' : '' ?>><?= e($label) ?></a><?php endforeach; ?>
</nav>
