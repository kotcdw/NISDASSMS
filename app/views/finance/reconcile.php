<?php $stmt = old('statement_balance', $rec ? number_format((float) $rec['statement_balance'], 2, '.', '') : ''); ?>
<div class="page-head">
    <div><p class="breadcrumb"><a href="<?= e(url('finance/reconcile')) ?>">Bank reconciliation</a> › <?= e($acct['name']) ?></p>
        <h1><?= e($acct['name']) ?> — <?= e(fmt_date($end, 'F Y')) ?></h1>
        <p class="muted">Statement to <?= e(fmt_date($end)) ?><?= $last ? ' · last reconciled ' . e(fmt_date($last['period_end'])) : '' ?></p></div>
    <div class="actions"><a class="btn" href="<?= e(url('finance/new', ['kind' => 'expense', 'account' => $acct['id'], 'date' => $end, 'cat' => 'BANK', 'desc' => 'Bank charges ' . date('M Y', strtotime($end))])) ?>">Add a missing entry</a></div>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>
<form method="post" action="<?= e(url('finance/reconcile/' . $acct['id'])) ?>" data-recon data-book="<?= e($book) ?>">
    <?= csrf_field() ?><input type="hidden" name="end" value="<?= e($end) ?>">
    <div class="cols">
        <div class="card" style="padding:0">
            <div class="xg-bar" style="padding:.7rem 1rem"><strong>Tick what is on the statement</strong><span class="muted" style="margin-left:auto"><button type="button" class="btn btn-sm btn-ghost" data-recon-all>Tick all</button></span></div>
            <div class="table-wrap"><table data-nopage>
                <thead><tr><th style="width:2.5rem"></th><th>Date</th><th>No.</th><th>Details</th><th class="num">In</th><th class="num">Out</th></tr></thead>
                <tbody>
                <?php foreach ($items as $t): $on = $t['cleared_on'] !== null; ?><tr>
                    <td><input type="checkbox" name="cleared[]" value="<?= (int) $t['id'] ?>" data-eff="<?= e($t['effect']) ?>"<?= $on ? ' checked' : '' ?> aria-label="On the statement"></td>
                    <td><?= e(fmt_date($t['txn_date'], 'j M')) ?></td>
                    <td><a href="<?= e(url('finance/transactions/' . $t['id'])) ?>"><?= e($t['txn_no']) ?></a></td>
                    <td><?= e($t['description']) ?><?= $t['reference'] ? '<br><small class="muted">' . e($t['reference']) . '</small>' : '' ?></td>
                    <td class="num"><?= $t['effect'] > 0 ? e(number_format($t['effect'], 2)) : '' ?></td>
                    <td class="num"><?= $t['effect'] < 0 ? e(number_format(-$t['effect'], 2)) : '' ?></td>
                </tr><?php endforeach; ?>
                <?php if (!$items): ?><tr><td colspan="6" class="muted">No entries to tick for this period.</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </div>
        <div>
            <div class="card form recon-sum">
                <h2>Check</h2>
                <label class="required">Statement closing balance (GH₵)<input name="statement_balance" inputmode="decimal" required value="<?= e($stmt) ?>" data-recon-stmt></label>
                <p><span>Statement balance</span><strong data-r="stmt">0.00</strong></p>
                <p><span>+ Money in not yet on statement</span><strong data-r="in">0.00</strong></p>
                <p><span>− Money out not yet on statement</span><strong data-r="out">0.00</strong></p>
                <p class="tot"><span>= Adjusted balance</span><strong data-r="adj">0.00</strong></p>
                <p><span>Cash book balance</span><strong><?= e(number_format($book, 2)) ?></strong></p>
                <p class="tot"><span>Difference</span><strong data-r="diff">0.00</strong></p>
                <label>Notes<input name="notes" maxlength="255" value="<?= e($rec['notes'] ?? '') ?>"></label>
                <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                    <button class="btn" type="submit" name="action" value="save">Save &amp; finish later</button>
                    <button class="btn btn-primary" type="submit" name="action" value="complete" data-recon-done>Complete</button>
                </div>
            </div>
        </div>
    </div>
</form>
