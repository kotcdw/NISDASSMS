<?php
declare(strict_types=1);

final class Auth
{
    public const ROLES = [
        'super_admin'          => 'Super Admin',
        'administrator'        => 'Administrator',
        'head_teacher'         => 'Head Teacher',
        'academic_coordinator' => 'Academic Coordinator',
        'class_teacher'        => 'Class Teacher',
        'subject_teacher'      => 'Subject Teacher',
        'bursar'               => 'Bursar / Accountant',
        'admissions_officer'   => 'Admissions Officer',
        'security'             => 'Security',
        'parent'               => 'Parent / Guardian',
        'pupil'                => 'Student',
    ];

    private const STAFF = ['super_admin', 'administrator', 'head_teacher', 'academic_coordinator', 'class_teacher', 'subject_teacher', 'bursar', 'admissions_officer'];

    /**
     * Recommended (factory) permissions per role. Since Sprint 13 the live rules are in the database
     * (roles + role_permissions, editable in Administration → Roles & permissions); this list seeds them,
     * is used by "Reset to recommended", and keeps the system working until the database is updated.
     * Super Admin is always allowed.
     */
    public const DEFAULT_PERMISSIONS = [
        'staff'            => self::STAFF,
        'setup.manage'     => ['administrator'],
        'users.manage'     => ['administrator'],
        'teachers.profile.manage' => ['administrator'],
        'calendar.view'    => ['administrator', 'head_teacher', 'academic_coordinator', 'class_teacher', 'subject_teacher', 'bursar', 'admissions_officer', 'parent', 'pupil'],
        'calendar.manage'  => ['head_teacher', 'academic_coordinator', 'class_teacher', 'subject_teacher', 'administrator'],
        'admission.view'   => ['head_teacher', 'admissions_officer', 'administrator'],
        'admission.manage' => ['head_teacher', 'admissions_officer', 'administrator'],
        'admission.enrol'  => ['head_teacher', 'admissions_officer', 'administrator'],
        'pupils.view'      => ['head_teacher', 'academic_coordinator', 'class_teacher', 'subject_teacher', 'bursar', 'admissions_officer', 'administrator'],
        'pupils.manage'    => ['head_teacher', 'admissions_officer', 'class_teacher', 'subject_teacher', 'administrator'],
        'audit.view'       => ['administrator'],
        'attendance.take'  => ['head_teacher', 'academic_coordinator', 'class_teacher', 'administrator'],
        'attendance.all'   => ['head_teacher', 'academic_coordinator', 'administrator'],
        'attendance.view'  => ['head_teacher', 'academic_coordinator', 'class_teacher', 'administrator'],
        'staff_att.view'   => ['head_teacher'],
        'messages.send'    => ['head_teacher', 'academic_coordinator', 'administrator'],
        'messages.manage'  => ['head_teacher'],
        'forms.manage'     => ['head_teacher', 'academic_coordinator', 'admissions_officer', 'administrator'],
        'exams.enter'      => ['head_teacher', 'academic_coordinator', 'class_teacher', 'subject_teacher', 'administrator'],
        'exams.manage'     => ['head_teacher', 'academic_coordinator', 'administrator'],
        'reports.view'     => ['head_teacher', 'academic_coordinator', 'class_teacher', 'administrator'],
        'reports.head'     => ['head_teacher'],
        'timetable.view'   => ['head_teacher', 'academic_coordinator', 'class_teacher', 'subject_teacher', 'administrator'],
        'timetable.manage' => ['head_teacher', 'academic_coordinator', 'administrator'],
        'fees.view'        => ['head_teacher', 'bursar', 'admissions_officer'],
        'fees.manage'      => ['head_teacher', 'bursar'],
        'fees.reverse'     => ['head_teacher'],
        'finance.view'     => ['head_teacher', 'bursar'],
        'finance.manage'   => ['head_teacher', 'bursar'],
        'finance.void'     => ['head_teacher'],
        'finance.budget'   => ['head_teacher'],
        'fees.approve'     => ['head_teacher'],
        'finance.close'    => ['bursar'],
        'finance.reopen'   => ['head_teacher'],
        'assets.manage'    => ['head_teacher', 'bursar'],
        // Requisitions: any staff member can request; the Head approves; above the threshold the
        // School Manager (Super Admin) gives a second approval; the Bursar pays out.
        'requisitions.create'   => self::STAFF,
        'requisitions.view_all' => ['head_teacher', 'bursar'],
        'requisitions.approve'  => ['head_teacher'],
        'requisitions.approve2' => [],
        'requisitions.pay'      => ['bursar'],
        // Sprint 7
        'portal.parent'    => ['parent'],
        'chat.use'         => ['head_teacher', 'academic_coordinator', 'class_teacher', 'subject_teacher', 'bursar', 'admissions_officer', 'parent', 'pupil'],
        'chat.oversee'     => ['head_teacher'],
        'notices.view'     => ['administrator', 'head_teacher', 'academic_coordinator', 'class_teacher', 'subject_teacher', 'bursar', 'admissions_officer', 'parent', 'pupil'],
        'notices.manage'   => ['head_teacher', 'academic_coordinator', 'class_teacher', 'subject_teacher', 'administrator'],
        'logins.manage'    => ['administrator'],
        'quiz.manage'      => ['head_teacher', 'academic_coordinator', 'class_teacher', 'subject_teacher'],
        'quiz.take'        => ['pupil'],
        'promotion.manage' => ['administrator', 'head_teacher'],
        // Sprint 8
        'golive.manage'    => ['administrator', 'head_teacher'],
        'backups.manage'   => ['administrator'],
        'imports.manage'   => ['administrator', 'admissions_officer', 'bursar'],
        // Sprint 9: payroll (Bursar prepares and pays, Head approves), library, transport, health, BECE
        'payroll.view'     => ['head_teacher', 'bursar'],
        'payroll.manage'   => ['bursar'],
        'payroll.approve'  => ['head_teacher'],
        'payslips.own'     => self::STAFF,
        'library.view'     => ['head_teacher', 'class_teacher', 'subject_teacher'],
        'library.manage'   => ['head_teacher'],
        'transport.view'   => ['head_teacher', 'bursar', 'parent', 'pupil', 'administrator'],
        'transport.manage' => ['head_teacher', 'bursar', 'administrator'],
        'health.view'      => ['head_teacher', 'class_teacher'],
        'health.manage'    => ['head_teacher', 'class_teacher'],
        'bece.view'        => ['head_teacher', 'academic_coordinator', 'class_teacher'],
        'bece.manage'      => ['head_teacher', 'academic_coordinator'],
        // Sprint 10
        'analytics.view'   => ['head_teacher', 'academic_coordinator', 'bursar'],
        // Sprint 13: least privilege
        'pupils.sensitive' => ['head_teacher', 'academic_coordinator', 'class_teacher', 'subject_teacher', 'admissions_officer'],
        'security.manage'  => ['administrator'],
        // Sprint 16: teachers, curriculum & classrooms
        'teachers.view'    => ['head_teacher', 'academic_coordinator', 'administrator'],
        'appraisal.manage' => ['head_teacher'],
        'curriculum.manage'=> ['head_teacher', 'academic_coordinator'],
        'scheme.plan'      => ['head_teacher', 'academic_coordinator', 'class_teacher', 'subject_teacher'],
        'lessonnotes.vet'  => ['head_teacher', 'academic_coordinator'],
        'seating.manage'   => ['head_teacher', 'academic_coordinator', 'class_teacher'],
        'resources.view'   => ['head_teacher', 'academic_coordinator', 'class_teacher', 'subject_teacher'],
        'resources.manage' => ['head_teacher', 'academic_coordinator'],
        'examtt.manage'    => ['head_teacher', 'academic_coordinator'],
        // Sprint 17: assignments, pupil leave, staff timesheets
        'assignments.manage' => ['head_teacher', 'academic_coordinator', 'class_teacher', 'subject_teacher'],
        'assignments.submit' => ['pupil'],
        'leave.approve'    => ['head_teacher', 'academic_coordinator', 'class_teacher'],
        'timesheets.manage'=> ['head_teacher'],
        // Sprint 18: canteen, events, appointments
        'canteen.sell'     => ['head_teacher', 'bursar'],
        'canteen.manage'   => ['head_teacher', 'bursar'],
        'events.manage'    => ['head_teacher', 'academic_coordinator', 'administrator'],
        'appointments.offer' => ['head_teacher', 'academic_coordinator', 'class_teacher', 'subject_teacher', 'bursar', 'admissions_officer'],
        'appointments.manage' => ['head_teacher'],
        'appointments.book' => ['parent'],
        // NISDAS APPS: documents, online learning, live classes, CRM, marketing, shop
        'documents.view'   => self::STAFF,
        'documents.manage' => ['head_teacher', 'admissions_officer'],
        'documents.staff'  => ['head_teacher', 'bursar'],
        'lms.manage'       => ['head_teacher', 'academic_coordinator', 'class_teacher', 'subject_teacher'],
        'lms.view'         => ['pupil'],
        'live.manage'      => ['head_teacher', 'academic_coordinator', 'class_teacher', 'subject_teacher'],
        'live.join'        => ['pupil'],
        'crm.manage'       => ['head_teacher', 'admissions_officer'],
        'governance.view'  => ['administrator'],
        'welfare.record'   => ['head_teacher', 'academic_coordinator', 'class_teacher', 'subject_teacher'],
        'welfare.manage'   => ['head_teacher', 'academic_coordinator'],
        'welfare.safeguarding' => ['head_teacher'],
        'governance.manage' => ['administrator'],
        'marketing.send'   => ['head_teacher', 'admissions_officer'],
        'shop.manage'      => ['head_teacher', 'bursar'],
        'shop.order'       => ['parent'],
        // GES school records (migration 039)
        'logbook.manage'   => ['head_teacher'],
        'visitors.record'  => ['head_teacher', 'administrator', 'admissions_officer', 'security'],
        'pickup.gate'      => ['head_teacher', 'security'],
        'pickup.manage'    => ['head_teacher', 'administrator', 'admissions_officer'],
        'movement.view'    => ['head_teacher'],
        'staffleave.approve' => ['head_teacher'],
        'cpd.view'         => ['head_teacher', 'academic_coordinator'],
        'stores.view'      => ['head_teacher', 'bursar', 'storekeeper', 'auditor'],
        'stores.manage'    => ['bursar', 'storekeeper'],
        'permits.view'     => ['head_teacher', 'administrator', 'bursar'],
        'permits.manage'   => ['head_teacher', 'administrator'],
    ];

