<?php
/* Fees: what is owed, and collection by class (lowest first) for this term. */
$title = 'Fee collection';
$href = 'fees/debtors';
$widget = 'c-fee-class';
if (empty($d['empty']) && empty($d['rows']) && empty($d['owed'])) { $d['empty'] = 'No bills yet this term.'; }
ob_start();
if (!empty($d['empty'])): ?><p class="muted"><?= e($d['empty']) ?></p><?php else:
    $rows = $d['rows'];
    usort($rows, fn($a, $b) => $a['pct'] <=> $b['pct']);
    $show = array_slice($rows, 0, 8); ?>
    <div class="an-stats">
        <a href="<?= e(url('fees/debtors')) ?>"><b><?= e(Analytics::cedis($d['owed'])) ?></b><span>owed this term</span></a>
        <a href="<?= e(url('fees/debtors')) ?>"><b><?= (int) $d['debtors'] ?></b><span>bills not fully paid</span></a>
        <a href="<?= e(url('fees/debtors')) ?>" class="<?= $d['overdue'] ? 'is-warn' : '' ?>"><b><?= (int) $d['overdue'] ?></b><span>past due date</span></a>
        <?php if ($d['arrears'] > 0): ?><a href="<?= e(url('fees/debtors')) ?>"><b><?= e(Analytics::cedis($d['arrears'])) ?></b><span>from earlier terms</span></a><?php endif; ?>
    </div>
    <h3 class="an-h3">Collected by class <small>lowest first</small></h3>
    <?= Charts::hbars(array_map(fn($r) => [$r['name'], $r['pct'], null, $r['pct'] . '%'], $show), ['max' => 100]) ?>
    <?php if (count($rows) > count($show)): ?><a class="an-more" href="<?= e(url('analytics', ['tab' => 'fees', 'print' => 1] + (!empty($d['term']['id']) ? ['term' => $d['term']['id']] : []))) ?>" target="_blank">All <?= count($rows) ?> classes →</a><?php endif; ?>
<?php endif;
$body = ob_get_clean();
$note = null;
require __DIR__ . '/_panel.php';
