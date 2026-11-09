<?php
declare(strict_types=1);

/** Admission List (merit ranking): applicants per level ranked by entrance assessment (70%) and interview (30%); offer the best. */
class MeritController extends Controller
{
    public const W_ENTRANCE = 0.7;

    public static function merit(?string $entrance, ?string $interview): ?float
    {
        if ($entrance === null && $interview === null) {
            return null;
        }
        if ($entrance === null || $interview === null) {
            return round((float) ($entrance ?? $interview), 1);
        }
        return round((float) $entrance * self::W_ENTRANCE + (float) $interview * (1 - self::W_ENTRANCE), 1);
    }

    public function index(): void
    {
        $year = current_year();
        $levels = db()->all('SELECT l.id, l.name, (SELECT COUNT(*) FROM applications a WHERE a.level_id = l.id AND a.academic_year_id = ? AND a.stage IN ("submitted","screening","interview")) AS n FROM levels l ORDER BY l.sort_order', [$year['id'] ?? 0]);
        $lid = (int) ($_GET['level'] ?? 0) ?: (int) (array_values(array_filter($levels, fn($l) => (int) $l['n'] > 0))[0]['id'] ?? ($levels[0]['id'] ?? 0));
        $rows = db()->all(
            "SELECT a.* FROM applications a WHERE a.level_id = ? AND a.academic_year_id = ? AND a.stage IN ('submitted','screening','interview','offered','accepted') ORDER BY a.id",
            [$lid, $year['id'] ?? 0]
        );
        foreach ($rows as &$r) {
            $r['merit'] = self::merit($r['entrance_score'], $r['interview_score']);
        }
        unset($r);
        usort($rows, fn($a, $b) => [$b['merit'] ?? -1, $a['id']] <=> [$a['merit'] ?? -1, $b['id']]);
        $rank = 0;
        $prev = null;
        foreach ($rows as $i => &$r) {
            if ($r['merit'] !== null && $r['merit'] !== $prev) {
                $rank = $i + 1;
            }
            $r['rank'] = $r['merit'] === null ? null : $rank;
            $prev = $r['merit'];
        }
        unset($r);
        $places = (int) db()->value(
            "SELECT COALESCE(SUM(c.capacity), 0) - (SELECT COUNT(*) FROM enrolments e JOIN classes c2 ON c2.id = e.class_id WHERE c2.level_id = ? AND e.academic_year_id = ? AND e.status = 'active')
             FROM classes c WHERE c.level_id = ? AND c.is_active = 1",
            [$lid, $year['id'] ?? 0, $lid]
        );
        $offered = count(array_filter($rows, fn($r) => in_array($r['stage'], ['offered', 'accepted'], true)));
        if (isset($_GET['csv'])) {
            $out = csv_start('admission-list.csv', ['Rank', 'Ref', 'Applicant', 'Entrance', 'Interview', 'Merit', 'Stage']);
            foreach ($rows as $r) {
                csv_row($out, [$r['rank'], $r['ref_no'], full_name($r), $r['entrance_score'], $r['interview_score'], $r['merit'], Admission::label($r['stage'])]);
            }
            csv_end($out);
        }
        $this->view('admission/merit', ['title' => 'Admission List', 'levels' => $levels, 'lid' => $lid, 'rows' => $rows, 'places' => max(0, $places - $offered), 'offered' => $offered]);
    }

    public function scores(): void
    {
        $lid = (int) ($_POST['level'] ?? 0);
        $n = 0;
        $bad = 0;
        foreach ((array) ($_POST['entrance'] ?? []) as $id => $e) {
            $id = (int) $id;
            $iv = (string) ($_POST['interview'][$id] ?? '');
            $vals = [];
            foreach (['entrance_score' => trim((string) $e), 'interview_score' => trim($iv)] as $col => $v) {
                if ($v === '') {
                    $vals[$col] = null;
                } elseif (is_numeric($v) && (float) $v >= 0 && (float) $v <= 100) {
                    $vals[$col] = round((float) $v, 2);
                } else {
                    $bad++;
                    continue 2;
                }
            }
            $n += db()->update('applications', $vals, "id = ? AND stage IN ('submitted','screening','interview')", [$id]);
        }
        audit('merit_scores_saved', 'applications', null, ['level' => $lid, 'rows' => $n]);
        flash($bad ? 'warning' : 'success', 'Scores saved.' . ($bad ? " $bad row(s) skipped: scores must be between 0 and 100." : ''));
        redirect('admission/merit', ['level' => $lid]);
    }

    /** Offer places to the chosen applicants (in merit order), with the usual SMS. */
    public function offer(): void
    {
        $lid = (int) ($_POST['level'] ?? 0);
        $ids = array_map('intval', (array) ($_POST['ids'] ?? []));
        $made = 0;
        foreach ($ids as $id) {
            $app = db()->one('SELECT a.*, l.name AS level_name FROM applications a JOIN levels l ON l.id = a.level_id WHERE a.id = ? AND a.level_id = ?', [$id, $lid]);
            if (!$app || !in_array('offered', Admission::TRANSITIONS[$app['stage']] ?? [], true)) {
                continue;
            }
            db()->transaction(function (Database $d) use ($app) {
                $d->update('applications', ['stage' => 'offered'], 'id = ?', [$app['id']]);
                $d->insert('application_stage_log', ['application_id' => $app['id'], 'from_stage' => $app['stage'], 'to_stage' => 'offered', 'note' => 'Offered from the Admission List', 'changed_by' => Auth::id()]);
            });
            Admission::notifyGuardian('application_offered', db()->one('SELECT a.*, l.name AS level_name FROM applications a JOIN levels l ON l.id = a.level_id WHERE a.id = ?', [$id]));
            $made++;
        }
        audit('merit_offers', 'applications', null, ['level' => $lid, 'offered' => $made]);
        flash($made ? 'success' : 'warning', $made ? "$made place(s) offered; parents are told by SMS." : 'Tick the applicants to offer places to.');
        redirect('admission/merit', ['level' => $lid]);
    }
}
