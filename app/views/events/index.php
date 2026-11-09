<?php $tabs = ['upcoming' => ['Coming up', url('events')], 'all' => ['All', url('events', ['show' => 'all'])]]; ?>
<div class="xgrid-box" data-xgrid="events" data-xgrid-file="school-events">
<div class="page-head">
    <div><h1>Events</h1></div>
    <div class="actions"><?= export_grid_menu() ?><?php if ($manage): ?><a class="btn btn-primary" href="<?= e(url('events/create')) ?>">+ New event</a><?php endif; ?></div>
</div>
<?= card_tabs($tabs, $show === 'upcoming' ? 'upcoming' : 'all', 2, 'Events to show') ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['date' => ['Date', 'date'], 'time' => ['Time'], 'event' => ['Event'], 'venue' => ['Venue'], 'for' => ['For'], 'fee' => ['Fee', 'num', 'num'], 'places' => ['Registration'], 'status' => ['Status']]) ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $ev): [$sl, $st] = EventController::STATUS[$ev['status']]; $taken = (int) $ev['taken']; $time = substr($ev['start_at'], 11, 5); ?>
    <tr<?= $ev['status'] === 'cancelled' ? ' style="opacity:.55"' : '' ?>>
        <td data-c="date" data-s="<?= e($ev['start_at']) ?>" style="white-space:nowrap"><?= e(date('D j M Y', strtotime($ev['start_at']))) ?></td>
        <td data-c="time"><?= $time !== '00:00' ? e($time) : '' ?></td>
        <td data-c="event"><a href="<?= e(url('events/' . $ev['id'])) ?>"><strong><?= e($ev['title']) ?></strong></a></td>
        <td data-c="venue"><?= e((string) ($ev['venue'] ?? '')) ?></td>
        <td data-c="for"><?= e(EventController::AUDIENCE[$ev['audience']]) ?><?= $ev['level_name'] ? ': ' . e($ev['level_name']) : '' ?></td>
        <td data-c="fee" class="num" data-s="<?= (float) $ev['fee'] ?>"><?= (float) $ev['fee'] > 0 ? e(Fees::money($ev['fee'])) : 'Free' ?></td>
        <td data-c="places"><?= isset($mine[(int) $ev['id']]) ? badge('Registered', 'good') : ((int) $ev['registration'] ? ($ev['capacity'] ? $taken . ' / ' . (int) $ev['capacity'] . ' places taken' : $taken . ' registered') : '<span class="muted">Not needed</span>') ?></td>
        <td data-c="status"><?= badge($sl, $st) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No events <?= $show === 'upcoming' ? 'coming up' : 'yet' ?>.</p>
</div>
</div>
