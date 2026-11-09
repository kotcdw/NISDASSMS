<?php
$mg = can('health.manage');
$sens = can('pupils.sensitive'); // the health profile and reports also need "sensitive details"
$openForm = $mg && (!empty($_GET['new']) || old('complaint') !== '' || old('complaint_pick') !== '');
$oldPick = old('complaint_pick');
$oldActs = (array) old('actions', []);
?>
<?php $sentHome = count(array_filter($visits, fn($v) => in_array($v['outcome'], ['sent_home', 'referred'], true))); ?>

<?php require __DIR__ . '/_tabs.php'; ?>
<?php if ((int) $nhis['missing'] || (int) $nhis['expired']): ?><div class="alert alert-warning"><?= (int) $nhis['missing'] ?> student(s) have no NHIS number and <?= (int) $nhis['expired'] ?> NHIS card(s) have expired. <?php if ($sens): ?><a href="<?= e(url('health/report', ['kind' => 'nhis'])) ?>">See the list</a><?php endif; ?></div><?php endif; ?>

<?php if ($mg): ?>
<form method="post" action="<?= e(url('health/visits')) ?>" class="card form form-center visit-form" id="visit-form"<?= $openForm ? '' : ' hidden' ?>>
    <?= csrf_field() ?>
    <div class="vf-head"><h2>Record a sick bay visit</h2><span class="muted"><?= e(date('D j M, g:ia')) ?></span></div>
    <div class="grid-2">
        <label>Class<select data-limits="#vf-pupil" aria-label="Class"><option value="">All classes</option><?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></label>
        <label class="required">Student<select name="pupil_id" id="vf-pupil" required><option value="">Choose the student…</option>
            <?php $cur = null; foreach ($pupils as $p): if ($p['class_id'] !== $cur): if ($cur !== null): ?></optgroup><?php endif; $cur = $p['class_id']; ?><optgroup label="<?= e($p['class_name']) ?>" data-group="<?= (int) $p['class_id'] ?>"><?php endif; ?>
                <option value="<?= (int) $p['id'] ?>" data-group="<?= (int) $p['class_id'] ?>"<?= selected($p['id'], old('pupil_id')) ?>><?= e($p['last_name'] . ', ' . $p['first_name']) ?> · <?= e($p['admission_no']) ?></option>
            <?php endforeach; if ($cur !== null): ?></optgroup><?php endif; ?>
        </select></label>
        <label class="required">Complaint<select name="complaint_pick" required><option value="">Choose…</option><?php foreach (HealthController::COMPLAINTS as $c): ?><option<?= selected($c, $oldPick) ?>><?= e($c) ?></option><?php endforeach; ?><option value="other"<?= selected('other', $oldPick) ?>>Other (describe below)</option></select></label>
        <label>Temperature (°C)<input type="number" name="temperature" step="0.1" min="34" max="43" inputmode="decimal" value="<?= e(old('temperature')) ?>" placeholder="e.g. 37.5"></label>
    </div>
    <label>Details<input name="complaint" maxlength="200" value="<?= e(old('complaint')) ?>" placeholder="e.g. Since morning break; fell on the stairs"></label>
    <fieldset class="ln-chips"><legend>What was done</legend>
        <?php foreach (HealthController::ACTIONS as $a): ?><label><input type="checkbox" name="actions[]" value="<?= e($a) ?>"<?= in_array($a, $oldActs, true) ? ' checked' : '' ?>><span><?= e($a) ?></span></label><?php endforeach; ?>
    </fieldset>
    <label>Other notes<input name="action_taken" maxlength="200" value="<?= e(old('action_taken')) ?>" placeholder="e.g. Paracetamol 250 mg at 10:15 (consent on file)"></label>
    <div class="grid-2">
        <label class="required">Outcome<select name="outcome"><?php foreach (HealthController::OUTCOMES as $k => [$l]): ?><option value="<?= e($k) ?>"<?= selected(old('outcome'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
        <label class="vf-notify"><input type="checkbox" name="notify" value="1"<?= checked(old('notify') === '1') ?>> Inform the parent by SMS</label>
    </div>
    <div class="vf-foot"><span class="vf-warn">⚠ Give medicine only with the parent's written consent.</span><span style="display:flex;gap:.5rem"><button type="button" class="btn btn-ghost" data-help="visit-form">Cancel</button><button class="btn btn-primary" type="submit">Save visit</button></span></div>
</form>
<?php endif; ?>

<div class="xgrid-box" data-xgrid="sickbay" data-xgrid-file="sick-bay-visits">
<div class="page-head">
    <div><h1>Health &amp; sick bay</h1><p class="page-sub"><?= e(HealthController::PERIODS[$period] ?? 'This term') ?> · <?= count($visits) ?> visit<?= count($visits) === 1 ? '' : 's' ?><?= $today ? ' · <strong>' . $today . ' today</strong>' : '' ?><?= $sentHome ? ' · ' . $sentHome . ' sent home or referred' : '' ?></p></div>
    <div class="actions">
        <?= export_grid_menu() ?>
        <?php if ($mg): ?><button type="button" class="btn btn-primary" data-help="visit-form" aria-expanded="<?= $openForm ? 'true' : 'false' ?>">+ Record a visit</button><?php endif; ?>
    </div>
</div>
    <div class="xg-scroll">
    <table class="xgrid">
        <thead><tr>
            <?php foreach (['when' => ['When', 'date'], 'student' => ['Student', 'text'], 'class' => ['Class', 'text'], 'complaint' => ['Complaint', 'text'], 'temp' => ['Temp.', 'num'], 'done' => ['What was done', 'text'], 'outcome' => ['Outcome', 'text'], 'by' => ['By', 'text']] as $k => [$t, $ty]): ?><th data-c="<?= $k ?>" data-type="<?= $ty ?>"><button type="button" class="xg-sort"><?= e($t) ?><span class="xg-arrow"></span></button></th><?php endforeach; ?>
        </tr></thead>
        <tbody>
        <?php foreach ($visits as $v): [$ol, $ot] = HealthController::OUTCOMES[$v['outcome']]; $hot = $v['temperature'] !== null && (float) $v['temperature'] >= 37.5; ?>
        <tr>
            <td data-c="when" data-s="<?= e($v['seen_at']) ?>"><span class="hv-when"><?= e(date('D j M', strtotime($v['seen_at']))) ?><span hidden>, </span><small><?= e(date('g:ia', strtotime($v['seen_at']))) ?></small></span></td>
            <td data-c="student"><?php if ($sens): ?><a href="<?= e(url('health/pupils/' . $v['pupil_id'])) ?>" title="Open health record"><?= e($v['first_name'] . ' ' . $v['last_name']) ?></a><?php else: ?><?= e($v['first_name'] . ' ' . $v['last_name']) ?><?php endif; ?></td>
            <td data-c="class"><?= e($v['class_name'] ?? '') ?></td>
            <td data-c="complaint"><?= e($v['complaint']) ?></td>
            <td data-c="temp" class="num"><?php if ($v['temperature'] !== null): ?><span class="hv-temp<?= $hot ? ' is-hot' : '' ?>"<?= $hot ? ' title="Fever (37.5° or more)"' : '' ?>><?= e($v['temperature']) ?>°</span><?php else: ?><span class="muted">—</span><?php endif; ?></td>
            <td data-c="done" class="hv-done"><?= e($v['action_taken'] ?? '') ?: '<span class="muted">—</span>' ?></td>
            <td data-c="outcome"><?= badge($ol, $ot) ?><?= (int) $v['guardian_notified'] ? '<span class="hv-sms" title="Parent informed by SMS"><span hidden> · </span>SMS sent</span>' : '' ?></td>
            <td data-c="by"><?= e($v['recorded_name'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="xg-empty muted"<?= $visits ? ' hidden' : '' ?> data-xg-empty>No sick bay visits this term.</p>
    </div>
</div>
