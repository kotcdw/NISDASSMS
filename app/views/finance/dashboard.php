<?php
/* Finance dashboard. Expects $terms, $term, $d (FinanceDashboard::data). */
$m0 = fn($v) => 'GH₵ ' . number_format((float) $v, 0);
$m2 = fn($v) => 'GH₵ ' . number_format((float) $v, 2);
$sv = fn(string $p, int $z = 15) => '<svg viewBox="0 0 24 24" width="' . $z . '" height="' . $z . '" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
$caret = $sv('<path d="m6 9 6 6 6-6"/>', 14);
$q = fn(array $over) => url('finance/dashboard', array_filter(array_merge(['term' => $term['id'] ?? null, 'account' => $d['account'] ?: null], $over)));
$accName = 'All accounts';
foreach ($d['accounts'] as $a) { if ($a['id'] === $d['account']) $accName = $a['name']; }
$f = $d['fees'];
$pct = $f && $f['billed'] > 0 ? (int) round($f['paid'] * 100 / $f['billed']) : 0;
$used = $d['budget'] > 0 ? $d['spent'] * 100 / $d['budget'] : 0;
$collected = array_sum(array_column($d['daily'], 'amt'));
[$line, $area] = FinanceDashboard::sparkline(array_map('floatval', array_column($d['daily'], 'amt')));
$chanTotal = array_sum(array_column($d['channels'], 'amt')) ?: 1;
$chanColor = ['momo' => '#0f8b8d', 'cash' => '#3fbfb8', 'bank' => '#1b2f86', 'online' => '#7c5cc4', 'cheque' => '#c48a2c', 'credit' => '#9aa3b5'];
$today = new DateTimeImmutable('today');
?>
<div class="page-head fd-head">
    <div><h1 class="fee-title">Finance dashboard</h1>
        <div class="fd-filters">
            <details class="menu-btn fd-pick"><summary>Term: <b><?= e($term ? $term['name'] . ' · ' . $term['year_name'] : '—') ?></b><?= $caret ?></summary><div>
                <?php foreach ($terms as $t): ?><a href="<?= e($q(['term' => $t['id']])) ?>"<?= $term && (int) $t['id'] === (int) $term['id'] ? ' aria-current="page"' : '' ?>><?= e($t['name'] . ' · ' . $t['year_name']) ?><?= (int) $t['is_current'] ? ' <small class="muted">(current)</small>' : '' ?></a><?php endforeach; ?>
            </div></details>
            <details class="menu-btn fd-pick"><summary><b><?= e($accName) ?></b><?= $caret ?></summary><div>
                <a href="<?= e($q(['account' => null])) ?>"<?= !$d['account'] ? ' aria-current="page"' : '' ?>>All accounts</a>
                <?php foreach ($d['accounts'] as $a): ?><a href="<?= e($q(['account' => $a['id']])) ?>"<?= $a['id'] === $d['account'] ? ' aria-current="page"' : '' ?>><?= e($a['name']) ?></a><?php endforeach; ?>
            </div></details>
        </div>
    </div>
    <div class="actions">
        <button type="button" class="btn" onclick="window.print()">Print</button>
        <?php if (can('finance.manage') || can('fees.manage')): ?>
        <details class="menu-btn"><summary class="btn btn-primary fee-new">+ New <?= $caret ?></summary><div>
            <?php if (can('finance.manage')): ?><a href="<?= e(url('finance/new', ['kind' => 'income'])) ?>">Income (money in)</a>
            <a href="<?= e(url('finance/new', ['kind' => 'expense'])) ?>">Expense (money out)</a>
            <a href="<?= e(url('finance/new', ['kind' => 'transfer'])) ?>">Transfer between accounts</a><?php endif; ?>
            <?php if (can('fees.manage')): ?><a href="<?= e(url('fees/generate')) ?>">Make term bills</a><?php endif; ?>
            <?php if (can('requisitions.create')): ?><a href="<?= e(url('requisitions/create')) ?>">Requisition</a><?php endif; ?>
        </div></details>
        <?php endif; ?>
    </div>
</div>

