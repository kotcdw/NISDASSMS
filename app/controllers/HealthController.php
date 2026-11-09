<?php
declare(strict_types=1);

/**
 * Pupil health: health profile (NHIS, blood group, conditions, emergency contact), sick bay visits
 * with an optional SMS to the parent, and printable class health registers / NHIS lists.
 * Class teachers see the pupils of their own class only.
 */
class HealthController extends Controller
{
    public const OUTCOMES = [
        'back_to_class' => ['Returned to class', 'good'], 'rested' => ['Rested at the sick bay', 'info'],
        'sent_home' => ['Sent home', 'warn'], 'referred' => ['Referred to clinic / hospital', 'bad'],
    ];
    public const COMPLAINTS = ['Headache', 'Fever / high temperature', 'Stomach ache', 'Vomiting / diarrhoea', 'Cough / cold / catarrh', 'Malaria symptoms', 'Injury / fall / cut',
        'Nose bleed', 'Toothache', 'Eye problem', 'Ear ache', 'Rash / skin problem', 'Dizziness / fainting', 'Asthma / breathing difficulty', 'Sickle cell crisis', 'Menstrual pain'];
    public const ACTIONS = ['Rested at the sick bay', 'Cleaned and dressed the wound', 'Gave water / ORS', 'Sponged to bring the temperature down', 'Cold compress applied',
        'Medicine given with the parent\'s written consent', 'Observed and checked again', 'Parent called', 'Taken to the clinic'];
    public const PERIODS = ['today' => 'Today', 'week' => 'This week', '14' => 'Last 14 days', '30' => 'Last 30 days', 'term' => 'This term', 'custom' => 'Choose dates…'];
    private const SMS_OUTCOME = [
        'back_to_class' => 'They have returned to class.', 'rested' => 'They rested and are being watched.',
        'sent_home' => 'Please come for them; they need to go home.', 'referred' => 'They have been referred to a clinic.',
    ];

    public function index(): void
    {
        [$scope, $sp] = $this->scope('p');
        $period = isset(self::PERIODS[$_GET['period'] ?? '']) ? $_GET['period'] : (isset($_GET['from']) ? 'custom' : 'term'); // the page has no filters: show the whole term
        $term = current_term();
        [$from, $to] = match ($period) {
            'today' => [date('Y-m-d'), date('Y-m-d')],
            'week' => [date('Y-m-d', strtotime('monday this week')), date('Y-m-d')],
            '30' => [date('Y-m-d', strtotime('-29 days')), date('Y-m-d')],
            'term' => [$term['start_date'] ?? date('Y-m-d', strtotime('-90 days')), min(date('Y-m-d'), $term['end_date'] ?? date('Y-m-d'))],
            'custom' => [$this->date($_GET['from'] ?? '', date('Y-m-d', strtotime('-13 days'))), $this->date($_GET['to'] ?? '', date('Y-m-d'))],
            default => [date('Y-m-d', strtotime('-13 days')), date('Y-m-d')],
        };
        $yid = current_year()['id'] ?? 0;
        $visits = db()->all(
            "SELECT v.*, p.first_name, p.last_name, p.admission_no, c.name AS class_name, u.name AS recorded_name
             FROM health_visits v JOIN pupils p ON p.id = v.pupil_id
             LEFT JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ? LEFT JOIN classes c ON c.id = e.class_id
             LEFT JOIN users u ON u.id = v.recorded_by
             WHERE DATE(v.seen_at) BETWEEN ? AND ? AND $scope ORDER BY v.seen_at DESC LIMIT 300",
            array_merge([$yid, $from, $to], $sp)
        );
        $nhis = db()->one(
            "SELECT SUM(COALESCE(h.nhis_no, '') = '') AS missing, SUM(h.nhis_expiry < CURDATE()) AS expired
             FROM pupils p LEFT JOIN pupil_health h ON h.pupil_id = p.id
             LEFT JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ?
             WHERE p.status = 'active' AND p.deleted_at IS NULL AND $scope",
            array_merge([$yid], $sp)
        );
        $pupils = can('health.manage') ? db()->all(
            "SELECT p.id, p.first_name, p.last_name, p.admission_no, c.id AS class_id, c.name AS class_name
             FROM pupils p JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ? AND e.status = 'active' JOIN classes c ON c.id = e.class_id JOIN levels l ON l.id = c.level_id
             WHERE p.status = 'active' AND p.deleted_at IS NULL AND $scope ORDER BY l.sort_order, c.name, p.last_name, p.first_name",
            array_merge([$yid], $sp)
        ) : [];
        $today = (int) db()->value("SELECT COUNT(*) FROM health_visits v JOIN pupils p ON p.id = v.pupil_id WHERE DATE(v.seen_at) = CURDATE() AND $scope", $sp);
        $this->view('health/index', ['title' => 'Health & sick bay', 'visits' => $visits, 'from' => $from, 'to' => $to, 'period' => $period, 'nhis' => $nhis,
            'pupils' => $pupils, 'classes' => $this->classes(), 'today' => $today]);
    }

