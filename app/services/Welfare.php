<?php
declare(strict_types=1);

/**
 * Pupil welfare: positive-discipline behaviour log (GES banned corporal punishment; the actions offered are the
 * Positive Discipline alternatives), and welfare cases — counselling, safeguarding, accidents/incidents, persistent
 * absence and repeated behaviour concerns — with automatic case opening, follow-up reminders and Board figures.
 */
final class Welfare
{
    public const MERITS = ['Kindness / helping others', 'Good effort in class', 'Excellent work', 'Leadership', 'Punctuality', 'Honesty', 'Good conduct at worship', 'Sports / clubs', 'Other'];
    public const CONCERNS = ['Lateness', 'Disrupting lessons', 'Homework not done', 'Rudeness / disrespect', 'Fighting', 'Bullying', 'Dishonesty', 'Uniform / appearance', 'Damage to property', 'Leaving school without permission', 'Other'];
    /** Positive-discipline responses (no corporal punishment). */
    public const ACTIONS = [
        'reminder' => 'Verbal reminder', 'conversation' => 'Restorative conversation', 'privilege' => 'Loss of a privilege', 'task' => 'Helpful task in school',
        'study' => 'Supervised study time', 'parent' => 'Parent / guardian meeting', 'counselling' => 'Referred to counselling', 'head' => 'Referred to the Head',
    ];
    public const KINDS = [
        'counselling' => ['Counselling', 'info'], 'incident' => ['Accident / incident', 'warn'], 'absence' => ['Persistent absence', 'warn'],
        'behaviour' => ['Repeated behaviour concerns', 'warn'], 'safeguarding' => ['Safeguarding', 'bad'],
    ];
    public const STATUS = ['open' => ['Open', 'warn'], 'monitoring' => ['Monitoring', 'info'], 'closed' => ['Closed', 'good']];
    public const RISK = ['low' => ['Low', 'good'], 'medium' => ['Medium', 'warn'], 'high' => ['High', 'bad']];
    public const REFERRALS = ['School counsellor', 'Parent / guardian', 'GES Guidance & Counselling unit', 'Department of Social Welfare', 'DOVVSU (Police)', 'Health facility', 'Other'];
    public const ABSENCE_LIMIT = 3;   // absences in the last 7 days that open a follow-up case
    public const CONCERN_LIMIT = 3;   // concerns in 30 days that open a case

    /** SQL condition on welfare_cases (alias w) for what this person may see. */
    public static function visible(): array
    {
        $uid = Auth::id();
        if (can('welfare.manage') && can('welfare.safeguarding')) {
            return ['1 = 1', []];
        }
        $parts = [];
        $params = [];
        if (can('welfare.manage')) {
            $parts[] = "w.kind <> 'safeguarding'";
        } elseif (can('welfare.record')) {
            $parts[] = "(w.kind <> 'safeguarding' AND (w.assigned_to = ? OR w.opened_by = ?))";
            array_push($params, $uid, $uid);
        }
        if (can('welfare.safeguarding')) {
            $parts[] = "w.kind = 'safeguarding'";
        } else {
            $parts[] = "(w.kind = 'safeguarding' AND w.opened_by = ?)"; // the person who raised the concern sees its status
            $params[] = $uid;
        }
        return ['(' . implode(' OR ', $parts) . ')', $params];
    }

    public static function canSee(array $case): bool
    {
        [$w, $p] = self::visible();
        return (bool) db()->value("SELECT 1 FROM welfare_cases w WHERE w.id = ? AND $w", array_merge([(int) $case['id']], $p));
    }

    /** Full case details (notes, referral, outcome): not for the reporter of a safeguarding concern. */
    public static function canSeeDetails(array $case): bool
    {
        return $case['kind'] !== 'safeguarding' || can('welfare.safeguarding');
    }

    public static function canWork(array $case): bool
    {
        if ($case['kind'] === 'safeguarding') {
            return can('welfare.safeguarding');
        }
        return can('welfare.manage') || ((int) $case['assigned_to'] === Auth::id() && can('welfare.record'));
    }

