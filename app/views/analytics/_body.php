<?php /* shared by index (screen) and print; expects $tab, $d, $term, $print */ $money = fn($v) => number_format((float) $v, 2); ?>
<?php if ($tab === 'enrolment'): $tot = ['b' => array_sum(array_column($d['rows'], 'boys')), 'g' => array_sum(array_column($d['rows'], 'girls'))]; ?>
    <?php if (!$print): ?>
    <div class="grid-4" style="margin-bottom:1.25rem">
        <div class="stat"><div class="num"><?= (int) ($tot['b'] + $tot['g']) ?></div><div class="label">Students on roll</div></div>
        <div class="stat"><div class="num"><?= (int) $tot['b'] ?> / <?= (int) $tot['g'] ?></div><div class="label">Boys / girls</div></div>
        <div class="stat"><div class="num"><?= (int) array_sum(array_column($d['rows'], 'new_pupils')) ?></div><div class="label">Admitted this year</div></div>
        <div class="stat"><div class="num"><?= (int) (($d['left']['withdrawn'] ?? 0) + ($d['left']['transferred'] ?? 0)) ?></div><div class="label">Withdrawn / transferred this year</div></div>
    </div>
    <div class="card"><h2>Students per class</h2>
        <?= Charts::bars(array_map(fn($r) => [$r['name'], (int) $r['total'], $r['name'] . ': ' . (int) $r['total'] . ' students (' . (int) $r['boys'] . ' boys, ' . (int) $r['girls'] . ' girls) · capacity ' . (int) $r['capacity']], $d['rows']), ['label' => 'Students per class', 'height' => 240, 'width' => 1000]) ?>
    </div>
    <?php endif; ?>
    <div class="card" style="padding:0"><div class="table-wrap"><table>
        <tr><th>Class</th><th>Class teacher</th><th class="rpt-num">Boys</th><th class="rpt-num">Girls</th><th class="rpt-num">Total</th><th class="rpt-num">Capacity</th><th class="rpt-num">New this year</th><th class="rpt-num">SDA</th></tr>
        <?php foreach ($d['rows'] as $r): $full = (int) $r['total'] > (int) $r['capacity']; ?>
        <tr><td><?= e($r['name']) ?></td><td><?= e($r['teacher'] ?? '—') ?></td><td class="rpt-num"><?= (int) $r['boys'] ?></td><td class="rpt-num"><?= (int) $r['girls'] ?></td>
            <td class="rpt-num"><strong><?= (int) $r['total'] ?></strong></td><td class="rpt-num"><?= (int) $r['capacity'] ?><?= $full ? ' ' . badge('Over', 'warn') : '' ?></td><td class="rpt-num"><?= (int) $r['new_pupils'] ?></td><td class="rpt-num"><?= (int) $r['sda'] ?></td></tr>
        <?php endforeach; ?>
        <tr><th colspan="2">Whole school</th><th class="rpt-num"><?= (int) $tot['b'] ?></th><th class="rpt-num"><?= (int) $tot['g'] ?></th><th class="rpt-num"><?= (int) ($tot['b'] + $tot['g']) ?></th><th class="rpt-num"><?= (int) array_sum(array_column($d['rows'], 'capacity')) ?></th><th class="rpt-num"><?= (int) array_sum(array_column($d['rows'], 'new_pupils')) ?></th><th class="rpt-num"><?= (int) array_sum(array_column($d['rows'], 'sda')) ?></th></tr>
    </table></div></div>
    <div class="card"><h2>Ages at the start of the year</h2>
        <p style="margin:0"><?php foreach ($d['ages'] as $a): ?><span class="badge" style="margin:0 .3rem .3rem 0"><?= (int) $a['age'] ?> yrs: <?= (int) $a['n'] ?></span><?php endforeach; ?><?= $d['ages'] ? '' : '<span class="muted">No students.</span>' ?></p>
        
    </div>

