<?php
$bar = function (?float $v): string {
    if ($v === null) return '<span class="muted">—</span>';
    $c = $v >= 70 ? 'ok' : ($v >= 50 ? 'mid' : 'low');
    return '<span class="perf-bar ' . $c . '"><span style="width:' . max(2, min(100, (int) $v)) . '%"></span></span> ' . e(Results::fmt($v)) . '%';
};
$one = $subjectId ? ($subjects[0]['name'] ?? '') : '';
?>
<div class="xgrid-box" data-xgrid="perf-<?= $subjectId ? 'class' : 'subject' ?>" data-xgrid-file="subject-performance">
<div class="page-head">
    <div><?php if ($subjectId): ?><div class="breadcrumb"><a href="<?= e(url('curriculum/performance', ['term' => $term['id']])) ?>">All subjects</a> /</div><?php endif; ?><h1><?= $subjectId ? e($one ?: 'Subject') . ' performance' : 'Subject performance' ?></h1></div>
    <div class="actions">
        <form method="get" action="<?= e(url('curriculum/performance')) ?>" class="list-pick" data-autosubmit>
            <?php if ($subjectId): ?><input type="hidden" name="subject" value="<?= (int) $subjectId ?>"><?php endif; ?>
            <select name="term" aria-label="Term"><?php foreach ($terms as $t): ?><option value="<?= (int) $t['id'] ?>"<?= selected($t['id'], $term['id']) ?>><?= e($t['year_name'] . ' · ' . $t['name']) ?></option><?php endforeach; ?></select>
        </form>
        <?= export_grid_menu() ?>
    </div>
</div>
    <div class="xg-scroll">
    <?php if (!$subjectId): ?>
    <table class="xgrid">
        <thead><tr>
            <?php foreach (['name' => ['Subject', 'text'], 'classes' => ['Classes', 'num'], 'n' => ['Students scored', 'num'], 'avg' => ['Average', 'num'], 'pass' => ['Pass rate', 'num'], 'best' => ['Best class', 'text'], 'weak' => ['Needs help', 'text'], 'cov' => ['Scheme covered', 'num'], 'notes' => ['Lesson notes', 'num']] as $k => [$t, $ty]): ?><th data-c="<?= $k ?>" data-type="<?= $ty ?>"><button type="button" class="xg-sort"><?= e($t) ?><span class="xg-arrow"></span></button></th><?php endforeach; ?>
        </tr></thead>
        <tbody>
        <?php foreach ($subjects as $s): ?>
        <tr>
            <td data-c="name"><a href="<?= e(url('curriculum/performance', ['term' => $term['id'], 'subject' => $s['id']])) ?>"><?= e($s['name']) ?></a><?= (int) $s['is_core'] ? '<span hidden> · </span> <small class="muted">core</small>' : '' ?></td>
            <td data-c="classes" class="num"><?= (int) $s['classes'] ?></td>
            <td data-c="n" class="num"><?= (int) $s['n'] ?></td>
            <td data-c="avg" data-s="<?= $s['avg'] ?? '' ?>"><?= $bar($s['avg']) ?></td>
            <td data-c="pass" data-s="<?= $s['passpct'] ?? '' ?>"><?= $bar($s['passpct'] === null ? null : (float) $s['passpct']) ?></td>
            <td data-c="best"><?= isset($s['best']) ? e($s['best'][0]) . '<span hidden> · </span> <small class="muted">' . e(Results::fmt($s['best'][1])) . '</small>' : '—' ?></td>
            <td data-c="weak"><?= isset($s['weak']) && $s['classes'] > 1 ? e($s['weak'][0]) . '<span hidden> · </span> <small class="muted">' . e(Results::fmt($s['weak'][1])) . '</small>' : '—' ?></td>
            <td data-c="cov" data-s="<?= $s['cov'] ?? '' ?>"><?= $bar($s['cov'] === null ? null : (float) $s['cov']) ?></td>
            <td data-c="notes" data-s="<?= $s['notes'] ?? '' ?>"><?= $bar($s['notes'] === null ? null : (float) $s['notes']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <table class="xgrid">
        <thead><tr>
            <?php foreach (['class' => ['Class', 'text'], 'teacher' => ['Teacher', 'text'], 'n' => ['Scored', 'num'], 'avg' => ['Average', 'num'], 'pass' => ['Pass rate', 'num'], 'cov' => ['Scheme covered', 'num'], 'notes' => ['Lesson notes', 'num']] as $k => [$t, $ty]): ?><th data-c="<?= $k ?>" data-type="<?= $ty ?>"><button type="button" class="xg-sort"><?= e($t) ?><span class="xg-arrow"></span></button></th><?php endforeach; ?>
            <th class="xg-act"></th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $pp = $r['n'] ? round($r['pass'] * 100 / $r['n']) : null; ?>
        <tr>
            <td data-c="class"><strong><?= e($r['class_name']) ?></strong></td>
            <td data-c="teacher"><?= e($r['teacher_name'] ?? 'Class teacher') ?></td>
            <td data-c="n" class="num"><?= (int) $r['n'] ?> / <?= (int) $r['roll'] ?></td>
            <td data-c="avg" data-s="<?= $r['avg'] ?? '' ?>"><?= $bar($r['avg']) ?></td>
            <td data-c="pass" data-s="<?= $pp ?? '' ?>"><?= $bar($pp === null ? null : (float) $pp) ?></td>
            <td data-c="cov" data-s="<?= $r['cov'] ?? '' ?>"><?= $bar($r['cov']) ?></td>
            <td data-c="notes" data-s="<?= $r['notes'] ?? '' ?>"><?= $bar($r['notes'] === null ? null : (float) $r['notes']) ?></td>
            <td class="xg-act"><details class="rowmenu"><summary aria-label="Open">⋯</summary><div>
                <a href="<?= e(url('curriculum/scheme/' . $r['id'], ['term' => $term['id']])) ?>">Scheme of work</a>
                <a href="<?= e(url('lesson-notes', ['term' => $term['id'], 'class' => $r['class_id']])) ?>">Lesson notes</a>
                <?php if (Results::canEnter(['teacher_id' => $r['teacher_id'], 'class_teacher_id' => null])): ?><a href="<?= e(url('exams/sheet/' . $r['id'], ['term' => $term['id']])) ?>">Score sheet</a><?php endif; ?>
            </div></details></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <p class="xg-empty muted"<?= ($subjectId ? $rows : $subjects) ? ' hidden' : '' ?> data-xg-empty><?= $subjectId ? 'No classes take this subject this term.' : 'No subjects to show for this term.' ?></p>
    </div>
</div>
