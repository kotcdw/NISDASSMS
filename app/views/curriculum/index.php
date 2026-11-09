<?php
$planned = array_sum(array_map(fn($r) => $r['cov']['planned'] ?? 0, $rows));
$taught = array_sum(array_map(fn($r) => $r['cov']['taught'] ?? 0, $rows));
$overdue = array_sum(array_map(fn($r) => $r['cov']['overdue'] ?? 0, $rows));
$notStarted = count(array_filter($rows, fn($r) => !($r['cov']['planned'] ?? 0)));
?>
<div class="xgrid-box" data-xgrid="scheme" data-xgrid-file="scheme-of-work">
<div class="page-head">
    <div><h1>Scheme of work</h1></div>
    <div class="actions">
        <form method="get" action="<?= e(url('curriculum')) ?>" class="list-pick" data-autosubmit>
            <?php if (can('curriculum.manage')): ?>
                <?php if (!empty($_GET['term']) && $term): ?><input type="hidden" name="term" value="<?= (int) $term['id'] ?>"><?php endif; ?>
                <select name="class" aria-label="Class"><option value="">All classes</option><?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected($c['id'], $classId) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
            <?php else: ?>
                <select name="term" aria-label="Term"><?php foreach ($terms as $tm): ?><option value="<?= (int) $tm['id'] ?>"<?= selected($tm['id'], $term['id'] ?? 0) ?>><?= e($tm['year_name'] . ' · ' . $tm['name']) ?></option><?php endforeach; ?></select>
                <?php if ($classId): ?><input type="hidden" name="class" value="<?= (int) $classId ?>"><?php endif; ?>
            <?php endif; ?>
        </form>
        <?php if (can('teachers.view')): ?><a class="btn btn-ghost" href="<?= e(url('teachers')) ?>">Teacher profiles</a><?php endif; ?>
        <?= export_grid_menu() ?>
    </div>
</div>

<?php if ($term): ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['class' => ['Class'], 'subject' => ['Subject'], 'teacher' => ['Teacher'], 'topics' => ['Topics', 'num', 'num'], 'covered' => ['Covered %', 'num'], 'behind' => ['Behind', 'num', 'num'], 'last' => ['Last taught', 'date']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $c = $r['cov']; ?>
        <tr>
            <td data-c="class"><?= e($r['class_name']) ?></td>
            <td data-c="subject"><strong><?= e($r['subject_name']) ?></strong></td>
            <td data-c="teacher"><?= e($r['teacher_name'] ?? '—') ?></td>
            <td data-c="topics" class="num"><?= (int) $c['planned'] ?></td>
            <td data-c="covered" data-s="<?= $c['planned'] ? (float) $c['pct'] : -1 ?>"><?php if ($c['planned']): ?><?= Results::fmt($c['pct']) ?>%<span class="pct-bar"><span style="width:<?= (float) $c['pct'] ?>%"></span></span><?php else: ?><?= badge('Not planned', 'warn') ?><?php endif; ?></td>
            <td data-c="behind" class="num" data-s="<?= (int) $c['overdue'] ?>"><?= $c['overdue'] ? badge((string) $c['overdue'], 'bad') : '0' ?></td>
            <td data-c="last" data-s="<?= e((string) $c['last']) ?>"><?= $c['last'] ? e(date('j M', strtotime($c['last']))) : '<span class="muted">—</span>' ?></td>
            <td class="xg-act"><a class="btn btn-sm" href="<?= e(url('curriculum/scheme/' . $r['id'], ['term' => $term['id']])) ?>">Open</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No subjects to show.</p>
</div>
<?php endif; ?>
</div>