<?php elseif ($tab === 'census'): $sum = fn($k) => (int) array_sum(array_column($d['rows'], $k)); $cats = ['creche' => 'Creche', 'kg' => 'KG', 'primary' => 'Primary', 'jhs' => 'JHS']; $T = $d['staff']['teaching']; $N = $d['staff']['non_teaching']; ?>
    <?php if (!$print): ?>
    <div class="grid-4" style="margin-bottom:1.25rem">
        <div class="stat"><div class="num"><?= $sum('total') ?></div><div class="label">Students on roll (<?= $sum('boys') ?> boys, <?= $sum('girls') ?> girls)</div></div>
        <div class="stat"><div class="num"><?= $sum('sen_boys') + $sum('sen_girls') ?></div><div class="label">Learners with special needs</div></div>
        <div class="stat"><div class="num"><?= (int) ($T['total'] ?? 0) ?></div><div class="label">Teaching staff (<?= (int) ($T['trained'] ?? 0) ?> trained)</div></div>
        <div class="stat"><div class="num"<?= $d['licenceList'] ? ' style="color:var(--bad)"' : '' ?>><?= count($d['licenceList']) ?></div><div class="label">NTC licences to review</div></div>
    </div>
    <?php endif; ?>
    <h2>1. Enrolment by level</h2>
    <div class="card" style="padding:0"><div class="table-wrap"><table>
        <tr><th>Level</th><th class="rpt-num">Streams</th><th class="rpt-num">Boys</th><th class="rpt-num">Girls</th><th class="rpt-num">Total</th><th class="rpt-num">Repeaters B / G</th><th class="rpt-num">Special needs B / G</th></tr>
        <?php foreach ($d['rows'] as $r): ?>
        <tr><td><?= e($r['name']) ?></td><td class="rpt-num"><?= (int) $r['streams'] ?></td><td class="rpt-num"><?= (int) $r['boys'] ?></td><td class="rpt-num"><?= (int) $r['girls'] ?></td><td class="rpt-num"><strong><?= (int) $r['total'] ?></strong></td>
            <td class="rpt-num"><?= (int) $r['rep_boys'] ?> / <?= (int) $r['rep_girls'] ?></td><td class="rpt-num"><?= (int) $r['sen_boys'] ?> / <?= (int) $r['sen_girls'] ?></td></tr>
        <?php endforeach; ?>
        <tr><th>Whole school</th><th class="rpt-num"><?= $sum('streams') ?></th><th class="rpt-num"><?= $sum('boys') ?></th><th class="rpt-num"><?= $sum('girls') ?></th><th class="rpt-num"><?= $sum('total') ?></th><th class="rpt-num"><?= $sum('rep_boys') ?> / <?= $sum('rep_girls') ?></th><th class="rpt-num"><?= $sum('sen_boys') ?> / <?= $sum('sen_girls') ?></th></tr>
    </table></div></div>

    <h2>2. Enrolment by age (boys / girls)</h2>
    <div class="card" style="padding:0"><div class="table-wrap"><table>
        <tr><th>Age</th><?php foreach ($cats as $c => $l): ?><th class="rpt-num"><?= e($l) ?></th><?php endforeach; ?><th class="rpt-num">All</th></tr>
        <?php foreach ($d['ages'] as $age => $byCat): $tb = 0; $tg = 0; ?>
        <tr><td><?= $age <= 2 ? '2 or under' : ($age >= 17 ? '17 or over' : (int) $age) ?></td>
            <?php foreach ($cats as $c => $l): $b = $byCat[$c]['male'] ?? 0; $g = $byCat[$c]['female'] ?? 0; $tb += $b; $tg += $g; ?><td class="rpt-num"><?= ($b + $g) ? $b . ' / ' . $g : '—' ?></td><?php endforeach; ?>
            <td class="rpt-num"><strong><?= $tb ?> / <?= $tg ?></strong></td></tr>
        <?php endforeach; ?>
        <?php if (!$d['ages']): ?><tr><td colspan="6" class="muted">No students enrolled this year.</td></tr><?php endif; ?>
    </table></div></div>

    <div class="cols">
        <div>
            <h2>3. Learners with special needs</h2>
            <div class="card" style="padding:0"><div class="table-wrap"><table>
                <tr><th>Type</th><th class="rpt-num">Boys</th><th class="rpt-num">Girls</th></tr>
                <?php foreach ($d['sen'] as $s): ?><tr><td><?= e(Admission::SEN[$s['sen_category']] ?? $s['sen_category']) ?></td><td class="rpt-num"><?= (int) $s['boys'] ?></td><td class="rpt-num"><?= (int) $s['girls'] ?></td></tr><?php endforeach; ?>
                <?php if (!$d['sen']): ?><tr><td colspan="3" class="muted">None recorded.</td></tr><?php endif; ?>
            </table></div></div>
        </div>
        <div>
            <h2>4. Staff</h2>
            <div class="card" style="padding:0"><div class="table-wrap"><table>
                <tr><th></th><th class="rpt-num">Male</th><th class="rpt-num">Female</th><th class="rpt-num">Gender not set</th><th class="rpt-num">Total</th></tr>
                <?php foreach (['Teaching' => $T, 'Non-teaching' => $N] as $l => $x): ?>
                <tr><td><?= e($l) ?></td><td class="rpt-num"><?= (int) ($x['male'] ?? 0) ?></td><td class="rpt-num"><?= (int) ($x['female'] ?? 0) ?></td><td class="rpt-num"><?= (int) ($x['unknown'] ?? 0) ?></td><td class="rpt-num"><strong><?= (int) ($x['total'] ?? 0) ?></strong></td></tr>
                <?php endforeach; ?>
            </table></div></div>
            <div class="card"><dl class="details">
                <dt>Trained teachers</dt><dd><?= (int) ($T['trained'] ?? 0) ?></dd>
                <dt>Untrained teachers</dt><dd><?= (int) ($T['untrained'] ?? 0) ?></dd>
                <dt>Qualification not recorded</dt><dd><?= (int) ($T['qual_unknown'] ?? 0) ?></dd>
                <dt>Valid NTC licence</dt><dd><?= (int) ($T['ntc_valid'] ?? 0) + (int) ($T['ntc_expiring'] ?? 0) ?></dd>
                <dt>Licence expired / not recorded</dt><dd><?= (int) ($T['ntc_expired'] ?? 0) ?> / <?= (int) ($T['ntc_missing'] ?? 0) ?></dd>
            </dl></div>
        </div>
    </div>
    <?php if ($d['licenceList']): ?>
    <h2>Teachers to follow up (NTC licence)</h2>
    <div class="card" style="padding:0"><div class="table-wrap"><table>
        <tr><th>Teacher</th><th>Position</th><th>Licence no.</th><th>Expires</th><th>Status</th></tr>
        <?php foreach ($d['licenceList'] as $s): ?><tr><td><?= e($s['name']) ?></td><td><?= e($s['position']) ?></td><td><?= e($s['ntc_licence_no'] ?? '—') ?></td><td><?= $s['ntc_expiry'] ? e(fmt_date($s['ntc_expiry'])) : '—' ?></td><td><?= Payroll::ntcBadge($s) ?></td></tr><?php endforeach; ?>
    </table></div></div>
    <?php endif; ?>

