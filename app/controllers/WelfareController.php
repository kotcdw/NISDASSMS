<?php
declare(strict_types=1);

/**
 * Pupil welfare: behaviour log (merits and concerns, positive discipline), welfare cases (counselling,
 * safeguarding, accidents/incidents, persistent absence) with a notes timeline and follow-up dates.
 */
class WelfareController extends Controller
{
    public function __construct()
    {
        $this->needsTable('welfare_cases');
    }

    public function index(): void
    {
        [$w, $p] = Welfare::visible();
        $cases = db()->all(
            "SELECT w.*, pu.first_name, pu.last_name, u.name AS assigned_name FROM welfare_cases w JOIN pupils pu ON pu.id = w.pupil_id LEFT JOIN users u ON u.id = w.assigned_to
             WHERE w.status <> 'closed' AND $w ORDER BY w.follow_up_on IS NULL, w.follow_up_on, FIELD(w.risk, 'high','medium','low') LIMIT 30",
            $p
        );
        [$bw, $bp] = $this->behaviourScope();
        $recent = db()->all(
            "SELECT b.*, pu.first_name, pu.last_name, u.name AS by_name FROM behaviour_records b JOIN pupils pu ON pu.id = b.pupil_id LEFT JOIN users u ON u.id = b.recorded_by
             WHERE $bw ORDER BY b.occurred_on DESC, b.id DESC LIMIT 12",
            $bp
        );
        $this->view('welfare/index', ['title' => 'Student welfare', 's' => Welfare::summary(current_term()), 'cases' => $cases, 'recent' => $recent]);
    }

    // ---------------------------------------------------------------- behaviour log

    public function behaviour(): void
    {
        [$bw, $bp] = $this->behaviourScope();
        $kind = (string) ($_GET['kind'] ?? '');
        if (in_array($kind, ['merit', 'concern'], true)) {
            $bw .= ' AND b.kind = ?';
            $bp[] = $kind;
        }
        $period = (string) ($_GET['period'] ?? 'term');
        $term = current_term();
        $from = match ($period) {
            'week' => date('Y-m-d', strtotime('monday this week')),
            '30' => date('Y-m-d', strtotime('-29 days')),
            'all' => '1970-01-01',
            default => $term['start_date'] ?? date('Y-m-d', strtotime('-90 days')),
        };
        $bw .= ' AND b.occurred_on >= ?';
        $bp[] = $from;
        $rows = db()->all(
            "SELECT b.*, pu.first_name, pu.last_name, pu.admission_no, u.name AS by_name, c.name AS class_name FROM behaviour_records b JOIN pupils pu ON pu.id = b.pupil_id
             LEFT JOIN enrolments e ON e.pupil_id = pu.id AND e.academic_year_id = ? AND e.status = 'active' LEFT JOIN classes c ON c.id = e.class_id
             LEFT JOIN users u ON u.id = b.recorded_by
             WHERE $bw ORDER BY b.occurred_on DESC, b.id DESC LIMIT 1000",
            array_merge([current_year()['id'] ?? 0], $bp)
        );
        $this->view('welfare/behaviour', ['title' => 'Behaviour records', 'rows' => $rows, 'kind' => $kind, 'period' => $period,
            'classes' => array_values(array_unique(array_filter(array_column($this->pupilChoices(), 'class_name'))))]);
    }

    public function newBehaviour(): void
    {
        $this->view('welfare/behaviour_new', ['title' => 'Record behaviour', 'pupils' => $this->pupilChoices(), 'pick' => (int) ($_GET['pupil'] ?? 0)]);
    }

