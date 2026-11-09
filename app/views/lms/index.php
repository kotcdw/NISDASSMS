<div class="xgrid-box" data-xgrid="lms" data-xgrid-file="learning-materials">
<div class="page-head">
    <div><h1>Learning materials</h1></div>
    <div class="actions"><?= export_grid_menu() ?><a class="btn btn-primary" href="<?= e(url('lms/create')) ?>">+ New material</a></div>
</div>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['title' => ['Material'], 'class' => ['Class'], 'subject' => ['Subject'], 'kind' => ['Kind'], 'posted' => ['Posted', 'date'], 'by' => ['Teacher'], 'seen' => ['Opened by', 'num', 'num']]) ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $m): ?><tr>
        <td data-c="title"><a href="<?= e(url('lms/' . $m['id'])) ?>"><strong><?= e($m['title']) ?></strong></a><?= $m['status'] === 'draft' ? '<span hidden> · </span> ' . badge('Draft', 'neutral') : '' ?></td>
        <td data-c="class"><?= e($m['class_name']) ?></td>
        <td data-c="subject"><?= e($m['subject_name'] ?? '') ?></td>
        <td data-c="kind"><?= e(Learning::KINDS[$m['kind']]) ?></td>
        <td data-c="posted" data-s="<?= e($m['created_at']) ?>"><?= e(fmt_date($m['created_at'], 'j M Y')) ?></td>
        <td data-c="by"><?= e($m['teacher_name'] ?? '') ?></td>
        <td data-c="seen" class="num"><?= (int) $m['seen'] ?></td>
    </tr><?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No materials yet.</p>
</div>
</div>
<div class="list-pager"><?= pager_links($pager) ?></div>