<section class="fd-kpis">
    <div class="fd-kpi"><span class="fd-l">Total money held</span><strong class="fd-v"><?= e($m2($d['held'])) ?></strong>
        <small><?= $d['account'] ? e($accName) : $d['heldCount'] . ' accounts' ?><?php foreach (!$d['account'] ? $d['heldTop'] : [] as $a): ?> · <?= e(preg_replace('/\s*[(—].*$/u', '', $a['name'])) ?> <?= e($m0($a['balance'])) ?><?php endforeach; ?></small></div>
    <?php if ($f): ?>
    <div class="fd-kpi"><span class="fd-l">Fees collected this term</span><strong class="fd-v is-teal"><?= e($m0($f['paid'])) ?></strong>
        <div class="fd-bar"><span style="width:<?= $pct ?>%"></span></div><small><?= $pct ?>% of <?= e($m0($f['billed'])) ?> billed</small></div>
    <div class="fd-kpi"><span class="fd-l">Still owed</span><strong class="fd-v is-bad"><?= e($m0($f['owed'])) ?></strong>
        <small><a href="<?= e(url('fees/debtors')) ?>"><span class="fd-chip bad"><?= (int) $f['owing'] ?> bills</span> not fully paid →</a></small></div>
    <?php endif; ?>
    <div class="fd-kpi"><span class="fd-l">Budget used (year to date)</span><strong class="fd-v"><?= $d['budget'] > 0 ? number_format($used, 1) . '%' : '—' ?></strong>
        <?php if ($d['budget'] > 0): ?><div class="fd-bar"><span style="width:<?= min(100, $used) ?>%;background:<?= $used > 90 ? 'var(--bad)' : '#f0a35e' ?>"></span></div>
        <small><?= e($m0($d['spent'])) ?> of <?= e($m0($d['budget'])) ?> · <?= e($m0(max(0, $d['budget'] - $d['spent']))) ?> left</small>
        <?php else: ?><small><a href="<?= e(url('finance/budget')) ?>">Set this year's budget →</a></small><?php endif; ?></div>
</section>

<section class="fd-cards">
    <?php if (can('fees.view')): ?>
    <div class="fd-card">
        <h2>Money collected <a href="<?= e(url('fees/collections')) ?>">Details →</a></h2>
        <div class="fd-big"><?= e($m0($collected)) ?></div>
        <?php if ($line): ?>
        <svg class="fd-spark" viewBox="0 0 320 110" preserveAspectRatio="none" role="img" aria-label="Money collected each paying day this term">
            <defs><linearGradient id="fdg" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#0f8b8d" stop-opacity=".25"/><stop offset="1" stop-color="#0f8b8d" stop-opacity="0"/></linearGradient></defs>
            <path d="<?= $area ?>" fill="url(#fdg)"/><path d="<?= $line ?>" fill="none" stroke="#0f8b8d" stroke-width="2.4" stroke-linejoin="round" vector-effect="non-scaling-stroke"/>
        </svg>
        <div class="fd-foot"><span><?= e(fmt_date($d['daily'][0]['day'], 'j M')) ?> – <?= e(fmt_date(end($d['daily'])['day'], 'j M')) ?> · <?= count($d['daily']) ?> paying days</span><span><b class="fd-up"><?= e($m0($collected / max(1, count($d['daily'])))) ?></b> a day</span></div>
        <div class="fd-chan"><?php foreach ($d['channels'] as $c): ?><span style="width:<?= $c['amt'] * 100 / $chanTotal ?>%;background:<?= $chanColor[$c['method']] ?? '#9aa3b5' ?>"></span><?php endforeach; ?></div>
        <div class="fd-chanl"><?php foreach ($d['channels'] as $c): ?><span><i style="background:<?= $chanColor[$c['method']] ?? '#9aa3b5' ?>"></i><?= e(str_replace(' (direct)', '', Fees::PAID_BY[$c['method']] ?? $c['method'])) ?> <b><?= round($c['amt'] * 100 / $chanTotal) ?>%</b></span><?php endforeach; ?></div>
        <?php else: ?><p class="muted fd-empty">No payments in this term yet.</p><?php endif; ?>
    </div>
    <div class="fd-card">
        <h2>Who owes the most <?php if ($f && $f['owing']): ?><a href="<?= e(url('fees/debtors')) ?>">All <?= (int) $f['owing'] ?> →</a><?php endif; ?></h2>
        <?php if ($d['owes']): ?><ul class="fd-owe">
            <?php foreach ($d['owes'] as $o): $late = $o['due'] ? (int) $today->diff(new DateTimeImmutable($o['due']))->format('%r%a') * -1 : 0; ?>
            <li><div><a href="<?= e(url('fees/pupil/' . $o['id'])) ?>"><b><?= e($o['first_name'] . ' ' . $o['last_name']) ?></b></a><span><?= e($o['class_name'] ?? '') ?></span></div>
                <div class="fd-amt"><b><?= e($m2($o['owed'])) ?></b><span class="<?= $late > 0 ? 'is-late' : '' ?>"><?= $o['due'] ? 'Due ' . e(fmt_date($o['due'], 'j M')) . ($late > 0 ? ' · ' . $late . ' days late' : '') : '' ?></span></div></li>
            <?php endforeach; ?>
        </ul><?php else: ?><p class="muted fd-empty">Nobody owes on this term's bills.</p><?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="fd-card">
        <h2>Income vs spending <a href="<?= e(url('finance/reports')) ?>">Report →</a></h2>
        <div class="fd-legend"><span><i style="background:#0f8b8d"></i>Income</span><span><i style="background:#f0a35e"></i>Spending</span></div>
        <?php if ($d['months']):
            $W = 320; $H = 190; $L = 40; $B = 22; $T = 8;
            $max = FinanceDashboard::niceMax(max(array_map(fn($r) => max((float) $r['income'], (float) $r['spent']), $d['months'])));
            $gw = ($W - $L) / count($d['months']); $bw = min(26, $gw / 2 - 6); ?>
        <svg class="fd-bars" viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="Income and spending by month">
            <?php for ($k = 0; $k <= 4; $k++): $v = $max * $k / 4; $y = $H - $B - $v / $max * ($H - $B - $T); ?>
            <line x1="<?= $L ?>" x2="<?= $W ?>" y1="<?= $y ?>" y2="<?= $y ?>" stroke="#eef1f6"/><text x="<?= $L - 6 ?>" y="<?= $y + 4 ?>" font-size="10" fill="#5b6478" text-anchor="end"><?= $v >= 1000 ? round($v / 1000, 1) . 'k' : round($v) ?></text>
            <?php endfor; ?>
            <?php foreach ($d['months'] as $i => $r): $x = $L + $i * $gw + $gw / 2; $hi = $r['income'] / $max * ($H - $B - $T); $he = $r['spent'] / $max * ($H - $B - $T); ?>
            <rect x="<?= $x - $bw - 2 ?>" y="<?= $H - $B - $hi ?>" width="<?= $bw ?>" height="<?= max(1, $hi) ?>" rx="4" fill="#0f8b8d"><title><?= e(date('M Y', strtotime($r['ym'] . '-01'))) ?> income <?= e($m2($r['income'])) ?></title></rect>
            <rect x="<?= $x + 2 ?>" y="<?= $H - $B - $he ?>" width="<?= $bw ?>" height="<?= max(1, $he) ?>" rx="4" fill="#f0a35e"><title><?= e(date('M Y', strtotime($r['ym'] . '-01'))) ?> spending <?= e($m2($r['spent'])) ?></title></rect>
            <text x="<?= $x ?>" y="<?= $H - 6 ?>" font-size="11" fill="#3a4459" text-anchor="middle"><?= e(date('M', strtotime($r['ym'] . '-01'))) ?></text>
            <?php endforeach; ?>
        </svg>
        <?php else: ?><p class="muted fd-empty">No money in or out recorded in this term yet.</p><?php endif; ?>
    </div>
