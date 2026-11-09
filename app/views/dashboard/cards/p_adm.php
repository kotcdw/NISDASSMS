<?php
/* Admissions this year: how many applications reached each stage, and how many became students. */
$title = 'Admissions pipeline';
$href = 'admission';
$widget = 'c-admissions';
if (empty($d['empty']) && empty($d['total'])) { $d['empty'] = 'No applications yet this academic year.'; }
ob_start();
if (!empty($d['empty'])): ?><p class="muted"><?= e($d['empty']) ?></p>
<?php elseif (!$d['total']): ?><p class="muted">No applications yet this academic year.</p>
<?php else: ?>
    <div class="an-stats two">
        <div><b><?= (int) $d['total'] ?></b><span>applications this year</span></div>
        <div><b><?= $d['conv'] === null ? '—' : (int) $d['conv'] . '%' ?></b><span>became students (<?= (int) $d['enrolled'] ?> enrolled)</span></div>
    </div>
    <div class="an-funnel">
        <?php foreach ($d['rows'] as $r): $w = $d['total'] ? round($r['reached'] * 100 / $d['total']) : 0; ?>
        <a class="an-fstep" href="<?= e(url('admission', ['stage' => $r['stage']])) ?>" title="<?= e($r['label'] . ': ' . $r['reached'] . ' reached this stage, ' . $r['now'] . ' there now') ?>">
            <span class="an-fl"><?= e($r['label']) ?></span>
            <span class="an-ft"><span style="width:<?= max(1, $w) ?>%"></span></span>
            <span class="an-fv"><?= (int) $r['reached'] ?><?= $r['now'] && $r['stage'] !== 'enrolled' ? ' <small>(' . (int) $r['now'] . ' waiting)</small>' : '' ?></span>
        </a>
        <?php endforeach; ?>
    </div>
<?php endif;
$body = ob_get_clean();
$note = empty($d['empty']) && !empty($d['total']) ? 'Each bar = applications that reached that stage' . ($d['closed'] ? ' · ' . (int) $d['closed'] . ' not admitted or withdrawn' : '') : null;
require __DIR__ . '/_panel.php';
