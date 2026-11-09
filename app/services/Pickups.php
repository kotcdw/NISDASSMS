<?php
declare(strict_types=1);

/**
 * Gate pick-up check. Nobody but a child's own parent/guardian leaves with the child unless the parent approves:
 * by tapping the SMS link, in the parent portal, with a one-time code they gave the collector, or by phone to the office.
 * Security records the collector's ID, photo and signature; the parent gets an SMS when the child leaves.
 */
final class Pickups
{
    public const ID_TYPES = ['ghana_card' => 'Ghana Card', 'voter' => "Voter's ID", 'licence' => "Driver's licence", 'passport' => 'Passport', 'nhis' => 'NHIS card', 'other' => 'Other'];
    public const KINDS = ['guardian' => 'Parent / guardian', 'listed' => 'Allowed by parent', 'other' => 'Someone else'];
    public const STATUS = [
        'waiting'   => ['Waiting for parent', 'warn'],
        'approved'  => ['Approved — release', 'good'],
        'refused'   => ['Refused — do not release', 'bad'],
        'released'  => ['Released', 'info'],
        'expired'   => ['No answer — expired', 'bad'],
        'cancelled' => ['Cancelled', ''],
    ];
    public const VIA = ['guardian' => 'Parent collected', 'sms' => 'Parent (SMS link)', 'portal' => 'Parent (portal)', 'code' => 'One-time code', 'office' => 'Office after phone call'];
    /** Minutes a parent has to answer before the request expires. */
    public const ANSWER_MINUTES = 120;

    public static function ready(): bool
    {
        static $ok = null;
        try {
            return $ok ??= (bool) db()->value("SHOW TABLES LIKE 'pickups'");
        } catch (Throwable $e) {
            return $ok = false;
        }
    }

