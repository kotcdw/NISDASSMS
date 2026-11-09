<?php
declare(strict_types=1);

/** Online appointments: staff publish weekly availability per appointment type; parents book a free time; both can cancel. */
class AppointmentController extends Controller
{
    public const STATUS = ['booked' => ['Booked', 'info'], 'cancelled' => ['Cancelled', 'neutral'], 'done' => ['Held', 'good'], 'no_show' => ['Did not come', 'bad']];
    public const DAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    public function index(): void
    {
        $staffView = can('appointments.offer');
        $rows = db()->all(
            'SELECT a.*, t.name AS type_name, s.name AS staff_name, b.name AS booked_name, b.phone AS booked_phone, p.first_name, p.last_name
             FROM appointments a JOIN appointment_types t ON t.id = a.type_id JOIN users s ON s.id = a.staff_id JOIN users b ON b.id = a.booked_by
             LEFT JOIN pupils p ON p.id = a.pupil_id WHERE ' . ($staffView ? 'a.staff_id = ?' : 'a.booked_by = ?') . '
             ORDER BY a.start_at < NOW(), CASE WHEN a.start_at >= NOW() THEN a.start_at END, a.start_at DESC LIMIT 100',
            [Auth::id()]
        );
        $this->view('appointments/index', ['title' => 'Appointments', 'rows' => $rows, 'staffView' => $staffView,
            'hasAvailability' => $staffView && db()->value('SELECT 1 FROM appointment_availability WHERE user_id = ? LIMIT 1', [Auth::id()])]);
    }

    public function book(): void
    {
        $types = db()->all('SELECT t.* FROM appointment_types t WHERE t.is_active = 1 AND EXISTS (SELECT 1 FROM appointment_availability a JOIN users u ON u.id = a.user_id AND u.is_active = 1 WHERE a.type_id = t.id) ORDER BY t.name');
        $typeId = (int) ($_GET['type'] ?? 0);
        $type = $typeId ? db()->one('SELECT * FROM appointment_types WHERE id = ? AND is_active = 1', [$typeId]) : null;
        $staff = [];
        if ($type) {
            $teachers = array_map('intval', array_filter(array_column(Accounts::childrenOf((int) Auth::id()), 'class_teacher_id')));
            foreach (db()->all('SELECT DISTINCT u.id, u.name, u.role FROM appointment_availability a JOIN users u ON u.id = a.user_id WHERE a.type_id = ? AND u.is_active = 1 ORDER BY u.name', [$typeId]) as $s) {
                $s['mine'] = in_array((int) $s['id'], $teachers, true);
                $staff[] = $s;
            }
            usort($staff, fn($a, $b) => [$b['mine'], $a['name']] <=> [$a['mine'], $b['name']]);
        }
        $staffId = (int) ($_GET['staff'] ?? 0);
        $person = null;
        foreach ($staff as $s) {
            if ((int) $s['id'] === $staffId) {
                $person = $s;
            }
        }
        $days = [];
        if ($type && $person) {
            for ($i = 0; $i < 21 && count($days) < 10; $i++) {
                $d = date('Y-m-d', strtotime("+$i days"));
                if ($slots = Appointments::slots((int) $person['id'], $type, $d)) {
                    $days[$d] = $slots;
                }
            }
        }
        $this->view('appointments/book', ['title' => 'Book an appointment', 'types' => $types, 'type' => $type, 'staff' => $staff, 'person' => $person, 'days' => $days,
            'children' => array_values(array_filter(Accounts::childrenOf((int) Auth::id()), fn($c) => $c['status'] === 'active'))]);
    }

