<?php $void = $t['status'] === 'void'; ?>
<div class="page-head">
    <div><p class="breadcrumb"><a href="<?= e(url('finance/transactions')) ?>">Transactions</a> › <?= e($t['txn_no']) ?></p>
    <h1><?= e(Finance::KINDS[$t['kind']]) ?> · <?= e(Fees::money($t['amount'])) ?> <?= $void ? badge('Void', 'bad') : '' ?></h1>
    <p class="muted"><?= e($t['description']) ?></p></div>
</div>
<div class="cols">
    <div class="card">
        <table>
            <tr><th style="width:30%">Date</th><td><?= e(fmt_date($t['txn_date'])) ?></td></tr>
            <tr><th><?= $t['kind'] === 'transfer' ? 'From' : 'Account' ?></th><td><?= e($t['account_name']) ?></td></tr>
            <?php if ($t['kind'] === 'transfer'): ?><tr><th>To</th><td><?= e($t['to_account_name']) ?></td></tr><?php else: ?><tr><th>Category</th><td><?= e($t['category_name'] ?? '') ?></td></tr><?php endif; ?>
            <tr><th><?= $t['kind'] === 'income' ? 'Received from' : 'Paid to' ?></th><td><?= e($t['party'] ?? '') ?></td></tr>
            <tr><th>Reference</th><td><?= e($t['reference'] ?? '') ?></td></tr>
            <tr><th>Recorded by</th><td><?= e($t['created_by_name'] ?? '') ?> · <?= e(date('d M Y H:i', strtotime($t['created_at']))) ?></td></tr>
            <tr><th>Came from</th><td><?= e(Finance::SOURCES[$t['source']] ?? $t['source']) ?>
                <?php if ($t['source'] === 'fee_payment'): ?> — <a href="<?= e(url('fees/receipts/' . $t['source_id'])) ?>" target="_blank" rel="noopener">receipt</a><?php endif; ?>
                <?php if (in_array($t['source'], ['requisition', 'req_refund'], true)): ?> — <a href="<?= e(url('requisitions/' . $t['source_id'])) ?>">requisition</a><?php endif; ?></td></tr>
            <?php if ($t['attachment']): ?><tr><th>Document</th><td><a href="<?= e(url('finance/transactions/' . $t['id'] . '/file')) ?>"><?= e($t['attachment_name']) ?></a></td></tr><?php endif; ?>
            <?php if ($void): ?><tr><th>Voided</th><td><?= e($t['void_reason']) ?> — <?= e($t['voided_by_name'] ?? '') ?>, <?= e(date('d M Y H:i', strtotime((string) $t['voided_at']))) ?></td></tr><?php endif; ?>
        </table>
    </div>
    <div>
        <?php if (!$void && can('finance.void') && $t['source'] === 'manual'): ?>
        <div class="card">
            <h2>Void this entry</h2>
            <form method="post" action="<?= e(url('finance/transactions/' . $t['id'] . '/void')) ?>" class="form" data-confirm="Void <?= e($t['txn_no']) ?>?">
                <?= csrf_field() ?><label>Reason<input name="reason" required maxlength="255"></label>
                <button class="btn btn-danger" type="submit">Void</button>
            </form>
        </div>
        <?php elseif (!$void && $t['source'] !== 'manual'): ?>
        <div class="card"><p class="hint">This entry is controlled by <?= e(Finance::SOURCES[$t['source']] ?? '') ?>. To correct it, change it there (for example reverse the fee receipt).</p></div>
        <?php endif; ?>
    </div>
</div>