    private static ?array $user = null;
    private static ?array $roleRows = null;
    private static ?array $grants = null;
    private static $scope = false;
    private static bool $ending = false;

    /**
     * Check the password (and lock-outs, account and role restrictions).
     * Returns [ok, error, next]: next = 'mfa' when the 6-digit code is still needed.
     */
    public static function attempt(string $email, string $password): array
    {
        $email = strtolower(trim($email));
        $ip = Security::clientIp();
        $max = max(3, (int) setting('login_max_tries', (string) config('login_max_tries', 5)));
        $mins = max(5, (int) setting('login_lock_mins', (string) config('login_lock_mins', 15)));

        $recent = (int) db()->value(
            'SELECT COUNT(*) FROM login_attempts WHERE (identifier = ? OR ip = ?) AND attempted_at > (UTC_TIMESTAMP() - INTERVAL ? MINUTE)',
            [$email, $ip, $mins]
        );
        if ($recent >= $max * 2 || (int) db()->value(
            'SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND attempted_at > (UTC_TIMESTAMP() - INTERVAL ? MINUTE)',
            [$email, $mins]
        ) >= $max) {
            return [false, "Too many failed attempts. Try again in $mins minutes.", null];
        }

        // Staff sign in with email; parents with their phone number; pupils with their admission number
        $username = self::normaliseUsername($email);
        $user = db()->one('SELECT * FROM users WHERE email = ? OR username = ? ORDER BY email = ? DESC LIMIT 1', [$email, $username, $email]);
        // Always run password_verify to keep timing similar for unknown emails
        $hash = $user['password_hash'] ?? '$2y$10$HFZXYpsxCYbxy4Wp0JT/LeMyQNiCfCp3mPDv8Z.45gfNtCBy5McBy';
        $ok = password_verify($password, $hash);

        if (!$user || !$ok || !(int) $user['is_active']) {
            db()->insert('login_attempts', ['identifier' => $email, 'ip' => $ip, 'attempted_at' => gmdate('Y-m-d H:i:s')]);
            if ($user && $ok && !(int) $user['is_active']) {
                audit('login_refused_inactive', 'users', (int) $user['id']);
            }
            return [false, 'Incorrect sign-in name or password.', null];
        }
        // Role switched off, outside the allowed hours or network
        if ($why = Security::restrictionError($user)) {
            audit('login_refused', 'users', (int) $user['id'], ['reason' => $why, 'ip' => $ip]);
            return [false, $why, null];
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            db()->update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [$user['id']]);
        }
        db()->run('DELETE FROM login_attempts WHERE identifier = ?', [$email]);

