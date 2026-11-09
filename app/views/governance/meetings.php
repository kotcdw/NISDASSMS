<?php $gt = 'governance/meetings'; $mg = can('governance.manage'); $openForm = old('title') !== ''; ?>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="xgrid-box" data-xgrid="board-meetings" data-xgrid-file="board-meetings">
<div class="page-head">
    <div><h1>Board meetings</h1></div>
    <div class="actions"><?= export_grid_menu() ?><?php if ($mg): ?><button type="button" class="btn btn-primary" data-help="new-meeting" aria-expanded="<?= $openForm ? 'true' : 'false' ?>">+ Schedule a meeting</button><?php endif; ?></div>
</div>
<?php if ($mg): $m = null; ?>
<div id="new-meeting" class="card list-form"<?= $openForm ? '' : ' hidden' ?>><h2>Schedule a meeting</h2><?php require __DIR__ . '/_meeting_form.php'; ?>
    <div class="gate-actions" style="margin-top:.5rem"><button type="button" class="btn btn-ghost" data-help="new-meeting">Cancel</button></div>
</div>
<?php endif; ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['date' => ['Date', 'date'], 'meeting' => ['Meeting'], 'when' => ['Coming / held'], 'type' => ['Type'], 'att' => ['Attendance %', 'num', 'num'], 'present' => ['Present', 'num', 'num']]) ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $m): $pct = (int) $m['marked'] ? round($m['present'] * 100 / $m['marked']) : null; ?>
    <tr>
        <td data-c="date" data-s="<?= e($m['meeting_date']) ?>"><?= e(fmt_date($m['meeting_date'], 'D j M Y')) ?></td>
        <td data-c="meeting"><a href="<?= e(url('governance/meetings/' . $m['id'])) ?>"><strong><?= e($m['title']) ?></strong></a></td>
        <td data-c="when"><?= $m['meeting_date'] >= date('Y-m-d') ? badge('Coming', 'info') : 'Held' ?></td>
        <td data-c="type"><?= e(Governance::MEETING_KINDS[$m['kind']]) ?></td>
        <td data-c="att" class="num" data-s="<?= $pct ?? '' ?>"><?= $pct !== null ? $pct . '%' : '<span class="muted">Not recorded</span>' ?></td>
        <td data-c="present" class="num" data-s="<?= (int) $m['present'] ?>"><?= (int) $m['marked'] ? (int) $m['present'] . ' of ' . (int) $m['marked'] : '' ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No meetings yet.</p>
</div>
</div>
