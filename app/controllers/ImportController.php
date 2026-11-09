<?php
declare(strict_types=1);

/** Import pupils & parents, opening balances and staff from Excel (.xlsx), Word (.docx, first table) or CSV. */
class ImportController extends Controller
{
    public function index(): void
    {
        $kinds = array_filter(Importer::KINDS, fn($k) => can($k['perm']));
        $this->view('imports/index', [
            'title' => 'Import from Excel or Word', 'kinds' => $kinds,
            'history' => db()->all('SELECT b.*, u.name AS by_name FROM import_batches b LEFT JOIN users u ON u.id = b.created_by ORDER BY b.id DESC LIMIT 20'),
            'year' => current_year(),
        ]);
    }

    public function template(): void
    {
        $kind = $this->kind((string) ($_GET['kind'] ?? ''));
        $k = Importer::KINDS[$kind];
        $format = isset($_GET['format']) ? Export::format() : 'xlsx';
        // Excel: the ready-made template (headings, dropdowns, instructions sheet) when present
        $xlsx = BASE_PATH . '/assets/templates/nisdas-import-' . $kind . '.xlsx';
        if ($format === 'xlsx' && is_file($xlsx)) {
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="nisdas-import-' . $kind . '.xlsx"');
            header('Content-Length: ' . filesize($xlsx));
            readfile($xlsx);
            exit;
        }
        if ($format !== 'csv') { // Word (fill the table, then upload), PDF (print and fill by hand), or Excel without the ready-made file
            Export::send('nisdas-import-' . $kind . '.' . $format, 'Import: ' . $k['label'], array_keys($k['columns']), [$k['example']], $format);
            exit;
        }
        $out = csv_start('nisdas-import-' . $kind . '.csv', array_keys($k['columns']));
        csv_row($out, $k['example']);
        csv_end($out);
    }

    public function upload(): void
    {
        $kind = $this->kind((string) ($_POST['kind'] ?? ''));
        if (!in_array($kind, ['staff', 'staff_records'], true) && !current_year()) {
            flash('error', 'Set the current academic year in School setup first.');
            redirect('imports');
        }
        $f = $_FILES['file'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name']) || $f['size'] > 5 * 1048576) {
            flash('error', 'Choose an Excel (.xlsx), Word (.docx) or CSV file of at most 5 MB.');
            redirect('imports');
        }
        [$rows, $err] = SheetReader::read($f['tmp_name'], (string) $f['name']);
        if ($err) {
            flash('error', $err);
            redirect('imports');
        }
        $needed = array_keys(array_filter(Importer::KINDS[$kind]['columns'], fn($v) => strpos($v, 'required') === 0));
        $missing = array_diff($needed, array_keys($rows[0] ?? []));
        if (!$rows || $missing) {
            flash('error', $rows ? 'These columns are missing from the first row: ' . implode(', ', $missing) . '. Download the template to see the expected headings.' : 'No data rows found.');
            redirect('imports');
        }
        $dir = BASE_PATH . '/storage/imports';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        foreach (glob($dir . '/*.json') ?: [] as $old) { // tidy up abandoned previews
            if (filemtime($old) < time() - 86400) {
                @unlink($old);
            }
        }
        $token = bin2hex(random_bytes(12));
        file_put_contents("$dir/$token.json", json_encode(['kind' => $kind, 'name' => mb_substr(basename((string) $f['name']), 0, 150), 'rows' => $rows, 'user' => Auth::id()]));
        $_SESSION['import_token'] = $token;
        redirect('imports/preview');
    }

    public function preview(): void
    {
        [$job] = $this->job();
        $checked = Importer::validate($job['kind'], $job['rows']);
        $this->view('imports/preview', [
            'title' => 'Check the import', 'job' => $job, 'checked' => $checked,
            'ok' => count(array_filter($checked, fn($c) => !$c['errors'])), 'bad' => count(array_filter($checked, fn($c) => (bool) $c['errors'])),
        ]);
    }

    public function commit(): void
    {
        [$job, $path] = $this->job();
        @set_time_limit(300);
        [$done, $skipped, $slips] = Importer::run($job['kind'], $job['rows']);
        @unlink($path);
        unset($_SESSION['import_token']);
        $bid = db()->insert('import_batches', ['kind' => $job['kind'], 'file_name' => $job['name'], 'rows_total' => count($job['rows']), 'rows_imported' => $done, 'rows_skipped' => $skipped, 'created_by' => Auth::id()]);
        audit('import_' . $job['kind'], 'import_batches', $bid, ['imported' => $done, 'skipped' => $skipped]);
        if ($slips) {
            $_SESSION['login_slips'] = $slips;
        }
        flash($done ? 'success' : 'warning', "$done row(s) imported" . ($skipped ? ", $skipped skipped because of errors" : '') . '.' . ($slips ? ' Print the sign-in slips now — passwords are shown only once.' : ''));
        redirect('imports', $slips ? ['slips' => 1] : []);
    }

    public function cancel(): void
    {
        if (!empty($_SESSION['import_token']) && preg_match('/^[a-f0-9]{24}$/', $_SESSION['import_token'])) {
            @unlink(BASE_PATH . '/storage/imports/' . $_SESSION['import_token'] . '.json');
        }
        unset($_SESSION['import_token']);
        redirect('imports');
    }

    /** @return array{0: array, 1: string} */
    private function job(): array
    {
        $t = (string) ($_SESSION['import_token'] ?? '');
        $path = BASE_PATH . '/storage/imports/' . $t . '.json';
        if (!preg_match('/^[a-f0-9]{24}$/', $t) || !is_file($path)) {
            flash('warning', 'Upload the file again.');
            redirect('imports');
        }
        $job = json_decode((string) file_get_contents($path), true);
        if (!is_array($job) || (int) $job['user'] !== Auth::id()) {
            redirect('imports');
        }
        $this->kind($job['kind']);
        return [$job, $path];
    }

    private function kind(string $kind): string
    {
        if (!isset(Importer::KINDS[$kind]) || !can(Importer::KINDS[$kind]['perm'])) {
            $this->notFound();
        }
        return $kind;
    }
}
