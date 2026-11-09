<?php
declare(strict_types=1);

/**
 * Governance & risk for the Board: board members, meetings and attendance, the policy register (with review
 * dates) and the risk register (likelihood × impact, mitigation). Feeds the Board view on the Dashboard.
 */
class GovernanceController extends Controller
{
    public function __construct()
    {
        $this->needsTable('risks');
    }

    public function index(): void
    {
        $this->view('governance/index', [
            'title' => 'Governance & risk', 's' => Governance::summary(current_term()),
            'meetings' => db()->all('SELECT * FROM board_meetings WHERE meeting_date >= CURDATE() ORDER BY meeting_date LIMIT 4'),
            'policies' => db()->all("SELECT * FROM school_policies WHERE status = 'active' AND next_review <= CURDATE() + INTERVAL " . Governance::REVIEW_DUE_DAYS . ' DAY ORDER BY next_review LIMIT 8'),
            'risks' => db()->all("SELECT * FROM risks WHERE status = 'open' ORDER BY likelihood * impact DESC, review_date LIMIT 8"),
        ]);
    }

    // ---------------------------------------------------------------- board members

    public function members(): void
    {
        $this->view('governance/members', ['title' => 'Board members', 'rows' => db()->all('SELECT m.*, (SELECT COUNT(*) FROM board_attendance a WHERE a.member_id = m.id AND a.status = \'present\') AS attended, (SELECT COUNT(*) FROM board_attendance a WHERE a.member_id = m.id) AS marked FROM board_members m ORDER BY m.is_active DESC, m.name')]);
    }

    public function saveMember(int $id = 0): void
    {
        $d = $this->only(['name', 'position', 'phone', 'email']);
        if (!$d['name']) {
            flash('error', 'Enter the member\'s name.');
            redirect('governance/members');
        }
        $d['is_active'] = isset($_POST['is_active']) || !$id ? 1 : 0;
        if ($id) {
            db()->update('board_members', $d, 'id = ?', [$id]);
        } else {
            $id = db()->insert('board_members', $d);
        }
        audit('board_member_saved', 'board_members', $id);
        flash('success', 'Board member saved.');
        redirect('governance/members');
    }

    // ---------------------------------------------------------------- meetings & attendance

    public function meetings(): void
    {
        $rows = db()->all(
            "SELECT m.*, SUM(a.status = 'present') AS present, COUNT(a.member_id) AS marked FROM board_meetings m
             LEFT JOIN board_attendance a ON a.meeting_id = m.id GROUP BY m.id ORDER BY m.meeting_date DESC LIMIT 100"
        );
        $this->view('governance/meetings', ['title' => 'Board meetings', 'rows' => $rows]);
    }

    public function meeting(int $id): void
    {
        $m = db()->one('SELECT * FROM board_meetings WHERE id = ?', [$id]);
        if (!$m) {
            $this->notFound();
            return;
        }
        $members = db()->all(
            'SELECT b.*, a.status FROM board_members b LEFT JOIN board_attendance a ON a.member_id = b.id AND a.meeting_id = ?
             WHERE b.is_active = 1 OR a.status IS NOT NULL ORDER BY b.name',
            [$id]
        );
        $this->view('governance/meeting', ['title' => $m['title'], 'm' => $m, 'members' => $members]);
    }

    public function saveMeeting(int $id = 0): void
    {
        $d = $this->only(['meeting_date', 'start_time', 'title', 'kind', 'venue', 'agenda', 'decisions']);
        if (!$d['title'] || !$d['meeting_date'] || !strtotime((string) $d['meeting_date']) || !isset(Governance::MEETING_KINDS[$d['kind'] ?? ''])) {
            with_old($_POST);
            flash('error', 'Give the meeting a title, date and type.');
            redirect($id ? 'governance/meetings/' . $id : 'governance/meetings');
        }
        if ($id) {
            $old = db()->one('SELECT meeting_date FROM board_meetings WHERE id = ?', [$id]);
            if ($old && $old['meeting_date'] !== $d['meeting_date']) {
                $d['reminded_at'] = null; // moved: remind again
            }
            db()->update('board_meetings', $d, 'id = ?', [$id]);
        } else {
            $id = db()->insert('board_meetings', $d + ['created_by' => Auth::id()]);
        }
        audit('board_meeting_saved', 'board_meetings', $id);
        flash('success', 'Meeting saved.');
        redirect('governance/meetings/' . $id);
    }

    public function attendance(int $id): void
    {
        if (!db()->one('SELECT id FROM board_meetings WHERE id = ?', [$id])) {
            $this->notFound();
            return;
        }
        $n = 0;
        foreach ((array) ($_POST['status'] ?? []) as $mid => $st) {
            if (isset(Governance::ATTEND[$st])) {
                db()->run('INSERT INTO board_attendance (meeting_id, member_id, status) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)', [$id, (int) $mid, $st]);
                $n++;
            }
        }
        audit('board_attendance_saved', 'board_meetings', $id, ['marked' => $n]);
        flash('success', 'Attendance saved for ' . $n . ' member(s).');
        redirect('governance/meetings/' . $id);
    }

    public function deleteMeeting(int $id): void
    {
        db()->run('DELETE FROM board_meetings WHERE id = ?', [$id]);
        audit('board_meeting_deleted', 'board_meetings', $id);
        flash('success', 'Meeting deleted.');
        redirect('governance/meetings');
    }