    public function store(): void
    {
        $type = db()->one('SELECT * FROM appointment_types WHERE id = ? AND is_active = 1', [(int) ($_POST['type_id'] ?? 0)]);
        $staffId = (int) ($_POST['staff_id'] ?? 0);
        $start = (string) ($_POST['start'] ?? '');
        $pid = (int) ($_POST['pupil_id'] ?? 0);
        $children = array_map('intval', array_column(Accounts::childrenOf((int) Auth::id()), 'id'));
        $back = 'appointments/book' . ($type ? '?type=' . $type['id'] . '&staff=' . $staffId : '');
        $err = null;
        if (!$type || !preg_match('/^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2})$/', $start, $m)) {
            $err = 'Choose a time.';
        } elseif ($pid && !in_array($pid, $children, true)) {
            $err = 'Choose your child.';
        } elseif ($staffId === Auth::id()) {
            $err = 'You cannot book an appointment with yourself.';
        } elseif (!in_array($m[2], Appointments::slots($staffId, $type, $m[1]), true)) {
            $err = 'That time is no longer free. Please choose another.';
        }
        $answer = mb_substr(trim((string) ($_POST['answer'] ?? '')), 0, 500);
        if (!$err && $type['question'] && $answer === '') {
            $err = 'Please answer: ' . $type['question'];
        }
        if ($err) {
            flash('error', $err);
            redirect($back);
        }
        $begin = $m[1] . ' ' . $m[2] . ':00';
        $end = date('Y-m-d H:i:s', strtotime($begin) + (int) $type['duration_min'] * 60);
        $id = db()->transaction(function (Database $d) use ($type, $staffId, $begin, $end, $pid, $answer) {
            // Lock the staff member's bookings for that day and re-check the clash
            $d->all('SELECT id FROM appointments WHERE staff_id = ? AND DATE(start_at) = ? FOR UPDATE', [$staffId, substr($begin, 0, 10)]);
            if ($d->value("SELECT 1 FROM appointments WHERE staff_id = ? AND status = 'booked' AND start_at < ? AND end_at > ?", [$staffId, $end, $begin])) {
                return 0;
            }
            return $d->insert('appointments', ['type_id' => $type['id'], 'staff_id' => $staffId, 'booked_by' => Auth::id(), 'pupil_id' => $pid ?: null, 'start_at' => $begin, 'end_at' => $end, 'answer' => $answer ?: null]);
        });
        if (!$id) {
            flash('error', 'Someone booked that time a moment ago. Please choose another.');
            redirect($back);
        }
        Notifier::inapp([$staffId], 'New appointment: ' . $type['name'], auth()['name'] . ' · ' . date('D j M H:i', strtotime($begin)) . ($answer ? ' — ' . mb_strimwidth($answer, 0, 80, '…') : ''), 'appointments');
        audit('appointment_booked', 'appointments', $id, ['staff' => $staffId, 'start' => $begin]);
        flash('success', 'Booked for ' . date('l j F \a\t H:i', strtotime($begin)) . '. You can cancel it from Appointments if you cannot come.');
        redirect('appointments');
    }

