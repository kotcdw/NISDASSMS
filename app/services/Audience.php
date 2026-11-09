<?php
declare(strict_types=1);

/** Resolves "who should receive this" into phone numbers / user ids. */
final class Audience
{
    /** Options for the compose screen: value => label */
    public static function options(): array
    {
        $opts = [
            'parents:all'      => 'All parents (active students)',
            'parents:creche'   => 'Parents — Creche',
            'parents:kg'       => 'Parents — Kindergarten',
            'parents:primary'  => 'Parents — Basic 1–6',
            'parents:jhs'      => 'Parents — JHS',
        ];
        foreach (db()->all('SELECT c.id, c.name FROM classes c JOIN levels l ON l.id = c.level_id WHERE c.is_active = 1 ORDER BY l.sort_order, c.name') as $c) {
            $opts['class:' . $c['id']] = 'Parents — ' . $c['name'];
        }
        $opts['staff:all'] = 'All staff (with phone numbers)';
        $opts['custom'] = 'Type phone numbers';
        return $opts;
    }

    /**
     * @return array<string,string> phone => name (deduplicated; one SMS per family)
     */
    public static function phones(string $audience, string $custom = '', ?int $levelId = null): array
    {
        $yid = current_year()['id'] ?? 0;
        $base = "SELECT DISTINCT g.phone, g.name FROM pupils p
                 JOIN pupil_guardian pg ON pg.pupil_id = p.id AND pg.is_primary = 1
                 JOIN guardians g ON g.id = pg.guardian_id
                 JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ? AND e.status = 'active'
                 JOIN classes c ON c.id = e.class_id
                 JOIN levels l ON l.id = c.level_id
                 WHERE p.status = 'active' AND p.deleted_at IS NULL";
        $rows = [];
        [$type, $val] = array_pad(explode(':', $audience, 2), 2, '');

        if ($type === 'parents' && $val === 'all') {
            $rows = db()->all($base, [$yid]);
        } elseif ($type === 'parents' && in_array($val, ['creche', 'kg', 'primary', 'jhs'], true)) {
            $rows = db()->all("$base AND l.category = ?", [$yid, $val]);
        } elseif ($type === 'class' && ctype_digit($val)) {
            $rows = db()->all("$base AND c.id = ?", [$yid, (int) $val]);
        } elseif ($type === 'level' && $levelId) {
            $rows = db()->all("$base AND l.id = ?", [$yid, $levelId]);
        } elseif ($type === 'staff') {
            $rows = db()->all("SELECT phone, name FROM users WHERE is_active = 1 AND phone IS NOT NULL AND phone <> '' AND role NOT IN ('parent','pupil')");
        } elseif ($type === 'custom') {
            foreach (preg_split('/[,;\r\n]+/', $custom) as $p) {
                $n = Admission::normalisePhone($p);
                if ($n) {
                    $rows[] = ['phone' => $n, 'name' => null];
                }
            }
        }

        $out = [];
        foreach ($rows as $r) {
            $phone = Admission::normalisePhone((string) $r['phone']);
            if ($phone && SmsGateway::validPhone($phone) && !isset($out[$phone])) {
                $out[$phone] = (string) ($r['name'] ?? '');
            }
        }
        return $out;
    }

    public static function label(string $audience): string
    {
        return self::options()[$audience] ?? $audience;
    }
}
