<?php
declare(strict_types=1);

class AdmissionController extends Controller
{
    public function index(): void
    {
        $years = db()->all('SELECT id, name FROM academic_years ORDER BY start_date DESC');
        $yid = (int) ($_GET['year'] ?? (current_year()['id'] ?? 0));
        $stage = (string) ($_GET['stage'] ?? '');
        $level = (int) ($_GET['level'] ?? 0);
        $q = trim((string) ($_GET['q'] ?? ''));

        $counts = array_column(db()->all('SELECT stage, COUNT(*) AS n FROM applications WHERE academic_year_id = ? GROUP BY stage', [$yid]), 'n', 'stage');

        $sql = 'SELECT a.*, l.name AS level_name FROM applications a JOIN levels l ON l.id = a.level_id WHERE a.academic_year_id = ?';
        $p = [$yid];
        if ($stage !== '' && isset(Admission::STAGES[$stage])) {
            $sql .= ' AND a.stage = ?';
            $p[] = $stage;
        }
        if ($level) {
            $sql .= ' AND a.level_id = ?';
            $p[] = $level;
        }
        if ($q !== '') {
            $sql .= ' AND (a.ref_no LIKE ? OR a.first_name LIKE ? OR a.last_name LIKE ? OR a.guardian_name LIKE ? OR a.guardian_phone LIKE ?)';
            array_push($p, "%$q%", "%$q%", "%$q%", "%$q%", "%$q%");
        }
        $apps = db()->all($sql . ' ORDER BY a.created_at DESC LIMIT 5000', $p);
        $pager = ['total' => count($apps)];

        $this->view('admission/index', [
            'title' => 'Admission', 'apps' => $apps, 'pager' => $pager, 'counts' => $counts,
            'years' => $years, 'yid' => $yid, 'stage' => $stage, 'level' => $level, 'q' => $q,
            'levels' => db()->all('SELECT id, name FROM levels ORDER BY sort_order'),
        ]);
    }

    public function create(): void
    {
        if (!current_year()) {
            flash('error', 'Set the current academic year in Setup first.');
            redirect('setup/years');
        }
        $this->view('admission/form', ['title' => 'Student Registration', 'app' => null, 'levels' => db()->all('SELECT id, name FROM levels ORDER BY sort_order')]);
    }

    public function store(): void
    {
        $year = current_year();
        if (!$year) {
            redirect('setup/years');
        }
        $in = $this->only(Admission::FIELDS);
        $this->validate($in, Admission::rules(), 'admission/create');
        $in['guardian_phone'] = Admission::normalisePhone($in['guardian_phone']);
        $in['guardian_phone_alt'] = Admission::normalisePhone($in['guardian_phone_alt']);

        $id = db()->transaction(function (Database $d) use ($in, $year) {
            $id = $d->insert('applications', $in + [
                'ref_no' => Admission::nextRef($d), 'academic_year_id' => $year['id'],
                'stage' => 'submitted', 'source' => 'walk_in', 'created_by' => Auth::id(),
            ]);
            $d->insert('application_stage_log', ['application_id' => $id, 'to_stage' => 'submitted', 'note' => 'Recorded at the school (Student Registration)', 'changed_by' => Auth::id()]);
            return $id;
        });
        audit('application_created', 'applications', $id);
        Admission::notifyGuardian('application_received', db()->one('SELECT * FROM applications WHERE id = ?', [$id]));
        Notifier::process(10);
        flash('success', 'Application recorded. Upload documents and move it through the stages below.');
        redirect('admission/' . $id);
    }

    public function show(int $id): void
    {
        $app = $this->find($id);
        $docs = db()->all('SELECT * FROM application_documents WHERE application_id = ? ORDER BY uploaded_at', [$id]);
        $log = db()->all('SELECT g.*, u.name AS user_name FROM application_stage_log g LEFT JOIN users u ON u.id = g.changed_by WHERE application_id = ? ORDER BY g.id DESC', [$id]);
        $classes = db()->all('SELECT c.id, c.name, c.capacity, (SELECT COUNT(*) FROM enrolments e WHERE e.class_id = c.id AND e.academic_year_id = ? AND e.status = \'active\') AS pupils FROM classes c WHERE c.level_id = ? AND c.is_active = 1 ORDER BY c.name', [$app['academic_year_id'], $app['level_id']]);
        $siblings = db()->all(
            "SELECT p.id, p.admission_no, p.first_name, p.last_name FROM guardians g JOIN pupil_guardian pg ON pg.guardian_id = g.id JOIN pupils p ON p.id = pg.pupil_id WHERE g.phone = ? AND p.status = 'active' AND p.id <> ?",
            [$app['guardian_phone'], (int) $app['pupil_id']]
        );
        $this->view('admission/show', [
            'title' => $app['ref_no'], 'app' => $app, 'docs' => $docs, 'log' => $log, 'classes' => $classes, 'siblings' => $siblings,
        ]);
    }

