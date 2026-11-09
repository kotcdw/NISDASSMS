<?php
declare(strict_types=1);

/** Parent and pupil sign-in accounts. */
final class Accounts
{
    /** Easy-to-read temporary password (no 0/O, 1/l/I). */
    public static function tempPassword(int $len = 8): string
    {
        $chars = 'abcdefghjkmnpqrstuvwxyz23456789';
        $out = '';
        for ($i = 0; $i < $len; $i++) {
            $out .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $out;
    }

    /**
     * Create (or reset) the portal account of a guardian. Sign-in name = phone number.
     * @return array{username:string,password:string,user_id:int,created:bool}|null null when the phone is unusable
     */
    public static function parentLogin(int $guardianId, bool $sms = true): ?array
    {
        $g = db()->one('SELECT * FROM guardians WHERE id = ?', [$guardianId]);
        $phone = $g ? Admission::normalisePhone($g['phone']) : null;
        if (!$g || !$phone || !preg_match('/^0\d{9}$/', $phone)) {
            return null;
        }
        $password = self::tempPassword();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $created = false;
        $userId = (int) $g['user_id'];
        // Another guardian record with the same phone may already have an account: share it
        if (!$userId) {
            $userId = (int) db()->value("SELECT id FROM users WHERE username = ? AND role = 'parent'", [$phone]);
        }
        if ($userId) {
            db()->update('users', ['password_hash' => $hash, 'must_change_password' => 1, 'is_active' => 1, 'username' => $phone], 'id = ?', [$userId]);
        } else {
            if (db()->value('SELECT id FROM users WHERE username = ?', [$phone])) {
                return null; // phone already used as a staff/pupil sign-in name
            }
            $email = filter_var((string) $g['email'], FILTER_VALIDATE_EMAIL) && !db()->value('SELECT id FROM users WHERE email = ?', [strtolower($g['email'])])
                ? strtolower($g['email']) : 'parent' . $g['id'] . '@users.nisdas.local';
            $userId = db()->insert('users', [
                'name' => $g['name'], 'email' => $email, 'username' => $phone, 'phone' => $phone, 'role' => 'parent',
                'password_hash' => $hash, 'must_change_password' => 1, 'is_active' => 1,
            ]);
            $created = true;
        }
        db()->run('UPDATE guardians SET user_id = ? WHERE id = ? OR (phone = ? AND user_id IS NULL)', [$userId, $guardianId, $g['phone']]);
        if ($sms) {
            Notifier::smsTemplate('parent_login', $phone, [
                'guardian' => $g['name'], 'link' => absolute_url('login'), 'username' => $phone, 'password' => $password,
            ], ['name' => $g['name'], 'entity' => 'login', 'entity_id' => random_int(1, 2147483647), 'urgent' => true]);
        }
        audit($created ? 'parent_login_created' : 'parent_login_reset', 'users', $userId, ['guardian' => $guardianId]);
        return ['username' => $phone, 'password' => $password, 'user_id' => $userId, 'created' => $created];
    }

    /**
     * Create (or reset) a pupil's account. Sign-in name = admission number (lower case).
     * @return array{username:string,password:string,user_id:int,created:bool}|null
     */
    public static function pupilLogin(int $pupilId): ?array
    {
        $p = db()->one('SELECT * FROM pupils WHERE id = ? AND deleted_at IS NULL', [$pupilId]);
        if (!$p) {
            return null;
        }
        $username = strtolower($p['admission_no']);
        $password = self::tempPassword(6);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $created = false;
        $userId = (int) $p['user_id'];
        if ($userId) {
            db()->update('users', ['password_hash' => $hash, 'must_change_password' => 1, 'is_active' => 1, 'username' => $username], 'id = ?', [$userId]);
        } else {
            if (db()->value('SELECT id FROM users WHERE username = ?', [$username])) {
                return null;
            }
            $userId = db()->insert('users', [
                'name' => trim($p['first_name'] . ' ' . $p['last_name']), 'email' => 'pupil' . $p['id'] . '@users.nisdas.local',
                'username' => $username, 'role' => 'pupil', 'password_hash' => $hash, 'must_change_password' => 1, 'is_active' => 1,
            ]);
            db()->update('pupils', ['user_id' => $userId], 'id = ?', [$pupilId]);
            $created = true;
        }
        audit($created ? 'pupil_login_created' : 'pupil_login_reset', 'users', $userId, ['pupil' => $pupilId]);
        return ['username' => $username, 'password' => $password, 'user_id' => $userId, 'created' => $created];
    }

    /** Pupils whose parent/guardian account is this user (active pupils first). */
    public static function childrenOf(int $userId): array
    {
        $yid = current_year()['id'] ?? 0;
        return db()->all(
            "SELECT DISTINCT p.*, c.id AS class_id, c.name AS class_name, l.category, l.id AS level_id, ct.name AS class_teacher, ct.id AS class_teacher_id
             FROM guardians g JOIN pupil_guardian pg ON pg.guardian_id = g.id JOIN pupils p ON p.id = pg.pupil_id
             LEFT JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ? LEFT JOIN classes c ON c.id = e.class_id
             LEFT JOIN levels l ON l.id = c.level_id LEFT JOIN users ct ON ct.id = c.class_teacher_id
             WHERE g.user_id = ? AND p.deleted_at IS NULL ORDER BY p.status = 'active' DESC, p.date_of_birth",
            [$yid, $userId]
        );
    }

    /** The pupil record of a pupil user, with current class. */
    public static function pupilOf(int $userId): ?array
    {
        $yid = current_year()['id'] ?? 0;
        return db()->one(
            "SELECT p.*, e.class_id, c.name AS class_name, l.category, ct.name AS class_teacher, ct.id AS class_teacher_id FROM pupils p
             LEFT JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ? AND e.status = 'active'
             LEFT JOIN classes c ON c.id = e.class_id LEFT JOIN levels l ON l.id = c.level_id LEFT JOIN users ct ON ct.id = c.class_teacher_id
             WHERE p.user_id = ? AND p.deleted_at IS NULL AND p.status = 'active'",
            [$yid, $userId]
        );
    }

    /** Children linked to a guardian record (Super Admin preview of the parent portal). */
    public static function childrenOfGuardian(int $guardianId): array
    {
        $yid = current_year()['id'] ?? 0;
        return db()->all(
            "SELECT DISTINCT p.*, c.id AS class_id, c.name AS class_name, l.category, l.id AS level_id, ct.name AS class_teacher, ct.id AS class_teacher_id
             FROM pupil_guardian pg JOIN pupils p ON p.id = pg.pupil_id
             LEFT JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ? LEFT JOIN classes c ON c.id = e.class_id
             LEFT JOIN levels l ON l.id = c.level_id LEFT JOIN users ct ON ct.id = c.class_teacher_id
             WHERE pg.guardian_id = ? AND p.deleted_at IS NULL ORDER BY p.status = 'active' DESC, p.date_of_birth",
            [$yid, $guardianId]
        );
    }

    /** Any pupil with class details (Super Admin preview). */
    public static function pupilById(int $pupilId): ?array
    {
        $yid = current_year()['id'] ?? 0;
        return db()->one(
            "SELECT p.*, c.id AS class_id, c.name AS class_name, l.category, l.id AS level_id, ct.name AS class_teacher, ct.id AS class_teacher_id
             FROM pupils p LEFT JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ?
             LEFT JOIN classes c ON c.id = e.class_id LEFT JOIN levels l ON l.id = c.level_id LEFT JOIN users ct ON ct.id = c.class_teacher_id
             WHERE p.id = ? AND p.deleted_at IS NULL",
            [$yid, $pupilId]
        );
    }
}
