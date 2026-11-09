<?php
declare(strict_types=1);

/** Notifications & reminders: compose SMS, outbox, templates, gateway settings, in-app inbox. */
class MessageController extends Controller
{
    private const SETTING_KEYS = [
        'sms_provider', 'sms_sender_id', 'sms_api_key', 'sms_client_id', 'sms_client_secret', 'sms_mnotify_url',
        'sabbath_quiet_start', 'sabbath_quiet_end', 'sms_cost',
    ];
    private const SETTING_FLAGS = ['notify_absent', 'notify_late', 'notify_admission', 'sabbath_quiet'];

    // ---- Message history & compose -------------------------------------------------

    public function index(): void
    {
        [$batches, $pager] = paginate(
            "SELECT b.*, u.name AS author,
                (SELECT COUNT(*) FROM notification_outbox o WHERE o.batch_id = b.id AND o.status = 'sent') AS sent,
                (SELECT COUNT(*) FROM notification_outbox o WHERE o.batch_id = b.id AND o.status = 'queued') AS queued,
                (SELECT COUNT(*) FROM notification_outbox o WHERE o.batch_id = b.id AND o.status = 'failed') AS failed
             FROM message_batches b LEFT JOIN users u ON u.id = b.created_by ORDER BY b.id DESC",
            []
        );
        $stats = array_column(db()->all("SELECT status, COUNT(*) n FROM notification_outbox WHERE created_at > (UTC_TIMESTAMP() - INTERVAL 30 DAY) GROUP BY status"), 'n', 'status');
        $this->view('messages/index', ['title' => 'Messages', 'batches' => $batches, 'pager' => $pager, 'stats' => $stats]);
    }

    public function compose(): void
    {
        $this->view('messages/compose', [
            'title' => 'Send message', 'options' => Audience::options(), 'preview' => null,
        ]);
    }

    public function send(): void
    {
        $in = $this->only(['title', 'audience', 'custom', 'body', 'schedule_date', 'schedule_time']);
        $errors = Validator::check($in, [
            'title'         => 'required|max:150',
            'audience'      => 'required',
            'body'          => 'required|max:612',
            'schedule_date' => 'date',
        ]);
        if (!isset(Audience::options()[(string) $in['audience']])) {
            $errors['audience'] = 'Choose who should receive the message.';
        }
        $phones = $errors ? [] : Audience::phones((string) $in['audience'], (string) $in['custom']);
        if (!$errors && !$phones) {
            $errors['audience'] = 'No valid phone numbers found for this group.';
        }
        $scheduled = gmdate('Y-m-d H:i:s');
        if (!$errors && $in['schedule_date']) {
            $time = preg_match('/^\d{2}:\d{2}$/', (string) $in['schedule_time']) ? $in['schedule_time'] : '07:00';
            $ts = strtotime($in['schedule_date'] . ' ' . $time); // Africa/Accra = UTC+0
            if ($ts < time() - 60) {
                $errors['schedule_date'] = 'Schedule time is in the past.';
            } else {
                $scheduled = gmdate('Y-m-d H:i:s', $ts);
            }
        }
        if ($errors) {
            with_old($in, $errors);
            flash('error', reset($errors));
            redirect('messages/compose');
        }

        // Preview step: show recipient count and cost before queueing
        if (empty($_POST['confirm'])) {
            $segments = Notifier::segments((string) $in['body']);
            $this->view('messages/compose', [
                'title' => 'Confirm message', 'options' => Audience::options(),
                'preview' => [
                    'count' => count($phones), 'segments' => $segments,
                    'cost' => count($phones) * $segments * (float) setting('sms_cost', '0.035'),
                    'sample' => array_slice($phones, 0, 5, true), 'scheduled' => $in['schedule_date'] ? $scheduled : null,
                ],
                'in' => $in,
            ]);
            return;
        }

        $batchId = db()->transaction(function (Database $d) use ($in, $phones, $scheduled) {
            $batchId = $d->insert('message_batches', [
                'title' => $in['title'], 'audience' => $in['audience'], 'audience_label' => Audience::label((string) $in['audience']),
                'body' => $in['body'], 'recipients' => count($phones), 'created_by' => Auth::id(),
            ]);
            foreach ($phones as $phone => $name) {
                Notifier::sms($phone, (string) $in['body'], ['name' => $name, 'event' => 'broadcast', 'batch_id' => $batchId, 'scheduled_at' => $scheduled]);
            }
            return $batchId;
        });
        audit('message_sent', 'message_batches', $batchId, ['audience' => $in['audience'], 'recipients' => count($phones)]);
        $r = Notifier::process(100);
        $msg = count($phones) . ' messages queued.';
        if ($r['held']) {
            $msg .= ' Sending is paused for the Sabbath and resumes on Saturday evening.';
        } elseif ($in['schedule_date']) {
            $msg .= ' They will be sent at the scheduled time.';
        } else {
            $msg .= " {$r['sent']} sent now" . ($r['failed'] ? ", {$r['failed']} failed (will retry)" : '') . '.';
        }
        flash('success', $msg);
        redirect('messages/outbox', ['batch' => $batchId]);
    }

