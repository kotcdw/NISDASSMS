<?php
declare(strict_types=1);

/**
 * Data Protection Act, 2012 (Act 843): public privacy notice, Data Protection Commission registration,
 * and the record-retention rule — personal details of pupils who left long ago are anonymised
 * (names, admission number, class history, results and fee records are kept for transcripts and accounts).
 */
class PrivacyController extends Controller
{
    /** Public page: what the school keeps, why, who sees it and parents' rights. */
    public function notice(): void
    {
        $this->view('privacy/notice', ['title' => 'Privacy notice', 'text' => self::noticeText()], 'layouts/public');
    }

    public function settings(): void
    {
        $years = self::retentionYears();
        $this->view('privacy/settings', [
            'title' => 'Data protection',
            'years' => $years,
            'due' => self::due($years),
            'done' => (int) db()->value('SELECT COUNT(*) FROM pupils WHERE anonymised_at IS NOT NULL'),
            'custom' => setting('privacy_notice'),
            'default' => self::defaultNotice(),
        ]);
    }

    public function save(): void
    {
        $years = (int) ($_POST['retention_years'] ?? 7);
        $dpc = mb_substr(trim((string) ($_POST['dpc_registration_no'] ?? '')), 0, 60);
        $contact = mb_substr(trim((string) ($_POST['privacy_contact'] ?? '')), 0, 150);
        $text = trim((string) ($_POST['privacy_notice'] ?? ''));
        if ($years < 1 || $years > 50) {
            flash('error', 'Keep records for between 1 and 50 years after a student leaves.');
            redirect('setup/privacy');
        }
        if (mb_strlen($text) > 20000) {
            flash('error', 'The privacy notice is too long (20,000 characters at most).');
            redirect('setup/privacy');
        }
        // Saving the unchanged default keeps it automatic (it follows the school name/phone)
        if ($text === trim(self::defaultNotice())) {
            $text = '';
        }
        setting_set('retention_years', (string) $years);
        setting_set('dpc_registration_no', $dpc);
        setting_set('privacy_contact', $contact);
        setting_set('privacy_notice', $text);
        audit('privacy_settings_saved', 'settings', null, ['retention_years' => $years, 'dpc' => $dpc !== '']);
        flash('success', 'Data protection settings saved.');
        redirect('setup/privacy');
    }

    // ---------------------------------------------------------------- data-request log (Act 843)

    /** Requests from parents, staff or former students to see, correct or delete their records — logged with a reply-by date. */
    public function requests(): void
    {
        if (!Registers::ready()) {
            $this->needsTable('data_requests');
        }
        $rows = db()->all("SELECT r.*, u.name AS handler FROM data_requests r LEFT JOIN users u ON u.id = r.handled_by ORDER BY r.status = 'open' DESC, r.due_on, r.id DESC LIMIT 300");
        $this->view('privacy/requests', ['title' => 'Data requests', 'rows' => $rows, 'days' => max(1, (int) setting('data_request_days', '21'))]);
    }

