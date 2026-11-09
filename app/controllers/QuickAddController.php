<?php
declare(strict_types=1);

/**
 * "+ Add new…" in dropdowns: lets authorised users create a missing item (a teacher account, class,
 * subject, fee item, money account, income/expense category, library category) without leaving the form.
 * Called by assets/js/app.js; answers JSON {ok, id, label, note} or {ok:false, error}.
 */
class QuickAddController extends Controller
{
    /** kind => [permission, label] */
    public const KINDS = [
        'staff'            => ['users.manage', 'staff member'],
        'class'            => ['setup.manage', 'class'],
        'subject'          => ['setup.manage', 'subject'],
        'fee_item'         => ['fees.manage', 'fee item'],
        'fin_account'      => ['finance.manage', 'money account'],
        'fin_category'     => ['finance.manage', 'category'],
        'library_category' => ['library.manage', 'category'],
    ];

    public const STAFF_ROLES = ['head_teacher', 'academic_coordinator', 'class_teacher', 'subject_teacher', 'bursar', 'admissions_officer'];

    /** Attributes to put on a <select> so authorised users get "+ Add new …" (empty string for everyone else). */
    public static function attr(string $kind, array $preset = []): string
    {
        if (!isset(self::KINDS[$kind]) || !can(self::KINDS[$kind][0])) {
            return '';
        }
        return ' data-quick-add="' . e($kind) . '" data-quick-add-label="' . e(self::KINDS[$kind][1]) . '"'
            . ($preset ? ' data-quick-add-preset="' . e(json_encode($preset)) . '"' : '');
    }

    public function store(): void
    {
        $kind = (string) ($_POST['kind'] ?? '');
        header('Content-Type: application/json; charset=utf-8');
        if (!isset(self::KINDS[$kind])) {
            $this->out(['ok' => false, 'error' => 'Unknown item type.']);
        }
        if (!can(self::KINDS[$kind][0])) {
            http_response_code(403);
            $this->out(['ok' => false, 'error' => 'You are not allowed to add a ' . self::KINDS[$kind][1] . '.']);
        }
        $v = fn(string $k, int $max = 150) => mb_substr(trim((string) ($_POST[$k] ?? '')), 0, $max);
        try {
            $res = match ($kind) {
                'staff' => $this->staff($v),
                'class' => $this->classRow($v),
                'subject' => $this->subject($v),
                'fee_item' => $this->feeItem($v),
                'fin_account' => $this->finAccount($v),
                'fin_category' => $this->finCategory($v),
                'library_category' => $this->libraryCategory($v),
            };
        } catch (InvalidArgumentException $e) {
            $this->out(['ok' => false, 'error' => $e->getMessage()]);
        }
        audit('quick_add_' . $kind, $kind, is_numeric($res['id']) ? (int) $res['id'] : null, ['label' => $res['label']]);
        $this->out(['ok' => true] + $res);
    }

    // ---------------------------------------------------------------- kinds

    private function staff(callable $v): array
    {
        $name = $v('name');
        $email = strtolower($v('email', 190));
        $role = $v('role', 40);
        $phone = $v('phone', 20);
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter the full name and a valid email address.');
        }
        if (!in_array($role, self::STAFF_ROLES, true)) {
            throw new InvalidArgumentException('Choose the role.');
        }
        if (db()->value('SELECT id FROM users WHERE email = ?', [$email])) {
            throw new InvalidArgumentException('An account with this email already exists.');
        }
        if ($phone !== '') {
            $phone = (string) Admission::normalisePhone($phone);
            if (!preg_match('/^0[2-5]\d{8}$/', $phone)) {
                throw new InvalidArgumentException('Enter a Ghana phone number like 0244123456, or leave it empty.');
            }
        }
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $temp = '';
        for ($i = 0; $i < 10; $i++) {
            $temp .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $id = db()->insert('users', ['name' => $name, 'email' => $email, 'phone' => $phone ?: null, 'role' => $role,
            'password_hash' => password_hash($temp, PASSWORD_DEFAULT), 'must_change_password' => 1]);
        return ['id' => $id, 'label' => $name, 'note' => "Account created for $name ($email). Temporary password: $temp — write it down and give it to them privately; they must change it when they first sign in."];
    }

    private function classRow(callable $v): array
    {
        $name = $v('name', 40);
        $level = (int) ($_POST['level_id'] ?? 0);
        if ($name === '' || !db()->value('SELECT id FROM levels WHERE id = ?', [$level])) {
            throw new InvalidArgumentException('Enter the class name (e.g. "JHS 1B") and choose its level.');
        }
        if (db()->value('SELECT id FROM classes WHERE name = ?', [$name])) {
            throw new InvalidArgumentException('A class with this name already exists.');
        }
        $id = db()->insert('classes', ['name' => $name, 'level_id' => $level, 'capacity' => max(1, min(200, (int) ($_POST['capacity'] ?? 40) ?: 40))]);
        // The new class gets the standard subjects of its department, as in School setup
        db()->run('INSERT IGNORE INTO class_subjects (class_id, subject_id) SELECT ?, s.id FROM subjects s JOIN levels l ON l.id = ? WHERE s.is_active = 1 AND FIND_IN_SET(l.category, s.applies_to) > 0', [$id, $level]);
        return ['id' => $id, 'label' => $name, 'note' => "Class $name added with its standard subjects. Choose its class teacher in School setup → Classes."];
    }

