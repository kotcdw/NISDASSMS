<?php
$use = Petty::canUse($box); $mg = can('finance.manage'); $holder = (int) $box['custodian_id'] === (int) Auth::id();
$spent = array_sum(array_map(fn($t) => (float) $t['amount'], $unclaimed)); $need = round((float) $box['float_amount'] - $box['balance'], 2);
$pcv = (int) ($_GET['pcv'] ?? 0); $claim = (int) ($_GET['claim'] ?? 0);
?>
<div class="page-head">
    <div><?php if (can('finance.view')): ?><p class="breadcrumb"><a href="<?= e(url('petty-cash')) ?>">Petty cash</a> › <?= e($box['name']) ?></p><?php endif; ?>
        <h1><?= e($box['name']) ?><?= $box['is_active'] ? '' : ' ' . badge('Closed', 'neutral') ?></h1>
        <p class="muted">Held by <?= e($box['custodian_name'] ?? '—') ?> · float <?= e(Fees::money($box['float_amount'])) ?> · cash in box <strong><?= e(Fees::money($box['balance'])) ?></strong> · spent since last top-up <?= e(Fees::money($spent)) ?><?= $box['spend_limit'] ? ' · up to ' . e(Fees::money($box['spend_limit'])) . ' per voucher' : '' ?></p></div>
    <?php if ($mg): ?><div class="actions"><details class="menu-btn"><summary class="btn">Tools ▾</summary><div><a href="#" data-help="box-settings">Box settings</a><a href="<?= e(url('finance/cashbook', ['account' => $box['id']])) ?>">Cash book of this box</a></div></details></div><?php endif; ?>
</div>
<?php if ($pcv): ?><div class="alert alert-success">Voucher saved. <a class="btn btn-sm" href="<?= e(url('petty-cash/' . $box['id'] . '/print', ['pcv' => $pcv])) ?>" target="_blank" rel="noopener">Print voucher</a></div><?php endif; ?>
<?php if ($claim): ?><div class="alert alert-success">Top-up done. <a class="btn btn-sm" href="<?= e(url('petty-cash/' . $box['id'] . '/print', ['claim' => $claim])) ?>" target="_blank" rel="noopener">Print top-up claim</a></div><?php endif; ?>
<?php if ($mg): ?>
<div id="box-settings" class="help-box form-center" hidden>
    <form method="post" action="<?= e(url('petty-cash/' . $box['id'])) ?>" class="card form"><?= csrf_field() ?>
        <h2>Box settings</h2>
        <div class="grid-2">
            <label>Held by<select name="custodian_id"><?php foreach ($staff as $u): ?><option value="<?= (int) $u['id'] ?>"<?= selected($box['custodian_id'], $u['id']) ?>><?= e($u['name']) ?></option><?php endforeach; ?></select></label>
            <label>Float (GH₵)<input name="float_amount" inputmode="decimal" value="<?= e($box['float_amount']) ?>"></label>
            <label>Most for one voucher (GH₵)<input name="spend_limit" inputmode="decimal" value="<?= e($box['spend_limit']) ?>"></label>
        </div>
        <div class="vf-foot"><label style="font-weight:400;margin:0"><input type="checkbox" name="is_active" value="1"<?= checked($box['is_active']) ?>> Box in use</label><span style="display:flex;gap:.5rem"><button type="button" class="btn btn-ghost" data-help="box-settings">Close</button><button class="btn btn-primary" type="submit">Save</button></span></div>
    </form>
