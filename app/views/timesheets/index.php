<?php $tab = 'timesheets'; $label = date('F Y', strtotime($period . '-01')); $approved = count(array_filter($rows, fn($r) => ($r['appr']['status'] ?? '') === 'approved')); ?>
<?php require dirname(__DIR__) . '/attendance/_staff_tabs.php'; ?>
<div class="xgrid-box" data-xgrid="timesheets" data-xgrid-file="timesheets-<?= e($period) ?>">
<div class="page-head">
    <div><h1>Staff timesheets</h1></div>
    <div class="actions">
        <form method="get" action="<?= e(url('timesheets')) ?>" class="list-pick" data-autosubmit><input type="month" name="period" value="<?= e($period) ?>" max="<?= e(date('Y-m')) ?>" aria-label="Month"></form>
        <?= export_grid_menu() ?>
    </div>
</div>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['name' => ['Staff'], 'role' => ['Role'], 'pay' => ['Paid'], 'shift' => ['Shift'], 'days' => ['Days worked', 'num', 'num'], 'expected' => ['Days expected', 'num', 'num'], 'hours' => ['Hours', 'num', 'num'], 'late' => ['Late', 'num', 'num'], 'early' => ['Left early', 'num', 'num'], 'noout' => ['No sign-out', 'num', 'num'], 'absent' => ['Absent', 'num', 'num'], 'appr' => ['Approval']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $t = $r['tot']; $ok = ($r['appr']['status'] ?? '') === 'approved'; ?>
    <tr>
        <td data-c="name"><a href="<?= e(url('timesheets/' . $r['id'], ['period' => $period])) ?>"><strong><?= e($r['name']) ?></strong></a></td>
        <td data-c="role"><?= e(Auth::roleLabel($r['role'])) ?></td>
        <td data-c="pay"><?= $r['daily'] ? 'By the day' : 'Monthly' ?></td>
        <td data-c="shift"><?= e($r['shift']['name'] ?? 'School day') ?></td>
        <td data-c="days" class="num"><?= (int) $t['days'] ?></td>
        <td data-c="expected" class="num"><?= (int) $t['expected'] ?></td>
        <td data-c="hours" class="num" data-s="<?= e((string) $t['hours']) ?>"><?= e(number_format($t['hours'], 1)) ?></td>
        <td data-c="late" class="num" data-s="<?= (int) $t['late'] ?>"><?= $t['late'] ? badge((string) $t['late'], 'warn') : '0' ?></td>
        <td data-c="early" class="num"><?= (int) $t['early'] ?></td>
        <td data-c="noout" class="num" data-s="<?= (int) $t['no_out'] ?>"><?= $t['no_out'] ? badge((string) $t['no_out'], 'neutral') : '0' ?></td>
        <td data-c="absent" class="num" data-s="<?= (int) $t['absent'] ?>"><?= $t['absent'] ? badge((string) $t['absent'], 'bad') : '0' ?></td>
        <td data-c="appr"><?= $ok ? badge('Approved', 'good') : '<span class="muted">Not yet</span>' ?></td>
        <td class="xg-act"><?php if (!$ok && (int) $r['id'] !== Auth::id()): ?>
            <form method="post" action="<?= e(url('timesheets/' . $r['id'] . '/approve')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="period" value="<?= e($period) ?>"><input type="hidden" name="back" value="1"><button class="btn btn-sm" type="submit">Approve</button></form>
        <?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No staff yet.</p>
</div>
</div>
