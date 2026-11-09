<?php
$r = current_route();
$tabs = ['timetable' => 'Classes', 'timetable/my' => 'My time table', 'timetable/free' => 'Find a substitute'];
if (can('timetable.manage')) $tabs += ['timetable/lessons' => 'Lessons per week', 'timetable/periods' => 'Bell schedule', 'timetable/check' => 'Check'];
?>
<nav class="seg" data-pagenav>
    <?php foreach ($tabs as $route => $label): ?>
        <a href="<?= e(url($route)) ?>"<?= $r === $route ? ' class="active"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>
