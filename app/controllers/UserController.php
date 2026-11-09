<?php
declare(strict_types=1);

class UserController extends Controller
{
    public function index(): void
    {
        $role = (string) ($_GET['role'] ?? '');
        $q = trim((string) ($_GET['q'] ?? ''));
        $sql = 'SELECT * FROM users WHERE 1=1';
        $p = [];
        if ($role !== '' && Auth::roleRow($role)) {
            $sql .= ' AND role = ?';
            $p[] = $role;
        } else {
            // Parent and pupil accounts are managed under "Parent & pupil logins"
            $sql .= " AND role NOT IN ('parent','pupil')";
        }
        if ($q !== '') {
            $sql .= ' AND (name LIKE ? OR email LIKE ? OR phone LIKE ? OR username LIKE ?)';
            array_push($p, "%$q%", "%$q%", "%$q%", "%$q%");
        }
        $users = db()->all($sql . ' ORDER BY is_active DESC, name LIMIT 2000', $p);
        $pager = ['total' => count($users)];
        $this->view('users/index', ['title' => 'Users & roles', 'users' => $users, 'pager' => $pager, 'role' => $role, 'q' => $q]);
    }

    public function create(): void
    {
        $this->view('users/form', ['title' => 'New user', 'user' => null]);
    }

    public function store(): void
    {
        $in = $this->only(['name', 'email', 'phone', 'role']);
        $in['email'] = strtolower((string) $in['email']);
        $this->guardSuper(null, (string) $in['role']);
        $this->validate($in, $this->rules(0), 'users/create');
        $in += $this->accessUntil();
        // The admin may type the temporary password, or let the system make one
        $temp = $this->chosenTemp($in, 'users/create', $in);
        $id = db()->insert('users', $in + [
            'password_hash'        => password_hash($temp, PASSWORD_DEFAULT),
            'must_change_password' => 1,
        ]);
        audit('user_created', 'users', $id, ['email' => $in['email'], 'role' => $in['role']]);
        flash('success', "Account created. Temporary password for {$in['email']}: $temp — share it privately; they must change it at first sign-in.");
        $this->forgetTyped();
        redirect('users');
    }

    public function edit(int $id): void
    {
        $user = db()->one('SELECT * FROM users WHERE id = ?', [$id]) ?? $this->notFound();
        $this->guardSuper($user);
        $this->view('users/form', ['title' => 'Edit user', 'user' => $user, 'sessions' => Security::sessions($id),
            'mfa' => Security::totpEnabled($user), 'mfaRequired' => Security::mfaRequired($user)]);
    }

    public function update(int $id): void
    {
        $user = db()->one('SELECT * FROM users WHERE id = ?', [$id]) ?? $this->notFound();
        $in = $this->only(['name', 'email', 'phone', 'role']);
        $in['email'] = strtolower((string) $in['email']);
        $in['is_active'] = isset($_POST['is_active']) ? 1 : 0;
        $this->guardSuper($user, (string) $in['role']);
        $this->validate($in, $this->rules($id), "users/$id/edit");
        $in += $this->accessUntil();
        if (Security::ready()) {
            $in['locked_reason'] = $in['is_active'] ? null : (mb_substr(trim((string) ($_POST['locked_reason'] ?? '')), 0, 255) ?: 'Switched off by the administrator');
        }

        if ($id === Auth::id() && ($in['role'] !== $user['role'] || !$in['is_active'])) {
            flash('error', 'You cannot change your own role or switch off your own account.');
            redirect("users/$id/edit");
        }
        if ($user['role'] === 'super_admin' && ($in['role'] !== 'super_admin' || !$in['is_active'])
            && (int) db()->value("SELECT COUNT(*) FROM users WHERE role = 'super_admin' AND is_active = 1") <= 1) {
            flash('error', 'There must be at least one active Super Admin.');
            redirect("users/$id/edit");
        }
        db()->update('users', $in, 'id = ?', [$id]);
        // A new role or a switched-off account takes effect at once: sign the person out everywhere
        if ($in['role'] !== $user['role'] || (!$in['is_active'] && (int) $user['is_active'])) {
            Security::revokeAll($id, $in['is_active'] ? 'role_changed' : 'account_disabled');
        }
        audit('user_updated', 'users', $id, ['from_role' => $user['role']] + $in);
        flash('success', 'User updated.');
        redirect('users');
    }

