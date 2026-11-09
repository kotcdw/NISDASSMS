<?php
declare(strict_types=1);

/** Minimal HTTPS client for SMS gateways (uses cURL when available). */
final class Http
{
    /** Form-encoded POST (e.g. Twilio). @return array{0:int,1:string,2:?string} [status code, body, error] */
    public static function postForm(string $url, array $fields, ?string $basicAuth = null, int $timeout = 20): array
    {
        return self::post($url, http_build_query($fields), ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'], $basicAuth, $timeout);
    }

    /** JSON POST. @return array{0:int,1:string,2:?string} [status code, body, error] */
    public static function postJson(string $url, array $body, array $headers = [], ?string $basicAuth = null, int $timeout = 20): array
    {
        return self::post($url, (string) json_encode($body, JSON_UNESCAPED_UNICODE), array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers), $basicAuth, $timeout);
    }

    /** One POST, by cURL when available, else PHP streams. */
    private static function post(string $url, string $body, array $headers, ?string $basicAuth, int $timeout): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => true]);
            if ($basicAuth !== null) {
                curl_setopt($ch, CURLOPT_USERPWD, $basicAuth);
            }
            $resp = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = $resp === false ? curl_error($ch) : null;
            curl_close($ch);
            return [$code, $resp === false ? '' : (string) $resp, $err];
        }
        if ($basicAuth !== null) {
            $headers[] = 'Authorization: Basic ' . base64_encode($basicAuth);
        }
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $body, 'timeout' => $timeout, 'ignore_errors' => true]]);
        $resp = @file_get_contents($url, false, $ctx);
        $code = 0;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
                $code = (int) $m[1];
            }
        }
        return [$code, $resp === false ? '' : (string) $resp, $resp === false ? 'Connection failed' : null];
    }
}
