<?php
$owing = array_sum(array_map(fn($b) => (float) $b['balance'], $open));
$mg = can('fees.manage');
$newRcpt = (string) ($_GET['receipt'] ?? '');
?>
<div class="page-head">
    <div>
        <p class="breadcrumb"><a href="<?= e(url('fees/families')) ?>">Family accounts</a> › <?= e($g['name']) ?></p>
        <h1><?= e($g['name']) ?></h1>
        <p class="muted"><?= e($g['phone']) ?> · <?= count($kids) ?> <?= count($kids) === 1 ? 'child' : 'children' ?> · owing <strong style="color:<?= $owing > 0 ? 'var(--bad)' : 'inherit' ?>"><?= e(Fees::money($owing)) ?></strong> · family credit <strong style="color:var(--good)"><?= e(Fees::money($credit)) ?></strong><?= abs($free - $credit) > 0.004 ? ' (' . e(Fees::money($free)) . ' free)' : '' ?></p>
    </div>
    <?php if ($mg): ?><div class="actions"><details class="menu-btn"><summary class="btn">Tools ▾</summary><div>
        <?php if ($free > 0.004 && $open): ?><form method="post" action="<?= e(url('fees/family/' . $g['id'] . '/apply')) ?>" data-confirm="Use <?= e(Fees::money($free)) ?> of family credit on the open bills (oldest first)?"><?= csrf_field() ?><button type="submit" class="menu-item">Use credit on open bills</button></form><?php endif; ?>
        <?php if ($free > 0.004): ?><a href="#" data-help="refund-form">Ask for a refund</a><?php endif; ?>
        <a href="<?= e(url('fees/refunds')) ?>">Refunds &amp; credit notes</a>
    </div></details></div><?php endif; ?>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>
<?php if ($newRcpt !== ''): ?><div class="alert alert-success">Payment saved. <a class="btn btn-sm" href="<?= e(url('fees/family-receipt', ['no' => $newRcpt])) ?>" target="_blank" rel="noopener">Print receipt <?= e($newRcpt) ?></a></div><?php endif; ?>

