<?php $gt = 'governance/risks';
$tabs = []; foreach (['open' => 'Open risks', 'closed' => 'Closed risks'] as $k => $l) { $tabs[$k] = [$l, url('governance/risks', ['show' => $k])]; } ?>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="xgrid-box" data-xgrid="risks" data-xgrid-file="risk-register">
<div class="page-head">
    <div><h1>Risk register</h1></div>
    <div class="actions"><?= export_grid_menu() ?><?php if (can('governance.manage')): ?><a class="btn btn-primary" href="<?= e(url('governance/risks/new')) ?>">+ Record a risk</a><?php endif; ?></div>
</div>
<?= card_tabs($tabs, $show === 'closed' ? 'closed' : 'open', 4, 'Risks to show') ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['ref' => ['Ref'], 'risk' => ['Risk'], 'type' => ['Type'], 'like' => ['Likelihood', 'num', 'num'], 'impact' => ['Impact', 'num', 'num'], 'score' => ['Score', 'num'], 'mit' => ['Mitigation'], 'owner' => ['Owner'], 'review' => ['Review', 'date'], 'late' => ['Review overdue']]) ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $sc = $r['likelihood'] * $r['impact']; [$lv, $lt] = Governance::level($sc); [$ml, $mt] = Governance::MITIGATION[$r['mitigation_status']]; ?>
    <tr>
        <td data-c="ref"><a href="<?= e(url('governance/risks/' . $r['id'])) ?>"><?= e($r['ref_no']) ?></a></td>
        <td data-c="risk"><strong><?= e($r['title']) ?></strong></td>
        <td data-c="type"><?= e(Governance::RISK_CATEGORIES[$r['category']] ?? $r['category']) ?></td>
        <td data-c="like" class="num"><?= (int) $r['likelihood'] ?></td>
        <td data-c="impact" class="num"><?= (int) $r['impact'] ?></td>
        <td data-c="score" data-s="<?= (int) $sc ?>"><?= badge($sc . ' ' . $lv, $lt) ?></td>
        <td data-c="mit"><?= badge($ml, $mt) ?></td>
        <td data-c="owner"><?= e($r['owner'] ?? '') ?></td>
        <td data-c="review" data-s="<?= e((string) $r['review_date']) ?>"><?= $r['review_date'] ? e(fmt_date($r['review_date'])) : '—' ?></td>
        <td data-c="late"><?= $r['review_date'] && $r['status'] === 'open' && $r['review_date'] < date('Y-m-d') ? badge('Overdue', 'bad') : '' ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No risks here.</p>
</div>
</div>
