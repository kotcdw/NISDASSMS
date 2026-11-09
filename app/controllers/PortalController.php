<?php
declare(strict_types=1);

/** Parent portal: everything a parent may see about their own children. */
class PortalController extends Controller
{
    public function index(): void
    {
        $preview = null;
        if (Auth::role() === 'super_admin') {
            // Super Admin: see the parent portal exactly as a chosen parent sees it (read-only preview)
            $preview = $this->previewGuardian();
            if (!$preview['guardian']) {
                $this->view('portal/preview_pick', ['title' => 'Parent portal (preview)'] + $preview);
                return;
            }
        } elseif (Auth::role() !== 'parent') {
            flash('info', '"My children" is the page parents see. Use Students to look up any child.');
            redirect(can('pupils.view') ? 'pupils' : 'dashboard');
        }
        $term = current_term();
        $children = [];
        foreach ($preview ? Accounts::childrenOfGuardian((int) $preview['guardian']['id']) : Accounts::childrenOf((int) Auth::id()) as $c) {
            $children[] = $c + $this->summary($c, $term);
        }
        [$w, $p] = Notices::visibility();
        $notices = db()->all(
            "SELECT n.*, r.read_at, r.acknowledged_at FROM notices n LEFT JOIN notice_reads r ON r.notice_id = n.id AND r.user_id = ?
             WHERE $w AND n.publish_at <= NOW() AND (n.expires_on IS NULL OR n.expires_on >= CURDATE()) ORDER BY n.is_pinned DESC, n.publish_at DESC LIMIT 5",
            array_merge([Auth::id()], $p)
        );
        $unreadMsgs = (int) db()->value(
            'SELECT COUNT(*) FROM conversation_members cm JOIN conversations cv ON cv.id = cm.conversation_id
             WHERE cm.user_id = ? AND (cm.last_read_at IS NULL OR cv.last_message_at > cm.last_read_at)',
            [Auth::id()]
        );
        $events = db()->all('SELECT * FROM calendar_events WHERE end_date >= CURDATE() AND ' . CalendarEvent::visibilitySql() . ' ORDER BY start_date, start_time LIMIT 5');
        $this->view('portal/index', ['title' => 'My children', 'children' => $children, 'notices' => $notices, 'unreadMsgs' => $unreadMsgs, 'events' => $events, 'term' => $term, 'preview' => $preview['guardian'] ?? null]);
    }

