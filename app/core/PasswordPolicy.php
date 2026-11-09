<?php
declare(strict_types=1);

/** Password rules set by the administrator (Administration → Roles & security → Sign-in rules). */
final class PasswordPolicy
{
    /** Very common passwords and words people use first (checked case-insensitively, also with digits/symbols removed). */
    private const COMMON = [
        'password', 'passw0rd', 'password1', 'password123', '123456', '1234567', '12345678', '123456789', '1234567890', '0123456789',
        '111111', '000000', '123123', '654321', 'qwerty', 'qwerty123', 'qwertyuiop', 'asdfgh', 'asdfghjkl', 'zxcvbnm', 'abc123', 'abcd1234',
        'iloveyou', 'welcome', 'welcome1', 'welcome123', 'admin', 'admin123', 'administrator', 'letmein', 'monkey', 'dragon', 'football',
        'baseball', 'sunshine', 'princess', 'master', 'shadow', 'superman', 'trustno1', 'changeme', 'default', 'secret', 'login', 'teacher',
        'teacher123', 'school', 'school123', 'student', 'student123', 'pupil', 'parent', 'nisdas', 'nisdas123', 'nisdassms', 'adventist',
        'seventhday', 'sda123', 'jesus', 'jesus123', 'jesuschrist', 'god', 'godisgood', 'blessed', 'blessing', 'faith', 'grace', 'ghana',
        'ghana123', 'accra', 'kumasi', 'kasoa', 'blackstars', 'mtnghana', 'vodafone', 'momo', 'demopass', 'temp1234', 'test1234', 'pass1234',
    ];

    public static function settings(): array
    {
        return [
            'min_staff'  => max(8, (int) setting('pw_min_length', '10')),
            'min_portal' => max(6, (int) setting('pw_portal_min_length', '8')),
            'mix'        => setting('pw_require_mix', '1') === '1',
            'history'    => max(0, min(24, (int) setting('pw_history', '5'))),
            'expiry'     => max(0, (int) setting('pw_expiry_days', '0')),
        ];
    }

    private static function isPortal(?array $user): bool
    {
        return $user && in_array(Auth::roleRow($user['role'] ?? null)['kind'] ?? 'staff', ['parent', 'pupil'], true);
    }

    /** Plain-language rules for the form. */
    public static function hints(?array $user = null): array
    {
        $s = self::settings();
        $h = ['At least ' . (self::isPortal($user) ? $s['min_portal'] : $s['min_staff']) . ' characters (a short sentence is easy to remember).'];
        if ($s['mix']) {
            $h[] = 'Letters and at least one number or symbol.';
        }
        $h[] = 'Not a common password, and not your name, sign-in name or the school\'s name.';
        if ($s['history']) {
            $h[] = 'Not one of your last ' . $s['history'] . ' passwords.';
        }
        return $h;
    }

    /** @return string[] problems (empty = acceptable) */
    public static function check(string $pw, ?array $user = null, ?string $currentHash = null): array
    {
        $s = self::settings();
        $min = self::isPortal($user) ? $s['min_portal'] : $s['min_staff'];
        $e = [];
        if (mb_strlen($pw) < $min) {
            $e[] = "Use at least $min characters.";
        }
        if (mb_strlen($pw) > 200) {
            $e[] = 'Use at most 200 characters.';
        }
        if ($s['mix'] && (!preg_match('/\pL/u', $pw) || !preg_match('/[\d\W_]/u', $pw))) {
            $e[] = 'Mix letters with at least one number or symbol.';
        }
        $low = mb_strtolower($pw);
        $bare = preg_replace('/[^a-z]/', '', $low);
        if (in_array($low, self::COMMON, true) || ($bare !== '' && in_array($bare, self::COMMON, true)) || preg_match('/^(.)\1+$/u', $pw) || mb_strlen(count_chars($low, 3)) <= 2) {
            $e[] = 'That password is too common or easy to guess.';
        }
        $personal = [];
        if ($user) {
            foreach (preg_split('/[\s@._-]+/', mb_strtolower(($user['name'] ?? '') . ' ' . ($user['email'] ?? '') . ' ' . ($user['username'] ?? ''))) as $w) {
                if (mb_strlen($w) >= 4) {
                    $personal[] = $w;
                }
            }
        }
        foreach (array_merge($personal, [mb_strtolower(preg_replace('/\s+/', '', school_name())), mb_strtolower(setting('school_short_name', 'NISDAS'))]) as $w) {
            if ($w !== '' && mb_strlen($w) >= 4 && str_contains(preg_replace('/\s+/', '', $low), $w)) {
                $e[] = 'Do not use your name, sign-in name or the school\'s name in the password.';
                break;
            }
        }
        if ($currentHash && password_verify($pw, $currentHash)) {
            $e[] = 'Choose a password different from the current one.';
        } elseif ($user && $s['history'] && !empty($user['id'])) {
            try {
                foreach (db()->all('SELECT hash FROM password_history WHERE user_id = ? ORDER BY id DESC LIMIT ' . (int) $s['history'], [$user['id']]) as $h) {
                    if (password_verify($pw, $h['hash'])) {
                        $e[] = 'You used that password recently — choose a new one.';
                        break;
                    }
                }
            } catch (Throwable $ex) {
                // password_history table not created yet (before migration 011)
            }
        }
        return array_values(array_unique($e));
    }

    /** Save a new password for a user (keeps history, clears forced change). */
    public static function set(int $userId, string $pw, bool $mustChange = false): void
    {
        $hash = password_hash($pw, PASSWORD_DEFAULT);
        $data = ['password_hash' => $hash, 'must_change_password' => $mustChange ? 1 : 0];
        try {
            db()->update('users', $data + ['password_changed_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [$userId]);
            if (!$mustChange) {
                db()->insert('password_history', ['user_id' => $userId, 'hash' => $hash]);
                db()->run('DELETE FROM password_history WHERE user_id = ? AND id NOT IN (SELECT id FROM (SELECT id FROM password_history WHERE user_id = ? ORDER BY id DESC LIMIT 24) x)', [$userId, $userId]);
            }
        } catch (PDOException $e) {
            db()->update('users', $data, 'id = ?', [$userId]); // before migration 011
        }
    }

    /** Has the password passed the expiry age set by the administrator? (Temporary passwords are already forced.) */
    public static function expired(array $user): bool
    {
        $days = self::settings()['expiry'];
        if ($days <= 0 || self::isPortal($user) || empty($user['password_changed_at'])) {
            return false;
        }
        return strtotime($user['password_changed_at'] . ' UTC') < time() - $days * 86400;
    }
}
