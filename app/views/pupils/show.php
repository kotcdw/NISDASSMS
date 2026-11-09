<?php
$pid = (int) $pupil['id'];
$canManage = can('pupils.manage');
$canHealth = can('health.view') && can('pupils.sensitive');
$canFiles = can('pupils.sensitive') && class_exists('Documents') && Documents::canSeePupil($pid);
?>
<div class="profile-head">
    <?php if ($pupil['photo_path']): ?>
        <img class="avatar" src="<?= e(url('pupils/' . $pid . '/photo')) ?>" alt="Photo of <?= e($pupil['first_name']) ?>" style="object-fit:cover">
    <?php else: ?>
        <div class="avatar" aria-hidden="true"><?= e(mb_substr($pupil['first_name'], 0, 1) . mb_substr($pupil['last_name'], 0, 1)) ?></div>
    <?php endif; ?>
    <div class="ph-main">
        <div class="breadcrumb"><a href="<?= e(url('pupils')) ?>">Students</a> /</div>
        <h1><?= e(full_name($pupil)) ?> <?= $pupil['status'] === 'active' ? badge('Active', 'good') : badge(ucfirst($pupil['status']), 'warn') ?></h1>
        <dl class="ph-facts">
            <div><dt>Admission no.</dt><dd><?= e($pupil['admission_no']) ?></dd></div>
            <div><dt>Class</dt><dd><?= e($pupil['class_name'] ?? 'Not enrolled this year') ?></dd></div>
            <?php if ($pupil['class_teacher']): ?><div><dt>Class teacher</dt><dd><?= e($pupil['class_teacher']) ?></dd></div><?php endif; ?>
            <div><dt>Age</dt><dd><?= e(age_from($pupil['date_of_birth'])) ?></dd></div>
        </dl>
    </div>
    <div class="actions ph-actions">
        <?php if ($canManage): ?><a class="btn btn-primary" href="<?= e(url('pupils/' . $pid . '/edit')) ?>">Edit</a><?php endif; ?>
        <details class="menu-btn">
            <summary class="btn">More ▾</summary>
            <div>
                <a href="<?= e(url('pupils/id-cards', ['pupil' => $pid])) ?>" target="_blank">ID card</a>
                <a href="<?= e(url('pupils/' . $pid . '/record-card')) ?>" target="_blank">Cumulative record</a>
                <?php if ($canFiles): ?><a href="<?= e(url('documents/pupils/' . $pid)) ?>">Files</a><?php endif; ?>
                <?php if ($canHealth): ?><a href="<?= e(url('health/pupils/' . $pid)) ?>">Health record</a><?php endif; ?>
                <?php if ($canManage): ?>
                    <a href="#status">Change status</a>
                    <span class="menu-label">Letters</span>
                    <?php foreach (PupilDocsController::LETTERS as $k => $l): ?><a href="<?= e(url('pupils/' . $pid . '/letter', ['kind' => $k])) ?>" target="_blank"><?= e($l) ?></a><?php endforeach; ?>
                <?php endif; ?>
                <button class="menu-item" type="button" data-print>Print this page</button>
            </div>
        </details>
    </div>
</div>

<?php if (!empty($pupil['sen_category']) && can('pupils.sensitive')): ?>
    <div class="alert alert-info"><strong>Special educational needs:</strong> <?= e(Admission::SEN[$pupil['sen_category']] ?? $pupil['sen_category']) ?><?= $pupil['sen_support'] ? ' — ' . e($pupil['sen_support']) : '' ?></div>
<?php endif; ?>
<?php if ($pupil['allergies'] || ($pupil['medical_notes'] && can('pupils.sensitive'))): // allergies are a safety matter: every teacher sees them ?>
    <div class="alert alert-warning"><strong>Health:</strong> <?= e(trim(($pupil['allergies'] ? 'Allergies: ' . $pupil['allergies'] . '. ' : '') . (can('pupils.sensitive') ? ($pupil['medical_notes'] ?? '') : ''))) ?></div>
<?php endif; ?>

