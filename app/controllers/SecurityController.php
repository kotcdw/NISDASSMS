<?php
declare(strict_types=1);

/**
 * Administration → Roles & security (Super Admin): roles and what each may do (least privilege),
 * custom roles, the permission matrix, sign-in rules, and the access review.
 */
class SecurityController extends Controller
{
    public const TABS = ['security' => 'Roles', 'security/matrix' => 'Permission matrix', 'security/settings' => 'Sign-in rules', 'security/review' => 'Access review'];

    private function ready(): void
    {
        if (!Security::ready()) {
            flash('warning', 'Click "Update database now" first — roles and permissions become editable after the update.');
            redirect('dashboard');
        }
    }

    // ------------------------------------------------------------------ roles

    public function index(): void
    {
        $this->ready();
        $counts = [];
        foreach (db()->all('SELECT role, SUM(is_active = 1) AS active, COUNT(*) AS total, SUM(is_active = 1 AND totp_enabled_at IS NOT NULL) AS mfa FROM users GROUP BY role') as $r) {
            $counts[$r['role']] = $r;
        }
        $this->view('security/index', ['title' => 'Roles & security', 'roles' => Auth::roleRows(), 'grants' => Auth::grants(), 'counts' => $counts]);
    }

    public function role(): void
    {
        $this->ready();
        $key = (string) ($_GET['key'] ?? '');
        $role = $key !== '' ? (Auth::roleRow($key) ?? $this->notFound()) : null;
        $this->view('security/role', [
            'title' => $role ? 'Role: ' . $role['name'] : 'New role', 'role' => $role,
            'perms' => $role ? (Auth::grants()[$key] ?? []) : [],
            'users' => $role ? db()->all('SELECT id, name, email, is_active, last_login_at, totp_enabled_at FROM users WHERE role = ? ORDER BY is_active DESC, name LIMIT 200', [$key]) : [],
            'bases' => array_filter(Auth::roles('staff'), fn($n, $k) => (int) (Auth::roleRow($k)['is_system'] ?? 0) === 1 && !in_array($k, ['super_admin', Permissions::ADMIN_ROLE], true), ARRAY_FILTER_USE_BOTH),
        ]);
    }

