<?php
declare(strict_types=1);

/** Rooms and the resources in them (furniture, textbooks, equipment), with shortfalls against class size. */
final class Resources
{
    public const KINDS = ['classroom' => 'Classroom', 'lab' => 'Lab / ICT room', 'hall' => 'Hall', 'library' => 'Library', 'office' => 'Office', 'store' => 'Store', 'other' => 'Other'];
    public const CATEGORIES = ['furniture' => 'Furniture', 'textbook' => 'Textbook', 'equipment' => 'Equipment', 'teaching_aid' => 'Teaching aid', 'ict' => 'ICT', 'other' => 'Other'];

    /** Pupils on roll this year per class: class_id => count */
    public static function rolls(): array
    {
        $y = current_year();
        $out = [];
        foreach (db()->all(
            "SELECT e.class_id, COUNT(*) AS n FROM enrolments e JOIN pupils p ON p.id = e.pupil_id
             WHERE e.academic_year_id = ? AND e.status IN ('active','promoted','repeated') AND p.deleted_at IS NULL AND p.status = 'active' GROUP BY e.class_id",
            [$y['id'] ?? 0]
        ) as $r) {
            $out[(int) $r['class_id']] = (int) $r['n'];
        }
        return $out;
    }

    public static function rooms(bool $activeOnly = true): array
    {
        return db()->all(
            'SELECT r.*, c.name AS class_name, (SELECT COUNT(*) FROM resource_items i WHERE i.room_id = r.id) AS items
             FROM rooms r LEFT JOIN classes c ON c.id = r.class_id' . ($activeOnly ? ' WHERE r.is_active = 1' : '') . ' ORDER BY r.kind = \'classroom\' DESC, r.name'
        );
    }

    /** How many are needed: per pupil × roll of the room's class, else the fixed number. */
    public static function needed(array $item, int $roll): ?int
    {
        if ($item['per_pupil'] !== null && (float) $item['per_pupil'] > 0) {
            return $roll > 0 ? (int) ceil($roll * (float) $item['per_pupil']) : null;
        }
        return $item['needed'] !== null ? (int) $item['needed'] : null;
    }

    public static function shortfall(array $item, int $roll): int
    {
        $n = self::needed($item, $roll);
        return $n === null ? 0 : max(0, $n - (int) $item['qty_good']);
    }

    /** Items for a room (or all rooms) with needed/shortfall worked out. */
    public static function items(?int $roomId = null): array
    {
        $rolls = self::rolls();
        $rows = db()->all(
            'SELECT i.*, r.name AS room_name, r.class_id, c.name AS class_name, s.name AS subject_name
             FROM resource_items i JOIN rooms r ON r.id = i.room_id LEFT JOIN classes c ON c.id = r.class_id LEFT JOIN subjects s ON s.id = i.subject_id
             WHERE r.is_active = 1' . ($roomId ? ' AND r.id = ?' : '') . ' ORDER BY r.name, FIELD(i.category, \'furniture\', \'textbook\', \'equipment\', \'teaching_aid\', \'ict\', \'other\'), i.name',
            $roomId ? [$roomId] : []
        );
        foreach ($rows as &$r) {
            $roll = $r['class_id'] ? ($rolls[(int) $r['class_id']] ?? 0) : 0;
            $r['roll'] = $roll;
            $r['need'] = self::needed($r, $roll);
            $r['short'] = self::shortfall($r, $roll);
        }
        unset($r);
        return $rows;
    }

    /** Standard items for a classroom: desks and chairs per pupil, teacher's furniture, board, and a textbook per pupil for each subject. */
    public static function standardItems(array $room): array
    {
        $items = [
            ['furniture', 'Student desks', null, 1.0, null],
            ['furniture', 'Student chairs', null, 1.0, null],
            ['furniture', "Teacher's table", null, null, 1],
            ['furniture', "Teacher's chair", null, null, 1],
            ['furniture', 'Cupboard / shelf', null, null, 1],
            ['teaching_aid', 'Whiteboard / chalkboard', null, null, 1],
        ];
        if ($room['class_id']) {
            foreach (db()->all('SELECT s.id, s.name FROM class_subjects cs JOIN subjects s ON s.id = cs.subject_id WHERE cs.class_id = ? ORDER BY s.is_core DESC, s.name', [$room['class_id']]) as $s) {
                $items[] = ['textbook', $s['name'] . ' textbooks', (int) $s['id'], 1.0, null];
            }
        }
        return $items;
    }
}
