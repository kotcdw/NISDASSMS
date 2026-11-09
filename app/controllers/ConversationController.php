<?php
declare(strict_types=1);

/** Parent–teacher messages. Members only; the Head Teacher can read every conversation (safeguarding). */
class ConversationController extends Controller
{
    public function index(): void
    {
        $all = can('chat.oversee') && !empty($_GET['all']);
        $sql = "SELECT cv.*, p.first_name, p.last_name, me.last_read_at,
                    (SELECT body FROM conversation_messages m WHERE m.conversation_id = cv.id ORDER BY m.id DESC LIMIT 1) AS last_body,
                    (SELECT GROUP_CONCAT(u.name ORDER BY u.name SEPARATOR ', ') FROM conversation_members cm JOIN users u ON u.id = cm.user_id
                      WHERE cm.conversation_id = cv.id AND cm.user_id <> ?) AS others
                FROM conversations cv LEFT JOIN pupils p ON p.id = cv.pupil_id ";
        $params = [Auth::id()];
        if ($all) {
            $sql .= 'LEFT JOIN conversation_members me ON me.conversation_id = cv.id AND me.user_id = ? ';
        } else {
            $sql .= 'JOIN conversation_members me ON me.conversation_id = cv.id AND me.user_id = ? ';
        }
        $params[] = Auth::id();
        [$rows, $pager] = paginate($sql . 'ORDER BY cv.last_message_at DESC', $params, 30);
        $this->view('conversations/index', ['title' => can('staff') ? 'Parent messages' : 'Messages', 'rows' => $rows, 'pager' => $pager, 'all' => $all]);
    }

    public function create(): void
    {
        $pupilId = (int) ($_GET['pupil'] ?? 0);
        $data = ['title' => 'New message', 'pupilId' => $pupilId];
        if (can('staff')) {
            $pupil = $pupilId ? $this->pupilForStaff($pupilId) : null;
            $data['pupil'] = $pupil;
            $data['parents'] = $pupil ? $this->parentUsers($pupilId) : [];
            $data['studentUser'] = $pupil && !empty($pupil['user_id'])
                ? db()->one("SELECT id, name FROM users WHERE id = ? AND role = 'pupil' AND is_active = 1", [$pupil['user_id']])
                : null;
            $q = trim((string) ($_GET['q'] ?? ''));
            $data['q'] = $q;
            $data['found'] = $q !== '' ? db()->all(
                "SELECT id, first_name, last_name, admission_no FROM pupils WHERE deleted_at IS NULL AND status = 'active'
                 AND (admission_no LIKE ? OR CONCAT_WS(' ', first_name, last_name) LIKE ?) ORDER BY last_name LIMIT 20",
                ["%$q%", "%$q%"]
            ) : [];
        } elseif (Auth::role() === 'pupil') {
            $child = Accounts::pupilOf((int) Auth::id());
            if (!$child) {
                $this->notFound();
            }
            $data['child'] = $child;
            $data['staff'] = $this->teachersFor($child);
            $data['student'] = true;
        } else {
            $children = array_values(array_filter(Accounts::childrenOf((int) Auth::id()), fn($c) => $c['status'] === 'active'));
            $data['children'] = $children;
            $child = null;
            foreach ($children as $c) {
                if ((int) $c['id'] === $pupilId) {
                    $child = $c;
                }
            }
            $child = $child ?: ($children[0] ?? null);
            $data['child'] = $child;
            $data['staff'] = $child ? $this->staffFor($child) : [];
            $data['studentUser'] = $child ? $this->studentUserForParent((int) $child['id']) : null;
        }
        $this->view('conversations/create', $data);
    }

