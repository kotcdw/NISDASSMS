<div class="page-head">
    <div><h1>My learning</h1><p class="muted"><?= e(full_name($student)) ?> · <?= e($student['admission_no']) ?> · <?= e($student['class_name'] ?? 'No current class') ?></p></div>
    <div class="actions">
        <a class="btn" href="<?= e(url('calendar')) ?>">School calendar</a>
        <a class="btn btn-primary" href="<?= e(url('conversations/create')) ?>">Message a teacher</a>
    </div>
</div>
<div class="stats-grid">
    <div class="stat"><span>Class</span><strong><?= e($student['class_name'] ?? '—') ?></strong></div>
    <div class="stat"><span>Attendance this term</span><strong><?= $attendance && (int) $attendance['marked'] ? e(number_format(100 * (int) $attendance['present'] / (int) $attendance['marked'], 1)) . '%' : '—' ?></strong><small class="muted"><?= (int) ($attendance['present'] ?? 0) ?> of <?= (int) ($attendance['marked'] ?? 0) ?> marked</small></div>
    <div class="stat"><span>Released overall average</span><strong><?= $ownSummary && $ownSummary['average'] !== null ? e(Results::fmt((float) $ownSummary['average'])) . '%' : '—' ?></strong><small class="muted"><?= e($term['name'] ?? 'No current term') ?></small></div>
    <div class="stat"><span>Class average</span><strong><?= $classSummary ? e(Results::fmt(array_sum($classSummary) / count($classSummary))) . '%' : '—' ?></strong><small class="muted"><?= $classSummary ? count($classSummary) . ' students with released results' : 'No released class results' ?></small></div>
</div>
<div class="cols" style="margin-top:1.25rem">
    <div>
        <?php if ($student['class_id'] && $periods && module_on('timetable')): ?>
        <div class="card" style="padding:0">
            <h2 style="padding:1rem 1rem 0">My class routine</h2>
            <div class="table-wrap"><table class="tt-grid">
                <tr><th></th><?php foreach (Timetable::DAYS as $d => $day): ?><th><?= e(substr($day, 0, 3)) ?></th><?php endforeach; ?></tr>
                <?php foreach ($periods as $period): ?><tr>
                    <td><small><?= e(substr($period['start_time'], 0, 5)) ?></small></td>
                    <?php foreach (Timetable::DAYS as $d => $day): $slot = $timetable[$d][$period['id']] ?? null; ?>
                    <td><small><?= $period['kind'] !== 'lesson' ? e($period['label']) : e($slot['subject_name'] ?? '') ?></small></td>
                    <?php endforeach; ?>
                </tr><?php endforeach; ?>
            </table></div>
        </div>
        <?php endif; ?>
        <?php require BASE_PATH . '/app/views/pupils/_progress.php'; ?>
        <?php if ($remarks): ?>
        <div class="card">
            <h2>Report comments</h2>
            <?php foreach ($remarks as $reportRemark): ?>
                <h3><?= e($reportRemark['term_name'] . ' · ' . $reportRemark['year_name']) ?></h3>
                <?php foreach (['teacher_remark' => 'Class teacher', 'head_remark' => 'Head of school', 'personality' => 'Personal development', 'conduct' => 'Conduct'] as $key => $label): ?>
                    <?php if ($reportRemark[$key]): ?><p><strong><?= e($label) ?>:</strong> <?= e($reportRemark[$key]) ?></p><?php endif; ?>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (module_on('attendance')): ?>
        <div class="card">
            <h2>Attendance and comments</h2>
            <?php foreach ($absences as $absence): ?><p style="margin:0 0 .35rem"><?= e(fmt_date($absence['date'], 'D j M')) ?> · <?= e(Attendance::STATUSES[$absence['status']][0] ?? $absence['status']) ?><?= $absence['note'] ? ' · ' . e($absence['note']) : '' ?></p><?php endforeach; ?>
            <?php if (!$absences): ?><p class="muted">No absences or lateness recorded this term.</p><?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <div>
        <div class="card">
            <h2>Teachers and subjects</h2>
            <?php foreach ($teachers as $teacher): ?><p style="margin:0 0 .55rem"><a href="<?= e(url('teacher-profile/' . $teacher['id'])) ?>"><strong><?= e($teacher['name']) ?></strong></a><br><small class="muted"><?= !empty($teacher['is_class_teacher']) ? 'Class teacher' . ($teacher['subjects'] ? ' · ' . e($teacher['subjects']) : '') : e($teacher['subjects'] ?? '') ?></small></p><?php endforeach; ?>
            <?php if (!$teachers): ?><p class="muted">No teachers are linked to your current class.</p><?php endif; ?>
        </div>
        <?php if (module_on('transport')): ?>
        <div class="card">
            <h2>My school transport</h2>
            <?php if ($transport): ?>
                <p><strong><?= e($transport['route_name']) ?></strong><?= $transport['stop'] ? ' · ' . e($transport['stop']) : '' ?></p>
                <?php if ($transport['trips']): ?><p>Trips: <?= e(ucfirst($transport['trips'])) ?></p><?php endif; ?>
                <?php if ($transport['stops']): ?><p>Route stops: <?= e($transport['stops']) ?></p><?php endif; ?>
                <?php if ($transport['vehicle']): ?><p>Vehicle: <?= e($transport['vehicle']) ?></p><?php endif; ?>
                <?php if ($transport['driver_name']): ?><p>Driver: <?= e($transport['driver_name']) ?><?= $transport['driver_phone'] ? ' · ' . e($transport['driver_phone']) : '' ?></p><?php endif; ?>
            <?php else: ?><p class="muted">No active transport assignment is recorded.</p><?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="card">
            <h2>Upcoming school events</h2>
            <?php foreach ($events as $event): ?><p style="margin:0 0 .5rem"><strong><?= e(fmt_date($event['start_date'], 'D j M')) ?></strong> · <a href="<?= e(url('calendar/' . $event['id'])) ?>"><?= e($event['title']) ?></a></p><?php endforeach; ?>
            <?php if (!$events): ?><p class="muted">Nothing scheduled.</p><?php endif; ?>
            <a href="<?= e(url('calendar')) ?>">Open calendar →</a>
        </div>
        <div class="card">
            <h2>My profile</h2>
            <form method="post" action="<?= e(url('student/profile')) ?>" class="form">
                <?= csrf_field() ?>
                <label>Email<input type="email" name="email" required maxlength="190" value="<?= e($user['email'] ?? '') ?>"></label>
                <label>Phone<input type="tel" name="phone" maxlength="20" value="<?= e($user['phone'] ?? '') ?>"></label>
                <button class="btn" type="submit">Update contact details</button>
            </form>
        </div>
    </div>
</div>
