<?php $gt = 'governance'; $pct = fn($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 1), '0'), '.') . '%'; ?>
<div class="page-head"><div><h1>Governance &amp; risk</h1></div>
    <?php if (can('governance.manage')): ?><div class="actions"><a class="btn btn-primary" href="<?= e(url('governance/risks/new')) ?>">+ Record a risk</a></div><?php endif; ?></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="grid-2">
    <div class="card"><h2>Coming board meetings</h2>
        <?php foreach ($meetings as $m): ?><p style="margin:.3rem 0"><a href="<?= e(url('governance/meetings/' . $m['id'])) ?>"><strong><?= e($m['title']) ?></strong></a><br><small class="muted"><?= e(fmt_date($m['meeting_date'], 'D j M Y')) ?><?= $m['start_time'] ? ' · ' . e(substr($m['start_time'], 0, 5)) : '' ?><?= $m['venue'] ? ' · ' . e($m['venue']) : '' ?></small></p><?php endforeach; ?>
        <?php if (!$meetings): ?><p class="muted">None scheduled. <a href="<?= e(url('governance/meetings')) ?>">Schedule one</a>.</p><?php endif; ?>
    </div>
    <div class="card"><h2>Policies due for review</h2>
        <?php foreach ($policies as $p): ?><p style="margin:.3rem 0"><?= e($p['title']) ?> <?= $p['next_review'] < date('Y-m-d') ? badge('Overdue ' . fmt_date($p['next_review'], 'j M Y'), 'bad') : badge('Due ' . fmt_date($p['next_review'], 'j M Y'), 'warn') ?></p><?php endforeach; ?>
        <?php if (!$policies): ?><p class="muted">✓ No policy review due in the next <?= Governance::REVIEW_DUE_DAYS ?> days.</p><?php endif; ?>
    </div>
</div>
<div class="card" style="padding:0"><h2 style="padding:1rem 1.2rem 0">Top open risks</h2><div class="table-wrap"><table>
    <tr><th>Ref</th><th>Risk</th><th>Score</th><th>Mitigation</th><th>Review</th></tr>
    <?php foreach ($risks as $r): $sc = $r['likelihood'] * $r['impact']; [$lv, $lt] = Governance::level($sc); [$ml, $mt] = Governance::MITIGATION[$r['mitigation_status']]; ?>
    <tr><td><a href="<?= e(url('governance/risks/' . $r['id'])) ?>"><?= e($r['ref_no']) ?></a></td><td><?= e($r['title']) ?></td><td><?= badge($sc . ' ' . $lv, $lt) ?></td><td><?= badge($ml, $mt) ?></td><td><?= $r['review_date'] ? e(fmt_date($r['review_date'])) : '—' ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$risks): ?><tr><td colspan="5" class="muted">No open risks recorded.</td></tr><?php endif; ?>
</table></div></div>
