<?php
declare(strict_types=1);

/**
 * Pupil leave: a parent (or the class teacher / Head for them) asks for days off; the class teacher or Head approves.
 * Approved days are marked "Excused" on the register (and already-marked absences on those days become Excused).
 */
class LeaveController extends Controller
{
    public const KINDS = ['sick' => 'Sick / hospital', 'family' => 'Family matter', 'travel' => 'Travel', 'funeral' => 'Funeral', 'religious' => 'Church / religious', 'other' => 'Other'];
    public const STATUS = ['pending' => ['Waiting for approval', 'warn'], 'approved' => ['Approved', 'good'], 'rejected' => ['Not approved', 'bad'], 'cancelled' => ['Cancelled', 'neutral']];

    /** Staff list (class teacher: own class; Head/coordinator: all). Parents see their own requests. */
    public function index(): void
    {
        if (Auth::role() === 'parent') {
            $ids = array_map('intval', array_column(Accounts::childrenOf((int) Auth::id()), 'id')) ?: [0];
            $rows = db()->all(
                'SELECT l.*, p.first_name, p.last_name, d.name AS decided_name FROM leave_requests l JOIN pupils p ON p.id = l.pupil_id
                 LEFT JOIN users d ON d.id = l.decided_by WHERE l.pupil_id IN (' . implode(',', $ids) . ') ORDER BY l.from_date DESC, l.id DESC'
            );
            $this->view('leave/mine', ['title' => 'Leave requests', 'rows' => $rows]);
            return;
        }
        if (!can('leave.approve')) {
            $this->forbidden();
        }
        $status = (string) ($_GET['status'] ?? 'pending');
        [$w, $p] = $this->scopeSql();
        $sql = "SELECT l.*, p.first_name, p.last_name, p.admission_no, c.name AS class_name, r.name AS requested_name, d.name AS decided_name
                FROM leave_requests l JOIN pupils p ON p.id = l.pupil_id
                LEFT JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ? AND e.status = 'active' LEFT JOIN classes c ON c.id = e.class_id
                LEFT JOIN users r ON r.id = l.requested_by LEFT JOIN users d ON d.id = l.decided_by WHERE $w";
        array_unshift($p, current_year()['id'] ?? 0);
        if (isset(self::STATUS[$status])) {
            $sql .= ' AND l.status = ?';
            $p[] = $status;
        }
        [$rows, $pager] = paginate($sql . ' ORDER BY l.status = "pending" DESC, l.from_date DESC, l.id DESC', $p, 30);
        $this->view('leave/index', ['title' => 'Student leave', 'rows' => $rows, 'pager' => $pager, 'status' => $status]);
    }

    public function create(): void
    {
        $this->view('leave/form', ['title' => 'Ask for leave', 'pupils' => $this->choosable(), 'pupilId' => (int) ($_GET['pupil'] ?? 0)]);
    }

