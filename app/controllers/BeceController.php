<?php
declare(strict_types=1);

/**
 * BECE: check JHS 3 candidates' details before WAEC registration, export the candidate list (CSV)
 * and photos (zip), keep index numbers, and record results with the aggregate (best 6: 4 core + best 2).
 */
class BeceController extends Controller
{

    public function index(): void
    {
        $year = $this->year();
        $rows = $year ? $this->candidates((int) $year['id']) : [];
        $issues = 0;
        foreach ($rows as &$r) {
            $r['problems'] = $this->problems($r, (int) substr((string) $year['end_date'], 0, 4));
            $issues += $r['problems'] ? 1 : 0;
        }
        unset($r);
        $this->view('bece/index', ['title' => 'BECE registration', 'year' => $year, 'years' => $this->years(), 'rows' => $rows, 'issues' => $issues]);
    }

    /** Save index numbers and NIA (Ghana Card) numbers. */
    public function save(): void
    {
        $year = $this->year() ?? $this->notFound();
        $ids = array_column($this->candidates((int) $year['id']), 'id');
        $idx = (array) ($_POST['index_no'] ?? []);
        $nia = (array) ($_POST['nia_no'] ?? []);
        $bad = [];
        $saved = 0;
        foreach ($ids as $pid) {
            $i = strtoupper(preg_replace('/\s+/', '', (string) ($idx[$pid] ?? '')));
            $n = strtoupper(preg_replace('/\s+/', '', (string) ($nia[$pid] ?? '')));
            if ($i !== '' && !preg_match('/^[0-9A-Z\/-]{6,20}$/', $i)) {
                $bad[] = $i;
                continue;
            }
            if ($n !== '' && !preg_match('/^GHA-?(\d{9})-?(\d)$/', $n, $m)) {
                $bad[] = $n;
                continue;
            }
            if ($n !== '') {
                $n = 'GHA-' . $m[1] . '-' . $m[2]; // one format, as WAEC expects
                if (db()->value('SELECT id FROM bece_candidates WHERE nia_no = ? AND NOT (pupil_id = ? AND academic_year_id = ?)', [$n, $pid, $year['id']])) {
                    $bad[] = "$n (already used by another student)";
                    continue;
                }
            }
            if ($i !== '' && db()->value('SELECT id FROM bece_candidates WHERE index_no = ? AND NOT (pupil_id = ? AND academic_year_id = ?)', [$i, $pid, $year['id']])) {
                $bad[] = "$i (already used)";
                continue;
            }
            db()->run(
                'INSERT INTO bece_candidates (pupil_id, academic_year_id, index_no, nia_no) VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE index_no = VALUES(index_no), nia_no = VALUES(nia_no)',
                [$pid, $year['id'], $i ?: null, $n ?: null]
            );
            $saved++;
        }
        audit('bece_numbers_saved', 'academic_years', (int) $year['id'], ['count' => $saved]);
        flash($bad ? 'warning' : 'success', 'Saved.' . ($bad ? ' Not saved (check the format): ' . implode(', ', array_slice($bad, 0, 8)) . '. Ghana Card numbers look like GHA-123456789-0.' : ''));
        redirect('bece', ['year' => $year['id']]);
    }

    public function export(): void
    {
        $year = $this->year() ?? $this->notFound();
        $rows = $this->candidates((int) $year['id']);
        $out = csv_start('bece-candidates-' . $year['name'] . '.csv');
        csv_row($out, ['No.', 'Surname', 'Other names', 'Sex', 'Date of birth (DD/MM/YYYY)', 'Class', 'Admission no.', 'Ghana Card (NIA) no.', 'Index no.', 'Photo file', 'Guardian', 'Guardian phone']);
        foreach ($rows as $i => $r) {
            csv_row($out, [
                $i + 1, strtoupper($r['last_name']), strtoupper(trim($r['first_name'] . ' ' . ($r['middle_name'] ?? ''))), $r['gender'] === 'male' ? 'M' : 'F',
                date('d/m/Y', strtotime($r['date_of_birth'])), $r['class_name'], $r['admission_no'], $r['nia_no'], $r['index_no'],
                $r['photo_path'] ? $this->photoName($r) : '', $r['guardian_name'], preg_replace('/^\+?233(\d{9})$/', '0$1', preg_replace('/\s+/', '', (string) $r['guardian_phone'])),
            ]);
        }
        audit('bece_exported', 'academic_years', (int) $year['id']);
        csv_end($out);
    }

