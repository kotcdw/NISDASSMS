<div class="xgrid-box" data-xgrid="users" data-xgrid-file="staff-users">
<div class="page-head">
    <div><h1>Users</h1></div>
    <div class="actions">
        <form method="get" action="<?= e(url('users')) ?>" class="list-pick" data-autosubmit>
            <select name="role" aria-label="Role"><option value="">All staff roles</option><?php foreach (Auth::roles() as $k => $label): ?><option value="<?= e($k) ?>"<?= selected($k, $role) ?>><?= e($label) ?></option><?php endforeach; ?></select>
        </form>
        <?= export_grid_menu() ?>
        <a class="btn btn-primary" href="<?= e(url('users/create')) ?>">+ New user</a>
    </div>
</div>
<div class="xg-scroll"><table class="xgrid">
    <thead><tr><th class="xg-n" scope="col">#</th><?= list_th(['name' => ['Name'], 'email' => ['Email'], 'phone' => ['Phone'], 'role' => ['Role'], 'status' => ['Status'], 'twostep' => ['2-step sign-in'], 'last' => ['Last sign-in', 'date']]) ?><th class="xg-act"></th></tr></thead>
    <tbody>
    <?php $iAmSuper = Auth::role() === 'super_admin'; foreach ($users as $i => $u): $edit = e(url('users/' . $u['id'] . '/edit')); $mine = $iAmSuper || $u['role'] !== 'super_admin'; ?>
    <tr>
        <td class="xg-n"><?= $i + 1 ?></td>
        <td data-c="name"><?php if ($mine): ?><a href="<?= $edit ?>"><strong><?= e($u['name']) ?></strong></a><?php else: ?><strong><?= e($u['name']) ?></strong><?php endif; ?></td>
        <td data-c="email"><?= e($u['email']) ?></td>
        <td data-c="phone"><?= e($u['phone']) ?></td>
        <td data-c="role"><?= e(Auth::roleLabel($u['role'])) ?></td>
        <td data-c="status"><?= $u['is_active'] ? badge('Active', 'good') : badge('Disabled', 'bad') ?><?= $u['must_change_password'] ? '<span hidden> · </span> ' . badge('Temp password', 'warn') : '' ?></td>
        <td data-c="twostep"><?= !empty($u['totp_enabled_at']) ? badge('On', 'good') : '' ?></td>
        <td data-c="last" data-s="<?= e((string) $u['last_login_at']) ?>"><?= e(fmt_date($u['last_login_at'], 'd M Y H:i')) ?></td>
        <td class="xg-act"><?php if ($mine): ?>
            <details class="rowmenu"><summary aria-label="Actions for <?= e($u['name']) ?>">⋯</summary><div>
                <a href="<?= $edit ?>">Edit</a>
                <form method="post" action="<?= e(url('users/' . $u['id'] . '/reset')) ?>" data-confirm="Reset the password for <?= e($u['name']) ?>?" data-confirm-title="Reset the password for <?= e($u['name']) ?>?" data-confirm-detail="A temporary password will be shown to you once. <?= e($u['name']) ?> will be signed out of every device and must choose a new password at the next sign-in." data-confirm-ok="Reset password" data-confirm-tone="warning">
                    <?= csrf_field() ?><button type="submit">Reset password</button>
                </form>
                <form method="post" action="<?= e(url('users/' . $u['id'] . '/signout')) ?>" data-confirm="Sign <?= e($u['name']) ?> out of every device?" data-confirm-ok="Sign out everywhere"><?= csrf_field() ?><button type="submit">Sign out everywhere</button></form>
            </div></details><?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="xg-empty muted"<?= $users ? ' hidden' : '' ?> data-xg-empty>No users found.</p>
</div>
</div>
