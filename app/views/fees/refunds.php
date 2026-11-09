<?php $isCn = $tab === 'credit'; $stOpts = $isCn ? ['pending' => 'Waiting for approval', 'approved' => 'Approved', 'rejected' => 'Rejected'] : ['pending' => 'Waiting for approval', 'approved' => 'Approved — to pay', 'paid' => 'Paid', 'rejected' => 'Rejected']; ?>
<div class="xgrid-box" data-xgrid="refunds-<?= e($tab) ?>" data-xgrid-file="<?= $isCn ? 'credit-notes' : 'fee-refunds' ?>">
<div class="page-head">
    <div><h1>Refunds &amp; credit notes</h1></div>
    <div class="actions">
        <form method="get" action="<?= e(url('fees/refunds')) ?>" class="list-pick" data-autosubmit><?php if ($isCn): ?><input type="hidden" name="tab" value="credit"><?php endif; ?>
            <select name="status" aria-label="Status"><option value="">Any status</option><?php foreach ($stOpts as $k => $l): ?><option value="<?= $k ?>"<?= selected($k, $status) ?>><?= e($l) ?></option><?php endforeach; ?></select>
        </form>
        <a class="btn btn-ghost" href="<?= e(url('fees/families')) ?>">Family accounts</a>
        <?= export_grid_menu() ?>
    </div>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>
