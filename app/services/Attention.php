<?php
declare(strict_types=1);

/** "Needs your attention" alerts shown under the warning button in the top bar. */
final class Attention
{
    /** Things waiting for this person, by role: [count, label, route, tone]. Cached for two minutes per sign-in. */
    public static function items(bool $fresh = false): array
    {
        $c = $_SESSION['attention'] ?? null;
        if (!$fresh && is_array($c) && ($c['uid'] ?? 0) === Auth::id() && $c['at'] > time() - 120) {
            return $c['items'];
        }
        $items = self::build();
        $_SESSION['attention'] = ['uid' => Auth::id(), 'at' => time(), 'items' => $items];
        return $items;
    }

    private static function build(): array
    {
        $a = [];
        $add = function (int $n, string $label, string $route, string $tone = 'warn') use (&$a): void {
            if ($n > 0 && can_open($route)) { // only alerts this person can act on
                $a[] = [$n, $label, $route, $tone];
            }
        };
        if (can('security.manage') && !Security::mfaOn()) {
            $add(1, '2-step sign-in is off — review the sign-in policy before go-live', 'security/settings');
        }
        $term = current_term();
        $tid = $term['id'] ?? 0;
        if (can('exams.manage')) {
            $add((int) db()->value("SELECT COUNT(*) FROM score_sheets WHERE term_id = ? AND status = 'submitted'", [$tid]), 'score sheets to approve', 'exams', 'info');
        }
        if ($term && can('lessonnotes.vet')) {
            $add(LessonNotes::waiting((int) $tid), 'lesson notes to vet', 'lesson-notes', 'info');
        }
        if ($term && can('scheme.plan')) {
            try {
                $add(LessonNotes::myDue($term), 'of your lesson notes for this week not submitted', 'lesson-notes', 'warn');
            } catch (PDOException $e) {
                // migration 029 not applied yet
            }
        }
        try {
            if (can('leave.approve')) {
                $own = Auth::classScope() !== null;
                $add((int) db()->value(
                    "SELECT COUNT(*) FROM leave_requests l" . ($own ? " JOIN enrolments e ON e.pupil_id = l.pupil_id AND e.academic_year_id = ? AND e.status = 'active' JOIN classes c ON c.id = e.class_id AND c.class_teacher_id = ?" : '') . " WHERE l.status = 'pending'",
                    $own ? [current_year()['id'] ?? 0, Auth::id()] : []
                ), 'leave requests to approve', 'leave', 'warn');
            }
            if (can('transport.manage')) {
                $add(TransportController::papersDue(), 'bus routes with driver licence / roadworthy / insurance expiring', 'transport', 'bad');
            }
            if (can('canteen.manage')) {
                $add((int) db()->value('SELECT COUNT(*) FROM canteen_items WHERE is_active = 1 AND track_stock = 1 AND stock_qty <= reorder_level'), 'canteen items low on stock', 'canteen/items', 'warn');
            }
            if (can('appointments.offer')) {
                $add((int) db()->value("SELECT COUNT(*) FROM appointments WHERE staff_id = ? AND status = 'booked' AND DATE(start_at) = CURDATE()", [Auth::id()]), 'appointments today', 'appointments', 'info');
            }
            if (can('fees.manage')) {
                $add((int) db()->value("SELECT COUNT(DISTINCT ii.invoice_id) FROM invoice_instalments ii JOIN invoices i ON i.id = ii.invoice_id WHERE i.status IN ('unpaid','part') AND ii.due_date < CURDATE()
                    AND i.paid < (SELECT SUM(x.amount) FROM invoice_instalments x WHERE x.invoice_id = ii.invoice_id AND x.due_date < CURDATE())"), 'payment plans behind schedule', 'fees/debtors', 'bad');
            }
            if (can('assignments.manage')) {
                $add((int) db()->value(
                    "SELECT COUNT(*) FROM assignment_submissions s JOIN assignments a ON a.id = s.assignment_id LEFT JOIN class_subjects cs ON cs.id = a.class_subject_id
                     WHERE s.status IN ('submitted','late') AND (a.created_by = ? OR cs.teacher_id = ?)",
                    [Auth::id(), Auth::id()]
                ), 'handed-in assignments to mark', 'assignments', 'info');
            }
        } catch (PDOException $e) {
            // migration 017 not applied yet
        }
        if (Registers::ready() && (can('staffleave.approve') || can('permits.view') || can('payroll.manage') || can('setup.manage') || can('stores.manage') || can('movement.view'))) {
            $r = Registers::attention();
            if (can('staffleave.approve')) {
                $add($r['leave'], 'staff leave requests to approve', 'staff-leave?status=pending', 'warn');
            }
            if (can('permits.view')) {
                $add($r['permits'], 'licences or permits expired or expiring within 60 days', 'permits', 'bad');
            }
            if (can('payroll.manage') || can('staffleave.approve')) {
                $add($r['contracts'], 'staff contracts or probations ending soon', can('payroll.manage') ? 'payroll/staff' : 'staff-leave', 'warn');
            }
            if (can('setup.manage')) {
                $add($r['requests'], 'data requests due within 3 days or overdue', 'setup/privacy/requests', 'bad');
            }
            if (can('movement.view')) {
                $add($r['outlate'], 'staff out of school past their return time', 'movements', 'warn');
            }
            if (can('stores.manage')) {
                $add((int) db()->value("SELECT COUNT(*) FROM store_items i WHERE i.is_active = 1 AND i.reorder_level > 0 AND i.reorder_level >= (SELECT COALESCE(SUM(CASE m.kind WHEN 'issue' THEN -m.qty ELSE m.qty END), 0) FROM store_moves m WHERE m.item_id = i.id)"), 'store items at or below their re-order level', 'stores', 'info');
            }
        }
        try { // NISDAS APPS (migration 020+)
            if (can('crm.manage')) {
                $add((int) db()->value("SELECT COUNT(*) FROM crm_enquiries WHERE stage IN ('new','contacted','visit_booked','visited') AND next_follow_up <= CURDATE()"), 'enquiry follow-ups due', 'crm?stage=due', 'warn');
            }
            if (can('shop.manage')) {
                $add((int) db()->value("SELECT COUNT(*) FROM shop_orders WHERE status = 'paid'"), 'paid shop orders to prepare', 'shop/admin', 'info');
            }
            if (can('live.manage')) {
                $add((int) db()->value("SELECT COUNT(*) FROM live_sessions WHERE created_by = ? AND status = 'scheduled' AND DATE(starts_at) = CURDATE()", [Auth::id()]), 'live classes today', 'live', 'info');
            }
            if (can('documents.manage')) {
                $add(count(Documents::expiring(Documents::REMIND_DAYS, can('documents.staff'))), 'documents expiring or expired', 'documents/expiring', 'warn');
            }
        } catch (PDOException $e) {
            // migration 020 not applied yet
        }
        try { // Pupil welfare (migration 025)
            if (can('welfare.record')) {
                $add((int) db()->value("SELECT COUNT(*) FROM welfare_cases WHERE assigned_to = ? AND status <> 'closed' AND kind <> 'safeguarding' AND (follow_up_on IS NULL OR follow_up_on <= CURDATE())", [Auth::id()]), 'welfare follow-ups due', 'welfare/cases', 'warn');
            }
            if (can('welfare.safeguarding')) {
                $add((int) db()->value("SELECT COUNT(*) FROM welfare_cases WHERE kind = 'safeguarding' AND status = 'open'"), 'open safeguarding concerns', 'welfare/cases?kind=safeguarding', 'bad');
            }
        } catch (PDOException $e) {
            // migration 025 not applied yet
        }
        try { // Governance (migration 024)
            if (can('governance.manage')) {
                $add((int) db()->value("SELECT COUNT(*) FROM school_policies WHERE status = 'active' AND next_review < CURDATE()"), 'policies overdue for review', 'governance/policies?show=due', 'bad');
                $add((int) db()->value("SELECT COUNT(*) FROM risks WHERE status = 'open' AND review_date IS NOT NULL AND review_date < CURDATE()"), 'risk reviews overdue', 'governance/risks', 'warn');
                $add((int) db()->value('SELECT COUNT(*) FROM board_meetings m WHERE m.meeting_date < CURDATE() AND m.meeting_date >= CURDATE() - INTERVAL 60 DAY AND NOT EXISTS (SELECT 1 FROM board_attendance a WHERE a.meeting_id = m.id)'), 'board meetings without attendance recorded', 'governance/meetings', 'info');
            }
        } catch (PDOException $e) {
            // migration 024 not applied yet
        }
        if (can('admission.manage')) {
            $add((int) db()->value("SELECT COUNT(*) FROM applications WHERE stage = 'submitted'"), 'new admission applications', 'admission', 'info');
        }
        if (can('payroll.approve')) {
            $add((int) db()->value("SELECT COUNT(*) FROM payroll_runs WHERE status = 'draft' AND (created_by IS NULL OR created_by <> ?)", [Auth::id()]), 'payroll to approve', 'payroll', 'info');
        }
        if (can('payroll.manage')) {
            $add((int) db()->value("SELECT COUNT(*) FROM payroll_runs WHERE status = 'approved'"), 'approved payroll to pay', 'payroll', 'info');
            $add((int) db()->value("SELECT COUNT(*) FROM payroll_runs WHERE status = 'paid' AND statutory_paid_on IS NULL AND (paye + ssnit_employee + ssnit_employer) > 0 AND period < DATE_FORMAT(CURDATE(), '%Y-%m')"), 'SSNIT/PAYE payments to record', 'payroll');
        }
        if (can('payroll.manage') || can('teachers.view')) {
            $add(Payroll::ntcProblems(), 'teachers with a missing or expiring NTC licence', can('payroll.manage') ? 'payroll/staff' : 'teachers');
        }
        if (can('fees.view')) {
            $add((int) db()->value("SELECT COUNT(*) FROM invoices WHERE status IN ('unpaid','part') AND due_date < CURDATE()"), 'overdue bills', 'fees/debtors');
        }
        if (can('chat.use')) {
            $add((int) db()->value(
                'SELECT COUNT(DISTINCT m.conversation_id) FROM conversation_members cm JOIN conversation_messages m ON m.conversation_id = cm.conversation_id
                 WHERE cm.user_id = ? AND m.user_id <> ? AND (cm.last_read_at IS NULL OR m.created_at > cm.last_read_at)',
                [Auth::id(), Auth::id()]
            ), 'unread parent conversations', 'conversations', 'info');
        }
        if (can('library.manage')) {
            $add((int) db()->value("SELECT COUNT(*) FROM library_loans WHERE status = 'out' AND due_on < CURDATE()"), 'overdue library books', 'library');
        }
        if (can('health.view')) {
            $add((int) db()->value('SELECT COUNT(*) FROM health_visits WHERE DATE(seen_at) = CURDATE()'), 'sick bay visits today', 'health', 'info');
        }
        if (can('messages.send')) {
            $add((int) db()->value("SELECT COUNT(*) FROM notification_outbox WHERE status = 'failed' AND created_at > (NOW() - INTERVAL 7 DAY)"), 'SMS failed this week', 'messages/outbox', 'bad');
        }
        if (can('pupils.manage')) {
            $add((int) db()->value("SELECT COUNT(*) FROM pupils p WHERE p.status = 'active' AND p.deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM pupil_guardian pg JOIN guardians g ON g.id = pg.guardian_id WHERE pg.pupil_id = p.id AND g.phone <> '')"), 'students without a parent phone', 'pupils');
        }
        if (can('backups.manage')) {
            $last = db()->value('SELECT created_at FROM backups ORDER BY id DESC LIMIT 1');
            if (!$last || strtotime($last . ' UTC') < time() - 2 * 86400) {
                $a[] = [1, $last ? 'backup older than 2 days' : 'no backup made yet', 'backups', 'bad'];
            }
        }
        return $a;
    }
}
