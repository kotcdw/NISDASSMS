<?php
/* Academic performance: average by class against the pass mark, best and weakest subject. */
$title = 'Academic performance';
$href = 'exams';
$widget = 'c-academic';
if (empty($d['empty']) && empty($d['rows'])) { $d['empty'] = 'No approved scores yet this term.'; }
ob_start();
if (!empty($d['empty'])): ?><p class="muted"><?= e($d['empty']) ?></p>
<?php elseif (!$d['rows']): ?><p class="muted">No approved scores yet this term.</p>
<?php else: ?>
    <?php if ($d['best'] || $d['weak']): ?>
    <div class="an-stats two">
        <?php if ($d['best']): ?><div><b><?= e(Analytics::num((float) $d['best']['average'])) ?>%</b><span>Strongest: <?= e($d['best']['name']) ?></span></div><?php endif; ?>
        <?php if ($d['weak']): ?><div class="<?= $d['weak']['average'] < Analytics::PASS_MARK ? 'is-warn' : '' ?>"><b><?= e(Analytics::num((float) $d['weak']['average'])) ?>%</b><span>Weakest: <?= e($d['weak']['name']) ?></span></div><?php endif; ?>
    </div>
    <?php endif; ?>
    <h3 class="an-h3">Average by class</h3>
    <?= Charts::hbars(array_map(fn($r) => [$r['name'], $r['avg'], null, Analytics::num($r['avg']) . '%'], $d['rows']), ['max' => 100, 'target' => Analytics::PASS_MARK]) ?>
<?php endif;
$body = ob_get_clean();
$note = empty($d['empty']) && !empty($d['rows']) ? 'Dashed marker = pass mark (' . Analytics::PASS_MARK . '%) · subjects with scores entered so far' : null;
require __DIR__ . '/_panel.php';
