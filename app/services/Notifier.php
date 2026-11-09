<?php
declare(strict_types=1);

/**
 * Notification engine used by every module.
 *  - Messages are queued in notification_outbox, then sent by process()
 *    (from the "Send now" button, the scheduled task bin/cron.php, or right after queueing).
 *  - Bulk/non-urgent SMS are held during Sabbath quiet hours (Friday evening → Saturday evening).
 */
final class Notifier
{
    /** Fill a template's {placeholders}. Returns null when the template is missing or switched off. */
    public static function render(string $key, array $vars): ?string
    {
        $tpl = db()->one('SELECT sms_body, is_active FROM notification_templates WHERE `key` = ?', [$key]);
        if (!$tpl || !(int) $tpl['is_active']) {
            return null;
        }
        $vars += ['school' => setting('school_short_name', 'NISDAS')];
        $body = preg_replace_callback('/\{(\w+)\}/', fn($m) => array_key_exists($m[1], $vars) ? (string) $vars[$m[1]] : $m[0], $tpl['sms_body']);
        return trim(preg_replace('/\s+/', ' ', (string) $body));
    }

    /**
     * Queue one SMS. $meta keys: name, event, entity, entity_id, urgent, batch_id, scheduled_at (UTC 'Y-m-d H:i:s').
     * Returns the outbox id, or null when the number is not a valid Ghana mobile number.
     */
    public static function sms(string $phone, string $body, array $meta = []): ?int
    {
        $phone = (string) Admission::normalisePhone($phone);
        if (!SmsGateway::validPhone($phone) || trim($body) === '') {
            return null;
        }
        $row = [
            'recipient'      => $phone,
            'recipient_name' => isset($meta['name']) ? mb_substr((string) $meta['name'], 0, 150) : null,
            'body'           => mb_substr($body, 0, 918), // 6 SMS parts max
            'event_key'      => $meta['event'] ?? null,
            'entity'         => $meta['entity'] ?? null,
            'entity_id'      => $meta['entity_id'] ?? null,
            'batch_id'       => $meta['batch_id'] ?? null,
            'is_urgent'      => !empty($meta['urgent']) ? 1 : 0,
            'scheduled_at'   => $meta['scheduled_at'] ?? gmdate('Y-m-d H:i:s'),
            'created_by'     => Auth::id(),
        ];
        // WhatsApp (Settings): SMS only, both, or WhatsApp first with SMS as the fallback
        $mode = !empty($meta['sms_only']) ? 'off' : WhatsappGateway::mode();
        $id = null;
        if ($mode !== 'off') {
            $id = db()->insert('notification_outbox', $row + ['channel' => 'whatsapp']);
        }
        if ($mode !== 'whatsapp_first') {
            $sid = db()->insert('notification_outbox', $row + ['channel' => 'sms']);
            $id = $id ?? $sid;
        }
        return $id;
    }

    /** Queue a templated SMS unless the same event was already sent for this record. */
    public static function smsTemplate(string $key, string $phone, array $vars, array $meta = []): ?int
    {
        if (isset($meta['entity'], $meta['entity_id']) && db()->value(
            "SELECT id FROM notification_outbox WHERE entity = ? AND entity_id = ? AND event_key = ? AND status <> 'cancelled' LIMIT 1",
            [$meta['entity'], $meta['entity_id'], $key]
        )) {
            return null;
        }
        $body = self::render($key, $vars);
        return $body === null ? null : self::sms($phone, $body, $meta + ['event' => $key]);
    }

    /** Hide temporary passwords once a login SMS has gone out (staff can read the outbox). */
    public static function redact(string $body): string
    {
        return (string) preg_replace('/(Password:\s*)\S+/i', '$1••••••', $body);
    }

    /** In-app notification (bell icon) for specific users. */
    public static function inapp(array $userIds, string $title, ?string $body = null, ?string $link = null): void
    {
        foreach (array_unique(array_map('intval', $userIds)) as $uid) {
            if ($uid > 0) {
                db()->insert('inapp_notifications', [
                    'user_id' => $uid, 'title' => mb_substr($title, 0, 150),
                    'body' => $body ? mb_substr($body, 0, 500) : null, 'link' => $link,
                ]);
            }
        }
        // Phone notification straight away for a few people; larger groups go out with the scheduled task
        $ids = array_values(array_filter(array_unique(array_map('intval', $userIds))));
        if ($ids && count($ids) <= 3) {
            try {
                WebPush::flush(10, $ids);
            } catch (Throwable $e) {
                // never block the page because a push service is slow or down
            }
        }
    }

