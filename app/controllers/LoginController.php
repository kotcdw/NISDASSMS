<?php
declare(strict_types=1);

/** Create and reset parent (phone number) and pupil (admission number) sign-ins. */
class LoginController extends Controller
{
    public function index(): void
    {
        $tab = ($_GET['tab'] ?? 'parents') === 'pupils' ? 'pupils' : 'parents';
        $classId = (int) ($_GET['class'] ?? 0);
        $yid = current_year()['id'] ?? 0;
        $classes = db()->all('SELECT c.id, c.name, l.category FROM classes c JOIN levels l ON l.id = c.level_id WHERE c.is_active = 1 ORDER BY l.sort_order, c.name');
        $where = "p.status = 'active' AND p.deleted_at IS NULL" . ($classId ? ' AND e.class_id = ' . $classId : '');
        if ($tab === 'parents') {
            $rows = db()->all(
                "SELECT g.id, g.name, g.phone, g.user_id, u.is_active, u.last_login_at, u.must_change_password,
                        GROUP_CONCAT(DISTINCT CONCAT(p.first_name, ' (', c.name, ')') ORDER BY p.first_name SEPARATOR ', ') AS children
                 FROM guardians g JOIN pupil_guardian pg ON pg.guardian_id = g.id JOIN pupils p ON p.id = pg.pupil_id
                 JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ? JOIN classes c ON c.id = e.class_id
                 LEFT JOIN users u ON u.id = g.user_id
                 WHERE $where GROUP BY g.id, g.name, g.phone, g.user_id, u.is_active, u.last_login_at, u.must_change_password ORDER BY g.name",
                [$yid]
            );
        } else {
            $rows = db()->all(
                "SELECT p.id, p.first_name, p.middle_name, p.last_name, p.admission_no, p.user_id, c.name AS class_name, l.category,
                        u.is_active, u.last_login_at, u.must_change_password
                 FROM pupils p JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ? JOIN classes c ON c.id = e.class_id
                 JOIN levels l ON l.id = c.level_id LEFT JOIN users u ON u.id = p.user_id
                 WHERE $where ORDER BY l.sort_order, c.name, p.last_name, p.first_name",
                [$yid]
            );
        }
        $this->view('logins/index', ['title' => 'Parent & student logins', 'tab' => $tab, 'rows' => $rows, 'classes' => $classes, 'classId' => $classId]);
    }

    public function parents(): void
    {
        $ids = array_map('intval', (array) ($_POST['ids'] ?? []));
        $sms = !empty($_POST['sms']);
        $done = 0;
        $skipped = 0;
        $slips = [];
        foreach (array_slice($ids, 0, 300) as $gid) {
            $r = Accounts::parentLogin($gid, $sms);
            if ($r) {
                $done++;
                $slips[] = ['name' => (string) db()->value('SELECT name FROM guardians WHERE id = ?', [$gid]), 'who' => 'Parent / guardian'] + $r;
            } else {
                $skipped++;
            }
        }
        if ($sms) {
            Notifier::process(50);
        }
        $_SESSION['login_slips'] = $slips;
        flash($done ? 'success' : 'warning', "$done parent login(s) ready" . ($sms ? ' and sent by SMS' : '') . ($skipped ? "; $skipped skipped (no valid phone number, or the number is already used by another account)" : '') . '.');
        redirect('logins', ['tab' => 'parents', 'class' => (int) ($_POST['class'] ?? 0), 'slips' => $done && !$sms ? 1 : null]);
    }

    public function pupils(): void
    {
        $ids = array_map('intval', (array) ($_POST['ids'] ?? []));
        $slips = [];
        foreach (array_slice($ids, 0, 300) as $pid) {
            $r = Accounts::pupilLogin($pid);
            if ($r) {
                $p = db()->one('SELECT first_name, last_name FROM pupils WHERE id = ?', [$pid]);
                $slips[] = ['name' => $p['first_name'] . ' ' . $p['last_name'], 'who' => 'Student'] + $r;
            }
        }
        $_SESSION['login_slips'] = $slips;
        flash($slips ? 'success' : 'warning', count($slips) . ' student login(s) ready. Print the slips now — passwords are shown only once.');
        redirect('logins', ['tab' => 'pupils', 'class' => (int) ($_POST['class'] ?? 0), 'slips' => $slips ? 1 : null]);
    }

    /** One-time printable sign-in slips (passwords are not stored, so they cannot be shown again). */
    public function slips(): void
    {
        $slips = $_SESSION['login_slips'] ?? [];
        unset($_SESSION['login_slips']);
        if (!$slips) {
            flash('warning', 'The slips have already been shown. Reset the password to make a new slip.');
            redirect('logins');
        }
        $this->view('logins/slips', ['title' => 'Sign-in slips', 'slips' => $slips], 'layouts/print');
    }

    public function toggle(int $id): void
    {
        $u = db()->one("SELECT * FROM users WHERE id = ? AND role IN ('parent','pupil')", [$id]) ?? $this->notFound();
        db()->update('users', ['is_active' => (int) $u['is_active'] ? 0 : 1], 'id = ?', [$id]);
        if ((int) $u['is_active']) {
            Security::revokeAll($id, 'account_disabled');
        }
        audit((int) $u['is_active'] ? 'login_disabled' : 'login_enabled', 'users', $id);
        flash('success', $u['name'] . ((int) $u['is_active'] ? ' can no longer sign in.' : ' can sign in again.'));
        back('logins');
    }
}