<?php if ($mg && $free > 0.004): ?>
<div id="refund-form" class="help-box form-center" hidden>
    <form method="post" action="<?= e(url('fees/family/' . $g['id'] . '/refund')) ?>" class="card form">
        <?= csrf_field() ?>
        <h2>Ask for a refund</h2>
        
        <div class="grid-2">
            <label class="required">Amount (GH₵)<input name="amount" inputmode="decimal" required value="<?= e(number_format($free, 2, '.', '')) ?>"></label>
            <label>Pay by<select name="method"><?php foreach (Family::REFUND_METHODS as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?></select></label>
            <label>Pay to<input name="pay_to" maxlength="150" value="<?= e($g['name']) ?>"></label>
            <label>MoMo no. / bank account / cheque payee<input name="pay_details" maxlength="150" value="<?= e($g['phone']) ?>"></label>
        </div>
        <label class="required">Reason<input name="reason" required maxlength="255" placeholder="e.g. Child left the school; balance of fees paid ahead"></label>
        <div class="vf-foot"><span></span><span style="display:flex;gap:.5rem"><button type="button" class="btn btn-ghost" data-help="refund-form">Cancel</button><button class="btn btn-primary" type="submit">Send for approval</button></span></div>
    </form>
</div>
<?php endif; ?>

<div class="cols">
    <div>
        <?php if ($mg): ?>
        <form method="post" action="<?= e(url('fees/family/' . $g['id'] . '/pay')) ?>" class="card form" data-family-pay>
            <?= csrf_field() ?>
            <h2>Receive a payment for the family</h2>
            <div class="grid-2">
                <label class="required">Amount received (GH₵)<input name="amount" inputmode="decimal" required data-fp-amount value="<?= e(old('amount')) ?>" placeholder="<?= e(number_format($owing, 2, '.', '')) ?>"></label>
                <label class="required">Paid by<select name="method" required><?php foreach (Fees::METHODS as $k => $v): if ($k === 'online') continue; ?><option value="<?= e($k) ?>"<?= selected(old('method'), $k) ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
                <label>Transaction ID / slip / cheque no.<input name="reference" maxlength="100" value="<?= e(old('reference')) ?>" placeholder="Required for MoMo, bank and cheque"></label>
                <label>Date<input type="date" name="paid_on" value="<?= e(old('paid_on', date('Y-m-d'))) ?>" max="<?= e(date('Y-m-d')) ?>"></label>
            </div>
            <label>Paid in by (name)<input name="payer_name" maxlength="150" value="<?= e(old('payer_name', $g['name'])) ?>"></label>
            <?php if ($open): ?>
            <div class="table-wrap" style="margin:.4rem 0"><table data-nopage>
                <thead><tr><th>Bill</th><th>Child</th><th>Due</th><th class="num">Balance</th><th class="num" style="width:9rem">Pay now</th></tr></thead>
                <tbody>
                <?php foreach ($open as $b): ?><tr>
                    <td><a href="<?= e(url('fees/invoices/' . $b['id'])) ?>"><?= e($b['invoice_no']) ?></a><br><small class="muted"><?= e($b['title']) ?></small></td>
                    <td><?= e($b['first_name']) ?></td>
                    <td><?= e($b['due_date'] ? fmt_date($b['due_date'], 'j M') : '') ?></td>
                    <td class="num"><?= e(number_format((float) $b['balance'], 2)) ?></td>
                    <td class="num"><input name="alloc[<?= (int) $b['id'] ?>]" inputmode="decimal" data-fp-bal="<?= e($b['balance']) ?>" style="text-align:right" value="<?= e(old('alloc')[$b['id']] ?? '') ?>"></td>
                </tr><?php endforeach; ?>
                </tbody>
            </table></div>
            <?php else: ?><?php endif; ?>
            <p class="fp-sum">On bills: <strong data-fp-applied>0.00</strong> · Kept as family credit: <strong data-fp-credit>0.00</strong></p>
            <div class="vf-foot">
                <label style="font-weight:400;margin:0"><input type="checkbox" name="sms" value="1" checked> SMS the receipt to <?= e($g['phone']) ?></label>
                <span style="display:flex;gap:.5rem"><?php if ($open): ?><button type="button" class="btn btn-ghost" data-fp-auto>Share oldest first</button><?php endif; ?><button class="btn btn-primary" type="submit">Save payment</button></span>
            </div>
        </form>
        <?php endif; ?>

        <div class="card">
            <h2>Children</h2>
            <div class="table-wrap"><table>
                <thead><tr><th>Name</th><th>Admission no.</th><th>Class</th><th class="num">Owing</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($kids as $k): $kOw = array_sum(array_map(fn($b) => (int) $b['pupil_id'] === (int) $k['id'] ? (float) $b['balance'] : 0, $open)); ?><tr>
                    <td><a href="<?= e(url('pupils/' . $k['id'])) ?>"><?= e($k['first_name'] . ' ' . $k['last_name']) ?></a><?= $k['status'] !== 'active' ? ' ' . badge(ucfirst($k['status']), 'neutral') : '' ?></td>
                    <td><?= e($k['admission_no']) ?></td><td><?= e($k['class_name'] ?? '—') ?></td>
                    <td class="num"><?= $kOw > 0 ? e(number_format($kOw, 2)) : '—' ?></td>
                    <td><a class="btn btn-sm" href="<?= e(url('fees/pupil/' . $k['id'])) ?>">Statement</a></td>
                </tr><?php endforeach; ?>
                </tbody>
            </table></div>
        </div>

        <div class="card">
            <h2>Family credit history</h2>
            <?php if (!$ledger): ?><p class="muted">No credit yet.</p><?php else: ?>
            <div class="table-wrap"><table>
                <thead><tr><th>Date</th><th>What</th><th>Details</th><th class="num">Amount</th><th>By</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($ledger as $l): $rev = $l['status'] === 'reversed'; ?><tr<?= $rev ? ' style="opacity:.55;text-decoration:line-through"' : '' ?>>
                    <td><?= e(fmt_date($l['entry_date'], 'j M Y')) ?></td>
                    <td><?= e(Family::LEDGER_KINDS[$l['kind']] ?? $l['kind']) ?></td>
                    <td><small><?= e(trim(($l['receipt_no'] ? $l['receipt_no'] . ' · ' : '') . ($l['invoice_no'] ? $l['invoice_no'] . ($l['first_name'] ? ' (' . $l['first_name'] . ')' : '') . ' · ' : '') . ($l['note'] ?? ''), ' ·')) ?></small></td>
                    <td class="num" style="color:<?= (float) $l['amount'] >= 0 ? 'var(--good)' : 'var(--bad)' ?>"><?= (float) $l['amount'] >= 0 ? '+' : '−' ?><?= e(number_format(abs((float) $l['amount']), 2)) ?></td>
                    <td><small><?= e($l['by_name'] ?? '') ?></small></td>
                    <td class="xg-act"><?php if ($l['kind'] === 'advance' && !$rev && can('fees.reverse')): ?>
                        <details class="rowmenu"><summary aria-label="Actions">⋯</summary><div>
                            <form method="post" action="<?= e(url('fees/family/' . $g['id'] . '/advance/' . $l['id'] . '/reverse')) ?>" data-confirm="Reverse this advance of <?= e(Fees::money($l['amount'])) ?>? Its cash book entry is cancelled.">
                                <?= csrf_field() ?><input name="reason" required maxlength="200" placeholder="Reason" style="margin:.3rem .4rem;width:calc(100% - .8rem)"><button type="submit" class="menu-item danger">Reverse advance</button>
                            </form>
                        </div></details>
                    <?php endif; ?></td>
                </tr><?php endforeach; ?>
                </tbody>
            </table></div>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <?php if ($receipts): ?>
        <div class="card">
            <h2>Family receipts</h2>
            <?php foreach ($receipts as $r): ?><p style="display:flex;justify-content:space-between;margin:0 0 .45rem"><a href="<?= e(url('fees/family-receipt', ['no' => $r['no']])) ?>" target="_blank" rel="noopener"><?= e($r['no']) ?></a><span class="muted"><?= e(fmt_date($r['paid_on'], 'j M')) ?> · <?= e(number_format((float) $r['amt'], 2)) ?></span></p><?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="card">
            <h2>Refunds</h2>
            <?php if (!$refunds): ?><p class="muted">No refunds.</p><?php endif; ?>
            <?php foreach ($refunds as $r): [$sl, $st] = Family::STATUS[$r['status']]; ?>
                <p style="margin:0 0 .6rem"><a href="<?= e(url('fees/refunds/' . $r['id'] . '/print')) ?>" target="_blank" rel="noopener"><?= e($r['rf_no']) ?></a> <?= badge($sl, $st) ?><br><strong><?= e(Fees::money($r['amount'])) ?></strong> <small class="muted"><?= e($r['reason']) ?></small></p>
            <?php endforeach; ?>
        </div>
        <div class="card">
            <h2>Recent bills</h2>
            <?php foreach (array_slice($bills, 0, 12) as $b): [$sl, $st] = Fees::STATUS[$b['status']]; ?>
                <p style="display:flex;justify-content:space-between;gap:.5rem;margin:0 0 .45rem"><span><a href="<?= e(url('fees/invoices/' . $b['id'])) ?>"><?= e($b['invoice_no']) ?></a> <small class="muted"><?= e($b['first_name']) ?></small></span><span><?= badge($sl, $st) ?></span></p>
            <?php endforeach; ?>
        </div>
    </div>
</div>
