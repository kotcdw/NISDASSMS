<?php
declare(strict_types=1);

/**
 * Backups without needing mysqldump: the whole database as SQL (importable with phpMyAdmin too)
 * plus the uploaded documents and photos, in one .zip file under storage/backups.
 */
final class Backup
{
    public static function dir(): string
    {
        $d = BASE_PATH . (is_demo() ? '/storage/demo_backups' : '/storage/backups');
        if (!is_dir($d)) {
            @mkdir($d, 0775, true);
        }
        return $d;
    }

    /** Write the database as SQL into $fh (a file handle). Returns number of tables. */
    public static function dumpSql($fh): int
    {
        $pdo = db()->pdo();
        $w = function (string $s) use ($fh): void {
            fwrite($fh, $s);
        };
        $w("-- NISDAS School Management System database backup\n");
        $w('-- Created ' . date('Y-m-d H:i:s') . ' (' . config('timezone', 'Africa/Accra') . ")\n");
        $w("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\nSET time_zone = '+00:00';\n\n");
        $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
        foreach ($tables as [$table]) {
            $q = '`' . str_replace('`', '``', $table) . '`';
            $create = $pdo->query("SHOW CREATE TABLE $q")->fetch(PDO::FETCH_NUM)[1];
            $w("DROP TABLE IF EXISTS $q;\n$create;\n");
            $stmt = $pdo->query("SELECT * FROM $q");
            $batch = [];
            $cols = null;
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($cols === null) {
                    $cols = '(' . implode(', ', array_map(fn($c) => '`' . str_replace('`', '``', $c) . '`', array_keys($row))) . ')';
                }
                $batch[] = '(' . implode(', ', array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), array_values($row))) . ')';
                if (count($batch) >= 200) {
                    $w("INSERT INTO $q $cols VALUES\n" . implode(",\n", $batch) . ";\n");
                    $batch = [];
                }
            }
            if ($batch) {
                $w("INSERT INTO $q $cols VALUES\n" . implode(",\n", $batch) . ";\n");
            }
            $w("\n");
        }
        $w("SET FOREIGN_KEY_CHECKS = 1;\n");
        return count($tables);
    }

    /**
     * Make a backup. Returns the backups row id.
     * @throws RuntimeException when the file cannot be written
     */
    public static function create(string $kind = 'manual'): int
    {
        @set_time_limit(600);
        $stamp = date('Ymd-His');
        $dir = self::dir();
        $sqlTmp = $dir . "/tmp-$stamp-" . bin2hex(random_bytes(4)) . '.sql';
        $fh = fopen($sqlTmp, 'wb');
        if (!$fh) {
            throw new RuntimeException('Cannot write to storage/backups.');
        }
        $tables = self::dumpSql($fh);
        fclose($fh);

        if (class_exists('ZipArchive')) {
            $name = "nisdas-$stamp-$kind.zip";
            $zip = new ZipArchive();
            if ($zip->open("$dir/$name", ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                @unlink($sqlTmp);
                throw new RuntimeException('Cannot create the backup file.');
            }
            $zip->addFile($sqlTmp, 'database.sql');
            $up = uploads_dir();
            if (is_dir($up)) {
                $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($up, FilesystemIterator::SKIP_DOTS));
                foreach ($it as $f) {
                    if ($f->isFile() && $f->getFilename() !== '.gitkeep') {
                        $zip->addFile($f->getPathname(), 'uploads/' . str_replace('\\', '/', substr($f->getPathname(), strlen($up) + 1)));
                    }
                }
            }
            // Key that protects 2-step sign-in secrets: needed to restore them on another computer
            if (is_file(Crypto::keyFile())) {
                $zip->addFile(Crypto::keyFile(), 'app.key');
            }
            $zip->addFromString('README.txt', "NISDAS SMS backup made " . date('Y-m-d H:i') . ".\r\nRestore with: php bin\\restore.php <this file>\r\nor import database.sql in phpMyAdmin and copy the uploads folder to storage\\uploads.\r\n");
            $zip->close();
        } else {
            $name = "nisdas-$stamp-$kind.sql.gz";
            $in = fopen($sqlTmp, 'rb');
            $out = gzopen("$dir/$name", 'wb6');
            while (!feof($in)) {
                gzwrite($out, (string) fread($in, 1 << 20));
            }
            fclose($in);
            gzclose($out);
        }
        @unlink($sqlTmp);

        $copied = null;
        $copyDir = trim((string) setting('backup_copy_dir', ''));
        if ($copyDir !== '' && is_dir($copyDir) && is_writable($copyDir)) {
            if (@copy("$dir/$name", rtrim($copyDir, '/\\') . DIRECTORY_SEPARATOR . $name)) {
                $copied = $copyDir;
                self::prune(rtrim($copyDir, '/\\'));
            }
        }
        $id = db()->insert('backups', [
            'file_name' => $name, 'size_bytes' => (int) filesize("$dir/$name"), 'tables_count' => $tables,
            'kind' => $kind, 'copied_to' => $copied, 'created_by' => PHP_SAPI === 'cli' ? null : Auth::id(),
        ]);
        self::prune($dir);
        return $id;
    }

    /** Keep only the newest N backup files in a folder. */
    public static function prune(string $dir): void
    {
        $keep = max(3, (int) setting('backup_keep', '14'));
        $files = array_values(array_filter(glob($dir . '/nisdas-*') ?: [], fn($f) => (bool) preg_match('/nisdas-\d{8}-\d{6}-(auto|manual)\.(zip|sql\.gz)$/', $f)));
        rsort($files); // names start with the date, so newest first
        foreach (array_slice($files, $keep) as $f) {
            @unlink($f);
            if ($dir === self::dir()) {
                db()->run('DELETE FROM backups WHERE file_name = ?', [basename($f)]);
            }
        }
    }

    /** Daily automatic backup, after the configured hour (called by bin/cron.php). */
    public static function daily(): ?int
    {
        if (setting('backup_auto', '1') !== '1') {
            return null;
        }
        if ((int) date('G') < (int) setting('backup_hour', '18') || setting('backup_last_auto') === date('Y-m-d')) {
            return null;
        }
        setting_set('backup_last_auto', date('Y-m-d'));
        return self::create('auto');
    }

    public static function path(string $name): ?string
    {
        if (!preg_match('/^nisdas-\d{8}-\d{6}-(auto|manual)\.(zip|sql\.gz)$/', $name)) {
            return null;
        }
        $p = self::dir() . '/' . $name;
        return is_file($p) ? $p : null;
    }

    public static function size(int $bytes): string
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' MB' : max(1, round($bytes / 1024)) . ' KB';
    }
}
