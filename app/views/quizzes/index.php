<?php $q = null; ?>
<div class="xgrid-box" data-xgrid="quizzes" data-xgrid-file="quizzes">
<div class="page-head">
    <div><h1>Quizzes</h1></div>
    <div class="actions"><?= export_grid_menu() ?><?php if ($css): ?><button type="button" class="btn btn-primary" data-help="new-quiz" aria-expanded="false">+ New quiz</button><?php endif; ?></div>
</div>
<?php if ($css): ?>
<div id="new-quiz" class="card" hidden>
    <h2>New quiz</h2>
    <form method="post" action="<?= e(url('quizzes')) ?>" class="form"><?= csrf_field() ?><?php require __DIR__ . '/_settings.php'; ?>
        <div class="gate-actions"><button class="btn btn-primary" type="submit">Create &amp; add questions</button><button type="button" class="btn btn-ghost" data-help="new-quiz">Cancel</button></div></form>
</div>
<?php endif; ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['quiz' => ['Quiz'], 'class' => ['Class'], 'subject' => ['Subject'], 'qs' => ['Questions', 'num', 'num'], 'closes' => ['Closes', 'date'], 'status' => ['Status'], 'done' => ['Done', 'num', 'num'], 'avg' => ['Average %', 'num', 'num']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $open = Quiz::isOpen($r); ?>
    <tr>
        <td data-c="quiz"><a href="<?= e(url('quizzes/' . $r['id'])) ?>"><strong><?= e($r['title']) ?></strong></a><?= (int) $r['time_limit'] ? ' <small class="muted">' . (int) $r['time_limit'] . ' min</small>' : '' ?></td>
        <td data-c="class"><?= e($r['class_name']) ?></td>
        <td data-c="subject"><?= e($r['subject_name']) ?></td>
        <td data-c="qs" class="num"><?= (int) $r['questions'] ?></td>
        <td data-c="closes" data-s="<?= e((string) $r['closes_at']) ?>"><?= $r['closes_at'] ? e(date('d M H:i', strtotime($r['closes_at']))) : '—' ?></td>
        <td data-c="status"><?= $r['status'] === 'draft' ? badge('Draft') : ($r['status'] === 'closed' ? badge('Closed') : ($open ? badge('Open', 'good') : badge('Scheduled', 'warn'))) ?></td>
        <td data-c="done" class="num" data-s="<?= (int) $r['done'] ?>"><?= (int) $r['done'] ?> / <?= (int) $r['pupils'] ?></td>
        <td data-c="avg" class="num"><?= $r['avg_pct'] !== null ? round((float) $r['avg_pct']) : '—' ?></td>
        <td class="xg-act"><a class="btn btn-sm" href="<?= e(url('quizzes/' . $r['id'] . '/results')) ?>">Results</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty><?= $css ? 'No quizzes yet.' : 'No quizzes yet. Ask the Academic Coordinator to allocate your subjects.' ?></p>
</div>
</div>