<?php elseif ($tab === 'attendance'): $marks = array_sum(array_column($d['rows'], 'marks')); $pres = array_sum(array_column($d['rows'], 'present')); ?>
    <?php if (!$print): ?>
    <div class="grid-3" style="margin-bottom:1.25rem">
        <div class="stat"><div class="num"><?= $marks ? round($pres * 100 / $marks, 1) . '%' : '—' ?></div><div class="label">Whole-school attendance this term</div></div>
        <div class="stat"><div class="num"><?= (int) array_sum(array_column($d['rows'], 'absent')) ?></div><div class="label">Absences recorded</div></div>
        <div class="stat"><div class="num"<?= $d['chronic'] ? ' style="color:var(--bad)"' : '' ?>><?= count($d['chronic']) ?></div><div class="label">Students below 85% attendance</div></div>
    </div>
    <div class="card"><h2>Attendance rate by class</h2><p class="muted" style="margin:0 0 .5rem;font-size:.85rem">Dashed line = 90% target</p>
        <?= Charts::bars(array_values(array_map(fn($r) => [$r['name'], (float) $r['rate'], $r['name'] . ': ' . ($r['rate'] === null ? 'no registers' : $r['rate'] . '% over ' . (int) $r['days'] . ' days')], $d['rows'])), ['max' => 100, 'target' => 90, 'format' => fn($v) => $v . '%', 'label' => 'Attendance rate by class', 'width' => 1000, 'height' => 240, 'soft' => array_keys(array_filter(array_values($d['rows']), fn($r) => $r['rate'] !== null && $r['rate'] < 90))]) ?>
        <div class="chart-legend"><span><i style="background:var(--brand)"></i>90% or more</span><span><i style="background:#c9d6ea"></i>Below 90%</span></div>
    </div>
    <?php endif; ?>
    <div class="card" style="padding:0"><div class="table-wrap"><table>
        <tr><th>Class</th><th class="rpt-num">Days marked</th><th class="rpt-num">Present / late</th><th class="rpt-num">Absent</th><th class="rpt-num">Late</th><th class="rpt-num">Excused / sick</th><th class="rpt-num">Attendance</th></tr>
        <?php foreach ($d['rows'] as $r): ?>
        <tr><td><?= $print ? e($r['name']) : '<a href="' . e(url('attendance/class/' . $r['id'])) . '">' . e($r['name']) . '</a>' ?></td><td class="rpt-num"><?= (int) $r['days'] ?></td><td class="rpt-num"><?= (int) $r['present'] ?></td><td class="rpt-num"><?= (int) $r['absent'] ?></td><td class="rpt-num"><?= (int) $r['late'] ?></td><td class="rpt-num"><?= (int) $r['excused'] ?></td>
            <td class="rpt-num"><?= $r['rate'] === null ? '—' : '<strong>' . $r['rate'] . '%</strong>' ?></td></tr>
        <?php endforeach; ?>
    </table></div></div>
    <h2>Students below 85% attendance</h2>
    <div class="card" style="padding:0"><div class="table-wrap"><table>
        <tr><th>Student</th><th>Class</th><th class="rpt-num">Days marked</th><th class="rpt-num">Absent</th><th class="rpt-num">Attendance</th><th>Parent phone</th></tr>
        <?php foreach ($d['chronic'] as $p): ?>
        <tr><td><?= $print ? e($p['first_name'] . ' ' . $p['last_name']) : '<a href="' . e(url('pupils/' . $p['id'])) . '">' . e($p['first_name'] . ' ' . $p['last_name']) . '</a>' ?> <small class="muted"><?= e($p['admission_no']) ?></small></td><td><?= e($p['class_name']) ?></td>
            <td class="rpt-num"><?= (int) $p['marks'] ?></td><td class="rpt-num"><?= (int) $p['absent'] ?></td><td class="rpt-num"><strong><?= e($p['rate']) ?>%</strong></td><td><?= e($p['phone'] ?? '') ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$d['chronic']): ?><tr><td colspan="6" class="muted">No student is below 85% (students need at least 5 marked days to be listed).</td></tr><?php endif; ?>
    </table></div></div>

