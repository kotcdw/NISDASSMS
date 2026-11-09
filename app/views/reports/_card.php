<?php
/**
 * Terminal report in the school's own format (page 1) + Child's Personality Development Report (page 2).
 * Expects $R (Results::classResults) and $p (pupil row).
 */
$pid = (int) $p['id'];
$scheme = $R['scheme'];
$term = $R['term'];
$class = $R['class'];
$sm = $R['summary'][$pid];
$rm = $R['remarks'][$pid] ?? [];
$att = $R['attendance'];
$roll = count($R['pupils']);
// Leave out subjects nobody in the class has a score for yet
$cardSubjects = array_values(array_filter($R['subjects'], fn($s) => ($R['subjectStats'][$s['id']]['count'] ?? 0) > 0));
$section = ReportController::sectionTitle($class);
$termWord = ReportController::termWords((int) ($term['term_no'] ?? 0)) ?: $term['name'];
$date = $term['end_date'] ? date('d/m/Y', strtotime($term['end_date'])) : '';
$showLevels = !empty($scheme['show_levels']);
$showPos = (int) $scheme['show_position'] === 1;
[$promotedTo, $repeat] = ReportController::promotionText($class, (string) ($rm['promotion'] ?? ''));
$U = fn(?string $s) => mb_strtoupper((string) $s);
$school = $U(school_name());
$logo = '<img class="nr-logo" src="' . e(logo_url()) . '" alt="">';
$bandsGrid = function (array $bands, string $title): void {
    if (!$bands) {
        return;
    } ?>
    <table class="nr-grid">
        <?php if ($title !== ''): ?><caption><?= e($title) ?></caption><?php endif; ?>
        <tr><th>SCORE</th><?php foreach ($bands as $i => $b): $hi = $i === 0 ? 100 : (float) $bands[$i - 1]['min_score'] - 1; ?><td><?= e(Results::fmt((float) $b['min_score'])) ?> - <?= e(Results::fmt(max((float) $b['min_score'], $hi))) ?></td><?php endforeach; ?></tr>
        <tr><th>GRADE</th><?php foreach ($bands as $b): ?><td><?= e($b['grade']) ?></td><?php endforeach; ?></tr>
        <tr><th>REMARKS</th><?php foreach ($bands as $b): ?><td class="nr-small"><?= e(mb_strtoupper($b['remark'])) ?></td><?php endforeach; ?></tr>
    </table>
<?php };
$head = function (string $title) use ($logo, $school, $p, $class, $termWord, $date, $U): void { ?>
    <header class="nr-head">
        <?= $logo ?>
        <div class="nr-titles">
            <h1><?= e($school) ?></h1>
            <?= $title ?>
        </div>
    </header>
    <div class="nr-line">
        <span class="nr-grow">NAME: <b class="nr-fill"><?= e($U(trim($p['last_name'] . ' ' . $p['first_name'] . ' ' . ($p['middle_name'] ?? '')))) ?></b></span>
        <span>CLASS: <b class="nr-fill"><?= e($U($class['name'])) ?></b></span>
        <span>TERM: <b class="nr-fill"><?= e($U($termWord)) ?></b></span>
        <span>DATE: <b class="nr-fill"><?= e($date) ?></b></span>
    </div>
<?php };
?>
<section class="card-page nr-page<?= $showLevels ? ' nr-compact' : '' ?>">
    <?php $head('<h2>' . e($section) . '</h2><h2>TERMINAL REPORT</h2>'); ?>
    <div class="nr-line">
        <span>AVERAGE SCORE: <b class="nr-fill"><?= Results::fmt($sm['average']) ?></b></span>
        <span>NUMBER ON ROLL: <b class="nr-fill"><?= $roll ?></b></span>
        <?php if (setting('report_overall_position', '0') === '1' && $sm['position']): ?><span>POSITION: <b class="nr-fill"><?= e($U(Results::ordinal($sm['position']))) ?></b></span><?php endif; ?>
        <span class="nr-right">NEXT TERM BEGINS: <b class="nr-fill"><?= $R['nextTerm'] ? e(date('d/m/Y', strtotime($R['nextTerm']['start_date']))) : 'TBA' ?></b></span>
    </div>

    <table class="nr-scores">
        <thead><tr>
            <th class="l">SUBJECT</th>
            <th>CLASS<br>(<?= (int) $scheme['class_weight'] ?>%)</th>
            <th>EXAMS<br>(<?= (int) $scheme['exam_weight'] ?>%)</th>
            <th>TOTAL<br>(100%)</th>
            <th>GRADE</th>
            <?php if ($showLevels): ?><th>LEVEL</th><?php endif; ?>
            <?php if ($showPos): ?><th>POSITION</th><?php endif; ?>
            <th>REMARKS</th>
        </tr></thead>
        <tbody>
        <?php foreach ($cardSubjects as $s): $sc = $R['scores'][$pid][$s['id']] ?? []; ?>
            <tr>
                <td class="l"><?= e($U($s['name'])) ?></td>
                <td><?= Results::fmt($sc['class'] ?? null) ?></td>
                <td><?= !empty($sc['absent']) ? 'ABS' : Results::fmt($sc['exam'] ?? null) ?></td>
                <td><b><?= !empty($sc['absent']) ? 'ABS' : Results::fmt($sc['total'] ?? null) ?></b></td>
                <td><?= e($sc['grade'] ?? '') ?></td>
                <?php if ($showLevels): ?><td><?= e($sc['level'] ?? '') ?></td><?php endif; ?>
                <?php if ($showPos): ?><td><?= e($U(Results::ordinal($sc['position'] ?? null))) ?></td><?php endif; ?>
                <td><?= e($U($sc['grade_remark'] ?? $sc['remark'] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
            <tr class="nr-blank"><td class="l">&nbsp;</td><td></td><td></td><td></td><td></td><?= $showLevels ? '<td></td>' : '' ?><?= $showPos ? '<td></td>' : '' ?><td></td></tr>
        </tbody>
        <tfoot>
            <tr><td class="l"><b>TOTAL</b></td><td></td><td></td><td><b><?= Results::fmt($sm['total']) ?></b></td>
                <td colspan="<?= 2 + ($showLevels ? 1 : 0) + ($showPos ? 1 : 0) ?>" class="l"><?= $scheme['use_aggregate'] ? 'AGGREGATE (BEST 6): <b>' . e($sm['aggregate'] ?? '—') . '</b>' : '' ?></td></tr>
        </tfoot>
    </table>

    <h3 class="nr-caption">GRADING SCHEME</h3>
    <?php $bandsGrid($scheme['bands'], ''); ?>
    <?php if ($showLevels): $bandsGrid($scheme['level_bands'] ?? [], 'NaCCA PROFICIENCY LEVELS'); endif; ?>

    <div class="nr-box">
        <div class="nr-line nr-tight">
            <span>ATTENDANCE: <b class="nr-fill"><?= $att['_days'] ? (int) ($att[$pid] ?? 0) : '' ?></b></span>
            <span>OUT OF: <b class="nr-fill"><?= $att['_days'] ? (int) $att['_days'] : '' ?></b></span>
            <span>PROMOTED TO: <b class="nr-fill"><?= e($U($promotedTo)) ?></b></span>
            <span>REPEAT: <b class="nr-fill"><?= e($U($repeat)) ?></b></span>
        </div>
        <div class="nr-comment"><span class="nr-label">CLASS TEACHER'S COMMENTS:</span> <i><?= e($rm['teacher_remark'] ?? '') ?></i></div>
        <div class="nr-sign"><span></span>CLASS TEACHER'S SIGNATURE</div>
        <div class="nr-comment"><span class="nr-label">HEAD OF SCHOOL'S COMMENTS:</span> <i><?= e($rm['head_remark'] ?? '') ?></i></div>
        <div class="nr-sign"><span></span>SIGNATURE OF HEAD OF SCHOOL</div>
    </div>
    <?php if (setting('fee_show_on_report', '0') === '1' && db()->value('SELECT 1 FROM invoices WHERE pupil_id = ? LIMIT 1', [$p['id']])): $owed = Fees::arrearsAsOf((int) $p['id'], $term); ?>
        <p class="nr-fees">School fees: <?= $owed > 0.004 ? 'arrears <b>' . e(Fees::money($owed)) . '</b> — kindly settle at the accounts office' . (PaymentGateway::active() !== 'none' ? ' or online by Mobile Money' : '') . '.' : 'no arrears. Thank you.' ?></p>
    <?php endif; ?>
</section>

<?php if (setting('report_personality', '1') === '1'): $traits = ReportController::traits(); $pers = json_decode((string) ($rm['personality'] ?? ''), true) ?: []; ?>
<section class="card-page nr-page">
    <?php $head('<h2>' . e($section) . ' TERMINAL REPORT</h2>'); ?>
    <h2 class="nr-big">CHILD'S PERSONALITY DEVELOPMENT REPORT</h2>
    <table class="nr-traits">
        <?php foreach ($traits as $t): ?>
        <tr><th><?= e($t) ?></th><td><i><?= e($pers[$t] ?? '') ?></i></td></tr>
        <?php endforeach; ?>
    </table>
    <div class="nr-sign2">
        <div><span></span>CLASS TEACHER</div>
        <div><span></span>HEAD OF SCHOOL</div>
    </div>
</section>
<?php endif; ?>
