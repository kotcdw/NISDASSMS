<?php
$docTitle = 'TEACHER APPRAISAL';
$docSub = $term['year_name'] . ' · ' . $term['name'];
$sc = fn($cid, $who) => $a['scores'][(int) $cid][$who . '_score'] ?? null;
$selfMean = Teachers::mean(array_map(fn($c) => $sc($c['id'], 'self'), $criteria));
?>
<section class="card-page doc-page print-tables">
    <?php require BASE_PATH . '/app/views/fees/_doc_head.php'; ?>
    <table>
        <tr><th style="width:22%">Teacher</th><td><?= e($t['name']) ?></td><th style="width:18%">Role</th><td><?= e(Auth::roleLabel($t['role'])) ?></td></tr>
        <tr><th>Staff no.</th><td><?= e($record['staff_no'] ?? '—') ?></td><th>Qualification</th><td><?= e($record['qualification'] ?? '—') ?></td></tr>
        <tr><th>Status</th><td><?= e(Teachers::STATUS[$a['status']][0]) ?></td><th>Rated by</th><td><?= e($a['appraiser_name'] ?? '—') ?><?= $a['completed_at'] ? ', ' . e(date('j M Y', strtotime($a['completed_at']))) : '' ?></td></tr>
    </table>
    <h2>Ratings (1 = Poor, 2 = Fair, 3 = Good, 4 = Very good, 5 = Excellent)</h2>
    <table>
        <thead><tr><th>What is assessed</th><th class="rpt-num" style="width:16%">Self</th><th class="rpt-num" style="width:16%">Head</th></tr></thead>
        <tbody>
        <?php foreach ($criteria as $c): ?>
            <tr><td><?= e($c['name']) ?></td><td class="rpt-num"><?= e((string) ($sc($c['id'], 'self') ?? '—')) ?></td><td class="rpt-num"><?= e((string) ($sc($c['id'], 'head') ?? '—')) ?></td></tr>
        <?php endforeach; ?>
        <tr><th>Average</th><th class="rpt-num"><?= $selfMean === null ? '—' : e(Results::fmt($selfMean)) ?></th><th class="rpt-num"><?= $a['overall'] === null ? '—' : e(Results::fmt((float) $a['overall'])) . ' (' . e(Teachers::ratingWord((float) $a['overall'])) . ')' ?></th></tr>
        </tbody>
    </table>
    <?php foreach (['self_comment' => "Teacher's comments", 'strengths' => 'Strengths', 'improvements' => 'Areas to improve', 'targets' => 'Targets for next term', 'head_comment' => "Head's general comment", 'teacher_response' => "Teacher's response"] as $k => $lab): ?>
        <h2><?= e($lab) ?></h2>
        <p style="white-space:pre-line;min-height:1.5em;border-bottom:1px solid #c8ced8;padding-bottom:4px;font-size:.85rem"><?= e((string) ($a[$k] ?? '')) ?></p>
    <?php endforeach; ?>
    <div class="rc-sign"><div><span></span>Teacher</div><div><span></span>Head of school</div><div><span></span>Date</div></div>
</section>
