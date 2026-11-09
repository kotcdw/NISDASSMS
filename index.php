<?php
/**
 * NISDAS School Management System — front controller.
 * Every request (except static assets) is routed through this file.
 */
declare(strict_types=1);

define('BASE_PATH', __DIR__);

require BASE_PATH . '/app/bootstrap.php';

if (!is_installed() && current_route() !== 'install') {
    header('Location: ' . app_base() . '/install.php');
    exit;
}

// A protected folder or file was asked for (see .htaccess): show a friendly page, never the file
if (isset($_GET['_blocked']) && ($_SERVER['SCRIPT_NAME'] ?? '') !== '' && current_route() !== '') {
    http_response_code(403);
    (new Controller())->view('errors/403', ['title' => 'Protected location', 'blocked' => true], auth() ? 'layouts/app' : 'layouts/public');
    exit;
}

// A new version was copied in: update the database by itself (safety backup first)
try {
    Migrator::autoUpdate(db()->pdo());
} catch (Throwable $e) {
    // the "Update database now" bar is the fallback
}

$router = new Router();
require BASE_PATH . '/app/routes.php';
// After a form that may have changed money records, bring the double-entry books up to date
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    register_shutdown_function(fn() => Ledger::afterRequest());
}
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', current_route());
