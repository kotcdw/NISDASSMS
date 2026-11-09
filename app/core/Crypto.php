<?php
declare(strict_types=1);

/**
 * Encrypts small secrets (2-step sign-in keys) with AES-256-GCM.
 * Key: 'app_key' in config.local.php (64 hex characters) or, if not set, storage/app.key (created once).
 * The key is copied into every backup so a restore keeps 2-step sign-in working.
 */
final class Crypto
{
    public static function keyFile(): string
    {
        return BASE_PATH . '/storage/app.key';
    }

    private static function key(): string
    {
        static $key = null;
        if ($key !== null) {
            return $key;
        }
        $hex = (string) config('app_key', '');
        if (!preg_match('/^[0-9a-f]{64}$/i', $hex)) {
            $file = self::keyFile();
            if (is_file($file)) {
                $hex = trim((string) file_get_contents($file));
            }
            if (!preg_match('/^[0-9a-f]{64}$/i', $hex)) {
                $hex = bin2hex(random_bytes(32));
                if (@file_put_contents($file, $hex, LOCK_EX) === false) {
                    throw new RuntimeException('Cannot write storage/app.key — make the storage folder writable.');
                }
                @chmod($file, 0600);
            }
        }
        return $key = hex2bin($hex);
    }

    public static function encrypt(string $plain): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new RuntimeException('Encryption failed.');
        }
        return 'v1:' . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(?string $stored): ?string
    {
        if (!$stored || !str_starts_with($stored, 'v1:')) {
            return null;
        }
        $raw = base64_decode(substr($stored, 3), true);
        if ($raw === false || strlen($raw) < 29) {
            return null;
        }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $plain === false ? null : $plain;
    }
}
