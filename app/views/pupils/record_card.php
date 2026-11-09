<?php
/* Cumulative record card (GES). Expects $pupil, $guardians, $rows (per term), $subjects, $pterms, $bece, $health. */
$p = $pupil;
$docTitle = 'CUMULATIVE RECORD CARD';
$docSub = $p['admission_no'];
$sens = can('pupils.sensitive');
$fmt = fn($v) => $v === null ? '—' : Results::fmt((float) $v);
$years = [];
foreach ($rows as $r) {
    $years[$r['year_name'] . ' · ' . $r['class_name']][] = $r;
}
?>
<div class="card-page doc-page">
    <?php require dirname(__DIR__) . '/fees/_doc_head.php'; ?>
    <table class="rc-info">
        <tr><th>Name</th><td colspan="3"><strong><?= e(trim($p['first_name'] . ' ' . ($p['middle_name'] ?? '') . ' ' . $p['last_name'])) ?></strong></td><th>Admission no.</th><td><?= e($p['admission_no']) ?></td></tr>
        <tr><th>Date of birth</th><td><?= e(fmt_date($p['date_of_birth'])) ?></td><th>Gender</th><td><?= e(ucfirst((string) $p['gender'])) ?></td><th>Nationality</th><td><?= e($p['nationality'] ?? '') ?></td></tr>
        <tr><th>Hometown</th><td><?= e($p['hometown'] ?? '') ?></td><th>Denomination</th><td><?= e($p['denomination'] ?? '') ?></td><th>House</th><td><?= e($p['house'] ?? '') ?></td></tr>
        <tr><th>Admitted</th><td><?= e(fmt_date($p['admitted_on'])) ?></td><th>Previous school</th><td><?= e($p['previous_school'] ?? '') ?></td><th>Status</th><td><?= e(ucfirst((string) $p['status'])) ?><?= $p['left_on'] ? ' (' . e(fmt_date($p['left_on'])) . ')' : '' ?></td></tr>
        <?php if ($sens && ($p['sen_category'] || $p['allergies'] || $health)): ?>
        <tr><th>Special needs</th><td><?= e(trim(($p['sen_category'] ?? '') . ' ' . ($p['sen_support'] ? '— ' . $p['sen_support'] : ''))) ?: '—' ?></td><th>Allergies</th><td><?= e($p['allergies'] ?: '—') ?></td><th>Blood / NHIS</th><td><?= e(trim(($health['blood_group'] ?? '') . ' ' . ($health['nhis_no'] ?? ''))) ?: '—' ?></td></tr>
        <?php endif; ?>
    </table>

    <table class="rc-info">
        <tr><th colspan="4" style="background:#173b6b;color:#fff">Parents / guardians</th></tr>
        <?php foreach ($guardians as $g): ?><tr><td><?= e($g['name']) ?><?= $g['is_primary'] ? ' (main contact)' : '' ?></td><td><?= e(ucfirst((string) $g['relationship'])) ?></td><td><?= e($g['phone'] ?? '') ?></td><td><?= e($g['occupation'] ?? '') ?></td></tr><?php endforeach; ?>
        <?php if (!$guardians): ?><tr><td colspan="4">None recorded.</td></tr><?php endif; ?>
    </table>

    <table class="rc-scores">
        <thead><tr><th class="l">Year &amp; class</th><th>Term</th><th>Average %</th><th>Attendance</th><th class="l">Conduct</th><th class="l">Class teacher's remark</th></tr></thead>
        <tbody>
        <?php foreach ($years as $label => $list): foreach ($list as $i => $r): ?>
        <tr><?php if ($i === 0): ?><td class="l" rowspan="<?= count($list) ?>"><?= e($label) ?></td><?php endif; ?>
            <td><?= (int) $r['term_no'] ?></td><td><?= e($fmt($r['average'])) ?></td>
            <td><?= $r['days'] ? (int) ($r['present'] ?? 0) . ' / ' . (int) $r['days'] : '—' ?></td>
            <td class="l"><?= e($r['conduct'] ?? '') ?></td><td class="l"><?= e($r['remark'] ?? '') ?><?= $r['promotion'] ? ' <em>(' . e($r['promotion']) . ')</em>' : '' ?></td></tr>
        <?php endforeach; endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6">No terms yet.</td></tr><?php endif; ?>
        </tbody>
    </table>

    <?php if ($pterms && $subjects): ?>
    <table class="rc-scores">
        <thead><tr><th class="l">Subject</th><?php foreach ($pterms as $t): ?><th title="<?= e($t['name']) ?>"><?= e($t['label']) ?></th><?php endforeach; ?></tr></thead>
        <tbody><?php foreach ($subjects as $name => $vals): ?><tr><td class="l"><?= e($name) ?></td><?php foreach ($vals as $v): ?><td><?= e($fmt($v)) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
    </table>
    <?php endif; ?>

    <?php if ($bece): ?>
    <table class="rc-info"><tr><th>BECE</th><td><?= e($bece['year_name'] ?? '') ?></td><th>Index no.</th><td><?= e($bece['index_no'] ?? '') ?></td><th>Aggregate</th><td><?= $bece['aggregate'] !== null ? (int) $bece['aggregate'] : '—' ?></td></tr></table>
    <?php endif; ?>

    <div class="rc-sign"><div>____________________<br>Class teacher</div><div>____________________<br>Head teacher</div><div>____________________<br>Date / school stamp</div></div>
    <p style="font-size:10px;color:#6b7280">Printed <?= e(date('d M Y H:i')) ?> from the school's records. Confidential: for the student, their parents and the school.</p>
</div>