</div>
<?php endif; ?>
<div class="cols">
    <div>
        <?php if ($use): ?>
        <form method="post" action="<?= e(url('petty-cash/' . $box['id'] . '/spend')) ?>" enctype="multipart/form-data" class="card form"><?= csrf_field() ?>
            <h2>Record a spend</h2>
            <div class="grid-2">
                <label class="required">Amount (GH₵)<input name="amount" inputmode="decimal" required value="<?= e(old('amount')) ?>"></label>
                <label class="required">Date<input type="date" name="txn_date" required max="<?= e(date('Y-m-d')) ?>" value="<?= e(old('txn_date', date('Y-m-d'))) ?>"></label>
                <label class="required">For<select name="category_id" required><option value="">Choose…</option><?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected(old('category_id'), $c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></label>
                <?php if (Finance::hasDepartments()): ?><label>Department<select name="department"><option value="">—</option><?php foreach (Finance::departments() as $dn): ?><option value="<?= e($dn) ?>"<?= selected(old('department'), $dn) ?>><?= e($dn) ?></option><?php endforeach; ?></select></label><?php endif; ?>
                <label>Paid to<input name="party" maxlength="150" value="<?= e(old('party')) ?>" placeholder="e.g. Shop / person"></label>
            </div>
            <label class="required">What was bought<input name="description" required maxlength="255" value="<?= e(old('description')) ?>" placeholder="e.g. 2 boxes of chalk, cleaning soap"></label>
            <div class="grid-2"><label>Receipt no.<input name="reference" maxlength="100" value="<?= e(old('reference')) ?>"></label><label>Photo of the receipt<input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png"></label></div>
            <div class="vf-foot"><span class="hint" style="margin:0">Keep the receipt with the voucher.</span><button class="btn btn-primary" type="submit">Save voucher</button></div>
        </form>
        <?php endif; ?>
        <div class="card">
            <h2>Vouchers since the last top-up</h2>
            <?php if (!$unclaimed): ?><p class="muted">None.</p><?php else: ?>
            <div class="table-wrap"><table data-nopage>
                <thead><tr><th>Voucher</th><th>Date</th><th>For</th><th class="num">Amount</th><th>By</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($unclaimed as $t): ?><tr>
                    <td><?= e($t['txn_no']) ?></td><td><?= e(fmt_date($t['txn_date'], 'j M')) ?></td>
                    <td><?= e($t['description']) ?><br><small class="muted"><?= e($t['category_name'] ?? '') ?><?= $t['party'] ? ' · ' . e($t['party']) : '' ?></small></td>
                    <td class="num"><?= e(number_format((float) $t['amount'], 2)) ?></td><td><small><?= e($t['by_name'] ?? '') ?></small></td>
                    <td class="xg-act"><details class="rowmenu"><summary aria-label="Actions">⋯</summary><div>
                        <a href="<?= e(url('petty-cash/' . $box['id'] . '/print', ['pcv' => $t['id']])) ?>" target="_blank" rel="noopener">Print voucher</a>
                        <?php if ($t['attachment']): ?><a href="<?= e(url('petty-cash/' . $box['id'] . '/file/' . $t['id'])) ?>" target="_blank" rel="noopener">Receipt photo</a><?php endif; ?>
                        <?php if ($mg): ?><form method="post" action="<?= e(url('petty-cash/' . $box['id'] . '/void/' . $t['id'])) ?>" data-confirm="Cancel voucher <?= e($t['txn_no']) ?>?"><?= csrf_field() ?><input name="reason" required maxlength="255" placeholder="Reason" style="margin:.3rem .4rem;width:calc(100% - .8rem)"><button type="submit" class="danger">Cancel voucher</button></form><?php endif; ?>
                    </div></details></td>
                </tr><?php endforeach; ?>
                </tbody>
                <tfoot><tr><td colspan="3"><strong>Total to top up</strong></td><td class="num"><strong><?= e(number_format($spent, 2)) ?></strong></td><td colspan="2"></td></tr></tfoot>
            </table></div>
            <?php endif; ?>
        </div>
    </div>
    <div>
        <?php if ($mg && !$holder && $need > 0.004 && $unclaimed): ?>
        <form method="post" action="<?= e(url('petty-cash/' . $box['id'] . '/topup')) ?>" class="card form" data-confirm="Check the vouchers and receipts, then top up <?= e(Fees::money($need)) ?>?"><?= csrf_field() ?>
            <h2>Top up to the float</h2>
            <p class="fp-sum">Top-up: <strong><?= e(Fees::money($need)) ?></strong> (<?= count($unclaimed) ?> vouchers)</p>
            <label class="required">From<select name="from_account" required><?php foreach ($accounts as $a): ?><option value="<?= (int) $a['id'] ?>"><?= e($a['name']) ?> (<?= e(number_format(Finance::balance((int) $a['id']), 2)) ?>)</option><?php endforeach; ?></select></label>
            <button class="btn btn-primary" type="submit">Top up</button>
        </form>
        <?php endif; ?>
        <div class="card">
            <h2>Top-ups &amp; earlier vouchers</h2>
            <?php $tops = array_filter($history, fn($t) => $t['kind'] === 'transfer'); if (!$tops): ?><p class="muted">No top-up yet.</p><?php endif; ?>
            <?php foreach (array_slice($tops, 0, 12) as $t): ?><p style="display:flex;justify-content:space-between;gap:.5rem;margin:0 0 .45rem"><span><a href="<?= e(url('petty-cash/' . $box['id'] . '/print', ['claim' => $t['id']])) ?>" target="_blank" rel="noopener"><?= e($t['txn_no']) ?></a> <small class="muted"><?= e(fmt_date($t['txn_date'], 'j M Y')) ?></small></span><strong><?= e(number_format((float) $t['amount'], 2)) ?></strong></p><?php endforeach; ?>
        </div>
    </div>
</div>