<div class="cols profile-cols">
    <div>
        <div class="card">
            <h2>Personal details</h2>
            <dl class="details">
                <dt>Gender</dt><dd><?= e(ucfirst($pupil['gender'])) ?></dd>
                <dt>Date of birth</dt><dd><?= e(fmt_date($pupil['date_of_birth'])) ?> (<?= e(age_from($pupil['date_of_birth'])) ?>)</dd>
                <dt>Nationality</dt><dd><?= e($pupil['nationality']) ?></dd>
                <dt>Hometown</dt><dd><?= e($pupil['hometown']) ?></dd>
                <dt>Denomination</dt><dd><?= e($pupil['denomination']) ?></dd>
                <dt>House</dt><dd><?= e($pupil['house']) ?></dd>
                <dt>Address</dt><dd><?= e($pupil['address']) ?></dd>
                <dt>Previous school</dt><dd><?= e($pupil['previous_school']) ?></dd>
                <dt>Admitted</dt><dd><?= e(fmt_date($pupil['admitted_on'])) ?><?= $application ? ' · application <a href="' . e(url('admission/' . $application['id'])) . '">' . e($application['ref_no']) . '</a>' : '' ?></dd>
                <?php if ($pupil['left_on']): ?><dt>Left</dt><dd><?= e(fmt_date($pupil['left_on'])) ?></dd><?php endif; ?>
            </dl>
        </div>
        <div class="card">
            <h2>Class history</h2>
            <div class="table-wrap"><table>
                <tr><th>Year</th><th>Class</th><th>Status</th><th>From</th></tr>
                <?php foreach ($enrolments as $en): ?>
                    <tr><td><?= e($en['year_name']) ?></td><td><?= e($en['class_name']) ?></td><td><?= e(ucfirst($en['status'])) ?></td><td><?= e(fmt_date($en['enrolled_on'])) ?></td></tr>
                <?php endforeach; ?>
            </table></div>
        </div>
        <?php if ($progress !== null) { require __DIR__ . '/_progress.php'; } ?>
    </div>
    <div>
        <div class="card">
            <h2>Parents / guardians</h2>
            <?php foreach ($guardians as $g): ?>
                <p class="guardian"><strong><?= e($g['name']) ?></strong> <?= $g['is_primary'] ? badge('Primary', 'info') : '' ?><br>
                <small><?= e($g['relationship']) ?><?= $g['occupation'] ? ' · ' . e($g['occupation']) : '' ?></small><br>
                <a href="tel:<?= e($g['phone']) ?>"><?= e($g['phone']) ?></a><?= $g['phone_alt'] ? ' · ' . e($g['phone_alt']) : '' ?><?= $g['email'] ? '<br>' . e($g['email']) : '' ?></p>
                <?php if (can('pupils.manage')): ?>
                <details>
                    <summary class="btn btn-sm">Edit details</summary>
                    <form method="post" action="<?= e(url('pupils/' . $pupil['id'] . '/guardians/' . $g['id'])) ?>" class="form" style="margin-top:.75rem">
                        <?= csrf_field() ?>
                        <label>Name <input name="name" required maxlength="150" value="<?= e($g['name']) ?>"></label>
                        <label>Relationship <select name="relationship"><?php foreach (Admission::RELATIONSHIPS as $r): ?><option value="<?= e($r) ?>"<?= selected($r, $g['relationship']) ?>><?= e($r) ?></option><?php endforeach; ?></select></label>
                        <label>Phone <input name="phone" required value="<?= e($g['phone']) ?>"></label>
                        <label>Alternative phone <input name="phone_alt" value="<?= e($g['phone_alt']) ?>"></label>
                        <label>Email <input type="email" name="email" value="<?= e($g['email']) ?>"></label>
                        <label>Occupation <input name="occupation" maxlength="100" value="<?= e($g['occupation']) ?>"></label>
                        <label>Address <input name="address" maxlength="255" value="<?= e($g['address']) ?>"></label>
                        <label><input type="checkbox" name="is_primary" value="1"<?= checked($g['is_primary']) ?>> Primary contact</label>
                        <button class="btn btn-primary btn-sm" type="submit">Save guardian</button>
                    </form>
                    <form method="post" action="<?= e(url('pupils/' . $pupil['id'] . '/guardians/' . $g['id'] . '/remove')) ?>" data-confirm="Remove this guardian from this student? Their details and links to other students will remain.">
                        <?= csrf_field() ?><button class="btn btn-danger btn-sm" type="submit">Remove from student</button>
                    </form>
                </details>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if (can('pupils.manage')): ?>
            <details>
                <summary class="btn btn-sm">+ Add guardian</summary>
                <form method="post" action="<?= e(url('pupils/' . $pupil['id'] . '/guardians')) ?>" class="form" style="margin-top:.75rem">
                    <?= csrf_field() ?>
                    <label>Name <input name="name" required></label>
                    <label>Relationship <select name="relationship"><?php foreach (Admission::RELATIONSHIPS as $r): ?><option><?= e($r) ?></option><?php endforeach; ?></select></label>
                    <label>Phone <input name="phone" required placeholder="0244123456"></label>
                    <label>Email <input type="email" name="email"></label>
                    <button class="btn btn-primary btn-sm" type="submit">Add</button>
                </form>
            </details>
            <?php endif; ?>
        </div>
        <?php if ($results): ?>
        <div class="card">
            <h2>Results</h2>
            <?php foreach ($results as $res): $sm = $res['summary']; ?>
                <p style="margin:0 0 .5rem;font-size:.9rem"><strong><?= e($res['term']['year_name'] . ' · ' . $res['term']['name']) ?></strong><br>
                Average <?= Results::fmt($sm['average'] ?? null) ?>%<?= $res['show_position'] && !empty($sm['position']) ? ' · ' . e(Results::ordinal($sm['position'])) . ' of ' . (int) $res['roll'] : '' ?>
                · <a href="<?= e(url('reports/pupil/' . $pupil['id'], ['term' => $res['term']['id']])) ?>" target="_blank" rel="noopener">Report card</a></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if ($attendance && (int) $attendance['marked'] > 0): ?>
        <div class="card">
            <h2>Attendance · <?= e($term['name']) ?></h2>
            <?= Attendance::meter(Attendance::percent((int) $attendance['present'], (int) $attendance['marked'])) ?>
            <p style="margin:.5rem 0"><small>Present <?= (int) $attendance['present'] ?> of <?= (int) $attendance['marked'] ?> days · absent <?= (int) $attendance['absent'] ?> · late <?= (int) $attendance['late'] ?></small></p>
            <?php foreach ($recentAbs as $ra): ?><small><?= e(fmt_date($ra['date'], 'D j M')) ?> — <?= e(Attendance::STATUSES[$ra['status']][0]) ?><?= $ra['note'] ? ': ' . e($ra['note']) : '' ?></small><br><?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php
        $yid = current_year()['id'] ?? 0;
        $bus = db()->one('SELECT r.name, a.stop, a.trips FROM transport_assignments a JOIN transport_routes r ON r.id = a.route_id WHERE a.pupil_id = ? AND a.academic_year_id = ? AND a.ended_on IS NULL', [$pupil['id'], $yid]);
        $books = db()->all("SELECT b.title, l.due_on FROM library_loans l JOIN library_books b ON b.id = l.book_id WHERE l.pupil_id = ? AND l.status = 'out'", [$pupil['id']]);
        $visits = (int) db()->value('SELECT COUNT(*) FROM health_visits WHERE pupil_id = ? AND seen_at > (NOW() - INTERVAL 90 DAY)', [$pupil['id']]);
        $bece = db()->one('SELECT index_no, aggregate FROM bece_candidates WHERE pupil_id = ? ORDER BY academic_year_id DESC LIMIT 1', [$pupil['id']]);
        if ($bus || $books || $visits || $bece): ?>
        <div class="card">
            <h2>School services</h2>
            <dl class="details">
                <?php if ($bus): ?><dt>School bus</dt><dd><?= e($bus['name']) ?><?= $bus['stop'] ? ' · ' . e($bus['stop']) : '' ?><?= $bus['trips'] !== 'both' ? ' (' . e($bus['trips']) . ' only)' : '' ?></dd><?php endif; ?>
                <?php if ($books): ?><dt>Library books</dt><dd><?php foreach ($books as $bk): ?><?= e($bk['title']) ?> — due <?= e(fmt_date($bk['due_on'], 'd M')) ?><?= $bk['due_on'] < date('Y-m-d') ? ' ' . badge('Overdue', 'bad') : '' ?><br><?php endforeach; ?></dd><?php endif; ?>
                <?php if ($visits && can('health.view') && can('pupils.sensitive')): ?><dt>Sick bay</dt><dd><a href="<?= e(url('health/pupils/' . $pupil['id'])) ?>"><?= $visits ?> visit(s) in the last 3 months</a></dd><?php endif; ?>
                <?php if ($bece): ?><dt>BECE</dt><dd><?= $bece['index_no'] ? 'Index no. ' . e($bece['index_no']) : 'Registered' ?><?= $bece['aggregate'] !== null ? ' · aggregate ' . (int) $bece['aggregate'] : '' ?></dd><?php endif; ?>
            </dl>
        </div>
        <?php endif; ?>
        <?php if (can('welfare.record')): try {
            $tf = $term['start_date'] ?? date('Y-m-d', strtotime('-90 days'));
            $beh = db()->one("SELECT SUM(kind = 'merit') AS m, SUM(kind = 'concern') AS c FROM behaviour_records WHERE pupil_id = ? AND occurred_on >= ?", [$pupil['id'], $tf]);
            $behRecent = db()->all('SELECT * FROM behaviour_records WHERE pupil_id = ? ORDER BY occurred_on DESC, id DESC LIMIT 3', [$pupil['id']]);
            [$vw, $vp] = Welfare::visible();
            $wcases = db()->all("SELECT w.* FROM welfare_cases w WHERE w.pupil_id = ? AND w.status <> 'closed' AND $vw", array_merge([$pupil['id']], $vp));
        } catch (PDOException $e) { $beh = null; } if ($beh !== null): ?>
        <div class="card">
            <h2>Behaviour &amp; welfare</h2>
            <p style="margin:.2rem 0 .6rem"><?= badge((int) $beh['m'] . ((int) $beh['m'] === 1 ? ' merit' : ' merits'), 'good') ?> <?= badge((int) $beh['c'] . ((int) $beh['c'] === 1 ? ' concern' : ' concerns'), (int) $beh['c'] ? 'warn' : 'neutral') ?> <small class="muted">this term</small></p>
            <?php foreach ($behRecent as $b): ?><small><?= e(fmt_date($b['occurred_on'], 'D j M')) ?> — <?= $b['kind'] === 'merit' ? 'Merit' : 'Concern' ?>: <?= e($b['category']) ?><?= $b['action_taken'] ? ' (' . e(Welfare::ACTIONS[$b['action_taken']] ?? '') . ')' : '' ?></small><br><?php endforeach; ?>
            <?php foreach ($wcases as $wc): [$wkl, $wkt] = Welfare::KINDS[$wc['kind']]; ?><p style="margin:.4rem 0 0"><a href="<?= e(url('welfare/cases/' . $wc['id'])) ?>"><?= e($wc['ref_no']) ?></a> <?= badge($wkl, $wkt) ?> <?= e($wc['kind'] === 'safeguarding' && !can('welfare.safeguarding') ? '' : $wc['title']) ?></p><?php endforeach; ?>
            <div class="actions" style="justify-content:flex-start;margin-top:.7rem;flex-wrap:wrap">
                <a class="btn btn-sm" href="<?= e(url('welfare/behaviour/new', ['pupil' => $pupil['id']])) ?>">+ Merit or concern</a>
                <a class="btn btn-sm btn-ghost" href="<?= e(url('welfare/cases/new', ['kind' => 'counselling', 'pupil' => $pupil['id']])) ?>">Counselling</a>
                <a class="btn btn-sm btn-ghost" href="<?= e(url('welfare/cases/new', ['kind' => 'incident', 'pupil' => $pupil['id']])) ?>">Report an accident</a>
            </div>
        </div>
        <?php endif; endif; ?>
        <?php if ($fees !== null): ?>
        <div class="card">
            <h2>Fees</h2>
            <p style="margin:0 0 .3rem"><?= $fees['balance'] < 0 ? 'Credit' : 'Balance owed' ?>: <strong style="color:<?= $fees['balance'] > 0 ? 'var(--bad)' : 'var(--good)' ?>"><?= e(Fees::money(abs($fees['balance']))) ?></strong></p>
            <?php if ($fees['last']): ?><p class="muted" style="margin:0 0 .5rem;font-size:.85rem">Last paid <?= e(Fees::money($fees['last']['amount'])) ?> on <?= e(fmt_date($fees['last']['paid_on'])) ?></p><?php endif; ?>
            <a class="btn btn-sm" href="<?= e(url('fees/pupil/' . $pupil['id'])) ?>">Fee statement</a>
        </div>
        <?php endif; ?>
        <?php if ($siblings): ?>
        <div class="card">
            <h2>Siblings in school</h2>
            <?php foreach ($siblings as $s): ?>
                <p style="margin:0 0 .4rem"><a href="<?= e(url('pupils/' . $s['id'])) ?>"><?= e($s['first_name'] . ' ' . $s['last_name']) ?></a> <small><?= e($s['admission_no']) ?></small></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (can('pupils.manage')): ?>
        <div class="card" id="status">
            <h2>Status</h2>
            <form method="post" action="<?= e(url('pupils/' . $pupil['id'] . '/status')) ?>" class="form" data-confirm="Change this student's status?">
                <?= csrf_field() ?>
                <label>Status
                    <select name="status">
                        <?php foreach (['active' => 'Active', 'withdrawn' => 'Withdrawn', 'transferred' => 'Transferred out', 'graduated' => 'Completed JHS 3'] as $k => $l): ?><option value="<?= $k ?>"<?= selected($k, $pupil['status']) ?>><?= e($l) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label>Effective date <input type="date" name="left_on" value="<?= e($pupil['left_on'] ?: date('Y-m-d')) ?>"></label>
                <button class="btn btn-primary btn-sm" type="submit">Update status</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>