<?= card_tabs(['refunds' => ['Refunds to parents', url('fees/refunds')], 'credit' => ['Credit notes on bills', url('fees/refunds', ['tab' => 'credit'])]], $tab, 2, 'Show') ?>
    <div class="xg-scroll"><table class="xgrid">
    <?php if ($isCn): ?>
        <thead><tr><?= list_th(['no' => ['No.'], 'date' => ['Asked', 'date'], 'student' => ['Student'], 'adm' => ['Admission no.'], 'bill' => ['Bill'], 'reason' => ['Reason'], 'note' => ['Note'], 'amount' => ['Amount (GH₵)', 'num', 'num'], 'status' => ['Status'], 'asked' => ['Asked by'], 'decided' => ['Decided by']]) ?><th class="xg-act"></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): [$sl, $st] = Family::STATUS[$r['status']]; $mine = (int) $r['requested_by'] === (int) Auth::id(); ?><tr>
            <td data-c="no"><?= e($r['cn_no']) ?></td>
            <td data-c="date" data-s="<?= e($r['requested_at']) ?>"><?= e(fmt_date(substr($r['requested_at'], 0, 10), 'j M Y')) ?></td>
            <td data-c="student"><?= e($r['first_name'] . ' ' . $r['last_name']) ?></td>
            <td data-c="adm"><?= e($r['admission_no']) ?></td>
            <td data-c="bill"><a href="<?= e(url('fees/invoices/' . $r['invoice_id'])) ?>"><?= e($r['invoice_no']) ?></a></td>
            <td data-c="reason" class="audit-details"><?= e($r['reason']) ?></td>
            <td data-c="note" class="audit-details muted"><?= e((string) $r['decision_note']) ?><?= (float) $r['to_credit'] > 0 ? ($r['decision_note'] ? '<span hidden> · </span> ' : '') . e(Fees::money($r['to_credit'])) . ' moved to family credit' : '' ?></td>
            <td data-c="amount" class="num" data-s="<?= e((string) (float) $r['amount']) ?>"><?= e(number_format((float) $r['amount'], 2)) ?></td>
            <td data-c="status"><?= badge($sl, $st) ?></td>
            <td data-c="asked"><?= e($r['requested_name'] ?? '') ?></td>
            <td data-c="decided"><?= e($r['decided_name'] ?? '') ?></td>
            <td class="xg-act"><details class="rowmenu"><summary aria-label="Actions">⋯</summary><div>
                <a href="<?= e(url('fees/credit-notes/' . $r['id'] . '/print')) ?>" target="_blank" rel="noopener">Print credit note</a>
                <?php if ($r['status'] === 'pending' && can('fees.approve') && (!$mine || Auth::role() === 'super_admin')): ?>
                    <form method="post" action="<?= e(url('fees/credit-notes/' . $r['id'] . '/decide')) ?>" data-confirm="Approve credit note <?= e($r['cn_no']) ?>? Bill <?= e($r['invoice_no']) ?> is reduced by <?= e(Fees::money($r['amount'])) ?>."><?= csrf_field() ?><input type="hidden" name="decision" value="approve"><button type="submit">Approve</button></form>
                    <form method="post" action="<?= e(url('fees/credit-notes/' . $r['id'] . '/decide')) ?>"><?= csrf_field() ?><input type="hidden" name="decision" value="reject"><input name="note" required maxlength="255" placeholder="Reason for rejecting" style="margin:.3rem .4rem;width:calc(100% - .8rem)"><button type="submit" class="danger">Reject</button></form>
                <?php elseif ($r['status'] === 'pending' && $mine): ?><span class="muted" style="padding:.5rem .7rem;font-size:.85rem">Someone else must approve</span><?php endif; ?>
            </div></details></td>
        </tr><?php endforeach; ?>
        </tbody>
    <?php else: ?>
        <thead><tr><?= list_th(['no' => ['No.'], 'date' => ['Asked', 'date'], 'family' => ['Family'], 'reason' => ['Reason'], 'note' => ['Note'], 'pay' => ['Pay by'], 'payto' => ['Pay to'], 'amount' => ['Amount (GH₵)', 'num', 'num'], 'status' => ['Status'], 'paid' => ['Paid on', 'date'], 'asked' => ['Asked by'], 'decided' => ['Decided by']]) ?><th class="xg-act"></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): [$sl, $st] = Family::STATUS[$r['status']]; $mine = (int) $r['requested_by'] === (int) Auth::id(); ?><tr>
            <td data-c="no"><?= e($r['rf_no']) ?></td>
            <td data-c="date" data-s="<?= e($r['requested_at']) ?>"><?= e(fmt_date(substr($r['requested_at'], 0, 10), 'j M Y')) ?></td>
            <td data-c="family"><a href="<?= e(url('fees/family/' . $r['guardian_id'])) ?>"><?= e($r['guardian_name']) ?></a></td>
            <td data-c="reason" class="audit-details"><?= e($r['reason']) ?></td>
            <td data-c="note" class="audit-details muted"><?= e((string) $r['decision_note']) ?></td>
            <td data-c="pay"><?= e(Family::REFUND_METHODS[$r['method']]) ?></td>
            <td data-c="payto"><?= e($r['pay_to']) ?><?= $r['pay_details'] ? ' · ' . e($r['pay_details']) : '' ?></td>
            <td data-c="amount" class="num" data-s="<?= e((string) (float) $r['amount']) ?>"><?= e(number_format((float) $r['amount'], 2)) ?></td>
            <td data-c="status"><?= badge($sl, $st) ?></td>
            <td data-c="paid" data-s="<?= e((string) $r['paid_on']) ?>"><?= $r['paid_on'] ? e(fmt_date($r['paid_on'], 'j M Y')) : '' ?></td>
            <td data-c="asked"><?= e($r['requested_name'] ?? '') ?></td>
            <td data-c="decided"><?= e($r['decided_name'] ?? '') ?></td>
            <td class="xg-act"><details class="rowmenu"><summary aria-label="Actions">⋯</summary><div>
                <a href="<?= e(url('fees/refunds/' . $r['id'] . '/print')) ?>" target="_blank" rel="noopener">Print refund voucher</a>
                <?php if ($r['status'] === 'pending' && can('fees.approve') && (!$mine || Auth::role() === 'super_admin')): ?>
                    <form method="post" action="<?= e(url('fees/refunds/' . $r['id'] . '/decide')) ?>" data-confirm="Approve refund <?= e($r['rf_no']) ?> of <?= e(Fees::money($r['amount'])) ?>?"><?= csrf_field() ?><input type="hidden" name="decision" value="approve"><button type="submit">Approve</button></form>
                    <form method="post" action="<?= e(url('fees/refunds/' . $r['id'] . '/decide')) ?>"><?= csrf_field() ?><input type="hidden" name="decision" value="reject"><input name="note" required maxlength="255" placeholder="Reason for rejecting" style="margin:.3rem .4rem;width:calc(100% - .8rem)"><button type="submit" class="danger">Reject</button></form>
                <?php elseif ($r['status'] === 'pending' && $mine): ?><span class="muted" style="padding:.5rem .7rem;font-size:.85rem">Someone else must approve</span><?php endif; ?>
                <?php if ($r['status'] === 'approved' && can('fees.manage')): ?>
                    <form method="post" action="<?= e(url('fees/refunds/' . $r['id'] . '/pay')) ?>" data-confirm="Pay <?= e(Fees::money($r['amount'])) ?> to <?= e($r['pay_to']) ?> now?" style="padding:.3rem .4rem;display:grid;gap:.3rem"><?= csrf_field() ?>
                        <select name="account_id" required aria-label="Pay from"><?php foreach ($accounts as $a): ?><option value="<?= (int) $a['id'] ?>"><?= e($a['name']) ?></option><?php endforeach; ?></select>
                        <input name="reference" maxlength="100" placeholder="MoMo ID / cheque no.">
                        <button type="submit" class="btn btn-sm btn-primary">Pay refund</button>
                    </form>
                <?php endif; ?>
            </div></details></td>
        </tr><?php endforeach; ?>
        </tbody>
    <?php endif; ?>
    </table>
    <p class="xg-empty muted"<?= $rows ? ' hidden' : '' ?> data-xg-empty><?= $isCn ? 'No credit notes.' : 'No refunds.' ?></p>
    </div>
</div>
