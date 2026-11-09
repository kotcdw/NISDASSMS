<?php
declare(strict_types=1);

/** Notice board and circulars (with optional "I have read this" acknowledgement and SMS alert). */
class NoticeController extends Controller
{
    public function index(): void
    {
        [$w, $p] = Notices::visibility();
        $cat = (string) ($_GET['category'] ?? '');
        $sql = "SELECT n.*, u.name AS author, r.read_at, r.acknowledged_at FROM notices n LEFT JOIN users u ON u.id = n.created_by
                LEFT JOIN notice_reads r ON r.notice_id = n.id AND r.user_id = ? WHERE $w";
        $params = array_merge([Auth::id()], $p);
        $manage = can('notices.manage');
        if (!$manage || empty($_GET['all'])) {
            $sql .= ' AND n.publish_at <= NOW() AND (n.expires_on IS NULL OR n.expires_on >= CURDATE())';
        }
        if (isset(Notices::CATEGORIES[$cat])) {
            $sql .= ' AND n.category = ?';
            $params[] = $cat;
        }
        [$rows, $pager] = paginate($sql . ' ORDER BY n.is_pinned DESC, n.publish_at DESC', $params, 20);
        $this->view('notices/index', ['title' => 'Notice board', 'rows' => $rows, 'pager' => $pager, 'manage' => $manage]);
    }

    public function show(int $id): void
    {
        $n = $this->visible($id);
        db()->run('INSERT IGNORE INTO notice_reads (notice_id, user_id) VALUES (?, ?)', [$id, Auth::id()]);
        $mine = db()->one('SELECT * FROM notice_reads WHERE notice_id = ? AND user_id = ?', [$id, Auth::id()]);
        $stats = null;
        if (can('notices.manage')) {
            $stats = [
                'reads' => (int) db()->value('SELECT COUNT(*) FROM notice_reads WHERE notice_id = ?', [$id]),
                'acks' => (int) db()->value('SELECT COUNT(*) FROM notice_reads WHERE notice_id = ? AND acknowledged_at IS NOT NULL', [$id]),
                'audience' => Notices::audienceUserCount($n['audience']),
                'list' => db()->all('SELECT r.*, u.name, u.role FROM notice_reads r JOIN users u ON u.id = r.user_id WHERE r.notice_id = ? ORDER BY r.acknowledged_at IS NULL, u.name LIMIT 500', [$id]),
            ];
        }
        $this->view('notices/show', ['title' => $n['title'], 'n' => $n, 'mine' => $mine, 'stats' => $stats]);
    }

    public function acknowledge(int $id): void
    {
        $n = $this->visible($id);
        db()->run(
            'INSERT INTO notice_reads (notice_id, user_id, acknowledged_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE acknowledged_at = COALESCE(acknowledged_at, NOW())',
            [$id, Auth::id()]
        );
        flash('success', 'Thank you — the school can see you have read "' . $n['title'] . '".');
        redirect('notices/' . $id);
    }

    public function file(int $id): void
    {
        $n = $this->visible($id);
        if (!$n['attachment']) {
            $this->notFound();
        }
        Upload::send($n['attachment'], $n['attachment_mime'], $n['attachment_name']);
    }

    /** Attachment of a notice shown on the sign-in page: anyone may download it (no sign-in needed). */
    public function publicFile(int $id): void
    {
        $n = Notices::loginNotice($id);
        if (!$n || !$n['attachment']) {
            $this->notFound();
        }
        Upload::send($n['attachment'], $n['attachment_mime'], $n['attachment_name']);
    }

    public function create(): void
    {
        $this->view('notices/form', ['title' => 'New notice', 'n' => null]);
    }

    public function store(): void
    {
        $data = $this->input('notices/create');
        $id = db()->insert('notices', $data + ['created_by' => Auth::id()]);
        audit('notice_created', 'notices', $id, ['audience' => $data['audience']]);
        $this->afterSave($id);
        flash('success', 'Notice published' . ($data['publish_at'] > date('Y-m-d H:i:s') ? ' (it will appear on ' . date('d M H:i', strtotime($data['publish_at'])) . ')' : '') . '.');
        redirect('notices/' . $id);
    }