    public function child(int $id): void
    {
        $child = $this->child_($id);
        $term = current_term();
        $absences = $term ? db()->all(
            "SELECT date, status, note FROM attendance_records WHERE pupil_id = ? AND date BETWEEN ? AND ? AND status <> 'present' ORDER BY date DESC",
            [$id, $term['start_date'], $term['end_date']]
        ) : [];
        $invoices = db()->all("SELECT * FROM invoices WHERE pupil_id = ? AND status <> 'cancelled' ORDER BY issue_date DESC, id DESC", [$id]);
        $family = null;
        if (Family::ready() && ($gid = Family::guardianFor($id))) {
            $family = [
                'credit' => Family::credit($gid),
                'notes' => db()->all("SELECT cn.cn_no, cn.amount, cn.reason, cn.decided_at, i.invoice_no FROM credit_notes cn JOIN invoices i ON i.id = cn.invoice_id WHERE cn.pupil_id = ? AND cn.status = 'approved' ORDER BY cn.id DESC LIMIT 10", [$id]),
                'refunds' => db()->all("SELECT rf_no, amount, status, method, paid_on, requested_at FROM fee_refunds WHERE guardian_id = ? AND status <> 'rejected' ORDER BY id DESC LIMIT 10", [$gid]),
            ];
        }
        $payments = db()->all("SELECT pay.receipt_no, pay.amount, pay.method, pay.paid_on, i.invoice_no FROM payments pay JOIN invoices i ON i.id = pay.invoice_id WHERE pay.pupil_id = ? AND pay.status = 'completed' ORDER BY pay.paid_on DESC, pay.id DESC LIMIT 20", [$id]);
        $reports = [];
        foreach (db()->all(
            'SELECT t.id, t.name, y.name AS year_name FROM enrolments e JOIN terms t ON t.academic_year_id = e.academic_year_id
             JOIN academic_years y ON y.id = t.academic_year_id JOIN report_releases rr ON rr.term_id = t.id AND rr.class_id = e.class_id
             WHERE e.pupil_id = ? ORDER BY t.start_date DESC',
            [$id]
        ) as $t) {
            $reports[] = $t + ['token' => $this->reportToken((int) $t['id'], $id)];
        }
        $timetable = [];
        $periods = [];
        $teachers = [];
        if ($child['class_id']) {
            $periods = Timetable::periods((string) $child['category']);
            foreach (db()->all(
                'SELECT ts.day, ts.period_id, s.name AS subject_name, u.name AS teacher_name FROM timetable_slots ts
                 LEFT JOIN class_subjects cs ON cs.id = ts.class_subject_id LEFT JOIN subjects s ON s.id = cs.subject_id
                 LEFT JOIN users u ON u.id = ts.teacher_id WHERE ts.class_id = ?',
                [$child['class_id']]
            ) as $s) {
                $timetable[$s['day']][$s['period_id']] = $s;
            }
            $teachers = db()->all(
                'SELECT u.id, u.name, MAX(u.id = ?) AS is_class_teacher,
                        GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ", ") AS subjects
                 FROM users u LEFT JOIN class_subjects cs ON cs.teacher_id = u.id AND cs.class_id = ?
                 LEFT JOIN subjects s ON s.id = cs.subject_id
                 WHERE u.is_active = 1 AND (u.id = ? OR cs.class_id = ?)
                 GROUP BY u.id, u.name ORDER BY u.name',
                [(int) ($child['class_teacher_id'] ?? 0), $child['class_id'], (int) ($child['class_teacher_id'] ?? 0), $child['class_id']]
            );
        }
        $transport = module_on('transport') ? db()->one(
            'SELECT a.stop, a.trips, r.name AS route_name, r.stops, r.vehicle, r.driver_name, r.driver_phone, r.attendant_name, r.attendant_phone
             FROM transport_assignments a JOIN transport_routes r ON r.id = a.route_id
             WHERE a.pupil_id = ? AND a.academic_year_id = ? AND a.ended_on IS NULL ORDER BY a.started_on DESC LIMIT 1',
            [$id, current_year()['id'] ?? 0]
        ) : null;
        $progress = Progress::pupil($id, true);
        $passMark = $child['category'] ? (float) Results::scheme((string) $child['category'])['pass_mark'] : null;
        $this->view('portal/child', [
            'progress' => $progress, 'passMark' => $passMark, 'homework' => Assignments::forPupil($id, 8), 'canteen' => $this->canteen($id),
            'title' => $child['first_name'] . ' ' . $child['last_name'], 'child' => $child + $this->summary($child, $term), 'term' => $term,
            'absences' => $absences, 'invoices' => $invoices, 'payments' => $payments, 'family' => $family, 'reports' => $reports, 'periods' => $periods, 'timetable' => $timetable,
            'teachers' => $teachers, 'transport' => $transport,
            'quizzes' => db()->all(
                "SELECT q.title, s.name AS subject, a.score, a.max_score, a.submitted_at FROM quiz_attempts a JOIN quizzes q ON q.id = a.quiz_id
                 JOIN class_subjects cs ON cs.id = q.class_subject_id JOIN subjects s ON s.id = cs.subject_id
                 WHERE a.pupil_id = ? AND a.submitted_at IS NOT NULL ORDER BY a.submitted_at DESC LIMIT 10",
                [$id]
            ),
        ]);
    }

    // ---------------------------------------------------------------- helpers

    /** Canteen wallet, meal plan, recent purchases and today's menu for a child (empty before migration 018). */
    private function canteen(int $pupilId): ?array
    {
        try {
            $menu = Canteen::menu(date('Y-m-d'), date('Y-m-d'))[date('Y-m-d')] ?? [];
            return [
                'balance' => db()->value('SELECT balance FROM canteen_wallets WHERE pupil_id = ?', [$pupilId]),
                'plan' => Canteen::planOf($pupilId, (int) (current_term()['id'] ?? 0)),
                'recent' => db()->all("SELECT s.created_at, s.total, GROUP_CONCAT(CONCAT(l.qty, '× ', l.name) SEPARATOR ', ') AS items FROM canteen_sales s JOIN canteen_sale_lines l ON l.sale_id = s.id
                                       WHERE s.pupil_id = ? AND s.status = 'completed' GROUP BY s.id ORDER BY s.id DESC LIMIT 5", [$pupilId]),
                'menu' => $menu,
            ];
        } catch (PDOException $e) {
            return null;
        }
    }

    private function summary(array $c, ?array $term): array
    {
        $att = $term ? db()->one(
            "SELECT SUM(status IN ('present','late')) AS present, COUNT(*) AS marked FROM attendance_records WHERE pupil_id = ? AND date BETWEEN ? AND ?",
            [$c['id'], $term['start_date'], $term['end_date']]
        ) : null;
        $owing = db()->one("SELECT public_key, invoice_no FROM invoices WHERE pupil_id = ? AND status IN ('unpaid','part') ORDER BY due_date, id LIMIT 1", [$c['id']]);
        $latest = db()->one(
            'SELECT t.id, t.name, y.name AS year_name FROM enrolments e JOIN terms t ON t.academic_year_id = e.academic_year_id
             JOIN academic_years y ON y.id = t.academic_year_id JOIN report_releases rr ON rr.term_id = t.id AND rr.class_id = e.class_id
             WHERE e.pupil_id = ? ORDER BY t.start_date DESC LIMIT 1',
            [$c['id']]
        );
        return [
            'att_present' => (int) ($att['present'] ?? 0), 'att_marked' => (int) ($att['marked'] ?? 0),
            'balance' => Fees::pupilBalance((int) $c['id']), 'owing' => $owing,
            'latest_report' => $latest ? $latest + ['token' => $this->reportToken((int) $latest['id'], (int) $c['id'])] : null,
        ];
    }

    private function reportToken(int $termId, int $pupilId): string
    {
        $t = db()->value('SELECT token FROM report_links WHERE term_id = ? AND pupil_id = ?', [$termId, $pupilId]);
        if (!$t) {
            $t = bin2hex(random_bytes(12));
            db()->run('INSERT IGNORE INTO report_links (token, term_id, pupil_id) VALUES (?, ?, ?)', [$t, $termId, $pupilId]);
            $t = db()->value('SELECT token FROM report_links WHERE term_id = ? AND pupil_id = ?', [$termId, $pupilId]);
        }

        return (string) $t;
    }

    private function previewGuardian(): array
    {
        $gid = (int) ($_GET['guardian'] ?? 0);
        $q = trim((string) ($_GET['q'] ?? ''));
        $guardian = $gid ? db()->one('SELECT * FROM guardians WHERE id = ?', [$gid]) : null;
        $sql = "SELECT g.id, g.name, g.phone, GROUP_CONCAT(CONCAT(p.first_name, ' ', p.last_name) ORDER BY p.first_name SEPARATOR ', ') AS children
                FROM guardians g JOIN pupil_guardian pg ON pg.guardian_id = g.id JOIN pupils p ON p.id = pg.pupil_id AND p.deleted_at IS NULL AND p.status = 'active'";
        $params = [];
        if ($q !== '') {
            $sql .= ' WHERE g.name LIKE ? OR g.phone LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR p.admission_no LIKE ?';
            array_push($params, "%$q%", "%$q%", "%$q%", "%$q%", "%$q%");
        }
        $list = $guardian ? [] : db()->all($sql . ' GROUP BY g.id ORDER BY g.name LIMIT 50', $params);
        return ['guardian' => $guardian, 'list' => $list, 'q' => $q];
    }

    private function child_(int $id): array
    {
        if (Auth::role() === 'super_admin') {
            return Accounts::pupilById($id) ?? $this->notFound();
        }
        foreach (Accounts::childrenOf((int) Auth::id()) as $c) {
            if ((int) $c['id'] === $id) {
                return $c;
            }
        }
        $this->notFound();
        return []; // not reached: notFound() stops the request
    }
}
