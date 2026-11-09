<?php $docTitle = 'GO-LIVE CHECKLIST'; $docSub = date('d M Y'); ?>
<div class="card-page doc-page">
    <?php require dirname(__DIR__) . '/fees/_doc_head.php'; ?>
    <table class="rc-scores"><thead><tr><th class="l">Check</th><th class="l">Status</th></tr></thead><tbody>
    <?php foreach ($checks as $c): ?><tr><td class="l"><?= e($c['group'] . ' — ' . $c['label']) ?></td><td class="l"><?= $c['ok'] === true ? 'Ready' : ($c['ok'] === false ? 'FIX: ' : 'Check: ') ?><?= $c['ok'] === true ? '' : e($c['detail']) ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <table class="rc-scores"><thead><tr><th class="l">Decision</th><th>Confirmed</th></tr></thead><tbody>
    <?php foreach (GoLive::CONFIRMATIONS as $k => [$label]): $t = $ticks['confirm:' . $k] ?? null; ?><tr><td class="l"><?= e($label) ?></td><td><?= $t && $t['done_at'] ? e(date('d M Y', strtotime($t['done_at'] . ' UTC'))) . ' (' . e($t['name'] ?? '') . ')' : '☐' ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <table class="rc-scores"><thead><tr><th class="l">Trial-run task</th><th class="l">Expected result</th><th>Passed</th></tr></thead><tbody>
    <?php foreach (GoLive::TRIAL as $who => $items): foreach ($items as $k => [$label, $detail]): $t = $ticks['trial:' . $k] ?? null; ?><tr><td class="l"><strong><?= e($who) ?>:</strong> <?= e($label) ?></td><td class="l"><small><?= e($detail) ?></small></td><td><?= $t && $t['done_at'] ? '✓' : '☐' ?></td></tr><?php endforeach; endforeach; ?>
    </tbody></table>
    <div class="rc-sign"><div><span></span>Head Teacher</div><div><span></span>Bursar</div><div><span></span>School Manager</div></div>
</div>