    public function storeBehaviour(): void
    {
        $pupil = $this->pupil((int) ($_POST['pupil_id'] ?? 0));
        $kind = ($_POST['kind'] ?? '') === 'merit' ? 'merit' : 'concern';
        $cat = (string) ($_POST['category'] ?? '');
        $action = $kind === 'concern' ? (string) ($_POST['action_taken'] ?? '') : null;
        $date = (string) ($_POST['occurred_on'] ?? date('Y-m-d'));
        if (!in_array($cat, $kind === 'merit' ? Welfare::MERITS : Welfare::CONCERNS, true) || ($kind === 'concern' && !isset(Welfare::ACTIONS[$action]))
            || !strtotime($date) || $date > date('Y-m-d')) {
            with_old($_POST);
            flash('error', $kind === 'concern' ? 'Choose the concern and the positive-discipline action taken (date not in the future).' : 'Choose what the merit is for (date not in the future).');
            redirect('welfare/behaviour/new', ['pupil' => $pupil['id']]);
        }
        $id = Welfare::record($pupil, ['kind' => $kind, 'category' => $cat, 'description' => mb_substr(trim((string) ($_POST['description'] ?? '')), 0, 500) ?: null,
            'action_taken' => $action, 'points' => 1, 'occurred_on' => $date], !empty($_POST['tell_parent']));
        audit('behaviour_recorded', 'behaviour_records', $id, ['kind' => $kind]);
        flash('success', ($kind === 'merit' ? 'Merit' : 'Concern') . ' recorded for ' . full_name($pupil) . (!empty($_POST['tell_parent']) ? '. The parent will get an SMS.' : '.'));
        redirect((string) ($_POST['back'] ?? '') === 'pupil' ? 'pupils/' . $pupil['id'] : 'welfare/behaviour/new');
    }

    // ---------------------------------------------------------------- cases

    public function cases(): void
    {
        [$w, $p] = Welfare::visible();
        $kind = (string) ($_GET['kind'] ?? '');
        $status = (string) ($_GET['status'] ?? 'all'); // no filter on the page: open cases first, closed ones below
        if (isset(Welfare::KINDS[$kind])) {
            $w .= ' AND w.kind = ?';
            $p[] = $kind;
        }
        $w .= $status === 'closed' ? " AND w.status = 'closed'" : ($status === 'all' ? '' : " AND w.status <> 'closed'");
        $rows = db()->all(
            "SELECT w.*, pu.first_name, pu.last_name, u.name AS assigned_name FROM welfare_cases w JOIN pupils pu ON pu.id = w.pupil_id LEFT JOIN users u ON u.id = w.assigned_to
             WHERE $w ORDER BY w.status = 'closed', w.created_at DESC LIMIT 200",
            $p
        );
        $this->view('welfare/cases', ['title' => 'Welfare cases', 'rows' => $rows, 'kind' => $kind, 'status' => $status]);
    }

    public function newCase(): void
    {
        $kind = (string) ($_GET['kind'] ?? 'counselling');
        if (!isset(Welfare::KINDS[$kind]) || $kind === 'absence' || $kind === 'behaviour') {
            $kind = 'counselling';
        }
        $this->view('welfare/case_new', ['title' => 'Open a welfare case', 'kind' => $kind, 'pupils' => $this->pupilChoices(), 'pick' => (int) ($_GET['pupil'] ?? 0),
            'staff' => db()->all("SELECT id, name FROM users WHERE is_active = 1 AND role NOT IN ('parent','pupil') ORDER BY name")]);
    }

