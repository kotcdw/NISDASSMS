<?php
declare(strict_types=1);

/** Documents: the school document library and each pupil's / staff member's files, with expiry reminders. */
final class Documents
{
    public const CATEGORIES = ['policy' => 'Policy', 'circular' => 'Circular / letter to parents', 'ges' => 'GES / NaCCA / NaSIA letter', 'minutes' => 'Minutes (staff, PTA, board)',
        'form' => 'Form / template', 'certificate' => 'School licence / certificate', 'other' => 'Other'];
    public const PUPIL_TYPES = ['birth_certificate' => 'Birth certificate', 'passport_photo' => 'Passport photo', 'nhis_card' => 'NHIS card', 'immunisation' => 'Immunisation / weighing card',
        'previous_report' => 'Report from previous school', 'transfer_letter' => 'Transfer / release letter', 'medical' => 'Medical report', 'consent' => 'Signed consent form', 'other' => 'Other'];
    public const STAFF_TYPES = ['appointment_letter' => 'Appointment letter', 'contract' => 'Contract', 'certificate' => 'Certificate / degree', 'ntc_licence' => 'NTC teaching licence',
        'national_id' => 'Ghana Card (copy)', 'ssnit' => 'SSNIT document', 'cv' => 'CV', 'medical' => 'Medical report', 'reference' => 'Reference letter', 'other' => 'Other'];
    public const REMIND_DAYS = 30;