    /** In-app notification to every active user holding a permission. */
    public static function inappPermission(string $permission, string $title, ?string $body = null, ?string $link = null): void
    {
        $roles = Auth::rolesWith($permission);
        if (!$roles) {
            return;
        }
        $in = implode(',', array_fill(0, count($roles), '?'));
        $ids = array_column(db()->all("SELECT id FROM users WHERE is_active = 1 AND role IN ($in)", $roles), 'id');
        self::inapp($ids, $title, $body, $link);
    }

    public static function inQuietHours(?DateTime $now = null): bool
    {
        if (setting('sabbath_quiet', '1') !== '1') {
            return false;
        }
        $now = $now ?: new DateTime('now', new DateTimeZone('Africa/Accra'));
        $day = (int) $now->format('N'); // 5 = Friday, 6 = Saturday
        $hm = $now->format('H:i');
        return ($day === 5 && $hm >= setting('sabbath_quiet_start', '18:00'))
            || ($day === 6 && $hm < setting('sabbath_quiet_end', '18:30'));
    }

    public static function segments(string $body): int
    {
        $len = mb_strlen($body);
        $gsm = !preg_match('/[^\x0A\x0D\x20-\x7E£¥èéùìòÇØøÅå_ÆæßÉ¤¡ÄÖÑÜ§¿äöñüà]/u', $body);
        [$single, $multi] = $gsm ? [160, 153] : [70, 67];
        return $len <= $single ? 1 : (int) ceil($len / $multi);
    }

    /**
     * Send queued messages that are due. Returns counts.
     * @return array{sent:int, failed:int, held:int}
     */
    public static function process(int $limit = 50): array
    {
        $stats = ['sent' => 0, 'failed' => 0, 'held' => 0];
        $quiet = self::inQuietHours();
        $rows = db()->all(
            "SELECT * FROM notification_outbox WHERE status = 'queued' AND scheduled_at <= UTC_TIMESTAMP()" . ($quiet ? ' AND is_urgent = 1' : '') . " ORDER BY is_urgent DESC, id LIMIT " . max(1, min(500, $limit))
        );
        if ($quiet) {
            $stats['held'] = (int) db()->value("SELECT COUNT(*) FROM notification_outbox WHERE status = 'queued' AND is_urgent = 0");
        }
        foreach ($rows as $m) {
            // Claim the row so two runs never send the same message twice
            $claimed = db()->run(
                "UPDATE notification_outbox SET attempts = attempts + 1 WHERE id = ? AND status = 'queued' AND attempts = ?",
                [$m['id'], $m['attempts']]
            )->rowCount();
            if (!$claimed) {
                continue;
            }
            $wa = ($m['channel'] ?? 'sms') === 'whatsapp';
            $res = $wa ? WhatsappGateway::send($m['recipient'], $m['body']) : SmsGateway::send($m['recipient'], $m['body']);
            if ($res['ok']) {
                db()->update('notification_outbox', [
                    'status' => 'sent', 'provider' => $res['provider'], 'provider_ref' => $res['ref'],
                    'sent_at' => gmdate('Y-m-d H:i:s'), 'last_error' => null,
                ] + ($m['event_key'] === 'parent_login' ? ['body' => self::redact($m['body'])] : []), 'id = ?', [$m['id']]);
                $stats['sent']++;
            } else {
                $final = ((int) $m['attempts'] + 1) >= ($wa ? 2 : 3);
                if ($wa && $final && WhatsappGateway::mode() === 'whatsapp_first') {
                    // Not on WhatsApp (or WhatsApp failed): send the same message by SMS
                    $copy = array_intersect_key($m, array_flip(['recipient', 'recipient_name', 'body', 'event_key', 'entity', 'entity_id', 'batch_id', 'is_urgent', 'created_by']));
                    db()->insert('notification_outbox', $copy + ['channel' => 'sms', 'scheduled_at' => gmdate('Y-m-d H:i:s')]);
                }
                db()->update('notification_outbox', [
                    'status' => $final ? 'failed' : 'queued', 'provider' => $res['provider'], 'last_error' => $res['error'],
                    // retry after 10 minutes
                    'scheduled_at' => gmdate('Y-m-d H:i:s', time() + 600),
                ], 'id = ?', [$m['id']]);
                $stats['failed']++;
            }
        }
        return $stats;
    }
}
