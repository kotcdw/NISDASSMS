<?php
$tab = 'summary';
$days = $data['days'];
$rows = $data['rows'];
$q = fn(array $x) => array_merge(['p' => $p, 'from' => $from, 'to' => $to], $x);
$tot = count($rows) * count($days);
$present = array_sum(array_column($rows, 'present'));
$lateN = array_sum(array_column($rows, 'late'));
?>
<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('attendance/staff')) ?>">Staff attendance</a> /</div>
    <h1>Staff attendance summary</h1></div>
    <div class="actions">
        <button class="btn btn-ghost" type="button" data-print>Print</button>
        <?= export_grid_menu('Export', 'staff-summary') ?>
        <details class="menu-btn"><summary class="btn">More ▾</summary><div>
            <?= export_links(url('attendance/staff/summary/export', $q([])), 'With every day —') ?>
        </div></details>
    </div>
</div>
<?php require __DIR__ . '/_staff_tabs.php'; ?>
<div class="xgrid-box" data-xgrid="staff-summary" data-xgrid-file="staff-attendance-<?= e($from) ?>-to-<?= e($to) ?>">
<?php $ptabs = []; foreach (['week' => 'This week', 'month' => 'This month', 'term' => 'This term'] as $k => $l) { $ptabs[$k] = [$l, url('attendance/staff/summary', ['p' => $k])]; } ?>
<?= card_tabs($ptabs, (string) $p, 5, 'Dates to show') ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['name' => ['Name'], 'role' => ['Role'], 'present' => ['Present', 'num', 'num'], 'leave' => ['On leave', 'num', 'num'], 'absent' => ['Absent', 'num', 'num'], 'late' => ['Late', 'num', 'num'], 'pct' => ['Attendance', 'num'], 'in' => ['Usual sign-in'], 'noout' => ['No sign-out', 'num', 'num']]) ?></tr></thead>
    <tbody>
    <?php foreach ($days ? $rows : [] as $r): ?>
        <tr>
            <td data-c="name"><?php if (can('teachers.view')): ?><a href="<?= e(url('teachers/' . $r['id'])) ?>"><?= e($r['name']) ?></a><?php else: ?><?= e($r['name']) ?><?php endif; ?></td>
            <td data-c="role"><?= e(Auth::roleLabel($r['role'])) ?></td>
            <td data-c="present" class="num"><?= (int) $r['present'] ?></td>
            <td data-c="leave" class="num"><?= (int) ($r['leave'] ?? 0) ?></td>
            <td data-c="absent" class="num" data-s="<?= (int) $r['absent'] ?>"><?= $r['absent'] ? badge((string) $r['absent'], $r['absent'] > 2 ? 'bad' : 'warn') : '0' ?></td>
            <td data-c="late" class="num" data-s="<?= (int) $r['late'] ?>"><?= $r['late'] ? badge((string) $r['late'], 'warn') : '0' ?></td>
            <td data-c="pct" data-s="<?= e((string) ($r['pct'] ?? '')) ?>"><?= Attendance::meter($r['pct']) ?></td>
            <td data-c="in"><?= e($r['avg_in'] ?? '—') ?><?= $r['avg_in'] && $r['avg_in'] > $lateAfter ? '<span hidden> · </span> ' . badge('late on average', 'warn') : '' ?></td>
            <td data-c="noout" class="num"><?= (int) $r['no_out'] ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $days && $rows ? ' hidden' : '' ?> data-xg-empty><?= $days ? 'No staff to show.' : 'No school days in this period (weekends, holidays and vacations are left out).' ?></p>
</div>
</div>

<?php if ($days): ?>
<h2>Day by day</h2>
<p class="muted" style="font-size:.85rem">P = present · L = late · V = on approved leave · A = no sign-in</p>
<div class="card" style="padding:0"><div class="table-wrap"><table class="att-grid">
    <thead><tr><th>Name</th><?php foreach ($days as $d): ?><th title="<?= e(date('D j M', strtotime($d))) ?>"><?= e(date('j', strtotime($d))) ?><br><small><?= e(substr(date('D', strtotime($d)), 0, 2)) ?></small></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr><td style="white-space:nowrap"><?= e($r['name']) ?></td><?php foreach ($r['grid'] as $d => $m): ?><td class="sa-<?= strtolower($m) ?>" title="<?= e(date('D j M', strtotime($d))) ?>"><?= $m ?></td><?php endforeach; ?></tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div>
<?php endif; ?>
