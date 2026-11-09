<?php
$cardTabs = [];
foreach ($tabs as $k => $label) { $cardTabs[$k] = [$label, url('requisitions', ['tab' => $k])]; }
?>
<div class="xgrid-box" data-xgrid="requisitions" data-xgrid-file="requisitions">
<div class="page-head">
    <div><h1>Requests</h1></div>
    <div class="actions"><?= export_grid_menu() ?><a class="btn btn-primary" href="<?= e(url('requisitions/create')) ?>">+ New requisition</a></div>
</div>
<?php require dirname(__DIR__) . '/purchasing/_nav.php'; ?>
<?php if (count($cardTabs) > 1): ?><?= card_tabs($cardTabs, $tab, 4, 'Requests to show') ?><?php endif; ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['no' => ['No.'], 'title' => ['Title'], 'dept' => ['Department'], 'by' => ['Requested by'], 'cat' => ['Category'], 'amount' => ['Amount (GH₵)', 'num', 'num'], 'status' => ['Status'], 'needed' => ['Needed by', 'date'], 'asked' => ['First asked (GH₵)', 'num', 'num']]) ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $amt = (float) ($r['approved_amount'] ?? $r['amount']); $cut = $r['approved_amount'] !== null && (float) $r['approved_amount'] < (float) $r['amount']; ?>
    <tr>
        <td data-c="no"><a href="<?= e(url('requisitions/' . $r['id'])) ?>"><?= e($r['req_no']) ?></a></td>
        <td data-c="title"><?= e($r['title']) ?></td>
        <td data-c="dept"><?= e($r['department'] ?? '') ?></td>
        <td data-c="by"><?= e($r['requester']) ?></td>
        <td data-c="cat"><?= e($r['category_name'] ?? '') ?></td>
        <td data-c="amount" class="num" data-s="<?= $amt ?>"><?= e(number_format($amt, 2)) ?></td>
        <td data-c="status"><?= Requisition::badge($r['status']) ?></td>
        <td data-c="needed" data-s="<?= e((string) $r['needed_by']) ?>"><?= e(fmt_date($r['needed_by'])) ?></td>
        <td data-c="asked" class="num" data-s="<?= $cut ? (float) $r['amount'] : '' ?>"><?= $cut ? e(number_format((float) $r['amount'], 2)) : '' ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty><?= $tab === 'approve' ? 'Nothing waiting for your approval.' : ($tab === 'pay' ? 'Nothing to pay or close.' : 'No requisitions yet.') ?></p>
</div>
</div>
<div class="list-pager"><?= pager_links($pager) ?></div>