    public function storeVisit(): void
    {
        $adm = strtolower(trim((string) ($_POST['admission_no'] ?? '')));
        $pid = (int) ($_POST['pupil_id'] ?? 0);
        $p = $pid
            ? db()->one("SELECT id, first_name, last_name FROM pupils WHERE id = ? AND status = 'active' AND deleted_at IS NULL", [$pid])
            : db()->one("SELECT id, first_name, last_name FROM pupils WHERE LOWER(admission_no) = ? AND status = 'active' AND deleted_at IS NULL", [$adm]);
        // complaint and action: a choice from the list, plus optional details
        $pick = trim((string) ($_POST['complaint_pick'] ?? ''));
        $complaint = trim((string) ($_POST['complaint'] ?? ''));
        if ($pick !== '' && $pick !== 'other') {
            $complaint = in_array($pick, self::COMPLAINTS, true) ? $pick . ($complaint !== '' ? ' — ' . $complaint : '') : $complaint;
        }
        $acts = array_values(array_intersect(self::ACTIONS, (array) ($_POST['actions'] ?? [])));
        $actNote = trim((string) ($_POST['action_taken'] ?? ''));
        $_POST['action_taken'] = implode('; ', array_filter(array_merge($acts, [$actNote])));
        $temp = trim((string) ($_POST['temperature'] ?? ''));
        $outcome = isset(self::OUTCOMES[$_POST['outcome'] ?? '']) ? $_POST['outcome'] : 'back_to_class';
        if ($p) { // class teachers record visits for their own class only
            [$scope, $sp] = $this->scope('p');
            if (!db()->value("SELECT 1 FROM pupils p WHERE p.id = ? AND $scope", array_merge([(int) $p['id']], $sp))) {
                $p = null;
            }
        }
        if (!$p || $complaint === '' || ($temp !== '' && (!is_numeric($temp) || (float) $temp < 30 || (float) $temp > 45))) {
            flash('error', !$p ? ($pid || $adm === '' ? 'Choose the student.' : 'No active student has admission number "' . $adm . '".') : 'Choose or describe the complaint; temperature must be in °C (e.g. 37.8).');
            with_old($_POST);
            redirect('health', ['new' => 1]);
        }
        $seen = date('Y-m-d H:i:s');
        $id = db()->insert('health_visits', [
            'pupil_id' => $p['id'], 'seen_at' => $seen, 'complaint' => mb_substr($complaint, 0, 255),
            'temperature' => $temp === '' ? null : round((float) $temp, 1), 'action_taken' => mb_substr(trim((string) ($_POST['action_taken'] ?? '')), 0, 255) ?: null,
            'outcome' => $outcome, 'recorded_by' => Auth::id(),
        ]);
        $note = '';
        if (!empty($_POST['notify']) || in_array($outcome, ['sent_home', 'referred'], true)) {
            $g = Fees::guardian((int) $p['id']);
            $body = $g ? Notifier::render('sick_bay', [
                'guardian' => $g['name'], 'pupil' => $p['first_name'], 'time' => date('g:ia'),
                'complaint' => mb_strimwidth($complaint, 0, 70, '…'), 'outcome' => self::SMS_OUTCOME[$outcome],
            ]) : null;
            if ($body && Notifier::sms($g['phone'], $body, ['name' => $g['name'], 'event' => 'sick_bay', 'entity' => 'health_visits', 'entity_id' => $id, 'urgent' => true])) {
                db()->update('health_visits', ['guardian_notified' => 1], 'id = ?', [$id]);
                Notifier::process(5);
                $note = ' Parent informed by SMS.';
            } else {
                $note = ' No SMS sent (no valid parent number or the SMS template is switched off) — please call the parent.';
            }
        }
        audit('sick_bay_visit', 'pupils', (int) $p['id'], ['visit' => $id, 'outcome' => $outcome]);
        flash('success', 'Visit recorded for ' . $p['first_name'] . ' ' . $p['last_name'] . '.' . $note);
        redirect('health');
    }

