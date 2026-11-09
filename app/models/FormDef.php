<?php
declare(strict_types=1);

/** Form Builder helpers: field types, validation of answers, public links. */
final class FormDef
{
    public const TYPES = [
        'text'     => 'Short text',
        'textarea' => 'Paragraph',
        'number'   => 'Number',
        'email'    => 'Email',
        'phone'    => 'Phone number',
        'date'     => 'Date',
        'select'   => 'Dropdown (choose one)',
        'radio'    => 'Multiple choice (choose one)',
        'checkbox' => 'Checkboxes (choose any)',
        'file'     => 'File upload (PDF/JPG/PNG)',
        'section'  => 'Section heading / instructions',
    ];

    public const CHOICE_TYPES = ['select', 'radio', 'checkbox'];

    public const STATUSES = ['draft' => ['Draft', 'neutral'], 'published' => ['Open', 'good'], 'closed' => ['Closed', 'bad']];

    public static function options(?string $raw): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $raw)), fn($o) => $o !== ''));
    }

    public static function fields(int $formId, bool $withArchived = false): array
    {
        return db()->all('SELECT * FROM form_fields WHERE form_id = ?' . ($withArchived ? '' : ' AND is_archived = 0') . ' ORDER BY sort_order, id', [$formId]);
    }

    /** Absolute link parents can open (the public web address when set, else the address in use). */
    public static function publicUrl(array $form): string
    {
        return absolute_url('f/' . $form['public_key']);
    }

    public static function isOpen(array $form): bool
    {
        return $form['status'] === 'published' && (!$form['closes_on'] || $form['closes_on'] >= date('Y-m-d'));
    }

    /**
     * Validate submitted answers.
     * @return array{0: array<int,string>, 1: array<int,string>} [values by field id, errors by field id]
     */
    public static function validate(array $fields, array $post, array $files): array
    {
        $values = [];
        $errors = [];
        foreach ($fields as $f) {
            if ($f['type'] === 'section') {
                continue;
            }
            $id = (int) $f['id'];
            $raw = $post['f'][$id] ?? null;
            $label = $f['label'];

            if ($f['type'] === 'file') {
                $has = isset($files['f']['error'][$id]) && $files['f']['error'][$id] !== UPLOAD_ERR_NO_FILE;
                if ($f['is_required'] && !$has) {
                    $errors[$id] = "$label is required.";
                }
                continue;
            }
            if ($f['type'] === 'checkbox') {
                $picked = array_values(array_intersect(self::options($f['options']), array_map('strval', (array) $raw)));
                if ($f['is_required'] && !$picked) {
                    $errors[$id] = "Choose at least one option for “{$label}”.";
                }
                $values[$id] = $picked ? json_encode($picked, JSON_UNESCAPED_UNICODE) : '';
                continue;
            }
            $v = is_string($raw) ? trim($raw) : '';
            if ($v === '') {
                if ($f['is_required']) {
                    $errors[$id] = "$label is required.";
                }
                $values[$id] = '';
                continue;
            }
            $ok = true;
            switch ($f['type']) {
                case 'number':
                    $ok = is_numeric($v);
                    break;
                case 'email':
                    $ok = (bool) filter_var($v, FILTER_VALIDATE_EMAIL);
                    break;
                case 'phone':
                    $ok = SmsGateway::validPhone((string) Admission::normalisePhone($v));
                    $v = $ok ? (string) Admission::normalisePhone($v) : $v;
                    break;
                case 'date':
                    $ok = Validator::isDate($v);
                    break;
                case 'select':
                case 'radio':
                    $ok = in_array($v, self::options($f['options']), true);
                    break;
            }
            if (!$ok) {
                $errors[$id] = "Please enter a valid value for “{$label}”.";
            }
            $values[$id] = mb_substr($v, 0, $f['type'] === 'textarea' ? 5000 : 500);
        }
        return [$values, $errors];
    }

    /** Human-readable answer text. */
    public static function display(?string $value, string $type): string
    {
        if ($type === 'checkbox' && $value) {
            $arr = json_decode($value, true);
            return is_array($arr) ? implode(', ', $arr) : (string) $value;
        }
        return (string) $value;
    }
}
