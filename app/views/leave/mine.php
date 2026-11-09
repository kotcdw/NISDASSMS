<div class="page-head">
    <div><h1>Leave requests</h1></div>
    <div class="actions"><a class="btn btn-primary" href="<?= e(url('leave/create')) ?>">+ Ask for leave</a></div>
</div>
<div class="card" style="padding:0"><div class="table-wrap"><table>
    <tr><th>Child</th><th>Days</th><th>Reason</th><th>Status</th></tr>
    <?php foreach ($rows as $r): [$sl, $st] = LeaveController::STATUS[$r['status']]; ?>
    <tr>
        <td><a href="<?= e(url('leave/' . $r['id'])) ?>"><strong><?= e($r['first_name'] . ' ' . $r['last_name']) ?></strong></a></td>
        <td><?= e(fmt_date($r['from_date'], 'D j M')) ?><?= $r['to_date'] !== $r['from_date'] ? ' – ' . e(fmt_date($r['to_date'], 'D j M')) : '' ?></td>
        <td><?= e(LeaveController::KINDS[$r['kind']]) ?><br><small class="muted"><?= e(mb_strimwidth($r['reason'], 0, 80, '…')) ?></small></td>
        <td><?= badge($sl, $st) ?><?= $r['decision_note'] ? '<br><small class="muted">' . e($r['decision_note']) . '</small>' : '' ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="4" class="muted">No leave requests yet.</td></tr><?php endif; ?>
</table></div></div>
