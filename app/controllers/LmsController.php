<?php
declare(strict_types=1);

/**
 * LMS — learning materials: teachers post lesson notes, videos (YouTube plays inside the page), web links and
 * worksheets for a class or a class-subject; pupils and parents read them; teachers see who has opened each one.
 */
class LmsController extends Controller
{
    public function __construct()
    {
        $this->needsTable('lms_materials');
        if ((Auth::role() === 'pupil' && !can('lms.view')) || (Auth::role() === 'parent' && !can('portal.parent'))) {
            $this->forbidden();
        }
    }

    public function index(): void
    {
        $viewer = Learning::viewerClasses();
        if (Auth::role() === 'pupil' || Auth::role() === 'parent') {
            $ids = array_keys($viewer) ?: [0];
            $rows = db()->all(
                'SELECT m.*, c.name AS class_name, s.name AS subject_name, u.name AS teacher_name FROM lms_materials m JOIN classes c ON c.id = m.class_id
                 LEFT JOIN class_subjects cs ON cs.id = m.class_subject_id LEFT JOIN subjects s ON s.id = cs.subject_id LEFT JOIN users u ON u.id = m.created_by
                 WHERE m.status = "published" AND m.class_id IN (' . implode(',', array_map('intval', $ids)) . ') ORDER BY m.created_at DESC LIMIT 200'
            );
            $seen = array_flip(array_map('intval', array_column(db()->all('SELECT material_id FROM lms_views WHERE user_id = ?', [Auth::id()]), 'material_id')));
            $this->view('lms/learn', ['title' => 'Learning materials', 'rows' => $rows, 'seen' => $seen, 'viewer' => $viewer]);
            return;
        }
        if (!can('lms.manage')) {
            $this->forbidden();
        }
        $targets = Learning::targets();
        $classIds = array_values(array_unique(array_column($targets, 'class_id'))) ?: [0];
        $cid = (int) ($_GET['class'] ?? 0);
        $sql = 'SELECT m.*, c.name AS class_name, s.name AS subject_name, u.name AS teacher_name,
                       (SELECT COUNT(DISTINCT v.pupil_id) FROM lms_views v WHERE v.material_id = m.id AND v.pupil_id IS NOT NULL) AS seen
                FROM lms_materials m JOIN classes c ON c.id = m.class_id LEFT JOIN class_subjects cs ON cs.id = m.class_subject_id
                LEFT JOIN subjects s ON s.id = cs.subject_id LEFT JOIN users u ON u.id = m.created_by
                WHERE (m.class_id IN (' . implode(',', array_map('intval', $classIds)) . ') OR m.created_by = ?)';
        $p = [Auth::id()];
        if ($cid) {
            $sql .= ' AND m.class_id = ?';
            $p[] = $cid;
        }
        [$rows, $pager] = paginate($sql . ' ORDER BY m.created_at DESC', $p, 30);
        $rows = array_values(array_filter($rows, fn($r) => Learning::canManage($r, 'lms.manage')));
        $classes = db()->all('SELECT id, name FROM classes WHERE id IN (' . implode(',', array_map('intval', $classIds)) . ') ORDER BY name');
        $this->view('lms/index', ['title' => 'LMS — learning materials', 'rows' => $rows, 'pager' => $pager, 'classes' => $classes, 'cid' => $cid]);
    }

    public function create(): void
    {
        $this->view('lms/form', ['title' => 'New learning material', 'm' => null, 'targets' => Learning::targets(), 'strands' => $this->strands()]);
    }

    public function store(): void
    {
        $data = $this->input('lms/create');
        $id = db()->insert('lms_materials', $data + ['created_by' => Auth::id(), 'term_id' => current_term()['id'] ?? null]);
        audit('lms_material_created', 'lms_materials', $id, ['class' => $data['class_id'], 'kind' => $data['kind']]);
        if ($data['status'] === 'published') {
            $this->tell($id, $data);
        }
        flash('success', $data['status'] === 'published' ? 'Published. Students and parents of the class can read it now.' : 'Saved as a draft (only you can see it).');
        redirect('lms/' . $id);
    }

    public function show(int $id): void
    {
        $m = $this->find($id);
        if (!$this->canSee($m)) {
            $this->notFound();
        }
        $manage = Learning::canManage($m, 'lms.manage');
        if (in_array(Auth::role(), ['pupil', 'parent'], true)) {
            $pupil = Learning::viewerClasses()[(int) $m['class_id']][0]['id'] ?? null;
            db()->run('INSERT INTO lms_views (material_id, user_id, pupil_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE last_at = NOW(), times = times + 1', [$id, Auth::id(), Auth::role() === 'pupil' ? $pupil : null]);
        }
        $yt = $m['kind'] === 'video' && $m['url'] ? Learning::youtubeId($m['url']) : null;
        if ($yt) { // allow the YouTube player (privacy-enhanced domain) on this page only
            header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; frame-src https://www.youtube-nocookie.com; frame-ancestors 'self'; form-action 'self'");
        }
        $readers = [];
        if ($manage) {
            $roster = Assignments::roster((int) $m['class_id']);
            $seen = array_column(db()->all('SELECT pupil_id, MAX(last_at) AS at FROM lms_views WHERE material_id = ? AND pupil_id IS NOT NULL GROUP BY pupil_id', [$id]), 'at', 'pupil_id');
            foreach ($roster as $p) {
                $readers[] = $p + ['seen_at' => $seen[$p['id']] ?? null];
            }
        }
        $this->view('lms/show', ['title' => $m['title'], 'm' => $m, 'manage' => $manage, 'yt' => $yt, 'readers' => $readers,
            'parentViews' => $manage ? (int) db()->value("SELECT COUNT(*) FROM lms_views v JOIN users u ON u.id = v.user_id WHERE v.material_id = ? AND u.role = 'parent'", [$id]) : 0]);
    }