    public function cancel(int $id): void
    {
        $a = $this->find($id);
        $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 500);
        $isStaff = (int) $a['staff_id'] === Auth::id();
        if ($a['status'] !== 'booked' || $a['start_at'] < date('Y-m-d H:i:s') || ($isStaff && $note === '')) {
            flash('error', $isStaff ? 'Write a short note to the parent explaining why.' : 'This appointment cannot be cancelled now.');
            redirect('appointments');
        }
        db()->update('appointments', ['status' => 'cancelled', 'cancelled_by' => Auth::id(), 'staff_note' => $isStaff ? $note : $a['staff_note']], 'id = ?', [$id]);
        Notifier::inapp([$isStaff ? (int) $a['booked_by'] : (int) $a['staff_id']], 'Appointment cancelled', date('D j M H:i', strtotime($a['start_at'])) . ($note ? ' — ' . $note : ''), 'appointments');
        audit('appointment_cancelled', 'appointments', $id);
        flash('success', 'Appointment cancelled; the other person has been told.');
        redirect('appointments');
    }

    public function status(int $id): void
    {
        $a = $this->find($id);
        $to = (string) ($_POST['to'] ?? '');
        if ((int) $a['staff_id'] !== Auth::id() || !in_array($to, ['done', 'no_show'], true) || $a['status'] === 'cancelled' || $a['start_at'] > date('Y-m-d H:i:s')) {
            flash('error', 'You can mark an appointment only after its start time.');
            redirect('appointments');
        }
        db()->update('appointments', ['status' => $to, 'staff_note' => mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 500) ?: $a['staff_note']], 'id = ?', [$id]);
        flash('success', 'Saved.');
        redirect('appointments');
    }

    // ---------------------------------------------------------------- staff availability

    public function availability(): void
    {
        $rows = db()->all('SELECT a.*, t.name AS type_name FROM appointment_availability a JOIN appointment_types t ON t.id = a.type_id WHERE a.user_id = ? ORDER BY a.weekday, a.start_time', [Auth::id()]);
        $this->view('appointments/availability', ['title' => 'My availability', 'rows' => $rows, 'types' => db()->all('SELECT * FROM appointment_types WHERE is_active = 1 ORDER BY name')]);
    }

    public function addAvailability(): void
    {
        $type = (int) ($_POST['type_id'] ?? 0);
        $day = (int) ($_POST['weekday'] ?? 0);
        $s = (string) ($_POST['start_time'] ?? '');
        $e = (string) ($_POST['end_time'] ?? '');
        $t = '/^([01]\d|2[0-3]):[0-5]\d$/';
        if (!db()->value('SELECT id FROM appointment_types WHERE id = ? AND is_active = 1', [$type]) || !isset(self::DAYS[$day]) || !preg_match($t, $s) || !preg_match($t, $e) || $e <= $s) {
            flash('error', 'Choose the appointment type, the day and a start time before the end time.');
            redirect('appointments/availability');
        }
        if (db()->value('SELECT 1 FROM appointment_availability WHERE user_id = ? AND weekday = ? AND start_time < ? AND end_time > ?', [Auth::id(), $day, $e . ':00', $s . ':00'])) {
            flash('error', 'This overlaps a time you already offer on ' . self::DAYS[$day] . '.');
            redirect('appointments/availability');
        }
        db()->insert('appointment_availability', ['user_id' => Auth::id(), 'type_id' => $type, 'weekday' => $day, 'start_time' => $s . ':00', 'end_time' => $e . ':00']);
        audit('appointment_availability_added', 'appointment_availability', null, ['day' => $day, 'from' => $s, 'to' => $e]);
        flash('success', 'Added. Parents can now book this time.');
        redirect('appointments/availability');
    }

    public function removeAvailability(int $id): void
    {
        db()->run('DELETE FROM appointment_availability WHERE id = ? AND user_id = ?', [$id, Auth::id()]);
        flash('success', 'Removed. Appointments already booked are kept.');
        redirect('appointments/availability');
    }

    // ---------------------------------------------------------------- types (Head)

    public function types(): void
    {
        $this->view('appointments/types', ['title' => 'Appointment types', 'types' => db()->all(
            'SELECT t.*, (SELECT COUNT(DISTINCT user_id) FROM appointment_availability a WHERE a.type_id = t.id) AS staff_n,
                    (SELECT COUNT(*) FROM appointments x WHERE x.type_id = t.id AND x.status = "booked" AND x.start_at >= NOW()) AS upcoming
             FROM appointment_types t ORDER BY t.is_active DESC, t.name'
        )]);
    }

    public function saveType(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 80);
        $dur = (int) ($_POST['duration_min'] ?? 0);
        if ($name === '' || $dur < 5 || $dur > 180 || db()->value('SELECT id FROM appointment_types WHERE name = ? AND id <> ?', [$name, $id])) {
            flash('error', 'Give the type a unique name and a length between 5 and 180 minutes.');
            redirect('appointments/types');
        }
        $data = ['name' => $name, 'duration_min' => $dur, 'description' => mb_substr(trim((string) ($_POST['description'] ?? '')), 0, 255) ?: null,
                 'question' => mb_substr(trim((string) ($_POST['question'] ?? '')), 0, 255) ?: null, 'is_active' => $id && empty($_POST['is_active']) ? 0 : 1];
        $id ? db()->update('appointment_types', $data, 'id = ?', [$id]) : db()->insert('appointment_types', $data);
        audit('appointment_type_saved', 'appointment_types', $id ?: null, $data);
        flash('success', 'Appointment type saved.');
        redirect('appointments/types');
    }

    private function find(int $id): array
    {
        $a = db()->one('SELECT * FROM appointments WHERE id = ?', [$id]) ?? $this->notFound();
        if ((int) $a['staff_id'] !== Auth::id() && (int) $a['booked_by'] !== Auth::id()) {
            $this->notFound();
        }
        return $a;
    }
}
