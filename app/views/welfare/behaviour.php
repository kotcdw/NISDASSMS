<?php $wt = 'welfare/behaviour'; ?>

<?php require __DIR__ . '/_tabs.php'; ?>
<div class="xgrid-box" data-xgrid="behaviour" data-xgrid-file="behaviour-records">
<div class="page-head"><div><h1>Behaviour records</h1></div>
    <div class="actions"><?= export_grid_menu() ?><a class="btn btn-primary" href="<?= e(url('welfare/behaviour/new')) ?>">+ Record behaviour</a></div></div>
    <div class="xg-scroll"><table class="xgrid">
        <thead><tr><?php foreach (['date' => ['Date', 'date'], 'student' => ['Student', 'text'], 'class' => ['Class', 'text'], 'record' => ['Record', 'text'], 'action' => ['Action taken', 'text'], 'parent' => ['Parent told', 'text'], 'by' => ['By', 'text']] as $k => [$t, $ty]): ?><th data-c="<?= $k ?>" data-type="<?= $ty ?>"><button type="button" class="xg-sort"><?= e($t) ?><span class="xg-arrow"></span></button></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach ($rows as $b): ?><tr>
            <td data-c="date" data-s="<?= e($b['occurred_on']) ?>"><?= e(fmt_date($b['occurred_on'], 'D j M')) ?></td>
            <td data-c="student"><a href="<?= e(url('pupils/' . $b['pupil_id'])) ?>"><?= e($b['first_name'] . ' ' . $b['last_name']) ?></a></td>
            <td data-c="class"><?= e($b['class_name'] ?? '') ?></td>
            <td data-c="record"<?= $b['description'] ? ' title="' . e($b['description']) . '"' : '' ?>><?= $b['kind'] === 'merit' ? badge('Merit', 'good') : badge('Concern', 'warn') ?> <?= e($b['category']) ?></td>
            <td data-c="action"><?= e(Welfare::ACTIONS[$b['action_taken']] ?? '—') ?></td>
            <td data-c="parent"><?= $b['parent_informed'] ? 'SMS' : '—' ?></td>
            <td data-c="by"><?= e($b['by_name'] ?? '') ?></td>
        </tr><?php endforeach; ?>
        </tbody>
    </table>
    <p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>Nothing recorded this term.</p>
    </div>
</div>
