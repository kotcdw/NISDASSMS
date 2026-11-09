<?php
$tabs = [];
foreach (['credit' => 'With credit', 'owing' => 'Owing', 'all' => 'All families'] as $k => $l) {
    $tabs[$k] = [$l, url('fees/families', array_filter(['show' => $k === 'credit' ? '' : $k, 'q' => $q]))];
}
?>
<div class="xgrid-box" data-xgrid="families" data-xgrid-file="family-accounts">
<div class="page-head">
    <div><h1>Family accounts</h1></div>
    <div class="actions">
        <span class="muted">Credit held: <strong><?= e(Fees::money($totalCredit)) ?></strong></span>
        <?= export_grid_menu() ?>
    </div>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>
<?= card_tabs($tabs, $show, 3, 'Families to show') ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['name' => ['Parent / guardian'], 'phone' => ['Phone'], 'kids' => ['Children in school', 'num', 'num'], 'owing' => ['Owing (GH₵)', 'num', 'num'], 'credit' => ['Credit (GH₵)', 'num', 'num']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
    <tr>
        <td data-c="name"><a href="<?= e(url('fees/family/' . $r['id'])) ?>"><?= e($r['name']) ?></a></td>
        <td data-c="phone"><?= e($r['phone']) ?></td>
        <td data-c="kids" class="num"><?= (int) $r['kids'] ?></td>
        <td data-c="owing" class="num" data-s="<?= e($r['owing']) ?>"><?= (float) $r['owing'] > 0.004 ? '<span style="color:var(--bad)">' . e(number_format((float) $r['owing'], 2)) . '</span>' : '—' ?></td>
        <td data-c="credit" class="num" data-s="<?= e($r['credit']) ?>"><?= (float) $r['credit'] > 0.004 ? '<strong style="color:var(--good)">' . e(number_format((float) $r['credit'], 2)) . '</strong>' : '—' ?></td>
        <td class="xg-act"><a class="btn btn-sm" href="<?= e(url('fees/family/' . $r['id'])) ?>">Open</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty><?= $show === 'credit' ? 'No family has credit.' : ($show === 'owing' ? 'No family owes fees.' : 'No families yet.') ?></p>
</div>
</div>
