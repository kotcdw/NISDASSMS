<?php $wt = 'welfare/behaviour/new'; $ok = old('kind', 'merit'); $classes = array_values(array_unique(array_column($pupils, 'class_name'))); ?>
<div class="page-center">
<div class="page-head"><div><h1>Record behaviour</h1></div><div class="actions"><a class="btn btn-ghost" href="<?= e(url('welfare/behaviour')) ?>">See behaviour records</a></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<form method="post" action="<?= e(url('welfare/behaviour')) ?>" class="card form beh-form"><?= csrf_field() ?>
    <div class="grid-2">
        <label>Class<select data-limits="#beh-pupil" aria-label="Class"><option value="">All classes</option><?php foreach ($classes as $c): ?><option value="<?= e($c) ?>"><?= e($c) ?></option><?php endforeach; ?></select></label>
        <label class="required">Student<select name="pupil_id" id="beh-pupil" required><option value="">Choose the student…</option>
            <?php foreach ($pupils as $p): ?><option value="<?= (int) $p['id'] ?>" data-group="<?= e($p['class_name']) ?>"<?= selected((int) old('pupil_id', $pick), (int) $p['id']) ?>><?= e($p['class_name'] . ' — ' . $p['first_name'] . ' ' . $p['last_name']) ?></option><?php endforeach; ?>
        </select></label>
        <label class="required">Type<select name="kind" data-limits="#beh-cat, #beh-action"><option value="merit"<?= selected('merit', $ok) ?>>⭐ Merit (well done)</option><option value="concern"<?= selected('concern', $ok) ?>>⚠ Concern</option></select></label>
        <label class="required">Date<input type="date" name="occurred_on" max="<?= e(date('Y-m-d')) ?>" value="<?= e(old('occurred_on', date('Y-m-d'))) ?>" required></label>
        <label class="required">What for<select name="category" id="beh-cat" required><option value="">Choose…</option>
            <?php foreach (Welfare::MERITS as $c): ?><option data-group="merit"<?= selected(old('category'), $c) ?>><?= e($c) ?></option><?php endforeach; ?>
            <?php foreach (Welfare::CONCERNS as $c): ?><option data-group="concern"<?= selected(old('category'), $c) ?>><?= e($c) ?></option><?php endforeach; ?>
        </select></label>
        <label>Positive-discipline action (concerns)<select name="action_taken" id="beh-action"><option value="">—</option><?php foreach (Welfare::ACTIONS as $k => $l): ?><option value="<?= e($k) ?>" data-group="concern"<?= selected(old('action_taken'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
    </div>
    <label>What happened<textarea name="description" rows="2" maxlength="500" placeholder="Optional — a short note for the record"><?= e(old('description')) ?></textarea></label>
    <div class="vf-foot">
        <label style="font-weight:400;margin:0"><input type="checkbox" name="tell_parent" value="1"<?= checked(old('tell_parent')) ?>> Send the parent an SMS</label>
        <button class="btn btn-primary" type="submit">Save</button>
    </div>
    <p class="vf-warn" style="margin:.6rem 0 0">Positive discipline only — corporal punishment is banned by GES.</p>
</form>
</div>
