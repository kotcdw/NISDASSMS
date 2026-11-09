<?php
declare(strict_types=1);

/**
 * WhatsApp messages to parents and staff. The provider is chosen in Notifications & SMS → Settings:
 *  - off    : WhatsApp not used
 *  - log    : test mode — recorded in the outbox, nothing leaves the server
 *  - meta   : WhatsApp Business Cloud API from Meta (access token + phone number ID + an approved template with one {{1}})
 *  - twilio : Twilio WhatsApp (Account SID + auth token + WhatsApp sender number)
 *  - hubtel : Hubtel (or another provider) with a JSON endpoint, client ID and secret
 * Read receipts come back to webhooks/whatsapp (Meta and Twilio status callbacks).
 */
final class WhatsappGateway
{
    public const PROVIDERS = ['off' => 'Not used', 'log' => 'Test mode (record only)', 'meta' => 'Meta WhatsApp Cloud API', 'twilio' => 'Twilio', 'hubtel' => 'Hubtel / other JSON API'];
    public const MODES = ['off' => 'SMS only', 'both' => 'WhatsApp and SMS', 'whatsapp_first' => 'WhatsApp first, SMS if WhatsApp fails'];

    public static function provider(): string
    {
        $p = setting('whatsapp_provider', 'off');
        return isset(self::PROVIDERS[$p]) ? $p : 'off';
    }

    /** How automatic messages go out: off (SMS only), both, whatsapp_first. */
    public static function mode(): string
    {
        $m = setting('whatsapp_mode', 'off');
        return self::provider() === 'off' || !isset(self::MODES[$m]) ? 'off' : $m;
    }

    /** @return array{ok:bool, ref:?string, error:?string, provider:string} */
    public static function send(string $phone, string $message): array
    {
        $provider = self::provider();
        $to = SmsGateway::international($phone); // 233244123456
        $res = ['ok' => false, 'ref' => null, 'error' => null, 'provider' => $provider];
        try {
            switch ($provider) {
                case 'meta':
                    $url = 'https://graph.facebook.com/v19.0/' . rawurlencode(setting('wa_meta_phone_id')) . '/messages';
                    $tpl = setting('wa_meta_template', 'school_notice');
                    [$code, $body, $err] = Http::postJson($url, [
                        'messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'template',
                        'template' => ['name' => $tpl, 'language' => ['code' => setting('wa_meta_language', 'en')],
                            'components' => [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => mb_substr($message, 0, 1000)]]]]],
                    ], ['Authorization: Bearer ' . setting('wa_meta_token')]);
                    $j = json_decode($body, true) ?: [];
                    if (!$err && $code >= 200 && $code < 300 && !empty($j['messages'][0]['id'])) {
                        return ['ok' => true, 'ref' => (string) $j['messages'][0]['id'], 'error' => null, 'provider' => $provider];
                    }
                    $res['error'] = $err ?: ('HTTP ' . $code . ' ' . ($j['error']['message'] ?? substr($body, 0, 120)));
                    break;
                case 'twilio':
                    $sid = setting('wa_twilio_sid');
                    [$code, $body, $err] = Http::postForm('https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($sid) . '/Messages.json', [
                        'From' => 'whatsapp:+' . ltrim(SmsGateway::international(setting('wa_twilio_from')), '+'), 'To' => 'whatsapp:+' . $to, 'Body' => $message,
                        'StatusCallback' => absolute_url('webhooks/whatsapp'),
                    ], $sid . ':' . setting('wa_twilio_token'));
                    $j = json_decode($body, true) ?: [];
                    if (!$err && $code >= 200 && $code < 300 && !empty($j['sid'])) {
                        return ['ok' => true, 'ref' => (string) $j['sid'], 'error' => null, 'provider' => $provider];
                    }
                    $res['error'] = $err ?: ('HTTP ' . $code . ' ' . ($j['message'] ?? substr($body, 0, 120)));
                    break;
                case 'hubtel':
                    [$code, $body, $err] = Http::postJson(setting('wa_hubtel_url'), ['From' => setting('wa_hubtel_from'), 'To' => $to, 'Content' => $message],
                        [], setting('wa_hubtel_client_id') . ':' . setting('wa_hubtel_client_secret'));
                    $j = json_decode($body, true) ?: [];
                    if (!$err && $code >= 200 && $code < 300) {
                        return ['ok' => true, 'ref' => (string) ($j['messageId'] ?? $j['MessageId'] ?? $j['id'] ?? ''), 'error' => null, 'provider' => $provider];
                    }
                    $res['error'] = $err ?: ('HTTP ' . $code . ' ' . substr($body, 0, 120));
                    break;
                case 'log':
                    return ['ok' => true, 'ref' => 'wa-test-' . bin2hex(random_bytes(4)), 'error' => null, 'provider' => 'log'];
                default:
                    $res['error'] = 'WhatsApp is switched off.';
            }
        } catch (Throwable $e) {
            $res['error'] = $e->getMessage();
        }
        $res['error'] = mb_substr((string) $res['error'], 0, 250);
        return $res;
    }

    /** Status update from the provider: sent / delivered / read / failed. */
    public static function status(string $ref, string $status, ?int $when = null): void
    {
        $status = strtolower($status);
        if ($ref === '' || !in_array($status, ['sent', 'delivered', 'read', 'failed', 'undelivered'], true)) {
            return;
        }
        $upd = ['delivery_status' => $status];
        if ($status === 'read') {
            $upd['read_at'] = gmdate('Y-m-d H:i:s', $when ?: time());
        }
        db()->update('notification_outbox', $upd, "provider_ref = ? AND channel = 'whatsapp' AND (delivery_status IS NULL OR delivery_status <> 'read')", [$ref]);

        // "WhatsApp first": the provider accepted the message but reports later that it could not be delivered
        // (usually the number is not on WhatsApp) — send the same message by SMS, once.
        if (in_array($status, ['failed', 'undelivered'], true) && self::mode() === 'whatsapp_first') {
            foreach (db()->all("SELECT * FROM notification_outbox WHERE provider_ref = ? AND channel = 'whatsapp'", [$ref]) as $m) {
                $copy = array_intersect_key($m, array_flip(['recipient', 'recipient_name', 'body', 'event_key', 'entity', 'entity_id', 'batch_id', 'is_urgent', 'created_by']));
                $dup = db()->value(
                    "SELECT 1 FROM notification_outbox WHERE channel = 'sms' AND recipient = ? AND body = ? AND created_at >= ? LIMIT 1",
                    [$m['recipient'], $m['body'], $m['created_at']]
                );
                if (!$dup) {
                    db()->insert('notification_outbox', $copy + ['channel' => 'sms', 'scheduled_at' => gmdate('Y-m-d H:i:s')]);
                }
            }
        }
    }
}
