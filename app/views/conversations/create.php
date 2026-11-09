<div class="page-center">
<div class="page-head"><div><p class="breadcrumb"><a href="<?= e(url('conversations')) ?>">Messages</a></p><h1>New message</h1></div></div>
<?php if (can('staff')): ?>
    <?php if (empty($pupil)): ?>
    <div class="card">
        <form method="get" action="<?= e(url('conversations/create')) ?>" class="filters"><label>Find the student (name or admission no.)<input type="search" name="q" value="<?= e($q) ?>" autofocus></label><button class="btn" type="submit">Search</button></form>
        <?php foreach ($found as $f): ?><p style="margin:0 0 .3rem"><a href="<?= e(url('conversations/create', ['pupil' => $f['id']])) ?>"><?= e($f['first_name'] . ' ' . $f['last_name']) ?></a> <small class="muted"><?= e($f['admission_no']) ?></small></p><?php endforeach; ?>
        <?php if ($q !== '' && !$found): ?><p class="muted">No student found.</p><?php endif; ?>
    </div>
    <?php elseif (!empty($student)): ?>
        <?php if (empty($child)): ?><div class="card"><p class="muted" style="margin:0">Your student record is not linked to this account. Please contact the school office.</p></div>
        <?php elseif (!$staff): ?><div class="card"><p class="muted" style="margin:0">No teacher is linked to your class yet.</p></div>
        <?php else: ?>
        <form method="post" action="<?= e(url('conversations')) ?>" class="card form">
            <?= csrf_field() ?><input type="hidden" name="pupil_id" value="<?= (int) $child['id'] ?>">
            <p><strong>About:</strong> <?= e(full_name($child)) ?> · <?= e($child['class_name'] ?? 'No current class') ?></p>
            <label>To<select name="to" required><?php foreach ($staff as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e($s['name'] . ' — ' . $s['label']) ?></option><?php endforeach; ?></select></label>
            <label>Subject<input name="subject" required maxlength="150"></label>
            <label>Message<textarea name="body" rows="6" required maxlength="5000"></textarea></label>
            <button class="btn btn-primary" type="submit">Send</button>
        </form>
        <?php endif; ?>
    <?php else: ?>
    <form method="post" action="<?= e(url('conversations')) ?>" class="card form">
        <?= csrf_field() ?><input type="hidden" name="pupil_id" value="<?= (int) $pupil['id'] ?>">
        <p><strong>About:</strong> <?= e(full_name($pupil)) ?> (<?= e($pupil['admission_no']) ?>) · <a href="<?= e(url('conversations/create')) ?>">change</a></p>
        <?php if (!$parents && empty($studentUser)): ?><div class="alert alert-warning">This student's parents do not have a portal login yet.<?php if (can('logins.manage')): ?> <a href="<?= e(url('logins')) ?>">Create one</a>.<?php endif; ?></div>
        <?php else: ?>
        <?php if ($parents): ?><p><strong>To parents:</strong> <?= e(implode(', ', array_column($parents, 'name'))) ?></p><?php endif; ?>
        <?php if (!empty($studentUser)): ?><label><input type="checkbox" name="include_student" value="1"> Also send to <?= e($studentUser['name']) ?></label><?php endif; ?>
        <label>Subject<input name="subject" required maxlength="150"></label>
        <label>Message<textarea name="body" rows="6" required maxlength="5000"></textarea></label>
        <button class="btn btn-primary" type="submit">Send</button>
        <?php endif; ?>
    </form>
    <?php endif; ?>
<?php else: ?>
    <?php if (empty($children)): ?><div class="card"><p class="muted" style="margin:0">No children are linked to your account. Please contact the school office.</p></div>
    <?php else: ?>
    <form method="get" action="<?= e(url('conversations/create')) ?>" class="filters" data-autosubmit>
        <label>About<select name="pupil"><?php foreach ($children as $ch): ?><option value="<?= (int) $ch['id'] ?>"<?= selected($child['id'], $ch['id']) ?>><?= e($ch['first_name'] . ' ' . $ch['last_name'] . ' — ' . ($ch['class_name'] ?? '')) ?></option><?php endforeach; ?></select></label>
    </form>
    <form method="post" action="<?= e(url('conversations')) ?>" class="card form">
        <?= csrf_field() ?><input type="hidden" name="pupil_id" value="<?= (int) $child['id'] ?>">
        <label>To<select name="to" required><?php foreach ($staff as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e($s['name'] . ' — ' . $s['label']) ?></option><?php endforeach; ?><?php if (!empty($studentUser)): ?><option value="<?= (int) $studentUser['id'] ?>"><?= e($studentUser['name'] . ' — your child') ?></option><?php endif; ?></select></label>
        <label>Subject<input name="subject" required maxlength="150" placeholder="e.g. Absence on Monday"></label>
        <label>Message<textarea name="body" rows="6" required maxlength="5000"></textarea></label>
        <button class="btn btn-primary" type="submit">Send</button>
    </form>
    <?php endif; ?>
<?php endif; ?>
</div>
