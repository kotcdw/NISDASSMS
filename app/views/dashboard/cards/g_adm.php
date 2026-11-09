<?php
/* Online Admission growth: applications per year, conversion to enrolled, where applicants came from. */
$title = 'Admissions';
$href = 'admission';
$widget = 'c-admissions';
if (empty($d['empty']) && !array_filter(array_column($d['rows'] ?? [], 'n'))) { $d['empty'] = 'No applications yet.'; }
ob_start();
if (!empty($d['empty'])): ?><p class="muted"><?= e($d['empty']) ?></p><?php else: $src = $d['online'] + $d['walkin']; ?>
    <div class="an-stats">
        <?= Analytics::open('admission', [], '') ?><b><?= number_format($d['now']) ?></b><span>applications for <?= e($d['year']) ?></span><?= Analytics::close('admission', []) ?>
        <div class="<?= $d['growth'] !== null && $d['growth'] < 0 ? 'is-warn' : '' ?>"><b><?= $d['before'] === null ? '—' : e(Analytics::signed($d['growth'])) ?></b><span><?= $d['before'] === null ? 'growth shows from next year' : 'vs ' . number_format($d['before']) . ($d['sameDate'] ? ' by this date last year' : ' last year') ?></span></div>
        <?= Analytics::open('admission', ['stage' => 'enrolled'], '') ?><b><?= $d['conv'] === null ? '—' : e(Analytics::num($d['conv'])) . '%' ?></b><span>became students (<?= (int) $d['enrolled'] ?>)</span><?= Analytics::close('admission', ['stage' => 'enrolled']) ?>
    </div>
    <h3 class="an-h3">Applications per year</h3>
    <?php $cols = array_map(fn($r) => [$r['name'], $r['n'], number_format($r['n'])], $d['rows']); $colsLabel = 'Applications per academic year'; require __DIR__ . '/_cols.php'; ?>
    <?php if ($src): ?>
    <h3 class="an-h3">How they applied <small><?= e($d['year']) ?></small></h3>
    <div class="an-yrs">
        <?php foreach ([['Online form', $d['online']], ['At the school (walk-in)', $d['walkin']]] as [$l, $n]): ?>
        <div class="an-yr"><span class="an-yr-l"><?= e($l) ?></span><span class="an-bullet"><span style="width:<?= round($n * 100 / $src) ?>%"></span></span><span class="an-yr-v"><?= (int) $n ?> <small><?= round($n * 100 / $src) ?>%</small></span></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
<?php endif;
$body = ob_get_clean();
$note = empty($d['empty']) ? 'Applications counted by the year the child will join' : null;
require __DIR__ . '/_panel.php';