    public function pupil(int $id): void
    {
        $this->needSensitive();
        $p = $this->pupilRow($id);
        $h = db()->one('SELECT h.*, u.name AS updated_name FROM pupil_health h LEFT JOIN users u ON u.id = h.updated_by WHERE h.pupil_id = ?', [$id]);
        $visits = db()->all('SELECT v.*, u.name AS recorded_name FROM health_visits v LEFT JOIN users u ON u.id = v.recorded_by WHERE v.pupil_id = ? ORDER BY v.seen_at DESC LIMIT 100', [$id]);
        $guardian = Fees::guardian($id);
        $this->view('health/pupil', ['title' => 'Health — ' . $p['first_name'] . ' ' . $p['last_name'], 'p' => $p, 'h' => $h, 'visits' => $visits, 'guardian' => $guardian]);
    }

    public function savePupil(int $id): void
    {
        $this->needSensitive();
        $this->pupilRow($id);
        $in = $this->only(['blood_group', 'genotype', 'nhis_no', 'nhis_expiry', 'conditions', 'medication', 'clinic', 'emergency_name', 'emergency_phone', 'allergies', 'medical_notes']);
        $errors = Validator::check($in, ['nhis_expiry' => 'date', 'emergency_phone' => 'phone', 'conditions' => 'max:255', 'medication' => 'max:255', 'clinic' => 'max:120', 'emergency_name' => 'max:120', 'allergies' => 'max:255', 'nhis_no' => 'max:30']);
        if ($in['blood_group'] !== null && !in_array($in['blood_group'], ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'], true)) $errors['blood_group'] = 'Choose a blood group.';
        if ($in['genotype'] !== null && !in_array($in['genotype'], ['AA', 'AS', 'SS', 'AC', 'SC', 'CC'], true)) $errors['genotype'] = 'Choose a genotype.';
        if ($errors) {
            with_old($_POST, $errors);
            flash('error', 'Please correct the highlighted fields.');
            redirect('health/pupils/' . $id);
        }
        $imm = $_POST['immunisation_ok'] ?? '';
        $health = [
            'blood_group' => $in['blood_group'], 'genotype' => $in['genotype'], 'nhis_no' => $in['nhis_no'], 'nhis_expiry' => $in['nhis_expiry'],
            'conditions' => $in['conditions'], 'medication' => $in['medication'], 'clinic' => $in['clinic'],
            'emergency_name' => $in['emergency_name'], 'emergency_phone' => $in['emergency_phone'] ? Admission::normalisePhone($in['emergency_phone']) : null,
            'immunisation_ok' => $imm === '1' ? 1 : ($imm === '0' ? 0 : null), 'updated_by' => Auth::id(),
        ];
        db()->transaction(function (Database $d) use ($id, $health, $in) {
            if ($d->value('SELECT pupil_id FROM pupil_health WHERE pupil_id = ?', [$id])) {
                $d->update('pupil_health', $health, 'pupil_id = ?', [$id]);
            } else {
                $d->insert('pupil_health', ['pupil_id' => $id] + $health);
            }
            $d->update('pupils', ['allergies' => $in['allergies'], 'medical_notes' => $in['medical_notes'] ? mb_substr($in['medical_notes'], 0, 2000) : null], 'id = ?', [$id]);
        });
        audit('health_profile_updated', 'pupils', $id);
        flash('success', 'Health record saved.');
        redirect('health/pupils/' . $id);
    }

    /** Printable class lists: health register (allergies/conditions) or NHIS status. */
    /** The health profile (conditions, medication, NHIS, emergency contact) needs "sensitive details" as well as health rights. */
    private function needSensitive(): void
    {
        if (!can('pupils.sensitive')) {
            $this->forbidden();
        }
    }

    public function report(): void
    {
        $this->needSensitive();
        $kind = ($_GET['kind'] ?? 'register') === 'nhis' ? 'nhis' : 'register';
        $classId = (int) ($_GET['class'] ?? 0);
        [$scope, $sp] = $this->scope('p');
        $yid = current_year()['id'] ?? 0;
        $classes = $this->classes();
        $sql = "SELECT p.id, p.admission_no, p.first_name, p.last_name, p.allergies, p.medical_notes, c.name AS class_name, h.*
                FROM pupils p JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ? JOIN classes c ON c.id = e.class_id
                LEFT JOIN pupil_health h ON h.pupil_id = p.id
                WHERE p.status = 'active' AND p.deleted_at IS NULL AND $scope";
        $params = array_merge([$yid], $sp);
        if ($classId) {
            $sql .= ' AND c.id = ?';
            $params[] = $classId;
        }
        if ($kind === 'register' && empty($_GET['all'])) {
            $sql .= " AND (COALESCE(p.allergies, '') <> '' OR COALESCE(h.conditions, '') <> '' OR COALESCE(h.medication, '') <> '' OR h.genotype IN ('SS','SC'))";
        }
        if ($kind === 'nhis' && empty($_GET['all'])) {
            $sql .= " AND (h.pupil_id IS NULL OR COALESCE(h.nhis_no, '') = '' OR h.nhis_expiry IS NULL OR h.nhis_expiry < (CURDATE() + INTERVAL 30 DAY))";
        }
        $rows = db()->all($sql . ' ORDER BY c.name, p.last_name, p.first_name', $params);
        if (isset($_GET['print'])) {
            $this->view('health/report_print', ['title' => $kind === 'nhis' ? 'NHIS status' : 'Class health register', 'rows' => $rows, 'kind' => $kind], 'layouts/print');
            return;
        }
        $this->view('health/report', ['title' => $kind === 'nhis' ? 'NHIS status' : 'Class health register', 'rows' => $rows, 'kind' => $kind, 'classes' => $classes, 'classId' => $classId]);
    }

    // ------------------------------------------------------------------ helpers

    /** Roles limited to "own classes" see health records only for the class(es) they are class teacher of; others see all. */
    private function scope(string $alias): array
    {
        if (Auth::classScope() === null) {
            return ['1 = 1', []];
        }
        $yid = current_year()['id'] ?? 0;
        return ["$alias.id IN (SELECT e2.pupil_id FROM enrolments e2 JOIN classes c2 ON c2.id = e2.class_id WHERE e2.academic_year_id = ? AND c2.class_teacher_id = ?)", [$yid, Auth::id()]];
    }

    private function classes(): array
    {
        return Auth::classScope() !== null
            ? db()->all('SELECT id, name FROM classes WHERE class_teacher_id = ? AND is_active = 1 ORDER BY name', [Auth::id()])
            : db()->all('SELECT c.id, c.name FROM classes c JOIN levels l ON l.id = c.level_id WHERE c.is_active = 1 ORDER BY l.sort_order, c.name');
    }

    private function pupilRow(int $id): array
    {
        [$scope, $sp] = $this->scope('p');
        $yid = current_year()['id'] ?? 0;
        $p = db()->one(
            "SELECT p.*, c.name AS class_name FROM pupils p LEFT JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ? LEFT JOIN classes c ON c.id = e.class_id
             WHERE p.id = ? AND p.deleted_at IS NULL AND $scope",
            array_merge([$yid, $id], $sp)
        );
        return $p ?? $this->notFound();
    }

    private function date(string $v, string $default): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) ? $v : $default;
    }
}