    public function edit(int $id): void
    {
        $n = db()->one('SELECT * FROM notices WHERE id = ?', [$id]) ?? $this->notFound();
        $this->view('notices/form', ['title' => 'Edit notice', 'n' => $n]);
    }

    public function update(int $id): void
    {
        db()->one('SELECT id FROM notices WHERE id = ?', [$id]) ?? $this->notFound();
        $data = $this->input('notices/' . $id . '/edit');
        db()->update('notices', $data, 'id = ?', [$id]);
        audit('notice_updated', 'notices', $id);
        $this->afterSave($id);
        flash('success', 'Notice updated.');
        redirect('notices/' . $id);
    }

    public function destroy(int $id): void
    {
        $n = db()->one('SELECT * FROM notices WHERE id = ?', [$id]) ?? $this->notFound();
        db()->run('DELETE FROM notices WHERE id = ?', [$id]);
        audit('notice_deleted', 'notices', $id, ['title' => $n['title']]);
        flash('success', 'Notice deleted.');
        redirect('notices');
    }

    // ---------------------------------------------------------------- helpers

    private function afterSave(int $id): void
    {
        $n = db()->one('SELECT * FROM notices WHERE id = ?', [$id]);
        if (self::hasExtras() && $n['category'] === 'circular' && !$n['circular_no']) {
            // Formal numbering for circulars: NIS/CIR/2026/001
            $no = db()->transaction(function (Database $d) {
                $name = 'circular_' . date('Y');
                $d->run('INSERT IGNORE INTO number_sequences (name, next_value) VALUES (?, 1)', [$name]);
                $k = (int) $d->value('SELECT next_value FROM number_sequences WHERE name = ? FOR UPDATE', [$name]);
                $d->run('UPDATE number_sequences SET next_value = next_value + 1 WHERE name = ?', [$name]);
                return setting('circular_prefix', 'NIS/CIR') . '/' . date('Y') . '/' . str_pad((string) $k, 3, '0', STR_PAD_LEFT);
            });
            db()->update('notices', ['circular_no' => $no], 'id = ?', [$id]);
            $n['circular_no'] = $no;
        }
        [$file, $err] = Upload::store($_FILES['attachment'] ?? ['error' => UPLOAD_ERR_NO_FILE], 'notices');
        if ($err) {
            flash('warning', 'Attachment not saved: ' . $err);
        } elseif ($file) {
            db()->update('notices', ['attachment' => $file['stored_name'], 'attachment_name' => $file['original_name'], 'attachment_mime' => $file['mime']], 'id = ?', [$id]);
        } elseif (!empty($_POST['remove_attachment'])) {
            db()->update('notices', ['attachment' => null, 'attachment_name' => null, 'attachment_mime' => null], 'id = ?', [$id]);
        }
        // In-app alert to staff for staff notices; SMS alert (once) to the audience if asked
        if (!empty($_POST['send_sms']) && !$n['sms_sent_at']) {
            $phones = Notices::phones($n['audience']);
            $q = 0;
            // One message per family; sms_sent_at stops it being sent twice
            $body = Notifier::render('notice_alert', ['title' => mb_strimwidth($n['title'], 0, 70, '…'), 'link' => absolute_url('notices/' . $id)]);
            // A notice set to appear later: the SMS waits until then (before that the link would not open)
            $later = $n['publish_at'] > date('Y-m-d H:i:s');
            $when = $later ? gmdate('Y-m-d H:i:s', strtotime($n['publish_at'])) : gmdate('Y-m-d H:i:s');
            foreach ($phones as $phone => $name) {
                $q += $body !== null && Notifier::sms((string) $phone, $body, [
                    'name' => $name, 'event' => 'notice_alert', 'entity' => 'notices', 'entity_id' => $id, 'urgent' => $n['category'] === 'urgent',
                    'scheduled_at' => $when,
                ]) ? 1 : 0;
            }
            db()->update('notices', ['sms_sent_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
            if (!$later) {
                Notifier::process(30);
            }
            flash('info', "$q SMS alert(s) queued" . ($later ? ' — they go out on ' . date('d M H:i', strtotime($n['publish_at'])) . ' when the notice appears' : '') . '.');
        }
        Notices::alertStaff($n);
    }

    private function input(string $back): array
    {
        $in = $this->only(['title', 'body', 'category', 'audience', 'publish_at', 'expires_on']);
        $publish = $in['publish_at'] ? str_replace('T', ' ', (string) $in['publish_at']) : date('Y-m-d H:i:s');
        $err = null;
        if (!$in['title'] || !$in['body']) {
            $err = 'Give the notice a title and some text.';
        } elseif (!isset(Notices::CATEGORIES[(string) $in['category']])) {
            $err = 'Choose the kind of notice.';
        } elseif (!array_key_exists((string) $in['audience'], Notices::audiences())) {
            $err = 'Choose who should see it.';
        } elseif (!strtotime($publish)) {
            $err = 'Enter a valid publish date.';
        } elseif ($in['expires_on'] && (!Validator::isDate($in['expires_on']) || $in['expires_on'] < substr($publish, 0, 10))) {
            $err = 'The "remove after" date must be after the publish date.';
        }
        if ($err) {
            with_old($_POST);
            flash('error', $err);
            redirect($back);
        }
        return [
            'title' => mb_substr($in['title'], 0, 150), 'body' => mb_substr($in['body'], 0, 20000), 'category' => $in['category'],
            'audience' => $in['audience'], 'publish_at' => date('Y-m-d H:i:s', strtotime($publish)), 'expires_on' => $in['expires_on'],
            'requires_ack' => isset($_POST['requires_ack']) ? 1 : 0, 'is_pinned' => isset($_POST['is_pinned']) ? 1 : 0,
        ] + (Notices::loginReady() ? ['on_login' => isset($_POST['on_login']) ? 1 : 0] : [])
          + (self::hasExtras() ? ['priority' => in_array($_POST['priority'] ?? '', ['important', 'urgent'], true) ? $_POST['priority'] : 'normal'] : []);
    }

    /** Circular numbers and priority (migration 019). */
    public static function hasExtras(): bool
    {
        static $h = null;
        return $h ??= (bool) db()->value("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'notices' AND column_name = 'circular_no'");
    }

    /** Read / confirmed list as CSV (who saw it and when). */
    public function readsCsv(int $id): void
    {
        $n = db()->one('SELECT * FROM notices WHERE id = ?', [$id]) ?? $this->notFound();
        $out = csv_start('notice-' . $id . '-reads.csv', [($n['circular_no'] ?? '') . ' ' . $n['title']]);
        csv_row($out, ['Name', 'Role', 'Opened', 'Confirmed']);
        foreach (db()->all('SELECT r.*, u.name, u.role FROM notice_reads r JOIN users u ON u.id = r.user_id WHERE r.notice_id = ? ORDER BY u.name', [$id]) as $r) {
            csv_row($out, [preg_match('/^[=+\-@]/', $r['name']) ? "'" . $r['name'] : $r['name'], Auth::roleLabel($r['role']), $r['read_at'], $r['acknowledged_at']]);
        }
        csv_end($out);
    }

    private function visible(int $id): array
    {
        [$w, $p] = Notices::visibility();
        $sql = "SELECT n.*, u.name AS author FROM notices n LEFT JOIN users u ON u.id = n.created_by WHERE n.id = ? AND $w";
        if (!can('notices.manage')) {
            $sql .= ' AND n.publish_at <= NOW()';
        }
        return db()->one($sql, array_merge([$id], $p)) ?? $this->notFound();
    }
}
