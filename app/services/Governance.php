<?php
declare(strict_types=1);

/**
 * Governance & risk: board meetings with attendance, the policy register and the risk register.
 * All Board-view figures are calculated from these records automatically; reminders are sent by the scheduler.
 */
final class Governance
{
    public const MEETING_KINDS = ['regular' => 'Regular board meeting', 'special' => 'Special meeting', 'agm' => 'Annual general meeting', 'committee' => 'Committee meeting'];
    public const ATTEND = ['present' => ['Present', 'good'], 'apology' => ['Apology', 'warn'], 'absent' => ['Absent', 'bad']];
    public const POLICY_CATEGORIES = ['Safeguarding & child protection', 'Academic', 'Finance', 'Human resources', 'Health & safety', 'Admissions', 'Discipline & conduct', 'Data protection', 'ICT', 'Spiritual life', 'Other'];
    public const RISK_CATEGORIES = ['strategic' => 'Strategic', 'financial' => 'Financial', 'operational' => 'Operational', 'safeguarding' => 'Safeguarding', 'health_safety' => 'Health & safety', 'compliance' => 'Regulatory / compliance', 'reputational' => 'Reputational', 'ict' => 'ICT & data'];
    public const MITIGATION = ['not_started' => ['Not started', 'bad'], 'in_progress' => ['In progress', 'warn'], 'in_place' => ['In place', 'good']];
    public const REVIEW_DUE_DAYS = 30;

    /** Risk score (likelihood × impact, 1–25) and its level. */
    public static function level(int $score): array
    {
        return $score >= 15 ? ['High', 'bad'] : ($score >= 8 ? ['Medium', 'warn'] : ['Low', 'good']);
    }

    /** Board-view figures for the academic year of $term (or the last 12 months). Never throws. */
    public static function summary(?array $term = null): array
    {
        $empty = ['ready' => false, 'attendance' => null, 'meetings' => 0, 'next' => null, 'pol_total' => 0, 'pol_ok' => 0, 'pol_pct' => null, 'pol_overdue' => 0, 'pol_soon' => 0,
            'risk_open' => 0, 'risk_high' => 0, 'risk_index' => null, 'risk_overdue' => 0, 'confidence' => null, 'members' => 0];
        try {
            $year = $term ? db()->one('SELECT start_date, end_date FROM academic_years WHERE id = ?', [$term['academic_year_id']]) : null;
            $from = $year['start_date'] ?? date('Y-m-d', strtotime('-1 year'));
            $to = min($year['end_date'] ?? date('Y-m-d'), date('Y-m-d'));
            $a = db()->one(
                "SELECT SUM(a.status = 'present') AS p, COUNT(a.member_id) AS n, COUNT(DISTINCT m.id) AS meetings
                 FROM board_meetings m LEFT JOIN board_attendance a ON a.meeting_id = m.id WHERE m.meeting_date BETWEEN ? AND ?",
                [$from, $to]
            );
            $p = db()->one(
                "SELECT COUNT(*) AS n, SUM(next_review >= CURDATE()) AS ok, SUM(next_review < CURDATE()) AS overdue,
                        SUM(next_review BETWEEN CURDATE() AND CURDATE() + INTERVAL " . self::REVIEW_DUE_DAYS . " DAY) AS soon
                 FROM school_policies WHERE status = 'active'"
            );
            $r = db()->one(
                "SELECT COUNT(*) AS n, SUM(likelihood * impact >= 15) AS high, SUM(mitigation_status = 'in_place') AS done,
                        SUM(mitigation_status = 'in_progress') AS doing, SUM(review_date IS NOT NULL AND review_date < CURDATE()) AS overdue
                 FROM risks WHERE status = 'open'"
            );
        } catch (PDOException $e) {
            return $empty; // migration 024 not applied yet
        }
        $index = (int) $r['n'] ? round(((int) $r['done'] + 0.5 * (int) $r['doing']) * 100 / (int) $r['n'], 1) : null;
        return [
            'ready' => true,
            'attendance' => (int) $a['n'] ? round($a['p'] * 100 / $a['n'], 1) : null,
            'meetings' => (int) $a['meetings'],
            'next' => db()->one('SELECT * FROM board_meetings WHERE meeting_date >= CURDATE() ORDER BY meeting_date, start_time LIMIT 1'),
            'pol_total' => (int) $p['n'], 'pol_ok' => (int) $p['ok'], 'pol_overdue' => (int) $p['overdue'], 'pol_soon' => (int) $p['soon'],
            'pol_pct' => (int) $p['n'] ? round($p['ok'] * 100 / $p['n'], 1) : null,
            'risk_open' => (int) $r['n'], 'risk_high' => (int) $r['high'], 'risk_index' => $index, 'risk_overdue' => (int) $r['overdue'],
            'confidence' => $index === null ? null : ($index >= 75 && !(int) $r['overdue'] ? 'High' : ($index >= 50 ? 'Medium' : 'Low')),
            'members' => (int) db()->value('SELECT COUNT(*) FROM board_members WHERE is_active = 1'),
        ];
    }

