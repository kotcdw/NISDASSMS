<?php
/* Spending against the year's budget, cumulative by month (budget spread evenly over the year). */
$title = 'Budget vs actual spending';
$href = 'finance/budget';
$widget = null;
if (empty($d['empty']) && empty($d['ytd']) && empty($d['annual'])) { $d['empty'] = 'No spending or budget recorded yet this year.'; }
ob_start();
if (!empty($d['empty'])): ?><p class="muted"><?= e($d['empty']) ?></p>
<?php elseif (!$d['labels']): ?><p class="muted">Set up the academic year and its terms first.</p>
<?php else:
    $v = $d['ytdVar'];
    $tone = $v === null ? '' : ($v > 10 ? 'is-bad' : ($v > 0 ? 'is-warn' : 'is-good')); ?>
    <div class="an-stats">
        <div><b><?= e(Analytics::cedis($d['ytd'])) ?></b><span>spent so far, <?= e($d['year']) ?></span></div>
        <div><b><?= $d['annual'] > 0 ? e(Analytics::cedis($d['annual'])) : '—' ?></b><span>year budget</span></div>
        <div class="<?= $tone ?>"><b><?= $v === null ? '—' : e(sprintf('%+.1f%%', $v)) ?></b><span><?= $v === null ? 'no budget set' : ($v > 0 ? 'over budget to date' : 'under budget to date') ?></span></div>
    </div>
    <div class="an-legend"><span><i class="k s-actual"></i>Actual</span><?php if ($d['annual'] > 0): ?><span><i class="k s-budget"></i>Budget</span><?php endif; ?></div>
    <?= Charts::lines($d['labels'], array_values(array_filter([
        ['Actual', 's-actual', $d['actual']],
        $d['annual'] > 0 ? ['Budget', 's-budget', $d['budget']] : null,
    ])), ['height' => 140, 'width' => 560, 'format' => fn($x) => 'GH₵ ' . number_format((float) $x), 'label' => 'Cumulative spending against budget by month']) ?>
<?php endif;
$body = ob_get_clean();
$note = empty($d['empty']) && !empty($d['labels']) && $d['annual'] <= 0 ? 'Enter the year budget in Finance → Budget to compare.' : (empty($d['empty']) ? 'Cumulative, by month · budget spread evenly over the year' : null);
require __DIR__ . '/_panel.php';