    public function store(): void
    {
        $pupilId = (int) ($_POST['pupil_id'] ?? 0);
        $subject = mb_substr(trim((string) ($_POST['subject'] ?? '')), 0, 150);
        $body = trim((string) ($_POST['body'] ?? ''));
        if ($subject === '' || $body === '' || mb_strlen($body) > 5000) {
            flash('error', 'Write a subject and a message (up to 5,000 characters).');
            redirect('conversations/create', ['pupil' => $pupilId ?: null]);
        }
        if (can('staff')) {
            $pupil = $this->pupilForStaff($pupilId);
            $recipients = array_column($this->parentUsers($pupilId), 'id');
            if (!empty($_POST['include_student'])) {
                $studentUser = !empty($pupil['user_id'])
                    ? db()->one("SELECT id FROM users WHERE id = ? AND role = 'pupil' AND is_active = 1", [$pupil['user_id']])
                    : null;
                if (!$studentUser) {
                    flash('error', 'The student login is no longer active. Refresh the form or send to the parents only.');
                    redirect('conversations/create', ['pupil' => $pupilId]);
                }
                $recipients[] = (int) $studentUser['id'];
            }
            if (!$recipients) {
                flash('error', 'This student\'s parents do not have a portal login yet. Create one under "Parent & student logins", or send an SMS from Notifications & SMS.');
                redirect('conversations/create', ['pupil' => $pupilId]);
            }
        } elseif (Auth::role() === 'pupil') {
            $child = Accounts::pupilOf((int) Auth::id());
            $to = (int) ($_POST['to'] ?? 0);
            $allowed = $child && (int) $child['id'] === $pupilId
                ? array_map('intval', array_column($this->teachersFor($child), 'id')) : [];
            if (!$child || (int) $child['id'] !== $pupilId || !in_array($to, $allowed, true)) {
                flash('error', 'Choose one of your teachers.');
                redirect('conversations/create');
            }
            $recipients = [$to];
        } else {
            $child = null;
            foreach (Accounts::childrenOf((int) Auth::id()) as $c) {
                if ((int) $c['id'] === $pupilId && $c['status'] === 'active') {
                    $child = $c;
                }
            }
            $to = (int) ($_POST['to'] ?? 0);
            $allowed = $child ? array_map('intval', array_column($this->staffFor($child), 'id')) : [];
            $studentUser = $child ? $this->studentUserForParent($pupilId) : null;
            if (!$child || (!in_array($to, $allowed, true) && (!$studentUser || (int) $studentUser['id'] !== $to))) {
                flash('error', 'Choose your child and who you are writing to.');
                redirect('conversations/create');
            }
            $recipients = [$to];
        }
        $id = db()->transaction(function (Database $d) use ($pupilId, $subject, $body, $recipients) {
            $id = $d->insert('conversations', ['subject' => $subject, 'pupil_id' => $pupilId ?: null, 'created_by' => Auth::id()]);
            $d->insert('conversation_members', ['conversation_id' => $id, 'user_id' => Auth::id(), 'last_read_at' => date('Y-m-d H:i:s')]);
            foreach (array_unique($recipients) as $uid) {
                if ((int) $uid !== Auth::id()) {
                    $d->insert('conversation_members', ['conversation_id' => $id, 'user_id' => (int) $uid]);
                }
            }
            $d->insert('conversation_messages', ['conversation_id' => $id, 'user_id' => Auth::id(), 'body' => mb_substr($body, 0, 5000)]);
            return $id;
        });
        audit('conversation_started', 'conversations', $id);
        $this->notifyOthers($id, $body);
        flash('success', 'Message sent.');
        redirect('conversations/' . $id);
    }

    public function show(int $id): void
    {
        [$c, $member] = $this->find($id);
        if ($member) {
            db()->update('conversation_members', ['last_read_at' => date('Y-m-d H:i:s')], 'conversation_id = ? AND user_id = ?', [$id, Auth::id()]);
        } else {
            audit('conversation_viewed', 'conversations', $id); // Head Teacher oversight
        }
        $this->view('conversations/show', [
            'title' => $c['subject'], 'c' => $c, 'member' => $member,
            'messages' => db()->all('SELECT m.*, u.name, u.role FROM conversation_messages m JOIN users u ON u.id = m.user_id WHERE m.conversation_id = ? ORDER BY m.id', [$id]),
            'members' => db()->all('SELECT u.id, u.name, u.role FROM conversation_members cm JOIN users u ON u.id = cm.user_id WHERE cm.conversation_id = ? ORDER BY u.name', [$id]),
        ]);
    }

