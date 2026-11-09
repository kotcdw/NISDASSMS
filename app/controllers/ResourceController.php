<?php
declare(strict_types=1);

/** Rooms & class resources register: rooms, furniture, textbooks and equipment, condition and shortfalls. */
class ResourceController extends Controller
{
    public function index(): void
    {
        $items = Resources::items();
        $rolls = Resources::rolls();
        $rooms = Resources::rooms(false);
        foreach ($rooms as &$r) {
            $r['roll'] = $r['class_id'] ? ($rolls[(int) $r['class_id']] ?? 0) : null;
            $mine = array_filter($items, fn($i) => (int) $i['room_id'] === (int) $r['id']);
            $r['short'] = array_sum(array_column($mine, 'short'));
            $r['repair'] = array_sum(array_column($mine, 'qty_repair')) + array_sum(array_column($mine, 'qty_bad'));
            $desks = array_values(array_filter($mine, fn($i) => stripos($i['name'], 'desk') !== false && $i['category'] === 'furniture'));
            $r['desks'] = $desks ? array_sum(array_column($desks, 'qty_good')) : null;
        }
        unset($r);
        $classesWithout = (int) db()->value('SELECT COUNT(*) FROM classes c WHERE c.is_active = 1 AND NOT EXISTS (SELECT 1 FROM rooms r WHERE r.class_id = c.id)');
        $this->view('resources/index', [
            'title' => 'Rooms & resources', 'rooms' => $rooms, 'classesWithout' => $classesWithout,
            'shortages' => array_values(array_filter($items, fn($i) => $i['short'] > 0)),
            'repairs' => array_values(array_filter($items, fn($i) => (int) $i['qty_repair'] + (int) $i['qty_bad'] > 0)),
            'classes' => $this->classes(),
        ]);
    }

    public function printReport(): void
    {
        $items = Resources::items();
        $this->view('resources/print', [
            'title' => 'Class resources — shortages', 'rooms' => Resources::rooms(),
            'shortages' => array_values(array_filter($items, fn($i) => $i['short'] > 0)),
            'repairs' => array_values(array_filter($items, fn($i) => (int) $i['qty_repair'] + (int) $i['qty_bad'] > 0)),
        ], 'layouts/print');
    }

    public function storeRoom(): void
    {
        $data = $this->roomInput('resources');
        $id = db()->insert('rooms', $data);
        audit('room_created', 'rooms', $id, ['name' => $data['name']]);
        flash('success', 'Room added. Now record what is in it.');
        redirect('resources/rooms/' . $id);
    }

    /** One classroom per class that has none yet (named after the class, capacity from the class). */
    public function roomsForClasses(): void
    {
        $n = 0;
        foreach (db()->all('SELECT c.id, c.name, c.capacity FROM classes c JOIN levels l ON l.id = c.level_id WHERE c.is_active = 1 AND NOT EXISTS (SELECT 1 FROM rooms r WHERE r.class_id = c.id) ORDER BY l.sort_order, c.name') as $c) {
            $name = $c['name'] . ' classroom';
            if (db()->value('SELECT 1 FROM rooms WHERE name = ?', [$name])) {
                continue;
            }
            db()->insert('rooms', ['name' => $name, 'kind' => 'classroom', 'capacity' => (int) $c['capacity'], 'class_id' => $c['id']]);
            $n++;
        }
        audit('rooms_for_classes', 'rooms', null, ['count' => $n]);
        flash('success', $n ? "$n classroom(s) added, one for each class." : 'Every class already has a classroom.');
        redirect('resources');
    }

    public function room(int $id): void
    {
        $room = $this->roomOr404($id);
        $rolls = Resources::rolls();
        $this->view('resources/room', [
            'title' => $room['name'], 'room' => $room, 'items' => Resources::items($id), 'roll' => $room['class_id'] ? ($rolls[(int) $room['class_id']] ?? 0) : null,
            'classes' => $this->classes(), 'subjects' => db()->all('SELECT id, name FROM subjects WHERE is_active = 1 ORDER BY name'),
            'edit' => can('resources.manage'),
        ]);
    }

    public function updateRoom(int $id): void
    {
        $this->roomOr404($id);
        $data = $this->roomInput('resources/rooms/' . $id, $id);
        $data['is_active'] = isset($_POST['is_active']) ? 1 : 0;
        db()->update('rooms', $data, 'id = ?', [$id]);
        audit('room_updated', 'rooms', $id);
        flash('success', 'Room saved.');
        redirect('resources/rooms/' . $id);
    }

    public function destroyRoom(int $id): void
    {
        $room = $this->roomOr404($id);
        db()->run('DELETE FROM rooms WHERE id = ?', [$id]);
        audit('room_deleted', 'rooms', $id, ['name' => $room['name']]);
        flash('success', 'Room and its items removed.');
        redirect('resources');
    }

