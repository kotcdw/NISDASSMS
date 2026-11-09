<?php
$kind = (string) ($_GET['kind'] ?? 'all');
$keep = array_diff_key($_GET, ['kind' => 1, 'page' => 1, 'export' => 1, 'format' => 1]);
$tabs = [];
foreach (['all' => 'All entries', 'income' => 'Money in', 'expense' => 'Money out', 'transfer' => 'Transfers', 'refund' => 'Refunds'] as $k => $l) {
    $tabs[$k] = [$l, url('finance/transactions', $k === 'all' ? $keep : $keep + ['kind' => $k])];
}
if (!isset($tabs[$kind])) $kind = 'all';
?>
<div class="xgrid-box" data-xgrid="fin-entries" data-xgrid-file="cash-book-entries">
<div class="page-head">
    <div><h1>All entries</h1></div>
    <div class="actions">
        <?= export_menu(url('finance/transactions', $keep + ($kind !== 'all' ? ['kind' => $kind] : []) + ['export' => 1]), 'Download all', 'btn btn-ghost') ?>
        <?= export_grid_menu() ?>
    </div>
    <?php require __DIR__ . '/_actions.php'; ?>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>
<?= card_tabs($tabs, $kind, 4, 'Entries to show') ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['date' => ['Date', 'date'], 'no' => ['No.'], 'details' => ['Details'], 'party' => ['Paid to / from'], 'category' => ['Category'], 'account' => ['Account'], 'amount' => ['GH₵', 'num', 'num'], 'status' => ['Status']]) ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $t): $void = $t['status'] === 'void'; $in = in_array($t['kind'], ['income', 'refund'], true); $tr = $t['kind'] === 'transfer'; ?>
    <tr<?= $void ? ' style="opacity:.55;text-decoration:line-through"' : '' ?>>
        <td data-c="date" data-s="<?= e($t['txn_date']) ?>" style="white-space:nowrap"><?= e(fmt_date($t['txn_date'])) ?></td>
        <td data-c="no"><a href="<?= e(url('finance/transactions/' . $t['id'])) ?>"><?= e($t['txn_no']) ?></a></td>
        <td data-c="details" class="audit-details"><?= e($t['description']) ?></td>
        <td data-c="party"><?= e($t['party'] ?? '') ?></td>
        <td data-c="category"><?= e($tr ? 'Transfer to ' . ($t['to_account_name'] ?? '') : ($t['category_name'] ?? '')) ?></td>
        <td data-c="account"><?= e($t['account_name']) ?></td>
        <td data-c="amount" class="num" data-s="<?= $tr ? (float) $t['amount'] : ($in ? '' : '-') . (float) $t['amount'] ?>" style="white-space:nowrap;color:<?= $tr ? 'inherit' : ($in ? 'var(--good)' : 'var(--bad)') ?>"><?= $tr ? '' : ($in ? '+' : '−') ?><?= e(number_format((float) $t['amount'], 2)) ?></td>
        <td data-c="status"><?= $void ? badge('Void', 'bad') : badge('Posted', 'good') ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No entries here.</p>
</div>
</div>
<div class="list-pager"><?= pager_links($pager) ?></div>
