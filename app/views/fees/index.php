<?php
/* Fees overview: this term at a glance, find a student, then By class / Latest payments as tabs. */
$pct = $sum['billed'] > 0 ? min(100, round($sum['paid'] * 100 / $sum['billed'])) : 0;
$mg = can('fees.manage');
$sv = fn(string $d, int $z = 18) => '<svg viewBox="0 0 24 24" width="' . $z . '" height="' . $z . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
$termName = $term ? trim(($term['name'] ?? '') . ' · ' . ($term['year_name'] ?? current_year()['name'] ?? ''), ' ·') : 'No current term';
?>
<div class="page-head fee-head">
    <div><h1 class="fee-title">Fees</h1><p class="page-sub"><?= e($termName) ?></p></div>
    <div class="actions">
        <form method="get" action="<?= e(url('fees')) ?>" class="fee-find" role="search">
            <?= $sv('<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>', 17) ?>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Find a student — name or admission no." aria-label="Find a student by name or admission number">
        </form>
        <?php if ($mg): ?>
        <details class="menu-btn">
            <summary class="btn btn-primary fee-new">+ New <?= $sv('<path d="m6 9 6 6 6-6"/>', 15) ?></summary>
            <div>
                <a href="<?= e(url('fees/generate')) ?>"><strong>Make term bills</strong><br><small class="muted">Bill every class for this term</small></a>
                <?php if (can('app.configure')): ?><a href="<?= e(url('fees/discounts')) ?>"><strong>Give a discount or scholarship</strong><br><small class="muted">Sibling, staff child, bursary</small></a>
                <a href="<?= e(url('fees/schedule')) ?>"><strong>Change the fee schedule</strong><br><small class="muted">What each class pays</small></a><?php endif; ?>
            </div>
        </details>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>
<?php if ($pendingOnline): ?><div class="alert alert-info"><?= (int) $pendingOnline ?> online payment(s) started but not yet confirmed. <a href="<?= e(url('fees/online')) ?>">Check them</a>.</div><?php endif; ?>

<?php if ($q !== ''): ?>
<section class="card fee-results" style="padding:0">
    <div class="fee-results-head"><h2>Students matching “<?= e($q) ?>”</h2><a class="btn btn-ghost btn-sm" href="<?= e(url('fees')) ?>">Clear</a></div>
    <?php if (!$found): ?><p class="muted" style="padding:0 1.2rem 1rem">No student found. Check the spelling or try the admission number.</p><?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Student</th><th>Admission no.</th><th class="num">Balance (GH₵)</th><th></th></tr></thead>
        <?php foreach ($found as $p): ?>
        <tr>
            <td><a href="<?= e(url('fees/pupil/' . $p['id'])) ?>"><strong><?= e(full_name($p)) ?></strong></a> <?= $p['status'] !== 'active' ? badge(ucfirst($p['status'])) : '' ?></td>
            <td><?= e($p['admission_no']) ?></td>
            <td class="num"><strong<?= $p['balance'] > 0 ? ' class="fee-owe"' : '' ?>><?= e(number_format((float) $p['balance'], 2)) ?></strong></td>
            <td class="num"><a class="btn btn-sm" href="<?= e(url('fees/pupil/' . $p['id'])) ?>">Statement</a></td>
        </tr>
        <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if ((int) $sum['bills'] === 0): ?>
<section class="card fee-empty">
    <div class="fee-empty-ico"><?= $sv('<path d="M6 2h9l5 5v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h5"/>', 28) ?></div>
    <h2>No bills for this term yet</h2>
    <?php if ($mg): ?><div class="fee-empty-actions"><a class="btn btn-primary" href="<?= e(url('fees/generate')) ?>">Make term bills</a><?php if (can('app.configure')): ?><a class="btn btn-ghost" href="<?= e(url('fees/schedule')) ?>">Check the fee schedule</a><?php endif; ?></div><?php endif; ?>
