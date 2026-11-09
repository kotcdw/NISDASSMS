<?php
$hm = fn($t) => substr((string) $t, 0, 5);
$people = array_merge(array_map(fn($t) => $t + ['class' => '', 'busy' => false], $free), array_map(fn($t) => $t + ['busy' => true], $busyNow));
?>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="xgrid-box" data-xgrid="timetable-free" data-xgrid-file="find-a-substitute">
<div class="page-head">
    <div><h1>Find a substitute</h1></div>
    <div class="actions">
        <form method="get" action="<?= e(url('timetable/free')) ?>" class="list-pick" data-autosubmit>
            <select name="day" aria-label="Day"><?php foreach (Timetable::DAYS as $d => $dn): ?><option value="<?= $d ?>"<?= selected($d, $day) ?>><?= e($dn) ?></option><?php endforeach; ?></select>
            <select name="period" aria-label="Period"><?php foreach ($periods as $p): ?><option value="<?= (int) $p['id'] ?>"<?= selected($p['id'], $period['id'] ?? 0) ?>><?= e($p['label'] . ' (' . $hm($p['start_time']) . '–' . $hm($p['end_time']) . ')') ?></option><?php endforeach; ?></select>
        </form>
        <?= export_grid_menu() ?>
    </div>
</div>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['teacher' => ['Teacher'], 'role' => ['Role'], 'now' => ['At this time'], 'class' => ['Teaching class'], 'today' => ['Lessons that day', 'num', 'num']]) ?></tr></thead>
    <tbody>
    <?php foreach ($people as $t): ?>
    <tr>
        <td data-c="teacher"><a href="<?= e(url('timetable/teacher/' . $t['id'])) ?>"><strong><?= e($t['name']) ?></strong></a></td>
        <td data-c="role"><?= e(Auth::roleLabel($t['role'])) ?></td>
        <td data-c="now"><?= !$t['busy'] ? badge('Free', 'good') : badge('Teaching', 'neutral') ?></td>
        <td data-c="class"><?= e((string) ($t['class'] ?? '')) ?></td>
        <td data-c="today" class="num"><?= (int) $t['today'] ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $people ? ' hidden' : '' ?> data-xg-empty>No teachers found for this time.</p>
</div>
</div>