    public function saveRole(): void
    {
        $this->ready();
        $key = (string) ($_POST['key'] ?? '');
        $existing = $key !== '' ? Auth::roleRow($key) : null;
        if ($key !== '' && !$existing) {
            $this->notFound();
        }
        $back = $existing ? 'security/role?key=' . rawurlencode($key) : 'security/role';
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 80);
        $errors = [];
        if ($name === '') {
            $errors[] = 'Give the role a name.';
        } elseif (db()->value('SELECT 1 FROM roles WHERE LOWER(name) = ? AND role_key <> ?', [mb_strtolower($name), $key])) {
            $errors[] = 'Another role already has that name.';
        }
        $isSuper = $key === 'super_admin';
        if ($isSuper && Auth::role() !== 'super_admin') {
            $errors[] = 'Only a Super Admin can change the Super Admin role.';
        }
        $kind = $existing['kind'] ?? 'staff';
        $base = $existing ? ($existing['base_role'] ?: $key) : (string) ($_POST['base_role'] ?? '');
        if (!$existing && (!Auth::roleRow($base) || (int) Auth::roleRow($base)['is_system'] !== 1 || Auth::roleRow($base)['kind'] !== 'staff' || in_array($base, ['super_admin', Permissions::ADMIN_ROLE], true))) {
            $errors[] = 'Choose which built-in role this new role works like.';
        }
        $scope = ($_POST['data_scope'] ?? 'all') === 'own' ? 'own' : 'all';
        $idle = (int) ($_POST['idle_minutes'] ?? 30);
        if ($idle < 5 || $idle > 480) {
            $errors[] = 'Automatic sign-out must be between 5 and 480 minutes.';
        }
        if ($isSuper && $idle > 30) {
            $errors[] = 'Super Admin sessions must sign out after 30 minutes of inactivity or less.';
        }
        $days = array_values(array_intersect(array_map('strval', (array) ($_POST['days'] ?? [])), ['1', '2', '3', '4', '5', '6', '7']));
        $from = preg_match('/^\d{2}:\d{2}$/', (string) ($_POST['allowed_from'] ?? '')) ? $_POST['allowed_from'] . ':00' : null;
        $to = preg_match('/^\d{2}:\d{2}$/', (string) ($_POST['allowed_to'] ?? '')) ? $_POST['allowed_to'] . ':00' : null;
        if (($from === null) !== ($to === null)) {
            $errors[] = 'Give both a start and an end time, or leave both empty.';
        }
        $nets = trim((string) ($_POST['allowed_networks'] ?? ''));
        if ($nets !== '' && !Security::validNetworkList($nets)) {
            $errors[] = 'Networks must be IP addresses or ranges like 192.168.1.0/24, separated by commas.';
        }
        [$perms, $conflicts] = Permissions::sanitise((array) ($_POST['perms'] ?? []), $kind, $key);
        $errors = array_merge($errors, $conflicts);
        $active = $isSuper ? 1 : (!empty($_POST['is_active']) ? 1 : 0);
        if ($existing && !$active && (int) db()->value('SELECT COUNT(*) FROM users WHERE role = ? AND is_active = 1', [$key])) {
            $errors[] = 'Move the people with this role to another role (Users) before switching the role off.';
        }
        if ($errors) {
            with_old($_POST);
            flash('error', implode(' ', $errors));
            redirect($back);
        }
        $data = [
            'name' => $name, 'description' => mb_substr(trim((string) ($_POST['description'] ?? '')), 0, 255) ?: null,
            'data_scope' => $isSuper ? 'all' : $scope, 'require_2fa' => $isSuper ? 1 : (!empty($_POST['require_2fa']) ? 1 : 0),
            'idle_minutes' => $idle, 'allowed_days' => $isSuper || !$days || count($days) === 7 ? null : implode(',', $days),
            'allowed_from' => $isSuper ? null : $from, 'allowed_to' => $isSuper ? null : $to,
            'allowed_networks' => $isSuper || $nets === '' ? null : mb_substr($nets, 0, 500), 'is_active' => $active, 'updated_by' => Auth::id(),
        ];
        $before = $existing ? (Auth::grants()[$key] ?? []) : [];
        $key = db()->transaction(function (Database $d) use ($existing, $key, $data, $base, $perms, $isSuper, $name) {
            if (!$existing) {
                $slug = 'c_' . trim(preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($name)), '_');
                $slug = substr($slug ?: 'c_role', 0, 34);
                $key = $slug;
                for ($i = 2; $d->value('SELECT 1 FROM roles WHERE role_key = ?', [$key]); $i++) {
                    $key = $slug . '_' . $i;
                }
                $d->insert('roles', $data + ['role_key' => $key, 'base_role' => $base, 'kind' => 'staff', 'is_system' => 0, 'sort_order' => 60]);
            } else {
                $d->update('roles', $data, 'role_key = ?', [$key]);
            }
            if (!$isSuper) {
                $this->writePerms($d, $key, $perms);
            }
            return $key;
        });
        $newly2fa = $data['require_2fa'] && !($existing && (int) $existing['require_2fa']);
        if ($newly2fa || !$active) {
            // People in this role sign in again (to set up 2-step sign-in, or because the role is off)
            foreach (db()->all('SELECT id FROM users WHERE role = ?', [$key]) as $u) {
                if ((int) $u['id'] !== Auth::id()) {
                    Security::revokeAll((int) $u['id'], 'role_changed');
                }
            }
        }
        Auth::flushCache();
        audit($existing ? 'role_updated' : 'role_created', 'roles', null, [
            'role' => $key, 'added' => array_values(array_diff($perms, $before)), 'removed' => array_values(array_diff($before, $perms)),
            'settings' => array_diff_key($data, ['updated_by' => 1]),
        ]);
        $warn = Permissions::warnings($perms);
        flash('success', 'Role "' . $name . '" saved.' . ($warn ? ' Please review: ' . implode(' ', $warn) : ''));
        redirect('security/role?key=' . rawurlencode($key));
    }

    public function resetRole(): void
    {
        $this->ready();
        $key = (string) ($_POST['key'] ?? '');
        $role = Auth::roleRow($key) ?? $this->notFound();
        if (!(int) $role['is_system'] || $key === 'super_admin') {
            redirect('security');
        }
        $defaults = [];
        foreach (Auth::DEFAULT_PERMISSIONS as $perm => $roles) {
            if (in_array($key, $roles, true)) {
                $defaults[] = $perm;
            }
        }
        [$perms] = Permissions::sanitise($defaults, $role['kind'], $key);
        $before = Auth::grants()[$key] ?? [];
        db()->transaction(fn(Database $d) => $this->writePerms($d, $key, $perms));
        Auth::flushCache();
        audit('role_reset', 'roles', null, ['role' => $key, 'added' => array_values(array_diff($perms, $before)), 'removed' => array_values(array_diff($before, $perms))]);
        flash('success', 'Permissions for "' . $role['name'] . '" are back to the recommended set.');
        redirect('security/role?key=' . rawurlencode($key));
    }

    public function deleteRole(): void
    {
        $this->ready();
        $key = (string) ($_POST['key'] ?? '');
        $role = Auth::roleRow($key) ?? $this->notFound();
        if ((int) $role['is_system']) {
            flash('error', 'Built-in roles cannot be deleted.');
            redirect('security');
        }
        if (db()->value('SELECT 1 FROM users WHERE role = ? LIMIT 1', [$key])) {
            flash('error', 'Some accounts still have this role. Give them another role first (Users).');
            redirect('security/role?key=' . rawurlencode($key));
        }
        db()->run('DELETE FROM roles WHERE role_key = ?', [$key]);
        Auth::flushCache();
        audit('role_deleted', 'roles', null, ['role' => $key, 'name' => $role['name']]);
        flash('success', 'Role "' . $role['name'] . '" deleted.');
        redirect('security');
    }

    // ------------------------------------------------------------------ matrix

    public function matrix(): void
    {
        $this->ready();
        $this->view('security/matrix', ['title' => 'Permission matrix', 'roles' => Auth::roleRows(), 'grants' => Auth::grants()]);
    }

    public function saveMatrix(): void
    {
        $this->ready();
        $posted = (array) ($_POST['perm'] ?? []);
        $changes = [];
        $errors = [];
        foreach (Auth::roleRows() as $key => $role) {
            if ($key === 'super_admin') {
                continue;
            }
            [$perms, $conflicts] = Permissions::sanitise((array) ($posted[$key] ?? []), $role['kind'], $key);
            foreach ($conflicts as $c) {
                $errors[] = $role['name'] . ': ' . $c;
            }
            $before = Auth::grants()[$key] ?? [];
            sort($before);
            if ($perms !== $before) {
                $changes[$key] = [$perms, $before];
            }
        }
        if ($errors) {
            flash('error', 'Nothing was saved. ' . implode(' ', $errors));
            redirect('security/matrix');
        }
        if (!$changes) {
            flash('info', 'No changes.');
            redirect('security/matrix');
        }
        db()->transaction(function (Database $d) use ($changes) {
            foreach ($changes as $key => [$perms]) {
                $this->writePerms($d, $key, $perms);
            }
        });
        foreach ($changes as $key => [$perms, $before]) {
            audit('role_permissions_changed', 'roles', null, ['role' => $key, 'added' => array_values(array_diff($perms, $before)), 'removed' => array_values(array_diff($before, $perms))]);
        }
        Auth::flushCache();
        flash('success', 'Permissions saved for ' . count($changes) . ' role(s). They apply from each person\'s next page.');
        redirect('security/matrix');
    }

    private function writePerms(Database $d, string $key, array $perms): void
    {
        $d->run('DELETE FROM role_permissions WHERE role_key = ?', [$key]);
        foreach ($perms as $p) {
            $d->insert('role_permissions', ['role_key' => $key, 'permission' => $p]);
        }
    }

    // ------------------------------------------------------------------ sign-in rules

    public const SETTINGS = [
        'pw_min_length'          => [8, 64],
        'pw_portal_min_length'   => [6, 64],
        'pw_history'             => [0, 24],
        'pw_expiry_days'         => [0, 730],
        'login_max_tries'        => [3, 20],
        'login_lock_mins'        => [5, 1440],
        'session_absolute_hours' => [1, 24],
        'dormant_days'           => [0, 730],
        'step_up_minutes'        => [1, 60],
    ];
    public const SWITCHES = ['mfa_enforced', 'pw_require_mix', 'single_session', 'login_alerts', 'login_alert_sms'];

    public function settings(): void
    {
        $this->ready();
        $this->view('security/settings', ['title' => 'Sign-in rules']);
    }

    public function saveSettings(): void
    {
        $this->ready();
        $changed = [];
        foreach (self::SETTINGS as $k => [$min, $max]) {
            $v = (int) ($_POST[$k] ?? -1);
            if ($k === 'dormant_days' && $v > 0 && $v < 30) {
                $v = 30;
            }
            if ($v < $min || $v > $max) {
                flash('error', 'A value is out of range: ' . str_replace('_', ' ', $k) . " must be between $min and $max.");
                redirect('security/settings');
            }
            if ((string) $v !== setting($k, '')) {
                $changed[$k] = $v;
            }
            setting_set($k, (string) $v);
        }
        foreach (self::SWITCHES as $k) {
            $v = !empty($_POST[$k]) ? '1' : '0';
            if ($v !== setting($k, '')) {
                $changed[$k] = $v;
            }
            setting_set($k, $v);
        }
        audit('security_settings_changed', 'settings', null, $changed);
        flash('success', 'Sign-in rules saved.');
        redirect('security/settings');
    }

    // ------------------------------------------------------------------ access review

    public function review(): void
    {
        $this->ready();
        $roles = Auth::roleRows();
        $users = db()->all("SELECT id, name, email, username, role, is_active, must_change_password, last_login_at, created_at, totp_enabled_at, locked_reason FROM users WHERE role NOT IN ('parent','pupil') ORDER BY role, name");
        $highRoles = [];
        foreach (Auth::grants() as $k => $perms) {
            foreach ($perms as $p) {
                if ((Permissions::all()[$p][3] ?? '') === 'high') {
                    $highRoles[$k] = true;
                }
            }
        }
        $highRoles['super_admin'] = true;
        $days = fn($u) => $u['last_login_at'] ? (int) floor((time() - strtotime($u['last_login_at'] . ' UTC')) / 86400) : null;
        $findings = ['no2fa_required' => [], 'no2fa_high' => [], 'dormant' => [], 'never' => [], 'temp' => []];
        foreach ($users as $u) {
            if (!(int) $u['is_active']) {
                continue;
            }
            $req = (int) ($roles[$u['role']]['require_2fa'] ?? 0);
            if ($req && !$u['totp_enabled_at']) {
                $findings['no2fa_required'][] = $u;
            } elseif (!$u['totp_enabled_at'] && isset($highRoles[$u['role']])) {
                $findings['no2fa_high'][] = $u;
            }
            $d = $days($u);
            if ($d === null) {
                $findings['never'][] = $u;
            } elseif ($d >= 60) {
                $findings['dormant'][] = $u + ['days' => $d];
            }
            if ((int) $u['must_change_password']) {
                $findings['temp'][] = $u;
            }
        }
        $warnings = [];
        foreach (Auth::grants() as $k => $perms) {
            foreach (Permissions::warnings($perms) as $w) {
                $warnings[] = [$roles[$k]['name'] ?? $k, $w];
            }
        }
        if (isset($_GET['export'])) {
            $this->exportUsers($users, $roles);
        }
        $events = db()->all(
            "SELECT a.*, u.name AS user_name FROM audit_log a LEFT JOIN users u ON u.id = a.user_id
             WHERE a.action IN ('role_created','role_updated','role_deleted','role_reset','role_permissions_changed','security_settings_changed','login_refused','access_denied',
                                '2fa_enabled','2fa_disabled','2fa_reset_by_admin','login_2fa_failed','step_up_failed','sessions_revoked_by_admin','account_dormant_disabled','user_updated','login_new_device')
             ORDER BY a.id DESC LIMIT 60"
        );
        $this->view('security/review', ['title' => 'Access review', 'roles' => $roles, 'users' => $users, 'findings' => $findings, 'warnings' => $warnings, 'events' => $events, 'highRoles' => $highRoles]);
    }

    private function exportUsers(array $users, array $roles): void
    {
        $out = csv_start('access-review-' . date('Y-m-d') . '.csv', ['Name', 'Sign-in', 'Role', 'Active', '2-step sign-in', 'Last sign-in (UTC)', 'Temporary password', 'Permissions']);
        foreach ($users as $u) {
            $perms = $u['role'] === 'super_admin' ? 'ALL' : implode('; ', Auth::grants()[$u['role']] ?? []);
            csv_row($out, [$u['name'], $u['email'] ?: $u['username'], $roles[$u['role']]['name'] ?? $u['role'], (int) $u['is_active'] ? 'Yes' : 'No',
                $u['totp_enabled_at'] ? 'On' : 'Off', $u['last_login_at'] ?? 'Never', (int) $u['must_change_password'] ? 'Yes' : 'No', $perms]);
        }
        audit('access_review_exported', 'users', null, ['rows' => count($users)]);
        csv_end($out);
    }
}
