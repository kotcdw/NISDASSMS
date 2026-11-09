<?php
declare(strict_types=1);

/**
 * Safe file uploads: checks real MIME type (not the browser's), size,
 * stores under storage/uploads with a random name. Files are served only
 * through an authenticated controller, never directly.
 */
final class Upload
{
    /** @return array{0: ?array, 1: ?string} [file info, error] */
    public static function store(array $file, string $subdir): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [null, null];
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            return [null, 'Upload failed. Please try again.'];
        }
        $max = (int) config('uploads.max_bytes');
        if ($file['size'] > $max) {
            return [null, 'File is too large (max ' . round($max / 1048576) . ' MB).'];
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']) ?: '';
        $allowed = config('uploads.allowed', []);
        if (!isset($allowed[$mime])) {
            return [null, 'Only PDF, JPG or PNG files are allowed.'];
        }
        $subdir = preg_replace('/[^a-z0-9_-]/i', '', $subdir);
        $dir = uploads_dir() . '/' . $subdir;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return [null, 'Upload folder is not writable.'];
        }
        $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($file['tmp_name'], "$dir/$name")) {
            return [null, 'Could not save the file.'];
        }
        $original = mb_substr(preg_replace('/[^\w .()-]/u', '_', basename((string) $file['name'])), 0, 150);
        return [[
            'stored_name'   => "$subdir/$name",
            'original_name' => $original,
            'mime'          => $mime,
            'size'          => (int) $file['size'],
        ], null];
    }

    public static function path(string $storedName): ?string
    {
        if (!preg_match('#^[a-z0-9_-]+/[a-f0-9]{32}\.(pdf|jpg|png)$#i', $storedName)) {
            return null;
        }
        $p = uploads_dir() . '/' . $storedName;
        return is_file($p) ? $p : null;
    }

    public static function send(string $storedName, string $mime, string $downloadName): void
    {
        $p = self::path($storedName);
        if (!$p) {
            http_response_code(404);
            exit('File not found');
        }
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($p));
        header('Content-Disposition: inline; filename="' . str_replace('"', '', $downloadName) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($p);
        exit;
    }
}
