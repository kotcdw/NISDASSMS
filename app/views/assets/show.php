<?php [$sl, $st] = Assets::STATUS[$a['status']]; $mg = can('assets.manage'); $gone = in_array($a['status'], ['disposed', 'lost'], true); ?>
<div class="page-head">
    <div><p class="breadcrumb"><a href="<?= e(url('fixed-assets')) ?>">Fixed assets</a> › <?= e($a['asset_no']) ?></p>
        <h1><?= e($a['name']) ?> <?= badge($sl, $st) ?></h1>
        <p class="muted"><?= e(Assets::CATEGORIES[$a['category']][0] ?? '') ?> · <?= e($a['location'] ?? 'no location') ?><?= $a['holder_name'] ? ' · held by ' . e($a['holder_name']) : '' ?> · condition <?= e(Assets::CONDITION[$a['condition_state']][0]) ?></p></div>
    <?php if ($mg && !$gone): ?><div class="actions"><details class="menu-btn"><summary class="btn">Tools ▾</summary><div><a href="#" data-help="asset-edit">Edit / move</a><a href="#" data-help="asset-dispose" class="danger">Dispose, sell or report lost</a></div></details></div><?php endif; ?>
</div>
<?php if ($mg && !$gone): ?>
<div id="asset-edit" class="help-box form-center" hidden>
    <form method="post" action="<?= e(url('fixed-assets/' . $a['id'])) ?>" class="card form"><?= csrf_field() ?><h2>Edit / move</h2>
        <?php require __DIR__ . '/_fields.php'; ?>
        <div class="vf-foot"><span></span><span style="display:flex;gap:.5rem"><button type="button" class="btn btn-ghost" data-help="asset-edit">Close</button><button class="btn btn-primary" type="submit">Save</button></span></div>
    </form>
</div>
<div id="asset-dispose" class="help-box form-center" hidden>
    <form method="post" action="<?= e(url('fixed-assets/' . $a['id'] . '/dispose')) ?>" class="card form" data-confirm="Take “<?= e($a['name']) ?>” off the register?"><?= csrf_field() ?><h2>Take off the register</h2>
        <div class="grid-2">
            <label>What happened<select name="status"><option value="disposed">Disposed / sold / written off</option><option value="lost">Lost or stolen</option></select></label>
            <label>Date<input type="date" name="disposed_on" value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>"></label>
            <label>Sold for (GH₵, 0 if not sold)<input name="disposal_amount" inputmode="decimal" value="0"></label>
            <label>Money paid into<select name="account_id"><?php foreach ($accounts as $ac): ?><option value="<?= (int) $ac['id'] ?>"><?= e($ac['name']) ?></option><?php endforeach; ?></select></label>
        </div>
        <label class="required">Details<input name="disposal_note" required maxlength="255" placeholder="e.g. Broken beyond repair — Board approval 12/10; or police report no."></label>
        <div class="vf-foot"><span></span><span style="display:flex;gap:.5rem"><button type="button" class="btn btn-ghost" data-help="asset-dispose">Close</button><button class="btn btn-danger" type="submit">Take off register</button></span></div>
    </form>
</div>
<?php endif; ?>
<div class="cols">
    <div>
        <div class="card" style="padding:0"><div class="table-wrap"><table>
            <tr><th>Tag no.</th><td><?= e($a['asset_no']) ?><?= $a['serial_no'] ? ' · S/N ' . e($a['serial_no']) : '' ?></td></tr>
            <tr><th>Bought / received</th><td><?= e(fmt_date($a['purchase_date'])) ?><?= $a['supplier_name'] ? ' from ' . e($a['supplier_name']) : '' ?> · paid for by <?= e(Assets::FUNDS[$a['funded_by']] ?? $a['funded_by']) ?></td></tr>
            <tr><th>Cost</th><td><?= e(Fees::money($a['cost'])) ?></td></tr>
            <tr><th>Wear (depreciation)</th><td><?= (int) $a['life_years'] > 0 ? e(Fees::money($v['annual'])) . ' a year over ' . (int) $a['life_years'] . ' years' : 'Not depreciated' ?></td></tr>
            <tr><th>Worn so far</th><td><?= e(Fees::money($v['accum'])) ?></td></tr>
            <tr><th>Value now</th><td><strong><?= e(Fees::money($v['nbv'])) ?></strong></td></tr>
            <?php if ($gone): ?><tr><th><?= e($sl) ?></th><td><?= e(fmt_date($a['disposed_on'])) ?> — <?= e($a['disposal_note']) ?><?= $a['disposal_amount'] ? ' · sold for ' . e(Fees::money($a['disposal_amount'])) : '' ?></td></tr><?php endif; ?>
            <?php if ($a['notes']): ?><tr><th>Notes</th><td><?= e($a['notes']) ?></td></tr><?php endif; ?>
        </table></div></div>
        <div class="card"><h2>History</h2>
            <?php foreach ($log as $l): ?><p style="margin:0 0 .45rem"><small class="muted"><?= e(fmt_date(substr($l['created_at'], 0, 10), 'j M Y')) ?> · <?= e($l['by_name'] ?? '') ?></small><br><?= e(ucfirst($l['action'])) ?><?= $l['note'] ? ': ' . e($l['note']) : '' ?></p><?php endforeach; ?>
        </div>
    </div>
    <div>
        <div class="card"><h2>Value by year</h2>
            <div class="table-wrap"><table data-nopage>
                <thead><tr><th>Year</th><th class="num">Wear</th><th class="num">Value at 31 Dec</th></tr></thead>
                <tbody><?php foreach ($sched as $s): ?><tr><td><?= $s['year'] ?></td><td class="num"><?= e(number_format((float) $s['charge'], 2)) ?></td><td class="num"><?= e(number_format($s['nbv'], 2)) ?></td></tr><?php endforeach; ?></tbody>
            </table></div>
        </div>
    </div>
</div>
