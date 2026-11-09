<?php
/* Subject Manager: scheme-of-work coverage, lesson notes, subjects with a teacher, and the school average term by term. */
$title = 'Teaching & learning';
$href = 'curriculum';
$widget = null;
if (empty($d['empty']) && !$d['subjects'] && !$d['planned'] && !$d['trend']) { $d['empty'] = 'No subjects set up yet.'; }
$rowsS = empty($d['empty']) ? [
    ['Scheme of work taught', $d['coverage'], $d['planned'] ? number_format($d['taught']) . ' of ' . number_format($d['planned']) . ' planned topics' . ($d['week'] ? ' to week ' . $d['week'] : '') : 'nothing planned yet', 'curriculum'],
    ['Lesson notes handed in', $d['notesPct'], $d['notes']['expected'] ? number_format($d['notes']['submitted']) . ' of ' . number_format($d['notes']['expected']) . ' due so far' : 'none due yet', 'lesson-notes'],
    ['… handed in on time', $d['ontimePct'], $d['notes']['expected'] ? number_format($d['notes']['ontime']) . ' by the end of their week' : '', 'lesson-notes'],
    ['… vetted by the Head', $d['vettedPct'], $d['notes']['expected'] ? number_format($d['notes']['vetted']) . ' approved' : '', 'lesson-notes'],
    ['Subjects with a teacher', $d['staffedPct'], number_format($d['staffed']) . ' of ' . number_format($d['subjects']) . ' class subjects', 'curriculum'],
] : [];
ob_start();
if (!empty($d['empty'])): ?><p class="muted"><?= e($d['empty']) ?></p><?php else: ?>
    <div class="an-meters">
        <?php foreach ($rowsS as [$l, $p, $sub, $link]): $tone = $p === null ? '' : ($p >= 80 ? 'good' : ($p >= 50 ? 'warn' : 'bad')); ?>
        <?= Analytics::open($link, [], 'an-mrow') ?>
            <span class="an-ml"><?= e($l) ?><small><?= e($sub) ?></small></span>
            <span class="an-mt"><span class="<?= e($tone) ?>" style="width:<?= $p === null ? 0 : min(100, (int) $p) ?>%"></span></span>
            <span class="an-mv <?= e($tone) ?>"><?= $p === null ? '—' : e(Analytics::num($p)) . '%' ?></span>
        <?= Analytics::close($link) ?>
        <?php endforeach; ?>
    </div>
    <?php if (count($d['trend']) > 1): ?>
    <h3 class="an-h3">School average by term</h3>
    <?= Charts::line(array_map(fn($t) => [$t['label'], $t['avg'], $t['name'] . ': ' . Analytics::num($t['avg']) . '%'], $d['trend']), ['max' => 100, 'min' => 0, 'target' => Analytics::PASS_MARK, 'format' => fn($v) => $v . '%', 'label' => 'School average by term', 'height' => 140, 'width' => 720, 'labelEvery' => max(1, (int) ceil(count($d['trend']) / 6))]) ?>
    <?php elseif (count($d['trend']) === 1): ?>
    <p class="an-ok" style="color:var(--ink)">School average <?= e($d['trend'][0]['name']) ?>: <b><?= e(Analytics::num($d['trend'][0]['avg'])) ?>%</b> · the trend shows from the next term.</p>
    <?php endif; ?>
<?php endif;
$body = ob_get_clean();
$note = empty($d['empty']) ? 'Green 80% or more · amber 50–79% · red below 50%' . (count($d['trend'] ?? []) > 1 ? ' · dashed line = pass mark' : '') : null;
require __DIR__ . '/_panel.php';
