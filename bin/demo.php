<?php
/**
 * Create or reset the PRACTICE COPY (training data) from the command line:
 *   C:\xampp\php\php.exe C:\xampp\htdocs\NISDASSMS\bin\demo.php
 * It uses a separate database (<name>_demo); the real records are never touched.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Run from the command line.\n");
}
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

echo 'This wipes and rebuilds the practice database "' . DemoSeeder::dbName() . "\". Type YES to continue: ";
if (trim((string) fgets(STDIN)) !== 'YES') {
    exit("Cancelled.\n");
}
try {
    echo DemoSeeder::build(), "\nOpen http://localhost/NISDASSMS/demo/ — every practice login uses the password ", DemoSeeder::PASSWORD, "\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Failed: ' . $e->getMessage() . "\n");
    exit(1);
}
