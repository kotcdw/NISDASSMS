<?php
declare(strict_types=1);

/** Homework and class assignments: set → hand in (online or on paper) → mark → return. */
final class Assignments
{
    public const SUB_STATUS = [
        'submitted' => ['Handed in', 'info'],
        'late'      => ['Handed in late', 'warn'],
        'marked'    => ['Marked', 'good'],
        'returned'  => ['Returned', 'good'],
        'missing'   => ['Not handed in', 'bad'],
    ];
    public const STATUS = ['draft' => ['Draft', 'neutral'], 'published' => ['Open', 'info'], 'closed' => ['Closed', 'neutral']];

    /** Class-subjects this user may set work for: [id => label], grouped by class. */
    public static function choices(): array
    {
        $sql = 'SELECT cs.id, cs.class_id, c.name AS class_name, s.name AS subject_name FROM class_subjects cs
                JOIN classes c ON c.id = cs.class_id JOIN levels l ON l.id = c.level_id JOIN subjects s ON s.id = cs.subject_id
                WHERE c.is_active = 1';
        $p = [];
        if (Auth::classScope() !== null) {
            $sql .= ' AND (cs.teacher_id = ? OR c.class_teacher_id = ?)';
            $p = [Auth::id(), Auth::id()];
        }
        return db()->all($sql . ' ORDER BY l.sort_order, c.name, s.name', $p);
    }

    public static function canManage(array $a): bool
    {
        if (!can('assignments.manage')) {
            return false;
        }
        if (Auth::classScope() === null) {
            return true;
        }
        if ((int) $a['created_by'] === Auth::id()) {
            return true;
        }
        $cs = $a['class_subject_id'] ? db()->one('SELECT cs.teacher_id, c.class_teacher_id FROM class_subjects cs JOIN classes c ON c.id = cs.class_id WHERE cs.id = ?', [$a['class_subject_id']]) : null;
        return $cs && ((int) $cs['teacher_id'] === Auth::id() || (int) $cs['class_teacher_id'] === Auth::id());
    }

    /** Pupils in the class this year. */
    public static function roster(int $classId): array
    {
        return db()->all(
            "SELECT p.id, p.first_name, p.last_name, p.admission_no, p.user_id FROM enrolments e JOIN pupils p ON p.id = e.pupil_id
             WHERE e.class_id = ? AND e.academic_year_id = ? AND e.status = 'active' AND p.deleted_at IS NULL ORDER BY p.last_name, p.first_name",
            [$classId, current_year()['id'] ?? 0]
        );
    }

    public static function find(int $id): ?array
    {
        return db()->one(
            'SELECT a.*, c.name AS class_name, s.name AS subject_name, u.name AS teacher_name FROM assignments a
             JOIN classes c ON c.id = a.class_id LEFT JOIN class_subjects cs ON cs.id = a.class_subject_id
             LEFT JOIN subjects s ON s.id = cs.subject_id LEFT JOIN users u ON u.id = a.created_by WHERE a.id = ?',
            [$id]
        );
    }

    /** Assignments for one pupil's current class (published or closed), newest due first, with their submission. */
    public static function forPupil(int $pupilId, int $limit = 50): array
    {
        $classId = (int) db()->value("SELECT class_id FROM enrolments WHERE pupil_id = ? AND academic_year_id = ? AND status = 'active'", [$pupilId, current_year()['id'] ?? 0]);
        if (!$classId) {
            return [];
        }
        return db()->all(
            "SELECT a.*, s.name AS subject_name, u.name AS teacher_name, sub.id AS sub_id, sub.status AS sub_status, sub.score, sub.feedback, sub.submitted_at
             FROM assignments a LEFT JOIN class_subjects cs ON cs.id = a.class_subject_id LEFT JOIN subjects s ON s.id = cs.subject_id
             LEFT JOIN users u ON u.id = a.created_by LEFT JOIN assignment_submissions sub ON sub.assignment_id = a.id AND sub.pupil_id = ?
             WHERE a.class_id = ? AND a.status <> 'draft' ORDER BY a.due_on DESC, a.id DESC LIMIT " . (int) $limit,
            [$pupilId, $classId]
        );
    }

    /** What a pupil (or their parent) should see as the state of one assignment. */
    public static function pupilState(array $row): array
    {
        if ($row['sub_status'] ?? null) {
            return self::SUB_STATUS[$row['sub_status']];
        }
        if ($row['due_on'] < date('Y-m-d')) {
            return ['Not handed in', 'bad'];
        }
        return [$row['due_on'] === date('Y-m-d') ? 'Due today' : 'To do', 'warn'];
    }

    /** Handed-in / marked counts per assignment id. */
    public static function counts(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $in = implode(',', array_map('intval', $ids));
        $out = [];
        foreach (db()->all("SELECT assignment_id, SUM(status IN ('submitted','late','marked','returned')) AS handed, SUM(status IN ('marked','returned')) AS marked FROM assignment_submissions WHERE assignment_id IN ($in) GROUP BY assignment_id") as $r) {
            $out[(int) $r['assignment_id']] = $r;
        }
        return $out;
    }
}
