<?php
declare(strict_types=1);

/**
 * Live Classroom: teachers schedule an online class with a Zoom, Google Meet or Microsoft Teams link (made in that app);
 * pupils and parents see it, get a reminder and join through the school system, which keeps a join register.
 */
class LiveController extends Controller
{
    public const EARLY_MIN = 15; // the Join button opens this many minutes before the start

    public function __construct()
    {
        $this->needsTable('live_sessions');
        if ((Auth::role() === 'pupil' && !can('live.join')) || (Auth::role() === 'parent' && !can('portal.parent'))) {
            $this->forbidden();
        }
    }

    public function index(): void
    {
        $prov = isset(Learning::PROVIDERS[$_GET['provider'] ?? '']) ? (string) $_GET['provider'] : '';
        $pf = $prov ? ' AND l.provider = ' . db()->pdo()->quote($prov) : '';
        $base = 'SELECT l.*, c.name AS class_name, s.name AS subject_name, u.name AS teacher_name,
                        (SELECT COUNT(*) FROM live_attendance a WHERE a.session_id = l.id) AS joined
                 FROM live_sessions l JOIN classes c ON c.id = l.class_id LEFT JOIN class_subjects cs ON cs.id = l.class_subject_id
                 LEFT JOIN subjects s ON s.id = cs.subject_id LEFT JOIN users u ON u.id = l.created_by';
        if (in_array(Auth::role(), ['pupil', 'parent'], true)) {
            $viewer = Learning::viewerClasses();
            $ids = implode(',', array_map('intval', array_keys($viewer) ?: [0]));
            $rows = db()->all("$base WHERE l.class_id IN ($ids) AND l.starts_at >= NOW() - INTERVAL 14 DAY$pf ORDER BY l.starts_at >= NOW() - INTERVAL 3 HOUR DESC, ABS(TIMESTAMPDIFF(MINUTE, NOW(), l.starts_at)) LIMIT 60");
            $this->view('live/learn', ['title' => 'Live classes', 'rows' => $rows, 'prov' => $prov, 'viewer' => $viewer]);
            return;
        }
        if (!can('live.manage')) {
            $this->forbidden();
        }
        $classIds = implode(',', array_map('intval', array_values(array_unique(array_column(Learning::targets(), 'class_id'))) ?: [0]));
        $when = ($_GET['when'] ?? '') === 'past' ? 'past' : 'coming';
        $rows = db()->all("$base WHERE (l.class_id IN ($classIds) OR l.created_by = ?)$pf AND " . ($when === 'past' ? 'l.starts_at < NOW() - INTERVAL 3 HOUR ORDER BY l.starts_at DESC LIMIT 100' : 'l.starts_at >= NOW() - INTERVAL 3 HOUR ORDER BY l.starts_at LIMIT 100'), [Auth::id()]);
        $rows = array_values(array_filter($rows, fn($r) => Learning::canManage($r, 'live.manage')));
        $this->view('live/index', ['title' => 'Live classes', 'rows' => $rows, 'prov' => $prov, 'when' => $when]);
    }

    public function create(): void
    {
        $prov = isset(Learning::PROVIDERS[$_GET['provider'] ?? '']) ? (string) $_GET['provider'] : '';
        $this->view('live/form', ['title' => 'Schedule a live class', 's' => null, 'targets' => Learning::targets(), 'prov' => $prov]);
    }

    public function store(): void
    {
        $data = $this->input('live/create');
        $id = db()->insert('live_sessions', $data + ['created_by' => Auth::id()]);
        audit('live_class_scheduled', 'live_sessions', $id, ['class' => $data['class_id'], 'provider' => $data['provider'], 'at' => $data['starts_at']]);
        $sms = $this->tell($id, $data, !empty($_POST['sms']), 'New live class');
        flash('success', 'Live class scheduled. Students and parents can see it' . ($sms ? " and $sms parent(s) were sent an SMS" : '') . '.');
        redirect('live/' . $id);
    }

    public function show(int $id): void
    {
        $s = $this->find($id);
        $manage = Learning::canManage($s, 'live.manage');
        if (!$manage && !$this->canSee($s)) {
            $this->notFound();
        }
        $register = [];
        if ($manage) {
            $att = [];
            foreach (db()->all('SELECT a.*, u.name AS by_name, u.role AS by_role FROM live_attendance a JOIN users u ON u.id = a.joined_by WHERE a.session_id = ?', [$id]) as $a) {
                $att[$a['pupil_id']] = $a;
            }
            foreach (Assignments::roster((int) $s['class_id']) as $p) {
                $register[] = $p + ['att' => $att[$p['id']] ?? null];
            }
        }
        $this->view('live/show', ['title' => $s['title'], 's' => $s, 'manage' => $manage, 'register' => $register, 'open' => $this->isOpen($s)]);
    }

