<?php
/**
 * Scheduled jobs: calendar and fee reminders, missed online-payment checks, queued SMS, daily backup.
 *
 * Windows Task Scheduler — run every 10 minutes:
 *   Program:   C:\xampp\php\php.exe
 *   Arguments: C:\xampp\htdocs\NISDASSMS\bin\cron.php
 *
 * Linux server crontab:
 *   (every 10 minutes)  php /var/www/NISDASSMS/bin/cron.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run from the command line.');
}
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

if (!is_installed()) {
    exit("Not installed yet.\n");
}

// Prevent overlapping runs
$lock = fopen(BASE_PATH . '/storage/cron.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit("Another run is in progress.\n");
}

$r = Scheduler::run();
try {
    if ($n = Security::sweepDormant()) {
        echo '[' . date('Y-m-d H:i') . "] switched off $n unused staff account(s)\n";
    }
} catch (Throwable $e) {
    error_log('[NISDAS] dormant sweep failed: ' . $e->getMessage());
}
setting_set('cron_last_run', gmdate('Y-m-d H:i:s'));
try {
    if ($bid = Backup::daily()) {
        echo '[' . date('Y-m-d H:i') . '] daily backup: ' . db()->value('SELECT file_name FROM backups WHERE id = ?', [$bid]) . "\n";
    }
} catch (Throwable $e) {
    error_log('[NISDAS] daily backup failed: ' . $e->getMessage());
    echo 'Daily backup FAILED: ' . $e->getMessage() . "\n";
}
printf("[%s] reminders queued: %d, sent: %d, failed: %d, held (Sabbath): %d\n", date('Y-m-d H:i'), $r['reminders'], $r['sent'], $r['failed'], $r['held']);
flock($lock, LOCK_UN);