    public function storeCase(): void
    {
        $pupil = $this->pupil((int) ($_POST['pupil_id'] ?? 0));
        $d = $this->only(['kind', 'title', 'details', 'risk', 'referred_to', 'first_aid', 'follow_up_on']);
        if (!in_array($d['kind'], ['counselling', 'incident', 'safeguarding'], true) || !$d['title'] || !isset(Welfare::RISK[$d['risk'] ?? ''])
            || ($d['follow_up_on'] && !strtotime((string) $d['follow_up_on'])) || ($d['referred_to'] && !in_array($d['referred_to'], Welfare::REFERRALS, true))) {
            with_old($_POST);
            flash('error', 'Choose the student, the type of case, a short title and the risk level.');
            redirect('welfare/cases/new', ['kind' => (string) $d['kind'], 'pupil' => $pupil['id']]);
        }
        $assign = (int) ($_POST['assigned_to'] ?? 0);
        if ($d['kind'] === 'safeguarding') {
            $assign = 0; // the Head / designated safeguarding lead takes it
        } elseif (!$assign || !can('welfare.manage')) {
            $assign = $d['kind'] === 'counselling' && can('welfare.manage') ? Auth::id() : (Welfare::classTeacher((int) $pupil['id']) ?? Auth::id());
        }
        $tell = $d['kind'] === 'incident' && !empty($_POST['tell_parent']);
        $id = Welfare::openCase($d + ['pupil_id' => $pupil['id'], 'assigned_to' => $assign ?: null, 'parent_informed' => $tell ? 1 : 0]);
        if (!$id) {
            flash('error', 'The case could not be saved. Please try again.');
            redirect('welfare/cases/new');
        }
        if ($tell) {
            foreach (Welfare::guardians((int) $pupil['id']) as $g) {
                if ($body = Notifier::render('incident_parent', ['guardian' => $g['name'], 'pupil' => $pupil['first_name'], 'title' => $d['title'], 'first_aid' => $d['first_aid'] ?: 'none needed', 'school' => school_name(), 'school_phone' => (string) setting('school_phone')])) {
                    Notifier::sms($g['phone'], $body, ['name' => $g['name'], 'entity' => 'welfare_case', 'entity_id' => $id, 'urgent' => true]);
                }
            }
        }
        if ($d['kind'] === 'safeguarding') {
            Notifier::inappPermission('welfare.safeguarding', 'Safeguarding concern raised', 'A new safeguarding concern needs your attention today.', url('welfare/cases/' . $id));
        } elseif ($assign && $assign !== Auth::id()) {
            Notifier::inapp([$assign], 'Welfare case for you: ' . full_name($pupil), (string) $d['title'], url('welfare/cases/' . $id));
        }
        audit('welfare_case_opened', 'welfare_cases', $id, ['kind' => $d['kind']]);
        flash('success', $d['kind'] === 'safeguarding' ? 'Thank you. The concern has been passed to the Head in confidence.' : 'Case opened.');
        redirect('welfare/cases/' . $id);
    }

    public function show(int $id): void
    {
        $c = $this->case($id);
        $details = Welfare::canSeeDetails($c);
        $this->view('welfare/case', [
            'title' => $c['ref_no'], 'c' => $c, 'details' => $details, 'work' => Welfare::canWork($c),
            'pupil' => db()->one('SELECT p.*, c.name AS class_name FROM pupils p LEFT JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ? LEFT JOIN classes c ON c.id = e.class_id WHERE p.id = ?', [current_year()['id'] ?? 0, $c['pupil_id']]),
            'notes' => $details ? db()->all('SELECT n.*, u.name AS by_name FROM welfare_notes n LEFT JOIN users u ON u.id = n.created_by WHERE n.case_id = ? ORDER BY n.created_at', [$id]) : [],
            'staff' => db()->all("SELECT id, name FROM users WHERE is_active = 1 AND role NOT IN ('parent','pupil') ORDER BY name"),
            'assigned' => $c['assigned_to'] ? db()->value('SELECT name FROM users WHERE id = ?', [$c['assigned_to']]) : null,
            'opener' => $c['opened_by'] ? db()->value('SELECT name FROM users WHERE id = ?', [$c['opened_by']]) : 'The system (automatic)',
        ]);
    }