    public function edit(int $id): void
    {
        $m = $this->managed($id);
        $this->view('lms/form', ['title' => 'Edit material', 'm' => $m, 'targets' => Learning::targets(), 'strands' => $this->strands()]);
    }

    public function update(int $id): void
    {
        $m = $this->managed($id);
        $data = $this->input('lms/' . $id . '/edit', $m);
        db()->update('lms_materials', $data + ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        audit('lms_material_updated', 'lms_materials', $id);
        if ($m['status'] === 'draft' && $data['status'] === 'published') {
            $this->tell($id, $data);
        }
        flash('success', 'Saved.');
        redirect('lms/' . $id);
    }

    public function delete(int $id): void
    {
        $m = $this->managed($id);
        db()->run('DELETE FROM lms_materials WHERE id = ?', [$id]);
        if ($m['attachment'] && ($path = Upload::path($m['attachment']))) {
            @unlink($path);
        }
        audit('lms_material_deleted', 'lms_materials', $id, ['title' => $m['title']]);
        flash('success', 'Material deleted.');
        redirect('lms');
    }

    public function file(int $id): void
    {
        $m = $this->find($id);
        if (!$m['attachment'] || !$this->canSee($m)) {
            $this->notFound();
        }
        Upload::send($m['attachment'], $m['attachment_mime'], $m['attachment_name']);
    }

    // ---------------------------------------------------------------- helpers

    private function input(string $back, ?array $old = null): array
    {
        $t = Learning::target((string) ($_POST['target'] ?? ''));
        $title = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 150);
        $kind = (string) ($_POST['kind'] ?? 'note');
        $body = trim((string) ($_POST['body'] ?? ''));
        $url = trim((string) ($_POST['url'] ?? ''));
        $strand = (int) ($_POST['strand_id'] ?? 0) ?: null;
        $err = null;
        if (!$t) {
            $err = 'Choose the class (and subject) this is for.';
        } elseif ($title === '') {
            $err = 'Give it a title.';
        } elseif (!isset(Learning::KINDS[$kind])) {
            $err = 'Choose what kind of material it is.';
        } elseif (mb_strlen($body) > 20000) {
            $err = 'The lesson note is too long (20,000 characters at most).';
        } elseif ($kind === 'note' && $body === '') {
            $err = 'Write the lesson note.';
        } elseif (in_array($kind, ['video', 'link'], true) && (!filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with($url, 'https://') || strlen($url) > 500)) {
            $err = 'Paste the full web address, starting with https://';
        } elseif ($strand && !db()->value('SELECT 1 FROM curriculum_strands WHERE id = ?', [$strand])) {
            $err = 'Choose a valid strand.';
        }
        [$f, $ferr] = $err ? [null, null] : Upload::store($_FILES['attachment'] ?? ['error' => UPLOAD_ERR_NO_FILE], 'lms');
        $err = $err ?: $ferr;
        if (!$err && $kind === 'file' && !$f && empty($old['attachment'])) {
            $err = 'Choose the worksheet or file to upload (PDF, JPG or PNG).';
        }
        if ($err) {
            with_old($_POST);
            flash('error', $err);
            redirect($back);
        }
        $data = ['class_id' => $t['class_id'], 'class_subject_id' => $t['class_subject_id'], 'strand_id' => $strand, 'title' => $title, 'kind' => $kind,
            'body' => $body !== '' ? $body : null, 'url' => in_array($kind, ['video', 'link'], true) ? $url : null,
            'status' => ($_POST['status'] ?? '') === 'draft' ? 'draft' : 'published'];
        if ($f) {
            $data += ['attachment' => $f['stored_name'], 'attachment_name' => $f['original_name'], 'attachment_mime' => $f['mime']];
        }
        return $data;
    }

    private function tell(int $id, array $data): void
    {
        $users = Learning::classUsers((int) $data['class_id']);
        if ($users) {
            Notifier::inapp($users, 'New learning material: ' . $data['title'], Learning::KINDS[$data['kind']] . ' from ' . (auth()['name'] ?? 'the teacher'), url('lms/' . $id));
        }
    }

    private function find(int $id): array
    {
        return db()->one(
            'SELECT m.*, c.name AS class_name, s.name AS subject_name, u.name AS teacher_name, st.name AS strand_name FROM lms_materials m JOIN classes c ON c.id = m.class_id
             LEFT JOIN class_subjects cs ON cs.id = m.class_subject_id LEFT JOIN subjects s ON s.id = cs.subject_id LEFT JOIN users u ON u.id = m.created_by
             LEFT JOIN curriculum_strands st ON st.id = m.strand_id WHERE m.id = ?',
            [$id]
        ) ?? $this->notFound();
    }

    private function managed(int $id): array
    {
        $m = $this->find($id);
        if (!Learning::canManage($m, 'lms.manage')) {
            $this->forbidden();
        }
        return $m;
    }

    private function canSee(array $m): bool
    {
        if (Learning::canManage($m, 'lms.manage')) {
            return true;
        }
        if ($m['status'] !== 'published') {
            return false;
        }
        if (in_array(Auth::role(), ['pupil', 'parent'], true)) {
            return isset(Learning::viewerClasses()[(int) $m['class_id']]);
        }
        return can('lms.manage') && Auth::classScope() === null;
    }

    private function strands(): array
    {
        return db()->all('SELECT st.id, st.name, s.name AS subject_name FROM curriculum_strands st JOIN subjects s ON s.id = st.subject_id WHERE st.is_active = 1 AND st.parent_id IS NULL ORDER BY s.name, st.sort_order, st.name');
    }
}
