<?php $ct = 'canteen/wallets'; ?>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="xgrid-box" data-xgrid="canteen-wallets" data-xgrid-file="canteen-wallets">
<div class="page-head">
    <div><h1>Canteen wallets</h1></div>
    <div class="actions"><?= export_grid_menu() ?></div>
</div>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['student' => ['Student'], 'adm' => ['Admission no.'], 'class' => ['Class'], 'balance' => ['Balance', 'num', 'num'], 'limit' => ['Daily limit', 'num', 'num'], 'last' => ['Last used', 'date']]) ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
    <tr>
        <td data-c="student"><a href="<?= e(url('canteen/wallets/' . $r['id'])) ?>"><strong><?= e($r['last_name'] . ', ' . $r['first_name']) ?></strong></a></td>
        <td data-c="adm"><?= e($r['admission_no']) ?></td>
        <td data-c="class"><?= e($r['class_name'] ?? '') ?></td>
        <td data-c="balance" class="num" data-s="<?= e((string) (float) $r['balance']) ?>"><?= (float) $r['balance'] < 10 ? badge(Fees::money($r['balance']), 'warn') : e(Fees::money($r['balance'])) ?></td>
        <td data-c="limit" class="num" data-s="<?= $r['daily_limit'] !== null ? e((string) (float) $r['daily_limit']) : '' ?>"><?= $r['daily_limit'] !== null ? e(Fees::money($r['daily_limit'])) : '<span class="muted">none</span>' ?></td>
        <td data-c="last" data-s="<?= e((string) ($r['last_use'] ?? '')) ?>"><?= $r['last_use'] ? e(fmt_date($r['last_use'], 'j M H:i')) : '' ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No wallets yet.</p>
</div>
</div>