    // ---------------------------------------------------------------- policy register

    public function policies(): void
    {
        $f = (string) ($_GET['show'] ?? 'active');
        $where = ['active' => "status = 'active'", 'due' => "status = 'active' AND next_review <= CURDATE() + INTERVAL " . Governance::REVIEW_DUE_DAYS . ' DAY', 'retired' => "status = 'retired'"][$f] ?? "status = 'active'";
        $this->view('governance/policies', ['title' => 'Policy register', 'show' => $f, 'rows' => db()->all("SELECT * FROM school_policies WHERE $where ORDER BY next_review, title"),
            'edit' => isset($_GET['edit']) ? db()->one('SELECT * FROM school_policies WHERE id = ?', [(int) $_GET['edit']]) : null]);
    }

    public function savePolicy(int $id = 0): void
    {
        $d = $this->only(['title', 'category', 'owner', 'approved_on', 'last_reviewed', 'notes']);
        $months = (int) ($_POST['review_months'] ?? 12);
        if (!$d['title'] || $months < 1 || $months > 60) {
            with_old($_POST);
            flash('error', 'Enter the policy title and how often it is reviewed (1–60 months).');
            redirect('governance/policies');
        }
        $base = $d['last_reviewed'] ?: ($d['approved_on'] ?: date('Y-m-d'));
        $d += ['review_months' => $months, 'next_review' => date('Y-m-d', strtotime("$base +$months months")), 'status' => ($_POST['status'] ?? 'active') === 'retired' ? 'retired' : 'active', 'reminded_at' => null];
        if ($id) {
            db()->update('school_policies', $d, 'id = ?', [$id]);
        } else {
            $id = db()->insert('school_policies', $d + ['created_by' => Auth::id()]);
        }
        audit('policy_saved', 'school_policies', $id);
        flash('success', 'Policy saved. Next review: ' . fmt_date($d['next_review']) . '.');
        redirect('governance/policies');
    }

    /** "Reviewed today": sets the last review to today and the next review one cycle later. */
    public function policyReviewed(int $id): void
    {
        $p = db()->one('SELECT * FROM school_policies WHERE id = ?', [$id]);
        if (!$p) {
            $this->notFound();
            return;
        }
        $next = date('Y-m-d', strtotime('+' . (int) $p['review_months'] . ' months'));
        db()->update('school_policies', ['last_reviewed' => date('Y-m-d'), 'next_review' => $next, 'reminded_at' => null], 'id = ?', [$id]);
        audit('policy_reviewed', 'school_policies', $id);
        flash('success', '"' . $p['title'] . '" marked as reviewed. Next review: ' . fmt_date($next) . '.');
        redirect('governance/policies', ['show' => (string) ($_POST['show'] ?? 'active')]);
    }

    // ---------------------------------------------------------------- risk register

    public function risks(): void
    {
        $f = (string) ($_GET['show'] ?? 'open');
        $this->view('governance/risks', ['title' => 'Risk register', 'show' => $f,
            'rows' => db()->all('SELECT * FROM risks WHERE status = ? ORDER BY likelihood * impact DESC, review_date', [$f === 'closed' ? 'closed' : 'open'])]);
    }

    public function newRisk(): void
    {
        $this->risk(0);
    }

    public function risk(int $id): void
    {
        $r = $id ? db()->one('SELECT * FROM risks WHERE id = ?', [$id]) : null;
        if ($id && !$r) {
            $this->notFound();
            return;
        }
        $this->view('governance/risk', ['title' => $r ? $r['ref_no'] . ' ' . $r['title'] : 'New risk', 'r' => $r]);
    }

    public function saveRisk(int $id = 0): void
    {
        $d = $this->only(['title', 'category', 'owner', 'mitigation', 'mitigation_status', 'review_date']);
        $d['likelihood'] = (int) ($_POST['likelihood'] ?? 0);
        $d['impact'] = (int) ($_POST['impact'] ?? 0);
        $d['status'] = ($_POST['status'] ?? 'open') === 'closed' ? 'closed' : 'open';
        if (!$d['title'] || !isset(Governance::RISK_CATEGORIES[$d['category'] ?? '']) || !isset(Governance::MITIGATION[$d['mitigation_status'] ?? ''])
            || $d['likelihood'] < 1 || $d['likelihood'] > 5 || $d['impact'] < 1 || $d['impact'] > 5) {
            with_old($_POST);
            flash('error', 'Enter the risk, its type, likelihood and impact (1–5) and the mitigation status.');
            redirect($id ? 'governance/risks/' . $id : 'governance/risks/new');
        }
        if ($id) {
            $old = db()->one('SELECT review_date FROM risks WHERE id = ?', [$id]);
            if (($old['review_date'] ?? null) !== $d['review_date']) {
                $d['reminded_at'] = null;
            }
            db()->update('risks', $d, 'id = ?', [$id]);
        } else {
            $id = db()->transaction(fn(Database $db) => $db->insert('risks', $d + ['ref_no' => Fees::nextNumber($db, 'RSK'), 'created_by' => Auth::id()]));
        }
        audit('risk_saved', 'risks', (int) $id);
        flash('success', 'Risk saved.');
        redirect('governance/risks/' . $id);
    }
}
