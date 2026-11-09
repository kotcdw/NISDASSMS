<?php $r = current_route(); $tabs = ['setup' => 'School details', 'setup/years' => 'Years & terms', 'setup/classes' => 'Classes', 'setup/privacy' => 'Data protection', 'setup/privacy/requests' => 'Data requests', 'setup/columns' => 'List columns', 'setup/modules' => 'Modules on/off']; ?>
<nav class="seg" data-pagenav aria-label="School setup">
    <?php foreach ($tabs as $route => $label): ?><a href="<?= e(url($route)) ?>"<?= $r === $route ? ' class="active"' : '' ?>><?= e($label) ?></a><?php endforeach; ?>
</nav>
