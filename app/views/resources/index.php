<?php
$totalShort = array_sum(array_column($shortages, 'short'));
$view = ($_GET['show'] ?? '') === 'short' ? 'short' : 'rooms';
$tabs = ['rooms' => ['Rooms', url('resources')], 'short' => ['Shortages' . ($shortages ? ' (' . count($shortages) . ')' : ''), url('resources', ['show' => 'short'])]];
?>
<div class="xgrid-box" data-xgrid="<?= $view === 'short' ? 'resource-shortages' : 'rooms' ?>" data-xgrid-file="<?= $view === 'short' ? 'room-shortages' : 'rooms' ?>">
<div class="page-head">
    <div><h1>Rooms &amp; resources</h1></div>
    <div class="actions">
        <a class="btn btn-ghost" href="<?= e(url('resources/print')) ?>" target="_blank" rel="noopener">Print shortages</a>
        <?php if (can('resources.manage') && $classesWithout): ?>
        <form method="post" action="<?= e(url('resources/rooms/for-classes')) ?>" class="inline"><?= csrf_field() ?><button class="btn btn-ghost" type="submit">Add a classroom for each class (<?= (int) $classesWithout ?>)</button></form>
        <?php endif; ?>
        <?= export_grid_menu() ?>
        <?php if (can('resources.manage')): ?><button type="button" class="btn btn-primary" data-help="new-room" aria-expanded="false">+ Add a room</button><?php endif; ?>
    </div>
</div>
<?= card_tabs($tabs, $view, 2, 'List to show') ?>
<?php if (can('resources.manage')): ?>
<div id="new-room" class="card"<?= old('name') !== '' ? '' : ' hidden' ?>>
    <h2>Add a room</h2>
    <form method="post" action="<?= e(url('resources/rooms')) ?>" class="form">
        <?= csrf_field() ?>
        <div class="grid-4">
            <label class="required">Name<input name="name" maxlength="60" value="<?= e(old('name')) ?>" placeholder="e.g. Science lab" required></label>
            <label>Kind<select name="kind"><?php foreach (Resources::KINDS as $k => $l): ?><option value="<?= $k ?>"<?= selected($k, old('kind', 'classroom')) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
            <label>Class using it<select name="class_id"><option value="">— none —</option><?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected($c['id'], old('class_id')) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></label>
            <label>Seats (capacity)<input type="number" name="capacity" min="0" max="2000" value="<?= e(old('capacity', '40')) ?>"></label>
        </div>
        <div class="gate-actions"><button class="btn btn-primary" type="submit">Save</button><button type="button" class="btn btn-ghost" data-help="new-room">Cancel</button></div>
    </form>
</div>
<?php endif; ?>
<?php if ($view === 'short'): ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['room' => ['Room'], 'item' => ['Item'], 'roll' => ['Students', 'num', 'num'], 'need' => ['Needed', 'num', 'num'], 'good' => ['Good', 'num', 'num'], 'short' => ['Short', 'num', 'num']]) ?></tr></thead>
    <tbody>
    <?php foreach ($shortages as $s): ?>
    <tr>
        <td data-c="room"><a href="<?= e(url('resources/rooms/' . $s['room_id'])) ?>"><?= e($s['room_name']) ?></a></td>
        <td data-c="item"><?= e($s['name']) ?></td>
        <td data-c="roll" class="num"><?= $s['class_id'] ? (int) $s['roll'] : '—' ?></td>
        <td data-c="need" class="num"><?= (int) $s['need'] ?></td>
        <td data-c="good" class="num"><?= (int) $s['qty_good'] ?></td>
        <td data-c="short" class="num" data-s="<?= (int) $s['short'] ?>"><?= badge((string) $s['short'], 'bad') ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $shortages ? ' hidden' : '' ?> data-xg-empty>No shortages. Every counted room has enough.</p>
</div>
<?php else: ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['room' => ['Room'], 'kind' => ['Kind'], 'class' => ['Class'], 'cap' => ['Capacity', 'num', 'num'], 'roll' => ['Students', 'num', 'num'], 'desks' => ['Desks (good)', 'num', 'num'], 'items' => ['Items', 'num', 'num'], 'short' => ['Shortfall'], 'repair' => ['Broken / repair', 'num', 'num']]) ?></tr></thead>
    <tbody>
    <?php foreach ($rooms as $r): ?>
    <tr<?= (int) $r['is_active'] ? '' : ' class="muted"' ?>>
        <td data-c="room"><a href="<?= e(url('resources/rooms/' . $r['id'])) ?>"><strong><?= e($r['name']) ?></strong></a><?= (int) $r['is_active'] ? '' : '<span hidden> · </span> ' . badge('Not in use', 'neutral') ?></td>
        <td data-c="kind"><?= e(Resources::KINDS[$r['kind']] ?? $r['kind']) ?></td>
        <td data-c="class"><?= e($r['class_name'] ?? '—') ?></td>
        <td data-c="cap" class="num"><?= (int) $r['capacity'] ?></td>
        <td data-c="roll" class="num" data-s="<?= $r['roll'] === null ? '' : (int) $r['roll'] ?>"><?= $r['roll'] === null ? '—' : ((int) $r['roll'] > (int) $r['capacity'] && $r['capacity'] ? badge((string) $r['roll'], 'warn') : (int) $r['roll']) ?></td>
        <td data-c="desks" class="num" data-s="<?= $r['desks'] === null ? '' : (int) $r['desks'] ?>"><?= $r['desks'] === null ? '—' : (int) $r['desks'] ?></td>
        <td data-c="items" class="num"><?= (int) $r['items'] ?></td>
        <td data-c="short"><?= $r['short'] ? badge($r['short'] . ' short', 'bad') : ((int) $r['items'] ? badge('OK', 'good') : '<span class="muted">Not counted</span>') ?></td>
        <td data-c="repair" class="num" data-s="<?= (int) $r['repair'] ?>"><?= $r['repair'] ? badge((string) $r['repair'], 'warn') : '0' ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rooms ? ' hidden' : '' ?> data-xg-empty>No rooms yet.</p>
</div>
<?php endif; ?>
</div>