    public function addItem(int $id): void
    {
        $this->roomOr404($id);
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 120);
        $cat = (string) ($_POST['category'] ?? '');
        if ($name === '' || !isset(Resources::CATEGORIES[$cat])) {
            flash('error', 'Give the item a name and choose its kind.');
            redirect('resources/rooms/' . $id);
        }
        db()->insert('resource_items', $this->itemData($_POST) + ['room_id' => $id, 'name' => $name, 'category' => $cat, 'updated_by' => Auth::id()]);
        audit('resource_added', 'rooms', $id, ['item' => $name]);
        flash('success', '“' . $name . '” added.');
        redirect('resources/rooms/' . $id);
    }

    public function addStandard(int $id): void
    {
        $room = $this->roomOr404($id);
        $n = 0;
        foreach (Resources::standardItems($room) as [$cat, $name, $sid, $per, $need]) {
            if (db()->value('SELECT 1 FROM resource_items WHERE room_id = ? AND name = ?', [$id, $name])) {
                continue;
            }
            db()->insert('resource_items', ['room_id' => $id, 'category' => $cat, 'name' => $name, 'subject_id' => $sid, 'per_pupil' => $per, 'needed' => $need, 'updated_by' => Auth::id()]);
            $n++;
        }
        flash('success', $n ? "$n standard item(s) added with quantity 0. Count what is in the room and enter the numbers." : 'The standard items are already listed.');
        redirect('resources/rooms/' . $id);
    }

    public function saveItems(int $id): void
    {
        $this->roomOr404($id);
        $del = array_map('intval', (array) ($_POST['delete'] ?? []));
        db()->transaction(function (Database $d) use ($id, $del) {
            foreach ((array) ($_POST['it'] ?? []) as $iid => $r) {
                $iid = (int) $iid;
                if (!$d->value('SELECT 1 FROM resource_items WHERE id = ? AND room_id = ?', [$iid, $id])) {
                    continue;
                }
                if (in_array($iid, $del, true)) {
                    $d->run('DELETE FROM resource_items WHERE id = ?', [$iid]);
                    continue;
                }
                $d->update('resource_items', $this->itemData((array) $r) + ['updated_by' => Auth::id()], 'id = ?', [$iid]);
            }
        });
        audit('resources_saved', 'rooms', $id, ['deleted' => count($del)]);
        flash('success', 'Quantities saved.');
        redirect('resources/rooms/' . $id);
    }

    // ------------------------------------------------------------------ helpers

    private function roomOr404(int $id): array
    {
        return db()->one('SELECT r.*, c.name AS class_name FROM rooms r LEFT JOIN classes c ON c.id = r.class_id WHERE r.id = ?', [$id]) ?? $this->notFound();
    }

    private function roomInput(string $back, int $id = 0): array
    {
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 60);
        $kind = (string) ($_POST['kind'] ?? 'classroom');
        $classId = (int) ($_POST['class_id'] ?? 0) ?: null;
        $err = null;
        if ($name === '') {
            $err = 'Give the room a name.';
        } elseif (db()->value('SELECT 1 FROM rooms WHERE name = ? AND id <> ?', [$name, $id])) {
            $err = 'Another room already has that name.';
        } elseif (!isset(Resources::KINDS[$kind])) {
            $err = 'Choose the kind of room.';
        } elseif ($classId && !db()->value('SELECT 1 FROM classes WHERE id = ?', [$classId])) {
            $err = 'Choose a valid class.';
        }
        if ($err) {
            with_old($_POST);
            flash('error', $err);
            redirect($back);
        }
        return ['name' => $name, 'kind' => $kind, 'capacity' => max(0, min(2000, (int) ($_POST['capacity'] ?? 0))), 'class_id' => $classId, 'notes' => mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 255) ?: null];
    }

    private function itemData(array $r): array
    {
        $int = fn($k) => max(0, min(100000, (int) ($r[$k] ?? 0)));
        $per = trim((string) ($r['per_pupil'] ?? ''));
        $need = trim((string) ($r['needed'] ?? ''));
        $sid = (int) ($r['subject_id'] ?? 0);
        return [
            'qty_good' => $int('qty_good'), 'qty_repair' => $int('qty_repair'), 'qty_bad' => $int('qty_bad'),
            'per_pupil' => $per === '' || (float) $per <= 0 ? null : min(10.0, round((float) $per, 2)),
            'needed' => $need === '' ? null : max(0, min(100000, (int) $need)),
            'subject_id' => $sid && db()->value('SELECT 1 FROM subjects WHERE id = ?', [$sid]) ? $sid : null,
            'notes' => mb_substr(trim((string) ($r['notes'] ?? '')), 0, 255) ?: null,
        ];
    }

    private function classes(): array
    {
        return db()->all('SELECT c.id, c.name FROM classes c JOIN levels l ON l.id = c.level_id WHERE c.is_active = 1 ORDER BY l.sort_order, c.name');
    }
}
