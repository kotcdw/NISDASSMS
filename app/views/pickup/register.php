<?php /* Pick-up register (office, Head). Expects $rows, $period. */ ?>
<div class="page-head"><div><h1>Pick-up register</h1></div>
    <div class="actions"><?= export_grid_menu('Export', 'pickups') ?><?php if (can('pickup.gate')): ?><a class="btn btn-primary" href="<?= e(url('gate')) ?>">Go to the gate</a><?php endif; ?></div></div>
<nav class="seg" aria-label="Period"><?php foreach (['today' => 'Today', 'week' => 'This week', 'month' => 'This month', 'term' => 'This term'] as $k => $l): ?><a href="<?= e(url('gate/register', ['period' => $k])) ?>"<?= $period === $k ? ' class="active"' : '' ?>><?= e($l) ?></a><?php endforeach; ?></nav>
<div class="xgrid-box" data-xgrid="pickups" data-xgrid-file="pick-up-register">
    <div class="xg-scroll"><table class="xgrid">
        <thead><tr><?php foreach (['when' => ['When', 'date'], 'ref' => ['Ref', 'text'], 'child' => ['Child', 'text'], 'by' => ['Collected by', 'text'], 'id' => ['ID', 'text'], 'how' => ['Approved by', 'text'], 'st' => ['Status', 'text'], 'rec' => ['Recorded by', 'text']] as $k => [$t, $ty]): ?><th data-c="<?= $k ?>" data-type="<?= $ty ?>"><button type="button" class="xg-sort"><?= e($t) ?><span class="xg-arrow"></span></button></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): [$l, $tone] = Pickups::STATUS[$r['status']] ?? [$r['status'], '']; ?>
        <tr>
            <td data-c="when" data-s="<?= e($r['created_at']) ?>"><?= e(date('D j M, g:ia', strtotime($r['created_at']))) ?></td>
            <td data-c="ref"><a href="<?= e(url('gate/' . $r['id'])) ?>"><?= e($r['ref_no']) ?></a></td>
            <td data-c="child"><?= e($r['first_name'] . ' ' . $r['last_name']) ?></td>
            <td data-c="by"><?= e($r['collector_name']) ?><span hidden> · </span><br><small class="muted"><?= e($r['relationship'] ?: (Pickups::KINDS[$r['kind']] ?? '')) ?></small></td>
            <td data-c="id"><?= e(Pickups::ID_TYPES[$r['id_type']] ?? '') ?> <?= e($r['id_number']) ?></td>
            <td data-c="how"><?= e(Pickups::VIA[$r['approval_via']] ?? '—') ?></td>
            <td data-c="st"><?= badge($l, $tone ?: 'neutral') ?></td>
            <td data-c="rec"><?= e($r['recorded_name'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No pick-ups in this period.</p>
    </div>
</div>
