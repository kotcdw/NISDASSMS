<?php
declare(strict_types=1);

/** Delivery and read receipts from the WhatsApp provider (Meta Cloud API or Twilio). */
class WhatsappController extends Controller
{
    /** Meta webhook verification: GET ?hub.mode=subscribe&hub.verify_token=…&hub.challenge=… */
    public function verify(): void
    {
        $token = setting('wa_meta_verify_token');
        if ($token !== '' && ($_GET['hub_mode'] ?? '') === 'subscribe' && hash_equals($token, (string) ($_GET['hub_verify_token'] ?? ''))) {
            header('Content-Type: text/plain');
            echo preg_replace('/[^0-9A-Za-z_-]/', '', (string) ($_GET['hub_challenge'] ?? ''));
            exit;
        }
        http_response_code(403);
        exit;
    }

    public function hook(): void
    {
        $raw = (string) file_get_contents('php://input');
        if (isset($_SERVER['HTTP_X_TWILIO_SIGNATURE'])) {
            $tok = setting('wa_twilio_token');
            $params = $_POST;
            ksort($params);
            $data = absolute_url('webhooks/whatsapp');
            foreach ($params as $k => $v) {
                $data .= $k . $v;
            }
            if ($tok === '' || !hash_equals(base64_encode(hash_hmac('sha1', $data, $tok, true)), (string) $_SERVER['HTTP_X_TWILIO_SIGNATURE'])) {
                http_response_code(403);
                exit;
            }
            WhatsappGateway::status((string) ($_POST['MessageSid'] ?? ''), (string) ($_POST['MessageStatus'] ?? ''));
            http_response_code(204);
            exit;
        }
        $secret = setting('wa_meta_app_secret');
        if ($secret === '' || !hash_equals('sha256=' . hash_hmac('sha256', $raw, $secret), (string) ($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? ''))) {
            http_response_code(403);
            exit;
        }
        $j = json_decode($raw, true) ?: [];
        foreach ($j['entry'] ?? [] as $e) {
            foreach ($e['changes'] ?? [] as $c) {
                foreach ($c['value']['statuses'] ?? [] as $st) {
                    WhatsappGateway::status((string) ($st['id'] ?? ''), (string) ($st['status'] ?? ''), isset($st['timestamp']) ? (int) $st['timestamp'] : null);
                }
            }
        }
        http_response_code(200);
        echo 'ok';
        exit;
    }

    /** Save WhatsApp settings (Notifications & SMS → Settings). */
    public function settings(): void
    {
        $p = (string) ($_POST['whatsapp_provider'] ?? 'off');
        $m = (string) ($_POST['whatsapp_mode'] ?? 'off');
        if (!isset(WhatsappGateway::PROVIDERS[$p]) || !isset(WhatsappGateway::MODES[$m])) {
            flash('error', 'Choose a WhatsApp provider and how messages are sent.');
            redirect('messages/settings');
        }
        if ($p === 'hubtel' && ($u = trim((string) ($_POST['wa_hubtel_url'] ?? ''))) !== '' && !preg_match('#^https://#', $u)) {
            flash('error', 'The Hubtel / provider address must start with https://');
            redirect('messages/settings');
        }
        setting_set('whatsapp_provider', $p);
        setting_set('whatsapp_mode', $p === 'off' ? 'off' : $m);
        foreach (['wa_meta_phone_id', 'wa_meta_template', 'wa_meta_language', 'wa_meta_verify_token', 'wa_twilio_sid', 'wa_twilio_from', 'wa_hubtel_url', 'wa_hubtel_from', 'wa_hubtel_client_id'] as $k) {
            setting_set($k, mb_substr(trim((string) ($_POST[$k] ?? '')), 0, 190));
        }
        foreach (['wa_meta_token', 'wa_meta_app_secret', 'wa_twilio_token', 'wa_hubtel_client_secret'] as $k) {
            $v = trim((string) ($_POST[$k] ?? ''));
            if ($v !== '') {
                setting_set($k, mb_substr($v, 0, 500)); // secrets kept when left blank
            }
        }
        audit('whatsapp_settings_updated', 'settings', null, ['provider' => $p, 'mode' => $m]);
        flash('success', 'WhatsApp settings saved.' . ($p === 'log' ? ' Test mode: messages are recorded in the outbox but not sent.' : ''));
        redirect('messages/settings');
    }
}
