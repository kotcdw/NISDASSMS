<?php
declare(strict_types=1);

/** Notice board: who can see which notice. */
final class Notices
{
    public const CATEGORIES = ['notice' => ['Notice', 'info'], 'circular' => ['Circular', 'warn'], 'event' => ['Event', 'good'], 'urgent' => ['Urgent', 'bad']];

    /** Audience choices: value => label */
    public static function audiences(): array
    {
        $a = [
            'all' => 'Everyone (staff, parents, students)', 'staff' => 'Staff only', 'parents' => 'All parents', 'pupils' => 'All students',
            'parents:creche' => 'Parents — Creche', 'parents:kg' => 'Parents — KG', 'parents:primary' => 'Parents — Basic 1–6', 'parents:jhs' => 'Parents — JHS',
        ];
        foreach (db()->all('SELECT c.id, c.name FROM classes c JOIN levels l ON l.id = c.level_id WHERE c.is_active = 1 ORDER BY l.sort_order, c.name') as $c) {
            $a['class:' . $c['id']] = 'Class ' . $c['name'] . ' (parents & students)';
        }
        return $a;
    }

    public static function label(string $audience): string
    {
        return self::audiences()[$audience] ?? $audience;
    }

    /** SQL condition (and params) limiting notices to what the signed-in user may see. */
    public static function visibility(): array
    {
        if (can('staff')) {
            return ['1 = 1', []];
        }
        $uid = (int) Auth::id();
        $role = Auth::role();
        $keys = ["'all'"];
        $params = [];
        $classIds = [];
        $cats = [];
        if ($role === 'parent') {
            $keys[] = "'parents'";
            foreach (Accounts::childrenOf($uid) as $c) {
                if ($c['status'] === 'active' && $c['class_id']) {
                    $classIds[] = (int) $c['class_id'];
                    $cats[] = $c['category'];
                }
            }
        } elseif ($role === 'pupil') {
            $keys[] = "'pupils'";
            $p = Accounts::pupilOf($uid);
            if ($p && $p['class_id']) {
                $classIds[] = (int) $p['class_id'];
            }
        }
        foreach (array_unique($cats) as $cat) {
            $keys[] = '?';
            $params[] = 'parents:' . $cat;
        }
        foreach (array_unique($classIds) as $cid) {
            $keys[] = '?';
            $params[] = 'class:' . $cid;
        }
        return ['n.audience IN (' . implode(',', $keys) . ')', $params];
    }

    /** Phone numbers for the optional SMS alert (one per family). */
    public static function phones(string $audience): array
    {
        if (in_array($audience, ['staff', 'pupils'], true)) {
            return $audience === 'staff' ? Audience::phones('staff:all') : [];
        }
        if ($audience === 'all') {
            return Audience::phones('parents:all') + Audience::phones('staff:all');
        }
        if ($audience === 'parents') {
            return Audience::phones('parents:all');
        }
        return Audience::phones($audience); // parents:<category> and class:<id> are understood by Audience
    }

    /** Users who should acknowledge a circular (for the read report). */
    public static function audienceUserCount(string $audience): int
    {
        $yid = current_year()['id'] ?? 0;
        $parentsSql = "SELECT COUNT(DISTINCT g.user_id) FROM guardians g JOIN pupil_guardian pg ON pg.guardian_id = g.id JOIN pupils p ON p.id = pg.pupil_id
                       JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ? JOIN classes c ON c.id = e.class_id JOIN levels l ON l.id = c.level_id
                       WHERE g.user_id IS NOT NULL AND p.status = 'active'";
        [$type, $val] = array_pad(explode(':', $audience, 2), 2, '');
        if ($audience === 'staff') {
            return (int) db()->value("SELECT COUNT(*) FROM users WHERE is_active = 1 AND role NOT IN ('parent','pupil')");
        }
        if ($audience === 'pupils') {
            return (int) db()->value("SELECT COUNT(*) FROM users WHERE is_active = 1 AND role = 'pupil'");
        }
        if ($audience === 'parents' || $audience === 'all') {
            return (int) db()->value($parentsSql, [$yid]);
        }
        if ($type === 'parents') {
            return (int) db()->value($parentsSql . ' AND l.category = ?', [$yid, $val]);
        }
        if ($type === 'class') {
            return (int) db()->value($parentsSql . ' AND c.id = ?', [$yid, (int) $val]);
        }
        return 0;
    }

    /**
     * Bell alert to staff for a published notice meant for everyone or for staff — once per notice
     * (editing it does not alert again). Notices set to appear later are alerted by the scheduled task.
     */
    public static function alertStaff(array $n): bool
    {
        if (!in_array($n['audience'], ['all', 'staff'], true) || $n['publish_at'] > date('Y-m-d H:i:s')
            || ($n['expires_on'] && $n['expires_on'] < date('Y-m-d'))) {
            return false;
        }
        $link = 'notices/' . (int) $n['id'];
        if (db()->value('SELECT 1 FROM inapp_notifications WHERE link = ? LIMIT 1', [$link])) {
            return false;
        }
        Notifier::inappPermission('staff', 'Notice: ' . $n['title'], null, $link);
        return true;
    }

    /** Scheduled task: bell alerts for notices whose publish time has now arrived (last 7 days). */
    public static function alertDue(): int
    {
        $n = 0;
        foreach (db()->all("SELECT * FROM notices WHERE audience IN ('all','staff') AND publish_at <= NOW() AND publish_at >= NOW() - INTERVAL 7 DAY") as $row) {
            $n += self::alertStaff($row) ? 1 : 0;
        }
        return $n;
    }

    /** True once migration 034 (sign-in page notices) is in. */
    public static function loginReady(): bool
    {
        static $r = null;
        return $r ??= (bool) db()->value("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'notices' AND column_name = 'on_login'");
    }

    /** Notices ticked for the sign-in page (anyone can read them): the newest 5 showing now, pinned and urgent first. */
    public static function forLogin(int $limit = 5): array
    {
        if (!self::loginReady() || !ModuleSwitch::isOn('notices')) {
            return [];
        }
        return db()->all(
            "SELECT id, title, body, category, publish_at, attachment, attachment_name FROM notices
             WHERE on_login = 1 AND publish_at <= NOW() AND (expires_on IS NULL OR expires_on >= CURDATE())
             ORDER BY is_pinned DESC, (category = 'urgent') DESC, publish_at DESC LIMIT " . max(1, $limit)
        );
    }

    /** One sign-in page notice (or null when it is not ticked, not yet published or past its date). */
    public static function loginNotice(int $id): ?array
    {
        if (!self::loginReady()) {
            return null;
        }
        return db()->one("SELECT * FROM notices WHERE id = ? AND on_login = 1 AND publish_at <= NOW() AND (expires_on IS NULL OR expires_on >= CURDATE())", [$id]);
    }
}
