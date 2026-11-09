<?php $v = fn($k, $d = '') => old($k, $h[$k] ?? $d); $manage = can('health.manage'); ?>
<div class="page-head"><div><p class="breadcrumb"><a href="<?= e(url('health')) ?>">Health</a><?= can('pupils.view') ? ' · <a href="' . e(url('pupils/' . $p['id'])) . '">Student record</a>' : '' ?></p>
<h1><?= e($p['first_name'] . ' ' . $p['last_name']) ?></h1><p class="muted"><?= e($p['admission_no']) ?> · <?= e($p['class_name'] ?? '') ?> · <?= e(age_from($p['date_of_birth'])) ?></p></div></div>
<?php if ($p['allergies'] || ($h['conditions'] ?? '')): ?><div class="alert alert-warning"><strong>Allergies:</strong> <?= e($p['allergies'] ?: 'none recorded') ?><?= ($h['conditions'] ?? '') ? ' · <strong>Conditions:</strong> ' . e($h['conditions']) : '' ?></div><?php endif; ?>
<div class="cols">
<div>
<form method="post" action="<?= e(url('health/pupils/' . $p['id'])) ?>" class="card form">
    <?= csrf_field() ?>
    <h2>Health record</h2>
    <fieldset<?= $manage ? '' : ' disabled' ?> style="border:0;padding:0;margin:0">
    <div class="grid-3">
        <label>Blood group<select name="blood_group"><option value="">—</option><?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $b): ?><option<?= selected($v('blood_group'), $b) ?>><?= $b ?></option><?php endforeach; ?></select><?= field_error('blood_group') ?></label>
        <label>Genotype<select name="genotype"><option value="">—</option><?php foreach (['AA', 'AS', 'SS', 'AC', 'SC', 'CC'] as $g): ?><option<?= selected($v('genotype'), $g) ?>><?= $g ?></option><?php endforeach; ?></select><?= field_error('genotype') ?></label>
        <label>Immunisations up to date<select name="immunisation_ok"><option value="">Not known</option><option value="1"<?= selected((string) $v('immunisation_ok'), '1') ?>>Yes (card seen)</option><option value="0"<?= selected((string) $v('immunisation_ok'), '0') ?>>No</option></select></label>
        <label>NHIS number<input name="nhis_no" maxlength="30" value="<?= e($v('nhis_no')) ?>"></label>
        <label>NHIS expiry<input type="date" name="nhis_expiry" value="<?= e($v('nhis_expiry')) ?>"><?= field_error('nhis_expiry') ?></label>
        <label>Usual clinic / hospital<input name="clinic" maxlength="120" value="<?= e($v('clinic')) ?>"></label>
    </div>
    <label>Allergies<input name="allergies" maxlength="255" value="<?= e(old('allergies', $p['allergies'] ?? '')) ?>" placeholder="e.g. Groundnuts, penicillin"></label>
    <label>Conditions<input name="conditions" maxlength="255" value="<?= e($v('conditions')) ?>" placeholder="e.g. Asthma, sickle cell, epilepsy"></label>
    <label>Regular medication<input name="medication" maxlength="255" value="<?= e($v('medication')) ?>" placeholder="e.g. Inhaler kept in bag"></label>
    <label>Other medical notes<textarea name="medical_notes" rows="2"><?= e(old('medical_notes', $p['medical_notes'] ?? '')) ?></textarea></label>
    <div class="grid-2">
        <label>Emergency contact (if parent unreachable)<input name="emergency_name" maxlength="120" value="<?= e($v('emergency_name')) ?>"></label>
        <label>Their phone<input name="emergency_phone" value="<?= e($v('emergency_phone')) ?>"><?= field_error('emergency_phone') ?></label>
    </div>
    </fieldset>
    <?php if ($manage): ?><button class="btn btn-primary" type="submit">Save</button><?php endif; ?>
    <?php if ($h && $h['updated_name']): ?><p class="hint" style="margin-top:.6rem">Last updated by <?= e($h['updated_name']) ?><?= $h['updated_at'] ? ' on ' . e(fmt_date($h['updated_at'])) : '' ?>.</p><?php endif; ?>
</form>
</div>
<div>
    <div class="card"><h2>Parent</h2><p style="margin:0"><?= $guardian ? e($guardian['name']) . '<br><a href="tel:' . e($guardian['phone']) . '">' . e($guardian['phone']) . '</a>' : '<span class="muted">No parent on record.</span>' ?></p></div>
    <div class="card" style="padding:0">
        <h2 style="padding:1rem 1rem 0">Sick bay visits</h2>
        <div class="table-wrap"><table>
            <?php foreach ($visits as $x): [$ol, $ot] = HealthController::OUTCOMES[$x['outcome']]; ?>
            <tr><td><?= e(date('d M Y, g:ia', strtotime($x['seen_at']))) ?><br><small><?= e($x['complaint']) ?><?= $x['temperature'] !== null ? ' (' . e($x['temperature']) . '°C)' : '' ?></small></td><td><?= badge($ol, $ot) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$visits): ?><tr><td class="muted">None.</td></tr><?php endif; ?>
        </table></div>
    </div>
</div>
</div>