</section>

<section class="fd-card fd-table">
    <div class="fd-th"><h2>Recent large transactions (<?= e($m0($d['large_from'])) ?> and above)</h2><a href="<?= e(url('finance/transactions')) ?>">Cash book →</a></div>
    <?php if ($d['large']): ?>
    <div class="xgrid-box fd-grid" data-xgrid="fin-large" data-xgrid-file="large-transactions"><div class="xg-scroll"><table class="xgrid">
        <thead><tr><?php foreach (['date' => ['Date', 'date'], 'no' => ['No.', 'text'], 'desc' => ['Description', 'text'], 'cat' => ['Category', 'text'], 'amt' => ['Amount (GH₵)', 'num'], 'st' => ['Status', 'text']] as $k => [$h, $ty]): ?><th data-c="<?= $k ?>" data-type="<?= $ty ?>"<?= $k === 'amt' ? ' class="num"' : '' ?>><button type="button" class="xg-sort"><?= e($h) ?><span class="xg-arrow"></span></button></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach ($d['large'] as $t): $in = $t['kind'] === 'income'; $sign = $t['kind'] === 'transfer' ? '' : ($in ? '+' : '−'); ?>
        <tr>
            <td data-c="date" data-s="<?= e($t['txn_date']) ?>"><?= e(fmt_date($t['txn_date'], 'j M')) ?></td>
            <td data-c="no"><a href="<?= e(url('finance/transactions/' . $t['id'])) ?>"><strong><?= e($t['txn_no']) ?></strong></a></td>
            <td data-c="desc" class="fd-desc"><?= e($t['description']) ?></td>
            <td data-c="cat"><?= e($t['category'] ?? ($t['kind'] === 'transfer' ? 'Transfer' : '—')) ?></td>
            <td data-c="amt" data-s="<?= ($in || $t['kind'] === 'transfer' ? 1 : -1) * (float) $t['amount'] ?>" class="num <?= $sign === '' ? '' : ($in ? 'fd-in' : 'fd-out') ?>"><?= $sign ?><?= e(number_format((float) $t['amount'], 2)) ?></td>
            <td data-c="st"><?= $t['cleared_on'] ? '<span class="fd-chip good">Cleared</span>' : '<span class="fd-chip info">Posted</span>' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div></div>
    <?php else: ?><p class="muted fd-empty" style="padding:0 1.2rem 1.2rem">No transactions of <?= e($m0($d['large_from'])) ?> or more yet. Change the amount in Fee settings.</p><?php endif; ?>
</section>
