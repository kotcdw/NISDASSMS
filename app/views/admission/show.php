<?php
$flowIdx = array_search($app['stage'], Admission::FLOW, true);
$next = Admission::TRANSITIONS[$app['stage']] ?? [];
?>
<div class="page-head">
    <div>
        <div class="breadcrumb"><a href="<?= e(url('admission')) ?>">Admission</a> / <?= e($app['year_name']) ?></div>
        <h1><?= e(full_name($app)) ?> <?= Admission::badge($app['stage']) ?></h1>
        <p class="muted"><?= e($app['ref_no']) ?> · applying for <strong><?= e($app['level_name']) ?></strong> · <?= $app['source'] === 'online' ? 'online application' : 'walk-in, recorded by ' . e($app['created_by_name'] ?? '—') ?> · <?= e(fmt_date($app['created_at'], 'd M Y H:i')) ?></p>
    </div>
    <div class="actions">
        <?php if ($app['pupil_id']): ?><a class="btn btn-good" href="<?= e(url('pupils/' . $app['pupil_id'])) ?>">Open student record</a><?php endif; ?>
        <?php if (can('admission.manage') && $app['stage'] !== 'enrolled'): ?><a class="btn" href="<?= e(url('admission/' . $app['id'] . '/edit')) ?>">Edit</a><?php endif; ?>
    </div>
</div>

<ol class="steps">
    <?php foreach (Admission::FLOW as $i => $s): ?>
        <li class="<?= $flowIdx !== false && $i < $flowIdx ? 'done' : ($s === $app['stage'] ? 'now' : '') ?>"><?= e(Admission::label($s)) ?></li>
    <?php endforeach; ?>
</ol>

<?php if ($siblings): ?>
<div class="alert alert-info">Sibling(s) already in school with the same guardian phone:
    <?php foreach ($siblings as $i => $s): ?><?= $i ? ', ' : '' ?><a href="<?= e(url('pupils/' . $s['id'])) ?>"><?= e($s['first_name'] . ' ' . $s['last_name']) ?></a> (<?= e($s['admission_no']) ?>)<?php endforeach; ?>.
    The existing guardian record will be reused on enrolment.
</div>
<?php endif; ?>