    private function subject(callable $v): array
    {
        $name = $v('name', 80);
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $v('code', 12)));
        $applies = array_values(array_intersect((array) ($_POST['applies_to'] ?? []), ['creche', 'kg', 'primary', 'jhs']));
        if ($name === '') {
            throw new InvalidArgumentException('Enter the subject name.');
        }
        if (db()->value('SELECT id FROM subjects WHERE name = ?', [$name])) {
            throw new InvalidArgumentException('This subject already exists (it may be switched off in School setup → Subjects).');
        }
        $code = $this->uniqueCode('subjects', $code !== '' ? $code : $name, 12);
        $id = db()->insert('subjects', ['name' => $name, 'code' => $code, 'applies_to' => $applies ? implode(',', $applies) : 'primary,jhs']);
        return ['id' => $id, 'label' => $name, 'note' => "Subject $name ($code) added."];
    }

    private function feeItem(callable $v): array
    {
        $name = $v('name', 80);
        $freq = in_array($_POST['frequency'] ?? '', ['termly', 'yearly', 'once'], true) ? $_POST['frequency'] : 'termly';
        if ($name === '') {
            throw new InvalidArgumentException('Enter the fee item name.');
        }
        if (db()->value('SELECT id FROM fee_items WHERE name = ?', [$name])) {
            throw new InvalidArgumentException('This fee item already exists.');
        }
        $id = db()->insert('fee_items', ['name' => $name, 'code' => $this->uniqueCode('fee_items', $name, 12), 'frequency' => $freq,
            'discountable' => !empty($_POST['discountable']) ? 1 : 0, 'sort_order' => 50]);
        return ['id' => $id, 'label' => $name, 'note' => "Fee item $name added. Set its amount for each class level in Fees → Fee schedule."];
    }

    private function finAccount(callable $v): array
    {
        $name = $v('name', 80);
        $kind = isset(Finance::ACCOUNT_KINDS[$_POST['account_kind'] ?? '']) ? $_POST['account_kind'] : 'bank';
        if ($name === '') {
            throw new InvalidArgumentException('Enter the account name (e.g. "Fidelity Bank — PTA account").');
        }
        if (db()->value('SELECT id FROM fin_accounts WHERE name = ?', [$name])) {
            throw new InvalidArgumentException('An account with this name already exists.');
        }
        $id = db()->insert('fin_accounts', ['name' => $name, 'kind' => $kind, 'account_no' => $v('account_no', 60) ?: null, 'opening_date' => date('Y-m-d'), 'sort_order' => 20]);
        return ['id' => $id, 'label' => $name, 'note' => "Account $name added with a zero opening balance. Enter its opening balance in Finance → Accounts & categories."];
    }

    private function finCategory(callable $v): array
    {
        $name = $v('name', 80);
        $kind = ($_POST['category_kind'] ?? '') === 'income' ? 'income' : 'expense';
        if ($name === '') {
            throw new InvalidArgumentException('Enter the category name.');
        }
        if (db()->value('SELECT id FROM fin_categories WHERE kind = ? AND name = ?', [$kind, $name])) {
            throw new InvalidArgumentException('This category already exists.');
        }
        $id = db()->insert('fin_categories', ['kind' => $kind, 'code' => $this->uniqueCode('fin_categories', $name, 12), 'name' => $name, 'sort_order' => 30]);
        return ['id' => $id, 'label' => $name, 'note' => ucfirst($kind) . " category $name added."];
    }

    private function libraryCategory(callable $v): array
    {
        $name = trim(str_replace(',', ' ', $v('name', 60)));
        if ($name === '') {
            throw new InvalidArgumentException('Enter the category name.');
        }
        $all = LibraryController::categories();
        foreach ($all as $c) {
            if (strcasecmp($c, $name) === 0) {
                throw new InvalidArgumentException('This category already exists.');
            }
        }
        $extra = array_filter(array_map('trim', explode(',', setting('library_categories', ''))));
        $extra[] = $name;
        setting_set('library_categories', implode(',', $extra));
        return ['id' => $name, 'label' => $name, 'note' => "Category $name added."];
    }

    // ---------------------------------------------------------------- helpers

    private function uniqueCode(string $table, string $from, int $max): string
    {
        $base = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $from), 0, $max - 2)) ?: 'ITEM';
        $code = $base;
        for ($i = 2; db()->value("SELECT id FROM $table WHERE code = ?", [$code]); $i++) {
            $code = $base . $i;
        }
        return $code;
    }

    private function out(array $data): void
    {
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
