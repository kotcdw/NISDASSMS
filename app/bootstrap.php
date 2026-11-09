<?php
declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

$GLOBALS['__config'] = require BASE_PATH . '/config/config.php';
// Practice copy (/demo or bin/demo.php): its own database and session, never the real records
if (defined('DEMO_MODE') && DEMO_MODE) {
    $GLOBALS['__config']['db']['name'] .= '_demo';
    $GLOBALS['__config']['session_name'] = ($GLOBALS['__config']['session_name'] ?? 'nisdas_sess') . '_demo';
    $GLOBALS['__config']['force_https'] = false;
}

date_default_timezone_set($GLOBALS['__config']['timezone'] ?? 'Africa/Accra');

if (!empty($GLOBALS['__config']['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
}

spl_autoload_register(function (string $class): void {
    foreach (['core', 'services', 'controllers', 'models'] as $dir) {
        $file = BASE_PATH . "/app/$dir/$class.php";
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});

require BASE_PATH . '/app/helpers.php';

// Secure session
if (session_status() !== PHP_SESSION_ACTIVE && PHP_SAPI !== 'cli') {
    $https = is_https();
    // Production: send everyone to the https:// address
    if (!$https && !empty($GLOBALS['__config']['force_https']) && !empty($_SERVER['HTTP_HOST'])) {
        $host = preg_replace('/[^a-z0-9.:-]/i', '', (string) $_SERVER['HTTP_HOST']);
        header('Location: https://' . $host . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
        exit;
    }
    session_name($GLOBALS['__config']['session_name'] ?? 'nisdas_sess');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => app_base() === '' ? '/' : app_base() . '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.sid_length', '48');
    ini_set('session.sid_bits_per_character', '6');
    session_start();
}

// Old input and validation errors survive exactly one redirect
$GLOBALS['__old'] = $_SESSION['_old'] ?? [];
$GLOBALS['__errors'] = $_SESSION['_errors'] ?? [];
unset($_SESSION['_old'], $_SESSION['_errors']);

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('X-Permitted-Cross-Domain-Policies: none');
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; frame-ancestors 'self'; form-action 'self'");
}

set_exception_handler(function (Throwable $e): void {
    // A money record dated in a closed month (month-end): explain instead of failing
    if ($e instanceof RuntimeException && $e->getMessage() === 'period_closed' && function_exists('flash') && session_status() === PHP_SESSION_ACTIVE) {
        flash('error', 'That date is in a closed month (closed up to ' . fmt_date(Finance::closedUntil()) . '). Use a later date, or ask the Head to reopen the month.');
        $back = $_SERVER['HTTP_REFERER'] ?? '';
        header('Location: ' . ($back !== '' && parse_url($back, PHP_URL_HOST) === explode(':', (string) ($_SERVER['HTTP_HOST'] ?? ''))[0] ? $back : url('finance/close')));
        exit;
    }
    error_log('[NISDAS] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    if (!empty($GLOBALS['__config']['debug'])) {
        echo '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES) . '</pre>';
    } else {
        echo '<h1>Something went wrong</h1><p>Please try again or contact the system administrator.</p>';
    }
});