    /** Daily: tell the governance team about policy reviews and risk reviews falling due, and board members about meetings. */
    public static function reminders(): int
    {
        try {
            if (setting('governance_last_check') === date('Y-m-d')) {
                return 0;
            }
            setting_set('governance_last_check', date('Y-m-d'));
            $n = 0;
            foreach (db()->all("SELECT * FROM school_policies WHERE status = 'active' AND next_review <= CURDATE() + INTERVAL " . self::REVIEW_DUE_DAYS . " DAY
                                AND (reminded_at IS NULL OR reminded_at < next_review - INTERVAL " . self::REVIEW_DUE_DAYS . " DAY)") as $p) {
                db()->update('school_policies', ['reminded_at' => date('Y-m-d H:i:s')], 'id = ?', [$p['id']]);
                Notifier::inappPermission('governance.manage', 'Policy review ' . ($p['next_review'] < date('Y-m-d') ? 'overdue' : 'due') . ': ' . $p['title'], 'Review date ' . fmt_date($p['next_review']) . '.', url('governance/policies'));
                $n++;
            }
            foreach (db()->all("SELECT * FROM risks WHERE status = 'open' AND review_date IS NOT NULL AND review_date <= CURDATE() + INTERVAL 7 DAY
                                AND (reminded_at IS NULL OR reminded_at < review_date - INTERVAL 7 DAY)") as $r) {
                db()->update('risks', ['reminded_at' => date('Y-m-d H:i:s')], 'id = ?', [$r['id']]);
                Notifier::inappPermission('governance.manage', 'Risk review due: ' . $r['ref_no'] . ' ' . $r['title'], 'Review date ' . fmt_date($r['review_date']) . '.', url('governance/risks/' . $r['id']));
                $n++;
            }
            foreach (db()->all('SELECT * FROM board_meetings WHERE reminded_at IS NULL AND meeting_date BETWEEN CURDATE() AND CURDATE() + INTERVAL 2 DAY') as $m) {
                db()->update('board_meetings', ['reminded_at' => date('Y-m-d H:i:s')], 'id = ?', [$m['id']]);
                $when = fmt_date($m['meeting_date'], 'D j M') . ($m['start_time'] ? ' at ' . substr($m['start_time'], 0, 5) : '');
                foreach (db()->all("SELECT name, phone FROM board_members WHERE is_active = 1 AND phone IS NOT NULL AND phone <> ''") as $b) {
                    Notifier::sms($b['phone'], 'Dear ' . $b['name'] . ', reminder: ' . $m['title'] . ', ' . $when . ($m['venue'] ? ', ' . $m['venue'] : '') . '. - ' . school_name(), ['name' => $b['name'], 'entity' => 'board_meeting', 'entity_id' => $m['id']]);
                }
                Notifier::inappPermission('governance.manage', 'Board meeting ' . $when, $m['title'], url('governance/meetings/' . $m['id']));
                $n++;
            }
            return $n;
        } catch (PDOException $e) {
            return 0; // migration 024 not applied yet
        }
    }
}
