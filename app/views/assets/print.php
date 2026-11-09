<?php $docTitle = 'FIXED ASSET REGISTER'; $docSub = 'Year ' . $year; $t = ['cost' => 0, 'charge' => 0, 'accum' => 0, 'nbv' => 0]; ?>
<div class="card-page doc-page" style="max-width:none">
    <?php require dirname(__DIR__) . '/fees/_doc_head.php'; ?>
    <table class="rc-scores">
        <thead><tr><th>Tag no.</th><th class="l">Asset</th><th class="l">Category</th><th class="l">Location</th><th>Bought</th><th>Life</th><th>Cost</th><th>Wear <?= $year ?></th><th>Worn to date</th><th>Value 31 Dec</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): foreach (['charge', 'accum', 'nbv'] as $k) { $t[$k] += $r['v'][$k]; } $t['cost'] += (float) $r['cost']; ?><tr>
            <td><?= e($r['asset_no']) ?></td><td class="l"><?= e($r['name']) ?><?= in_array($r['status'], ['disposed', 'lost'], true) ? ' (' . e(Assets::STATUS[$r['status']][0]) . ' ' . e(fmt_date($r['disposed_on'], 'd/m/Y')) . ')' : '' ?></td>
            <td class="l"><?= e(Assets::CATEGORIES[$r['category']][0] ?? '') ?></td><td class="l"><?= e($r['location'] ?? '') ?></td>
            <td><?= e(fmt_date($r['purchase_date'], 'd/m/Y')) ?></td><td><?= (int) $r['life_years'] ?: '—' ?></td>
            <td style="text-align:right"><?= e(number_format((float) $r['cost'], 2)) ?></td><td style="text-align:right"><?= e(number_format((float) $r['v']['charge'], 2)) ?></td>
            <td style="text-align:right"><?= e(number_format($r['v']['accum'], 2)) ?></td><td style="text-align:right"><?= e(number_format($r['v']['nbv'], 2)) ?></td>
        </tr><?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="6" class="l"><strong>Total</strong></td><td style="text-align:right"><strong><?= e(number_format($t['cost'], 2)) ?></strong></td><td style="text-align:right"><strong><?= e(number_format($t['charge'], 2)) ?></strong></td><td style="text-align:right"><strong><?= e(number_format($t['accum'], 2)) ?></strong></td><td style="text-align:right"><strong><?= e(number_format($t['nbv'], 2)) ?></strong></td></tr></tfoot>
    </table>
    <p class="rc-key">Straight-line wear from the month bought. Printed <?= e(date('d M Y H:i')) ?>.</p>
    <div class="rc-sign"><div><span></span>Bursar / Accountant</div><div><span></span>Head Teacher</div><div></div></div>
</div>
