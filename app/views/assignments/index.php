<div class="xgrid-box" data-xgrid="assignments" data-xgrid-file="assignments">
<div class="page-head">
    <div><h1>Assignments</h1></div>
    <div class="actions"><?= export_grid_menu() ?><a class="btn btn-primary" href="<?= e(url('assignments/create')) ?>">+ New assignment</a></div>
</div>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['due' => ['Due', 'date'], 'title' => ['Assignment'], 'subject' => ['Subject'], 'class' => ['Class']] + (Auth::classScope() === null ? ['teacher' => ['Teacher']] : []) + ['handed' => ['Handed in', 'num', 'num'], 'marked' => ['Marked', 'num', 'num'], 'status' => ['Status']]) ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $c = $counts[(int) $r['id']] ?? ['handed' => 0, 'marked' => 0]; $roll = $rolls[(int) $r['class_id']] ?? 0; $over = $r['due_on'] < date('Y-m-d'); ?>
    <tr>
        <td data-c="due" data-s="<?= e($r['due_on']) ?>" style="white-space:nowrap"><?= e(date('D j M', strtotime($r['due_on']))) ?><?= $r['due_on'] === date('Y-m-d') ? ' ' . badge('Today', 'warn') : '' ?></td>
        <td data-c="title"><a href="<?= e(url('assignments/' . $r['id'])) ?>"><strong><?= e($r['title']) ?></strong></a><?= (int) $r['submit_online'] ? '' : ' <small class="muted">on paper</small>' ?></td>
        <td data-c="subject"><?= e($r['subject_name'] ?? '') ?></td>
        <td data-c="class"><?= e($r['class_name']) ?></td>
        <?php if (Auth::classScope() === null): ?><td data-c="teacher"><?= e($r['teacher_name'] ?? '') ?></td><?php endif; ?>
        <td data-c="handed" class="num" data-s="<?= (int) $c['handed'] ?>"><?= (int) $c['handed'] ?> / <?= (int) $roll ?></td>
        <td data-c="marked" class="num"><?= (int) $c['marked'] ?></td>
        <td data-c="status"><?= badge(...Assignments::STATUS[$r['status']]) ?><?= $over && $r['status'] === 'published' ? '<span hidden> · </span> ' . badge('Past due', 'neutral') : '' ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No assignments yet.</p>
</div>
</div>
<div class="list-pager"><?= pager_links($pager) ?></div>
