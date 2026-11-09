<?php
declare(strict_types=1);

/** Administrator management of teacher directory details only; payroll fields are never read or written. */
class TeacherRecordController extends Controller
{
    public function index(): void
    {
        $rows = db()->all(
            "SELECT s.id, s.user_id, s.staff_no, s.name, s.phone, s.position, s.category, s.employment, s.start_date, s.is_active, u.email, u.role
             FROM staff s LEFT JOIN users u ON u.id = s.user_id
             WHERE s.category = 'teaching' ORDER BY s.is_active DESC, s.name"
        );
        $this->view('teacher_records/index', ['title' => 'Teacher records', 'rows' => $rows]);
    }

    public function create(): void
    {
        $users = db()->all(
            "SELECT u.id, u.name, u.phone, u.role FROM users u
             WHERE u.is_active = 1 AND u.role IN ('head_teacher','academic_coordinator','class_teacher','subject_teacher')
               AND NOT EXISTS (SELECT 1 FROM staff s WHERE s.user_id = u.id)
             ORDER BY u.name"
        );
        $this->view('teacher_records/form', ['title' => 'Add teacher record', 'record' => null, 'users' => $users]);
    }

    public function store(): void
    {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $user = db()->one(
            "SELECT id, name, phone FROM users WHERE id = ? AND is_active = 1
             AND role IN ('head_teacher','academic_coordinator','class_teacher','subject_teacher')",
            [$userId]
        );
        if (!$user || db()->value('SELECT 1 FROM staff WHERE user_id = ?', [$userId])) {
            flash('error', 'Choose an active teacher account without an existing staff record.');
            redirect('teacher-records/create');
        }
        $data = $this->validated(true);
        if (!$data) {
            redirect('teacher-records/create');
        }
        $id = db()->insert('staff', $data + [
            'staff_no' => Payroll::nextStaffNo(db()),
            'user_id' => $userId,
            'name' => $user['name'],
            'phone' => $user['phone'],
            'position' => $this->position(),
            'category' => 'teaching',
        ]);
        audit('teacher_record_created', 'staff', $id, ['user_id' => $userId]);
        flash('success', 'Teacher record created.');
        redirect('teacher-records');
    }

    public function edit(int $id): void
    {
        $record = $this->record($id);
        $user = db()->one('SELECT id, name, phone FROM users WHERE id = ?', [$record['user_id']]);
        $this->view('teacher_records/form', [
            'title' => 'Edit teacher record',
            'record' => $record + ['name' => $user['name'] ?? $record['name'], 'phone' => $user['phone'] ?? $record['phone']],
            'users' => [],
        ]);
    }

    public function update(int $id): void
    {
        $record = $this->record($id);
        $data = $this->validated();
        if (!$data) {
            redirect('teacher-records/' . $id . '/edit');
        }
        db()->transaction(function (Database $d) use ($record, $data) {
            $d->update('staff', $data + [
                'name' => $this->name(),
                'phone' => $this->phone(),
                'position' => $this->position(),
            ], 'id = ?', [$record['id']]);
            if (!empty($record['user_id'])) {
                $d->update('users', ['name' => $this->name(), 'phone' => $this->phone()], 'id = ?', [$record['user_id']]);
            }
        });
        audit('teacher_record_updated', 'staff', $id);
        flash('success', 'Teacher record updated.');
        redirect('teacher-records');
    }

    public function archive(int $id): void
    {
        $record = $this->record($id);
        db()->update('staff', ['is_active' => 0, 'end_date' => date('Y-m-d')], 'id = ?', [$id]);
        audit('teacher_record_archived', 'staff', $id);
        flash('success', 'Teacher record archived. The teacher login and class assignments were not changed.');
        redirect('teacher-records');
    }

    private function record(int $id): array
    {
        return db()->one(
            "SELECT id, user_id, staff_no, name, phone, position, employment, start_date, end_date, gender, qualification, ntc_licence_no, ntc_expiry, is_active
             FROM staff WHERE id = ? AND category = 'teaching'",
            [$id]
        ) ?? $this->notFound();
    }

    private function validated(bool $creating = false): ?array
    {
        $name = $this->name();
        $position = $this->position();
        $category = (string) ($_POST['employment'] ?? 'permanent');
        $gender = (string) ($_POST['gender'] ?? '');
        $qualification = (string) ($_POST['qualification'] ?? '');
        $errors = [];
        if (!$creating && $name === '') $errors['name'] = 'Enter a name.';
        if ($position === '') $errors['position'] = 'Enter a position.';
        if (!isset(Payroll::EMPLOYMENT[$category])) $errors['employment'] = 'Choose a valid employment type.';
        if ($gender !== '' && !isset(Payroll::GENDER[$gender])) $errors['gender'] = 'Choose a valid gender.';
        if ($qualification !== '' && !isset(Payroll::QUALIFICATION[$qualification])) $errors['qualification'] = 'Choose a valid qualification.';
        if ($this->phone() !== '' && !preg_match('/^[0-9+() .-]{7,20}$/', $this->phone())) $errors['phone'] = 'Enter a valid phone number.';
        foreach (['start_date', 'end_date', 'ntc_expiry'] as $dateField) {
            $dateValue = trim((string) ($_POST[$dateField] ?? ''));
            if ($dateValue !== '' && $this->date($dateField) === null) $errors[$dateField] = 'Enter a valid date.';
        }
        if ($errors) {
            with_old($_POST, $errors);
            flash('error', 'Please correct the highlighted fields.');
            return null;
        }
        return [
            'employment' => $category,
            'start_date' => $this->date('start_date'),
            'end_date' => $creating || isset($_POST['is_active']) ? null : date('Y-m-d'),
            'gender' => $gender ?: null,
            'qualification' => $qualification ?: null,
            'ntc_licence_no' => mb_substr(trim((string) ($_POST['ntc_licence_no'] ?? '')), 0, 30) ?: null,
            'ntc_expiry' => $this->date('ntc_expiry'),
            'is_active' => $creating || isset($_POST['is_active']) ? 1 : 0,
        ];
    }

    private function name(): string
    {
        return mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 150);
    }

    private function phone(): string
    {
        return mb_substr(trim((string) ($_POST['phone'] ?? '')), 0, 20);
    }

    private function position(): string
    {
        return mb_substr(trim((string) ($_POST['position'] ?? '')), 0, 80);
    }

    private function date(string $key): ?string
    {
        $value = trim((string) ($_POST[$key] ?? ''));
        if ($value === '') return null;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }
}
