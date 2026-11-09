<div class="page-head">
    <div><div class="muted"><a href="<?= e(url('ledger/journal')) ?>">Journal</a> › <?= e(Ledger::SOURCES[$e['source']] ?? $e['source']) ?></div><h1><?= e($e['ref'] ?: 'Entry #' . $e['id']) ?></h1>
    <p class="muted"><?= e(fmt_date($e['entry_date'])) ?> · <?= e($e['memo']) ?></p></div>
    <?php if ($link): ?><div class="actions"><a class="btn" href="<?= e($link) ?>">Open the <?= e(strtolower(Ledger::SOURCES[$e['source']] ?? 'record')) ?></a></div><?php endif; ?>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="card" style="padding:0">
    <div class="table-wrap"><table data-nopage>
        <thead><tr><th>Account</th><th style="text-align:right">Debit</th><th style="text-align:right">Credit</th></tr></thead>
        <tbody><?php $td = 0; $tc = 0; foreach ($lines as $l): $td += (float) $l['debit']; $tc += (float) $l['credit']; ?>
        <tr><td<?= (float) $l['credit'] ? ' style="padding-left:2rem"' : '' ?>><a href="<?= e(url('ledger/accounts/' . $l['account_id'])) ?>"><code><?= e($l['code']) ?></code> <?= e($l['name']) ?></a></td>
            <td style="text-align:right"><?= (float) $l['debit'] ? e(number_format((float) $l['debit'], 2)) : '' ?></td>
            <td style="text-align:right"><?= (float) $l['credit'] ? e(number_format((float) $l['credit'], 2)) : '' ?></td></tr>
        <?php endforeach; ?></tbody>
        <tfoot><tr><td><strong>Total</strong></td><td style="text-align:right"><strong><?= e(number_format($td, 2)) ?></strong></td><td style="text-align:right"><strong><?= e(number_format($tc, 2)) ?></strong></td></tr></tfoot>
    </table></div>
    <p class="hint" style="padding:0 1rem 1rem">Written by the system<?= $e['by_name'] ? ' after a change by ' . e($e['by_name']) : '' ?> on <?= e(fmt_date($e['created_at'], 'd M Y H:i')) ?>.</p>
</div>
<?php if (count($history) > 1): ?>
<div class="card"><h2>All entries for this record</h2>
    <div class="table-wrap"><table data-nopage><tbody><?php foreach ($history as $h): ?>
        <tr><td><?= e(fmt_date($h['entry_date'])) ?></td><td><a href="<?= e(url('ledger/entries/' . $h['id'])) ?>"><?= e($h['memo']) ?></a></td><td style="text-align:right"><?= e(number_format((float) $h['total'], 2)) ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
</div>
<?php endif; ?>
