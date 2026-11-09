<?php
declare(strict_types=1);

/**
 * Documents: the school document library (policies, circulars, GES letters, minutes, licences) and each pupil's
 * and staff member's files (birth certificate, NHIS, certificates, contracts, NTC licence) with expiry reminders.
 */
class DocumentController extends Controller
{
    public function __construct()
    {
        $this->needsTable('person_documents');
    }

    // ---------------------------------------------------------------- school library

    public function index(): void
    {
        $cat = (string) ($_GET['category'] ?? '');
        $q = trim((string) ($_GET['q'] ?? ''));
        $archived = !empty($_GET['archived']) && can('documents.manage');
        $sql = 'SELECT d.*, u.name AS uploaded_name FROM school_documents d LEFT JOIN users u ON u.id = d.uploaded_by WHERE d.archived_at IS ' . ($archived ? 'NOT NULL' : 'NULL');
        $p = [];
        if (!can('documents.manage')) {
            $sql .= " AND d.audience = 'all_staff'";
        }
        if (isset(Documents::CATEGORIES[$cat])) {
            $sql .= ' AND d.category = ?';
            $p[] = $cat;
        }
        if ($q !== '') {
            $sql .= ' AND (d.title LIKE ? OR d.reference_no LIKE ? OR d.description LIKE ?)';
            array_push($p, "%$q%", "%$q%", "%$q%");
        }
        [$rows, $pager] = paginate($sql . ' ORDER BY d.created_at DESC', $p, 30);
        $expiring = can('documents.manage') ? Documents::expiring(Documents::REMIND_DAYS, can('documents.staff')) : [];
        $this->view('documents/index', ['title' => 'Documents', 'rows' => $rows, 'pager' => $pager, 'cat' => $cat, 'q' => $q, 'archived' => $archived, 'expiring' => $expiring]);
    }

