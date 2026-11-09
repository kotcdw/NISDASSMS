<?php $fmt = fn($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.'); $mg = can('stores.manage'); $openItem = old('name') !== ''; $openMove = !$openItem && old('qty') !== ''; ?>
<?php $low = array_filter($items, fn($i) => (float) $i['reorder_level'] > 0 && ($stock[(int) $i['id']] ?? 0) <= (float) $i['reorder_level']); if ($low): ?>
<div class="alert alert-warning">Low stock: <?= e(implode(', ', array_map(fn($i) => $i['name'] . ' (' . $fmt($stock[(int) $i['id']] ?? 0) . ')', $low))) ?></div><?php endif; ?>
<div class="xgrid-box" data-xgrid="store-items" data-xgrid-file="store-ledger">
<div class="page-head">
    <div><h1>Store ledger</h1></div>
    <div class="actions">
        <form method="get" action="<?= e(url('stores')) ?>" class="list-pick" data-autosubmit><?php if (isset($_GET['period']) && $period !== 'custom'): ?><input type="hidden" name="period" value="<?= e($period) ?>"><?php endif; ?><select name="category" aria-label="Category"><option value="">All categories</option><?php foreach (Registers::STORE_CATEGORIES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($cat, $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></form>
        <?php if ($mg): ?><button type="button" class="btn btn-ghost" data-help="new-item" aria-expanded="<?= $openItem ? 'true' : 'false' ?>">+ Add a store item</button><?php endif; ?>
        <?= export_grid_menu() ?>
        <?php if ($mg && $all): ?><button type="button" class="btn btn-primary" data-help="move-box" aria-expanded="<?= $openMove ? 'true' : 'false' ?>">+ Receive or issue</button><?php endif; ?>
    </div>
</div>
<?php if ($mg): ?>
<?php if ($all): ?><div id="move-box" class="list-form" style="padding:1rem;border-bottom:1px solid var(--line)"<?= $openMove ? '' : ' hidden' ?>><?php require __DIR__ . '/_move_form.php'; ?></div><?php endif; ?>
<form id="new-item" method="post" action="<?= e(url('stores/items')) ?>" class="card form list-form"<?= $openItem ? '' : ' hidden' ?>>
    <?= csrf_field() ?>
    <h2>Add a store item</h2>
    <label class="required">Name<input name="name" maxlength="150" required value="<?= e(old('name')) ?>" placeholder="e.g. A4 paper (ream)"></label>
    <div class="grid-2">
        <label>Category<select name="category"><?php foreach (Registers::STORE_CATEGORIES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(old('category', 'stationery'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
        <label>Unit<input name="unit" maxlength="30" value="<?= e(old('unit', 'piece')) ?>" placeholder="piece, ream, box, litre"></label>
        <label>Re-order at<input type="number" name="reorder_level" min="0" step="0.01" value="<?= e(old('reorder_level', '0')) ?>"></label>
        <label>Kept at<input name="location" maxlength="80" value="<?= e(old('location')) ?>" placeholder="e.g. Main store, shelf B"></label>
    </div>
    <div class="gate-actions"><button class="btn btn-primary" type="submit">Add item</button><button type="button" class="btn btn-ghost" data-help="new-item">Cancel</button></div>
</form>
<?php endif; ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['item' => ['Item'], 'category' => ['Category'], 'stock' => ['In stock', 'num', 'num'], 'unit' => ['Unit'], 'reorder' => ['Re-order at', 'num', 'num'], 'kept' => ['Kept at']]) ?></tr></thead>
    <tbody>
    <?php foreach ($items as $i): $b = $stock[(int) $i['id']] ?? 0; ?>
    <tr>
        <td data-c="item"><a href="<?= e(url('stores/items/' . $i['id'])) ?>"><?= e($i['name']) ?></a></td>
        <td data-c="category"><?= e(Registers::STORE_CATEGORIES[$i['category']] ?? '') ?></td>
        <td data-c="stock" class="num" data-s="<?= e((string) $b) ?>"><?= (float) $i['reorder_level'] > 0 && $b <= (float) $i['reorder_level'] ? badge($fmt($b), 'warn') : e($fmt($b)) ?></td>
        <td data-c="unit"><?= e($i['unit']) ?></td>
        <td data-c="reorder" class="num"><?= e($fmt($i['reorder_level'])) ?></td>
        <td data-c="kept"><?= e($i['location'] ?? '') ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $items ? ' hidden' : '' ?> data-xg-empty>No store items yet.</p>
</div>
</div>

<div class="xgrid-box" data-xgrid="store-moves" data-xgrid-file="store-entries-<?= e($period === 'custom' ? $from . '-to-' . $to : $period) ?>" style="margin-top:1.2rem">
<div class="page-head">
    <div><h2>Entries</h2></div>
    <div class="actions"><?= export_grid_menu() ?></div>
</div>
<?php $route = 'stores'; $keep = ['category' => $cat]; require __DIR__ . '/../registers/_range.php'; ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['date' => ['Date', 'date'], 'item' => ['Item'], 'what' => ['What'], 'qty' => ['Qty', 'num', 'num'], 'party' => ['From / to'], 'by' => ['By']]) ?></tr></thead>
    <tbody>
    <?php foreach ($recent as $m): ?>
    <tr>
        <td data-c="date" data-s="<?= e($m['moved_on']) ?>"><?= e(fmt_date($m['moved_on'])) ?></td>
        <td data-c="item"><?= e($m['name']) ?></td>
        <td data-c="what"><?= e(Registers::STORE_KINDS[$m['kind']] ?? '') ?></td>
        <td data-c="qty" class="num" data-s="<?= e((string) ($m['kind'] === 'issue' ? -abs((float) $m['qty']) : (float) $m['qty'])) ?>"><?= e(($m['kind'] === 'issue' ? '−' : ((float) $m['qty'] > 0 && $m['kind'] === 'adjust' ? '+' : '')) . $fmt($m['qty'])) ?></td>
        <td data-c="party"><?= e($m['party'] ?? '') ?></td>
        <td data-c="by"><?= e($m['by_name'] ?? '') ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $recent ? ' hidden' : '' ?> data-xg-empty>Nothing received or issued in this period.</p>
</div>
</div>
