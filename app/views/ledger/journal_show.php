<?php $st = ['pending' => ['Waiting for approval', 'warn'], 'approved' => ['In the books', 'good'], 'rejected' => ['Rejected', 'bad']]; [$sl, $stt] = $st[$j['status']]; ?>
<div class="page-head">
    <div><div class="muted"><a href="<?= e(url('ledger/journals')) ?>">Manual journals</a></div><h1><?= e($j['jnl_no']) ?> <?= badge($sl, $stt) ?></h1>
    <p class="muted"><?= e(fmt_date($j['entry_date'])) ?> · <?= e($j['memo']) ?></p></div>
    <?php if ($entry): ?><div class="actions"><a class="btn" href="<?= e(url('ledger/entries/' . $entry)) ?>">See it in the journal</a></div><?php endif; ?>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="card" style="padding:0">
    <div class="table-wrap"><table data-nopage>
        <thead><tr><th>Account</th><th style="text-align:right">Debit</th><th style="text-align:right">Credit</th><th>Note</th></tr></thead>
        <tbody><?php foreach ($lines as $l): ?>
        <tr><td><code><?= e($l['code']) ?></code> <?= e($l['name']) ?></td>
            <td style="text-align:right"><?= (float) $l['debit'] ? e(number_format((float) $l['debit'], 2)) : '' ?></td>
            <td style="text-align:right"><?= (float) $l['credit'] ? e(number_format((float) $l['credit'], 2)) : '' ?></td><td><?= e($l['note'] ?? '') ?></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <p class="hint" style="padding:0 1rem 1rem">Typed by <?= e($j['drafted_name'] ?? '—') ?> on <?= e(fmt_date($j['drafted_at'], 'd M Y H:i')) ?><?php if ($j['decided_at']): ?> · <?= $j['status'] === 'approved' ? 'approved' : 'rejected' ?> by <?= e($j['decided_name'] ?? '—') ?> on <?= e(fmt_date($j['decided_at'], 'd M Y H:i')) ?><?= $j['decision_note'] ? ': ' . e($j['decision_note']) : '' ?><?php endif; ?>.</p>
</div>
<?php if ($j['status'] === 'pending' && can('ledger.approve')): ?>
<form method="post" action="<?= e(url('ledger/journals/' . $j['id'] . '/decide')) ?>" class="card form"><?= csrf_field() ?>
    <h2>Decide</h2>
    <label>Note<input name="note" maxlength="255" placeholder="Needed when rejecting"></label>
    <div class="actions"><button class="btn btn-primary" name="decision" value="approve" type="submit">Approve — put it in the books</button> <button class="btn btn-danger" name="decision" value="reject" type="submit">Reject</button></div>
</form>
<?php endif; ?>
