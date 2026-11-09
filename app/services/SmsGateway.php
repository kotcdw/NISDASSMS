<?php
declare(strict_types=1);

/**
 * SMS providers used in Ghana. Choose one in Profile → App settings → Messaging settings.
 *  - log      : test mode, nothing leaves the server (default)
 *  - hubtel   : Hubtel SMS (Client ID + Client Secret)
 *  - arkesel  : Arkesel SMS v2 (API key)
 *  - mnotify  : mNotify / BMS quick SMS (API key)
 */
final class SmsGateway
{
    public const PROVIDERS = [
        'log'     => 'Test mode (do not send — record only)',
        'hubtel'  => 'Hubtel',
        'arkesel' => 'Arkesel',
        'mnotify' => 'mNotify (BMS)',
    ];

    public static function provider(): string
    {
        $p = setting('sms_provider', 'log');
        return isset(self::PROVIDERS[$p]) && !is_demo() ? $p : 'log';
    }

    /** 0244123456 -> 233244123456 */
    public static function international(string $phone): string
    {
        $p = preg_replace('/\D/', '', $phone);
        if (str_starts_with($p, '0') && strlen($p) === 10) {
            return '233' . substr($p, 1);
        }
        return $p;
    }

    public static function validPhone(string $phone): bool
    {
        return (bool) preg_match('/^0[235]\d{8}$/', $phone);
    }

    /** @return array{ok:bool, ref:?string, error:?string, provider:string} */
    public static function send(string $phone, string $message): array
    {
        $provider = self::provider();
        $sender = substr(preg_replace('/[^A-Za-z0-9 ]/', '', setting('sms_sender_id', 'NISDAS')), 0, 11) ?: 'NISDAS';
        $result = ['ok' => false, 'ref' => null, 'error' => null, 'provider' => $provider];

        try {
            switch ($provider) {
                case 'hubtel':
                    [$code, $body, $err] = Http::postJson(
                        'https://smsc.hubtel.com/v1/messages/send',
                        ['From' => $sender, 'To' => self::international($phone), 'Content' => $message],
                        [],
                        setting('sms_client_id') . ':' . setting('sms_client_secret')
                    );
                    $j = json_decode($body, true) ?: [];
                    if (!$err && $code >= 200 && $code < 300 && (int) ($j['status'] ?? $j['Status'] ?? 0) === 0) {
                        return ['ok' => true, 'ref' => (string) ($j['messageId'] ?? $j['MessageId'] ?? ''), 'error' => null, 'provider' => $provider];
                    }
                    $result['error'] = $err ?: ('HTTP ' . $code . ' ' . ($j['statusDescription'] ?? $j['message'] ?? substr($body, 0, 120)));
                    break;

                case 'arkesel':
                    [$code, $body, $err] = Http::postJson(
                        'https://sms.arkesel.com/api/v2/sms/send',
                        ['sender' => $sender, 'message' => $message, 'recipients' => [self::international($phone)]],
                        ['api-key: ' . setting('sms_api_key')]
                    );
                    $j = json_decode($body, true) ?: [];
                    if (!$err && $code >= 200 && $code < 300 && ($j['status'] ?? '') === 'success') {
                        $ref = $j['data'][0]['id'] ?? null;
                        return ['ok' => true, 'ref' => $ref ? (string) $ref : null, 'error' => null, 'provider' => $provider];
                    }
                    $result['error'] = $err ?: ('HTTP ' . $code . ' ' . ($j['message'] ?? substr($body, 0, 120)));
                    break;

                case 'mnotify':
                    $url = setting('sms_mnotify_url', 'https://api.mnotify.com/api/sms/quick');
                    [$code, $body, $err] = Http::postJson(
                        $url . (str_contains($url, '?') ? '&' : '?') . 'key=' . rawurlencode(setting('sms_api_key')),
                        ['recipient' => [$phone], 'sender' => $sender, 'message' => $message, 'is_schedule' => false, 'schedule_date' => '']
                    );
                    $j = json_decode($body, true) ?: [];
                    if (!$err && $code >= 200 && $code < 300 && ($j['status'] ?? '') === 'success') {
                        return ['ok' => true, 'ref' => (string) ($j['summary']['_id'] ?? ''), 'error' => null, 'provider' => $provider];
                    }
                    $result['error'] = $err ?: ('HTTP ' . $code . ' ' . ($j['message'] ?? substr($body, 0, 120)));
                    break;

                default: // log / test mode
                    return ['ok' => true, 'ref' => 'test-' . bin2hex(random_bytes(4)), 'error' => null, 'provider' => 'log'];
            }
        } catch (Throwable $e) {
            $result['error'] = $e->getMessage();
        }
        $result['error'] = mb_substr((string) $result['error'], 0, 250);
        return $result;
    }
    /** SMS credit left with the provider, where the provider offers it. @return array{ok:bool, message:string} */
    public static function balance(): array
    {
        $p = self::provider();
        try {
            if ($p === 'arkesel' || $p === 'mnotify') {
                if (setting('sms_api_key') === '') {
                    return ['ok' => false, 'message' => 'No API key saved yet.'];
                }
                $url = $p === 'arkesel' ? 'https://sms.arkesel.com/api/v2/clients/balance-details' : 'https://api.mnotify.com/api/balance/sms?key=' . rawurlencode(setting('sms_api_key'));
                $ch = curl_init($url);
                curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 8,
                    CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $p === 'arkesel' ? ['api-key: ' . setting('sms_api_key')] : [])]);
                $body = (string) curl_exec($ch);
                $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $err = curl_error($ch);
                curl_close($ch);
                $j = json_decode($body, true) ?: [];
                if ($code === 200 && (($j['status'] ?? '') === 'success' || isset($j['balance']))) {
                    $bal = $j['data']['sms_balance'] ?? $j['data']['balance'] ?? $j['balance'] ?? '?';
                    return ['ok' => true, 'message' => self::PROVIDERS[$p] . ' accepted the key. SMS balance: ' . (is_scalar($bal) ? $bal : json_encode($bal)) . '.'];
                }
                return ['ok' => false, 'message' => self::PROVIDERS[$p] . ' refused: ' . ($err ?: 'HTTP ' . $code . ' ' . ($j['message'] ?? ''))];
            }
            if ($p === 'hubtel') {
                return ['ok' => true, 'message' => 'Hubtel does not report the balance here — check it on the Hubtel dashboard, and use "Send a test SMS".'];
            }
            return ['ok' => false, 'message' => 'Test mode: no SMS provider is switched on.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
