<?php
/* Financial growth: fees billed and collected per year, income against spending per year, this year term by term. */
$title = 'Fee income & spending';
$href = 'finance';
$widget = null;
$fees = !empty($d['fees']);
$cash = !empty($d['cash']);
if (empty($d['empty']) && (!$fees || !array_filter(array_column($d['feeYears'] ?? [], 'billed'))) && (!$cash || !array_filter(array_map(fn($r) => $r['income'] + $r['spent'], $d['cashYears'] ?? [])))) {
    $d['empty'] = 'No bills or money recorded yet.';
}
ob_start();
if (!empty($d['empty'])): ?><p class="muted"><?= e($d['empty']) ?></p><?php else: ?>
    <div class="an-stats">
        <?php if ($fees): ?>
        <?= Analytics::open('fees', [], '') ?><b><?= e(Analytics::cedis($d['ytd'])) ?></b><span>fees received in <?= e($d['year']) ?></span><?= Analytics::close('fees', []) ?>
        <div class="<?= $d['ytdGrowth'] !== null && $d['ytdGrowth'] < 0 ? 'is-warn' : '' ?>"><b><?= e(Analytics::signed($d['ytdGrowth'])) ?></b><span><?= $d['ytdGrowth'] === null ? 'growth shows from next year' : ($d['sameDate'] ? 'on last year, same date' : 'on last year') ?></span></div>
        <?= Analytics::open('fees/debtors', [], $d['rate'] !== null && $d['rate'] < 50 ? 'is-warn' : '') ?><b><?= $d['rate'] === null ? '—' : e(Analytics::num($d['rate'])) . '%' ?></b><span>of this year's bills collected</span><?= Analytics::close('fees/debtors', []) ?>
        <div><b><?= $d['perStudent'] === null ? '—' : e(Analytics::cedis($d['perStudent'])) ?></b><span>collected per student</span></div>
        <?php endif; ?>
    </div>
    <div class="an-split">
        <?php if ($fees): ?>
        <div>
            <h3 class="an-h3">Fees per year <small>collected of billed</small></h3>
            <div class="an-yrs">
                <?php foreach ($d['feeYears'] as $y): $w = $y['billed'] > 0 ? round($y['paid'] * 100 / $y['billed']) : 0; ?>
                <div class="an-yr" title="<?= e($y['name'] . ': ' . Analytics::cedis($y['paid']) . ' collected of ' . Analytics::cedis($y['billed']) . ' billed') ?>">
                    <span class="an-yr-l"><?= e($y['name']) ?></span>
                    <span class="an-bullet"><span style="width:<?= min(100, $w) ?>%"></span></span>
                    <span class="an-yr-v"><?= $y['billed'] > 0 ? e(Analytics::cedis($y['paid'])) . ' <small>' . (int) $w . '%</small>' : '<small>no bills</small>' ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if (count($d['terms']) > 0): ?>
            <h3 class="an-h3"><?= e($d['year']) ?> by term</h3>
            <div class="an-yrs">
                <?php foreach ($d['terms'] as $t): $w = $t['billed'] > 0 ? round($t['paid'] * 100 / $t['billed']) : 0; ?>
                <div class="an-yr" title="<?= e($t['name'] . ': ' . Analytics::cedis($t['paid']) . ' of ' . Analytics::cedis($t['billed'])) ?>">
                    <span class="an-yr-l"><?= e($t['name']) ?></span>
                    <span class="an-bullet"><span style="width:<?= min(100, $w) ?>%"></span></span>
                    <span class="an-yr-v"><?= $t['billed'] > 0 ? e(Analytics::cedis($t['paid'])) . ' <small>' . (int) $w . '%</small>' : '<small>not billed yet</small>' ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if ($cash): $cmax = max(1, ...array_map(fn($r) => max($r['income'], $r['spent']), $d['cashYears'])); ?>
        <div>
            <h3 class="an-h3">Income and spending per year</h3>
            <div class="an-legend"><span class="k k-in"></span>Income<span class="k k-out"></span>Spending</div>
            <div class="an-yrs">
                <?php foreach ($d['cashYears'] as $y): ?>
                <div class="an-yr an-yr2" title="<?= e($y['name'] . ': income ' . Analytics::cedis($y['income']) . ', spending ' . Analytics::cedis($y['spent'])) ?>">
                    <span class="an-yr-l"><?= e($y['name']) ?></span>
                    <span class="an-pair"><span class="in" style="width:<?= round($y['income'] * 100 / $cmax, 1) ?>%"></span><span class="out" style="width:<?= round($y['spent'] * 100 / $cmax, 1) ?>%"></span></span>
                    <span class="an-yr-v"><?= e(Analytics::cedis($y['income'])) ?><br><small><?= e(Analytics::cedis($y['spent'])) ?> spent</small></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
<?php endif;
$body = ob_get_clean();
$note = empty($d['empty']) ? 'Fees: bills of each year\'s terms · income and spending: cash book entries dated in that year' : null;
require __DIR__ . '/_panel.php';
