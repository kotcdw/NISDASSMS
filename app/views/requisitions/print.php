<?php $docTitle = 'REQUISITION'; $docSub = $r['req_no'] . ' · ' . strip_tags(Requisition::STATUS[$r['status']][0]); ?>
<div class="card-page doc-page">
    <?php require dirname(__DIR__) . '/fees/_doc_head.php'; ?>
    <table class="rc-info">
        <tr><th>Requested by</th><td><?= e($r['requester']) ?> (<?= e(Auth::roleLabel($r['requester_role'])) ?>)</td><th>Date</th><td><?= e(fmt_date(substr((string) ($r['submitted_at'] ?? $r['created_at']), 0, 10))) ?></td></tr>
        <tr><th>Department</th><td><?= e($r['department'] ?? '') ?></td><th>Needed by</th><td><?= e(fmt_date($r['needed_by'])) ?></td></tr>
        <tr><th>Purpose</th><td colspan="3"><strong><?= e($r['title']) ?></strong><?= $r['purpose'] ? '<br>' . nl2br(e($r['purpose'])) : '' ?></td></tr>
        <tr><th>Expense type</th><td colspan="3"><?= e($r['category_name'] ?? '') ?></td></tr>
    </table>
    <table class="rc-scores">
        <thead><tr><th class="l">Item</th><th>Qty</th><th>Unit</th><th>Unit cost (GH₵)</th><th>Total (GH₵)</th></tr></thead>
        <tbody><?php foreach ($items as $it): ?><tr><td class="l"><?= e($it['description']) ?></td><td><?= e(rtrim(rtrim($it['quantity'], '0'), '.')) ?></td><td><?= e($it['unit'] ?? '') ?></td><td><?= e(number_format((float) $it['unit_cost'], 2)) ?></td><td><?= e(number_format((float) $it['quantity'] * (float) $it['unit_cost'], 2)) ?></td></tr><?php endforeach; ?></tbody>
        <tfoot>
            <tr><td class="l" colspan="4"><strong>Total requested</strong></td><td><strong><?= e(number_format((float) $r['amount'], 2)) ?></strong></td></tr>
            <?php if ($r['approved_amount'] !== null): ?><tr><td class="l" colspan="4"><strong>Approved</strong></td><td><strong><?= e(number_format((float) $r['approved_amount'], 2)) ?></strong></td></tr><?php endif; ?>
        </tfoot>
    </table>
    <table class="rc-info">
        <tr><th>Head Teacher</th><td><?= $r['approver_name'] ? e($r['approver_name']) . ' — approved ' . e(date('d M Y', strtotime((string) $r['approved_at']))) : '' ?></td></tr>
        <?php if ($r['needs_second'] || $r['second_name']): ?><tr><th>School Manager</th><td><?= $r['second_name'] ? e($r['second_name']) . ' — approved ' . e(date('d M Y', strtotime((string) $r['second_at']))) : '' ?></td></tr><?php endif; ?>
        <tr><th>Paid out</th><td><?= (float) $r['paid_amount'] > 0 ? e(Fees::money($r['paid_amount'])) . ($payment ? ' from ' . e($payment['account_name']) . ' on ' . e(fmt_date($payment['txn_date'])) : '') . ($r['paid_by_name'] ? ' by ' . e($r['paid_by_name']) : '') : '' ?></td></tr>
        <tr><th>Spent / change</th><td><?= $r['spent_amount'] !== null ? 'Spent ' . e(Fees::money($r['spent_amount'])) . ((float) $r['paid_amount'] - (float) $r['spent_amount'] > 0 ? ', change returned ' . e(Fees::money((float) $r['paid_amount'] - (float) $r['spent_amount'])) : '') : '' ?></td></tr>
    </table>
    <div class="rc-sign"><div><span></span>Requested by</div><div><span></span>Approved (Head Teacher)</div><div><span></span>Received payment</div></div>
</div>