    public function resetPassword(int $id): void
    {
        $user = db()->one('SELECT * FROM users WHERE id = ?', [$id]) ?? $this->notFound();
        $this->guardSuper($user);
        $back = (string) ($_POST['back'] ?? '') === 'edit' ? "users/$id/edit" : 'users';
        $temp = $this->chosenTemp($user, $back);
        PasswordPolicy::set($id, $temp, true);
        db()->run('DELETE FROM login_attempts WHERE identifier = ?', [$user['email']]);
        Security::revokeAll($id, 'password_reset');
        audit('password_reset', 'users', $id);
        flash('success', "Password reset. Temporary password for {$user['email']}: $temp — share it privately; they must change it at their next sign-in.");
        $this->forgetTyped();
        redirect($back);
    }

    public function signOutEverywhere(int $id): void
    {
        $user = db()->one('SELECT * FROM users WHERE id = ?', [$id]) ?? $this->notFound();
        $this->guardSuper($user);
        $n = Security::revokeAll($id, 'signed_out_by_admin', $id === Auth::id());
        audit('sessions_revoked_by_admin', 'users', $id, ['count' => $n]);
        flash('success', $user['name'] . " was signed out of $n device(s).");
        redirect("users/$id/edit");
    }

    /** The person lost their phone: switch off their 2-step sign-in so they can set it up again. */
    public function reset2fa(int $id): void
    {
        $user = db()->one('SELECT * FROM users WHERE id = ?', [$id]) ?? $this->notFound();
        $this->guardSuper($user);
        if ($id === Auth::id()) {
            flash('error', 'Reset your own 2-step sign-in from My security (or with bin/reset-admin.php on the school computer).');
            redirect("users/$id/edit");
        }
        db()->update('users', ['totp_secret' => null, 'totp_enabled_at' => null, 'totp_last_step' => 0, 'recovery_codes' => null], 'id = ?', [$id]);
        Security::revokeAll($id, '2fa_reset');
        audit('2fa_reset_by_admin', 'users', $id);
        flash('success', '2-step sign-in reset for ' . $user['name'] . '. ' . (Security::mfaRequired($user) ? 'They must set it up again at their next sign-in.' : 'They can set it up again in My security.'));
        redirect("users/$id/edit");
    }

    /** Only a Super Admin may create, change, reset or sign out a Super Admin, or give someone that role. */
    private function guardSuper(?array $user, ?string $newRole = null): void
    {
        if (Auth::role() === 'super_admin') {
            return;
        }
        if (($user['role'] ?? '') === 'super_admin' || $newRole === 'super_admin') {
            flash('error', 'Only a Super Admin can do that for a Super Admin account.');
            redirect('users');
        }
    }

    private function rules(int $id): array
    {
        return [
            'name'  => 'required|max:150',
            'email' => 'required|email|max:190|unique:users,email,' . $id,
            'phone' => 'phone',
            'role'  => 'required|in:' . implode(',', array_keys(Auth::roles())),
        ];
    }

    /**
     * The temporary password for a new account or a reset: the one the admin typed (checked against the
     * password rules), or one made by the system. The person must change it at the next sign-in either way.
     */
    private function chosenTemp(array $person, string $backRoute, array $keep = []): string
    {
        $typed = (string) ($_POST['temp_password'] ?? '');
        if (($_POST['pw_mode'] ?? 'auto') !== 'typed') {
            return $this->tempPassword();
        }
        $errors = $typed === '' ? ['Type the temporary password, or choose "Make one for me".']
            : PasswordPolicy::check($typed, ['name' => $person['name'] ?? '', 'email' => $person['email'] ?? '', 'role' => $person['role'] ?? '']);
        if ($errors) {
            with_old($keep + ['pw_mode' => 'typed'], ['temp_password' => implode(' ', $errors)]);
            flash('error', 'That temporary password cannot be used: ' . implode(' ', $errors));
            redirect($backRoute);
        }
        return $typed;
    }

    /** Never keep a typed password in the session (it is only shown once in the message). */
    private function forgetTyped(): void
    {
        unset($_POST['temp_password']);
    }

    private function tempPassword(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $out = '';
        for ($i = 0; $i < 12; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $out;
    }

    /** "Access ends on" date (auditors, temporary accounts). */
    private function accessUntil(): array
    {
        if (!Auth::hasAccessUntil()) {
            return [];
        }
        $v = trim((string) ($_POST['access_until'] ?? ''));
        return ['access_until' => Validator::isDate($v) ? $v : null];
    }
}
