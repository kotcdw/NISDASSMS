<div class="xgrid-box" data-xgrid="homework" data-xgrid-file="my-homework">
<div class="page-head">
    <div><h1>My homework</h1></div>
</div>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['due' => ['Due', 'date'], 'title' => ['Assignment'], 'subject' => ['Subject'], 'status' => ['Status'], 'score' => ['Score', 'num', 'num']]) ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): [$sl, $st] = Assignments::pupilState($r); $hasScore = $r['sub_status'] === 'returned' && $r['score'] !== null; ?>
    <tr>
        <td data-c="due" data-s="<?= e($r['due_on']) ?>" style="white-space:nowrap"><?= e(date('D j M', strtotime($r['due_on']))) ?></td>
        <td data-c="title"><a href="<?= e(url('homework/' . $r['id'])) ?>"><strong><?= e($r['title']) ?></strong></a></td>
        <td data-c="subject"><?= e($r['subject_name'] ?? '') ?></td>
        <td data-c="status"><?= badge($sl, $st) ?></td>
        <td data-c="score" class="num" data-s="<?= $hasScore ? (float) $r['score'] : '' ?>"><?= $hasScore ? e(Results::fmt((float) $r['score'])) . ($r['max_score'] !== null ? ' / ' . e(Results::fmt((float) $r['max_score'])) : '') : '—' ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No homework at the moment.</p>
</div>
</div>
