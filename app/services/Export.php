<?php
declare(strict_types=1);

/**
 * Every download in four formats from the same rows: Excel (.xlsx), Word (.docx), PDF and CSV.
 * Written in plain PHP (its own small zip and PDF writers), so nothing extra has to be installed.
 * Exports reach it through csv_start() / csv_row() / csv_end() with ?format=xlsx|docx|pdf (CSV otherwise),
 * so every module, and every future module that uses those helpers, offers all four formats.
 */
final class Export
{
    public const FORMATS = ['xlsx' => 'Excel (.xlsx)', 'docx' => 'Word (.docx)', 'pdf' => 'PDF', 'csv' => 'CSV (other systems)'];

    private const MIME = [
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'pdf'  => 'application/pdf',
    ];

    /** Helvetica / Helvetica-Bold widths (1/1000 em) for Windows-1252 characters 32–255. */
    private const W_REG = [278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584,761,556,278,222,556,333,1000,556,556,333,1000,667,333,1000,278,611,278,278,222,222,333,333,350,556,1000,333,1000,500,333,944,278,500,667,278,333,556,556,556,556,260,556,333,737,370,556,584,333,737,333,400,584,333,333,333,556,537,278,333,333,365,556,834,834,834,611,667,667,667,667,667,667,1000,722,667,667,667,667,278,278,278,278,722,722,778,778,778,778,778,584,778,722,722,722,722,667,667,611,556,556,556,556,556,556,889,500,556,556,556,556,278,278,278,278,556,556,556,556,556,556,556,584,611,556,556,556,556,500,556,500];
    private const W_BOLD = [278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500,389,280,389,584,761,556,278,278,556,500,1000,556,556,333,1000,667,333,1000,278,611,278,278,278,278,500,500,350,556,1000,333,1000,556,333,944,278,500,667,278,333,556,556,556,556,280,556,333,737,370,556,584,333,737,333,400,584,333,333,333,611,556,278,333,333,365,556,834,834,834,611,722,722,722,722,722,722,1000,722,667,667,667,667,278,278,278,278,722,722,778,778,778,778,778,584,778,722,722,722,722,667,667,611,556,556,556,556,556,556,889,556,556,556,556,556,278,278,278,278,611,611,611,611,611,611,611,584,611,611,611,611,611,556,611,556];

    /** The format asked for in the address (?format=…); CSV when none. */
    public static function format(): string
    {
        $f = strtolower((string) ($_GET['format'] ?? $_POST['format'] ?? 'csv'));
        return isset(self::FORMATS[$f]) ? $f : 'csv';
    }

    /** "fees-debtors-2026-11-02.csv" → "Fees debtors 2026-11-02" */
    public static function titleFrom(string $filename): string
    {
        $t = trim(preg_replace('/[-_]+/', ' ', preg_replace('/\.[a-z0-9]+$/i', '', $filename)));
        return $t === '' ? 'Export' : mb_strtoupper(mb_substr($t, 0, 1)) . mb_substr($t, 1);
    }

    /** Build the file in $format and send it to the browser. */
    public static function send(string $filename, string $title, ?array $head, array $rows, string $format): void
    {
        if ($head === null && $rows) {
            $head = array_shift($rows);
        }
        $head = array_map(fn($v) => self::text($v), $head ?? []);
        $cols = max(count($head), $rows ? max(array_map('count', $rows)) : 0, 1);
        $head = array_pad($head, $cols, '');
        $rows = array_map(fn($r) => array_pad(array_values($r), $cols, ''), $rows);
        $base = preg_replace('/\.[a-z0-9]+$/i', '', str_replace('"', '', $filename)) ?: 'export';
        $body = match ($format) {
            'xlsx' => self::xlsx($title, $head, $rows),
            'docx' => self::docx($title, $head, $rows),
            'pdf' => self::pdf($title, $head, $rows),
        };
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: ' . self::MIME[$format]);
        header('Content-Disposition: attachment; filename="' . $base . '.' . $format . '"');
        header('Content-Length: ' . strlen($body));
        echo $body;
    }

    // ------------------------------------------------------------------ values

    private static function text($v): string
    {
        if ($v === null) {
            return '';
        }
        if (is_bool($v)) {
            return $v ? 'Yes' : 'No';
        }
        return (string) $v;
    }

