<?php
$isNew = !$role;
$key = $role['role_key'] ?? '';
$isSuper = $key === 'super_admin';
$kind = $role['kind'] ?? 'staff';
$v = fn($k, $d = '') => old($k, $role[$k] ?? $d);
$checkedPerms = old('perms', $perms);
$days = old('days', array_filter(explode(',', (string) ($role['allowed_days'] ?? ''))));
?>
<div class="page-head">
    <div><p class="breadcrumb"><a href="<?= e(url('security')) ?>">Roles &amp; security</a></p><h1><?= $isNew ? 'New role' : e($role['name']) ?></h1>
    </div>
    <?php if (!$isNew && (int) $role['is_system'] && !$isSuper): ?><div class="actions">
        <form method="post" action="<?= e(url('security/role/reset')) ?>" data-confirm="Put this role's permissions back to the recommended set?"><?= csrf_field() ?><input type="hidden" name="key" value="<?= e($key) ?>"><button class="btn" type="submit">Reset to recommended</button></form>
    </div><?php endif; ?>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>
<form method="post" action="<?= e(url('security/role')) ?>" class="form">
    <?= csrf_field() ?><input type="hidden" name="key" value="<?= e($key) ?>">
    <div class="cols">
        <div class="card">
            <h2>Role</h2>
            <label class="required">Name <input name="name" required maxlength="80" value="<?= e($v('name')) ?>" placeholder="e.g. Deputy Head, Librarian, School Nurse"></label>
            <label>What this role is for <input name="description" maxlength="255" value="<?= e($v('description')) ?>"></label>
            <?php if ($isNew): ?>
            <label class="required">Works like (for teacher lists, class scope…)
                <select name="base_role" required><option value="">— choose —</option><?php foreach ($bases as $k => $n): ?><option value="<?= e($k) ?>"<?= selected($k, (string) old('base_role')) ?>><?= e($n) ?></option><?php endforeach; ?></select></label>
            <?php endif; ?>
            <?php if ($kind === 'staff' && !$isSuper): ?>
            <label>Student records this role can see
                <select name="data_scope"><option value="all">All classes</option><option value="own"<?= selected('own', (string) $v('data_scope', 'all')) ?>>Only classes they teach or are class teacher of</option></select></label>
            <?php endif; ?>
            <?php if (!$isSuper && !$isNew): ?><label style="font-weight:400"><input type="checkbox" name="is_active" value="1"<?= checked((int) $v('is_active', 1) === 1) ?>> Role switched on (off = nobody with this role can sign in)</label><?php elseif ($isNew): ?><input type="hidden" name="is_active" value="1"><?php endif; ?>
        </div>
        <div class="card">
            <h2>Sign-in rules for this role</h2>
            <label style="font-weight:400"><input type="checkbox" name="require_2fa" value="1"<?= checked($isSuper || (int) $v('require_2fa', 0) === 1) ?><?= $isSuper ? ' disabled' : '' ?>> Require 2-step sign-in (authenticator app)<?= $isSuper ? ' — always on for Super Admin' : '' ?></label>
            <label>Sign out automatically after (minutes without activity) <input type="number" name="idle_minutes" min="5" max="<?= $isSuper ? 30 : 480 ?>" required value="<?= (int) $v('idle_minutes', 30) ?>" style="max-width:120px"></label>
            <?php if (!$isSuper): ?>
            <fieldset><legend>Allowed days and hours (empty = any time)</legend>
                <div style="display:flex;gap:.6rem;flex-wrap:wrap"><?php foreach (Security::DAYS as $n => $d): ?><label style="font-weight:400"><input type="checkbox" name="days[]" value="<?= $n ?>"<?= checked(in_array((string) $n, array_map('strval', (array) $days), true)) ?>> <?= e($d) ?></label><?php endforeach; ?></div>
                <div class="grid-2"><label>From <input type="time" name="allowed_from" value="<?= e(substr((string) $v('allowed_from'), 0, 5)) ?>"></label><label>To <input type="time" name="allowed_to" value="<?= e(substr((string) $v('allowed_to'), 0, 5)) ?>"></label></div>
            </fieldset>
            <label>Only from these networks (empty = anywhere) <input name="allowed_networks" maxlength="500" value="<?= e($v('allowed_networks')) ?>" placeholder="e.g. 192.168.1.0/24, 41.66.10.5"></label>
            <?php else: ?><?php endif; ?>
        </div>
    </div>
    <h2>Permissions</h2>
        <div class="grid-2">
    <?php foreach (Permissions::CATALOG as $module => $list): ?>
        <div class="card" style="margin:0">
            <h3 style="margin:0 0 .5rem"><?= e($module) ?></h3>
            <?php foreach ($list as $p => [$label, $desc, $risk]): $lock = $isSuper ? 'Always' : Permissions::lockReason($p, $kind, $key); $on = $isSuper || in_array($p, (array) $checkedPerms, true) || ($p === 'staff' && $kind === 'staff'); ?>
            <label style="font-weight:400;display:flex;gap:.5rem;align-items:flex-start;margin:.25rem 0<?= $lock ? ';opacity:.6' : '' ?>">
                <input type="checkbox" name="perms[]" value="<?= e($p) ?>"<?= checked($on && ($isSuper || !$lock || $p === 'staff')) ?><?= $lock || ($p === 'staff' && $kind === 'staff') ? ' disabled' : '' ?>>
                <span><strong><?= e($label) ?></strong><?= $risk === 'high' ? ' ' . badge('Sensitive', 'warn') : '' ?><?= $lock && !$isSuper ? ' <small class="muted">(' . e($lock) . ')</small>' : '' ?><br><small class="muted"><?= e($desc) ?></small></span>
            </label>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
    </div>
    <p style="margin-top:1rem"><button class="btn btn-primary" type="submit"><?= $isNew ? 'Create role' : 'Save role' ?></button> <a class="btn btn-ghost" href="<?= e(url('security')) ?>">Cancel</a></p>
</form>
<?php if (!$isNew): ?>
<div class="card" style="padding:0">
    <h2 style="padding:1rem 1rem 0">People with this role</h2>
    <div class="table-wrap"><table>
        <tr><th>Name</th><th>Sign-in</th><th>Status</th><th>2-step</th><th>Last sign-in</th></tr>
        <?php foreach ($users as $u): ?><tr<?= (int) $u['is_active'] ? '' : ' class="muted"' ?>><td><?= e($u['name']) ?></td><td><?= e($u['email']) ?></td><td><?= (int) $u['is_active'] ? badge('Active', 'good') : badge('Off', 'bad') ?></td><td><?= $u['totp_enabled_at'] ? badge('On', 'good') : '—' ?></td><td><small><?= e(fmt_date($u['last_login_at'], 'd M Y H:i')) ?></small></td></tr><?php endforeach; ?>
        <?php if (!$users): ?><tr><td colspan="5" class="muted">Nobody yet.</td></tr><?php endif; ?>
    </table></div>
</div>
<?php if (!(int) $role['is_system']): ?>
<form method="post" action="<?= e(url('security/role/delete')) ?>" class="card" data-confirm="Delete the role &quot;<?= e($role['name']) ?>&quot;?"><?= csrf_field() ?><input type="hidden" name="key" value="<?= e($key) ?>">
    <button class="btn btn-danger" type="submit">Delete this role</button> <small class="muted">Only possible when nobody has it.</small></form>
<?php endif; ?>
<?php endif; ?>
