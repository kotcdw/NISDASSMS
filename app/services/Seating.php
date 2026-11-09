<?php
declare(strict_types=1);

/** Class and exam seating plans: a rows × columns grid filled automatically, then adjusted by hand. */
final class Seating
{
    public const METHODS = [
        'alpha'  => 'Alphabetical (by surname)',
        'mixed'  => 'Boys and girls mixed',
        'random' => 'Random',
    ];

    public static function plan(int $id): ?array
    {
        return db()->one(
            'SELECT sp.*, c.name AS class_name, c.class_teacher_id, r.name AS room_name, r.capacity AS room_capacity, es.name AS series_name
             FROM seating_plans sp LEFT JOIN classes c ON c.id = sp.class_id LEFT JOIN rooms r ON r.id = sp.room_id
             LEFT JOIN exam_series es ON es.id = sp.series_id WHERE sp.id = ?',
            [$id]
        );
    }

    /** Class ids a plan seats. */
    public static function classIds(array $plan): array
    {
        if ($plan['kind'] === 'class') {
            return [(int) $plan['class_id']];
        }
        return array_values(array_filter(array_map('intval', explode(',', (string) $plan['class_list']))));
    }

    /** Pupils (current year) of the given classes, sorted by class then surname: id => row */
    public static function pupils(array $classIds): array
    {
        if (!$classIds) {
            return [];
        }
        $y = current_year();
        $in = implode(',', array_fill(0, count($classIds), '?'));
        $out = [];
        foreach (db()->all(
            "SELECT p.id, p.first_name, p.last_name, p.gender, p.admission_no, e.class_id, c.name AS class_name
             FROM enrolments e JOIN pupils p ON p.id = e.pupil_id JOIN classes c ON c.id = e.class_id
             WHERE e.academic_year_id = ? AND e.class_id IN ($in) AND e.status IN ('active','promoted','repeated') AND p.deleted_at IS NULL AND p.status = 'active'
             ORDER BY FIELD(e.class_id, $in), p.last_name, p.first_name",
            array_merge([$y['id'] ?? 0], $classIds, $classIds)
        ) as $p) {
            $out[(int) $p['id']] = $p;
        }
        return $out;
    }

    /** row-col => pupil id */
    public static function seats(int $planId): array
    {
        $out = [];
        foreach (db()->all('SELECT row_no, col_no, pupil_id FROM seating_seats WHERE plan_id = ?', [$planId]) as $s) {
            $out[$s['row_no'] . '-' . $s['col_no']] = (int) $s['pupil_id'];
        }
        return $out;
    }

    /**
     * Fill the grid. Exam plans (several classes) put a different class in each neighbouring seat.
     * @return array<string,int> row-col => pupil id (pupils who do not fit are left out)
     */
    public static function arrange(array $pupils, int $rows, int $cols, string $method, bool $exam): array
    {
        $list = array_values($pupils);
        $seatsOrder = [];
        for ($r = 1; $r <= $rows; $r++) {
            for ($c = 1; $c <= $cols; $c++) {
                $seatsOrder[] = [$r, $c];
            }
        }
        $map = [];
        if ($exam) {
            $queues = [];
            foreach ($list as $p) {
                $queues[(int) $p['class_id']][] = (int) $p['id'];
            }
            if ($method === 'random') {
                foreach ($queues as &$q) {
                    shuffle($q);
                }
                unset($q);
            }
            $keys = array_keys($queues);
            $k = count($keys);
            foreach ($seatsOrder as [$r, $c]) {
                $want = $k ? ($r + $c) % $k : 0;
                for ($t = 0; $t < $k; $t++) {
                    $key = $keys[($want + $t) % $k];
                    if (!empty($queues[$key])) {
                        $map["$r-$c"] = array_shift($queues[$key]);
                        break;
                    }
                }
            }
            return $map;
        }
        if ($method === 'random') {
            shuffle($list);
        }
        if ($method === 'mixed') {
            $boys = array_values(array_filter($list, fn($p) => $p['gender'] === 'male'));
            $girls = array_values(array_filter($list, fn($p) => $p['gender'] !== 'male'));
            foreach ($seatsOrder as [$r, $c]) {
                $first = ($r + $c) % 2 === 0 ? 'boys' : 'girls';
                if ($first === 'boys' ? $boys : $girls) {
                    $p = $first === 'boys' ? array_shift($boys) : array_shift($girls);
                } elseif ($boys || $girls) {
                    $p = $boys ? array_shift($boys) : array_shift($girls);
                } else {
                    break;
                }
                $map["$r-$c"] = (int) $p['id'];
            }
            return $map;
        }
        foreach ($seatsOrder as $i => [$r, $c]) {
            if (!isset($list[$i])) {
                break;
            }
            $map["$r-$c"] = (int) $list[$i]['id'];
        }
        return $map;
    }

    public static function save(int $planId, array $map): void
    {
        db()->transaction(function (Database $d) use ($planId, $map) {
            $d->run('DELETE FROM seating_seats WHERE plan_id = ?', [$planId]);
            foreach ($map as $key => $pid) {
                [$r, $c] = array_map('intval', explode('-', (string) $key));
                $d->insert('seating_seats', ['plan_id' => $planId, 'row_no' => $r, 'col_no' => $c, 'pupil_id' => (int) $pid]);
            }
        });
    }

    /** Short display name: "Mensah, Ama" */
    public static function name(array $p): string
    {
        return $p['last_name'] . ', ' . $p['first_name'];
    }

    /** Suggested grid for a number of pupils (about 6 columns). */
    public static function suggest(int $n): array
    {
        $cols = $n > 36 ? 8 : 6;
        return [max(1, (int) ceil(max(1, $n) / $cols)), $cols];
    }
}