</section>
<?php else: ?>
<div class="fee-stats">
    <div class="fee-stat"><span>Billed this term</span><strong><?= e(Fees::money($sum['billed'])) ?></strong><small><?= (int) $sum['bills'] ?> bills</small></div>
    <div class="fee-stat"><span>Collected</span><strong><?= e(Fees::money($sum['paid'])) ?></strong><div class="fee-meter" aria-label="<?= $pct ?>% collected"><span style="width:<?= $pct ?>%"></span></div><small><?= $pct ?>% of what was billed</small></div>
    <div class="fee-stat is-owe"><span>Still owed</span><strong><?= e(Fees::money($sum['outstanding'])) ?></strong><small><a href="<?= e(url('fees/debtors')) ?>"><?= (int) $sum['owing'] ?> bills not fully paid →</a></small></div>
    <div class="fee-stat"><span>Received today</span><strong><?= e(Fees::money($today['amt'])) ?></strong><small><a href="<?= e(url('fees/daily')) ?>"><?= (int) $today['n'] ?> payment<?= (int) $today['n'] === 1 ? '' : 's' ?> →</a></small></div>
</div>
<?php endif; ?>

<div class="card ptabs" data-ptabs style="padding:0">
    <div class="ptab-bar" role="tablist" aria-label="Fees overview">
        <button type="button" role="tab" id="pt-classes" aria-controls="pp-classes" aria-selected="true" data-ptab="classes">By class <span class="ptab-n"><?= count($byClass) ?></span></button>
        <button type="button" role="tab" id="pt-payments" aria-controls="pp-payments" aria-selected="false" tabindex="-1" data-ptab="payments">Latest payments <span class="ptab-n"><?= count($recent) ?></span></button>
    </div>
    <div class="ptab-panel" role="tabpanel" id="pp-classes" aria-labelledby="pt-classes" data-ppanel="classes">
        <?php if ($byClass): ?>
        <div class="table-wrap"><table class="fee-table">
            <thead><tr><th>Class</th><th class="num">Bills</th><th class="num">Billed (GH₵)</th><th class="num">Collected (GH₵)</th><th class="num">Still owed (GH₵)</th><th>Collected</th></tr></thead>
            <?php foreach ($byClass as $c): $cp = $c['billed'] > 0 ? min(100, round($c['paid'] * 100 / $c['billed'])) : 0; $owe = max(0, (float) $c['billed'] - (float) $c['paid']); ?>
            <tr>
                <td><a href="<?= e(url('fees/invoices', ['term' => $term['id'] ?? '', 'class' => $c['id']])) ?>"><strong><?= e($c['name']) ?></strong></a></td>
                <td class="num"><?= (int) $c['bills'] ?></td>
                <td class="num"><?= e(number_format((float) $c['billed'], 2)) ?></td>
                <td class="num"><?= e(number_format((float) $c['paid'], 2)) ?></td>
                <td class="num<?= $owe > 0 ? ' fee-owe' : '' ?>"><?= e(number_format($owe, 2)) ?></td>
                <td><div class="fee-prog"><div class="fee-meter<?= $cp >= 80 ? ' good' : ($cp < 50 ? ' low' : '') ?>"><span style="width:<?= $cp ?>%"></span></div><small><?= $cp ?>%</small></div></td>
            </tr>
            <?php endforeach; ?>
        </table></div>
        <?php else: ?>
        <p class="muted fee-none">Each class appears here once this term's bills are made.</p>
        <?php endif; ?>
    </div>
    <div class="ptab-panel" role="tabpanel" id="pp-payments" aria-labelledby="pt-payments" data-ppanel="payments" hidden>
        <?php if ($recent): ?>
        <div class="table-wrap"><table class="fee-table">
            <thead><tr><th>Receipt</th><th>Student</th><th>Paid by</th><th>Date</th><th class="num">Amount (GH₵)</th></tr></thead>
            <?php foreach ($recent as $r): ?>
            <tr>
                <td><a href="<?= e(url('fees/receipts/' . $r['id'])) ?>" target="_blank" rel="noopener"><?= e($r['receipt_no']) ?></a> <?= $r['status'] === 'reversed' ? badge('Reversed', 'bad') : '' ?></td>
                <td><?= e($r['first_name'] . ' ' . $r['last_name']) ?></td>
                <td><?= e(Fees::PAID_BY[$r['method']] ?? $r['method']) ?></td>
                <td><?= e(fmt_date($r['paid_on'])) ?></td>
                <td class="num"><strong><?= e(number_format((float) $r['amount'], 2)) ?></strong></td>
            </tr>
            <?php endforeach; ?>
        </table></div>
        <p class="ptab-foot"><a href="<?= e(url('fees/collections')) ?>">All money collected →</a></p>
        <?php else: ?>
        <p class="muted fee-none">No payments yet.</p>
        <?php endif; ?>
    </div>
</div>
