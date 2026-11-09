<?php $st = ['pending' => ['Waiting for approval', 'warn'], 'approved' => ['In the books', 'good'], 'rejected' => ['Rejected', 'bad']]; ?>
<div class="xgrid-box" data-xgrid="journals" data-xgrid-file="manual-journals">
<div class="page-head">
    <div><h1>Corrections (manual journals)</h1></div>
    <div class="actions"><?= export_grid_menu() ?><?php if (can('ledger.journal')): ?><button type="button" class="btn btn-primary" data-help="jnl-form" aria-expanded="<?= old('memo') !== '' ? 'true' : 'false' ?>">+ New correction</button><?php endif; ?></div>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>
<?php if (can('ledger.journal')): ?>
<div id="jnl-form" class="card"<?= old('memo') !== '' ? '' : ' hidden' ?>>
<form method="post" action="<?= e(url('ledger/journals')) ?>" class="form"><?= csrf_field() ?>
    <h2>New correction (manual journal)</h2>
    <div class="grid-2">
        <label class="required">Date<input type="date" name="entry_date" required value="<?= e(old('entry_date', date('Y-m-d'))) ?>" max="<?= e(date('Y-m-d')) ?>"></label>
        <label class="required">What is it for?<input name="memo" maxlength="255" required value="<?= e(old('memo')) ?>" placeholder="e.g. GH₵ 310 entered under Repairs, should be Cleaning"></label>
    </div>
    <div class="table-wrap"><table class="lines" data-nopage>
        <thead><tr><th>Account</th><th style="width:9rem">Debit</th><th style="width:9rem">Credit</th><th>Note</th></tr></thead>
        <tbody><?php $oa = (array) old('account', []); $od = (array) old('debit', []); $oc = (array) old('credit', []); $on = (array) old('note', []); for ($i = 0; $i < 6; $i++): ?>
        <tr>
            <td><select name="account[]" aria-label="Account, line <?= $i + 1 ?>"><option value="0">—</option><?php foreach ($accounts as $a): ?><option value="<?= (int) $a['id'] ?>"<?= selected($oa[$i] ?? 0, $a['id']) ?>><?= e($a['code'] . ' ' . $a['name']) ?></option><?php endforeach; ?></select></td>
            <td><input name="debit[]" inputmode="decimal" value="<?= e($od[$i] ?? '') ?>" aria-label="Debit, line <?= $i + 1 ?>" style="text-align:right"></td>
            <td><input name="credit[]" inputmode="decimal" value="<?= e($oc[$i] ?? '') ?>" aria-label="Credit, line <?= $i + 1 ?>" style="text-align:right"></td>
            <td><input name="note[]" maxlength="150" value="<?= e($on[$i] ?? '') ?>" aria-label="Note, line <?= $i + 1 ?>"></td>
        </tr>
        <?php endfor; ?></tbody>
    </table></div>
    <div class="gate-actions"><button class="btn btn-primary" type="submit">Save for approval</button><button type="button" class="btn btn-ghost" data-help="jnl-form">Cancel</button></div>
</form>
</div>
<?php endif; ?>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><?= list_th(['no' => ['No.'], 'date' => ['Date', 'date'], 'memo' => ['What for'], 'total' => ['Amount (GH₵)', 'num', 'num'], 'by' => ['Typed by'], 'status' => ['Status']]) ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $j): [$l, $t] = $st[$j['status']]; ?>
    <tr>
        <td data-c="no"><a href="<?= e(url('ledger/journals/' . $j['id'])) ?>"><?= e($j['jnl_no']) ?></a></td>
        <td data-c="date" data-s="<?= e((string) $j['entry_date']) ?>"><?= e(fmt_date($j['entry_date'])) ?></td>
        <td data-c="memo"><?= e($j['memo']) ?></td>
        <td data-c="total" class="num" data-s="<?= (float) $j['total'] ?>"><?= e(number_format((float) $j['total'], 2)) ?></td>
        <td data-c="by"><?= e($j['drafted_name'] ?? '') ?></td>
        <td data-c="status"><?= badge($l, $t) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty>No manual journals yet.</p>
</div>
</div>
