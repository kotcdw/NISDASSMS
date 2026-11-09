<?php
$show = ($_GET['show'] ?? 'active') === 'all' ? 'all' : 'active';
$staffTabs = ['active' => ['Working here now', url('payroll/staff')], 'all' => ['Everyone (with those who left)', url('payroll/staff', ['show' => 'all'])]];
?>
<div class="xgrid-box" data-xgrid="staff-pay" data-xgrid-file="staff-records">
<div class="page-head">
    <div><h1>Staff records</h1></div>
    <div class="actions"><?= export_grid_menu() ?><?php if (can('payroll.manage')): ?><a class="btn btn-primary" href="<?= e(url('payroll/staff/create')) ?>">+ Add staff member</a><?php endif; ?></div>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>
<?= card_tabs($staffTabs, $show, 4, 'Staff to show') ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['no' => ['Staff no.'], 'name' => ['Name'], 'position' => ['Position'], 'cat' => ['Category'], 'emp' => ['Employment'], 'pay' => ['Paid by'], 'basic' => ['Basic (GH₵)', 'num', 'num'], 'allow' => ['Allowances (GH₵)', 'num', 'num'], 'ntc' => ['NTC licence'], 'login' => ['Login']]) ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $s): ?>
    <tr<?= (int) $s['is_active'] ? '' : ' class="muted"' ?>>
        <td data-c="no"><?= e($s['staff_no']) ?></td>
        <td data-c="name"><?php if (can('payroll.manage')): ?><a href="<?= e(url('payroll/staff/' . $s['id'])) ?>"><strong><?= e($s['name']) ?></strong></a><?php else: ?><strong><?= e($s['name']) ?></strong><?php endif; ?></td>
        <td data-c="position"><?= e($s['position']) ?></td>
        <td data-c="cat"><?= e(Payroll::CATEGORY[$s['category']]) ?></td>
        <td data-c="emp"><?= e(Payroll::EMPLOYMENT[$s['employment']]) ?><?= (int) $s['is_active'] ? '' : '<span hidden> · </span> ' . badge('Left') ?></td>
        <td data-c="pay"><?= e(Payroll::PAY_METHOD[$s['pay_method']]) ?></td>
        <td data-c="basic" class="num" data-s="<?= (float) $s['basic_salary'] ?>"><?= e(number_format((float) $s['basic_salary'], 2)) ?></td>
        <td data-c="allow" class="num" data-s="<?= (float) $s['allow'] ?>"><?= e(number_format((float) $s['allow'], 2)) ?></td>
        <td data-c="ntc"><?= Payroll::ntcBadge($s) ?></td>
        <td data-c="login"><?= e($s['email'] ?? '') ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No staff records yet.</p>
</div>
</div>