    public function edit(int $id): void
    {
        $s = $this->managed($id);
        $this->view('live/form', ['title' => 'Change live class', 's' => $s, 'targets' => Learning::targets(), 'prov' => $s['provider']]);
    }

    public function update(int $id): void
    {
        $s = $this->managed($id);
        if ($s['status'] === 'cancelled') {
            flash('error', 'This class was cancelled. Schedule a new one instead.');
            redirect('live/' . $id);
        }
        $data = $this->input('live/' . $id . '/edit', $id);
        $moved = $data['starts_at'] !== $s['starts_at'] || $data['join_url'] !== $s['join_url'];
        db()->update('live_sessions', $data + ($moved ? ['reminded_at' => null] : []), 'id = ?', [$id]);
        audit('live_class_changed', 'live_sessions', $id);
        if ($moved) {
            $this->tell($id, $data, !empty($_POST['sms']), 'Live class changed');
        }
        flash('success', 'Saved.' . ($moved ? ' Students and parents were told about the change.' : ''));
        redirect('live/' . $id);
    }

    public function cancel(int $id): void
    {
        $s = $this->managed($id);
        if ($s['status'] !== 'cancelled') {
            db()->update('live_sessions', ['status' => 'cancelled'], 'id = ?', [$id]);
            audit('live_class_cancelled', 'live_sessions', $id);
            if ($users = Learning::classUsers((int) $s['class_id'])) {
                Notifier::inapp($users, 'Live class cancelled: ' . $s['title'], 'It was planned for ' . fmt_date($s['starts_at'], 'D j M, H:i') . '.', url('live/' . $id));
            }
        }
        flash('success', 'Live class cancelled; students and parents were told.');
        redirect('live/' . $id);
    }

    /** Pupils and parents join through here: the join is recorded, then they go to Zoom / Meet / Teams. */
    public function join(int $id): void
    {
        $s = $this->find($id);
        $manage = Learning::canManage($s, 'live.manage');
        if (!$manage && !$this->canSee($s)) {
            $this->notFound();
        }
        if ($s['status'] === 'cancelled') {
            flash('error', 'This class was cancelled.');
            redirect('live/' . $id);
        }
        if (!$manage) {
            if (!$this->isOpen($s)) {
                flash('warning', strtotime($s['starts_at']) > time() ? 'The class has not started yet. Join opens ' . self::EARLY_MIN . ' minutes before ' . fmt_date($s['starts_at'], 'H:i') . '.' : 'This class has ended.');
                redirect('live/' . $id);
            }
            foreach (Learning::viewerClasses()[(int) $s['class_id']] ?? [] as $p) {
                db()->run('INSERT INTO live_attendance (session_id, pupil_id, joined_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE times = times + 1', [$id, $p['id'], Auth::id()]);
            }
        }
        header('Cache-Control: no-store');
        header('Location: ' . $s['join_url'], true, 302);
        exit;
    }

    /** Scheduler: remind pupils and parents 30 minutes before a class (once). */
    public static function reminders(): int
    {
        try {
            $rows = db()->all("SELECT * FROM live_sessions WHERE status = 'scheduled' AND reminded_at IS NULL AND starts_at BETWEEN NOW() AND NOW() + INTERVAL 30 MINUTE");
        } catch (PDOException $e) {
            return 0;
        }
        foreach ($rows as $s) {
            db()->update('live_sessions', ['reminded_at' => date('Y-m-d H:i:s')], 'id = ?', [$s['id']]);
            if ($users = Learning::classUsers((int) $s['class_id'])) {
                Notifier::inapp($users, 'Live class at ' . date('H:i', strtotime($s['starts_at'])) . ': ' . $s['title'], 'On ' . Learning::PROVIDERS[$s['provider']] . '. Open the school app and press Join.', url('live/' . $s['id']));
            }
        }
        return count($rows);
    }

    // ---------------------------------------------------------------- helpers

