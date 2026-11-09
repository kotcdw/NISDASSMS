<?php $wt = 'welfare/cases'; ?>

<?php require __DIR__ . '/_tabs.php'; ?>
<div class="xgrid-box" data-xgrid="welfare-cases" data-xgrid-file="welfare-cases">
<div class="page-head"><div><h1>Welfare cases</h1></div>
    <div class="actions"><?= export_grid_menu() ?><a class="btn btn-primary" href="<?= e(url('welfare/cases/new')) ?>">+ Open a case</a></div></div>
    <div class="xg-scroll"><table class="xgrid">
        <thead><tr><?php foreach (['ref' => 'Ref', 'student' => 'Student', 'case' => 'Case', 'risk' => 'Risk', 'by' => 'Followed up by', 'follow' => 'Follow-up', 'status' => 'Status'] as $k => $t): ?><th data-c="<?= $k ?>" data-type="<?= $k === 'follow' ? 'date' : 'text' ?>"><button type="button" class="xg-sort"><?= e($t) ?><span class="xg-arrow"></span></button></th><?php endforeach; ?></tr></thead>
        <tbody><?php foreach ($rows as $c): require __DIR__ . '/_case_row.php'; endforeach; ?></tbody>
    </table>
    <p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No welfare cases yet.</p>
    </div>
</div>
