<?php
declare(strict_types=1);

/**
 * CRM: every admission enquiry (phone call, walk-in, the public enquiry form) followed up until the child applies
 * and is enrolled — with follow-up dates, a log of calls and visits, and the application link sent by SMS.
 * Marketing: SMS / WhatsApp campaigns to enquirers and past or current families, with delivery and results.
 */
class CrmController extends Controller
{
    public const PUBLIC_PER_HOUR = 5;

    public function __construct()
    {
        $this->needsTable('crm_enquiries');
    }

    // ---------------------------------------------------------------- enquiries

    public function index(): void
    {
        Crm::syncApplications();
        $stage = (string) ($_GET['stage'] ?? 'open');
        $q = trim((string) ($_GET['q'] ?? ''));
        $sql = 'SELECT e.*, l.name AS level_name, u.name AS assigned_name FROM crm_enquiries e LEFT JOIN levels l ON l.id = e.level_id LEFT JOIN users u ON u.id = e.assigned_to WHERE 1';
        $p = [];
        if ($stage === 'open') {
            $sql .= " AND e.stage IN ('new','contacted','visit_booked','visited')";
        } elseif ($stage === 'due') {
            $sql .= " AND e.stage IN ('new','contacted','visit_booked','visited') AND e.next_follow_up <= CURDATE()";
        } elseif (isset(Crm::STAGES[$stage])) {
            $sql .= ' AND e.stage = ?';
            $p[] = $stage;
        }
        if ($q !== '') {
            $sql .= ' AND (e.parent_name LIKE ? OR e.child_name LIKE ? OR e.phone LIKE ? OR e.ref_no LIKE ?)';
            array_push($p, "%$q%", "%$q%", '%' . preg_replace('/\D/', '', $q) . '%', "%$q%");
        }
        [$rows, $pager] = paginate($sql . ' ORDER BY e.next_follow_up IS NULL, e.next_follow_up, e.created_at DESC', $p, 300);
        $counts = array_column(db()->all('SELECT stage, COUNT(*) AS n FROM crm_enquiries GROUP BY stage'), 'n', 'stage');
        $due = (int) db()->value("SELECT COUNT(*) FROM crm_enquiries WHERE stage IN ('new','contacted','visit_booked','visited') AND next_follow_up <= CURDATE()");
        $year = (int) db()->value('SELECT COUNT(*) FROM crm_enquiries WHERE created_at >= ?', [date('Y-01-01')]);
        $won = (int) db()->value("SELECT COUNT(*) FROM crm_enquiries WHERE created_at >= ? AND stage IN ('applied','enrolled')", [date('Y-01-01')]);
        $bySource = db()->all("SELECT source, COUNT(*) AS n, SUM(stage IN ('applied','enrolled')) AS won FROM crm_enquiries WHERE created_at >= ? GROUP BY source ORDER BY n DESC", [date('Y-01-01')]);
        $this->view('crm/index', ['title' => 'CRM — enquiries', 'rows' => $rows, 'pager' => $pager, 'stage' => $stage, 'q' => $q, 'counts' => $counts, 'due' => $due, 'year' => $year, 'won' => $won, 'bySource' => $bySource]);
    }

    public function create(): void
    {
        $this->view('crm/form', ['title' => 'New enquiry', 'e' => null, 'levels' => db()->all('SELECT id, name FROM levels ORDER BY sort_order'), 'staff' => $this->staff()]);
    }

    public function store(): void
    {
        $data = $this->input('crm/create');
        if ($dup = db()->one("SELECT id, ref_no FROM crm_enquiries WHERE phone = ? AND stage IN ('new','contacted','visit_booked','visited') LIMIT 1", [$data['phone']])) {
            if (empty($_POST['allow_duplicate'])) {
                with_old($_POST + ['allow_duplicate' => '1']);
                flash('warning', 'This phone number already has an open enquiry (' . $dup['ref_no'] . '). Open that one, or save again to record a second child.');
                redirect('crm/create');
            }
        }
        $id = db()->transaction(fn(Database $d) => $d->insert('crm_enquiries', $data + ['ref_no' => Fees::nextNumber($d, 'ENQ'), 'created_by' => Auth::id(), 'next_follow_up' => $data['next_follow_up'] ?? date('Y-m-d')]));
        Crm::addActivity($id, 'note', 'Enquiry recorded (' . Crm::SOURCES[$data['source']] . ').');
        audit('crm_enquiry_created', 'crm_enquiries', $id);
        flash('success', 'Enquiry saved.');
        redirect('crm/' . $id);
    }