<?php elseif ($tab === 'academic'): ?>
    <?php $scored = array_filter($d['rows'], fn($r) => $r['average'] !== null); if (!$print && $scored): ?>
    <div class="card"><h2>Class averages</h2>
        <?= Charts::bars(array_values(array_map(fn($r) => [$r['name'], (float) $r['average'], $r['name'] . ': average ' . $r['average'] . '%, pass rate ' . $r['pass'] . '%'], $scored)), ['max' => 100, 'target' => 50, 'format' => fn($v) => $v . '%', 'label' => 'Class average scores', 'height' => 240, 'width' => 1000]) ?>
        
    </div>
    <?php endif; ?>
    <div class="card" style="padding:0"><div class="table-wrap"><table>
        <tr><th>Class</th><th class="rpt-num">Students</th><th class="rpt-num">Average</th><th class="rpt-num">Pass rate</th><th>Best student</th><th>Score sheets</th></tr>
        <?php foreach ($d['rows'] as $r): ?>
        <tr><td><?= $print ? e($r['name']) : '<a href="' . e(url('reports/class/' . $r['id'] . '/broadsheet', ['term' => $term['id']])) . '">' . e($r['name']) . '</a>' ?></td><td class="rpt-num"><?= (int) $r['pupils'] ?></td>
            <td class="rpt-num"><?= $r['average'] === null ? '—' : '<strong>' . $r['average'] . '%</strong>' ?></td>
            <td class="rpt-num"><?= $r['pass'] === null ? '—' : $r['pass'] . '%' ?></td><td><?= e($r['best'] ?? '—') ?></td>
            <td><?= (int) $r['sheets']['approved'] ?> of <?= (int) $r['sheets']['total'] ?> approved<?= $r['sheets']['submitted'] ? ' · ' . (int) $r['sheets']['submitted'] . ' waiting' : '' ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$d['rows']): ?><tr><td colspan="6" class="muted">No classes with students this year.</td></tr><?php endif; ?>
    </table></div></div>
    <div class="cols">
        <div><h2>Subjects across the school</h2>
        <div class="card" style="padding:0"><div class="table-wrap"><table>
            <tr><th>Subject</th><th class="rpt-num">Students scored</th><th class="rpt-num">Average</th><th class="rpt-num">Pass rate</th></tr>
            <?php foreach ($d['subjects'] as $s): ?>
            <tr><td title="<?= e(implode(' · ', $s['classes'])) ?>"><?= e($s['name']) ?></td><td class="rpt-num"><?= (int) $s['n'] ?></td><td class="rpt-num"><strong><?= $s['average'] ?>%</strong></td>
                <td class="rpt-num"><?= $s['pass'] ?>%<?php if (!$print): ?><span class="pct-bar"><span style="width:<?= min(100, (float) $s['pass']) ?>%"></span></span><?php endif; ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$d['subjects']): ?><tr><td colspan="4" class="muted">No scores entered for this term yet.</td></tr><?php endif; ?>
        </table></div></div></div>
        <div><h2>Top 10 students (average)</h2>
        <div class="card" style="padding:0"><div class="table-wrap"><table>
            <?php foreach ($d['top'] as $i => $t): ?><tr><td><?= $i + 1 ?>.</td><td><?= e($t['name']) ?><br><small class="muted"><?= e($t['class']) ?></small></td><td class="rpt-num"><strong><?= $t['average'] ?>%</strong></td></tr><?php endforeach; ?>
            <?php if (!$d['top']): ?><tr><td class="muted">No results yet.</td></tr><?php endif; ?>
        </table></div></div></div>
    </div>

