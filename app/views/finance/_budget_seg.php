<?php $bsR = current_route(); ?>
<nav class="seg seg-sub" aria-label="Budget">
    <?php foreach (['finance/budget' => 'By category', 'finance/budget/departments' => 'By department (caps)', 'finance/budget/reserves' => 'Reserves'] as $route => $label): ?><a href="<?= e(url($route)) ?>"<?= $bsR === $route ? ' class="active"' : '' ?>><?= e($label) ?></a><?php endforeach; ?>
</nav>
