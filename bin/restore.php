<?php
/**
 * Restore a backup made by the system (replaces ALL current data).
 *
 *   C:\xampp\php\php.exe C:\xampp\htdocs\NISDASSMS\bin\restore.php C:\path\to\nisdas-20261004-180000-auto.zip
 *
 * A safety backup of the current data is made first.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run from the command line.');
}
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

$file = $argv[1] ?? '';
if ($file === '' || !is_file($file)) {
    exit("Usage: php bin/restore.php <backup .zip or .sql.gz file>\n");
}

// Read database.sql (and know where uploads are) from the backup
$sql = null;
$zip = null;
if (preg_match('/\.zip$/i', $file)) {
    if (!class_exists('ZipArchive')) {
        exit("PHP zip extension is not enabled. Enable extension=zip in php.ini.\n");
    }
    $zip = new ZipArchive();
    if ($zip->open($file) !== true || ($sql = $zip->getFromName('database.sql')) === false) {
        exit("This is not a NISDAS backup file (database.sql not found).\n");
    }
} elseif (preg_match('/\.sql\.gz$/i', $file)) {
    $sql = (string) gzdecode((string) file_get_contents($file));
} elseif (preg_match('/\.sql$/i', $file)) {
    $sql = (string) file_get_contents($file);
} else {
    exit("Unknown file type. Use the .zip (or .sql.gz) made by the system.\n");
}
if (strpos($sql, 'NISDAS School Management System database backup') === false) {
    exit("This file does not look like a NISDAS backup.\n");
}

echo "This will REPLACE all data in database '" . config('db.name') . "' with the backup:\n  $file\n";
echo "Type YES to continue: ";
$answer = trim((string) fgets(STDIN));
if ($answer !== 'YES') {
    exit("Cancelled.\n");
}

if (is_installed()) {
    try {
        $id = Backup::create('manual');
        echo "Safety backup of the current data: storage/backups/" . db()->value('SELECT file_name FROM backups WHERE id = ?', [$id]) . "\n";
    } catch (Throwable $e) {
        echo "Warning: could not make a safety backup (" . $e->getMessage() . ").\n";
    }
}

$pdo = db()->pdo();
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
// Start from an empty database so the backup comes back exactly as it was
foreach ($pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM) as [$t]) {
    $pdo->exec('DROP TABLE `' . str_replace('`', '``', $t) . '`');
}
$n = 0;
// Statements end with ";\n"; values never contain a raw new line (they are escaped as \n)
foreach (preg_split('/;\r?\n/', $sql) as $stmt) {
    $lines = array_filter(preg_split('/\r?\n/', $stmt), fn($l) => !preg_match('/^\s*--/', $l));
    $stmt = trim(implode("\n", $lines));
    if ($stmt === '') {
        continue;
    }
    $pdo->exec($stmt);
    $n++;
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
echo "Database restored ($n statements).\n";

if ($zip) {
    $count = 0;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (strpos($name, 'uploads/') !== 0 || substr($name, -1) === '/' || strpos($name, '..') !== false) {
            continue;
        }
        $target = BASE_PATH . '/storage/' . $name;
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0775, true);
        }
        file_put_contents($target, $zip->getFromIndex($i));
        $count++;
    }
    echo "Uploaded documents and photos restored ($count files).\n";
    // Key for 2-step sign-in secrets (Sprint 13+ backups)
    $key = $zip->getFromName('app.key');
    if ($key !== false && preg_match('/^[0-9a-f]{64}$/i', trim($key))) {
        $kf = BASE_PATH . '/storage/app.key';
        if (is_file($kf) && trim((string) file_get_contents($kf)) !== trim($key)) {
            copy($kf, $kf . '.before-restore');
        }
        file_put_contents($kf, trim($key));
        echo "2-step sign-in key restored.\n";
    }
}
if (!is_file(BASE_PATH . '/storage/installed.lock')) {
    file_put_contents(BASE_PATH . '/storage/installed.lock', date('c'));
}
echo "Done. Sign in again in the browser. If the backup is older than this version, sign in as Super Admin and click \"Update database now\".\n";