<div class="cols">
    <div>
        <div class="card">
            <h2>Child</h2>
            <dl class="details">
                <dt>Full name</dt><dd><?= e(full_name($app)) ?></dd>
                <dt>Gender</dt><dd><?= e(ucfirst($app['gender'])) ?></dd>
                <dt>Date of birth</dt><dd><?= e(fmt_date($app['date_of_birth'])) ?> (<?= e(age_from($app['date_of_birth'])) ?>)</dd>
                <dt>Nationality</dt><dd><?= e($app['nationality']) ?></dd>
                <dt>Hometown</dt><dd><?= e($app['hometown']) ?></dd>
                <dt>Denomination</dt><dd><?= e($app['denomination']) ?></dd>
                <dt>Previous school</dt><dd><?= e($app['previous_school']) ?></dd>
                <dt>Allergies</dt><dd><?= e($app['allergies']) ?></dd>
                <dt>Medical notes</dt><dd><?= e($app['medical_notes']) ?></dd>
                <?php if (!empty($app['sen_category'])): ?><dt>Special needs</dt><dd><?= e(Admission::SEN[$app['sen_category']] ?? $app['sen_category']) ?><?= $app['sen_support'] ? ' — ' . e($app['sen_support']) : '' ?></dd><?php endif; ?>
                <?php if ($app['entrance_score'] !== null): ?><dt>Entrance score</dt><dd><?= e($app['entrance_score']) ?>%</dd><?php endif; ?>
                <?php if ($app['interview_date']): ?><dt>Assessment / interview</dt><dd><?= e(fmt_date($app['interview_date'], 'l j F Y')) ?></dd><?php endif; ?>
            </dl>
        </div>
        <div class="card">
            <h2>Parent / guardian</h2>
            <dl class="details">
                <dt>Name</dt><dd><?= e($app['guardian_name']) ?> (<?= e($app['guardian_relationship']) ?>)</dd>
                <dt>Phone</dt><dd><a href="tel:<?= e($app['guardian_phone']) ?>"><?= e($app['guardian_phone']) ?></a><?= $app['guardian_phone_alt'] ? ' · ' . e($app['guardian_phone_alt']) : '' ?></dd>
                <dt>Email</dt><dd><?= e($app['guardian_email']) ?></dd>
                <dt>Occupation</dt><dd><?= e($app['guardian_occupation']) ?></dd>
                <dt>Address</dt><dd><?= e($app['guardian_address']) ?></dd>
            </dl>
        </div>
        <div class="card">
            <h2>Documents</h2>
            <?php if (!$docs): ?><p class="muted">No documents uploaded.</p><?php endif; ?>
            <ul>
                <?php foreach ($docs as $d): ?>
                    <li><a href="<?= e(url('admission/' . $app['id'] . '/documents/' . $d['id'])) ?>" target="_blank" rel="noopener"><?= e(Admission::DOC_TYPES[$d['doc_type']] ?? $d['doc_type']) ?></a>
                    <small><?= e($d['original_name']) ?> · <?= e(round($d['size'] / 1024)) ?> KB</small></li>
                <?php endforeach; ?>
            </ul>
            <?php if (can('admission.manage')): ?>
            <form method="post" action="<?= e(url('admission/' . $app['id'] . '/documents')) ?>" enctype="multipart/form-data" class="filters" style="margin:0">
                <?= csrf_field() ?>
                <label>Type <select name="doc_type"><?php foreach (Admission::DOC_TYPES as $k => $label): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
                <label>File <input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png"></label>
                <button class="btn" type="submit">Upload</button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <?php if (can('admission.enrol') && in_array($app['stage'], ['offered', 'accepted'], true)): ?>
        <div class="card" style="border-color:var(--good)">
            <h2>Enrol student</h2>
            <?php if (!$classes): ?>
                <p class="muted">No active class exists for <?= e($app['level_name']) ?>. Add one in <a href="<?= e(url('setup/classes')) ?>">Setup → Classes</a>.</p>
            <?php else: ?>
            <form method="post" action="<?= e(url('admission/' . $app['id'] . '/enrol')) ?>" class="form" data-confirm="Enrol <?= e(full_name($app)) ?> and issue an admission number?">
                <?= csrf_field() ?>
                <label>Class
                    <select name="class_id" required<?= QuickAddController::attr('class') ?>>
                        <?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?> (<?= (int) $c['pupils'] ?>/<?= (int) $c['capacity'] ?>)</option><?php endforeach; ?>
                    </select>
                </label>
                <label>Admission date <input type="date" name="admitted_on" value="<?= date('Y-m-d') ?>" required></label>
                <label style="font-weight:400"><input type="checkbox" name="admission_bill" value="1" checked> Raise the admission fee bill and SMS it to the parent</label>
                <button class="btn btn-good" type="submit">Enrol &amp; issue admission number</button>
            </form>
            
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (can('admission.manage') && $next): ?>
        <div class="card">
            <h2>Move to next stage</h2>
            <form method="post" action="<?= e(url('admission/' . $app['id'] . '/stage')) ?>" class="form">
                <?= csrf_field() ?>
                <label>New stage
                    <select name="stage" required>
                        <?php foreach ($next as $s): ?><option value="<?= e($s) ?>"><?= e(Admission::label($s)) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <div class="grid-2">
                    <label>Assessment date <input type="date" name="interview_date" value="<?= e($app['interview_date']) ?>"></label>
                    <label>Entrance score % <input type="number" name="entrance_score" min="0" max="100" step="0.5" value="<?= e($app['entrance_score']) ?>"></label>
                </div>
                <label>Note <input name="note" maxlength="255" placeholder="Optional — visible to staff only"></label>
                <button class="btn btn-primary" type="submit">Update stage</button>
            </form>
        </div>
        <?php endif; ?>

        <div class="card">
            <h2>History</h2>
            <ul class="timeline">
                <?php foreach ($log as $l): ?>
                    <li><strong><?= e(Admission::label($l['to_stage'])) ?></strong><?= $l['from_stage'] ? ' <small>from ' . e(Admission::label($l['from_stage'])) . '</small>' : '' ?><br>
                    <small><?= e(fmt_date($l['changed_at'], 'd M Y H:i')) ?> · <?= e($l['user_name'] ?? 'Parent (online)') ?></small>
                    <?php if ($l['note']): ?><br><?= e($l['note']) ?><?php endif; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