    // ---- Outbox ---------------------------------------------------------------------

    public function outbox(): void
    {
        $status = (string) ($_GET['status'] ?? '');
        $batch = (int) ($_GET['batch'] ?? 0);
        $q = trim((string) ($_GET['q'] ?? ''));
        $sql = 'SELECT * FROM notification_outbox WHERE 1=1';
        $p = [];
        if (in_array($status, ['queued', 'sent', 'failed', 'cancelled'], true)) {
            $sql .= ' AND status = ?';
            $p[] = $status;
        }
        if ($batch) {
            $sql .= ' AND batch_id = ?';
            $p[] = $batch;
        }
        if ($q !== '') {
            $sql .= ' AND (recipient LIKE ? OR recipient_name LIKE ? OR body LIKE ?)';
            array_push($p, "%$q%", "%$q%", "%$q%");
        }
        [$rows, $pager] = paginate($sql . ' ORDER BY id DESC', $p, 50);
        $this->view('messages/outbox', [
            'title' => 'Outbox', 'rows' => $rows, 'pager' => $pager, 'quiet' => Notifier::inQuietHours(),
            'queued' => (int) db()->value("SELECT COUNT(*) FROM notification_outbox WHERE status = 'queued'"),
        ]);
    }

    public function processNow(): void
    {
        $reminders = Scheduler::eventReminders();
        $r = Notifier::process(200);
        flash('success', "Sent {$r['sent']}, failed {$r['failed']}" . ($r['held'] ? ", {$r['held']} held for the Sabbath" : '') . ($reminders ? ", $reminders event reminders queued" : '') . '.');
        back('messages/outbox');
    }

    public function cancel(int $id): void
    {
        db()->run("UPDATE notification_outbox SET status = 'cancelled' WHERE id = ? AND status = 'queued'", [$id]);
        audit('message_cancelled', 'notification_outbox', $id);
        back('messages/outbox');
    }

    public function retry(int $id): void
    {
        db()->run("UPDATE notification_outbox SET status = 'queued', attempts = 0, scheduled_at = UTC_TIMESTAMP() WHERE id = ? AND status = 'failed'", [$id]);
        audit('message_retry', 'notification_outbox', $id);
        back('messages/outbox');
    }

    // ---- Templates & settings ------------------------------------------------------------

    public function templates(): void
    {
        $this->view('messages/templates', ['title' => 'SMS templates', 'templates' => db()->all('SELECT * FROM notification_templates ORDER BY name')]);
    }

    public function saveTemplate(): void
    {
        $key = (string) ($_POST['key'] ?? '');
        $tpl = db()->one('SELECT * FROM notification_templates WHERE `key` = ?', [$key]) ?? $this->notFound();
        $body = trim((string) ($_POST['sms_body'] ?? ''));
        if ($body === '' || mb_strlen($body) > 480) {
            flash('error', 'Message must be between 1 and 480 characters.');
            redirect('messages/templates');
        }
        db()->update('notification_templates', ['sms_body' => $body, 'is_active' => isset($_POST['is_active']) ? 1 : 0], '`key` = ?', [$key]);
        audit('template_updated', 'notification_templates', null, ['key' => $key]);
        flash('success', "Template “{$tpl['name']}” saved.");
        redirect('messages/templates');
    }