    public function reply(int $id): void
    {
        [$c, $member] = $this->find($id);
        $body = trim((string) ($_POST['body'] ?? ''));
        if ($body === '' || mb_strlen($body) > 5000) {
            flash('error', 'Write a message (up to 5,000 characters).');
            redirect('conversations/' . $id);
        }
        if ((int) $c['is_closed']) {
            flash('error', 'This conversation is closed.');
            redirect('conversations/' . $id);
        }
        db()->transaction(function (Database $d) use ($id, $body, $member) {
            if (!$member) { // Head Teacher joins the conversation
                $d->insert('conversation_members', ['conversation_id' => $id, 'user_id' => Auth::id()]);
            }
            $d->insert('conversation_messages', ['conversation_id' => $id, 'user_id' => Auth::id(), 'body' => mb_substr($body, 0, 5000)]);
            $d->update('conversations', ['last_message_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
            $d->update('conversation_members', ['last_read_at' => date('Y-m-d H:i:s')], 'conversation_id = ? AND user_id = ?', [$id, Auth::id()]);
        });
        $this->notifyOthers($id, $body);
        redirect('conversations/' . $id);
    }

    public function close(int $id): void
    {
        [$c, $member] = $this->find($id);
        if (!can('staff')) {
            $this->notFound();
        }
        db()->update('conversations', ['is_closed' => (int) $c['is_closed'] ? 0 : 1], 'id = ?', [$id]);
        audit((int) $c['is_closed'] ? 'conversation_reopened' : 'conversation_closed', 'conversations', $id);
        redirect('conversations/' . $id);
    }

    // ---------------------------------------------------------------- helpers

    /** In-app alert to the other members; an SMS nudge to parents when staff write (at most hourly). */
    private function notifyOthers(int $id, string $body): void
    {
        $c = db()->one('SELECT * FROM conversations WHERE id = ?', [$id]);
        $me = auth();
        $preview = mb_strimwidth(preg_replace('/\s+/', ' ', $body), 0, 60, '…');
        foreach (db()->all('SELECT u.* FROM conversation_members cm JOIN users u ON u.id = cm.user_id WHERE cm.conversation_id = ? AND cm.user_id <> ? AND u.is_active = 1', [$id, Auth::id()]) as $u) {
            Notifier::inapp([(int) $u['id']], 'New message from ' . $me['name'], $c['subject'] . ': ' . $preview, 'conversations/' . $id);
            if ($u['role'] === 'parent' && can('staff') && $u['phone']
                && !db()->value("SELECT id FROM notification_outbox WHERE event_key = 'new_message' AND recipient = ? AND created_at > (UTC_TIMESTAMP() - INTERVAL 1 HOUR)", [Admission::normalisePhone($u['phone'])])) {
                Notifier::smsTemplate('new_message', $u['phone'], [
                    'name' => $u['name'], 'sender' => $me['name'], 'preview' => mb_strimwidth($preview, 0, 40, '…'), 'link' => absolute_url('conversations/' . $id),
                ], ['name' => $u['name'], 'entity' => 'conv_message', 'entity_id' => random_int(1, 2147483647)]);
            }
        }
        Notifier::process(10);
    }

    /** @return array{0: array, 1: bool} conversation, whether the user is a member */
    private function find(int $id): array
    {
        $c = db()->one(
            'SELECT cv.*, p.first_name, p.last_name, p.admission_no FROM conversations cv LEFT JOIN pupils p ON p.id = cv.pupil_id WHERE cv.id = ?',
            [$id]
        );
        $member = $c && db()->value('SELECT 1 FROM conversation_members WHERE conversation_id = ? AND user_id = ?', [$id, Auth::id()]);
        if (!$c || (!$member && !can('chat.oversee'))) {
            $this->notFound();
        }
        return [$c, (bool) $member];
    }

    private function pupilForStaff(int $pupilId): array
    {
        if (!can('pupils.view') && !can('fees.view')) {
            $this->notFound();
        }
        $pupil = db()->one("SELECT * FROM pupils WHERE id = ? AND deleted_at IS NULL", [$pupilId]) ?? $this->notFound();
        if (!Auth::pupilInScope($pupilId)) {
            $this->notFound();
        }
        return $pupil;
    }

    private function parentUsers(int $pupilId): array
    {
        return db()->all(
            "SELECT DISTINCT u.id, u.name, u.phone FROM pupil_guardian pg JOIN guardians g ON g.id = pg.guardian_id JOIN users u ON u.id = g.user_id
             WHERE pg.pupil_id = ? AND u.is_active = 1 AND u.role = 'parent'",
            [$pupilId]
        );
    }

    /** A parent may message only the active pupil account linked to their own child. */
    private function studentUserForParent(int $pupilId): ?array
    {
        return db()->one(
            "SELECT u.id, u.name FROM pupil_guardian pg
             JOIN guardians g ON g.id = pg.guardian_id
             JOIN pupils p ON p.id = pg.pupil_id
             JOIN users u ON u.id = p.user_id
             WHERE pg.pupil_id = ? AND g.user_id = ? AND p.deleted_at IS NULL
               AND p.status = 'active' AND u.role = 'pupil' AND u.is_active = 1",
            [$pupilId, Auth::id()]
        );
    }

    /** Staff a parent may write to about a child: class teacher, the child's subject teachers, Head Teacher, Bursar. */
    private function staffFor(array $child): array
    {
        $out = [];
        if (!empty($child['class_teacher_id'])) {
            $classTeacher = db()->one('SELECT id, name FROM users WHERE id = ? AND is_active = 1', [(int) $child['class_teacher_id']]);
            if ($classTeacher) {
                $out[(int) $classTeacher['id']] = ['id' => (int) $classTeacher['id'], 'name' => $classTeacher['name'], 'label' => 'Class teacher'];
            }
        }
        if (!empty($child['class_id'])) {
            foreach (db()->all(
                "SELECT DISTINCT u.id, u.name, GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ', ') AS subjects FROM class_subjects cs
                 JOIN users u ON u.id = cs.teacher_id JOIN subjects s ON s.id = cs.subject_id
                 WHERE cs.class_id = ? AND u.is_active = 1 GROUP BY u.id, u.name",
                [$child['class_id']]
            ) as $t) {
                $out[(int) $t['id']] = $out[(int) $t['id']] ?? ['id' => (int) $t['id'], 'name' => $t['name'], 'label' => $t['subjects']];
            }
        }
        foreach (db()->all("SELECT id, name, role FROM users WHERE is_active = 1 AND role IN ('administrator','head_teacher','bursar') ORDER BY role DESC, name") as $u) {
            $out[(int) $u['id']] = $out[(int) $u['id']] ?? ['id' => (int) $u['id'], 'name' => $u['name'], 'label' => Auth::roleLabel($u['role'])];
        }
        return array_values($out);
    }

    /** Teachers assigned to the student's current class, without unrelated school staff. */
    private function teachersFor(array $student): array
    {
        if (empty($student['class_id'])) {
            return [];
        }
        $out = [];
        if (!empty($student['class_teacher_id'])) {
            $classTeacher = db()->one('SELECT id, name FROM users WHERE id = ? AND is_active = 1', [(int) $student['class_teacher_id']]);
            if ($classTeacher) {
                $out[(int) $classTeacher['id']] = [
                    'id' => (int) $classTeacher['id'], 'name' => $classTeacher['name'], 'label' => 'Class teacher',
                ];
            }
        }
        foreach (db()->all(
            "SELECT u.id, u.name, GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') AS subjects
             FROM class_subjects cs JOIN users u ON u.id = cs.teacher_id JOIN subjects s ON s.id = cs.subject_id
             WHERE cs.class_id = ? AND u.is_active = 1 GROUP BY u.id, u.name",
            [$student['class_id']]
        ) as $teacher) {
            $out[(int) $teacher['id']] = $out[(int) $teacher['id']] ?? [
                'id' => (int) $teacher['id'], 'name' => $teacher['name'], 'label' => $teacher['subjects'],
            ];
        }
        return array_values($out);
    }
}
