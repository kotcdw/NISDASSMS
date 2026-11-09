<?php
$show = array_flip(ListColumns::on('leave'));
$tabs = [];
foreach (['pending' => 'Waiting', 'approved' => 'Approved', 'rejected' => 'Not approved', 'all' => 'All'] as $k => $l) { $tabs[$k] = [$l, url('leave', ['status' => $k])]; }
?>
<div class="xgrid-box" data-xgrid="leave" data-xgrid-file="student-leave">
<div class="page-head">
    <div><h1>Student leave</h1></div>
    <div class="actions"><?= export_grid_menu() ?><a class="btn btn-primary" href="<?= e(url('leave/create')) ?>">+ Record leave</a></div>
</div>
<?= card_tabs($tabs, $status, 4, 'Leave to show') ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['student' => ['Student'], 'adm' => ['Admission no.'], 'class' => ['Class'], 'from' => ['From', 'date'], 'to' => ['To', 'date'], 'days' => ['Days', 'num', 'num'], 'kind' => ['Kind'], 'reason' => ['Reason'], 'by' => ['Asked by'], 'status' => ['Status']], $show) ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): [$sl, $st] = LeaveController::STATUS[$r['status']]; $days = (int) ((strtotime($r['to_date']) - strtotime($r['from_date'])) / 86400) + 1; ?>
    <tr>
        <td data-c="student"><a href="<?= e(url('leave/' . $r['id'])) ?>"><strong><?= e($r['last_name'] . ', ' . $r['first_name']) ?></strong></a></td>
        <?php if (isset($show['adm'])): ?><td data-c="adm"><?= e($r['admission_no']) ?></td><?php endif; ?>
        <?php if (isset($show['class'])): ?><td data-c="class"><?= e($r['class_name'] ?? '') ?></td><?php endif; ?>
        <?php if (isset($show['from'])): ?><td data-c="from" data-s="<?= e($r['from_date']) ?>"><?= e(fmt_date($r['from_date'], 'D j M')) ?></td><?php endif; ?>
        <?php if (isset($show['to'])): ?><td data-c="to" data-s="<?= e($r['to_date']) ?>"><?= e(fmt_date($r['to_date'], 'D j M')) ?></td><?php endif; ?>
        <?php if (isset($show['days'])): ?><td data-c="days" class="num"><?= $days ?></td><?php endif; ?>
        <?php if (isset($show['kind'])): ?><td data-c="kind"><?= e(LeaveController::KINDS[$r['kind']]) ?></td><?php endif; ?>
        <?php if (isset($show['reason'])): ?><td data-c="reason"><?= e(mb_strimwidth($r['reason'], 0, 70, '…')) ?><?= $r['attachment'] ? ' 📎' : '' ?></td><?php endif; ?>
        <?php if (isset($show['by'])): ?><td data-c="by"><?= e($r['requested_name'] ?? '') ?></td><?php endif; ?>
        <?php if (isset($show['status'])): ?><td data-c="status"><?= badge($sl, $st) ?></td><?php endif; ?>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>Nothing here.</p>
</div>
</div>
<div class="list-pager"><?= pager_links($pager) ?></div>
