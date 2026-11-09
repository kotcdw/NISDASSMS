<?php
$hm = fn($t) => substr((string) $t, 0, 5);
$sheetTone = ['approved' => 'good', 'submitted' => 'info', 'draft' => 'warn', 'returned' => 'bad'];
$st = $appraisal['status'] ?? null;
?>
<div class="page-head">
    <div>
        <?php if (can('teachers.view')): ?><div class="breadcrumb"><a href="<?= e(url('teachers', ['term' => $term['id'] ?? null])) ?>">Teachers</a> /</div><?php endif; ?>
        <h1><?= e($t['name']) ?><?= $own ? ' <small class="muted">(my profile)</small>' : '' ?></h1>
        <p class="muted"><?= e(Auth::roleLabel($t['role'])) ?><?= $record && strcasecmp(trim((string) $record['position']), Auth::roleLabel($t['role'])) !== 0 ? ' · ' . e($record['position']) : '' ?></p>
    </div>
    <div class="actions">
        <?php if ($record && !empty($record['id']) && class_exists('Documents') && Documents::canSeeStaff($record)): ?><a class="btn" href="<?= e(url('documents/staff/' . $record['id'])) ?>">Files</a><?php endif; ?>
        <button class="btn btn-ghost" type="button" data-print>Print</button>
    </div>
</div>

<p class="figs"><span>Lessons a week <strong><?= (int) $lessons ?></strong></span><span>Attendance this term<?= $att && $att['days'] ? ' (' . (int) $att['present'] . ' of ' . (int) $att['days'] . ' days' . ($att['late'] ? ', ' . (int) $att['late'] . ' late' : '') . ')' : '' ?><?= $att && $att['avg_in'] ? ' · usually signs in at ' . e($att['avg_in']) : '' ?> <strong><?= $att && $att['pct'] !== null ? Results::fmt($att['pct']) . '%' : '—' ?></strong></span><span>Average of class results <strong><?= $avg === null ? '—' : Results::fmt($avg) ?></strong></span><span>Scheme of work covered<?= $cov && $cov['planned'] ? ' (' . (int) $cov['taught'] . ' of ' . (int) $cov['planned'] . ' topics)' : '' ?> <strong><?= $cov && $cov['planned'] ? Results::fmt($cov['pct']) . '%' : '—' ?></strong></span></p>
<div class="cols">
<div>
    <div class="card">
        <h2>Class results · <?= e($term['name'] ?? '') ?></h2>
        <?php if (!$results): ?>
            <p class="muted">No subjects assigned. Subjects are given to teachers in <strong>Exams → Subject allocation</strong>.</p>
        <?php else: ?>
            <?php $oneClass = count(array_unique(array_column($results, 'class_id'))) === 1; ?>
            <?= Charts::bars(array_map(fn($r) => [count($results) > 6 ? ($oneClass ? '' : str_replace(['Basic ', 'JHS '], ['B', 'JHS'], $r['class_name']) . ' ') . $r['subject_code'] : ($oneClass ? $r['subject_name'] : $r['class_name'] . ' ' . $r['subject_name']), (float) ($r['average'] ?? 0), $r['class_name'] . ' · ' . $r['subject_name'] . ': ' . ($r['average'] === null ? 'no scores yet' : Results::fmt($r['average']) . ' average')], $results), ['height' => 170, 'max' => 100, 'label' => 'Class average per subject']) ?>
            <div class="table-wrap"><table>
                <thead><tr><th>Class</th><th>Subject</th><th class="num">Students scored</th><th class="num">Average</th><th class="num">Pass rate</th><th class="num">Highest</th><th>Score sheet</th></tr></thead>
                <tbody>
                <?php foreach ($results as $r): ?>
                    <tr>
                        <td style="white-space:nowrap"><?= e($r['class_name']) ?></td><td><?= e($r['subject_name']) ?></td>
                        <td class="num"><?= (int) $r['scored'] ?>/<?= (int) $r['roll'] ?></td>
                        <td class="num"><?= Results::fmt($r['average']) ?></td>
                        <td class="num"><?= $r['pass_rate'] === null ? '—' : Results::fmt($r['pass_rate']) . '%' ?></td>
                        <td class="num"><?= Results::fmt($r['highest']) ?></td>
                        <td><?= $r['sheet'] ? badge(ucfirst($r['sheet']), $sheetTone[$r['sheet']] ?? 'neutral') : '<span class="muted">Not started</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Time table <small class="muted"><?= (int) $lessons ?> lessons a week</small></h2>
        <?php if (!$lessons): ?>
            <p class="muted">No lessons on the time table yet.</p>
        <?php else: ?>
        <div class="tt-week">
            <?php foreach (Timetable::DAYS as $d => $dn): ?>
            <div style="margin:0">
                <h3 style="font-size:.95rem;margin:.2rem 0"><?= e($dn) ?></h3>
                <?php foreach ($byDay[$d] ?? [] as $l): ?>
                    <div class="tt-item"><span class="tt-time"><?= e($hm($l['start_time'])) ?>–<?= e($hm($l['end_time'])) ?></span> <strong><?= e($l['subject_name'] ?? '') ?></strong><br><small><?= e($l['class_name']) ?><?= $l['room'] ? ' · ' . e($l['room']) : '' ?></small></div>
                <?php endforeach; ?>
                <?php if (empty($byDay[$d])): ?><p class="muted" style="font-size:.85rem">Free</p><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if (can('timetable.view')): ?><p style="margin:.6rem 0 0"><a href="<?= e(url('timetable/teacher/' . $t['id'])) ?>">Open the full time table</a></p><?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if ($invig): ?>
    <div class="card">
        <h2>Exam invigilation · <?= e($term['name']) ?></h2>
        <div class="table-wrap"><table>
            <thead><tr><th>Date</th><th>Time</th><th>Class</th><th>Paper</th><th>Room</th></tr></thead>
            <tbody><?php foreach ($invig as $i): ?>
                <tr><td><?= e(date('D j M', strtotime($i['exam_date']))) ?></td><td><?= e($hm($i['start_time'])) ?> · <?= (int) $i['duration_min'] ?> min</td><td><?= e($i['class_name']) ?></td><td><?= e($i['subject_name'] . ($i['paper'] ? ' ' . $i['paper'] : '')) ?></td><td><?= e($i['room_name'] ?? '') ?></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
    </div>
    <?php endif; ?>