<?php elseif ($tab === 'fees'): $b = array_sum(array_column($d['rows'], 'billed')); $p = array_sum(array_column($d['rows'], 'paid')); ?>
    <?php if (!$print): ?>
    <div class="grid-4" style="margin-bottom:1.25rem">
        <div class="stat"><div class="num"><?= e(Fees::money($b)) ?></div><div class="label">Billed this term</div></div>
        <div class="stat"><div class="num" style="color:var(--good)"><?= e(Fees::money($p)) ?></div><div class="label">Collected (<?= $b > 0 ? round($p * 100 / $b) : 0 ?>%)</div></div>
        <div class="stat"><div class="num" style="color:var(--bad)"><?= e(Fees::money(array_sum(array_column($d['rows'], 'outstanding')))) ?></div><div class="label">Outstanding this term</div></div>
        <div class="stat"><div class="num"><?= e(Fees::money($d['arrears'])) ?></div><div class="label">Arrears from earlier terms</div></div>
    </div>
    <?php $billedRows = array_values(array_filter($d['rows'], fn($r) => $r['pct'] !== null)); if ($billedRows): ?>
    <div class="card"><h2>% of fees collected by class</h2>
        <?= Charts::bars(array_map(fn($r) => [$r['name'], (float) $r['pct'], $r['name'] . ': ' . $r['pct'] . '% collected — ' . Fees::money($r['paid']) . ' of ' . Fees::money($r['billed'])], $billedRows), ['max' => 100, 'format' => fn($v) => $v . '%', 'label' => 'Percentage of fees collected by class', 'width' => 1000, 'height' => 240]) ?>
    </div>
    <?php endif; endif; ?>
    <div class="card" style="padding:0"><div class="table-wrap"><table>
        <tr><th>Class</th><th class="rpt-num">Bills</th><th class="rpt-num">Billed</th><th class="rpt-num">Paid</th><th class="rpt-num">Outstanding</th><th class="rpt-num">Collected</th><th class="rpt-num">Fully paid</th></tr>
        <?php foreach ($d['rows'] as $r): ?>
        <tr><td><?= $print ? e($r['name']) : '<a href="' . e(url('fees/debtors', ['class' => $r['id']])) . '">' . e($r['name']) . '</a>' ?></td><td class="rpt-num"><?= (int) $r['bills'] ?></td><td class="rpt-num"><?= $money($r['billed']) ?></td><td class="rpt-num"><?= $money($r['paid']) ?></td>
            <td class="rpt-num"><strong><?= $money($r['outstanding']) ?></strong></td><td class="rpt-num"><?= $r['pct'] === null ? '—' : $r['pct'] . '%' ?></td><td class="rpt-num"><?= (int) $r['fully_paid'] ?></td></tr>
        <?php endforeach; ?>
        <tr><th>Whole school</th><th class="rpt-num"><?= (int) array_sum(array_column($d['rows'], 'bills')) ?></th><th class="rpt-num"><?= $money($b) ?></th><th class="rpt-num"><?= $money($p) ?></th><th class="rpt-num"><?= $money(array_sum(array_column($d['rows'], 'outstanding'))) ?></th><th class="rpt-num"><?= $b > 0 ? round($p * 100 / $b, 1) . '%' : '—' ?></th><th class="rpt-num"><?= (int) array_sum(array_column($d['rows'], 'fully_paid')) ?></th></tr>
    </table></div></div>
    <h2>How parents paid (this term's bills)</h2>
    <div class="card" style="padding:0"><div class="table-wrap"><table>
        <tr><th>Method</th><th class="rpt-num">Payments</th><th class="rpt-num">Amount</th></tr>
        <?php foreach ($d['methods'] as $m): ?><tr><td><?= e(ucfirst($m['method'])) ?></td><td class="rpt-num"><?= (int) $m['n'] ?></td><td class="rpt-num"><?= $money($m['amt']) ?></td></tr><?php endforeach; ?>
        <?php if (!$d['methods']): ?><tr><td colspan="3" class="muted">No payments yet.</td></tr><?php endif; ?>
    </table></div></div>
<?php elseif ($tab === 'money'): $pct = $d['billed'] > 0 ? round($d['paid'] * 100 / $d['billed']) : null; ?>
    <div class="grid-4" style="margin-bottom:1.25rem">
        <div class="stat"><div class="num"><?= e(Fees::money($d['cash'])) ?></div><div class="label">Cash in all accounts today</div></div>
        <div class="stat"><div class="num"<?= $d['runway'] !== null && $d['runway'] < (float) setting('reserve_min_months', '3') ? ' style="color:var(--bad)"' : '' ?>><?= $d['runway'] !== null ? e(rtrim(rtrim(number_format($d['runway'], 1), '0'), '.')) . ' months' : '—' ?></div><div class="label">Cash runway (cash ÷ <?= e(Fees::money($d['monthly'])) ?> average monthly spending)</div></div>
        <div class="stat"><div class="num"><?= $pct !== null ? $pct . '%' : '—' ?></div><div class="label">Of this term's fees collected (<?= e(Fees::money($d['paid'])) ?> of <?= e(Fees::money($d['billed'])) ?>)</div></div>
        <div class="stat"><div class="num"><?= $d['breakEven'] !== null ? (int) $d['breakEven'] : '—' ?></div><div class="label">Fee-paying students needed to cover this term's spending so far (<?= e(Fees::money($d['termSpend'])) ?> ÷ <?= e(Fees::money($d['avgFee'])) ?> average bill; <?= (int) $d['pupils'] ?> billed)</div></div>
    </div>
    <?php if (!$print && array_filter($d['months'], fn($m) => $m['in'] > 0 || $m['out'] > 0)): ?>
    <div class="card"><h2>Money in and out — last 6 months</h2>
        <div class="table-wrap"><table data-nopage><tr><th>Month</th><th class="rpt-num">In</th><th class="rpt-num">Out</th><th class="rpt-num">Difference</th></tr>
        <?php foreach ($d['months'] as $m): ?><tr><td><?= e(date('M Y', strtotime($m['month'] . '-01'))) ?></td><td class="rpt-num"><?= $money($m['in']) ?></td><td class="rpt-num"><?= $money($m['out']) ?></td><td class="rpt-num"<?= $m['in'] - $m['out'] < 0 ? ' style="color:var(--bad)"' : '' ?>><?= $money($m['in'] - $m['out']) ?></td></tr><?php endforeach; ?>
        </table></div>
    </div>
    <?php endif; ?>
    <div class="card" style="padding:0"><div class="table-wrap"><table>
        <tr><th>Account</th><th>Kind</th><th class="rpt-num">Balance</th></tr>
        <?php foreach ($d['rows'] as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= e($r['kind']) ?></td><td class="rpt-num"><?= $money($r['balance']) ?></td></tr><?php endforeach; ?>
        <tr><th colspan="2">All accounts</th><th class="rpt-num"><?= $money($d['cash']) ?></th></tr>
    </table></div></div>
<?php endif; ?>
