<?php
$stLabel = ['initiated' => 'Waiting', 'success' => 'Paid', 'failed' => 'Failed'];
$stTone = ['initiated' => 'warn', 'success' => 'good', 'failed' => 'bad'];
?>
<div class="xgrid-box" data-xgrid="online-payments" data-xgrid-file="online-payments">
<div class="page-head">
    <div><h1>Online payments</h1></div>
    <div class="actions"><?= export_grid_menu() ?></div>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['when' => ['When', 'date'], 'ref' => ['Reference'], 'gateway' => ['Gateway'], 'student' => ['Student'], 'bill' => ['Bill'], 'phone' => ['Phone'], 'amount' => ['Amount (GH₵)', 'num', 'num'], 'status' => ['Status'], 'receipt' => ['Receipt'], 'note' => ['Gateway message']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $o): ?>
    <tr>
        <td data-c="when" data-s="<?= e($o['created_at']) ?>"><?= e(date('d M Y H:i', strtotime($o['created_at']))) ?></td>
        <td data-c="ref"><code><?= e($o['reference']) ?></code></td>
        <td data-c="gateway"><?= e(ucfirst($o['gateway'])) ?></td>
        <td data-c="student"><?= e($o['first_name'] . ' ' . $o['last_name']) ?></td>
        <td data-c="bill"><a href="<?= e(url('fees/invoices/' . $o['invoice_id'])) ?>"><?= e($o['invoice_no']) ?></a></td>
        <td data-c="phone"><?= e($o['payer_phone'] ?? '') ?></td>
        <td data-c="amount" class="num" data-s="<?= e((string) $o['amount']) ?>"><?= e(number_format((float) $o['amount'], 2)) ?></td>
        <td data-c="status"><?= badge($stLabel[$o['status']], $stTone[$o['status']]) ?></td>
        <td data-c="receipt"><?= $o['receipt_no'] ? '<a href="' . e(url('fees/receipts/' . $o['payment_id'])) . '" target="_blank" rel="noopener">' . e($o['receipt_no']) . '</a>' : '' ?></td>
        <td data-c="note" class="audit-details"><?= $o['gateway_message'] && $o['status'] !== 'success' ? '<small>' . e($o['gateway_message']) . '</small>' : '' ?></td>
        <td class="xg-act"><?php if ($o['status'] === 'initiated'): ?><form method="post" action="<?= e(url('fees/online/' . $o['id'] . '/check')) ?>"><?= csrf_field() ?><button class="btn btn-sm" type="submit">Check now</button></form><?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No online payments yet.</p>
</div>
</div>
<div class="list-pager"><?= pager_links($pager) ?></div>
