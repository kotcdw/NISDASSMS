<?php
declare(strict_types=1);

/**
 * Weekly lesson notes (GES/NaCCA format): the teacher writes one note per class subject per week, submits it,
 * and the Head or Academic Coordinator vets it (approve, or return with a comment). Notes not submitted by the
 * week they are for are "late".
 */
final class LessonNotes
{
    public const STATUS = [
        'none' => ['Not written', 'neutral'], 'draft' => ['Draft', 'warn'], 'submitted' => ['Waiting for vetting', 'info'],
        'approved' => ['Approved', 'good'], 'returned' => ['Returned', 'bad'],
    ];
    public const FIELDS = ['topic', 'content_standard', 'indicators', 'core_competencies', 'resources', 'starter', 'main_activities', 'plenary', 'assessment', 'references_text', 'reflection'];
    public const COMPETENCIES = ['Critical thinking & problem solving', 'Creativity & innovation', 'Communication & collaboration', 'Cultural identity & global citizenship', 'Personal development & leadership', 'Digital literacy'];

    public static function ready(): bool
    {
        try {
            db()->value('SELECT 1 FROM lesson_notes LIMIT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    public static function note(int $id): ?array
    {
        return db()->one(
            'SELECT n.*, c.id AS class_id, c.name AS class_name, c.class_teacher_id, l.category, s.id AS subject_id, s.name AS subject_name,
                    cs.teacher_id, u.name AS teacher_name, r.name AS reviewer_name, t.name AS term_name, t.start_date AS term_start, y.name AS year_name,
                    st.name AS strand_name
             FROM lesson_notes n JOIN class_subjects cs ON cs.id = n.class_subject_id JOIN classes c ON c.id = cs.class_id
             JOIN levels l ON l.id = c.level_id JOIN subjects s ON s.id = cs.subject_id LEFT JOIN users u ON u.id = cs.teacher_id
             LEFT JOIN users r ON r.id = n.reviewed_by JOIN terms t ON t.id = n.term_id JOIN academic_years y ON y.id = t.academic_year_id
             LEFT JOIN curriculum_strands st ON st.id = n.strand_id WHERE n.id = ?',
            [$id]
        );
    }

    /** Monday–Friday dates of a week of the term, for headings ("Week 3 · 22–26 Sep"). */
    public static function weekRange(array $term, int $week): string
    {
        $start = strtotime($term['start_date'] . ' +' . (($week - 1) * 7) . ' days');
        $mon = strtotime('monday this week', $start);
        $fri = strtotime('+4 days', $mon);
        return date('j M', $mon) . ' – ' . date('j M', $fri);
    }

    /** Class subjects this person writes notes for (own subjects; class teachers their class), or all for vetters. */
    public static function classSubjects(?int $classId = null, bool $all = false): array
    {
        $where = 'c.is_active = 1';
        $p = [];
        if (!$all) {
            $where .= ' AND (cs.teacher_id = ? OR (cs.teacher_id IS NULL AND c.class_teacher_id = ?))';
            array_push($p, Auth::id(), Auth::id());
        }
        if ($classId) {
            $where .= ' AND c.id = ?';
            $p[] = $classId;
        }
        return db()->all(
            "SELECT cs.id, cs.teacher_id, c.id AS class_id, c.name AS class_name, c.class_teacher_id, s.name AS subject_name, u.name AS teacher_name
             FROM class_subjects cs JOIN classes c ON c.id = cs.class_id JOIN levels l ON l.id = c.level_id
             JOIN subjects s ON s.id = cs.subject_id LEFT JOIN users u ON u.id = cs.teacher_id
             WHERE $where ORDER BY l.sort_order, c.name, s.is_core DESC, s.name",
            $p
        );
    }

    /** May the signed-in person write / edit this note's class subject? */
    public static function canWrite(array $cs): bool
    {
        $me = (int) Auth::id();
        return can('scheme.plan') && ((int) ($cs['teacher_id'] ?? 0) === $me || (!$cs['teacher_id'] && (int) ($cs['class_teacher_id'] ?? 0) === $me));
    }

    public static function canSee(array $cs): bool
    {
        return can('lessonnotes.vet') || self::canWrite($cs) || (int) ($cs['class_teacher_id'] ?? 0) === (int) Auth::id();
    }

    /** Topics planned in the scheme of work for that week (to start a note from). */
    public static function plannedTopics(int $termId, int $csId, int $week): array
    {
        return db()->all('SELECT si.topic, si.strand_id, si.indicators, si.resources FROM scheme_items si WHERE si.term_id = ? AND si.class_subject_id = ? AND si.week_no = ? ORDER BY si.id', [$termId, $csId, $week]);
    }

    /** Submitted notes waiting for vetting in a term. */
    public static function waiting(int $termId): int
    {
        return self::ready() ? (int) db()->value("SELECT COUNT(*) FROM lesson_notes WHERE term_id = ? AND status = 'submitted'", [$termId]) : 0;
    }

    /** Own class subjects with no submitted / approved note for the current week. */
    public static function myDue(array $term): int
    {
        $week = Curriculum::currentWeek($term);
        if (!$week || !self::ready()) {
            return 0;
        }
        $n = 0;
        foreach (self::classSubjects() as $cs) {
            $st = db()->value('SELECT status FROM lesson_notes WHERE term_id = ? AND class_subject_id = ? AND week_no = ?', [$term['id'], $cs['id'], $week]);
            if (!in_array($st, ['submitted', 'approved'], true)) {
                $n++;
            }
        }
        return $n;
    }

    /** Share of class subjects with an approved note, per class subject id, for weeks 1..$upTo. */
    public static function approvedShare(int $termId, int $upTo): array
    {
        if (!self::ready() || $upTo < 1) {
            return [];
        }
        $out = [];
        foreach (db()->all("SELECT class_subject_id, COUNT(*) AS n FROM lesson_notes WHERE term_id = ? AND status = 'approved' AND week_no <= ? GROUP BY class_subject_id", [$termId, $upTo]) as $r) {
            $out[(int) $r['class_subject_id']] = min(100, round($r['n'] * 100 / $upTo));
        }
        return $out;
    }
}
