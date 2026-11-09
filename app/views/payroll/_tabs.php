<?php
$r = current_route();
$tabs = ['payroll' => 'Payroll engine', 'payroll/staff' => 'Staff records', 'payroll/annual' => 'Year summary'];
?>
<nav class="seg" data-pagenav>
    <?php foreach ($tabs as $route => $label): ?>
        <a href="<?= e(url($route)) ?>"<?= ($r === $route || ($route === 'payroll/staff' && strpos($r, 'payroll/staff') === 0)) ? ' class="active"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>