    public function show(int $id): void
    {
        Crm::syncApplications();
        $e = $this->find($id);
        $acts = db()->all('SELECT a.*, u.name AS by_name FROM crm_activities a LEFT JOIN users u ON u.id = a.created_by WHERE a.enquiry_id = ? ORDER BY a.created_at DESC, a.id DESC', [$id]);
        $this->view('crm/show', ['title' => $e['ref_no'], 'e' => $e, 'acts' => $acts, 'levels' => db()->all('SELECT id, name FROM levels ORDER BY sort_order'), 'staff' => $this->staff()]);
    }

    public function update(int $id): void
    {
        $this->find($id);
        $data = $this->input('crm/' . $id);
        db()->update('crm_enquiries', $data + ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        audit('crm_enquiry_updated', 'crm_enquiries', $id);
        flash('success', 'Details saved.');
        redirect('crm/' . $id);
    }

    /** Log a call / visit / note, move the stage and set the next follow-up in one go. */
    public function activity(int $id): void
    {
        $e = $this->find($id);
        $kind = (string) ($_POST['kind'] ?? 'note');
        $note = trim((string) ($_POST['note'] ?? ''));
        $stage = (string) ($_POST['stage'] ?? $e['stage']);
        $next = trim((string) ($_POST['next_follow_up'] ?? ''));
        $visit = trim((string) ($_POST['visit_at'] ?? ''));
        $err = null;
        if (!isset(Crm::ACTIVITIES[$kind]) || !isset(Crm::STAGES[$stage])) {
            $err = 'Choose what happened and the stage.';
        } elseif ($note === '' && $stage === $e['stage']) {
            $err = 'Write what happened (or change the stage).';
        } elseif ($next !== '' && (!Validator::isDate($next) || $next < date('Y-m-d'))) {
            $err = 'The next follow-up must be today or later.';
        } elseif ($stage === 'lost' && trim((string) ($_POST['lost_reason'] ?? '')) === '') {
            $err = 'Say why they are not joining (it helps the school improve).';
        } elseif ($stage === 'visit_booked' && !strtotime($visit)) {
            $err = 'Enter the date and time of the school visit.';
        }
        if ($err) {
            flash('error', $err);
            redirect('crm/' . $id);
        }
        $upd = ['stage' => $stage, 'next_follow_up' => in_array($stage, Crm::OPEN, true) ? ($next ?: null) : null, 'updated_at' => date('Y-m-d H:i:s')];
        if ($stage === 'lost') {
            $upd['lost_reason'] = mb_substr(trim((string) $_POST['lost_reason']), 0, 255);
        }
        if ($stage === 'visit_booked') {
            $upd['visit_at'] = date('Y-m-d H:i:s', strtotime($visit));
            $upd['next_follow_up'] = $upd['next_follow_up'] ?? date('Y-m-d', strtotime($visit));
        }
        db()->update('crm_enquiries', $upd, 'id = ?', [$id]);
        if ($note !== '') {
            Crm::addActivity($id, $kind, $note);
        }
        if ($stage !== $e['stage']) {
            Crm::addActivity($id, 'stage', 'Stage: ' . Crm::STAGES[$e['stage']][0] . ' → ' . Crm::STAGES[$stage][0] . ($stage === 'visit_booked' ? ' (' . date('D j M, H:i', strtotime($visit)) . ')' : '') . ($stage === 'lost' ? ' — ' . $upd['lost_reason'] : ''));
        }
        audit('crm_activity', 'crm_enquiries', $id, ['stage' => $stage]);
        flash('success', 'Saved.' . ($upd['next_follow_up'] ? ' Next follow-up: ' . fmt_date($upd['next_follow_up'], 'D j M') . '.' : ''));
        redirect('crm/' . $id);
    }

    /** SMS the online application link to the parent. */
    public function sendLink(int $id): void
    {
        $e = $this->find($id);
        $body = Notifier::render('crm_apply_link', ['parent' => $e['parent_name'], 'link' => absolute_url('apply'), 'school_phone' => setting('school_phone', '')]);
        $ok = $body !== null && !$e['opted_out'] && Notifier::sms($e['phone'], $body, ['name' => $e['parent_name'], 'event' => 'crm_apply_link', 'entity' => 'crm_enquiries', 'entity_id' => $id]);
        if ($ok) {
            Crm::addActivity($id, 'sms', 'Sent the online application link by SMS.');
            if ($e['stage'] === 'new') {
                db()->update('crm_enquiries', ['stage' => 'contacted'], 'id = ?', [$id]);
            }
        }
        flash($ok ? 'success' : 'error', $ok ? 'Application link sent to ' . $e['phone'] . '.' : ($e['opted_out'] ? 'This parent asked not to receive messages.' : 'Could not send: check the phone number.'));
        redirect('crm/' . $id);
    }

    public function optOut(int $id): void
    {
        $e = $this->find($id);
        $out = empty($e['opted_out']);
        db()->update('crm_enquiries', ['opted_out' => $out ? 1 : 0], 'id = ?', [$id]);
        if ($out) {
            db()->run('INSERT IGNORE INTO mkt_optouts (phone, reason, created_by) VALUES (?, ?, ?)', [$e['phone'], 'Asked from enquiry ' . $e['ref_no'], Auth::id()]);
        } else {
            db()->run('DELETE FROM mkt_optouts WHERE phone = ?', [$e['phone']]);
        }
        Crm::addActivity($id, 'note', $out ? 'Parent asked not to receive marketing messages.' : 'Parent agreed to receive messages again.');
        flash('success', $out ? 'No more campaign messages will go to this number.' : 'Messages allowed again.');
        redirect('crm/' . $id);
    }

    // ---------------------------------------------------------------- public enquiry form (website / QR code)

    public function publicForm(): void
    {
        $this->view('crm/public', ['title' => 'Enquire about admission', 'levels' => db()->all('SELECT id, name FROM levels ORDER BY sort_order')], 'layouts/public');
    }

    public function publicStore(): void
    {
        if (!empty($_POST['website'])) { // honeypot
            redirect('enquire/thanks');
        }
        $ip = Security::clientIp();
        if ((int) db()->value("SELECT COUNT(*) FROM audit_log WHERE action = 'crm_enquiry_online' AND ip = ? AND created_at > (UTC_TIMESTAMP() - INTERVAL 1 HOUR)", [$ip]) >= self::PUBLIC_PER_HOUR) {
            flash('error', 'Too many enquiries from this device. Please call the school office.');
            redirect('enquire');
        }
        $name = mb_substr(trim((string) ($_POST['parent_name'] ?? '')), 0, 150);
        $phone = (string) Admission::normalisePhone((string) ($_POST['phone'] ?? ''));
        $level = (int) ($_POST['level_id'] ?? 0) ?: null;
        $err = null;
        if ($name === '') {
            $err = 'Please enter your name.';
        } elseif (!SmsGateway::validPhone($phone)) {
            $err = 'Please enter a valid Ghana mobile number, e.g. 024 123 4567.';
        } elseif ($level && !db()->value('SELECT 1 FROM levels WHERE id = ?', [$level])) {
            $err = 'Choose a class.';
        }
        if ($err) {
            with_old($_POST);
            flash('error', $err);
            redirect('enquire');
        }
        $id = db()->transaction(fn(Database $d) => $d->insert('crm_enquiries', ['ref_no' => Fees::nextNumber($d, 'ENQ'), 'parent_name' => $name, 'phone' => $phone,
            'email' => filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL) ?: null, 'child_name' => mb_substr(trim((string) ($_POST['child_name'] ?? '')), 0, 150) ?: null,
            'level_id' => $level, 'source' => 'website', 'notes' => mb_substr(trim((string) ($_POST['message'] ?? '')), 0, 1000) ?: null, 'next_follow_up' => date('Y-m-d')]));
        db()->insert('crm_activities', ['enquiry_id' => $id, 'kind' => 'note', 'note' => 'Enquiry sent from the website form.']);
        audit('crm_enquiry_online', 'crm_enquiries', $id);
        Notifier::inappPermission('crm.manage', 'New admission enquiry: ' . $name, $phone, url('crm/' . $id));
        redirect('enquire/thanks');
    }

