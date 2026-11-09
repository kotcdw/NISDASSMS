<div class="xgrid-box" data-xgrid="daily-cash" data-xgrid-file="daily-cash-<?= e($date) ?>">
<div class="page-head">
    <div><h1>Daily cash</h1></div>
    <div class="actions">
        <form method="get" action="<?= e(url('fees/daily')) ?>" class="list-pick" data-autosubmit>
            <input type="date" name="date" value="<?= e($date) ?>" max="<?= e(date('Y-m-d')) ?>" aria-label="Day">
        </form>
        <a class="btn btn-ghost" href="<?= e(url('fees/daily', ['date' => $date, 'print' => 1])) ?>" target="_blank" rel="noopener">Print</a>
        <?= export_grid_menu() ?>
    </div>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['receipt' => ['Receipt'], 'student' => ['Student'], 'adm' => ['Admission no.'], 'bill' => ['Bill'], 'method' => ['Method'], 'ref' => ['Reference'], 'by' => ['Received by'], 'amount' => ['Amount (GH₵)', 'num', 'num'], 'status' => ['Status']]) ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $rev = $r['status'] === 'reversed'; ?>
    <tr<?= $rev ? ' style="text-decoration:line-through;color:var(--muted)"' : '' ?>>
        <td data-c="receipt"><?= e($r['receipt_no']) ?></td>
        <td data-c="student"><?= e(trim($r['first_name'] . ' ' . $r['last_name'])) ?></td>
        <td data-c="adm"><?= e($r['admission_no'] === 'family' ? '' : $r['admission_no']) ?></td>
        <td data-c="bill"><?= e($r['invoice_no']) ?></td>
        <td data-c="method"><?= e(Fees::PAID_BY[$r['method']] ?? $r['method']) ?></td>
        <td data-c="ref"><?= e($r['reference'] ?? '') ?></td>
        <td data-c="by"><?= e($r['method'] === 'online' ? 'Online' : ($r['received_by_name'] ?? '')) ?></td>
        <td data-c="amount" class="num" data-s="<?= e((string) $r['amount']) ?>"><?= e(number_format((float) $r['amount'], 2)) ?></td>
        <td data-c="status"><?= $rev ? badge('Reversed', 'bad') : badge('Received', 'good') ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    <?php if ($rows): ?><tfoot><tr><td colspan="7"><strong>Total received (excluding reversed)</strong></td><td class="num"><strong><?= e(number_format($total, 2)) ?></strong></td><td></td></tr></tfoot><?php endif; ?>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No payments on this day.</p>
</div>
</div>
<div class="cols" style="margin-top:1rem">
    <div class="card"><h2>By method</h2>
        <?php foreach ($byMethod as $m => $amt): ?><p style="display:flex;justify-content:space-between;margin:0 0 .4rem"><span><?= e(Fees::PAID_BY[$m] ?? $m) ?></span><strong><?= e(Fees::money($amt)) ?></strong></p><?php endforeach; ?>
        <?php if (!$byMethod): ?><p class="muted">Nothing received.</p><?php endif; ?>
    </div>
    <div class="card"><h2>By cashier</h2>
        <?php foreach ($byUser as $u => $amt): ?><p style="display:flex;justify-content:space-between;margin:0 0 .4rem"><span><?= e($u) ?></span><strong><?= e(Fees::money($amt)) ?></strong></p><?php endforeach; ?>
        <?php if (!$byUser): ?><p class="muted">Nothing received.</p><?php endif; ?>
    </div>
</div>
