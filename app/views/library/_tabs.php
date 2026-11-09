<?php $r = current_route(); $tabs = ['library' => 'Lending', 'library/books' => 'Catalogue']; ?>
<nav class="seg" data-pagenav>
    <?php foreach ($tabs as $route => $label): ?>
        <a href="<?= e(url($route)) ?>"<?= ($r === $route || ($route === 'library/books' && strpos($r, 'library/books') === 0)) ? ' class="active"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>