    public function saveRequest(): void
    {
        if (!Registers::ready()) {
            $this->needsTable('data_requests');
        }
        $id = (int) ($_POST['id'] ?? 0);
        if ($id) { // reply / close
            $r = db()->one('SELECT * FROM data_requests WHERE id = ?', [$id]) ?? $this->notFound();
            $status = (string) ($_POST['status'] ?? 'closed');
            $resp = mb_substr(trim((string) ($_POST['response'] ?? '')), 0, 1000);
            if (!in_array($status, ['open', 'closed', 'refused'], true) || ($status !== 'open' && $resp === '')) {
                flash('error', 'Write what was done or why it was refused.');
                redirect('setup/privacy/requests');
            }
            db()->update('data_requests', ['status' => $status, 'response' => $resp ?: null, 'closed_on' => $status === 'open' ? null : date('Y-m-d'), 'handled_by' => Auth::id()], 'id = ?', [$id]);
            audit('data_request_' . $status, 'data_requests', $id);
            flash('success', $r['ref_no'] . ($status === 'open' ? ' updated.' : ' closed.'));
            redirect('setup/privacy/requests');
        }
        $who = mb_substr(trim((string) ($_POST['requester'] ?? '')), 0, 150);
        $rel = (string) ($_POST['relationship'] ?? 'parent');
        $type = (string) ($_POST['request_type'] ?? 'access');
        $recv = (string) ($_POST['received_on'] ?? date('Y-m-d'));
        if ($who === '' || !isset(Registers::DR_RELATIONS[$rel]) || !isset(Registers::DR_TYPES[$type]) || !Validator::isDate($recv) || $recv > date('Y-m-d')) {
            with_old($_POST);
            flash('error', 'Fill in who asked, what they asked for and the date it was received.');
            redirect('setup/privacy/requests');
        }
        $days = max(1, (int) setting('data_request_days', '21'));
        $ref = Registers::nextRef('DR');
        $id = db()->insert('data_requests', [
            'ref_no' => $ref, 'requester' => $who, 'relationship' => $rel, 'contact' => mb_substr(trim((string) ($_POST['contact'] ?? '')), 0, 150) ?: null,
            'request_type' => $type, 'about' => mb_substr(trim((string) ($_POST['about'] ?? '')), 0, 150) ?: null, 'details' => mb_substr(trim((string) ($_POST['details'] ?? '')), 0, 1000) ?: null,
            'received_on' => $recv, 'due_on' => date('Y-m-d', strtotime($recv . " +$days days")), 'created_by' => Auth::id(),
        ]);
        audit('data_request_logged', 'data_requests', $id, ['type' => $type]);
        flash('success', "$ref logged. Reply by " . fmt_date(date('Y-m-d', strtotime($recv . " +$days days"))) . '.');
        redirect('setup/privacy/requests');
    }

    public function saveRequestDays(): void
    {
        $d = (int) ($_POST['data_request_days'] ?? 21);
        if ($d < 1 || $d > 90) {
            flash('error', 'Choose between 1 and 90 days.');
        } else {
            setting_set('data_request_days', (string) $d);
            flash('success', "New requests must be answered within $d days.");
        }
        redirect('setup/privacy/requests');
    }

    /** Anonymise selected leavers past the retention period (Super Admin only, cannot be undone). */
    public function anonymise(): void
    {
        $due = array_column(self::due(self::retentionYears()), null, 'id');
        $ids = array_values(array_filter(array_map('intval', (array) ($_POST['pupils'] ?? [])), fn($id) => isset($due[$id])));
        if (!$ids) {
            flash('error', 'Tick at least one former student whose retention period has passed.');
            redirect('setup/privacy');
        }
        if (trim((string) ($_POST['confirm'] ?? '')) !== 'ANONYMISE') {
            flash('error', 'Type ANONYMISE in the box to confirm. This cannot be undone.');
            redirect('setup/privacy');
        }
        $n = self::anonymisePupils($ids);
        audit('pupils_anonymised', 'pupils', null, ['count' => $n, 'ids' => $ids]);
        flash('success', "$n former student record(s) anonymised. Names, class history, results and fee records are kept.");
        redirect('setup/privacy');
    }

    // ------------------------------------------------------------------ helpers

    public static function retentionYears(): int
    {
        return max(1, (int) setting('retention_years', '7'));
    }

    /** Former pupils who left more than $years years ago and still hold personal details. */
    public static function due(int $years): array
    {
        return db()->all(
            "SELECT p.id, p.admission_no, p.first_name, p.last_name, p.status, p.left_on
             FROM pupils p
             WHERE p.status IN ('withdrawn','transferred','graduated') AND p.anonymised_at IS NULL AND p.left_on IS NOT NULL
               AND p.left_on < (CURDATE() - INTERVAL ? YEAR)
             ORDER BY p.left_on, p.last_name LIMIT 500",
            [$years]
        );
    }