    public function edit(int $id): void
    {
        $app = $this->find($id);
        if ($app['stage'] === 'enrolled') {
            flash('warning', 'This applicant is enrolled — edit the student record instead.');
            redirect('pupils/' . $app['pupil_id']);
        }
        $this->view('admission/form', ['title' => 'Edit application', 'app' => $app, 'levels' => db()->all('SELECT id, name FROM levels ORDER BY sort_order')]);
    }

    public function update(int $id): void
    {
        $app = $this->find($id);
        if ($app['stage'] === 'enrolled') {
            redirect('admission/' . $id);
        }
        $in = $this->only(Admission::FIELDS);
        $this->validate($in, Admission::rules(), "admission/$id/edit");
        $in['guardian_phone'] = Admission::normalisePhone($in['guardian_phone']);
        $in['guardian_phone_alt'] = Admission::normalisePhone($in['guardian_phone_alt']);
        db()->update('applications', $in, 'id = ?', [$id]);
        audit('application_updated', 'applications', $id);
        flash('success', 'Application updated.');
        redirect('admission/' . $id);
    }

    public function changeStage(int $id): void
    {
        $app = $this->find($id);
        $to = (string) ($_POST['stage'] ?? '');
        if (!in_array($to, Admission::TRANSITIONS[$app['stage']] ?? [], true)) {
            flash('error', 'That stage change is not allowed from ' . Admission::label($app['stage']) . '.');
            redirect('admission/' . $id);
        }
        $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 255) ?: null;
        $update = ['stage' => $to];
        $score = trim((string) ($_POST['entrance_score'] ?? ''));
        if ($score !== '') {
            if (!is_numeric($score) || $score < 0 || $score > 100) {
                flash('error', 'Entrance score must be between 0 and 100.');
                redirect('admission/' . $id);
            }
            $update['entrance_score'] = $score;
        }
        $date = trim((string) ($_POST['interview_date'] ?? ''));
        if ($date !== '') {
            if (!Validator::isDate($date)) {
                flash('error', 'Interview date is not valid.');
                redirect('admission/' . $id);
            }
            $update['interview_date'] = $date;
        }
        db()->transaction(function (Database $d) use ($id, $app, $to, $note, $update) {
            $d->update('applications', $update, 'id = ?', [$id]);
            $d->insert('application_stage_log', ['application_id' => $id, 'from_stage' => $app['stage'], 'to_stage' => $to, 'note' => $note, 'changed_by' => Auth::id()]);
        });
        audit('application_stage', 'applications', $id, ['from' => $app['stage'], 'to' => $to]);
        $fresh = $this->find($id);
        $tpl = ['screening' => 'application_interview', 'interview' => 'application_interview', 'offered' => 'application_offered', 'rejected' => 'application_rejected'][$to] ?? null;
        if ($tpl && ($tpl !== 'application_interview' || $fresh['interview_date'])) {
            Admission::notifyGuardian($tpl, $fresh);
            Notifier::process(10);
        }
        flash('success', 'Moved to ' . Admission::label($to) . '.');
        redirect('admission/' . $id);
    }

    public function uploadDocument(int $id): void
    {
        $this->find($id);
        $type = (string) ($_POST['doc_type'] ?? '');
        if (!isset(Admission::DOC_TYPES[$type]) || empty($_FILES['file'])) {
            flash('error', 'Choose a document type and a file.');
            redirect('admission/' . $id);
        }
        [$info, $err] = Upload::store($_FILES['file'], 'applications');
        if ($err || !$info) {
            flash('error', $err ?: 'Choose a file to upload.');
            redirect('admission/' . $id);
        }
        db()->insert('application_documents', $info + ['application_id' => $id, 'doc_type' => $type]);
        audit('document_uploaded', 'applications', $id, ['type' => $type]);
        flash('success', Admission::DOC_TYPES[$type] . ' uploaded.');
        redirect('admission/' . $id);
    }

    public function document(int $id, int $doc): void
    {
        $d = db()->one('SELECT * FROM application_documents WHERE id = ? AND application_id = ?', [$doc, $id]) ?? $this->notFound();
        Upload::send($d['stored_name'], $d['mime'], $d['original_name']);
    }

    /** Turn an accepted application into a pupil with an admission number and class enrolment. */
    public function enrol(int $id): void
    {
        $app = $this->find($id);
        if (!in_array($app['stage'], ['offered', 'accepted'], true)) {
            flash('error', 'Only offered or accepted applicants can be enrolled.');
            redirect('admission/' . $id);
        }
        $classId = (int) ($_POST['class_id'] ?? 0);
        $class = db()->one('SELECT * FROM classes WHERE id = ? AND level_id = ? AND is_active = 1', [$classId, $app['level_id']]);
        $admittedOn = (string) ($_POST['admitted_on'] ?? date('Y-m-d'));
        if (!$class || !Validator::isDate($admittedOn)) {
            flash('error', 'Choose a class for this level and a valid admission date.');
            redirect('admission/' . $id);
        }
        $year = db()->one('SELECT * FROM academic_years WHERE id = ?', [$app['academic_year_id']]);

        try {
        $pupilId = db()->transaction(function (Database $d) use ($app, $class, $admittedOn, $year) {
            // Re-check inside the transaction so a double click cannot enrol twice
            $fresh = $d->one('SELECT stage FROM applications WHERE id = ? FOR UPDATE', [$app['id']]);
            if ($fresh['stage'] === 'enrolled') {
                throw new RuntimeException('already_enrolled');
            }
            $admNo = Admission::nextAdmissionNo($d, substr($year['name'], 0, 4));
            $pupilId = $d->insert('pupils', [
                'admission_no' => $admNo, 'first_name' => $app['first_name'], 'middle_name' => $app['middle_name'],
                'last_name' => $app['last_name'], 'gender' => $app['gender'], 'date_of_birth' => $app['date_of_birth'],
                'nationality' => $app['nationality'], 'hometown' => $app['hometown'], 'denomination' => $app['denomination'],
                'previous_school' => $app['previous_school'], 'medical_notes' => $app['medical_notes'], 'allergies' => $app['allergies'],
                'sen_category' => $app['sen_category'] ?? null, 'sen_support' => $app['sen_support'] ?? null,
                'address' => $app['guardian_address'], 'admitted_on' => $admittedOn, 'application_id' => $app['id'],
            ]);
            // Reuse the guardian record when a sibling is already in school (same phone)
            $guardianId = (int) $d->value('SELECT id FROM guardians WHERE phone = ? ORDER BY id LIMIT 1', [$app['guardian_phone']]);
            if (!$guardianId) {
                $guardianId = $d->insert('guardians', [
                    'name' => $app['guardian_name'], 'phone' => $app['guardian_phone'], 'phone_alt' => $app['guardian_phone_alt'],
                    'email' => $app['guardian_email'], 'occupation' => $app['guardian_occupation'], 'address' => $app['guardian_address'],
                ]);
            }
            $d->insert('pupil_guardian', ['pupil_id' => $pupilId, 'guardian_id' => $guardianId, 'relationship' => $app['guardian_relationship'], 'is_primary' => 1]);
            $d->insert('enrolments', ['pupil_id' => $pupilId, 'class_id' => $class['id'], 'academic_year_id' => $year['id'], 'enrolled_on' => $admittedOn]);
            // Use the passport photo from the application as the pupil photo
            $photo = $d->value("SELECT stored_name FROM application_documents WHERE application_id = ? AND doc_type = 'passport_photo' AND mime IN ('image/jpeg','image/png') ORDER BY id DESC LIMIT 1", [$app['id']]);
            if ($photo) {
                $d->update('pupils', ['photo_path' => $photo], 'id = ?', [$pupilId]);
            }
            $d->update('applications', ['stage' => 'enrolled', 'pupil_id' => $pupilId], 'id = ?', [$app['id']]);
            // Admission fee bill (fee items charged "on admission only")
            if (!empty($_POST['admission_bill'])) {
                Fees::createAdmissionInvoice($d, ['id' => $pupilId, 'level_id' => $class['level_id'], 'class_id' => $class['id']]);
            }
            $d->insert('application_stage_log', ['application_id' => $app['id'], 'from_stage' => $app['stage'], 'to_stage' => 'enrolled', 'note' => "Admission no. $admNo, class {$class['name']}", 'changed_by' => Auth::id()]);
            return $pupilId;
        });
        } catch (RuntimeException $e) {
            if ($e->getMessage() !== 'already_enrolled') {
                throw $e;
            }
            flash('warning', 'This applicant has already been enrolled.');
            redirect('admission/' . $id);
        }
        audit('pupil_enrolled', 'pupils', $pupilId, ['application' => $app['ref_no'], 'class' => $class['name']]);
        $admNo = (string) db()->value('SELECT admission_no FROM pupils WHERE id = ?', [$pupilId]);
        Admission::notifyGuardian('pupil_enrolled', $app, ['class' => $class['name'], 'admission_no' => $admNo]);
        $admBill = (int) db()->value("SELECT id FROM invoices WHERE pupil_id = ? AND kind = 'admission' ORDER BY id DESC LIMIT 1", [$pupilId]);
        if ($admBill) {
            Fees::smsInvoice($admBill);
        }
        Notifier::process(10);
        flash('success', full_name($app) . ' is enrolled in ' . $class['name'] . '.');
        redirect('pupils/' . $pupilId);
    }

    private function find(int $id): array
    {
        return db()->one(
            'SELECT a.*, l.name AS level_name, y.name AS year_name, u.name AS created_by_name
             FROM applications a JOIN levels l ON l.id = a.level_id JOIN academic_years y ON y.id = a.academic_year_id
             LEFT JOIN users u ON u.id = a.created_by WHERE a.id = ?',
            [$id]
        ) ?? $this->notFound();
    }
}
