<table class="cash-table rc-scores">
    <thead><tr><th>Receipt</th><th class="l">Student</th><th>Bill</th><th>Method</th><th>Reference</th><th>Received by</th><th>Amount (GH₵)</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $rev = $r['status'] === 'reversed'; ?>
        <tr<?= $rev ? ' style="text-decoration:line-through;color:#9ca3af"' : '' ?>>
            <td><?= e($r['receipt_no']) ?></td><td class="l"><?= e($r['first_name'] . ' ' . $r['last_name']) ?> <small>(<?= e($r['admission_no']) ?>)</small></td>
            <td><?= e($r['invoice_no']) ?></td><td><?= e(Fees::PAID_BY[$r['method']] ?? $r['method']) ?></td><td><?= e($r['reference'] ?? '') ?></td>
            <td><?= e($r['method'] === 'online' ? 'Online' : ($r['received_by_name'] ?? '')) ?></td><td style="text-align:right"><?= e(number_format((float) $r['amount'], 2)) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7">No payments on this day.</td></tr><?php endif; ?>
    </tbody>
    <tfoot><tr><td colspan="6" class="l"><strong>Total received (excluding reversed)</strong></td><td style="text-align:right"><strong><?= e(number_format($total, 2)) ?></strong></td></tr></tfoot>
</table>
