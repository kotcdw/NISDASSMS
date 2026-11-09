<?php
declare(strict_types=1);

/**
 * Applies database/migrations/*.sql in filename order, once each.
 * New sprints add new numbered files (002_attendance.sql, …).
 */
final class Migrator
{
    /** "Table/column/index/constraint already exists" errors that are safe to skip when re-running a migration. */
    private const ALREADY_DONE = [1050, 1060, 1061, 1826, 1022];

    public static function run(PDO $pdo): array
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS migrations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(190) NOT NULL UNIQUE,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $done = $pdo->query('SELECT filename FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
        $files = glob(BASE_PATH . '/database/migrations/*.sql') ?: [];
        sort($files);
        $applied = [];

        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $done, true)) {
                continue;
            }
            foreach (self::split((string) file_get_contents($file)) as $statement) {
                try {
                    $pdo->exec($statement);
                } catch (PDOException $e) {
                    // MySQL commits table changes one by one, so a file that stopped half-way leaves some
                    // changes behind. When it is run again, skip what is already there and carry on.
                    if (!in_array((int) ($e->errorInfo[1] ?? 0), self::ALREADY_DONE, true)) {
                        throw $e;
                    }
                }
            }
            $stmt = $pdo->prepare('INSERT INTO migrations (filename) VALUES (?)');
            $stmt->execute([$name]);
            $applied[] = $name;
        }
        return $applied;
    }

    /**
     * Bring the database up to date by itself when a new version is installed: one request takes a lock,
     * makes a safety backup, applies the new files and records it in the audit log. Returns the files applied,
     * or null when nothing was pending or another request is already doing it. Errors are logged and the
     * "Update database now" bar stays as a fallback.
     */
    public static function autoUpdate(PDO $pdo, bool $backup = true): ?array
    {
        if (!self::pending($pdo)) {
            return null;
        }
        try {
            if ((int) $pdo->query("SELECT GET_LOCK('nisdas_migrate', 0)")->fetchColumn() !== 1) {
                return null; // another request is updating right now
            }
        } catch (Throwable $e) {
            return null;
        }
        try {
            if (!self::pending($pdo)) {
                return null;
            }
            if ($backup && class_exists('Backup')) {
                try {
                    Backup::create('auto'); // safety copy before the database changes
                } catch (Throwable $e) {
                    error_log('[NISDAS] safety backup before update failed: ' . $e->getMessage());
                }
            }
            $applied = self::run($pdo);
            if ($applied && function_exists('audit')) {
                try {
                    audit('database_migrated', 'migrations', null, ['automatic' => true, 'files' => $applied]);
                } catch (Throwable $e) {
                }
            }
            return $applied;
        } catch (Throwable $e) {
            error_log('[NISDAS] automatic database update failed: ' . $e->getMessage());
            return null;
        } finally {
            try {
                $pdo->query("SELECT RELEASE_LOCK('nisdas_migrate')");
            } catch (Throwable $e) {
            }
        }
    }

    /** Migration files not yet applied (used to show an "update database" banner). */
    public static function pending(PDO $pdo): array
    {
        try {
            $done = $pdo->query('SELECT filename FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            return [];
        }
        $files = array_map('basename', glob(BASE_PATH . '/database/migrations/*.sql') ?: []);
        sort($files);
        return array_values(array_diff($files, $done));
    }

    /** Split an SQL file on semicolons at line ends, ignoring comment lines. */
    private static function split(string $sql): array
    {
        $lines = array_filter(preg_split('/\R/', $sql), fn($l) => !preg_match('/^\s*--/', $l));
        $parts = preg_split('/;\s*$/m', implode("\n", $lines));
        return array_values(array_filter(array_map('trim', $parts), fn($s) => $s !== ''));
    }
}