    /** All candidates' passport photos in one zip, named by admission number (or index number once known). */
    public function photos(): void
    {
        $year = $this->year() ?? $this->notFound();
        if (!class_exists('ZipArchive')) {
            flash('error', 'The PHP zip extension is needed for this (turn on extension=zip in php.ini).');
            redirect('bece', ['year' => $year['id']]);
        }
        $tmp = tempnam(sys_get_temp_dir(), 'bece');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $n = 0;
        foreach ($this->candidates((int) $year['id']) as $r) {
            if ($r['photo_path'] && ($p = Upload::path($r['photo_path']))) {
                $zip->addFile($p, $this->photoName($r));
                $n++;
            }
        }
        if (!$n) {
            $zip->addFromString('README.txt', 'No candidate has a photo yet. Add photos on each student record.');
        }
        $zip->close();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="bece-photos-' . $year['name'] . '.zip"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    public function printList(): void
    {
        $year = $this->year() ?? $this->notFound();
        $this->view('bece/print', ['title' => 'BECE candidates ' . $year['name'], 'year' => $year, 'rows' => $this->candidates((int) $year['id'])], 'layouts/print');
    }

    public function results(): void
    {
        $year = $this->year();
        $rows = $year ? $this->candidates((int) $year['id']) : [];
        $this->view('bece/results', ['title' => 'BECE results', 'year' => $year, 'years' => $this->years(), 'rows' => $rows, 'subjects' => $this->subjects(), 'core' => $this->core()]);
    }

    public function saveResults(): void
    {
        $year = $this->year() ?? $this->notFound();
        $subjects = array_column($this->subjects(), 'code');
        $grades = (array) ($_POST['g'] ?? []);
        $saved = 0;
        foreach ($this->candidates((int) $year['id']) as $r) {
            $res = [];
            foreach ($subjects as $code) {
                $g = (string) ($grades[$r['id']][$code] ?? '');
                if (ctype_digit($g) && (int) $g >= 1 && (int) $g <= 9) {
                    $res[$code] = (int) $g;
                }
            }
            $agg = self::aggregate($res, $this->core());
            db()->run(
                'INSERT INTO bece_candidates (pupil_id, academic_year_id, results, aggregate) VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE results = VALUES(results), aggregate = VALUES(aggregate)',
                [$r['id'], $year['id'], $res ? json_encode($res) : null, $agg]
            );
            $saved += $res ? 1 : 0;
        }
        audit('bece_results_saved', 'academic_years', (int) $year['id'], ['candidates' => $saved]);
        flash('success', "Results saved for $saved candidate(s).");
        redirect('bece/results', ['year' => $year['id']]);
    }

    /** Aggregate = the 4 core subjects + the best 2 others. Null until all 4 cores and at least 2 others are graded. */
    public static function aggregate(array $res, array $core): ?int
    {
        return Results::bestSix(array_intersect_key($res, array_flip($core)), array_values(array_diff_key($res, array_flip($core))), $core);
    }

    // ------------------------------------------------------------------ helpers

    private function candidates(int $yearId): array
    {
        return db()->all(
            "SELECT p.id, p.admission_no, p.first_name, p.middle_name, p.last_name, p.gender, p.date_of_birth, p.photo_path, c.name AS class_name,
                    b.index_no, b.nia_no, b.results, b.aggregate,
                    (SELECT g.name FROM pupil_guardian pg JOIN guardians g ON g.id = pg.guardian_id WHERE pg.pupil_id = p.id ORDER BY pg.is_primary DESC LIMIT 1) AS guardian_name,
                    (SELECT g.phone FROM pupil_guardian pg JOIN guardians g ON g.id = pg.guardian_id WHERE pg.pupil_id = p.id ORDER BY pg.is_primary DESC LIMIT 1) AS guardian_phone
             FROM enrolments e JOIN classes c ON c.id = e.class_id JOIN levels l ON l.id = c.level_id JOIN pupils p ON p.id = e.pupil_id
             LEFT JOIN bece_candidates b ON b.pupil_id = p.id AND b.academic_year_id = e.academic_year_id
             WHERE e.academic_year_id = ? AND l.code = 'JHS3' AND p.deleted_at IS NULL AND p.status IN ('active','graduated')
             ORDER BY p.last_name, p.first_name",
            [$yearId]
        );
    }

    private function problems(array $r, int $examYear): array
    {
        $out = [];
        if (!$r['photo_path']) $out[] = 'No photo';
        $age = $examYear - (int) substr((string) $r['date_of_birth'], 0, 4);
        if ($age < 12 || $age > 20) $out[] = "Check date of birth (age $age)";
        if (!$r['nia_no']) $out[] = 'No Ghana Card no.';
        if (preg_match('/[^\p{L}\s\'-]/u', $r['first_name'] . $r['last_name'] . ($r['middle_name'] ?? ''))) $out[] = 'Unusual characters in name';
        return $out;
    }

    private function photoName(array $r): string
    {
        $ext = str_ends_with((string) $r['photo_path'], '.png') ? 'png' : 'jpg';
        $base = $r['index_no'] ?: $r['admission_no'];
        return preg_replace('/[^A-Za-z0-9_-]+/', '_', $base) . '.' . $ext;
    }

    private function year(): ?array
    {
        $id = (int) ($_GET['year'] ?? $_POST['year'] ?? 0);
        return $id ? db()->one('SELECT * FROM academic_years WHERE id = ?', [$id]) : current_year();
    }

    private function years(): array
    {
        return db()->all('SELECT id, name FROM academic_years ORDER BY start_date DESC');
    }

    private function subjects(): array
    {
        $core = $this->core();
        $rows = db()->all("SELECT code, name FROM subjects WHERE is_active = 1 AND FIND_IN_SET('jhs', applies_to) AND code <> 'BIB' ORDER BY name");
        usort($rows, fn($a, $b) => [array_search($a['code'], $core) === false, $a['name']] <=> [array_search($b['code'], $core) === false, $b['name']]);
        return $rows;
    }

    private function core(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', setting('bece_core_subjects', 'ENG,MATH,SCI,SOC')))));
    }
}