    /** Class teacher of the pupil this year (who follows up by default). */
    public static function classTeacher(int $pupilId): ?int
    {
        $v = db()->value("SELECT c.class_teacher_id FROM enrolments e JOIN classes c ON c.id = e.class_id WHERE e.pupil_id = ? AND e.academic_year_id = ? AND e.status = 'active'", [$pupilId, current_year()['id'] ?? 0]);
        return $v ? (int) $v : null;
    }

    public static function openCase(array $d, ?string $autoKey = null): ?int
    {
        try {
            return db()->transaction(function (Database $db) use ($d, $autoKey) {
                if ($autoKey && $db->value('SELECT id FROM welfare_cases WHERE auto_key = ?', [$autoKey])) {
                    return null;
                }
                return $db->insert('welfare_cases', $d + ['ref_no' => Fees::nextNumber($db, 'WEL'), 'auto_key' => $autoKey, 'opened_by' => $d['opened_by'] ?? Auth::id()]);
            });
        } catch (PDOException $e) {
            return null;
        }
    }

    /** Record a merit or concern; tells the parent when asked; three concerns in 30 days open a case. */
    public static function record(array $pupil, array $d, bool $tellParent): int
    {
        $id = db()->insert('behaviour_records', $d + ['pupil_id' => $pupil['id'], 'term_id' => current_term()['id'] ?? null, 'recorded_by' => Auth::id(), 'parent_informed' => $tellParent ? 1 : 0]);
        if ($tellParent) {
            $tpl = $d['kind'] === 'merit' ? 'behaviour_merit' : 'behaviour_concern';
            foreach (self::guardians((int) $pupil['id']) as $g) {
                $body = Notifier::render($tpl, ['guardian' => $g['name'], 'pupil' => $pupil['first_name'], 'category' => $d['category'], 'note' => (string) ($d['description'] ?? ''),
                    'action' => self::ACTIONS[$d['action_taken'] ?? ''] ?? '—', 'school' => school_name()]);
                if ($body) {
                    Notifier::sms($g['phone'], $body, ['name' => $g['name'], 'entity' => 'behaviour', 'entity_id' => $id]);
                }
            }
        }
        if ($d['kind'] === 'concern') {
            $n = (int) db()->value("SELECT COUNT(*) FROM behaviour_records WHERE pupil_id = ? AND kind = 'concern' AND occurred_on >= CURDATE() - INTERVAL 30 DAY", [$pupil['id']]);
            $open = db()->value("SELECT 1 FROM welfare_cases WHERE pupil_id = ? AND kind IN ('behaviour','counselling') AND status <> 'closed'", [$pupil['id']]);
            if ($n >= self::CONCERN_LIMIT && !$open) {
                $cid = self::openCase(['pupil_id' => $pupil['id'], 'kind' => 'behaviour', 'risk' => 'medium', 'title' => $n . ' behaviour concerns in 30 days',
                    'details' => 'Opened automatically after ' . $n . ' concerns. Talk with the student and the parents; consider counselling.',
                    'assigned_to' => self::classTeacher((int) $pupil['id']), 'follow_up_on' => date('Y-m-d', strtotime('+3 days'))], 'behaviour:' . $pupil['id'] . ':' . date('Y-m-d'));
                if ($cid) {
                    Notifier::inappPermission('welfare.manage', 'Behaviour follow-up: ' . full_name($pupil), $n . ' concerns in 30 days — a case was opened.', url('welfare/cases/' . $cid));
                }
            }
        }
        return $id;
    }

    public static function guardians(int $pupilId): array
    {
        return db()->all("SELECT g.name, g.phone FROM pupil_guardian pg JOIN guardians g ON g.id = pg.guardian_id WHERE pg.pupil_id = ? AND g.phone <> '' ORDER BY pg.is_primary DESC LIMIT 2", [$pupilId]);
    }

