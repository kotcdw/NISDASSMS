<?php
/* Attendance: last 15 school days against the 90% target, and students below 85% this term. */
$title = 'Attendance';
$href = 'attendance';
$widget = 'c-att-trend';
if (empty($d['empty']) && isset($d['chart']) && str_contains((string) $d['chart'], 'No data yet')) { $d['empty'] = 'No registers taken yet this term.'; }
ob_start();
if (!empty($d['empty'])): ?><p class="muted"><?= e($d['empty']) ?></p><?php else: ?>
    <?= $d['chart'] ?>
    <div class="an-sub">
        <h3>Below 85% this term <span class="an-count<?= $d['riskCount'] ? ' bad' : '' ?>"><?= (int) $d['riskCount'] ?></span></h3>
        <?php if (!$d['risk']): ?><p class="an-ok">✓ Every student is at 85% or above.</p>
        <?php else: ?><ul class="an-list">
            <?php foreach ($d['risk'] as $p): ?><li><a href="<?= e(url('pupils/' . $p['id'])) ?>"><?= e($p['name']) ?></a><small><?= e($p['class']) ?></small><b class="an-pill bad"><?= e((string) $p['rate']) ?>%</b></li><?php endforeach; ?>
        </ul>
        <?php if ($d['riskCount'] > count($d['risk'])): ?><a class="an-more" href="<?= e(url('attendance/absentees')) ?>">See all <?= (int) $d['riskCount'] ?> →</a><?php endif; ?>
        <?php endif; ?>
    </div>
<?php endif;
$body = ob_get_clean();
$note = empty($d['empty']) ? '% present or late each school day · dashed line = 90% target' : null;
require __DIR__ . '/_panel.php';
