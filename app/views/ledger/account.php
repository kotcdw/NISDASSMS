<div class="page-head">
    <div><div class="muted"><a href="<?= e(url('ledger')) ?>">The books</a> › <?= e(Ledger::TYPES[$a['type']]) ?></div><h1><code><?= e($a['code']) ?></code> <?= e($a['name']) ?></h1></div>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="actions" style="margin:0 0 .8rem"><?= export_menu(url('ledger/accounts/' . $a['id'], ['from' => $from, 'to' => $to, 'export' => 1]), 'Export', 'btn btn-ghost') ?></div>
<div class="card" style="padding:0">
    <div class="table-wrap"><table>
        <thead><tr><th>Date</th><th>Ref.</th><th>Details</th><th style="text-align:right">Debit</th><th style="text-align:right">Credit</th><th style="text-align:right">Balance</th></tr></thead>
        <tbody>
        <tr><td><?= e(fmt_date($from)) ?></td><td></td><td><em>Balance brought forward</em></td><td></td><td></td><td style="text-align:right"><strong><?= e(number_format($opening, 2)) ?></strong></td></tr>
        <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= e(fmt_date($r['entry_date'])) ?></td>
            <td><a href="<?= e(url('ledger/entries/' . $r['id'])) ?>"><?= e($r['ref'] ?: '#' . $r['id']) ?></a></td>
            <td><?= e($r['memo']) ?><?= (int) $r['is_change'] ? ' ' . badge('correction', 'warn') : '' ?></td>
            <td style="text-align:right"><?= (float) $r['debit'] ? e(number_format((float) $r['debit'], 2)) : '' ?></td>
            <td style="text-align:right"><?= (float) $r['credit'] ? e(number_format((float) $r['credit'], 2)) : '' ?></td>
            <td style="text-align:right"><?= e(number_format($r['running'], 2)) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="5"><strong>Balance at <?= e(fmt_date($to)) ?></strong></td><td style="text-align:right"><strong><?= e(number_format($closing, 2)) ?></strong></td></tr></tfoot>
    </table></div>
</div>
<?php if (can('finance.manage')): ?>
<details class="card"><summary><strong>Rename<?= !$a['sys_key'] && !$a['fin_account_id'] && !$a['category_id'] ? ' or stop using' : '' ?> this account</strong></summary>
    <form method="post" action="<?= e(url('ledger/accounts/' . $a['id'])) ?>" class="form" style="margin-top:.6rem"><?= csrf_field() ?>
        <label class="required">Name<input name="name" maxlength="120" required value="<?= e($a['name']) ?>"></label>
        <?php if (!$a['sys_key'] && !$a['fin_account_id'] && !$a['category_id']): ?><label style="font-weight:400"><input type="checkbox" name="is_active" value="1"<?= checked((int) $a['is_active']) ?>> In use</label><?php else: ?><?php endif; ?>
        <button class="btn btn-primary" type="submit">Save</button>
    </form>
</details>
<?php endif; ?>
