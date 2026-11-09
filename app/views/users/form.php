<?php $u = $user ?? []; $action = $user ? url('users/' . $user['id']) : url('users'); ?>
<div class="page-center">
<div class="page-head"><div><div class="breadcrumb"><a href="<?= e(url('users')) ?>">Users</a> /</div><h1><?= $user ? 'Edit ' . e($user['name']) : 'New user' ?></h1></div></div>
<div class="card">
    <form method="post" action="<?= e($action) ?>" class="form">
        <?= csrf_field() ?>
        <label class="required">Full name <input name="name" required value="<?= e(old('name', $u['name'] ?? '')) ?>"><?= field_error('name') ?></label>
        <div class="grid-2">
            <label class="required">Email <input type="email" name="email" required value="<?= e(old('email', $u['email'] ?? '')) ?>"><?= field_error('email') ?></label>
            <label>Phone <input name="phone" placeholder="0244123456" value="<?= e(old('phone', $u['phone'] ?? '')) ?>"><?= field_error('phone') ?></label>
        </div>
        <label class="required">Role
            <select name="role" required>
                <?php foreach (Auth::roles() as $k => $label): if ($k === 'super_admin' && Auth::role() !== 'super_admin') continue; ?><option value="<?= e($k) ?>"<?= selected($k, old('role', $u['role'] ?? 'class_teacher')) ?>><?= e($label) ?></option><?php endforeach; ?>
            </select><?= field_error('role') ?>
        </label>
        <?php if (Auth::hasAccessUntil()): ?><label>Access ends on (optional)<input type="date" name="access_until" value="<?= e(old('access_until', $u['access_until'] ?? '')) ?>"><small class="hint">For an external auditor or a temporary account: sign-in stops after this date.</small></label><?php endif; ?>
        <?php if ($user): ?>
            <label><input type="checkbox" name="is_active" value="1"<?= checked($u['is_active']) ?>> Account active</label>
            <?php if (Security::ready()): ?><label>Reason if switched off <input name="locked_reason" maxlength="255" value="<?= e($u['locked_reason'] ?? '') ?>" placeholder="e.g. Left the school, on leave"></label><?php endif; ?>
            
        <?php else: $pwMode = old('pw_mode', 'auto'); ?>
            <fieldset class="pw-choice">
                <legend>Sign-in password</legend>
                <label class="check"><input type="radio" name="pw_mode" value="auto"<?= $pwMode !== 'typed' ? ' checked' : '' ?>> Make a temporary password for me <small class="muted">(shown once after saving)</small></label>
                <label class="check"><input type="radio" name="pw_mode" value="typed"<?= $pwMode === 'typed' ? ' checked' : '' ?>> I will type a temporary password</label>
                <label class="pw-typed">Temporary password <input name="temp_password" type="text" autocomplete="new-password" spellcheck="false" maxlength="200"><?= field_error('temp_password') ?>
                    <small class="hint">At least <?= (int) PasswordPolicy::settings()['min_staff'] ?> characters, letters with a number or symbol, not their name. They must change it at their first sign-in.</small></label>
            </fieldset>
        <?php endif; ?>
        <button class="btn btn-primary" type="submit">Save</button>
        <a class="btn btn-ghost" href="<?= e(url('users')) ?>">Cancel</a>
    </form>
</div>
<?php if ($user): ?>
<div class="card">
    <h2>Password</h2>
    <form method="post" action="<?= e(url('users/' . $u['id'] . '/reset')) ?>" class="form" data-confirm="Give <?= e($u['name']) ?> a new temporary password? They will be signed out of every device and must choose their own password at the next sign-in.">
        <?= csrf_field() ?><input type="hidden" name="back" value="edit"><?php $pwMode = old('pw_mode', 'auto'); ?>
        <label class="check"><input type="radio" name="pw_mode" value="auto"<?= $pwMode !== 'typed' ? ' checked' : '' ?>> Make a temporary password for me <small class="muted">(shown once)</small></label>
        <label class="check"><input type="radio" name="pw_mode" value="typed"<?= $pwMode === 'typed' ? ' checked' : '' ?>> I will type a temporary password</label>
        <label class="pw-typed">Temporary password <input name="temp_password" type="text" autocomplete="new-password" spellcheck="false" maxlength="200"><?= field_error('temp_password') ?>
            <small class="hint">At least <?= (int) PasswordPolicy::settings()['min_staff'] ?> characters, letters with a number or symbol, not their name.</small></label>
        <button class="btn" type="submit">Set temporary password</button>
    </form>
</div>
<?php endif; ?>
<?php if ($user && Security::ready()): ?>
<div class="card">
    <h2>Security</h2>
    <p>2-step sign-in: <?= !empty($mfa) ? badge('On', 'good') : (!empty($mfaRequired) ? badge('Required — not set up yet', 'bad') : badge('Off', 'neutral')) ?>
       · Last sign-in: <?= e(fmt_date($u['last_login_at'], 'd M Y H:i')) ?><?= !empty($u['locked_reason']) && !(int) $u['is_active'] ? ' · ' . e($u['locked_reason']) : '' ?></p>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
        <form method="post" action="<?= e(url('users/' . $u['id'] . '/signout')) ?>" data-confirm="Sign <?= e($u['name']) ?> out of every device?"><?= csrf_field() ?><button class="btn" type="submit">Sign out everywhere</button></form>
        <?php if (!empty($mfa) && (int) $u['id'] !== Auth::id()): ?><form method="post" action="<?= e(url('users/' . $u['id'] . '/reset-2fa')) ?>" data-confirm="Reset 2-step sign-in for <?= e($u['name']) ?>? Do this only if you are sure it is really them (e.g. they lost their phone)."><?= csrf_field() ?><button class="btn btn-danger" type="submit">Reset 2-step sign-in</button></form><?php endif; ?>
    </div>
    <?php if (!empty($sessions)): ?>
    <div class="table-wrap" style="margin-top:.8rem"><table>
        <tr><th>Device</th><th>IP</th><th>Last active</th></tr>
        <?php foreach ($sessions as $s): ?><tr><td><?= e($s['device']) ?><?= (int) $s['mfa'] ? ' ' . badge('2-step', 'good') : '' ?></td><td><small><?= e($s['ip']) ?></small></td><td><small><?= e(date('j M H:i', strtotime($s['last_seen_at'] . ' UTC'))) ?></small></td></tr><?php endforeach; ?>
    </table></div>
    <?php else: ?><p class="hint">Not signed in anywhere right now.</p><?php endif; ?>
</div>
<?php endif; ?>
</div>
