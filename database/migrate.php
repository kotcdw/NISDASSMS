<?php
/**
 * Apply new database migrations after updating the code (future sprints).
 * Run from the project folder:  C:\xampp\php\php.exe database\migrate.php
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run from the command line.');
}
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';
$applied = Migrator::run(db()->pdo());
echo $applied ? 'Applied: ' . implode(', ', $applied) . PHP_EOL : 'Database is up to date.' . PHP_EOL;
