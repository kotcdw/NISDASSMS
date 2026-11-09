<nav class="seg" data-pagenav>
    <a<?= ($tab ?? '') === 'day' ? ' class="active"' : '' ?> href="<?= e(url('attendance/staff')) ?>">One day</a>
    <a<?= ($tab ?? '') === 'summary' ? ' class="active"' : '' ?> href="<?= e(url('attendance/staff/summary', ['p' => 'month'])) ?>">Summary &amp; export</a>
    <?php if (can('timesheets.manage')): ?>
    <a<?= ($tab ?? '') === 'timesheets' ? ' class="active"' : '' ?> href="<?= e(url('timesheets')) ?>">Monthly timesheets</a>
    <a<?= ($tab ?? '') === 'shifts' ? ' class="active"' : '' ?> href="<?= e(url('timesheets/shifts')) ?>">Shifts</a>
    <?php endif; ?>
</nav>
