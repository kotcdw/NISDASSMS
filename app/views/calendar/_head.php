<?php $r = current_route(); ?>
<div class="page-head">
    <div><h1>Academic Calendar</h1></div>
    <div class="actions">
        <nav class="seg" style="margin:0"><a<?= $r === 'calendar' ? ' class="active"' : '' ?> href="<?= e(url('calendar')) ?>">Month</a><a<?= $r === 'calendar/list' ? ' class="active"' : '' ?> href="<?= e(url('calendar/list')) ?>">List</a></nav>
        <a class="btn" href="<?= e(url('calendar/export.ics')) ?>" title="Import into Google Calendar or your phone">Export (.ics)</a>
        <?php if (can('calendar.manage')): ?><a class="btn btn-primary" href="<?= e(url('calendar/create')) ?>">+ New event</a><?php endif; ?>
    </div>
</div>
<div class="legend">
    <?php foreach (CalendarEvent::CATEGORIES as $k => [$label, $color]): ?>
        <a href="?<?= e(http_build_query(array_merge($_GET, ['category' => ($category ?? '') === $k ? '' : $k]))) ?>"><span style="--c:<?= e($color) ?>;<?= ($category ?? '') === $k ? 'font-weight:700' : '' ?>"><?= e($label) ?></span></a>
    <?php endforeach; ?>
</div>
