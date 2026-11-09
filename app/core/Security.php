<?php
declare(strict_types=1);

/**
 * Sign-in security: tracked sessions (idle and maximum time, sign out everywhere), role restrictions
 * (allowed hours and networks), 2-step sign-in state, new-device alerts and dormant accounts.
 */
final class Security
{
    private static ?bool $ready = null;

    /** Is the security database update (migration 011) applied? */
    public static function ready(): bool
    {
        if (self::$ready === null) {
            try {
                self::$ready = (bool) db()->value("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'user_sessions'");
            } catch (Throwable $e) {
                self::$ready = false;
            }
        }
        return self::$ready;
    }

    /** Visitor's IP address ('trust_proxy' => true in config.local.php when behind a hosting proxy). */
    public static function clientIp(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli');
        if (config('trust_proxy', false) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $first = trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                $ip = $first;
            }
        }
        return substr($ip, 0, 45);
    }

    // ------------------------------------------------------------------ role restrictions

    /** Does $ip fall in one of the listed networks ("192.168.1.0/24", "41.66.10.5", "2001:db8::/32")? */
    public static function ipAllowed(string $list, string $ip): bool
    {
        $entries = array_filter(array_map('trim', preg_split('/[\s,;]+/', $list)));
        if (!$entries) {
            return true;
        }
        $bin = @inet_pton($ip);
        if ($bin === false) {
            return false;
        }
        foreach ($entries as $e) {
            [$net, $bits] = array_pad(explode('/', $e, 2), 2, null);
            $nb = @inet_pton($net);
            if ($nb === false || strlen($nb) !== strlen($bin)) {
                continue;
            }
            $bits = $bits === null ? strlen($nb) * 8 : max(0, min(strlen($nb) * 8, (int) $bits));
            $bytes = intdiv($bits, 8);
            $rem = $bits % 8;
            if (substr($bin, 0, $bytes) !== substr($nb, 0, $bytes)) {
                continue;
            }
            if ($rem === 0 || ((ord($bin[$bytes]) ^ ord($nb[$bytes])) & (0xFF << (8 - $rem)) & 0xFF) === 0) {
                return true;
            }
        }
        return false;
    }

    public static function validNetworkList(string $list): bool
    {
        foreach (array_filter(array_map('trim', preg_split('/[\s,;]+/', $list))) as $e) {
            [$net, $bits] = array_pad(explode('/', $e, 2), 2, null);
            $nb = @inet_pton($net);
            if ($nb === false || ($bits !== null && (!ctype_digit($bits) || (int) $bits > strlen($nb) * 8))) {
                return false;
            }
        }
        return true;
    }

    public const DAYS = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

    /** Is "now" inside the role's allowed days and hours? */
    public static function withinHours(array $role, ?int $now = null): bool
    {
        $now ??= time();
        $days = array_filter(explode(',', (string) ($role['allowed_days'] ?? '')));
        if ($days && !in_array(date('N', $now), $days, true)) {
            return false;
        }
        $from = $role['allowed_from'] ?? null;
        $to = $role['allowed_to'] ?? null;
        if ($from && $to) {
            $t = date('H:i:s', $now);
            return $from <= $to ? ($t >= $from && $t <= $to) : ($t >= $from || $t <= $to); // overnight windows too
        }
        return true;
    }

    /** Why this user may not use the system right now (null = allowed). Super Admin is never blocked by hours/network. */
    public static function restrictionError(array $user): ?string
    {
        $role = Auth::roleRow($user['role'] ?? null);
        if (!$role) {
            return 'Your account has no valid role. Contact the system administrator.';
        }
        if (!(int) $role['is_active']) {
            return 'Sign-in for "' . $role['name'] . '" accounts is switched off. Contact the system administrator.';
        }
        if (($user['role'] ?? '') === 'super_admin') {
            return null;
        }
        if (!empty($user['access_until']) && $user['access_until'] < date('Y-m-d')) {
            return 'Your access ended on ' . fmt_date($user['access_until']) . '. Contact the school if you still need it.';
        }
        if (!self::withinHours($role)) {
            $days = array_filter(explode(',', (string) $role['allowed_days']));
            return $role['name'] . ' accounts can sign in only ' . ($days ? implode(', ', array_map(fn($d) => self::DAYS[(int) $d] ?? $d, $days)) : 'on allowed days')
                . ($role['allowed_from'] && $role['allowed_to'] ? ' between ' . substr($role['allowed_from'], 0, 5) . ' and ' . substr($role['allowed_to'], 0, 5) : '') . '.';
        }
        if (!empty($role['allowed_networks']) && !self::ipAllowed((string) $role['allowed_networks'], self::clientIp())) {
            return $role['name'] . ' accounts can sign in only from the school network.';
        }
        return null;
    }

    // ------------------------------------------------------------------ 2-step sign-in state

    /**
     * The school-wide 2-step sign-in switch (Administration → Roles & security → Sign-in rules).
     * Off during development and testing; switch it on before go-live (the go-live checklist and the
     * top-bar alerts remind the Super Admin and Administrator while it is off).
     */
    public static function mfaOn(): bool
    {
        return setting('mfa_enforced', '1') === '1';
    }

    public static function totpEnabled(array $user): bool
    {
        return self::mfaOn() && !empty($user['totp_enabled_at']) && !empty($user['totp_secret']);
    }

    public static function mfaRequired(array $user): bool
    {
        if (!self::ready() || !self::mfaOn()) {
            return false;
        }
        return (int) (Auth::roleRow($user['role'] ?? null)['require_2fa'] ?? 0) === 1;
    }

    /** Must this signed-in person set up 2-step sign-in before doing anything else? */
    public static function mustEnrol(?array $user): bool
    {
        return $user && !is_demo() && self::mfaRequired($user) && empty($user['totp_enabled_at']);
    }

    /** Check a 6-digit app code or a one-time recovery code for this user (updates the user row on success). */
    public static function verifySecondFactor(array $user, string $input): bool
    {
        $input = trim($input);
        $secret = Crypto::decrypt($user['totp_secret'] ?? null);
        if ($secret && preg_match('/^\d[\d ]{4,7}$/', $input)) {
            $step = Totp::verify($secret, $input, (int) ($user['totp_last_step'] ?? 0));
            if ($step !== null) {
                db()->update('users', ['totp_last_step' => $step], 'id = ?', [$user['id']]);
                return true;
            }
            return false;
        }
        // Recovery code (each works once)
        $norm = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $input));
        if (strlen($norm) === 10) {
            $codes = json_decode((string) ($user['recovery_codes'] ?? '[]'), true) ?: [];
            foreach ($codes as $i => $h) {
                if (password_verify($norm, $h)) {
                    unset($codes[$i]);
                    db()->update('users', ['recovery_codes' => json_encode(array_values($codes))], 'id = ?', [$user['id']]);
                    audit('2fa_recovery_code_used', 'users', (int) $user['id'], ['left' => count($codes)]);
                    if (count($codes) <= 2) {
                        Notifier::inapp([(int) $user['id']], 'Only ' . count($codes) . ' recovery codes left', 'Make new recovery codes in My security.', 'account/security');
                    }
                    return true;
                }
            }
        }
        return false;
    }

    /** @return array{0:string[],1:string[]} [codes to show once, hashes to store] */
    public static function newRecoveryCodes(int $n = 10): array
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $plain = [];
        $hashes = [];
        for ($i = 0; $i < $n; $i++) {
            $c = '';
            for ($j = 0; $j < 10; $j++) {
                $c .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $plain[] = substr($c, 0, 5) . '-' . substr($c, 5);
            $hashes[] = password_hash($c, PASSWORD_DEFAULT);
        }
        return [$plain, $hashes];
    }

    // ------------------------------------------------------------------ sessions

    private static function hashToken(string $t): string
    {
        return hash('sha256', $t);
    }

    public static function startSession(array $user, bool $mfa): void
    {
        if (!self::ready() || PHP_SAPI === 'cli') {
            return;
        }
        $token = bin2hex(random_bytes(32));
        $ip = self::clientIp();
        $ua = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $known = (bool) db()->value('SELECT 1 FROM user_sessions WHERE user_id = ? AND (ip = ? OR user_agent = ?) LIMIT 1', [$user['id'], $ip, $ua]);
        $hasHistory = (bool) db()->value('SELECT 1 FROM user_sessions WHERE user_id = ? LIMIT 1', [$user['id']]);
        if (setting('single_session', '0') === '1' && (Auth::roleRow($user['role'])['kind'] ?? 'staff') === 'staff') {
            db()->run("UPDATE user_sessions SET revoked_at = UTC_TIMESTAMP(), revoked_reason = 'new_sign_in' WHERE user_id = ? AND revoked_at IS NULL", [$user['id']]);
        }
        db()->insert('user_sessions', [
            'user_id' => $user['id'], 'token_hash' => self::hashToken($token), 'ip' => $ip, 'user_agent' => $ua,
            'mfa' => $mfa ? 1 : 0, 'created_at' => gmdate('Y-m-d H:i:s'), 'last_seen_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $_SESSION['sid'] = $token;
        $_SESSION['seen'] = time();
        // Sign-in from a new device or network: tell the user (and keep a record)
        if ($hasHistory && !$known && setting('login_alerts', '1') === '1') {
            Notifier::inapp([(int) $user['id']], 'New sign-in to your account', 'From ' . self::deviceName($ua) . ', IP ' . $ip . ' at ' . date('j M H:i') . '. If this was not you, change your password and sign out other sessions in My security.', 'account/security');
            audit('login_new_device', 'users', (int) $user['id'], ['ip' => $ip, 'device' => self::deviceName($ua)]);
            if (($user['role'] ?? '') === 'super_admin' && setting('login_alert_sms', '0') === '1' && !empty($user['phone'])) {
                Notifier::sms((string) $user['phone'], 'NISDAS: new Super Admin sign-in from ' . self::deviceName($ua) . ' (IP ' . $ip . ') at ' . date('j M H:i') . '. If this was not you, contact the school at once.', ['name' => $user['name'], 'event' => 'login_alert', 'entity' => 'users', 'entity_id' => $user['id'], 'urgent' => true]);
            }
        }
    }

    /** Is the current session still valid? Returns the reason to sign out, or null. Also refreshes "last seen". */
    public static function checkSession(array $user): ?string
    {
        if (!self::ready() || PHP_SAPI === 'cli') {
            return null;
        }
        if ($why = self::restrictionError($user)) {
            $_SESSION['_end_reason'] = 'restricted';
            return $why;
        }
        if (empty($_SESSION['sid'])) {
            // Signed in before this update: start tracking now
            self::startSession($user, false);
            return null;
        }
        $row = db()->one('SELECT * FROM user_sessions WHERE token_hash = ?', [self::hashToken((string) $_SESSION['sid'])]);
        if (!$row || (int) $row['user_id'] !== (int) $user['id'] || $row['revoked_at']) {
            $_SESSION['_end_reason'] = 'revoked';
            return 'You were signed out' . (($row['revoked_reason'] ?? '') === 'new_sign_in' ? ' because your account signed in somewhere else.' : ($row ? ' by an administrator or from another device.' : '.'));
        }
        $now = time();
        $idle = (int) (Auth::roleRow($user['role'])['idle_minutes'] ?? 0);
        $seen = strtotime($row['last_seen_at'] . ' UTC');
        if ($idle > 0 && $seen < $now - $idle * 60) {
            self::revokeToken((string) $_SESSION['sid'], 'idle');
            $_SESSION['_end_reason'] = 'idle';
            return "For safety you were signed out after $idle minutes without activity. Please sign in again.";
        }
        $maxH = max(1, (int) setting('session_absolute_hours', '12'));
        if (strtotime($row['created_at'] . ' UTC') < $now - $maxH * 3600) {
            self::revokeToken((string) $_SESSION['sid'], 'expired');
            $_SESSION['_end_reason'] = 'expired';
            return "Sessions last at most $maxH hours. Please sign in again.";
        }
        if ($seen < $now - 60) {
            db()->update('user_sessions', ['last_seen_at' => gmdate('Y-m-d H:i:s'), 'ip' => self::clientIp()], 'id = ?', [$row['id']]);
        }
        return null;
    }

    private static function revokeToken(string $token, string $reason): void
    {
        db()->run('UPDATE user_sessions SET revoked_at = UTC_TIMESTAMP(), revoked_reason = ? WHERE token_hash = ? AND revoked_at IS NULL', [$reason, self::hashToken($token)]);
    }

    public static function revokeCurrent(string $reason): void
    {
        if (self::ready() && !empty($_SESSION['sid'])) {
            self::revokeToken((string) $_SESSION['sid'], $reason);
        }
    }

    /** Sign a user out everywhere (optionally keeping the current session). */
    public static function revokeAll(int $userId, string $reason, bool $keepCurrent = false): int
    {
        if (!self::ready()) {
            return 0;
        }
        $sql = 'UPDATE user_sessions SET revoked_at = UTC_TIMESTAMP(), revoked_reason = ? WHERE user_id = ? AND revoked_at IS NULL';
        $p = [$reason, $userId];
        if ($keepCurrent && !empty($_SESSION['sid'])) {
            $sql .= ' AND token_hash <> ?';
            $p[] = self::hashToken((string) $_SESSION['sid']);
        }
        return db()->run($sql, $p)->rowCount();
    }

    public static function revokeOne(int $userId, int $sessionId, string $reason): bool
    {
        return self::ready() && db()->run('UPDATE user_sessions SET revoked_at = UTC_TIMESTAMP(), revoked_reason = ? WHERE id = ? AND user_id = ? AND revoked_at IS NULL', [$reason, $sessionId, $userId])->rowCount() > 0;
    }

    /** Open sessions (last 30 days) with a flag for the current one. */
    public static function sessions(int $userId): array
    {
        if (!self::ready()) {
            return [];
        }
        $cur = !empty($_SESSION['sid']) ? self::hashToken((string) $_SESSION['sid']) : '';
        $rows = db()->all('SELECT * FROM user_sessions WHERE user_id = ? AND revoked_at IS NULL AND last_seen_at > (UTC_TIMESTAMP() - INTERVAL 30 DAY) ORDER BY last_seen_at DESC LIMIT 50', [$userId]);
        foreach ($rows as &$r) {
            $r['current'] = hash_equals($r['token_hash'], $cur);
            $r['device'] = self::deviceName((string) $r['user_agent']);
        }
        return $rows;
    }

    public static function deviceName(string $ua): string
    {
        $os = preg_match('/Android/i', $ua) ? 'Android' : (preg_match('/iPhone|iPad/i', $ua) ? 'iPhone/iPad' : (preg_match('/Windows/i', $ua) ? 'Windows' : (preg_match('/Mac OS/i', $ua) ? 'Mac' : (preg_match('/Linux/i', $ua) ? 'Linux' : 'Unknown device'))));
        $br = preg_match('/Edg\//', $ua) ? 'Edge' : (preg_match('/OPR\//', $ua) ? 'Opera' : (preg_match('/Chrome\//', $ua) ? 'Chrome' : (preg_match('/Firefox\//', $ua) ? 'Firefox' : (preg_match('/Safari\//', $ua) ? 'Safari' : 'browser'))));
        return "$br on $os";
    }

    // ------------------------------------------------------------------ housekeeping (scheduled task)

    /** Switch off staff accounts unused for the set number of days (never Super Admins). Returns how many. */
    public static function sweepDormant(): int
    {
        $days = (int) setting('dormant_days', '0');
        if (!self::ready() || $days < 30) {
            return 0;
        }
        $staff = Auth::sqlRoles(array_keys(Auth::roles('staff')));
        $rows = db()->all(
            "SELECT id FROM users WHERE is_active = 1 AND role IN ($staff) AND role <> 'super_admin'
             AND COALESCE(last_login_at, created_at) < (UTC_TIMESTAMP() - INTERVAL ? DAY)",
            [$days]
        );
        foreach ($rows as $r) {
            db()->update('users', ['is_active' => 0, 'locked_reason' => "Not used for $days days (switched off automatically)"], 'id = ?', [$r['id']]);
            self::revokeAll((int) $r['id'], 'dormant');
            audit('account_dormant_disabled', 'users', (int) $r['id'], ['days' => $days]);
        }
        db()->run('DELETE FROM user_sessions WHERE last_seen_at < (UTC_TIMESTAMP() - INTERVAL 90 DAY)');
        return count($rows);
    }
}
