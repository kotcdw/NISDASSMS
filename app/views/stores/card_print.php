<?php $fmt = fn($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.'); $docTitle = 'STORE LEDGER CARD'; $docSub = $item['name'] . ' (' . $item['unit'] . ')'; ?>
<div class="card-page doc-page">
    <?php require dirname(__DIR__) . '/fees/_doc_head.php'; ?>
    <table class="lines" style="width:100%">
        <tr><th style="text-align:left">Date</th><th style="text-align:left">Ref</th><th style="text-align:left">Received from / issued to</th><th>Received</th><th>Issued</th><th>Balance</th><th style="text-align:left">Recorded by</th></tr>
        <?php foreach ($moves as $m): $in = $m['kind'] === 'receive' || ($m['kind'] === 'adjust' && (float) $m['qty'] > 0); ?>
        <tr><td><?= e(fmt_date($m['moved_on'])) ?></td><td><?= e($m['reference'] ?? '') ?></td><td><?= e($m['party'] ?? '') ?><?= $m['kind'] === 'adjust' ? ' (correction: ' . e($m['note'] ?? '') . ')' : '' ?></td>
            <td style="text-align:right"><?= $in ? e($fmt(abs((float) $m['qty']))) : '' ?></td><td style="text-align:right"><?= !$in ? e($fmt(abs((float) $m['qty']))) : '' ?></td><td style="text-align:right"><?= e($fmt($m['balance'])) ?></td><td><?= e($m['by_name'] ?? '') ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$moves): ?><tr><td colspan="7">No entries.</td></tr><?php endif; ?>
    </table>
    <p style="margin-top:2rem">Storekeeper: ____________________ &nbsp;&nbsp; Checked by (Head / Bursar): ____________________ &nbsp;&nbsp; Date: __________</p>
</div>
