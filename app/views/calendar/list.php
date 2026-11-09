<?php
$termQ = ['term' => (int) ($term['id'] ?? 0)];
$catTabs = ['' => ['All', url('calendar/list', $termQ)]];
foreach (CalendarEvent::CATEGORIES as $k => [$label]) {
    $catTabs[$k] = [$label, url('calendar/list', $termQ + ['category' => $k])];
}
$hm = fn($t) => substr((string) $t, 0, 5);
?>
<div class="xgrid-box" data-xgrid="calendar-list" data-xgrid-file="school-calendar">
<div class="page-head">
    <div><h1>Academic Calendar</h1></div>
    <div class="actions">
        <form method="get" action="<?= e(url('calendar/list')) ?>" class="list-pick" data-autosubmit>
            <?php if ($category): ?><input type="hidden" name="category" value="<?= e($category) ?>"><?php endif; ?>
            <select name="term" aria-label="Term">
                <option value="0">Upcoming (all)</option>
                <?php foreach ($terms as $t): ?><option value="<?= (int) $t['id'] ?>"<?= selected($t['id'], $term['id'] ?? 0) ?>><?= e($t['year_name'] . ' · ' . $t['name']) ?></option><?php endforeach; ?>
            </select>
        </form>
        <a class="btn btn-ghost" href="<?= e(url('calendar')) ?>">Month view</a>
        <a class="btn btn-ghost" href="<?= e(url('calendar/export.ics')) ?>" title="Import into Google Calendar or your phone">Add to phone (.ics)</a>
        <?= export_grid_menu() ?>
        <?php if (can('calendar.manage')): ?><a class="btn btn-primary" href="<?= e(url('calendar/create')) ?>">+ New event</a><?php endif; ?>
    </div>
</div>
<?= card_tabs($catTabs, (string) ($category ?? ''), 5, 'Kind of event to show') ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['date' => ['Date', 'date'], 'to' => ['Until', 'date'], 'time' => ['Time'], 'event' => ['Event'], 'type' => ['Type'], 'for' => ['For']]) ?></tr></thead>
    <tbody>
    <?php foreach ($events as $ev): ?>
    <tr>
        <td data-c="date" data-s="<?= e($ev['start_date']) ?>" style="white-space:nowrap"><?= e(fmt_date($ev['start_date'], 'D j M Y')) ?></td>
        <td data-c="to" data-s="<?= $ev['end_date'] !== $ev['start_date'] ? e($ev['end_date']) : '' ?>" style="white-space:nowrap"><?= $ev['end_date'] !== $ev['start_date'] ? e(fmt_date($ev['end_date'], 'D j M Y')) : '' ?></td>
        <td data-c="time" style="white-space:nowrap"><?= $ev['start_time'] ? e($hm($ev['start_time'])) . ($ev['end_time'] ? '–' . e($hm($ev['end_time'])) : '') : '' ?></td>
        <td data-c="event"><a href="<?= e(url('calendar/' . $ev['id'])) ?>"><strong><?= e($ev['title']) ?></strong></a><?= $ev['is_school_closed'] ? '<span hidden> · </span> ' . badge('School closed', 'bad') : '' ?></td>
        <td data-c="type"><span class="badge" style="background:<?= e(CalendarEvent::CATEGORIES[$ev['category']][1] ?? '#475467') ?>;color:#fff"><?= e(CalendarEvent::label($ev['category'])) ?></span></td>
        <td data-c="for"><?= e($ev['audience'] === 'level' ? ($ev['level_name'] ?? '') : ($ev['audience'] === 'class' ? ($ev['class_name'] ?? '') : CalendarEvent::AUDIENCES[$ev['audience']])) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $events ? ' hidden' : '' ?> data-xg-empty>No events.</p>
</div>
</div>