    /** @param int[] $ids */
    public static function anonymisePupils(array $ids): int
    {
        $photos = [];
        $n = db()->transaction(function (Database $d) use ($ids, &$photos) {
            $n = 0;
            foreach ($ids as $id) {
                $p = $d->one('SELECT * FROM pupils WHERE id = ? AND anonymised_at IS NULL FOR UPDATE', [$id]);
                if (!$p) {
                    continue;
                }
                if ($p['photo_path']) {
                    $photos[] = $p['photo_path'];
                }
                $d->update('pupils', [
                    'address' => null, 'hometown' => null, 'denomination' => null, 'medical_notes' => null, 'allergies' => null,
                    'sen_category' => null, 'sen_support' => null, 'photo_path' => null, 'house' => null,
                    'anonymised_at' => gmdate('Y-m-d H:i:s'),
                ], 'id = ?', [$id]);
                $d->run('DELETE FROM health_visits WHERE pupil_id = ?', [$id]);
                $d->run('DELETE FROM pupil_health WHERE pupil_id = ?', [$id]);
                if ($p['user_id']) {
                    $d->update('users', ['is_active' => 0], 'id = ?', [$p['user_id']]);
                }
                if ($p['application_id']) {
                    $d->update('applications', [
                        'medical_notes' => null, 'allergies' => null, 'sen_category' => null, 'sen_support' => null,
                        'guardian_phone' => '', 'guardian_phone_alt' => null, 'guardian_email' => null,
                        'guardian_occupation' => null, 'guardian_address' => null,
                    ], 'id = ?', [$p['application_id']]);
                }
                // Parents whose children have all been anonymised: remove contact details, close the login
                foreach ($d->all('SELECT guardian_id FROM pupil_guardian WHERE pupil_id = ?', [$id]) as $g) {
                    $open = (int) $d->value(
                        'SELECT COUNT(*) FROM pupil_guardian pg JOIN pupils x ON x.id = pg.pupil_id WHERE pg.guardian_id = ? AND x.anonymised_at IS NULL',
                        [$g['guardian_id']]
                    );
                    if ($open === 0) {
                        $gu = $d->one('SELECT user_id FROM guardians WHERE id = ?', [$g['guardian_id']]);
                        $d->update('guardians', ['phone' => '', 'phone_alt' => null, 'email' => null, 'occupation' => null, 'address' => null], 'id = ?', [$g['guardian_id']]);
                        if (!empty($gu['user_id'])) {
                            $d->update('users', ['is_active' => 0], 'id = ?', [$gu['user_id']]);
                        }
                    }
                }
                $n++;
            }
            return $n;
        });
        foreach ($photos as $ph) {
            $path = Upload::path($ph);
            if ($path && is_file($path)) {
                @unlink($path);
            }
        }
        return $n;
    }

    public static function noticeText(): string
    {
        $custom = trim(setting('privacy_notice'));
        return $custom !== '' ? $custom : self::defaultNotice();
    }

    public static function defaultNotice(): string
    {
        $school = school_name();
        $contact = setting('privacy_contact') !== '' ? setting('privacy_contact') : trim('the Head Teacher ' . (setting('school_phone') ? '(' . setting('school_phone') . ')' : '') . (setting('school_email') ? ', ' . setting('school_email') : ''));
        $dpc = setting('dpc_registration_no');
        $dpcText = $dpc !== '' ? " We are registered with the Data Protection Commission (registration no. $dpc)." : '';
        $years = self::retentionYears();
        return <<<TXT
## Who we are
$school is the data controller for the information in this school management system.$dpcText

## What we keep
- About students: name, date of birth, gender, photo, class, attendance, scores and report cards, health and allergy notes, special educational needs, fees and payments, library and bus records.
- About parents and guardians: name, relationship, phone numbers, email, occupation and address.
- About staff: contact details, qualifications, NTC licence, SSNIT and tax numbers, pay records.

## Why we keep it
To admit and teach students, keep them safe and healthy, report their progress, bill and receipt school fees, send SMS messages to parents, pay staff, and meet the requirements of the Ghana Education Service, NaSIA, WAEC and other authorities.

## Who can see it
Only school staff who need it for their work — for example, a class teacher sees their own class, the Bursar sees fees. Parents see only their own children. We share information only when the law requires it (for example GES census returns, BECE registration with WAEC, SSNIT and GRA for staff), or with service providers who send our SMS and handle online payments for us.

## How long we keep it
While the student is at the school, and for $years years after they leave. After that, contact, health and other personal details are removed; the name, class history, results and fee records are kept so the school can issue transcripts, testimonials and keep its accounts.

## Your rights
Under the Data Protection Act, 2012 (Act 843) you may ask to see the information we hold about you or your child, ask us to correct it, or object to how it is used. Contact $contact. You may also contact the Data Protection Commission (dataprotection.org.gh).
TXT;
    }
}