    private function input(string $back, int $id = 0): array
    {
        $t = Learning::target((string) ($_POST['target'] ?? ''));
        $title = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 150);
        $url = trim((string) ($_POST['join_url'] ?? ''));
        $prov = (string) ($_POST['provider'] ?? '');
        $date = (string) ($_POST['date'] ?? '');
        $time = substr((string) ($_POST['time'] ?? ''), 0, 5);
        $dur = (int) ($_POST['duration_min'] ?? 40);
        $found = strlen($url) <= 500 && filter_var($url, FILTER_VALIDATE_URL) ? Learning::providerOf($url) : null;
        $start = Validator::isDate($date) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) ? "$date $time:00" : null;
        $err = null;
        if (!$t) {
            $err = 'Choose the class (and subject).';
        } elseif ($title === '') {
            $err = 'Give the class a title.';
        } elseif (!$found) {
            $err = 'Paste the meeting link from Zoom, Google Meet or Teams (it starts with https://).';
        } elseif ($prov !== '' && $prov !== 'other' && isset(Learning::PROVIDERS[$prov]) && $found !== $prov) {
            $err = 'That link is not a ' . Learning::PROVIDERS[$prov] . ' link. Check you pasted the right one.';
        } elseif (!$start) {
            $err = 'Enter the date and start time.';
        } elseif (strtotime($start) < time() - 300 || strtotime($start) > strtotime('+120 days')) {
            $err = 'The start must be from now up to four months ahead.';
        } elseif ($dur < 10 || $dur > 240) {
            $err = 'A live class lasts from 10 to 240 minutes.';
        }
        if (!$err) {
            $end = date('Y-m-d H:i:s', strtotime($start) + $dur * 60);
            $c = db()->one("SELECT title, starts_at FROM live_sessions WHERE class_id = ? AND status = 'scheduled' AND id <> ? AND starts_at < ? AND starts_at + INTERVAL duration_min MINUTE > ?", [$t['class_id'], $id, $end, $start]);
            if ($c) {
                $err = 'This class already has a live class then: "' . $c['title'] . '" at ' . date('H:i', strtotime($c['starts_at'])) . '.';
            }
        }
        if ($err) {
            with_old($_POST);
            flash('error', $err);
            redirect($back);
        }
        return ['class_id' => $t['class_id'], 'class_subject_id' => $t['class_subject_id'], 'title' => $title, 'provider' => $prov === 'other' ? 'other' : $found, 'join_url' => $url,
            'meeting_code' => mb_substr(trim((string) ($_POST['meeting_code'] ?? '')), 0, 60) ?: null, 'passcode' => mb_substr(trim((string) ($_POST['passcode'] ?? '')), 0, 40) ?: null,
            'starts_at' => $start, 'duration_min' => $dur, 'notes' => mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 500) ?: null];
    }

    /** In-app notice to pupils and parents; SMS to parents if asked. Returns SMS count. */
    private function tell(int $id, array $s, bool $sms, string $what): int
    {
        $when = fmt_date($s['starts_at'], 'D j M') . ' at ' . date('H:i', strtotime($s['starts_at']));
        if ($users = Learning::classUsers((int) $s['class_id'])) {
            Notifier::inapp($users, "$what: " . $s['title'], Learning::PROVIDERS[$s['provider']] . ", $when", url('live/' . $id));
        }
        $n = 0;
        if ($sms) {
            $class = (string) db()->value('SELECT name FROM classes WHERE id = ?', [$s['class_id']]);
            // One message per parent (smsTemplate would send only once per class, so render it here)
            $body = Notifier::render('live_class', ['pupil_class' => $class, 'provider' => Learning::PROVIDERS[$s['provider']], 'title' => $s['title'], 'when' => $when, 'link' => absolute_url('live/' . $id)]);
            foreach ($body === null ? [] : Audience::phones('class:' . $s['class_id']) as $phone => $name) {
                if (Notifier::sms($phone, $body, ['name' => $name, 'event' => 'live_class', 'entity' => 'live_sessions', 'entity_id' => $id])) {
                    $n++;
                }
            }
        }
        return $n;
    }

    private function isOpen(array $s): bool
    {
        $start = strtotime($s['starts_at']);
        return $s['status'] === 'scheduled' && time() >= $start - self::EARLY_MIN * 60 && time() <= $start + ((int) $s['duration_min'] + 15) * 60;
    }

    private function find(int $id): array
    {
        return db()->one(
            'SELECT l.*, c.name AS class_name, s.name AS subject_name, u.name AS teacher_name FROM live_sessions l JOIN classes c ON c.id = l.class_id
             LEFT JOIN class_subjects cs ON cs.id = l.class_subject_id LEFT JOIN subjects s ON s.id = cs.subject_id LEFT JOIN users u ON u.id = l.created_by WHERE l.id = ?',
            [$id]
        ) ?? $this->notFound();
    }

    private function managed(int $id): array
    {
        $s = $this->find($id);
        if (!Learning::canManage($s, 'live.manage')) {
            $this->forbidden();
        }
        return $s;
    }

    private function canSee(array $s): bool
    {
        if (in_array(Auth::role(), ['pupil', 'parent'], true)) {
            return isset(Learning::viewerClasses()[(int) $s['class_id']]);
        }
        return can('live.manage') && Auth::classScope() === null;
    }
}