    public function store(): void
    {
        $title = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 160);
        $cat = (string) ($_POST['category'] ?? '');
        $aud = ($_POST['audience'] ?? '') === 'management' ? 'management' : 'all_staff';
        $exp = trim((string) ($_POST['expires_on'] ?? ''));
        $err = null;
        if ($title === '') {
            $err = 'Give the document a title.';
        } elseif (!isset(Documents::CATEGORIES[$cat])) {
            $err = 'Choose the kind of document.';
        } elseif ($exp !== '' && !Validator::isDate($exp)) {
            $err = 'Enter a valid expiry date, or leave it empty.';
        }
        [$f, $ferr] = $err ? [null, null] : Upload::store($_FILES['file'] ?? ['error' => UPLOAD_ERR_NO_FILE], 'documents');
        $err = $err ?: ($ferr ?: ($f ? null : 'Choose the file to upload (PDF, JPG or PNG).'));
        if ($err) {
            with_old($_POST);
            flash('error', $err);
            redirect('documents');
        }
        $id = db()->insert('school_documents', ['title' => $title, 'category' => $cat, 'reference_no' => mb_substr(trim((string) ($_POST['reference_no'] ?? '')), 0, 60) ?: null,
            'description' => mb_substr(trim((string) ($_POST['description'] ?? '')), 0, 500) ?: null, 'audience' => $aud, 'file' => $f['stored_name'], 'file_name' => $f['original_name'],
            'file_mime' => $f['mime'], 'file_size' => $f['size'], 'expires_on' => $exp ?: null, 'uploaded_by' => Auth::id()]);
        audit('school_document_uploaded', 'school_documents', $id, ['title' => $title]);
        flash('success', 'Document added to the library.');
        redirect('documents');
    }

    public function file(int $id): void
    {
        $d = db()->one('SELECT * FROM school_documents WHERE id = ?', [$id]) ?? $this->notFound();
        if (($d['audience'] === 'management' || $d['archived_at']) && !can('documents.manage')) {
            $this->notFound();
        }
        Upload::send($d['file'], $d['file_mime'], $d['file_name']);
    }

    public function archive(int $id): void
    {
        db()->update('school_documents', ['archived_at' => date('Y-m-d H:i:s')], 'id = ? AND archived_at IS NULL', [$id]);
        audit('school_document_archived', 'school_documents', $id);
        flash('success', 'Document archived. Find it again under “Archived”.');
        redirect('documents');
    }

    public function restore(int $id): void
    {
        db()->update('school_documents', ['archived_at' => null], 'id = ?', [$id]);
        audit('school_document_restored', 'school_documents', $id);
        flash('success', 'Document restored to the library.');
        redirect('documents');
    }

    public function expiring(): void
    {
        $this->view('documents/expiring', ['title' => 'Expiring documents', 'rows' => Documents::expiring(90, can('documents.staff'))]);
    }

    // ---------------------------------------------------------------- pupil files

    public function pupil(int $id): void
    {
        $pupil = db()->one('SELECT * FROM pupils WHERE id = ?', [$id]) ?? $this->notFound();
        if (!Documents::canSeePupil($id)) {
            $this->forbidden();
        }
        $fromApp = can('admission.view') ? db()->all('SELECT d.*, a.id AS app_id, a.ref_no FROM application_documents d JOIN applications a ON a.id = d.application_id WHERE a.pupil_id = ? ORDER BY d.uploaded_at', [$id]) : [];
        $this->view('documents/person', ['title' => 'Files — ' . full_name($pupil), 'ownerType' => 'pupil', 'owner' => $pupil, 'name' => full_name($pupil),
            'back' => ['pupils/' . $id, 'Student record'], 'docs' => Documents::forPerson('pupil', $id), 'types' => Documents::PUPIL_TYPES, 'canEdit' => can('documents.manage'),
            'action' => 'documents/pupils/' . $id, 'fromApp' => $fromApp]);
    }

    public function storePupil(int $id): void
    {
        db()->one('SELECT id FROM pupils WHERE id = ?', [$id]) ?? $this->notFound();
        if (!Documents::canSeePupil($id)) {
            $this->forbidden();
        }
        $err = Documents::storePerson('pupil', $id, $_POST, $_FILES['file'] ?? ['error' => UPLOAD_ERR_NO_FILE]);
        flash($err ? 'error' : 'success', $err ?: 'File added.');
        redirect('documents/pupils/' . $id);
    }

    // ---------------------------------------------------------------- staff files

    public function staffList(): void
    {
        $rows = db()->all(
            "SELECT s.id, s.staff_no, s.name, s.position, (SELECT COUNT(*) FROM person_documents d WHERE d.owner_type = 'staff' AND d.owner_id = s.id) AS files,
                    (SELECT MIN(d.expires_on) FROM person_documents d WHERE d.owner_type = 'staff' AND d.owner_id = s.id AND d.expires_on >= CURDATE() - INTERVAL 60 DAY) AS next_expiry
             FROM staff s WHERE s.is_active = 1 ORDER BY s.name"
        );
        $this->view('documents/staff_list', ['title' => 'Staff files', 'rows' => $rows]);
    }

    public function staff(int $id): void
    {
        $s = db()->one('SELECT * FROM staff WHERE id = ?', [$id]) ?? $this->notFound();
        if (!Documents::canSeeStaff($s)) {
            $this->forbidden();
        }
        $this->view('documents/person', ['title' => 'Files — ' . $s['name'], 'ownerType' => 'staff', 'owner' => $s, 'name' => $s['name'],
            'back' => can('documents.staff') ? ['documents/staff', 'Staff files'] : ['dashboard', 'Dashboard'], 'docs' => Documents::forPerson('staff', $id),
            'types' => Documents::STAFF_TYPES, 'canEdit' => can('documents.staff'), 'action' => 'documents/staff/' . $id, 'fromApp' => []]);
    }

    public function storeStaff(int $id): void
    {
        db()->one('SELECT id FROM staff WHERE id = ?', [$id]) ?? $this->notFound();
        $err = Documents::storePerson('staff', $id, $_POST, $_FILES['file'] ?? ['error' => UPLOAD_ERR_NO_FILE]);
        flash($err ? 'error' : 'success', $err ?: 'File added.');
        redirect('documents/staff/' . $id);
    }

    /** A staff member's own files. */
    public function mine(): void
    {
        $s = db()->one('SELECT * FROM staff WHERE user_id = ?', [Auth::id()]);
        if (!$s) {
            flash('info', 'Your sign-in is not linked to a staff record, so there are no files to show.');
            redirect('dashboard');
        }
        redirect('documents/staff/' . $s['id']);
    }

    // ---------------------------------------------------------------- one person file

    public function personFile(int $id): void
    {
        $d = db()->one('SELECT * FROM person_documents WHERE id = ?', [$id]) ?? $this->notFound();
        if (!$this->mayRead($d)) {
            $this->notFound();
        }
        Upload::send($d['file'], $d['file_mime'], $d['file_name']);
    }

    public function deletePerson(int $id): void
    {
        $d = db()->one('SELECT * FROM person_documents WHERE id = ?', [$id]) ?? $this->notFound();
        $ok = $d['owner_type'] === 'pupil' ? (can('documents.manage') && Documents::canSeePupil((int) $d['owner_id'])) : can('documents.staff');
        if (!$ok) {
            $this->forbidden();
        }
        db()->run('DELETE FROM person_documents WHERE id = ?', [$id]);
        if ($path = Upload::path($d['file'])) {
            @unlink($path);
        }
        audit('person_document_deleted', 'person_documents', $id, ['owner' => $d['owner_type'] . ':' . $d['owner_id'], 'type' => $d['doc_type']]);
        flash('success', 'File removed.');
        redirect('documents/' . ($d['owner_type'] === 'pupil' ? 'pupils/' : 'staff/') . $d['owner_id']);
    }

    private function mayRead(array $d): bool
    {
        if ($d['owner_type'] === 'pupil') {
            return Documents::canSeePupil((int) $d['owner_id']);
        }
        $s = db()->one('SELECT * FROM staff WHERE id = ?', [$d['owner_id']]);
        return $s && Documents::canSeeStaff($s);
    }
}