    public function settings(): void
    {
        $s = [];
        foreach (array_merge(self::SETTING_KEYS, self::SETTING_FLAGS, ['cron_last_run']) as $k) {
            $s[$k] = setting($k);
        }
        $this->view('messages/settings', ['title' => 'SMS settings', 's' => $s]);
    }

    public function saveSettings(): void
    {
        $in = $this->only(self::SETTING_KEYS);
        $errors = Validator::check($in, [
            'sms_provider'        => 'required|in:' . implode(',', array_keys(SmsGateway::PROVIDERS)),
            'sms_sender_id'       => 'required|max:11',
            'sabbath_quiet_start' => 'required',
            'sabbath_quiet_end'   => 'required',
        ]);
        foreach (['sabbath_quiet_start', 'sabbath_quiet_end'] as $t) {
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $in[$t])) {
                $errors[$t] = 'Use HH:MM.';
            }
        }
        if ($in['sms_mnotify_url'] && !preg_match('#^https://#', (string) $in['sms_mnotify_url'])) {
            $errors['sms_mnotify_url'] = 'Must start with https://';
        }
        if ($errors) {
            flash('error', reset($errors));
            redirect('messages/settings');
        }
        foreach ($in as $k => $v) {
            // Leave stored secrets unchanged when the field is left blank
            if (in_array($k, ['sms_api_key', 'sms_client_secret'], true) && ($v === null || $v === '')) {
                continue;
            }
            setting_set($k, (string) $v);
        }
        foreach (self::SETTING_FLAGS as $k) {
            setting_set($k, isset($_POST[$k]) ? '1' : '0');
        }
        audit('sms_settings_updated', 'settings', null, ['provider' => $in['sms_provider']]);
        flash('success', 'SMS settings saved.');
        redirect('messages/settings');
    }

    public function test(): void
    {
        $phone = (string) Admission::normalisePhone((string) ($_POST['phone'] ?? ''));
        if (!SmsGateway::validPhone($phone)) {
            flash('error', 'Enter a valid Ghana mobile number.');
            redirect('messages/settings');
        }
        $res = SmsGateway::send($phone, 'Test message from ' . setting('school_short_name', 'NISDAS') . ' SMS. It works!');
        audit('sms_test', 'settings', null, ['phone' => $phone, 'ok' => $res['ok']]);
        if ($res['ok']) {
            flash('success', $res['provider'] === 'log' ? 'Test mode: the message was recorded but not sent. Choose a provider to send real SMS.' : "Test SMS sent to $phone via {$res['provider']}.");
        } else {
            flash('error', 'Sending failed: ' . $res['error']);
        }
        redirect('messages/settings');
    }

    // ---- In-app inbox (every signed-in user) -------------------------------------------

    public function inbox(): void
    {
        [$rows, $pager] = paginate('SELECT * FROM inapp_notifications WHERE user_id = ? ORDER BY id DESC', [Auth::id()], 30);
        $this->view('messages/inbox', ['title' => 'Notifications', 'rows' => $rows, 'pager' => $pager]);
    }

    public function open(int $id): void
    {
        $n = db()->one('SELECT * FROM inapp_notifications WHERE id = ? AND user_id = ?', [$id, Auth::id()]) ?? $this->notFound();
        db()->run('UPDATE inapp_notifications SET read_at = UTC_TIMESTAMP() WHERE id = ? AND read_at IS NULL', [$id]);
        $link = (string) $n['link'];
        // Some alerts were saved with the full address (/NISDASSMS/demo/crm/5): keep only the page part
        $base = rtrim(url(''), '/') . '/';
        if ($base !== '/' && str_starts_with($link, $base)) {
            $link = substr($link, strlen($base));
        }
        $link = ltrim($link, '/');
        redirect(preg_match('#^[a-z0-9/_.?=&-]*$#i', $link) && $link !== '' ? $link : 'notifications');
    }

    public function readAll(): void
    {
        db()->run('UPDATE inapp_notifications SET read_at = UTC_TIMESTAMP() WHERE user_id = ? AND read_at IS NULL', [Auth::id()]);
        redirect('notifications');
    }
}
