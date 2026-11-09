<?php
declare(strict_types=1);

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function verifyOrFail(): void
    {
        $sent = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!is_string($sent) || empty($_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], $sent)) {
            // 403 (not the non-standard 419, which some servers log as an internal error)
            http_response_code(403);
            header('Content-Type: text/html; charset=utf-8');
            header('Cache-Control: no-store');
            $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
            $sameSite = $ref !== '' && parse_url($ref, PHP_URL_HOST) === parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
            $back = $sameSite ? htmlspecialchars($ref, ENT_QUOTES, 'UTF-8') : '';
            echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Session expired</title>'
                . '<body style="font-family:system-ui,sans-serif;max-width:560px;margin:4rem auto;padding:0 1rem;line-height:1.6;color:#1f2937">'
                . '<h1 style="font-size:1.4rem">Session expired</h1><p>This form was open for too long, or you signed in again in another tab. Nothing was saved.</p>'
                . '<p>Go back, refresh the page and try again.</p>'
                . ($back !== '' ? '<p><a href="' . $back . '">← Back to the form</a></p>' : '')
                . '</body>';
            exit;
        }
    }
}
