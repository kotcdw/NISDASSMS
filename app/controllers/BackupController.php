<?php
declare(strict_types=1);

/** Super Admin: backups (download, settings). Restoring is done from the command line for safety. */
class BackupController extends Controller
{
    public function index(): void
    {
        // Files on disk that are not listed (e.g. the safety backup made just before a restore)
        foreach (glob(Backup::dir() . '/nisdas-*') ?: [] as $f) {
            $n = basename($f);
            if (Backup::path($n) && !db()->value('SELECT id FROM backups WHERE file_name = ?', [$n])) {
                preg_match('/^nisdas-(\d{8})-(\d{6})-(auto|manual)/', $n, $m);
                db()->insert('backups', ['file_name' => $n, 'size_bytes' => (int) filesize($f), 'kind' => $m[3],
                    'created_at' => DateTime::createFromFormat('YmdHis', $m[1] . $m[2])->format('Y-m-d H:i:s')]);
            }
        }
        $files = db()->all('SELECT b.*, u.name AS by_name FROM backups b LEFT JOIN users u ON u.id = b.created_by ORDER BY b.id DESC LIMIT 60');
        foreach ($files as &$f) {
            $f['exists'] = Backup::path($f['file_name']) !== null;
        }
        unset($f);
        $copy = trim((string) setting('backup_copy_dir', ''));
        $this->view('backups/index', [
            'title' => 'Backups', 'files' => $files, 'zip' => class_exists('ZipArchive'),
            'copyOk' => $copy === '' ? null : (is_dir($copy) && is_writable($copy)),
            'cronLast' => setting('cron_last_run'),
        ]);
    }

    public function create(): void
    {
        try {
            $id = Backup::create('manual');
            $b = db()->one('SELECT * FROM backups WHERE id = ?', [$id]);
            audit('backup_created', 'backups', $id);
            flash('success', 'Backup made: ' . $b['file_name'] . ' (' . Backup::size((int) $b['size_bytes']) . ')' . ($b['copied_to'] ? ', also copied to ' . $b['copied_to'] : '') . '. Download it and keep a copy off this computer.');
        } catch (Throwable $e) {
            flash('error', 'Backup failed: ' . $e->getMessage());
        }
        redirect('backups');
    }

    public function download(int $id): void
    {
        $b = db()->one('SELECT * FROM backups WHERE id = ?', [$id]) ?? $this->notFound();
        $p = Backup::path($b['file_name']) ?? $this->notFound();
        audit('backup_downloaded', 'backups', $id);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $b['file_name'] . '"');
        header('Content-Length: ' . filesize($p));
        header('X-Content-Type-Options: nosniff');
        readfile($p);
        exit;
    }

    public function settings(): void
    {
        $dir = trim((string) ($_POST['backup_copy_dir'] ?? ''));
        if ($dir !== '' && (!is_dir($dir) || !is_writable($dir))) {
            flash('error', 'The folder "' . $dir . '" does not exist on this computer or cannot be written to. Create it first (e.g. on a USB drive or inside your Google Drive folder).');
            redirect('backups');
        }
        setting_set('backup_copy_dir', mb_substr($dir, 0, 250));
        setting_set('backup_auto', isset($_POST['backup_auto']) ? '1' : '0');
        setting_set('backup_keep', (string) max(3, min(90, (int) ($_POST['backup_keep'] ?? 14))));
        setting_set('backup_hour', (string) max(0, min(23, (int) ($_POST['backup_hour'] ?? 18))));
        audit('backup_settings_saved', 'settings');
        flash('success', 'Backup settings saved.');
        redirect('backups');
    }
}
