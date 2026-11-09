<?php
declare(strict_types=1);

/** Shared rules for online learning (LMS materials and live classes): who may post for which class, and who may see it. */
final class Learning
{
    public const PROVIDERS = ['zoom' => 'Zoom', 'meet' => 'Google Meet', 'teams' => 'Microsoft Teams', 'other' => 'Other link'];
    public const KINDS = ['note' => 'Lesson note', 'video' => 'Video', 'link' => 'Web link', 'file' => 'Worksheet / file'];

    /**
     * Where this teacher may post: [['key' => 'cs:12' | 'c:4', 'class_id', 'class_subject_id', 'label']].
     * Subject teachers: their class-subjects. Class teachers: also "whole class". Head / coordinator: everything.
     */
    public static function targets(): array
    {
        $out = [];
        $all = Auth::classScope() === null;
        $classes = db()->all(
            'SELECT c.id, c.name, c.class_teacher_id FROM classes c JOIN levels l ON l.id = c.level_id WHERE c.is_active = 1' . ($all ? '' : ' AND c.class_teacher_id = ?') . ' ORDER BY l.sort_order, c.name',
            $all ? [] : [Auth::id()]
        );
        foreach ($classes as $c) {
            $out[] = ['key' => 'c:' . $c['id'], 'class_id' => (int) $c['id'], 'class_subject_id' => null, 'label' => $c['name'] . ' — whole class'];
        }
        foreach (Assignments::choices() as $cs) {
            $out[] = ['key' => 'cs:' . $cs['id'], 'class_id' => (int) $cs['class_id'], 'class_subject_id' => (int) $cs['id'], 'label' => $cs['class_name'] . ' — ' . $cs['subject_name']];
        }
        usort($out, fn($a, $b) => strnatcasecmp($a['label'], $b['label']));
        return $out;
    }

    /** Resolve a posted target key against what this teacher may use. */
    public static function target(string $key): ?array
    {
        foreach (self::targets() as $t) {
            if ($t['key'] === $key) {
                return $t;
            }
        }
        return null;
    }

    /** May this staff member change a material / live class (row with created_by, class_id, class_subject_id)? */
    public static function canManage(array $row, string $perm): bool
    {
        if (!can($perm)) {
            return false;
        }
        if (Auth::classScope() === null || (int) $row['created_by'] === (int) Auth::id()) {
            return true;
        }
        $key = $row['class_subject_id'] ? 'cs:' . $row['class_subject_id'] : 'c:' . $row['class_id'];
        return self::target($key) !== null;
    }

    /** Classes the signed-in pupil or parent follows: class_id => [pupils]. Empty for staff. */
    public static function viewerClasses(): array
    {
        $out = [];
        if (Auth::role() === 'pupil') {
            $p = Accounts::pupilOf((int) Auth::id());
            if ($p && $p['class_id']) {
                $out[(int) $p['class_id']][] = $p;
            }
        } elseif (Auth::role() === 'parent') {
            foreach (Accounts::childrenOf((int) Auth::id()) as $c) {
                if ($c['class_id'] && $c['status'] === 'active') {
                    $out[(int) $c['class_id']][] = $c;
                }
            }
        }
        return $out;
    }

    /** Pupils' and parents' sign-ins for a class (to tell them about new work or a live class). */
    public static function classUsers(int $classId): array
    {
        $yid = current_year()['id'] ?? 0;
        return array_map('intval', array_column(db()->all(
            "SELECT p.user_id AS uid FROM enrolments e JOIN pupils p ON p.id = e.pupil_id WHERE e.class_id = ? AND e.academic_year_id = ? AND e.status = 'active' AND p.user_id IS NOT NULL
             UNION SELECT g.user_id FROM enrolments e JOIN pupil_guardian pg ON pg.pupil_id = e.pupil_id JOIN guardians g ON g.id = pg.guardian_id
             WHERE e.class_id = ? AND e.academic_year_id = ? AND e.status = 'active' AND g.user_id IS NOT NULL",
            [$classId, $yid, $classId, $yid]
        ), 'uid'));
    }

    /** YouTube video id from a watch / share / shorts link, or null. */
    public static function youtubeId(string $url): ?string
    {
        if (preg_match('~^https://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|shorts/|embed/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m)) {
            return $m[1];
        }
        return null;
    }

    /** Provider of a meeting link by its web address, or null if it is not a recognised https meeting link. */
    public static function providerOf(string $url): ?string
    {
        $p = parse_url($url);
        if (($p['scheme'] ?? '') !== 'https' || empty($p['host'])) {
            return null;
        }
        $host = strtolower($p['host']);
        if ($host === 'zoom.us' || str_ends_with($host, '.zoom.us')) {
            return 'zoom';
        }
        if ($host === 'meet.google.com') {
            return 'meet';
        }
        if (in_array($host, ['teams.microsoft.com', 'teams.live.com'], true)) {
            return 'teams';
        }
        return 'other';
    }
}
