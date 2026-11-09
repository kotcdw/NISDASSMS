<?php
/* SIS growth: students enrolled per academic year, new students and leavers this year, enrolment by level against last year. */
$title = 'Enrolment';
$href = 'pupils';
$widget = 'c-enrolment';
ob_start();
if (empty($d['empty']) && empty($d['now']) && empty($d['before'])) { $d['empty'] = 'No students enrolled yet.'; }
if (!empty($d['empty'])): ?><p class="muted"><?= e($d['empty']) ?></p><?php else: ?>
    <div class="an-stats">
        <?= Analytics::open('pupils', [], '') ?><b><?= number_format($d['now']) ?></b><span>students in <?= e($d['year']) ?></span><?= Analytics::close('pupils', []) ?>
        <div class="<?= $d['growth'] !== null && $d['growth'] < 0 ? 'is-warn' : '' ?>"><b><?= $d['hasPrev'] ? e(Analytics::signed($d['growth'])) : '—' ?></b><span><?= $d['hasPrev'] ? 'on ' . e($d['prevYear']) . ' (' . number_format((int) $d['before']) . ')' : 'growth shows from next year' ?></span></div>
        <?= Analytics::open('admission', [], '') ?><b><?= number_format($d['new']) ?></b><span>new students this year</span><?= Analytics::close('admission', []) ?>
        <?= Analytics::open('pupils', [], '') ?><b><?= number_format($d['left']) ?></b><span>left or graduated</span><?= Analytics::close('pupils', []) ?>
    </div>
    <div class="an-split">
        <div>
            <h3 class="an-h3">Students per year</h3>
            <?php $cols = array_map(fn($r) => [$r['name'], $r['n'], number_format($r['n'])], $d['rows']); $colsLabel = 'Students enrolled per academic year'; require __DIR__ . '/_cols.php'; ?>
        </div>
        <div>
            <h3 class="an-h3">By level <small><?= $d['hasPrev'] ? 'this year · change on last year' : 'this year' ?></small></h3>
            <div class="an-lvls">
                <?php foreach ($d['levels'] as $l): $chg = $l['now'] - $l['before']; ?>
                <div class="an-lv"><span><?= e($l['name']) ?></span><b><?= (int) $l['now'] ?></b><?php if ($d['hasPrev']): ?><i class="an-chg<?= $chg > 0 ? ' up' : ($chg < 0 ? ' down' : '') ?>"><?= $chg > 0 ? '+' . $chg : ($chg < 0 ? '−' . abs($chg) : '±0') ?></i><?php endif; ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif;
$body = ob_get_clean();
$note = empty($d['empty']) ? 'Students enrolled at any time in each academic year (up to five years)' : null;
require __DIR__ . '/_panel.php';
