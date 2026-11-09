<?php
declare(strict_types=1);

/** On-screen spreadsheet lists (Students, Users, Families …) exported as Excel, Word or PDF: the rows the person sees. */
class ExportController extends Controller
{
    private const MAX_BYTES = 8_000_000;
    private const MAX_ROWS = 50_000;

    public function table(): void
    {
        $format = Export::format();
        $raw = (string) ($_POST['data'] ?? '');
        $data = strlen($raw) <= self::MAX_BYTES ? json_decode($raw, true) : null;
        if ($format === 'csv' || !is_array($data) || !is_array($data['head'] ?? null) || !is_array($data['rows'] ?? null) || count($data['rows']) > self::MAX_ROWS) {
            http_response_code(400);
            exit('This list could not be exported. Reload the page and try again.');
        }
        $clean = fn(array $r) => array_map(fn($v) => is_scalar($v) ? mb_substr((string) $v, 0, 2000) : '', array_values($r));
        $head = $clean($data['head']);
        $rows = array_map(fn($r) => is_array($r) ? $clean($r) : [], $data['rows']);
        $file = preg_replace('/[^a-z0-9-]+/i', '-', (string) ($_POST['file'] ?? 'list')) ?: 'list';
        $title = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 120) ?: Export::titleFrom($file);
        audit('list_exported', 'export', null, ['file' => $file, 'format' => $format, 'rows' => count($rows)]);
        Export::send($file . '.' . $format, $title, $head, $rows, $format);
        exit;
    }
}
