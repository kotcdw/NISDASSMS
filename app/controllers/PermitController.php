<?php
declare(strict_types=1);

/** Licences & permits register: NaSIA licence, fire certificate, health permits… with expiry alerts 60 days ahead. */
class PermitController extends Controller
{
    public function __construct()
    {
        if (!Registers::ready()) {
            $this->needsTable('school_permits');
        }
    }

    public function index(): void
    {
        $rows = db()->all('SELECT * FROM school_permits ORDER BY expires_on IS NULL, expires_on, name');
        $edit = isset($_GET['edit']) && can('permits.manage') ? db()->one('SELECT * FROM school_permits WHERE id = ?', [(int) $_GET['edit']]) : null;
        $this->view('permits/index', ['title' => 'Licences & permits', 'rows' => $rows, 'edit' => $edit]);
    }

    public function save(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 150);
        $issued = trim((string) ($_POST['issued_on'] ?? ''));
        $expires = trim((string) ($_POST['expires_on'] ?? ''));
        $err = null;
        if ($name === '') {
            $err = 'Write the name of the licence or permit.';
        } elseif (($issued !== '' && !Validator::isDate($issued)) || ($expires !== '' && !Validator::isDate($expires))) {
            $err = 'Check the dates.';
        } elseif ($issued !== '' && $expires !== '' && $expires < $issued) {
            $err = 'The expiry date is before the issue date.';
        }
        if ($err) {
            with_old($_POST);
            flash('error', $err);
            redirect($id ? 'permits?edit=' . $id : 'permits');
        }
        $data = ['name' => $name, 'authority' => mb_substr(trim((string) ($_POST['authority'] ?? '')), 0, 150) ?: null, 'ref_no' => mb_substr(trim((string) ($_POST['ref_no'] ?? '')), 0, 80) ?: null,
            'issued_on' => $issued ?: null, 'expires_on' => $expires ?: null, 'notes' => mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 500) ?: null];
        if ($id) {
            $old = db()->one('SELECT * FROM school_permits WHERE id = ?', [$id]) ?? $this->notFound();
            if ($old['expires_on'] !== $data['expires_on']) {
                $data['alerted_on'] = null; // renewed: alert again before the new date
            }
            db()->update('school_permits', $data, 'id = ?', [$id]);
            audit('permit_updated', 'school_permits', $id);
        } else {
            $id = db()->insert('school_permits', $data + ['created_by' => Auth::id()]);
            audit('permit_added', 'school_permits', $id);
        }
        [$file, $upErr] = Upload::store($_FILES['document'] ?? ['error' => UPLOAD_ERR_NO_FILE], 'permits');
        if ($upErr) {
            flash('warning', 'Document not saved: ' . $upErr);
        } elseif ($file) {
            db()->update('school_permits', ['document' => $file['stored_name'], 'document_name' => $file['original_name'], 'document_mime' => $file['mime']], 'id = ?', [$id]);
        }
        flash('success', "$name saved.");
        redirect('permits');
    }

    public function delete(int $id): void
    {
        $p = db()->one('SELECT * FROM school_permits WHERE id = ?', [$id]) ?? $this->notFound();
        db()->run('DELETE FROM school_permits WHERE id = ?', [$id]);
        audit('permit_deleted', 'school_permits', $id, ['name' => $p['name']]);
        flash('success', $p['name'] . ' removed from the register.');
        redirect('permits');
    }

    public function file(int $id): void
    {
        $p = db()->one('SELECT * FROM school_permits WHERE id = ?', [$id]) ?? $this->notFound();
        if (!$p['document']) {
            $this->notFound();
        }
        Upload::send($p['document'], $p['document_mime'], $p['document_name']);
    }
}
