<?php
declare(strict_types=1);

/**
 * Reads the first sheet of an Excel .xlsx file, the first table in a Word .docx file, or a .csv file, into rows
 * keyed by normalised column headings (e.g. "Date of birth" → date_of_birth). No libraries needed.
 * Title lines above the headings (as in NISDAS's own Excel and Word exports) are skipped.
 */
final class SheetReader
{
    public const MAX_ROWS = 3000;

    /** @return array{0: array<int,array<string,string>>, 1: ?string} rows, error */
    public static function read(string $path, string $originalName): array
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        try {
            if ($ext === 'xlsx' || $ext === 'docx') {
                if (!class_exists('ZipArchive')) {
                    return [[], 'Excel and Word files need the PHP zip extension. Save the sheet as CSV instead (File → Save As → CSV).'];
                }
                $grid = $ext === 'xlsx' ? self::xlsx($path) : self::docx($path);
            } elseif ($ext === 'csv' || $ext === 'txt') {
                $grid = self::csv($path);
            } else {
                return [[], 'Upload an Excel workbook (.xlsx), a Word document (.docx) with a table, or a CSV file. Old .xls / .doc files: open them and "Save As" .xlsx or .docx.'];
            }
        } catch (Throwable $e) {
            return [[], 'The file could not be read: ' . $e->getMessage()];
        }
        // First row with two or more filled cells = headings (skips blank lines and title lines above the table)
        while ($grid && count(array_filter($grid[0], fn($v) => trim((string) $v) !== '')) < 2 && count($grid) > 1) {
            array_shift($grid);
        }
        if (!$grid) {
            return [[], 'The sheet is empty.'];
        }
        $heads = array_map([self::class, 'key'], array_shift($grid));
        $rows = [];
        foreach ($grid as $i => $cells) {
            if (!array_filter($cells, fn($v) => trim((string) $v) !== '')) {
                continue;
            }
            $row = ['_line' => (string) ($i + 2)];
            foreach ($heads as $c => $h) {
                if ($h !== '') {
                    $row[$h] = trim((string) ($cells[$c] ?? ''));
                }
            }
            $rows[] = $row;
            if (count($rows) > self::MAX_ROWS) {
                return [[], 'Too many rows (max ' . self::MAX_ROWS . '). Split the file.'];
            }
        }
        return [$rows, null];
    }

    /** Column heading → field key: "First name (optional)" → "first_name". */
    public static function key(string $h): string
    {
        $h = strtolower(trim(preg_replace('/\s*\(.*?\)\s*/', ' ', $h))); // drop "(optional)" notes
        $h = preg_replace('/[^a-z0-9]+/', '_', $h);
        return trim((string) $h, '_');
    }

    private static function csv(string $path): array
    {
        $raw = (string) file_get_contents($path);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        if (!mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }
        $first = strtok($raw, "\n");
        $delim = substr_count((string) $first, ';') > substr_count((string) $first, ',') ? ';' : (substr_count((string) $first, "\t") > substr_count((string) $first, ',') ? "\t" : ',');
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $raw);
        rewind($fh);
        $grid = [];
        while (($r = fgetcsv($fh, 0, $delim, '"', '')) !== false) {
            $grid[] = $r;
        }
        fclose($fh);
        return $grid;
    }

    /** The first table in a Word document: one row per table row, one value per cell (merged cells keep their place). */
    private static function docx(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true || ($xml = $zip->getFromName('word/document.xml')) === false) {
            throw new RuntimeException('not a valid Word .docx file');
        }
        $zip->close();
        $doc = self::xml($xml);
        $doc->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $tables = $doc->xpath('//w:body//w:tbl');
        if (!$tables) {
            throw new RuntimeException('there is no table in the Word document — put the data in a table with the headings in the first row');
        }
        $grid = [];
        $tbl = $tables[0];
        $tbl->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        foreach ($tbl->xpath('./w:tr') as $tr) {
            $tr->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $row = [];
            foreach ($tr->xpath('./w:tc') as $tc) {
                $tc->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                $paras = [];
                foreach ($tc->xpath('.//w:p') as $p) {
                    $p->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                    $paras[] = implode('', array_map('strval', $p->xpath('.//w:t') ?: []));
                }
                $row[] = trim(implode("\n", $paras));
                $span = $tc->xpath('./w:tcPr/w:gridSpan');
                for ($i = 1, $n = $span ? (int) $span[0]->attributes('w', true)['val'] : 1; $i < $n; $i++) {
                    $row[] = '';
                }
            }
            $grid[] = $row;
            if (count($grid) > self::MAX_ROWS + 5) {
                break;
            }
        }
        return $grid;
    }

    private static function xlsx(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('not a valid .xlsx file');
        }
        $shared = [];
        if (($ss = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $x = self::xml($ss);
            foreach ($x->si as $si) {
                $t = '';
                if (isset($si->t)) {
                    $t = (string) $si->t;
                } else {
                    foreach ($si->r as $r) {
                        $t .= (string) $r->t;
                    }
                }
                $shared[] = $t;
            }
        }
        // First sheet listed in the workbook
        $sheetPath = 'xl/worksheets/sheet1.xml';
        $wb = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($wb !== false && $rels !== false) {
            $w = self::xml($wb);
            $rid = null;
            foreach ($w->sheets->sheet ?? [] as $s) {
                $rid = (string) $s->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                break;
            }
            foreach (self::xml($rels)->Relationship as $r) {
                if ((string) $r['Id'] === $rid) {
                    $target = ltrim((string) $r['Target'], '/');
                    $sheetPath = strpos($target, 'xl/') === 0 ? $target : 'xl/' . $target;
                }
            }
        }
        $sheet = $zip->getFromName($sheetPath);
        $zip->close();
        if ($sheet === false) {
            throw new RuntimeException('no worksheet found');
        }
        $grid = [];
        foreach (self::xml($sheet)->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $col = self::colIndex((string) $c['r']);
                $type = (string) $c['t'];
                if ($type === 's') {
                    $v = $shared[(int) $c->v] ?? '';
                } elseif ($type === 'inlineStr') {
                    $v = (string) $c->is->t;
                } elseif ($type === 'b') {
                    $v = (string) $c->v === '1' ? 'TRUE' : 'FALSE';
                } else {
                    $v = (string) $c->v;
                    // Whole numbers stored as 233244123456.0 etc.
                    if (preg_match('/^-?\d+\.0+$/', $v)) {
                        $v = preg_replace('/\.0+$/', '', $v);
                    }
                }
                $cells[$col] = $v;
            }
            if ($cells) {
                $max = max(array_keys($cells));
                $line = array_fill(0, $max + 1, '');
                foreach ($cells as $k => $v) {
                    $line[$k] = $v;
                }
                $grid[(int) $row['r'] - 1] = $line;
            }
        }
        ksort($grid);
        // keep row numbers meaningful by filling skipped rows
        $out = [];
        $last = -1;
        foreach ($grid as $r => $line) {
            for ($i = $last + 1; $i < $r; $i++) {
                $out[] = [];
            }
            $out[] = $line;
            $last = $r;
        }
        return $out;
    }

    private static function xml(string $s): SimpleXMLElement
    {
        $prev = libxml_use_internal_errors(true);
        $x = simplexml_load_string($s, 'SimpleXMLElement', LIBXML_NONET);
        libxml_use_internal_errors($prev);
        if ($x === false) {
            throw new RuntimeException('damaged file');
        }
        return $x;
    }

    private static function colIndex(string $ref): int
    {
        $letters = preg_replace('/\d+/', '', strtoupper($ref));
        $n = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $n = $n * 26 + (ord($letters[$i]) - 64);
        }
        return $n - 1;
    }

    /** Dates: Excel serial numbers, 2015-03-21, 21/03/2015, 21-03-2015, 21.03.2015 (day first, as used in Ghana). */
    public static function date(string $v): ?string
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        if (preg_match('/^\d{4,5}(\.\d+)?$/', $v) && (int) $v > 1000) {
            return gmdate('Y-m-d', ((int) $v - 25569) * 86400);
        }
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $v, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }
        if (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{2,4})$#', $v, $m)) {
            $y = (int) $m[3] < 100 ? 2000 + (int) $m[3] : (int) $m[3];
            if (checkdate((int) $m[2], (int) $m[1], $y)) {
                return sprintf('%04d-%02d-%02d', $y, $m[2], $m[1]);
            }
        }
        return null;
    }

    /** Ghana phone numbers, including ones Excel turned into numbers (244123456 → 0244123456). */
    public static function phone(string $v): ?string
    {
        $d = preg_replace('/[^\d+]/', '', $v);
        if (preg_match('/^[2-5]\d{8}$/', $d)) {
            $d = '0' . $d;
        }
        $p = Admission::normalisePhone($d);
        return $p && preg_match('/^0[2-5]\d{8}$/', $p) ? $p : null;
    }
}
