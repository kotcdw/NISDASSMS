<div class="page-head">
    <div><h1>Every entry</h1></div>
    <div class="actions"><?= export_menu(url('ledger/journal', ['export' => 1])) ?></div>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>
<?php foreach ($rows as $r): ?>
<div class="card je-card" style="padding:0">
    <div class="je-head"><span><a href="<?= e(url('ledger/entries/' . $r['id'])) ?>"><strong><?= e($r['ref'] ?: 'Entry #' . $r['id']) ?></strong></a> · <?= e($r['memo']) ?><?= (int) $r['is_change'] ? ' ' . badge('correction', 'warn') : '' ?></span>
        <span class="muted"><?= e(fmt_date($r['entry_date'])) ?> · <?= e(Ledger::SOURCES[$r['source']] ?? $r['source']) ?></span></div>
    <div class="table-wrap"><table data-nopage>
        <thead><tr><th>Account</th><th style="text-align:right;width:9rem">Debit</th><th style="text-align:right;width:9rem">Credit</th></tr></thead>
        <tbody><?php foreach ($lines[(int) $r['id']] ?? [] as $l): ?>
        <tr><td<?= (float) $l['credit'] ? ' style="padding-left:2rem"' : '' ?>><code><?= e($l['code']) ?></code> <?= e($l['name']) ?></td>
            <td style="text-align:right"><?= (float) $l['debit'] ? e(number_format((float) $l['debit'], 2)) : '' ?></td>
            <td style="text-align:right"><?= (float) $l['credit'] ? e(number_format((float) $l['credit'], 2)) : '' ?></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
</div>
<?php endforeach; ?>
<?php if (!$rows): ?><div class="card"><p class="muted" style="margin:0">No entries in these dates.</p></div><?php endif; ?>
<?= pager_links($pager) ?>
