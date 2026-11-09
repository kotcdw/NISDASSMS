<?php $value = fn(string $key, string $default = '') => old($key, (string) ($record[$key] ?? $default)); ?>
<div class="page-head"><div><p class="breadcrumb"><a href="<?= e(url('teacher-records')) ?>">Teacher records</a></p><h1><?= e($title) ?></h1></div></div>
<form method="post" action="<?= e(url($record ? 'teacher-records/' . $record['id'] : 'teacher-records')) ?>" class="card form">
    <?= csrf_field() ?>
    <?php if (!$record): ?>
    <label class="required">Teacher login<select name="user_id" required><option value="">Choose account</option><?php foreach ($users as $user): ?><option value="<?= (int) $user['id'] ?>"><?= e($user['name']) ?> · <?= e(Auth::roleLabel($user['role'])) ?></option><?php endforeach; ?></select></label>
    <?php if (!$users): ?><p class="muted">There are no active teaching accounts available to link. Create a staff account first, then return here to add its teacher record.</p><p><a class="btn" href="<?= e(url('users/create')) ?>">Create staff account</a></p><?php endif; ?>
    <?php else: ?>
    <p><strong><?= e($record['staff_no']) ?></strong> · <?= e($record['name']) ?></p>
    <label class="required">Full name<input name="name" required maxlength="150" value="<?= e($value('name')) ?>"><?= field_error('name') ?></label>
    <label>Phone<input name="phone" maxlength="20" value="<?= e($value('phone')) ?>"><?= field_error('phone') ?></label>
    <?php endif; ?>
    <div class="grid-2">
        <label class="required">Position<input name="position" required maxlength="80" value="<?= e($value('position', 'Teacher')) ?>"><?= field_error('position') ?></label>
        <label>Employment<select name="employment"><?php foreach (Payroll::EMPLOYMENT as $key => $label): ?><option value="<?= e($key) ?>"<?= selected($value('employment', 'permanent'), $key) ?>><?= e($label) ?></option><?php endforeach; ?></select><?= field_error('employment') ?></label>
        <label>Gender<select name="gender"><option value="">—</option><?php foreach (Payroll::GENDER as $key => $label): ?><option value="<?= e($key) ?>"<?= selected($value('gender'), $key) ?>><?= e($label) ?></option><?php endforeach; ?></select><?= field_error('gender') ?></label>
        <label>Highest qualification<select name="qualification"><option value="">—</option><?php foreach (Payroll::QUALIFICATION as $key => [$label]): ?><option value="<?= e($key) ?>"<?= selected($value('qualification'), $key) ?>><?= e($label) ?></option><?php endforeach; ?></select><?= field_error('qualification') ?></label>
        <label>Started<input type="date" name="start_date" value="<?= e($value('start_date')) ?>"><?= field_error('start_date') ?></label>
        <label>NTC licence number<input name="ntc_licence_no" maxlength="30" value="<?= e($value('ntc_licence_no')) ?>"></label>
        <label>NTC licence expires<input type="date" name="ntc_expiry" value="<?= e($value('ntc_expiry')) ?>"><?= field_error('ntc_expiry') ?></label>
    </div>
    <?php if ($record): ?><label><input type="checkbox" name="is_active" value="1"<?= checked((int) $record['is_active'] === 1) ?>> Record is active</label><?php endif; ?>
    <p class="muted">This form does not manage salary, bank, tax or payroll access.</p>
    <div class="actions"><a class="btn" href="<?= e(url('teacher-records')) ?>">Cancel</a><button class="btn btn-primary" type="submit"<?= !$record && !$users ? ' disabled' : '' ?>>Save record</button></div>
</form>