    /** A real number for Excel: ints, floats and plain number text without leading zeros (phone numbers stay text). */
    private static function number($v): ?string
    {
        if (is_int($v) || is_float($v)) {
            return is_finite((float) $v) ? (string) $v : null;
        }
        if (is_string($v) && preg_match('/^-?(0|[1-9]\d{0,13})(\.\d{1,6})?$/', $v)) {
            return $v;
        }
        return null;
    }

    private static function xml(string $s): string
    {
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s) ?? '';
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function subtitle(): string
    {
        return school_name() . ' · exported ' . date('j M Y, H:i') . (function_exists('auth') && auth() ? ' by ' . auth()['name'] : '');
    }

    // ------------------------------------------------------------------ zip (stored, no extension needed)

    private static function zip(array $files): string
    {
        $data = '';
        $dir = '';
        foreach ($files as $name => $content) {
            $crc = crc32($content);
            $len = strlen($content);
            $off = strlen($data);
            $data .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, 0x21, $crc, $len, $len, strlen($name), 0) . $name . $content;
            $dir .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, 0, 0x21, $crc, $len, $len, strlen($name), 0, 0, 0, 0, 0, $off) . $name;
        }
        return $data . $dir . pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), strlen($dir), strlen($data), 0);
    }

    // ------------------------------------------------------------------ Excel

    private static function col(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26) . $s;
        }
        return $s;
    }

    private static function xlsx(string $title, array $head, array $rows): string
    {
        $cols = count($head);
        $cell = function (int $c, int $r, $v, int $style = 0): string {
            $ref = self::col($c) . $r;
            $n = self::number($v);
            if ($n !== null && $style === 0) {
                return '<c r="' . $ref . '"' . (str_contains($n, '.') ? ' s="3"' : '') . '><v>' . $n . '</v></c>';
            }
            $t = self::text($v);
            return $t === '' ? '' : '<c r="' . $ref . '" t="inlineStr"' . ($style ? ' s="' . $style . '"' : '') . '><is><t xml:space="preserve">' . self::xml($t) . '</t></is></c>';
        };
        $widths = [];
        foreach ($head as $i => $h) {
            $widths[$i] = mb_strlen($h) + 2;
        }
        foreach ($rows as $r) {
            foreach ($r as $i => $v) {
                $widths[$i] = max($widths[$i] ?? 8, min(60, mb_strlen(self::text($v)) + 2));
            }
        }
        $xml = '<row r="1">' . $cell(0, 1, $title, 2) . '</row><row r="2">' . $cell(0, 2, self::subtitle(), 4) . '</row>';
        $xml .= '<row r="4">';
        foreach ($head as $i => $h) {
            $xml .= $cell($i, 4, $h === '' ? ' ' : $h, 1);
        }
        $xml .= '</row>';
        foreach ($rows as $k => $r) {
            $n = $k + 5;
            $xml .= '<row r="' . $n . '">';
            foreach ($r as $i => $v) {
                $xml .= $cell($i, $n, $v);
            }
            $xml .= '</row>';
        }
        $last = self::col($cols - 1) . (4 + max(1, count($rows)));
        $colsXml = '';
        foreach ($widths as $i => $w) {
            $colsXml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . max(8, min(60, $w)) . '" customWidth="1"/>';
        }
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="4" topLeftCell="A5" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<cols>' . $colsXml . '</cols><sheetData>' . $xml . '</sheetData>'
            . ('<autoFilter ref="A4:' . $last . '"/>')
            . '<pageMargins left="0.5" right="0.5" top="0.6" bottom="0.6" header="0.3" footer="0.3"/>'
            . '<pageSetup orientation="' . ($cols > 6 ? 'landscape' : 'portrait') . '" fitToWidth="1" fitToHeight="0"/></worksheet>';
        $name = mb_substr(trim(preg_replace('/[\[\]:*?\/\\\\]/', ' ', $title)) ?: 'Export', 0, 31);
        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="4"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FF16267A"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="14"/><color rgb="FF16267A"/><name val="Calibri"/></font><font><i/><sz val="9"/><color rgb="FF667085"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE8EDF7"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left/><right/><top/><bottom style="thin"><color rgb="FF16267A"/></bottom><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="5"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
        return self::zip([
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<sheets><sheet name="' . self::xml($name) . '" sheetId="1" r:id="rId1"/></sheets>'
                . '<definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">\'' . self::xml(str_replace("'", "''", $name)) . '\'!$A$4:$' . self::col($cols - 1) . '$' . (4 + max(1, count($rows))) . '</definedName>'
                . '<definedName name="_xlnm.Print_Titles" localSheetId="0">\'' . self::xml(str_replace("'", "''", $name)) . '\'!$4:$4</definedName></definedNames></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml' => $styles,
            'xl/worksheets/sheet1.xml' => $sheet,
        ]);
    }

    // ------------------------------------------------------------------ Word

    private static function docx(string $title, array $head, array $rows): string
    {
        $cols = count($head);
        $wide = $cols > 6;
        $sz = $cols > 10 ? 14 : ($cols > 6 ? 16 : 18); // half-points
        $p = fn(string $t, string $rpr = '', string $ppr = '') => '<w:p><w:pPr>' . $ppr . '<w:spacing w:after="60"/></w:pPr><w:r><w:rPr>' . $rpr . '</w:rPr><w:t xml:space="preserve">' . self::xml($t) . '</w:t></w:r></w:p>';
        $tc = function ($v, bool $h) use ($sz): string {
            $t = self::text($v);
            $num = !$h && self::number($v) !== null;
            return '<w:tc><w:tcPr>' . ($h ? '<w:shd w:val="clear" w:color="auto" w:fill="E8EDF7"/>' : '') . '</w:tcPr><w:p><w:pPr><w:spacing w:before="20" w:after="20"/>' . ($num ? '<w:jc w:val="right"/>' : '')
                . '</w:pPr><w:r><w:rPr>' . ($h ? '<w:b/><w:color w:val="16267A"/>' : '') . '<w:sz w:val="' . $sz . '"/></w:rPr><w:t xml:space="preserve">' . self::xml($t) . '</w:t></w:r></w:p></w:tc>';
        };
        // Column widths in proportion to their content (like the PDF), so long notes get room
        $nat = [];
        foreach ($head as $i => $h) {
            $nat[$i] = max(4, min(mb_strlen($h), 18));
        }
        foreach ($rows as $r) {
            foreach ($r as $i => $v) {
                $nat[$i] = max($nat[$i], min(mb_strlen(self::text($v)), 45));
            }
        }
        $page = $wide ? 15398 : 10106; // twips between the margins
        $tw = array_map(fn($n) => (int) floor($n * $page / array_sum($nat)), $nat);
        $grid = '<w:tblGrid>' . implode('', array_map(fn($w) => '<w:gridCol w:w="' . $w . '"/>', $tw)) . '</w:tblGrid>';
        $tbl = '<w:tbl><w:tblPr><w:tblW w:w="' . array_sum($tw) . '" w:type="dxa"/><w:tblLayout w:type="fixed"/><w:tblBorders>'
            . '<w:top w:val="single" w:sz="4" w:color="C9D2E3"/><w:bottom w:val="single" w:sz="4" w:color="C9D2E3"/><w:insideH w:val="single" w:sz="4" w:color="DDE3EE"/>'
            . '<w:left w:val="single" w:sz="4" w:color="C9D2E3"/><w:right w:val="single" w:sz="4" w:color="C9D2E3"/><w:insideV w:val="single" w:sz="4" w:color="DDE3EE"/>'
            . '</w:tblBorders><w:tblCellMar><w:left w:w="70" w:type="dxa"/><w:right w:w="70" w:type="dxa"/></w:tblCellMar></w:tblPr>' . $grid;
        $tbl .= '<w:tr><w:trPr><w:tblHeader/></w:trPr>';
        foreach ($head as $h) {
            $tbl .= $tc($h, true);
        }
        $tbl .= '</w:tr>';
        foreach ($rows as $r) {
            $tbl .= '<w:tr><w:trPr><w:cantSplit/></w:trPr>';
            foreach ($r as $v) {
                $tbl .= $tc($v, false);
            }
            $tbl .= '</w:tr>';
        }
        if (!$rows) {
            $tbl .= '<w:tr>' . str_repeat($tc('', false), $cols) . '</w:tr>';
        }
        $tbl .= '</w:tbl>';
        $sect = $wide
            ? '<w:sectPr><w:pgSz w:w="16838" w:h="11906" w:orient="landscape"/><w:pgMar w:top="720" w:right="720" w:bottom="720" w:left="720" w:header="360" w:footer="360" w:gutter="0"/></w:sectPr>'
            : '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="900" w:right="900" w:bottom="900" w:left="900" w:header="360" w:footer="360" w:gutter="0"/></w:sectPr>';
        $doc = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
            . $p($title, '<w:b/><w:color w:val="16267A"/><w:sz w:val="32"/>')
            . $p(self::subtitle(), '<w:i/><w:color w:val="667085"/><w:sz w:val="18"/>')
            . $tbl . '<w:p/>' . $sect . '</w:body></w:document>';
        return self::zip([
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>',
            'word/document.xml' => $doc,
        ]);
    }

    // ------------------------------------------------------------------ PDF

    /** UTF-8 → Windows-1252 for the built-in PDF fonts (GH₵ is shown as GH¢). */
    private static function win(string $s): string
    {
        $s = strtr($s, ['₵' => '¢', '→' => '->', '←' => '<-', '✓' => 'v', '✔' => 'v', '✗' => 'x', '✕' => 'x', '≥' => '>=', '≤' => '<=', '−' => '-', '⇩' => '', '▾' => '', '…' => '...']);
        $out = @mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
        return is_string($out) ? $out : preg_replace('/[^\x20-\x7E]/', '?', $s);
    }

    private static function width(string $s, float $size, bool $bold): float
    {
        $w = 0;
        $t = $bold ? self::W_BOLD : self::W_REG;
        for ($i = 0, $n = strlen($s); $i < $n; $i++) {
            $c = ord($s[$i]);
            $w += $c >= 32 ? $t[$c - 32] : 0;
        }
        return $w * $size / 1000;
    }

    /** Break text (Windows-1252) into lines that fit $max points. */
    private static function wrap(string $s, float $max, float $size, bool $bold): array
    {
        $lines = [];
        foreach (preg_split('/\r\n|\r|\n/', $s) as $para) {
            $line = '';
            foreach (preg_split('/ +/', $para) as $word) {
                $try = $line === '' ? $word : $line . ' ' . $word;
                if (self::width($try, $size, $bold) <= $max) {
                    $line = $try;
                    continue;
                }
                if ($line !== '') {
                    $lines[] = $line;
                }
                while (self::width($word, $size, $bold) > $max && strlen($word) > 1) { // a very long word: cut it
                    $k = strlen($word);
                    while ($k > 1 && self::width(substr($word, 0, $k), $size, $bold) > $max) {
                        $k--;
                    }
                    $lines[] = substr($word, 0, $k);
                    $word = substr($word, $k);
                }
                $line = $word;
            }
            $lines[] = $line;
        }
        return $lines ?: [''];
    }

    private static function esc(string $s): string
    {
        return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', ''], $s);
    }

    private static function pdf(string $title, array $head, array $rows): string
    {
        $cols = count($head);
        $land = $cols > 6;
        [$pw, $ph] = $land ? [841.89, 595.28] : [595.28, 841.89];
        $m = 36.0;
        $avail = $pw - 2 * $m;
        $size = $cols > 12 ? 6.5 : ($cols > 8 ? 7.5 : 8.5);
        $lh = $size * 1.25;
        $pad = 3.0;
        $H = array_map(fn($v) => self::win(self::text($v)), $head);
        $R = array_map(fn($r) => array_map(fn($v) => self::win(self::text($v)), $r), $rows);
        // Column widths: natural width of the longest text (capped), then scaled to the page
        $nat = [];
        foreach ($H as $i => $h) {
            $long = 0;
            foreach (preg_split('/ +/', $h) as $w) {
                $long = max($long, self::width($w, $size, true));
            }
            $nat[$i] = max($long, min(self::width($h, $size, true), 90)) + 2 * $pad;
        }
        foreach ($R as $r) {
            foreach ($r as $i => $v) {
                $nat[$i] = max($nat[$i], min(self::width($v, $size, false), 220) + 2 * $pad);
            }
        }
        $total = array_sum($nat);
        $cw = array_map(fn($w) => $w * $avail / $total, $nat);
        $numeric = [];
        foreach (array_keys($H) as $i) {
            $vals = array_filter(array_column($rows, $i), fn($v) => self::text($v) !== '');
            $numeric[$i] = $vals && count(array_filter($vals, fn($v) => self::number($v) !== null)) === count($vals);
        }
        // Lay out pages
        $pages = [];
        $ops = '';
        $y = 0.0;
        $drawRow = function (array $cells, bool $bold, bool $shade) use (&$ops, &$y, $cw, $m, $size, $lh, $pad, $numeric): void {
            $wrapped = [];
            $lines = 1;
            foreach ($cells as $i => $t) {
                $wrapped[$i] = self::wrap($t, $cw[$i] - 2 * $pad, $size, $bold);
                $lines = max($lines, count($wrapped[$i]));
            }
            $h = $lines * $lh + 2 * $pad;
            if ($shade) {
                $ops .= sprintf("0.910 0.929 0.969 rg %.2F %.2F %.2F %.2F re f\n", $m, $y - $h, array_sum($cw), $h);
            }
            $x = $m;
            $ops .= $bold ? "0.086 0.149 0.478 rg\n" : "0.1 0.1 0.12 rg\n";
            foreach ($wrapped as $i => $ls) {
                foreach ($ls as $k => $l) {
                    $tx = $x + $pad;
                    if (!$bold && $numeric[$i]) {
                        $tx = $x + $cw[$i] - $pad - self::width($l, $size, false);
                    }
                    $ops .= sprintf("BT /%s %.2F Tf %.2F %.2F Td (%s) Tj ET\n", $bold ? 'F2' : 'F1', $size, $tx, $y - $pad - $size - $k * $lh + 1, self::esc($l));
                }
                $x += $cw[$i];
            }
            $ops .= sprintf("0.82 0.85 0.91 RG 0.5 w %.2F %.2F m %.2F %.2F l S\n", $m, $y - $h, $m + array_sum($cw), $y - $h);
            $y -= $h;
        };
        $rowHeight = function (array $cells, bool $bold) use ($cw, $size, $lh, $pad): float {
            $lines = 1;
            foreach ($cells as $i => $t) {
                $lines = max($lines, count(self::wrap($t, $cw[$i] - 2 * $pad, $size, $bold)));
            }
            return $lines * $lh + 2 * $pad;
        };
        $newPage = function (bool $first) use (&$ops, &$y, &$pages, $ph, $m, $title, $H, $drawRow): void {
            if ($ops !== '') {
                $pages[] = $ops;
            }
            $ops = '';
            $y = $ph - $m;
            if ($first) {
                $ops .= sprintf("0.086 0.149 0.478 rg BT /F2 15 Tf %.2F %.2F Td (%s) Tj ET\n", $m, $y - 15, self::esc(self::win($title)));
                $ops .= sprintf("0.40 0.44 0.52 rg BT /F1 8.5 Tf %.2F %.2F Td (%s) Tj ET\n", $m, $y - 29, self::esc(self::win(self::subtitle())));
                $y -= 42;
            }
            $drawRow($H, true, true);
        };
        $newPage(true);
        foreach ($R as $r) {
            if ($y - $rowHeight($r, false) < $m + 14) {
                $newPage(false);
            }
            $drawRow($r, false, false);
        }
        if (!$R) {
            $ops .= sprintf("0.40 0.44 0.52 rg BT /F1 9 Tf %.2F %.2F Td (Nothing to show.) Tj ET\n", $m, $y - 14);
        }
        $pages[] = $ops;
        // Write the file
        $n = count($pages);
        $objs = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        for ($i = 0; $i < $n; $i++) {
            $kids[] = (5 + 2 * $i) . ' 0 R';
        }
        $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $n . ' >>';
        $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        foreach ($pages as $i => $ops) {
            $foot = sprintf("0.40 0.44 0.52 rg BT /F1 7.5 Tf %.2F %.2F Td (%s) Tj ET\n", $m, $m - 14, self::esc(self::win($title . ' · ' . school_name())));
            $pg = 'Page ' . ($i + 1) . ' of ' . $n;
            $foot .= sprintf("BT /F1 7.5 Tf %.2F %.2F Td (%s) Tj ET\n", $pw - $m - self::width($pg, 7.5, false), $m - 14, $pg);
            $content = $ops . $foot;
            if (function_exists('gzcompress')) {
                $z = gzcompress($content, 6);
                $stream = '<< /Length ' . strlen($z) . ' /Filter /FlateDecode >>' . "\nstream\n" . $z . "\nendstream";
            } else {
                $stream = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
            }
            $objs[5 + 2 * $i] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>', $pw, $ph, 6 + 2 * $i);
            $objs[6 + 2 * $i] = $stream;
        }
        $infoId = count($objs) + 1;
        $objs[$infoId] = '<< /Title <FEFF' . strtoupper(bin2hex((string) mb_convert_encoding($title, 'UTF-16BE', 'UTF-8'))) . '> /Producer (NISDAS School Management System) /CreationDate (D:' . date('YmdHis') . ') >>';
        ksort($objs);
        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objs as $id => $body) {
            $offsets[$id] = strlen($out);
            $out .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($out);
        $out .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $o) {
            $out .= sprintf("%010d 00000 n \n", $o);
        }
        $out .= "trailer\n<< /Size " . (count($objs) + 1) . ' /Root 1 0 R /Info ' . $infoId . " 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
        return $out;
    }
}
