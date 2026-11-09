<?php /** Payment plan (instalments) of a bill: $inv; $manage = staff may edit. */
$plan = Instalments::schedule($inv); $manage = $manage ?? false; $owe = round((float) $inv['total'] - (float) $inv['paid'], 2);
$stTone = ['paid' => ['Paid', 'good'], 'part' => ['Part paid', 'warn'], 'overdue' => ['Overdue', 'bad'], 'due' => ['To pay', 'neutral']];
$canPlan = $manage && $owe > 0 && in_array($inv['status'], ['unpaid', 'part'], true);
if (!$plan && !$canPlan) return; ?>
<?php if ($plan): ?>
<div class="card">
    <h2>Payment plan</h2>
    <div class="table-wrap"><table>
        <tr><th>#</th><th>Due</th><th style="text-align:right">Amount</th><th style="text-align:right">Still to pay</th><th>Status</th></tr>
        <?php foreach ($plan as $p): ?><tr><td><?= (int) $p['seq'] ?></td><td><?= e(fmt_date($p['due_date'], 'D j M Y')) ?></td><td style="text-align:right"><?= e(number_format((float) $p['amount'], 2)) ?></td><td style="text-align:right"><?= e(number_format($p['due_left'], 2)) ?></td><td><?= badge(...$stTone[$p['state']]) ?></td></tr><?php endforeach; ?>
    </table></div>
    <?php if ($canPlan): ?><p style="margin:.6rem 0 0"><a href="#" data-help="plan-box">Change the plan</a></p><?php endif; ?>
</div>
<?php endif; ?>
<?php if ($canPlan): ?>
<div id="plan-box" class="help-box form-center" hidden>
    <div class="card">
        <h2><?= $plan ? 'Change the payment plan' : 'Agree a payment plan with the parent' ?></h2>
        <form method="post" action="<?= e(url('fees/invoices/' . $inv['id'] . '/plan')) ?>" class="form" style="margin-top:.5rem"><?= csrf_field() ?>
            
            <?php $n = 3; $each = floor($owe / $n * 100) / 100; for ($i = 0; $i < 6; $i++): $amt = $i < $n ? ($i === $n - 1 ? round($owe - $each * ($n - 1), 2) : $each) : ''; ?>
            <div class="grid-2"><label>Instalment <?= $i + 1 ?> date<input type="date" name="due[]" value="<?= $i < $n ? e(date('Y-m-d', strtotime('+' . ($i * 4 + 1) . ' weeks'))) : '' ?>"></label><label>Amount (GH₵)<input name="amount[]" inputmode="decimal" value="<?= e((string) $amt) ?>"></label></div>
            <?php endfor; ?>
            <button class="btn btn-primary" type="submit">Save plan</button>
            <?php if ($plan): ?><button class="btn btn-ghost" type="submit" name="remove" value="1">Remove the plan</button><?php endif; ?>
            <button type="button" class="btn btn-ghost" data-help="plan-box">Close</button>
        </form>
    </div>
</div>
<?php endif; ?>
