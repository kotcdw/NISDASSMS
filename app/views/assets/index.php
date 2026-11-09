<?php
$tabs = [];
foreach (['current' => 'In use & under repair', 'gone' => 'Disposed / lost', 'all' => 'Everything'] as $k => $l) { $tabs[$k] = [$l, url('fixed-assets', array_filter(['show' => $k, 'category' => $cat]))]; }
?>
<div class="xgrid-box" data-xgrid="assets" data-xgrid-file="fixed-assets">
<div class="page-head">
    <div><h1>Fixed assets</h1></div>
    <div class="actions">
        <form method="get" action="<?= e(url('fixed-assets')) ?>" class="list-pick" data-autosubmit>
            <input type="hidden" name="show" value="<?= e($show) ?>">
            <select name="category" aria-label="Category"><option value="">All categories</option><?php foreach (Assets::CATEGORIES as $k => [$l]): ?><option value="<?= $k ?>"<?= selected($k, $cat) ?>><?= e($l) ?></option><?php endforeach; ?></select>
        </form>
        <details class="menu-btn"><summary class="btn btn-ghost">Print ▾</summary><div>
            <a href="<?= e(url('fixed-assets/print')) ?>" target="_blank" rel="noopener">Register &amp; depreciation <?= date('Y') ?></a>
            <a href="<?= e(url('fixed-assets/print', ['year' => date('Y') - 1])) ?>" target="_blank" rel="noopener">Register &amp; depreciation <?= date('Y') - 1 ?></a>
        </div></details>
        <?= export_grid_menu() ?>
        <?php if (can('assets.manage')): ?><a class="btn btn-primary" href="<?= e(url('fixed-assets/new')) ?>">+ Add asset</a><?php endif; ?>
    </div>
</div>
<?= card_tabs($tabs, $show, 4, 'Assets to show') ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['no' => ['Tag no.'], 'name' => ['Asset'], 'serial' => ['Serial no.'], 'cat' => ['Category'], 'where' => ['Location'], 'holder' => ['Held by'], 'date' => ['Bought', 'date'], 'cost' => ['Cost (GH₵)', 'num', 'num'], 'nbv' => ['Value now (GH₵)', 'num', 'num'], 'cond' => ['Condition']]) ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $a): ?>
    <tr<?= in_array($a['status'], ['disposed', 'lost'], true) ? ' style="opacity:.6"' : '' ?>>
        <td data-c="no"><a href="<?= e(url('fixed-assets/' . $a['id'])) ?>"><?= e($a['asset_no']) ?></a></td>
        <td data-c="name"><a href="<?= e(url('fixed-assets/' . $a['id'])) ?>"><?= e($a['name']) ?></a></td>
        <td data-c="serial"><?= e($a['serial_no'] ?? '') ?></td>
        <td data-c="cat"><?= e(Assets::CATEGORIES[$a['category']][0] ?? $a['category']) ?></td>
        <td data-c="where"><?= e($a['location'] ?? '') ?></td>
        <td data-c="holder"><?= e($a['holder_name'] ?? '') ?></td>
        <td data-c="date" data-s="<?= e((string) $a['purchase_date']) ?>"><?= e(fmt_date($a['purchase_date'], 'M Y')) ?></td>
        <td data-c="cost" class="num" data-s="<?= (float) $a['cost'] ?>"><?= e(number_format((float) $a['cost'], 2)) ?></td>
        <td data-c="nbv" class="num" data-s="<?= (float) $a['v']['nbv'] ?>"><?= e(number_format($a['v']['nbv'], 2)) ?></td>
        <td data-c="cond"><?= $a['status'] === 'in_use' ? badge(Assets::CONDITION[$a['condition_state']][0], Assets::CONDITION[$a['condition_state']][1]) : badge(Assets::STATUS[$a['status']][0], Assets::STATUS[$a['status']][1]) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No assets recorded<?= $cat ? ' in this category' : '' ?>.</p>
</div>
</div>
