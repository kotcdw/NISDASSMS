<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('appointments')) ?>">Appointments</a> /</div><h1>Book an appointment</h1></div></div>
<div class="card">
    <h2>1. What is it about?</h2>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
        <?php foreach ($types as $t): ?><a class="pick-card<?= $type && (int) $type['id'] === (int) $t['id'] ? ' active' : '' ?>" href="<?= e(url('appointments/book', ['type' => $t['id']])) ?>"><?= e($t['name']) ?> <small>(<?= (int) $t['duration_min'] ?> min)</small></a><?php endforeach; ?>
        <?php if (!$types): ?><p class="muted">No staff are offering appointment times at the moment. Please call or message the school.</p><?php endif; ?>
    </div>
    <?php if ($type && $type['description']): ?><p class="hint" style="margin:.7rem 0 0"><?= e($type['description']) ?></p><?php endif; ?>
</div>
<?php if ($type): ?>
<div class="card">
    <h2>2. With whom?</h2>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
        <?php foreach ($staff as $s): ?><a class="pick-card<?= $person && (int) $person['id'] === (int) $s['id'] ? ' active' : '' ?>" href="<?= e(url('appointments/book', ['type' => $type['id'], 'staff' => $s['id']])) ?>"><?= e($s['name']) ?><?= $s['mine'] ? ' ★' : '' ?></a><?php endforeach; ?>
    </div>
    <?php if (array_filter($staff, fn($s) => $s['mine'])): ?><p class="hint" style="margin:.7rem 0 0">★ your child's class teacher</p><?php endif; ?>
</div>
<?php endif; ?>
<?php if ($person): ?>
<form method="post" action="<?= e(url('appointments')) ?>" class="card form"><?= csrf_field() ?>
    <input type="hidden" name="type_id" value="<?= (int) $type['id'] ?>"><input type="hidden" name="staff_id" value="<?= (int) $person['id'] ?>">
    <h2>3. Choose a time</h2>
    <?php foreach ($days as $d => $slots): ?>
        <p style="margin:.4rem 0 .2rem"><strong><?= e(date('l j M', strtotime($d))) ?></strong></p>
        <div style="display:flex;gap:.35rem;flex-wrap:wrap"><?php foreach ($slots as $s): ?><label class="btn btn-sm" style="font-weight:500"><input type="radio" name="start" value="<?= e($d . ' ' . $s) ?>" required> <?= e($s) ?></label><?php endforeach; ?></div>
    <?php endforeach; ?>
    <?php if (!$days): ?><p class="muted"><?= e($person['name']) ?> has no free times in the next three weeks. Try another person or send a message.</p><?php else: ?>
    <div class="grid-2" style="margin-top:1rem">
        <?php if ($children): ?><label>Child<select name="pupil_id"><option value="">—</option><?php foreach ($children as $c): ?><option value="<?= (int) $c['id'] ?>"<?= count($children) === 1 ? ' selected' : '' ?>><?= e($c['first_name'] . ' ' . $c['last_name']) ?></option><?php endforeach; ?></select></label><?php endif; ?>
    </div>
    <label<?= $type['question'] ? ' class="required"' : '' ?>><?= e($type['question'] ?: 'Anything the staff member should know first? (optional)') ?><textarea name="answer" rows="2" maxlength="500"<?= $type['question'] ? ' required' : '' ?>></textarea></label>
    <button class="btn btn-primary" type="submit">Book</button>
    <?php endif; ?>
</form>
<?php endif; ?>