    public function thanks(): void
    {
        $this->view('crm/thanks', ['title' => 'Thank you'], 'layouts/public');
    }

    // ---------------------------------------------------------------- marketing campaigns

    public function campaigns(): void
    {
        $rows = db()->all('SELECT c.*, u.name AS by_name FROM mkt_campaigns c LEFT JOIN users u ON u.id = c.created_by ORDER BY c.created_at DESC LIMIT 100');
        foreach ($rows as &$r) {
            $r['res'] = $r['status'] === 'sent' ? Crm::results((int) $r['id'], $r['sent_at']) : null;
        }
        unset($r);
        $bySource = db()->all("SELECT source, COUNT(*) AS n, SUM(stage IN ('applied','enrolled')) AS won FROM crm_enquiries WHERE created_at >= ? GROUP BY source ORDER BY n DESC", [date('Y-01-01')]);
        $this->view('crm/campaigns', ['title' => 'Marketing campaigns', 'rows' => $rows, 'optouts' => (int) db()->value('SELECT COUNT(*) FROM mkt_optouts'), 'bySource' => $bySource]);
    }

    public function newCampaign(): void
    {
        $this->view('crm/campaign_form', ['title' => 'New campaign']);
    }

    public function saveCampaign(): void
    {
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 120);
        $aud = (string) ($_POST['audience'] ?? '');
        $msg = trim((string) ($_POST['message'] ?? ''));
        $err = null;
        if ($name === '') {
            $err = 'Give the campaign a name (only staff see it).';
        } elseif (!isset(Crm::AUDIENCES[$aud])) {
            $err = 'Choose who should receive it.';
        } elseif ($msg === '' || mb_strlen($msg) > 480) {
            $err = 'Write the message (up to 480 characters, about 3 SMS).';
        }
        if ($err) {
            with_old($_POST);
            flash('error', $err);
            redirect('marketing/create');
        }
        $id = db()->insert('mkt_campaigns', ['name' => $name, 'audience' => $aud, 'audience_value' => mb_substr((string) ($_POST['audience_value'] ?? ''), 0, 60) ?: null,
            'custom_phones' => $aud === 'custom' ? mb_substr((string) ($_POST['custom_phones'] ?? ''), 0, 20000) : null, 'message' => $msg, 'created_by' => Auth::id()]);
        audit('campaign_created', 'mkt_campaigns', $id);
        redirect('marketing/' . $id);
    }

    public function campaign(int $id): void
    {
        $c = db()->one('SELECT c.*, u.name AS sent_name FROM mkt_campaigns c LEFT JOIN users u ON u.id = c.sent_by WHERE c.id = ?', [$id]) ?? $this->notFound();
        $people = $c['status'] === 'draft' ? Crm::audience($c['audience'], (string) $c['audience_value'], (string) $c['custom_phones']) : [];
        $res = $c['status'] === 'sent' ? Crm::results($id, $c['sent_at']) : null;
        $this->view('crm/campaign', ['title' => $c['name'], 'c' => $c, 'people' => $people, 'res' => $res, 'body' => $this->body($c['message']),
            'list' => $c['status'] === 'sent' ? db()->all('SELECT m.*, o.status AS ostatus, o.channel, o.read_at FROM mkt_recipients m LEFT JOIN notification_outbox o ON o.id = m.outbox_id WHERE m.campaign_id = ? ORDER BY m.id LIMIT 300', [$id]) : []]);
    }

    public function sendCampaign(int $id): void
    {
        $c = db()->one('SELECT * FROM mkt_campaigns WHERE id = ?', [$id]) ?? $this->notFound();
        if ($c['status'] !== 'draft') {
            flash('error', 'This campaign was already sent.');
            redirect('marketing/' . $id);
        }
        $people = Crm::audience($c['audience'], (string) $c['audience_value'], (string) $c['custom_phones']);
        if (!$people || count($people) > Crm::MAX_RECIPIENTS || empty($_POST['confirm'])) {
            flash('error', !$people ? 'Nobody to send to.' : (count($people) > Crm::MAX_RECIPIENTS ? 'More than ' . Crm::MAX_RECIPIENTS . ' people: choose a smaller group.' : 'Tick the box to confirm.'));
            redirect('marketing/' . $id);
        }
        // Mark as sent first so a double click cannot send twice
        if (!db()->update('mkt_campaigns', ['status' => 'sent', 'sent_at' => date('Y-m-d H:i:s'), 'sent_by' => Auth::id(), 'recipients' => count($people)], "id = ? AND status = 'draft'", [$id])) {
            redirect('marketing/' . $id);
        }
        $body = $this->body($c['message']);
        $n = 0;
        foreach ($people as $phone => [$name, $enq]) {
            $oid = Notifier::sms($phone, $body, ['name' => $name, 'event' => 'campaign', 'entity' => 'mkt_campaigns', 'entity_id' => $id]);
            db()->insert('mkt_recipients', ['campaign_id' => $id, 'phone' => $phone, 'name' => $name, 'enquiry_id' => $enq, 'outbox_id' => $oid]);
            if ($enq) {
                db()->insert('crm_activities', ['enquiry_id' => $enq, 'kind' => 'sms', 'note' => 'Campaign: ' . $c['name'], 'created_by' => Auth::id()]);
            }
            $n += $oid ? 1 : 0;
        }
        audit('campaign_sent', 'mkt_campaigns', $id, ['recipients' => $n]);
        flash('success', "$n message(s) queued. They go out in the next few minutes (not during quiet hours).");
        redirect('marketing/' . $id);
    }

    public function deleteCampaign(int $id): void
    {
        db()->run("DELETE FROM mkt_campaigns WHERE id = ? AND status = 'draft'", [$id]);
        flash('success', 'Draft deleted.');
        redirect('marketing');
    }

    public function optouts(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $phone = (string) Admission::normalisePhone((string) ($_POST['phone'] ?? ''));
            if (!empty($_POST['remove'])) {
                db()->run('DELETE FROM mkt_optouts WHERE phone = ?', [$phone]);
                db()->update('crm_enquiries', ['opted_out' => 0], 'phone = ?', [$phone]);
                flash('success', 'Number removed from the list.');
            } elseif (SmsGateway::validPhone($phone)) {
                db()->run('INSERT IGNORE INTO mkt_optouts (phone, reason, created_by) VALUES (?, ?, ?)', [$phone, mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 120) ?: null, Auth::id()]);
                db()->update('crm_enquiries', ['opted_out' => 1], 'phone = ?', [$phone]);
                flash('success', "$phone will not receive campaigns.");
            } else {
                flash('error', 'Enter a valid Ghana mobile number.');
            }
            redirect('marketing/optouts');
        }
        $this->view('crm/optouts', ['title' => 'Do not message', 'rows' => db()->all('SELECT o.*, u.name AS by_name FROM mkt_optouts o LEFT JOIN users u ON u.id = o.created_by ORDER BY o.created_at DESC')]);
    }

    // ---------------------------------------------------------------- helpers

    /** The message with the school's opt-out line added. */
    private function body(string $msg): string
    {
        $phone = setting('school_phone', '');
        return trim($msg) . ' - ' . setting('school_short_name', 'NISDAS') . ($phone !== '' ? ". To stop these messages call $phone." : '');
    }

    private function input(string $back): array
    {
        $name = mb_substr(trim((string) ($_POST['parent_name'] ?? '')), 0, 150);
        $phone = (string) Admission::normalisePhone((string) ($_POST['phone'] ?? ''));
        $source = (string) ($_POST['source'] ?? 'phone');
        $level = (int) ($_POST['level_id'] ?? 0) ?: null;
        $dob = trim((string) ($_POST['child_dob'] ?? ''));
        $next = trim((string) ($_POST['next_follow_up'] ?? ''));
        $assigned = (int) ($_POST['assigned_to'] ?? 0) ?: null;
        $email = trim((string) ($_POST['email'] ?? ''));
        $err = null;
        if ($name === '') {
            $err = "Enter the parent's name.";
        } elseif (!SmsGateway::validPhone($phone)) {
            $err = 'Enter a valid Ghana mobile number.';
        } elseif (!isset(Crm::SOURCES[$source])) {
            $err = 'Choose how they heard about the school.';
        } elseif ($level && !db()->value('SELECT 1 FROM levels WHERE id = ?', [$level])) {
            $err = 'Choose a valid class.';
        } elseif ($dob !== '' && (!Validator::isDate($dob) || $dob > date('Y-m-d'))) {
            $err = "Check the child's date of birth.";
        } elseif ($next !== '' && !Validator::isDate($next)) {
            $err = 'Check the follow-up date.';
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = 'Check the email address.';
        } elseif ($assigned && !db()->value("SELECT 1 FROM users WHERE id = ? AND role NOT IN ('parent','pupil')", [$assigned])) {
            $err = 'Choose a staff member.';
        }
        if ($err) {
            with_old($_POST);
            flash('error', $err);
            redirect($back);
        }
        $out = ['parent_name' => $name, 'phone' => $phone, 'email' => $email ?: null, 'child_name' => mb_substr(trim((string) ($_POST['child_name'] ?? '')), 0, 150) ?: null,
            'child_dob' => $dob ?: null, 'level_id' => $level, 'source' => $source, 'assigned_to' => $assigned, 'notes' => mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 1000) ?: null];
        if ($next !== '') {
            $out['next_follow_up'] = $next;
        }
        return $out;
    }

    private function find(int $id): array
    {
        return db()->one(
            'SELECT e.*, l.name AS level_name, u.name AS assigned_name, a.ref_no AS app_ref, a.stage AS app_stage FROM crm_enquiries e LEFT JOIN levels l ON l.id = e.level_id
             LEFT JOIN users u ON u.id = e.assigned_to LEFT JOIN applications a ON a.id = e.application_id WHERE e.id = ?',
            [$id]
        ) ?? $this->notFound();
    }

    private function staff(): array
    {
        return db()->all("SELECT id, name FROM users WHERE is_active = 1 AND role IN ('" . implode("','", array_map('addslashes', Auth::rolesWith('crm.manage'))) . "') ORDER BY name");
    }
}
