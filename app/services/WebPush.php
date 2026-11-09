<?php
declare(strict_types=1);

/**
 * Phone / computer notifications for the installed app (Web Push, RFC 8030/8291/8292) in plain PHP + OpenSSL:
 * VAPID (ES256-signed JWT) and "aes128gcm" payload encryption. Keys are created once and kept in settings.
 */
final class WebPush
{
    private const P256_SPKI_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    public static function b64u(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    public static function unb64u(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4));
    }

    /** VAPID public key (base64url, 65-byte uncompressed point) — created on first use. */
    public static function publicKey(): string
    {
        return self::keys()[1];
    }

    private static ?array $keys = null;

    private static function keys(): array
    {
        if (self::$keys) {
            return self::$keys;
        }
        // Read fresh from the database (the settings cache may be older than a key just created)
        $pem = (string) db()->value("SELECT `value` FROM settings WHERE `key` = 'vapid_private_pem'");
        $pub = (string) db()->value("SELECT `value` FROM settings WHERE `key` = 'vapid_public'");
        if ($pem === '' || $pub === '' || !openssl_pkey_get_private($pem)) {
            $k = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
            openssl_pkey_export($k, $pem);
            $d = openssl_pkey_get_details($k);
            $pub = self::b64u("\x04" . str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT));
            setting_set('vapid_private_pem', $pem);
            setting_set('vapid_public', $pub);
        }
        return self::$keys = [$pem, $pub];
    }

    /** PEM public key from a raw uncompressed P-256 point. */
    public static function pointToPem(string $point): string
    {
        $der = hex2bin(self::P256_SPKI_PREFIX) . $point;
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    /** DER ECDSA signature → raw r||s (64 bytes) as JWS ES256 needs. */
    public static function derToRaw(string $der): string
    {
        $pos = 2 + (ord($der[1]) & 0x80 ? (ord($der[1]) & 0x7f) : 0);
        $out = '';
        for ($i = 0; $i < 2; $i++) {
            $len = ord($der[$pos + 1]);
            $int = substr($der, $pos + 2, $len);
            $out .= str_pad(ltrim($int, "\0"), 32, "\0", STR_PAD_LEFT);
            $pos += 2 + $len;
        }
        return $out;
    }

    /** VAPID Authorization header for an endpoint. */
    public static function vapidHeader(string $endpoint): string
    {
        [$pem, $pub] = self::keys();
        $p = parse_url($endpoint);
        $aud = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
        $contact = setting('school_email') ?: 'admin@nisdasms.academy';
        $h = self::b64u(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $c = self::b64u(json_encode(['aud' => $aud, 'exp' => time() + 12 * 3600, 'sub' => 'mailto:' . $contact]));
        openssl_sign("$h.$c", $sig, openssl_pkey_get_private($pem), OPENSSL_ALGO_SHA256);
        return 'vapid t=' . "$h.$c." . self::b64u(self::derToRaw($sig)) . ', k=' . $pub;
    }

    /** Encrypt a payload for a subscription (RFC 8291, aes128gcm). Returns the request body. */
    public static function encrypt(string $payload, string $p256dhB64u, string $authB64u, ?string $salt = null, $localKey = null): string
    {
        $uaPub = self::unb64u($p256dhB64u);
        $auth = self::unb64u($authB64u);
        $local = $localKey ?: openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $ld = openssl_pkey_get_details($local);
        $asPub = "\x04" . str_pad($ld['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($ld['ec']['y'], 32, "\0", STR_PAD_LEFT);
        $shared = openssl_pkey_derive(openssl_pkey_get_public(self::pointToPem($uaPub)), $local, 32);
        $salt = $salt ?? random_bytes(16);
        $ikm = hash_hkdf('sha256', $shared, 32, "WebPush: info\0" . $uaPub . $asPub, $auth);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
        $ct = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        return $salt . pack('N', 4096) . chr(65) . $asPub . $ct . $tag;
    }

    /** Send one notification. @return array{ok:bool, code:int, gone:bool} */
    public static function send(array $sub, array $message): array
    {
        $body = self::encrypt(json_encode($message, JSON_UNESCAPED_UNICODE), $sub['p256dh'], $sub['auth']);
        $headers = ['Content-Type: application/octet-stream', 'Content-Encoding: aes128gcm', 'TTL: 86400', 'Urgency: normal',
            'Authorization: ' . self::vapidHeader($sub['endpoint'])];
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'code' => 0, 'gone' => false];
        }
        $ch = curl_init($sub['endpoint']);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6, CURLOPT_CONNECTTIMEOUT => 4]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['ok' => $code >= 200 && $code < 300, 'code' => $code, 'gone' => in_array($code, [404, 410], true)];
    }

    /** Push in-app notices not yet pushed (newest 2 days) to the recipients' devices. Returns how many were delivered. */
    public static function flush(int $limit = 100, ?array $onlyUsers = null): int
    {
        if (!self::ready()) {
            return 0;
        }
        $where = $onlyUsers ? ' AND n.user_id IN (' . implode(',', array_map('intval', $onlyUsers)) . ')' : '';
        $rows = db()->all(
            "SELECT n.* FROM inapp_notifications n WHERE n.pushed_at IS NULL AND n.read_at IS NULL AND n.created_at >= NOW() - INTERVAL 2 DAY
               AND EXISTS (SELECT 1 FROM push_subscriptions s WHERE s.user_id = n.user_id)$where ORDER BY n.id LIMIT " . (int) $limit
        );
        $sent = 0;
        foreach ($rows as $n) {
            db()->update('inapp_notifications', ['pushed_at' => date('Y-m-d H:i:s')], 'id = ?', [$n['id']]);
            foreach (db()->all('SELECT * FROM push_subscriptions WHERE user_id = ?', [$n['user_id']]) as $s) {
                $r = self::send($s, ['title' => $n['title'], 'body' => (string) ($n['body'] ?? ''), 'url' => url('notifications/' . $n['id']), 'tag' => 'n' . $n['id']]);
                if ($r['ok']) {
                    $sent++;
                    db()->update('push_subscriptions', ['last_used_at' => date('Y-m-d H:i:s')], 'id = ?', [$s['id']]);
                } elseif ($r['gone']) {
                    db()->run('DELETE FROM push_subscriptions WHERE id = ?', [$s['id']]); // the device unsubscribed
                }
            }
        }
        return $sent;
    }

    /** Push tables exist (migration 019) and OpenSSL can do the crypto. */
    public static function ready(): bool
    {
        static $ok = null;
        return $ok ??= function_exists('openssl_pkey_derive') && (bool) db()->value("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'inapp_notifications' AND column_name = 'pushed_at'");
    }
}