</div>

<div>
    <div class="card">
        <h2>Details</h2>
        <dl class="details" style="grid-template-columns:130px 1fr">
            <dt>Email</dt><dd><?= e($t['email']) ?></dd>
            <dt>Phone</dt><dd><?= e($t['phone'] ?: ($record['phone'] ?? '') ?: '—') ?></dd>
            <?php if ($record): ?>
                <dt>Staff no.</dt><dd><?= e($record['staff_no']) ?></dd>
                <dt>Employment</dt><dd><?= e(ucwords(str_replace('_', ' ', (string) $record['employment']))) ?></dd>
                <dt>Started</dt><dd><?= $record['start_date'] ? e(date('j M Y', strtotime($record['start_date']))) : '—' ?></dd>
                <dt>Qualification</dt><dd><?= e($record['qualification'] ?: '—') ?></dd>
                <dt>NTC licence</dt><dd><?= e($record['ntc_licence_no'] ?: '—') ?><?php if ($record['ntc_expiry']): $days = (int) floor((strtotime($record['ntc_expiry']) - time()) / 86400); ?><br><small>expires <?= e(date('j M Y', strtotime($record['ntc_expiry']))) ?></small> <?= $days < 0 ? badge('Expired', 'bad') : ($days <= 60 ? badge('Renew soon', 'warn') : '') ?><?php endif; ?></dd>
            <?php endif; ?>
            <dt>Last sign-in</dt><dd><?= $t['last_login_at'] ? e(date('j M Y H:i', strtotime($t['last_login_at']))) : 'Never' ?></dd>
        </dl>
        <?php if (!$record): ?><p class="muted" style="font-size:.85rem;margin-bottom:0">No staff record is linked to this profile yet. Contact a school administrator to have one created.</p><?php endif; ?>
    </div>

    <div class="card">
        <h2>Classes &amp; subjects</h2>
        <?php if ($assign['classes']): ?><p style="margin:0 0 .5rem">Class teacher of <strong><?= e(implode(', ', array_column($assign['classes'], 'name'))) ?></strong></p><?php endif; ?>
        <?php if ($assign['subjects']): ?>
            <ul style="margin:0;padding-left:1.1rem;font-size:.92rem">
            <?php foreach ($assign['subjects'] as $s): ?>
                <li><?= e($s['class_name'] . ' — ' . $s['subject_name']) ?> <small class="muted"><?= (int) $s['lessons'] ?> lesson(s)/wk</small>
                <?php if (can('scheme.plan') || can('curriculum.manage')): ?> · <a href="<?= e(url('curriculum/scheme/' . $s['id'], ['term' => $term['id'] ?? null])) ?>">Scheme</a><?php endif; ?></li>
            <?php endforeach; ?>
            </ul>
        <?php elseif (!$assign['classes']): ?><p class="muted">No classes or subjects assigned.</p><?php endif; ?>
    </div>

    <div class="card">
        <h2>Appraisal · <?= e($term['name'] ?? '') ?></h2>
        <?php if ($appraisal): ?>
            <p><?= badge(Teachers::STATUS[$st][0], Teachers::STATUS[$st][1]) ?>
            <?php if ($st === 'completed' && $appraisal['overall'] !== null): ?> <strong><?= e(Results::fmt((float) $appraisal['overall'])) ?>/5</strong> · <?= e(Teachers::ratingWord((float) $appraisal['overall'])) ?><?php endif; ?></p>
            <a class="btn btn-sm btn-primary" href="<?= e(url('teachers/' . $t['id'] . '/appraisal', ['term' => $term['id']])) ?>"><?= $own && $st === 'open' ? 'Do my self-assessment' : 'Open appraisal' ?></a>
        <?php else: ?>
            <p class="muted">Not opened for this term.</p>
            <?php if (can('appraisal.manage') && !$own && $term): ?>
                <form method="post" action="<?= e(url('teachers/' . $t['id'] . '/appraisal/start')) ?>"><?= csrf_field() ?><input type="hidden" name="term" value="<?= (int) $term['id'] ?>"><button class="btn btn-sm" type="submit">Open appraisal</button></form>
            <?php endif; ?>
        <?php endif; ?>
        <?php $past = array_filter($history, fn($h) => (int) $h['term_id'] !== (int) ($term['id'] ?? 0)); if ($past): ?>
            <h3 style="font-size:.95rem;margin:1rem 0 .3rem">Earlier appraisals</h3>
            <?php foreach ($past as $h): ?>
                <p style="margin:0 0 .3rem;font-size:.9rem"><a href="<?= e(url('teachers/' . $t['id'] . '/appraisal', ['term' => $h['term_id']])) ?>"><?= e($h['year_name'] . ' · ' . $h['term_name']) ?></a> — <?= $h['status'] === 'completed' ? e(Results::fmt((float) $h['overall'])) . '/5' : e(Teachers::STATUS[$h['status']][0]) ?></p>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</div>