    public function store(): void
    {
        $choices = array_column($this->choosable(), null, 'id');
        $pid = (int) ($_POST['pupil_id'] ?? 0);
        $from = (string) ($_POST['from_date'] ?? '');
        $to = (string) ($_POST['to_date'] ?? '') ?: $from;
        $kind = (string) ($_POST['kind'] ?? '');
        $reason = mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 500);
        $err = null;
        if (!isset($choices[$pid])) {
            $err = 'Choose the child.';
        } elseif (!Validator::isDate($from) || !Validator::isDate($to) || $to < $from) {
            $err = 'Enter the first and last day of the leave (the last day cannot be before the first).';
        } elseif ($from < date('Y-m-d', strtotime('-30 days'))) {
            $err = 'Leave can be asked for up to 30 days back.';
        } elseif ((strtotime($to) - strtotime($from)) / 86400 > 60) {
            $err = 'Leave longer than 60 days: please speak to the Head Teacher.';
        } elseif (!isset(self::KINDS[$kind])) {
            $err = 'Choose the reason.';
        } elseif ($reason === '') {
            $err = 'Write a short explanation.';
        } elseif (db()->value("SELECT 1 FROM leave_requests WHERE pupil_id = ? AND status IN ('pending','approved') AND from_date <= ? AND to_date >= ?", [$pid, $to, $from])) {
            $err = 'There is already a leave request for some of these days.';
        }
        [$file, $ferr] = $err ? [null, null] : Upload::store($_FILES['attachment'] ?? ['error' => UPLOAD_ERR_NO_FILE], 'leave');
        $err = $err ?: $ferr;
        if ($err) {
            with_old($_POST);
            flash('error', $err);
            redirect('leave/create', $pid ? ['pupil' => $pid] : []);
        }
        // Staff recording leave for a child they may approve: approved at once
        $auto = Auth::role() !== 'parent' && can('leave.approve');
        $id = db()->insert('leave_requests', [
            'pupil_id' => $pid, 'from_date' => $from, 'to_date' => $to, 'kind' => $kind, 'reason' => $reason, 'requested_by' => Auth::id(),
            'attachment' => $file['stored_name'] ?? null, 'attachment_name' => $file['original_name'] ?? null, 'attachment_mime' => $file['mime'] ?? null,
            'status' => $auto ? 'approved' : 'pending', 'decided_by' => $auto ? Auth::id() : null, 'decided_at' => $auto ? date('Y-m-d H:i:s') : null,
        ]);
        audit('leave_requested', 'leave_requests', $id, ['pupil' => $pid, 'from' => $from, 'to' => $to]);
        $c = $choices[$pid];
        if ($auto) {
            $this->applyToRegister($id);
        } else {
            $teacher = db()->value("SELECT c.class_teacher_id FROM enrolments e JOIN classes c ON c.id = e.class_id WHERE e.pupil_id = ? AND e.academic_year_id = ? AND e.status = 'active'", [$pid, current_year()['id'] ?? 0]);
            $msg = $c['first_name'] . ' ' . $c['last_name'] . ': ' . date('j M', strtotime($from)) . ($to !== $from ? ' – ' . date('j M', strtotime($to)) : '') . ' (' . self::KINDS[$kind] . ')';
            if ($teacher) {
                Notifier::inapp([(int) $teacher], 'Leave request to approve', $msg, 'leave/' . $id);
            } else {
                Notifier::inappPermission('leave.approve', 'Leave request to approve', $msg, 'leave/' . $id);
            }
        }
        flash('success', $auto ? 'Leave recorded and approved. The register shows these days as Excused.' : 'Leave request sent to the class teacher. You will see the answer here.');
        redirect(Auth::role() === 'parent' ? 'leave' : 'leave/' . $id);
    }

    public function show(int $id): void
    {
        $l = $this->find($id);
        $this->view('leave/show', ['title' => 'Leave request', 'l' => $l, 'canDecide' => $this->canDecide($l)]);
    }

    public function decide(int $id): void
    {
        $l = $this->find($id);
        $decision = (string) ($_POST['decision'] ?? '');
        $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 500) ?: null;
        if (!$this->canDecide($l) || $l['status'] !== 'pending' || !in_array($decision, ['approve', 'reject'], true)) {
            flash('error', 'This request cannot be decided by you, or was already decided.');
            redirect('leave/' . $id);
        }
        if ($decision === 'reject' && !$note) {
            flash('error', 'Write a short note to explain why it is not approved.');
            redirect('leave/' . $id);
        }
        $to = $decision === 'approve' ? 'approved' : 'rejected';
        db()->update('leave_requests', ['status' => $to, 'decided_by' => Auth::id(), 'decided_at' => date('Y-m-d H:i:s'), 'decision_note' => $note], "id = ? AND status = 'pending'", [$id]);
        if ($to === 'approved') {
            $this->applyToRegister($id);
        }
        if ($l['requested_by'] && (int) $l['requested_by'] !== Auth::id()) {
            Notifier::inapp([(int) $l['requested_by']], 'Leave ' . ($to === 'approved' ? 'approved' : 'not approved') . ': ' . $l['first_name'], $note, 'leave/' . $id);
        }
        audit('leave_' . $to, 'leave_requests', $id, ['note' => $note]);
        flash('success', $to === 'approved' ? 'Approved. The register shows these days as Excused.' : 'Marked as not approved; the parent has been told.');
        redirect('leave');
    }

    public function cancel(int $id): void
    {
        $l = $this->find($id);
        if ((int) $l['requested_by'] !== Auth::id() || !in_array($l['status'], ['pending', 'approved'], true) || ($l['status'] === 'approved' && $l['from_date'] <= date('Y-m-d'))) {
            flash('error', 'This request cannot be cancelled now. Contact the class teacher.');
            redirect('leave/' . $id);
        }
        db()->update('leave_requests', ['status' => 'cancelled'], 'id = ?', [$id]);
        audit('leave_cancelled', 'leave_requests', $id);
        flash('success', 'Leave request cancelled.');
        redirect(Auth::role() === 'parent' ? 'leave' : 'leave/' . $id);
    }

    public function file(int $id): void
    {
        $l = $this->find($id);
        if (!$l['attachment']) {
            $this->notFound();
        }
        Upload::send($l['attachment'], $l['attachment_mime'], $l['attachment_name']);
    }

    // ---------------------------------------------------------------- helpers

    /** Approved leave: change absences already marked on those days to Excused. */
    private function applyToRegister(int $id): void
    {
        $l = db()->one('SELECT * FROM leave_requests WHERE id = ?', [$id]);
        db()->run(
            "UPDATE attendance_records SET status = 'excused', note = COALESCE(note, ?) WHERE pupil_id = ? AND date BETWEEN ? AND ? AND status = 'absent'",
            ['Approved leave: ' . self::KINDS[$l['kind']], $l['pupil_id'], $l['from_date'], $l['to_date']]
        );
    }

    /** Pupils on approved leave on a date: pupil_id => leave kind label. */
    public static function onLeave(string $date, array $pupilIds): array
    {
        if (!$pupilIds) {
            return [];
        }
        $in = implode(',', array_map('intval', $pupilIds));
        $out = [];
        foreach (db()->all("SELECT pupil_id, kind FROM leave_requests WHERE status = 'approved' AND ? BETWEEN from_date AND to_date AND pupil_id IN ($in)", [$date]) as $r) {
            $out[(int) $r['pupil_id']] = self::KINDS[$r['kind']] ?? 'Leave';
        }
        return $out;
    }

    private function choosable(): array
    {
        if (Auth::role() === 'parent') {
            return array_values(array_filter(Accounts::childrenOf((int) Auth::id()), fn($c) => $c['status'] === 'active'));
        }
        if (!can('leave.approve')) {
            $this->forbidden();
        }
        $own = Auth::classScope() !== null;
        return db()->all(
            "SELECT p.id, p.first_name, p.last_name, c.name AS class_name FROM pupils p
             JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ? AND e.status = 'active' JOIN classes c ON c.id = e.class_id
             WHERE p.deleted_at IS NULL AND p.status = 'active'" . ($own ? ' AND c.class_teacher_id = ?' : '') . ' ORDER BY c.name, p.last_name, p.first_name',
            $own ? [current_year()['id'] ?? 0, Auth::id()] : [current_year()['id'] ?? 0]
        );
    }

    /** Leave rows the user may see: Head/coordinator all; class teachers their own class. */
    private function scopeSql(): array
    {
        if (Auth::classScope() === null) {
            return ['1=1', []];
        }
        return ["l.pupil_id IN (SELECT e2.pupil_id FROM enrolments e2 JOIN classes c2 ON c2.id = e2.class_id WHERE e2.academic_year_id = ? AND c2.class_teacher_id = ?)", [current_year()['id'] ?? 0, Auth::id()]];
    }

    private function canDecide(array $l): bool
    {
        if (!can('leave.approve') || (int) $l['requested_by'] === Auth::id() && Auth::role() === 'parent') {
            return false;
        }
        return Auth::classScope() === null || (int) $l['class_teacher_id'] === Auth::id();
    }

    private function find(int $id): array
    {
        $l = db()->one(
            "SELECT l.*, p.first_name, p.last_name, p.admission_no, c.name AS class_name, c.class_teacher_id, r.name AS requested_name, d.name AS decided_name
             FROM leave_requests l JOIN pupils p ON p.id = l.pupil_id
             LEFT JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ? AND e.status = 'active' LEFT JOIN classes c ON c.id = e.class_id
             LEFT JOIN users r ON r.id = l.requested_by LEFT JOIN users d ON d.id = l.decided_by WHERE l.id = ?",
            [current_year()['id'] ?? 0, $id]
        ) ?? $this->notFound();
        $ok = Auth::role() === 'parent'
            ? in_array((int) $l['pupil_id'], array_map('intval', array_column(Accounts::childrenOf((int) Auth::id()), 'id')), true)
            : can('leave.approve') && (Auth::classScope() === null || (int) $l['class_teacher_id'] === Auth::id());
        if (!$ok) {
            $this->notFound();
        }
        return $l;
    }
}
