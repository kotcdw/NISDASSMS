<?php $gt = 'governance/policies'; $mg = can('governance.manage'); $p = $edit; $v = fn($k, $d = '') => old($k, $p[$k] ?? $d); $openForm = $p || old('title') !== '';
$tabs = []; foreach (['active' => 'Active', 'due' => 'Due for review', 'retired' => 'Retired'] as $k => $l) { $tabs[$k] = [$l, url('governance/policies', ['show' => $k])]; } ?>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="xgrid-box" data-xgrid="policies" data-xgrid-file="policy-register">
<div class="page-head">
    <div><h1>Policy register</h1></div>
    <div class="actions"><?= export_grid_menu() ?><?php if ($mg): ?><button type="button" class="btn btn-primary" data-help="policy-form" aria-expanded="<?= $openForm ? 'true' : 'false' ?>">+ Add a policy</button><?php endif; ?></div>
</div>
<?= card_tabs($tabs, isset($tabs[$show]) ? $show : 'active', 4, 'Policies to show') ?>
<?php if ($mg): ?>
<div class="card list-form" id="policy-form"<?= $openForm ? '' : ' hidden' ?>><h2><?= $p ? 'Edit policy' : 'Add a policy' ?></h2>
    <form method="post" action="<?= e(url($p ? 'governance/policies/' . $p['id'] : 'governance/policies')) ?>" class="form"><?= csrf_field() ?>
        <label class="required">Policy title <input name="title" required value="<?= e($v('title')) ?>" placeholder="e.g. Child protection policy"></label>
        <div class="grid-2">
            <label>Category <select name="category"><option value=""></option><?php foreach (Governance::POLICY_CATEGORIES as $c): ?><option<?= selected($v('category'), $c) ?>><?= e($c) ?></option><?php endforeach; ?></select></label>
            <label>Owner (who keeps it) <input name="owner" value="<?= e($v('owner')) ?>" placeholder="e.g. Head Teacher"></label>
        </div>
        <div class="grid-3">
            <label>Approved on <input type="date" name="approved_on" value="<?= e($v('approved_on')) ?>"></label>
            <label>Last reviewed <input type="date" name="last_reviewed" value="<?= e($v('last_reviewed')) ?>"></label>
            <label class="required">Review every (months) <input type="number" name="review_months" min="1" max="60" required value="<?= e((string) $v('review_months', 12)) ?>"></label>
        </div>
        <?php if ($p): ?><label>Status <select name="status"><option value="active"<?= selected($v('status'), 'active') ?>>Active</option><option value="retired"<?= selected($v('status'), 'retired') ?>>Retired</option></select></label><?php endif; ?>
        <label>Notes <textarea name="notes" rows="2"><?= e($v('notes')) ?></textarea></label>
        <div class="gate-actions"><button class="btn btn-primary" type="submit">Save policy</button><?php if ($p): ?><a class="btn btn-ghost" href="<?= e(url('governance/policies', ['show' => $show])) ?>">Cancel</a><?php else: ?><button type="button" class="btn btn-ghost" data-help="policy-form">Cancel</button><?php endif; ?></div>
    </form>
</div>
<?php endif; ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['policy' => ['Policy'], 'every' => ['Review every (months)', 'num', 'num'], 'category' => ['Category'], 'owner' => ['Owner'], 'last' => ['Last reviewed', 'date'], 'next' => ['Next review', 'date'], 'state' => ['Review status']]) ?><?= $mg ? '<th class="xg-act"></th>' : '' ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $late = $r['next_review'] < date('Y-m-d'); $soon = !$late && $r['next_review'] <= date('Y-m-d', strtotime('+' . Governance::REVIEW_DUE_DAYS . ' days')); ?>
    <tr>
        <td data-c="policy"><strong><?= e($r['title']) ?></strong></td>
        <td data-c="every" class="num"><?= (int) $r['review_months'] ?></td>
        <td data-c="category"><?= e($r['category'] ?? '') ?></td>
        <td data-c="owner"><?= e($r['owner'] ?? '') ?></td>
        <td data-c="last" data-s="<?= e((string) $r['last_reviewed']) ?>"><?= $r['last_reviewed'] ? e(fmt_date($r['last_reviewed'])) : '—' ?></td>
        <td data-c="next" data-s="<?= e($r['next_review']) ?>"><?= e(fmt_date($r['next_review'])) ?></td>
        <td data-c="state"><?= $r['status'] === 'active' ? ($late ? badge('Overdue', 'bad') : ($soon ? badge('Due soon', 'warn') : badge('Up to date', 'good'))) : badge('Retired', 'neutral') ?></td>
        <?php if ($mg): ?><td class="xg-act" style="white-space:nowrap">
            <?php if ($r['status'] === 'active'): ?><form method="post" action="<?= e(url('governance/policies/' . $r['id'] . '/reviewed')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="show" value="<?= e($show) ?>"><button class="btn btn-sm" type="submit">Reviewed today</button></form><?php endif; ?>
            <a class="btn btn-sm btn-ghost" href="<?= e(url('governance/policies', ['show' => $show, 'edit' => $r['id']])) ?>#policy-form">Edit</a></td><?php endif; ?>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No policies here.</p>
</div>
</div>