    /** Daily: open persistent-absence cases and remind people of overdue follow-ups. */
    public static function daily(): int
    {
        try {
            if (setting('welfare_last_check') === date('Y-m-d')) {
                return 0;
            }
            setting_set('welfare_last_check', date('Y-m-d'));
            $n = 0;
            foreach (db()->all(
                "SELECT a.pupil_id, COUNT(*) AS n FROM attendance_records a JOIN pupils p ON p.id = a.pupil_id
                 WHERE a.status = 'absent' AND a.date >= CURDATE() - INTERVAL 7 DAY AND p.status = 'active'
                 GROUP BY a.pupil_id HAVING n >= ?",
                [self::ABSENCE_LIMIT]
            ) as $r) {
                if (db()->value("SELECT 1 FROM welfare_cases WHERE pupil_id = ? AND kind = 'absence' AND status <> 'closed'", [$r['pupil_id']])) {
                    continue;
                }
                $teacher = self::classTeacher((int) $r['pupil_id']);
                $cid = self::openCase(['pupil_id' => $r['pupil_id'], 'kind' => 'absence', 'risk' => (int) $r['n'] >= 5 ? 'high' : 'medium', 'title' => (int) $r['n'] . ' days absent in the last week',
                    'details' => 'Opened automatically. Call the parents, find out why, and record what you learn.', 'assigned_to' => $teacher,
                    'follow_up_on' => date('Y-m-d', strtotime('+2 days')), 'opened_by' => null], 'absence:' . $r['pupil_id'] . ':' . date('o-W'));
                if ($cid) {
                    $p = db()->one('SELECT first_name, last_name FROM pupils WHERE id = ?', [$r['pupil_id']]);
                    if ($teacher) {
                        Notifier::inapp([$teacher], 'Absence follow-up: ' . $p['first_name'] . ' ' . $p['last_name'], (int) $r['n'] . ' days absent in the last week. Please call the parents.', url('welfare/cases/' . $cid));
                    }
                    $n++;
                }
            }
            foreach (db()->all("SELECT * FROM welfare_cases WHERE status <> 'closed' AND follow_up_on < CURDATE() AND assigned_to IS NOT NULL") as $c) {
                Notifier::inapp([(int) $c['assigned_to']], 'Welfare follow-up overdue: ' . $c['ref_no'], $c['title'], url('welfare/cases/' . $c['id']));
                $n++;
            }
            return $n;
        } catch (PDOException $e) {
            return 0; // migration 025 not applied yet
        }
    }

    /** Figures for the Board view and the dashboard (no names). */
    public static function summary(?array $term): array
    {
        $from = $term['start_date'] ?? date('Y-m-d', strtotime('-90 days'));
        try {
            $b = db()->one("SELECT SUM(kind = 'merit') AS merits, SUM(kind = 'concern') AS concerns FROM behaviour_records WHERE occurred_on >= ?", [$from]);
            $c = db()->one(
                "SELECT SUM(status <> 'closed' AND kind <> 'safeguarding') AS open, SUM(status <> 'closed' AND kind = 'safeguarding') AS safeguarding,
                        SUM(kind = 'incident' AND created_at >= ?) AS incidents, SUM(status <> 'closed' AND follow_up_on < CURDATE()) AS overdue,
                        SUM(kind = 'counselling' AND created_at >= ?) AS counselling, SUM(kind = 'absence' AND created_at >= ?) AS absence
                 FROM welfare_cases",
                [$from, $from, $from]
            );
        } catch (PDOException $e) {
            return ['ready' => false];
        }
        $m = (int) $b['merits'];
        $k = (int) $b['concerns'];
        return ['ready' => true, 'merits' => $m, 'concerns' => $k, 'ratio' => $m + $k ? round($m * 100 / ($m + $k)) : null,
            'open' => (int) $c['open'], 'safeguarding' => (int) $c['safeguarding'], 'incidents' => (int) $c['incidents'], 'overdue' => (int) $c['overdue'],
            'counselling' => (int) $c['counselling'], 'absence' => (int) $c['absence']];
    }
}