    public static function typeLabel(string $ownerType, string $type): string
    {
        return ($ownerType === 'pupil' ? self::PUPIL_TYPES : self::STAFF_TYPES)[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    /** May the signed-in person see this pupil's files? (sensitive data, own classes only for teachers) */
    public static function canSeePupil(int $pupilId): bool
    {
        if (!can('pupils.view') || !can('pupils.sensitive')) {
            return false;
        }
        $scope = Auth::classScope();
        if ($scope === null) {
            return true;
        }
        $cls = (int) db()->value("SELECT class_id FROM enrolments WHERE pupil_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1", [$pupilId]);
        return in_array($cls, $scope, true);
    }

    /** May the signed-in person see this staff member's files? (staff files permission, or their own) */
    public static function canSeeStaff(array $staff): bool
    {
        return can('documents.staff') || ((int) ($staff['user_id'] ?? 0) && (int) $staff['user_id'] === (int) Auth::id());
    }

    /** Store an uploaded file for a pupil or staff member. Returns an error or null. */
    public static function storePerson(string $ownerType, int $ownerId, array $post, array $file): ?string
    {
        $types = $ownerType === 'pupil' ? self::PUPIL_TYPES : self::STAFF_TYPES;
        $type = (string) ($post['doc_type'] ?? '');
        $exp = trim((string) ($post['expires_on'] ?? ''));
        if (!isset($types[$type])) {
            return 'Choose what kind of document it is.';
        }
        if ($exp !== '' && !Validator::isDate($exp)) {
            return 'Enter a valid expiry date, or leave it empty.';
        }
        [$f, $err] = Upload::store($file, $ownerType === 'pupil' ? 'pupil-files' : 'staff-files');
        if ($err || !$f) {
            return $err ?: 'Choose the file to upload (PDF, JPG or PNG).';
        }
        $id = db()->insert('person_documents', ['owner_type' => $ownerType, 'owner_id' => $ownerId, 'doc_type' => $type,
            'title' => mb_substr(trim((string) ($post['title'] ?? '')), 0, 160) ?: null, 'file' => $f['stored_name'], 'file_name' => $f['original_name'],
            'file_mime' => $f['mime'], 'file_size' => $f['size'], 'expires_on' => $exp ?: null, 'uploaded_by' => Auth::id()]);
        audit('person_document_uploaded', 'person_documents', $id, ['owner' => "$ownerType:$ownerId", 'type' => $type]);
        return null;
    }

    public static function forPerson(string $ownerType, int $ownerId): array
    {
        return db()->all('SELECT d.*, u.name AS uploaded_name FROM person_documents d LEFT JOIN users u ON u.id = d.uploaded_by WHERE d.owner_type = ? AND d.owner_id = ? ORDER BY d.doc_type, d.created_at DESC', [$ownerType, $ownerId]);
    }

    /** Documents that expire within $days days (or have expired in the last 60 days). */
    public static function expiring(int $days = self::REMIND_DAYS, bool $withStaff = true): array
    {
        $out = db()->all(
            "SELECT 'school' AS kind, d.id, d.title, d.category AS type, d.expires_on, NULL AS owner_name, NULL AS owner_id FROM school_documents d
             WHERE d.archived_at IS NULL AND d.expires_on IS NOT NULL AND d.expires_on BETWEEN CURDATE() - INTERVAL 60 DAY AND CURDATE() + INTERVAL ? DAY", [$days]
        );
        $out = array_merge($out, db()->all(
            "SELECT 'pupil' AS kind, d.id, d.title, d.doc_type AS type, d.expires_on, CONCAT(p.first_name, ' ', p.last_name) AS owner_name, p.id AS owner_id
             FROM person_documents d JOIN pupils p ON p.id = d.owner_id WHERE d.owner_type = 'pupil' AND p.status = 'active'
               AND d.expires_on IS NOT NULL AND d.expires_on BETWEEN CURDATE() - INTERVAL 60 DAY AND CURDATE() + INTERVAL ? DAY", [$days]
        ));
        if ($withStaff) {
            $out = array_merge($out, db()->all(
                "SELECT 'staff' AS kind, d.id, d.title, d.doc_type AS type, d.expires_on, s.name AS owner_name, s.id AS owner_id
                 FROM person_documents d JOIN staff s ON s.id = d.owner_id WHERE d.owner_type = 'staff' AND s.is_active = 1
                   AND d.expires_on IS NOT NULL AND d.expires_on BETWEEN CURDATE() - INTERVAL 60 DAY AND CURDATE() + INTERVAL ? DAY", [$days]
            ));
        }
        usort($out, fn($a, $b) => $a['expires_on'] <=> $b['expires_on']);
        return $out;
    }

    /** Daily: tell the people who keep the files about documents expiring in 30 days (once per document). */
    public static function reminders(): int
    {
        try {
            if (setting('documents_last_check') === date('Y-m-d')) {
                return 0;
            }
            setting_set('documents_last_check', date('Y-m-d'));
            $n = 0;
            foreach (db()->all("SELECT * FROM school_documents WHERE archived_at IS NULL AND reminded_at IS NULL AND expires_on IS NOT NULL AND expires_on <= CURDATE() + INTERVAL " . self::REMIND_DAYS . " DAY") as $d) {
                db()->update('school_documents', ['reminded_at' => date('Y-m-d H:i:s')], 'id = ?', [$d['id']]);
                Notifier::inappPermission('documents.manage', 'Document expiring: ' . $d['title'], 'Expires ' . fmt_date($d['expires_on']) . '. Upload the renewed one.', url('documents'));
                $n++;
            }
            foreach (db()->all("SELECT d.*, s.name AS sname, s.user_id, p.first_name, p.last_name FROM person_documents d
                                LEFT JOIN staff s ON d.owner_type = 'staff' AND s.id = d.owner_id LEFT JOIN pupils p ON d.owner_type = 'pupil' AND p.id = d.owner_id
                                WHERE d.reminded_at IS NULL AND d.expires_on IS NOT NULL AND d.expires_on <= CURDATE() + INTERVAL " . self::REMIND_DAYS . " DAY") as $d) {
                db()->update('person_documents', ['reminded_at' => date('Y-m-d H:i:s')], 'id = ?', [$d['id']]);
                $what = self::typeLabel($d['owner_type'], $d['doc_type']);
                if ($d['owner_type'] === 'staff') {
                    Notifier::inappPermission('documents.staff', "$what expiring: " . $d['sname'], 'Expires ' . fmt_date($d['expires_on']) . '.', url('documents/staff/' . $d['owner_id']));
                    if ($d['user_id']) {
                        Notifier::inapp([(int) $d['user_id']], "Your $what expires " . fmt_date($d['expires_on']), 'Please bring the renewed one to the office.', url('documents/my'));
                    }
                } else {
                    Notifier::inappPermission('documents.manage', "$what expiring: " . trim($d['first_name'] . ' ' . $d['last_name']), 'Expires ' . fmt_date($d['expires_on']) . '.', url('documents/pupils/' . $d['owner_id']));
                }
                $n++;
            }
            return $n;
        } catch (PDOException $e) {
            return 0; // migration 020 not applied yet
        }
    }
}