        if (Security::totpEnabled($user)) {
            // Password is right; the 6-digit code from the authenticator app is still needed
            $intended = $_SESSION['_intended'] ?? null;
            session_regenerate_id(true);
            $_SESSION = ['mfa_uid' => (int) $user['id'], 'mfa_at' => time(), 'mfa_tries' => 0];
            if ($intended) {
                $_SESSION['_intended'] = $intended;
            }
            return [true, '', 'mfa'];
        }
        self::completeLogin($user, false);
        return [true, '', null];
    }

    /** Finish signing in (after the password, and the 6-digit code when 2-step sign-in is on). */
    public static function completeLogin(array $user, bool $mfa): void
    {
        $intended = $_SESSION['_intended'] ?? null;
        session_regenerate_id(true);
        $_SESSION = [];
        if ($intended) {
            $_SESSION['_intended'] = $intended;
        }
        $_SESSION['uid'] = (int) $user['id'];
        $_SESSION['login_at'] = time();
        $_SESSION['confirmed_at'] = time(); // password just typed: counts as a recent confirmation
        $_SESSION['mfa'] = $mfa;
        $updates = ['last_login_at' => gmdate('Y-m-d H:i:s')];
        if (PasswordPolicy::expired($user)) {
            $updates['must_change_password'] = 1;
            $_SESSION['pw_expired'] = 1;
        }
        db()->update('users', $updates, 'id = ?', [$user['id']]);
        self::$user = null;
        Security::startSession($user, $mfa);
        if (!$mfa && Security::mfaRequired($user) && !is_demo()) {
            $_SESSION['must_enrol_2fa'] = 1;
        }
        audit($mfa ? 'login_2fa' : 'login', 'users', (int) $user['id']);
    }

    /** Phone numbers are stored as 0XXXXXXXXX; admission numbers in lower case. */
    public static function normaliseUsername(string $login): string
    {
        $login = strtolower(trim($login));
        $digits = preg_replace('/[\s().-]/', '', $login);
        if (preg_match('/^\+?233(\d{9})$/', $digits, $m)) {
            return '0' . $m[1];
        }
        if (preg_match('/^0\d{9}$/', $digits)) {
            return $digits;
        }
        return $login;
    }

    public static function logout(string $reason = 'signed_out'): void
    {
        $uid = isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : null;
        Security::revokeCurrent($reason);
        if ($uid) {
            audit($reason === 'signed_out' ? 'logout' : 'logout_' . $reason, 'users', $uid);
        }
        self::$user = null;
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function user(): ?array
    {
        if (self::$ending) {
            return null;
        }
        if (self::$user === null && !empty($_SESSION['uid'])) {
            $u = db()->one('SELECT * FROM users WHERE id = ?', [$_SESSION['uid']]);
            if (!$u || !(int) $u['is_active']) {
                self::endSession('Your account has been switched off. Contact the system administrator.');
                return null;
            }
            // Session still valid? (signed out elsewhere, idle too long, outside allowed hours/network…)
            if ($why = Security::checkSession($u)) {
                self::endSession($why);
                return null;
            }
            unset($u['password_hash'], $u['totp_secret'], $u['recovery_codes']);
            self::$user = $u;
        }
        return self::$user;
    }

    /** Sign out because the session is no longer valid, keeping a message for the sign-in page. */
    private static function endSession(string $message): void
    {
        $reason = $_SESSION['_end_reason'] ?? 'ended';
        self::$ending = true;
        self::logout($reason);
        self::$ending = false;
        if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            session_start();
            flash('warning', $message);
        }
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u ? (int) $u['id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function role(): ?string
    {
        return self::user()['role'] ?? null;
    }

    // ------------------------------------------------------------------ roles (database, with built-in fallback)

    /** All roles: key => row (name, base_role, kind, data_scope, require_2fa, idle_minutes, hours, networks, is_active…). */
    public static function roleRows(): array
    {
        if (self::$roleRows === null) {
            $rows = null;
            try {
                $rows = db()->all('SELECT * FROM roles ORDER BY sort_order, name');
            } catch (Throwable $e) {
                $rows = null; // database not updated yet (migration 011)
            }
            if (!$rows) {
                $rows = [];
                foreach (self::ROLES as $k => $name) {
                    $rows[] = ['role_key' => $k, 'name' => $name, 'base_role' => $k, 'kind' => in_array($k, ['parent', 'pupil'], true) ? $k : 'staff',
                        'is_system' => 1, 'data_scope' => 'all', 'require_2fa' => 0, 'idle_minutes' => 0, 'allowed_days' => null,
                        'allowed_from' => null, 'allowed_to' => null, 'allowed_networks' => null, 'is_active' => 1, 'description' => null];
                }
            }
            self::$roleRows = array_column($rows, null, 'role_key');
        }
        return self::$roleRows;
    }

    public static function roleRow(?string $key): ?array
    {
        return $key === null ? null : (self::roleRows()[$key] ?? null);
    }

    /** Active roles for drop-downs: key => name. $kind 'staff' | 'portal' | null (all). */
    public static function roles(?string $kind = null): array
    {
        $out = [];
        foreach (self::roleRows() as $k => $r) {
            if (!(int) $r['is_active']) {
                continue;
            }
            if ($kind === 'staff' && $r['kind'] !== 'staff') {
                continue;
            }
            if ($kind === 'portal' && $r['kind'] === 'staff') {
                continue;
            }
            $out[$k] = $r['name'];
        }
        return $out;
    }

    /** SQL list ('a','b') of every role key based on the given built-in roles — safe to put in a query. */
    public static function sqlRoles(array $bases): string
    {
        $keys = [];
        foreach (self::roleRows() as $k => $r) {
            if (in_array($k, $bases, true) || in_array($r['base_role'] ?? '', $bases, true)) {
                $keys[] = $k;
            }
        }
        $keys = array_filter($keys ?: $bases, fn($k) => (bool) preg_match('/^[a-z0-9_]+$/', $k));
        return "'" . implode("','", $keys) . "'";
    }

    /** role => [permissions] from the database (or the recommended defaults before the update). */
    public static function grants(): array
    {
        if (self::$grants === null) {
            $g = null;
            try {
                if (db()->value("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'role_permissions'")) {
                    $g = [];
                    foreach (db()->all('SELECT role_key, permission FROM role_permissions') as $r) {
                        $g[$r['role_key']][] = $r['permission'];
                    }
                }
            } catch (Throwable $e) {
                $g = null;
            }
            if ($g === null) {
                $g = [];
                foreach (self::DEFAULT_PERMISSIONS as $perm => $roles) {
                    foreach ($roles as $role) {
                        $g[$role][] = $perm;
                    }
                }
            }
            $g[Permissions::ADMIN_ROLE] = array_values(array_unique(array_merge(
                $g[Permissions::ADMIN_ROLE] ?? [],
                ['app.configure', 'modules.switch']
            )));
            self::$grants = $g;
        }
        return self::$grants;
    }

    /** Forget cached roles/permissions (after the administrator changes them). */
    public static function flushCache(): void
    {
        self::$roleRows = null;
        self::$grants = null;
        self::$scope = false;
    }

    /** Is the database updated for "access ends on" dates (migration 032)? */
    public static function hasAccessUntil(): bool
    {
        static $has = null;
        if ($has === null) {
            try {
                $has = (bool) db()->value("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'access_until'");
            } catch (Throwable $e) {
                $has = false;
            }
        }
        return $has;
    }

    /** Read-only roles (e.g. External Auditor) can open pages but never save anything. */
    public static function readOnly(): bool
    {
        $role = self::role();
        if ($role === null || $role === 'super_admin') {
            return false;
        }
        return (int) (self::roleRow($role)['read_only'] ?? 0) === 1;
    }

    public static function can(string $permission): bool
    {
        $role = self::role();
        if ($role === null) {
            return false;
        }
        if ($role === 'super_admin') {
            return true;
        }
        $r = self::roleRow($role);
        if (!$r || !(int) $r['is_active']) {
            return false;
        }
        if (in_array($permission, ['app.configure', 'modules.switch'], true)) {
            return $role === Permissions::ADMIN_ROLE; // Application configuration is fixed to Administrators and Super Admin
        }
        if ($role !== Permissions::ADMIN_ROLE && in_array($permission, Permissions::ADMIN_ONLY, true)) {
            return false; // Administration: Super Admin and Administrator only, whatever the database says
        }
        return in_array($permission, self::grants()[$role] ?? [], true);
    }

    /** Roles that hold a permission (always includes Super Admin). */
    public static function rolesWith(string $permission): array
    {
        $out = ['super_admin'];
        foreach (self::grants() as $role => $perms) {
            if (in_array($permission, $perms, true) && (int) (self::roleRow($role)['is_active'] ?? 0)) {
                $out[] = $role;
            }
        }
        return array_values(array_unique($out));
    }

    public static function roleLabel(?string $role): string
    {
        return self::roleRow($role)['name'] ?? self::ROLES[$role] ?? (string) $role;
    }

    // ------------------------------------------------------------------ data scope (least privilege for teachers)

    /** Class IDs this user may see pupil records for, or null for all classes. */
    public static function classScope(): ?array
    {
        if (self::$scope === false) {
            $role = self::role();
            $r = self::roleRow($role);
            if ($role === null || $role === 'super_admin' || !$r || ($r['data_scope'] ?? 'all') !== 'own') {
                self::$scope = null;
            } else {
                $uid = self::id();
                self::$scope = array_map('intval', array_column(db()->all(
                    'SELECT id FROM classes WHERE is_active = 1 AND class_teacher_id = ?
                     UNION SELECT cs.class_id FROM class_subjects cs JOIN classes c ON c.id = cs.class_id WHERE c.is_active = 1 AND cs.teacher_id = ?',
                    [$uid, $uid]
                ), 'id'));
            }
        }
        return self::$scope;
    }

    public static function classInScope(int $classId): bool
    {
        $s = self::classScope();
        return $s === null || in_array($classId, $s, true);
    }

    /** Is this pupil in one of the user's classes this year (always true for "all classes" roles)? */
    public static function pupilInScope(int $pupilId): bool
    {
        $s = self::classScope();
        if ($s === null) {
            return true;
        }
        if (!$s) {
            return false;
        }
        $in = implode(',', array_fill(0, count($s), '?'));
        return (bool) db()->value(
            "SELECT 1 FROM enrolments e WHERE e.pupil_id = ? AND e.academic_year_id = ? AND e.class_id IN ($in) LIMIT 1",
            array_merge([$pupilId, current_year()['id'] ?? 0], $s)
        );
    }

    /** SQL condition limiting a class-id column to the user's scope ('1=1' when unrestricted). @return array{0:string,1:array} */
    public static function scopeSql(string $classColumn): array
    {
        $s = self::classScope();
        if ($s === null) {
            return ['1=1', []];
        }
        if (!$s) {
            return ['1=0', []];
        }
        return ["$classColumn IN (" . implode(',', array_fill(0, count($s), '?')) . ')', $s];
    }

    // ------------------------------------------------------------------ step-up: confirm the password again for risky actions

    public static function recentlyConfirmed(): bool
    {
        $mins = max(1, (int) setting('step_up_minutes', '10'));
        return !empty($_SESSION['confirmed_at']) && $_SESSION['confirmed_at'] > time() - $mins * 60;
    }

    /** For risky pages: send the user to re-enter their password (and code) first, then come back. */
    public static function requireConfirmed(string $returnRoute): void
    {
        if (self::recentlyConfirmed() || is_demo()) {
            return;
        }
        $_SESSION['confirm_return'] = preg_match('#^[a-z0-9/_.?=&-]*$#i', $returnRoute) ? $returnRoute : 'dashboard';
        redirect('account/confirm');
    }
}
