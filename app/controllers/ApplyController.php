<?php
declare(strict_types=1);

/** Public online admission form for parents (no login). */
class ApplyController extends Controller
{
    private const MAX_PER_HOUR_PER_IP = 6;

    public function form(): void
    {
        $year = current_year();
        $open = setting('online_admission_open', '1') === '1' && $year;
        $this->view('apply/form', [
            'title'  => 'Apply for admission',
            'open'   => $open,
            'year'   => $year,
            'levels' => db()->all('SELECT id, name FROM levels ORDER BY sort_order'),
        ], 'layouts/public');
    }

    public function submit(): void
    {
        $year = current_year();
        if (setting('online_admission_open', '1') !== '1' || !$year) {
            flash('error', 'Online admission is closed at the moment. Please contact the school office.');
            redirect('apply');
        }
        // Honeypot: real people never fill this hidden field
        if (!empty($_POST['website'])) {
            redirect('apply/done');
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $recent = (int) db()->value("SELECT COUNT(*) FROM audit_log WHERE action = 'application_online' AND ip = ? AND created_at > (UTC_TIMESTAMP() - INTERVAL 1 HOUR)", [$ip]);
        if ($recent >= self::MAX_PER_HOUR_PER_IP) {
            flash('error', 'Too many applications from this device. Please try again later or visit the school office.');
            redirect('apply');
        }

        $in = $this->only(Admission::FIELDS);
        $errors = Validator::check($in, Admission::rules());
        if (!$errors && !db()->value('SELECT id FROM levels WHERE id = ?', [$in['level_id']])) {
            $errors['level_id'] = 'Choose a class.';
        }
        if (empty($_POST['declaration'])) {
            $errors['declaration'] = 'Please confirm the information is correct.';
        }
        if (!$errors) {
            $dup = db()->one(
                "SELECT ref_no FROM applications WHERE academic_year_id = ? AND first_name = ? AND last_name = ? AND date_of_birth = ? AND stage NOT IN ('rejected','withdrawn')",
                [$year['id'], $in['first_name'], $in['last_name'], $in['date_of_birth']]
            );
            if ($dup) {
                $errors['first_name'] = "An application for this child already exists (reference {$dup['ref_no']}). Use “Check application” to follow it.";
            }
        }
        // Validate files before saving anything
        $files = [];
        foreach (['birth_certificate', 'passport_photo', 'previous_report'] as $type) {
            if (!empty($_FILES[$type]) && ($_FILES[$type]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $files[$type] = $_FILES[$type];
            }
        }
        if ($errors) {
            with_old($in + ['declaration' => $_POST['declaration'] ?? ''], $errors);
            flash('error', 'Please correct the highlighted fields.');
            redirect('apply');
        }

        $in['guardian_phone'] = Admission::normalisePhone($in['guardian_phone']);
        $in['guardian_phone_alt'] = Admission::normalisePhone($in['guardian_phone_alt']);

        $stored = [];
        foreach ($files as $type => $f) {
            [$info, $err] = Upload::store($f, 'applications');
            if ($err) {
                foreach ($stored as $s) {
                    @unlink(uploads_dir() . '/' . $s['stored_name']);
                }
                with_old($in, [$type => $err]);
                flash('error', Admission::DOC_TYPES[$type] . ': ' . $err);
                redirect('apply');
            }
            $stored[$type] = $info;
        }

        $ref = db()->transaction(function (Database $d) use ($in, $year, $stored) {
            $ref = Admission::nextRef($d);
            $id = $d->insert('applications', $in + [
                'ref_no' => $ref, 'academic_year_id' => $year['id'], 'stage' => 'submitted', 'source' => 'online',
            ]);
            $d->insert('application_stage_log', ['application_id' => $id, 'to_stage' => 'submitted', 'note' => 'Submitted online']);
            foreach ($stored as $type => $info) {
                $d->insert('application_documents', $info + ['application_id' => $id, 'doc_type' => $type]);
            }
            audit('application_online', 'applications', $id, ['ref' => $ref]);
            return $ref;
        });

        $app = db()->one('SELECT * FROM applications WHERE ref_no = ?', [$ref]);
        Admission::notifyGuardian('application_received', $app);
        Notifier::inappPermission('admission.manage', 'New online application ' . $ref, full_name($app) . ' applied online.', 'admission/' . $app['id']);
        Notifier::process(10);

        $_SESSION['last_application_ref'] = $ref;
        redirect('apply/done');
    }

    public function done(): void
    {
        $this->view('apply/done', ['title' => 'Application received', 'ref' => $_SESSION['last_application_ref'] ?? null], 'layouts/public');
    }

    public function status(): void
    {
        $this->view('apply/status', ['title' => 'Check application', 'app' => null], 'layouts/public');
    }

    public function checkStatus(): void
    {
        $_SESSION['status_checks'] = array_filter($_SESSION['status_checks'] ?? [], fn($t) => $t > time() - 900);
        if (count($_SESSION['status_checks']) >= 10) {
            flash('error', 'Too many attempts. Please wait 15 minutes.');
            redirect('apply/status');
        }
        $_SESSION['status_checks'][] = time();

        $ref = strtoupper(trim((string) ($_POST['ref_no'] ?? '')));
        $phone = Admission::normalisePhone(trim((string) ($_POST['phone'] ?? '')));
        $app = db()->one(
            'SELECT a.ref_no, a.first_name, a.last_name, a.stage, a.interview_date, a.created_at, l.name AS level_name
             FROM applications a JOIN levels l ON l.id = a.level_id WHERE a.ref_no = ? AND a.guardian_phone = ?',
            [$ref, $phone]
        );
        if (!$app) {
            flash('error', 'No application matches that reference and phone number.');
            redirect('apply/status');
        }
        $this->view('apply/status', ['title' => 'Application status', 'app' => $app], 'layouts/public');
    }
}
