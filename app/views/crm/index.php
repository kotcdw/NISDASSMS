<?php
/* CRM enquiries — Students-style list: title row with Export and + New enquiry, status tabs, sortable columns. */
$ct = 'crm';
$show = array_flip(ListColumns::on('crm'));
$cols = ['ref' => ['Ref'], 'parent' => ['Parent'], 'phone' => ['Phone'], 'child' => ['Child'], 'class' => ['Class'], 'stage' => ['Stage'], 'follow' => ['Next follow-up', 'date'], 'who' => ['Followed up by'], 'source' => ['Source']];
$stageTabs = ['open' => ['Open', url('crm', ['stage' => 'open'])], 'due' => ['Due today', url('crm', ['stage' => 'due'])], 'all' => ['All', url('crm', ['stage' => 'all'])]];
foreach (Crm::STAGES as $k => [$l]) { $stageTabs[$k] = [$l, url('crm', ['stage' => $k])]; }
?>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="xgrid-box" data-xgrid="crm" data-xgrid-file="enquiries">
<div class="page-head">
    <div><h1>Enquiries</h1></div>
    <div class="actions"><?= export_grid_menu() ?><a class="btn btn-primary" href="<?= e(url('crm/create')) ?>">+ New enquiry</a></div>
</div>
<?= card_tabs($stageTabs, $stage, 3, 'Enquiries to show') ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th($cols, $show) ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $e): [$sl, $st] = Crm::STAGES[$e['stage']]; $late = $e['next_follow_up'] && $e['next_follow_up'] <= date('Y-m-d') && in_array($e['stage'], Crm::OPEN, true); $href = e(url('crm/' . $e['id'])); ?>
    <tr>
        <?php if (isset($show['ref'])): ?><td data-c="ref"><a href="<?= $href ?>"><?= e($e['ref_no']) ?></a></td><?php endif; ?>
        <td data-c="parent"><a href="<?= $href ?>"><strong><?= e($e['parent_name']) ?></strong></a></td>
        <?php if (isset($show['phone'])): ?><td data-c="phone"><?= e($e['phone']) ?></td><?php endif; ?>
        <?php if (isset($show['child'])): ?><td data-c="child"><?= e($e['child_name'] ?? '') ?></td><?php endif; ?>
        <?php if (isset($show['class'])): ?><td data-c="class"><?= e($e['level_name'] ?? '') ?></td><?php endif; ?>
        <?php if (isset($show['stage'])): ?><td data-c="stage"><?= badge($sl, $st) ?></td><?php endif; ?>
        <?php if (isset($show['follow'])): ?><td data-c="follow" data-s="<?= e((string) $e['next_follow_up']) ?>"><?= $e['next_follow_up'] ? ($late ? badge(fmt_date($e['next_follow_up'], 'D j M'), 'bad') : e(fmt_date($e['next_follow_up'], 'D j M'))) : '—' ?></td><?php endif; ?>
        <?php if (isset($show['who'])): ?><td data-c="who"><?= e($e['assigned_name'] ?? '') ?></td><?php endif; ?>
        <?php if (isset($show['source'])): ?><td data-c="source"><?= e(Crm::SOURCES[$e['source']]) ?></td><?php endif; ?>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No enquiries here.</p>
</div>
</div>
<div class="list-pager"><?= pager_links($pager) ?></div>
