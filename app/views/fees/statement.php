<div class="page-head">
    <div>
        <p class="breadcrumb"><a href="<?= e(url('fees')) ?>">Fees</a> › Statement</p>
        <h1><?= e(full_name($pupil)) ?></h1>
        <p class="muted"><?= e($pupil['admission_no']) ?> · <?= e($pupil['class_name'] ?? 'No class this year') ?><?= $guardian ? ' · ' . e($guardian['name'] . ' ' . $guardian['phone']) : '' ?></p>
    </div>
    <div class="actions">
        <a class="btn" href="<?= e(url('fees/pupil/' . $pupil['id'], ['print' => 1])) ?>" target="_blank" rel="noopener">Print statement</a>
        <?php if (can('pupils.view')): ?><a class="btn btn-ghost" href="<?= e(url('pupils/' . $pupil['id'])) ?>">Student profile</a><?php endif; ?>
        <?php if (Family::ready() && ($fgid = Family::guardianFor((int) $pupil['id']))): ?><a class="btn" href="<?= e(url('fees/family/' . $fgid)) ?>">Family account<?= ($fc = Family::credit($fgid)) > 0.004 ? ' · credit ' . e(number_format($fc, 2)) : '' ?></a><?php endif; ?>
    </div>
</div>
<p class="figs"><span><?= $balance < 0 ? 'Credit' : 'Balance owed' ?> <strong> 0 ? 'var(--bad)' : 'var(--good)' ?>"><?= e(Fees::money(abs($balance))) ?></strong></span><span>Total billed (all time) <strong><?= e(Fees::money(array_sum(array_map(fn($i) => $i['status'] === 'cancelled' ? 0 : (float) $i['total'], $invoices)))) ?></strong></span><span>Total paid <strong><?= e(Fees::money(array_sum(array_map(fn($p) => $p['status'] === 'completed' ? (float) $p['amount'] : 0, $payments)))) ?></strong></span></p>
<div class="cols">
    <div>
        <div class="card" style="padding:0">
            <h2 style="padding:1rem 1rem 0">Bills</h2>
            <div class="table-wrap"><table>
                <tr><th>Bill</th><th>For</th><th style="text-align:right">Total</th><th style="text-align:right">Paid</th><th style="text-align:right">Balance</th><th>Status</th></tr>
                <?php foreach (array_reverse($invoices) as $i): [$sl, $st] = Fees::STATUS[$i['status']]; ?>
                <tr>
                    <td><a href="<?= e(url('fees/invoices/' . $i['id'])) ?>"><?= e($i['invoice_no']) ?></a></td>
                    <td><?= e($i['title']) ?><br><small class="muted"><?= e($i['class_name'] ?? '') ?> · <?= e(fmt_date($i['issue_date'])) ?></small></td>
                    <td style="text-align:right"><?= e(number_format((float) $i['total'], 2)) ?></td>
                    <td style="text-align:right"><?= e(number_format((float) $i['paid'], 2)) ?></td>
                    <td style="text-align:right"><strong><?= e(number_format(Fees::balance($i), 2)) ?></strong></td>
                    <td><?= badge($sl, $st) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$invoices): ?><tr><td colspan="6" class="muted">No bills yet.</td></tr><?php endif; ?>
            </table></div>
        </div>
        <div class="card" style="padding:0">
            <h2 style="padding:1rem 1rem 0">Account history</h2>
            <div class="table-wrap"><table>
                <tr><th>Date</th><th>Details</th><th style="text-align:right">Billed</th><th style="text-align:right">Paid</th><th style="text-align:right">Balance</th></tr>
                <?php foreach ($ledger as $l): ?>
                <tr>
                    <td><?= e(fmt_date($l['date'])) ?></td>
                    <td><a href="<?= e(url($l['link'])) ?>"<?= strpos($l['link'], 'receipts') !== false ? ' target="_blank" rel="noopener"' : '' ?>><?= e($l['text']) ?></a></td>
                    <td style="text-align:right"><?= $l['debit'] ? e(number_format($l['debit'], 2)) : '' ?></td>
                    <td style="text-align:right"><?= $l['credit'] ? e(number_format($l['credit'], 2)) : '' ?></td>
                    <td style="text-align:right"><?= e(number_format($l['balance'], 2)) ?></td>
                </tr>
                <?php endforeach; ?>
            </table></div>
        </div>
    </div>
    <div>
        <div class="card">
            <h2>Discounts</h2>
            <?php if ($siblingDiscount && $siblingDiscount['is_sibling']): ?><p class="hint">Qualifies for the automatic <strong><?= e($siblingDiscount['name']) ?></strong> (an older sibling is in school).</p><?php endif; ?>
            <?php if (can('fees.manage')): ?>
            <form method="post" action="<?= e(url('fees/pupil/' . $pupil['id'] . '/discounts')) ?>" class="form">
                <?= csrf_field() ?>
                <?php foreach ($discounts as $d): ?>
                    <label style="font-weight:400"><input type="checkbox" name="discounts[]" value="<?= (int) $d['id'] ?>"<?= checked(in_array((int) $d['id'], $myDiscounts, true)) ?>> <?= e($d['name']) ?> (<?= $d['kind'] === 'percent' ? e((float) $d['value'] . '%') : e(Fees::money($d['value'])) ?>)</label>
                <?php endforeach; ?>
                <label>Note<input name="note" maxlength="160" placeholder="e.g. approved by Board, Sept 2026"></label>
                <button class="btn btn-sm" type="submit">Save discounts</button>
                
            </form>
            <?php else: ?>
                <?php foreach ($discounts as $d): if (in_array((int) $d['id'], $myDiscounts, true)): ?><p><?= e($d['name']) ?></p><?php endif; endforeach; ?>
                <?php if (!$myDiscounts): ?><p class="muted">None.</p><?php endif; ?>
            <?php endif; ?>
        </div>
        <?php if (can('fees.manage')): ?>
        <div class="card">
            <h2>Other bill</h2>
            
            <form method="post" action="<?= e(url('fees/pupil/' . $pupil['id'] . '/invoice')) ?>" class="form">
                <?= csrf_field() ?>
                <label>Title<input name="title" required maxlength="150" placeholder="e.g. Arrears from 2025/2026"></label>
                <?php for ($k = 0; $k < 3; $k++): ?>
                <div class="grid-2"><input name="desc[]" maxlength="150" placeholder="Item"><input name="amt[]" inputmode="decimal" placeholder="Amount"></div>
                <?php endfor; ?>
                <label>Due date<input type="date" name="due_date"></label>
                <label style="font-weight:400"><input type="checkbox" name="sms" value="1"> SMS the bill to the parent</label>
                <button class="btn" type="submit">Create bill</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>