    public function update(int $id): void
    {
        $c = $this->case($id);
        if (!Welfare::canWork($c)) {
            $this->forbidden();
            return;
        }
        $d = $this->only(['status', 'risk', 'referred_to', 'follow_up_on', 'outcome']);
        if (!isset(Welfare::STATUS[$d['status'] ?? '']) || !isset(Welfare::RISK[$d['risk'] ?? '']) || ($d['referred_to'] && !in_array($d['referred_to'], Welfare::REFERRALS, true))
            || ($d['follow_up_on'] && !strtotime((string) $d['follow_up_on'])) || ($d['status'] === 'closed' && !$d['outcome'])) {
            flash('error', $d['status'] === 'closed' && !$d['outcome'] ? 'Write the outcome before closing the case.' : 'Check the status, risk and dates.');
            redirect('welfare/cases/' . $id);
        }
        if (can('welfare.manage') && isset($_POST['assigned_to'])) {
            $d['assigned_to'] = (int) $_POST['assigned_to'] ?: null;
        }
        $d['closed_at'] = $d['status'] === 'closed' ? ($c['closed_at'] ?: date('Y-m-d H:i:s')) : null;
        db()->update('welfare_cases', $d, 'id = ?', [$id]);
        if (!empty($d['assigned_to']) && (int) $d['assigned_to'] !== (int) $c['assigned_to'] && (int) $d['assigned_to'] !== Auth::id()) {
            Notifier::inapp([(int) $d['assigned_to']], 'Welfare case for you: ' . $c['ref_no'], $c['title'], url('welfare/cases/' . $id));
        }
        audit('welfare_case_updated', 'welfare_cases', $id, ['status' => $d['status']]);
        flash('success', 'Case updated.');
        redirect('welfare/cases/' . $id);
    }

    public function note(int $id): void
    {
        $c = $this->case($id);
        $note = trim((string) ($_POST['note'] ?? ''));
        if (!Welfare::canWork($c) && !(Welfare::canSeeDetails($c) && (int) $c['opened_by'] === Auth::id())) {
            $this->forbidden();
            return;
        }
        if ($note === '') {
            flash('error', 'Write the note first.');
            redirect('welfare/cases/' . $id);
        }
        db()->insert('welfare_notes', ['case_id' => $id, 'note' => mb_substr($note, 0, 4000), 'created_by' => Auth::id()]);
        audit('welfare_note_added', 'welfare_cases', $id);
        flash('success', 'Note added.');
        redirect('welfare/cases/' . $id);
    }

    // ---------------------------------------------------------------- helpers

    private function case(int $id): array
    {
        $c = db()->one('SELECT * FROM welfare_cases WHERE id = ?', [$id]);
        if (!$c || !Welfare::canSee($c)) {
            if ($c) {
                audit('access_denied', 'welfare_cases', $id);
            }
            $this->notFound();
            exit;
        }
        return $c;
    }

    private function pupil(int $id): array
    {
        $p = db()->one("SELECT * FROM pupils WHERE id = ? AND status = 'active' AND deleted_at IS NULL", [$id]);
        if (!$p || !Auth::pupilInScope($id)) {
            flash('error', 'Choose a student from your classes.');
            redirect('welfare');
        }
        return $p;
    }

    /** Behaviour records this person may see: everything for welfare.manage, own classes for teachers. */
    private function behaviourScope(): array
    {
        if (can('welfare.manage')) {
            return ['1 = 1', []];
        }
        $sc = Auth::classScope();
        if ($sc === null) {
            return ['1 = 1', []];
        }
        $sc = $sc ?: [0];
        return ['(b.recorded_by = ? OR b.pupil_id IN (SELECT e.pupil_id FROM enrolments e WHERE e.academic_year_id = ? AND e.status = \'active\' AND e.class_id IN (' . implode(',', array_map('intval', $sc)) . ')))',
            [Auth::id(), current_year()['id'] ?? 0]];
    }

    private function pupilChoices(): array
    {
        $sc = Auth::classScope();
        $where = $sc === null ? '' : ' AND c.id IN (' . implode(',', array_map('intval', $sc ?: [0])) . ')';
        return db()->all(
            "SELECT p.id, p.first_name, p.last_name, p.admission_no, c.name AS class_name FROM pupils p JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ? AND e.status = 'active'
             JOIN classes c ON c.id = e.class_id JOIN levels l ON l.id = c.level_id WHERE p.status = 'active' AND p.deleted_at IS NULL$where ORDER BY l.sort_order, c.name, p.first_name, p.last_name",
            [current_year()['id'] ?? 0]
        );
    }
}