    /** Child with class (for the gate: no contact details). */
    public static function pupil(int $id): ?array
    {
        $yid = current_year()['id'] ?? 0;
        return db()->one(
            "SELECT p.id, p.first_name, p.middle_name, p.last_name, p.admission_no, p.photo_path, p.status, c.name AS class_name
             FROM pupils p LEFT JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ? LEFT JOIN classes c ON c.id = e.class_id
             WHERE p.id = ? AND p.deleted_at IS NULL", [$yid, $id]);
    }

    public static function search(string $q): array
    {
        $yid = current_year()['id'] ?? 0;
        return db()->all(
            "SELECT p.id, p.first_name, p.last_name, p.admission_no, p.photo_path, c.name AS class_name
             FROM pupils p LEFT JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ? LEFT JOIN classes c ON c.id = e.class_id
             WHERE p.deleted_at IS NULL AND p.status = 'active'
               AND (p.admission_no LIKE ? OR CONCAT_WS(' ', p.first_name, p.last_name) LIKE ? OR CONCAT_WS(' ', p.last_name, p.first_name) LIKE ?)
             ORDER BY p.last_name, p.first_name LIMIT 12", [$yid, "%$q%", "%$q%", "%$q%"]);
    }

    /** The child's parents/guardians, primary first. */
    public static function guardians(int $pupilId): array
    {
        return db()->all(
            'SELECT g.id, g.name, g.phone, g.user_id, pg.relationship, pg.is_primary FROM pupil_guardian pg JOIN guardians g ON g.id = pg.guardian_id
             WHERE pg.pupil_id = ? ORDER BY pg.is_primary DESC, g.id', [$pupilId]);
    }

    /** People the parent (or the office) has allowed to collect this child. */
    public static function people(int $pupilId): array
    {
        return db()->all(
            'SELECT pp.* FROM pickup_people pp JOIN pickup_people_pupils x ON x.person_id = pp.id WHERE x.pupil_id = ? AND pp.is_active = 1 ORDER BY pp.name', [$pupilId]);
    }

    /** Pupil ids of this parent user. */
    public static function childIds(int $userId): array
    {
        return array_map('intval', array_column(db()->all(
            'SELECT DISTINCT pg.pupil_id FROM guardians g JOIN pupil_guardian pg ON pg.guardian_id = g.id WHERE g.user_id = ?', [$userId]), 'pupil_id'));
    }

    public static function nextRef(Database $d): string
    {
        $prefix = 'PU-' . date('Y') . '-';
        $last = (string) $d->value('SELECT ref_no FROM pickups WHERE ref_no LIKE ? ORDER BY id DESC LIMIT 1', [$prefix . '%']);
        return $prefix . str_pad((string) ((int) substr($last, strlen($prefix)) + 1), 5, '0', STR_PAD_LEFT);
    }

    /** Save a signature drawn on the gate screen (PNG data URL). Returns the stored name or null. */
    public static function saveSignature(string $dataUrl): ?string
    {
        if (!preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m)) {
            return null;
        }
        $png = base64_decode($m[1], true);
        if ($png === false || strlen($png) < 200 || strlen($png) > 400000 || substr($png, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            return null;
        }
        $dir = uploads_dir() . '/pickups';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $name = bin2hex(random_bytes(16)) . '.png';
        return file_put_contents($dir . '/' . $name, $png) ? 'pickups/' . $name : null;
    }

    // ------------------------------------------------------------------ one-time codes

    /** Make a 6-digit code for one child; returns the code (shown once). */
    public static function makeCode(int $pupilId, string $forName, int $hours): string
    {
        $code = (string) random_int(100000, 999999);
        db()->insert('pickup_codes', ['pupil_id' => $pupilId, 'code_hash' => hash('sha256', $pupilId . ':' . $code), 'for_name' => $forName ?: null,
            'created_by' => Auth::id(), 'expires_at' => date('Y-m-d H:i:s', time() + $hours * 3600)]);
        return $code;
    }

    /** A valid, unused code for this child, or null. */
    public static function checkCode(int $pupilId, string $code): ?array
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6) {
            return null;
        }
        return db()->one('SELECT * FROM pickup_codes WHERE pupil_id = ? AND code_hash = ? AND used_at IS NULL AND expires_at > NOW()',
            [$pupilId, hash('sha256', $pupilId . ':' . $code)]);
    }

    // ------------------------------------------------------------------ approval

    /** Ask the parents: SMS with a one-tap link (and an in-app message for parents who use the portal). */
    public static function requestApproval(array $pu): void
    {
        $child = self::pupil((int) $pu['pupil_id']);
        $link = absolute_url('p/' . $pu['token']);
        $sent = 0;
        $users = [];
        foreach (self::guardians((int) $pu['pupil_id']) as $g) {
            if ($g['user_id']) {
                $users[] = (int) $g['user_id'];
            }
            if ($sent >= 2 || !$g['phone']) {
                continue;
            }
            $body = Notifier::render('pickup_request', ['guardian' => $g['name'], 'collector' => $pu['collector_name'], 'relationship' => $pu['relationship'] ?: 'no relationship given',
                'pupil' => $child['first_name'], 'link' => $link])
                ?? "Dear {$g['name']}, {$pu['collector_name']} is at the school gate to pick up {$child['first_name']}. Approve or refuse: $link";
            if (Notifier::sms((string) $g['phone'], $body, ['name' => $g['name'], 'event' => 'pickup_request', 'entity' => 'pickups', 'entity_id' => $pu['id'], 'urgent' => 1, 'sms_only' => true])) {
                $sent++;
            }
        }
        if ($users) {
            Notifier::inapp(array_values(array_unique($users)), 'Approve a pick-up: ' . $child['first_name'], $pu['collector_name'] . ' is at the gate to collect ' . $child['first_name'] . '.', 'pickup');
        }
        Notifier::process(10);
    }

    /** Record the parent's (or office's) answer. Returns false when the request is no longer waiting. */
    public static function decide(array $pu, bool $approve, string $via, ?int $userId, ?string $note = null): bool
    {
        if ($pu['status'] !== 'waiting' || ($pu['token_expires'] && $pu['token_expires'] < date('Y-m-d H:i:s') && $via !== 'office')) {
            return false;
        }
        $n = db()->run("UPDATE pickups SET status = ?, approval_via = ?, decided_by = ?, decided_note = ?, decided_at = NOW() WHERE id = ? AND status = 'waiting'",
            [$approve ? 'approved' : 'refused', $via, $userId, $note ? mb_substr($note, 0, 200) : null, $pu['id']])->rowCount();
        if ($n) {
            audit($approve ? 'pickup_approved' : 'pickup_refused', 'pickups', (int) $pu['id'], ['via' => $via]);
        }
        return $n > 0;
    }

    /** SMS the parents that the child has left the school with someone. */
    public static function notifyReleased(array $pu): void
    {
        $child = self::pupil((int) $pu['pupil_id']);
        $sent = 0;
        foreach (self::guardians((int) $pu['pupil_id']) as $g) {
            if ($sent >= 2 || !$g['phone']) {
                continue;
            }
            $body = Notifier::render('pickup_done', ['guardian' => $g['name'], 'pupil' => $child['first_name'], 'collector' => $pu['collector_name'], 'time' => date('g:ia')])
                ?? "Dear {$g['name']}, {$child['first_name']} was picked up by {$pu['collector_name']} at " . date('g:ia') . '.';
            if (Notifier::sms((string) $g['phone'], $body, ['name' => $g['name'], 'event' => 'pickup_done', 'entity' => 'pickups', 'entity_id' => $pu['id'], 'urgent' => 1, 'sms_only' => true])) {
                $sent++;
            }
        }
        Notifier::process(10);
    }

    /** Waiting requests nobody answered in time become "expired". */
    public static function expireOld(): void
    {
        db()->run("UPDATE pickups SET status = 'expired' WHERE status = 'waiting' AND token_expires IS NOT NULL AND token_expires < NOW()");
    }
}
