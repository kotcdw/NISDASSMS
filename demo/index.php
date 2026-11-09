<?php
/**
 * NISDAS SMS — PRACTICE COPY (training). Same program, separate database (<name>_demo),
 * separate uploads and sign-in. SMS are never sent and online payments are always in test mode.
 * Created / reset from the real system: Administration → Practice copy (or php bin/demo.php).
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('DEMO_MODE', true);

require BASE_PATH . '/app/bootstrap.php';

if (config('practice_copy', true) === false) {
    http_response_code(404);
    exit('Not found');
}

try {
    db()->pdo();
    $ready = (bool) db()->value("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users'");
} catch (Throwable $e) {
    $ready = false;
}
if (!$ready) {
    http_response_code(503);
    echo '<!doctype html><meta charset="utf-8"><title>Practice copy not ready</title><body style="font-family:system-ui;max-width:560px;margin:4rem auto;line-height:1.6">'
        . '<h1>The practice copy has not been created yet</h1><p>Ask the Super Admin to open the real system and go to <strong>Administration → Practice copy</strong>, then press <em>Create the practice copy</em>.</p>'
        . '<p><a href="../">Go to the real system</a></p></body>';
    exit;
}

// The practice copy follows new versions by itself too (no backup needed: it can be rebuilt)
try {
    Migrator::autoUpdate(db()->pdo(), false);
} catch (Throwable $e) {
}

$router = new Router();
require BASE_PATH . '/app/routes.php';
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', current_route());
