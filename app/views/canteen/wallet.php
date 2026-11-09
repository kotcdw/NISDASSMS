<?php $ct = 'canteen/wallets'; $kinds = ['topup' => ['Top-up', 'good'], 'sale' => ['Purchase', 'neutral'], 'refund' => ['Refund', 'info'], 'adjust' => ['Adjustment', 'warn']]; ?>
<div class="page-head">
    <div><div class="breadcrumb"><a href="<?= e(url('canteen/wallets')) ?>">Wallets</a> /</div><h1><?= e($p['first_name'] . ' ' . $p['last_name']) ?></h1>
    <p class="muted"><?= e($p['admission_no']) ?> · <?= e($p['class_name'] ?? '') ?><?= $plan ? ' · meal plan: ' . e($plan['name']) : '' ?></p></div>
    <div class="actions"><a class="btn btn-primary" href="<?= e(url('canteen/sell', ['pupil' => $p['id']])) ?>">Sell</a></div>
</div>
<div class="cols">
<div class="card" style="padding:0"><h2 style="padding:1rem 1rem 0">History</h2><div class="table-wrap"><table>
    <tr><th>When</th><th>What</th><th class="num">Amount</th><th class="num">Balance</th><th>By</th></tr>
    <?php foreach ($txns as $t): ?><tr><td><?= e(fmt_date($t['created_at'], 'j M H:i')) ?></td><td><?= badge(...$kinds[$t['kind']]) ?> <small class="muted"><?= e(trim(($t['method'] ?? '') . ' ' . ($t['reference'] ?? ''))) ?></small></td>
        <td class="num" style="color:<?= (float) $t['amount'] >= 0 ? 'var(--good)' : 'inherit' ?>"><?= e(number_format((float) $t['amount'], 2)) ?></td><td class="num"><?= e(number_format((float) $t['balance_after'], 2)) ?></td><td><small><?= e($t['by_name'] ?? '') ?></small></td></tr><?php endforeach; ?>
    <?php if (!$txns): ?><tr><td colspan="5" class="muted">Nothing yet.</td></tr><?php endif; ?>
</table></div></div>
<div>
    <p class="figs"><span>Balance <strong><?= e(Fees::money($p['balance'] ?? 0)) ?></strong></span></p>
    <form method="post" action="<?= e(url('canteen/wallets/' . $p['id'] . '/topup')) ?>" class="card form"><?= csrf_field() ?>
        <h2>Top up</h2>
        <div class="grid-2"><label>Amount (GH₵)<input name="amount" inputmode="decimal" required></label><label>Paid by<select name="method"><option value="cash">Cash</option><option value="momo">MoMo</option></select></label></div>
        <label>MoMo transaction ID<input name="reference" maxlength="100"></label>
        <button class="btn btn-primary" type="submit">Add money</button>
        
    </form>
    <form method="post" action="<?= e(url('canteen/wallets/' . $p['id'] . '/limit')) ?>" class="card form"><?= csrf_field() ?>
        <h2>Daily spending limit</h2>
        <label>Most the child may spend per day (GH₵)<input name="daily_limit" inputmode="decimal" value="<?= e($p['daily_limit'] ?? '') ?>" placeholder="Empty = no limit"></label>
        <button class="btn" type="submit">Save limit</button>
    </form>
</div>
</div>
