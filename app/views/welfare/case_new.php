<?php $wt = 'welfare/cases'; $k = old('kind', $kind); ?>
<div class="page-center">
<div class="page-head"><div><h1>Open a welfare case</h1></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="kind-pick"><span>Type of case</span><nav class="seg" aria-label="Type of case"><?php foreach (['counselling' => 'Counselling', 'incident' => 'Accident / incident', 'safeguarding' => 'Safeguarding concern'] as $kk => $kl): ?><a href="<?= e(url('welfare/cases/new', array_filter(['kind' => $kk, 'pupil' => $pick ?: null]))) ?>"<?= $k === $kk ? ' class="active"' : '' ?>><?= e($kl) ?></a><?php endforeach; ?></nav></div>
<?php if ($k === 'safeguarding'): ?><div class="alert alert-warning"><strong>Safeguarding concern:</strong> if a child is in immediate danger, call the police (DOVVSU) or the Head now. Write only what you saw or were told, in the child's own words. Do not question the child further or promise to keep a secret. Only the safeguarding lead will see the details.</div><?php endif; ?>
<div class="card">
<form method="post" action="<?= e(url('welfare/cases')) ?>" class="form"><?= csrf_field() ?>
    <div>
        <label class="required">Student <select name="pupil_id" required><option value="">Choose…</option><?php foreach ($pupils as $p): ?><option value="<?= (int) $p['id'] ?>"<?= selected((int) old('pupil_id', $pick), (int) $p['id']) ?>><?= e($p['class_name'] . ' — ' . $p['first_name'] . ' ' . $p['last_name']) ?></option><?php endforeach; ?></select></label>
        <input type="hidden" name="kind" value="<?= e($k) ?>">
    </div>
    <label class="required">Short title <input name="title" maxlength="150" required value="<?= e(old('title')) ?>" placeholder="<?= $k === 'incident' ? 'e.g. Fell in the playground, cut on the knee' : 'e.g. Withdrawn and tearful this week' ?>"></label>
    <label>What happened <textarea name="details" rows="4"><?= e(old('details')) ?></textarea></label>
    <div class="grid-3">
        <label class="required">Risk <select name="risk"><?php foreach (Welfare::RISK as $rk => [$rl]): ?><option value="<?= e($rk) ?>"<?= selected(old('risk', $k === 'safeguarding' ? 'high' : 'low'), $rk) ?>><?= e($rl) ?></option><?php endforeach; ?></select></label>
        <label>Referred to <select name="referred_to"><option value="">—</option><?php foreach (Welfare::REFERRALS as $r): ?><option<?= selected(old('referred_to'), $r) ?>><?= e($r) ?></option><?php endforeach; ?></select></label>
        <label>Follow up on <input type="date" name="follow_up_on" value="<?= e(old('follow_up_on', date('Y-m-d', strtotime('+3 days')))) ?>"></label>
    </div>
    <?php if ($k !== 'safeguarding' && can('welfare.manage')): ?><label>Followed up by <select name="assigned_to"><option value="">Class teacher</option><?php foreach ($staff as $u): ?><option value="<?= (int) $u['id'] ?>"<?= selected((int) old('assigned_to'), (int) $u['id']) ?>><?= e($u['name']) ?></option><?php endforeach; ?></select></label><?php endif; ?>
    <?php if ($k === 'incident'): ?>
        <label>First aid given <input name="first_aid" maxlength="255" value="<?= e(old('first_aid')) ?>" placeholder="e.g. Cleaned and plaster applied at the sick bay"></label>
        <label><input type="checkbox" name="tell_parent" value="1" checked> Send the parent an SMS now</label>
    <?php endif; ?>
    <button class="btn btn-primary" type="submit"><?= $k === 'safeguarding' ? 'Pass to the safeguarding lead' : 'Open case' ?></button>
</form>
</div>
</div>
