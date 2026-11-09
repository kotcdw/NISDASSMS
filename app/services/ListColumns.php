<?php
declare(strict_types=1);

/**
 * Administration → School setup → List columns: which columns the school's lists show.
 * Chosen once for everyone by the administrator, so the list pages stay simple (no column menus on them).
 */
final class ListColumns
{
    /** list key => [title, setting key, [column key => [label, shown by default]]] */
    public const LISTS = [
        'admission' => ['Admission list', 'cols_admission', [
            'ref' => ['Ref', true], 'name' => ['Applicant', true], 'gender' => ['Gender', true], 'class' => ['Class', true],
            'age' => ['Age', true], 'parent' => ['Parent / guardian', true], 'phone' => ['Phone', true],
            'source' => ['Source', false], 'stage' => ['Stage', true], 'received' => ['Received', true],
        ]],
        'crm' => ['Enquiries (CRM)', 'cols_crm', [
            'ref' => ['Ref', true], 'parent' => ['Parent', true], 'phone' => ['Phone', true], 'child' => ['Child', true], 'class' => ['Class', true],
            'stage' => ['Stage', true], 'follow' => ['Next follow-up', true], 'who' => ['Followed up by', false], 'source' => ['Source', true],
        ]],
        'leave' => ['Student leave', 'cols_leave', [
            'student' => ['Student', true], 'adm' => ['Admission no.', false], 'class' => ['Class', true], 'from' => ['From', true], 'to' => ['To', true],
            'days' => ['Days', true], 'kind' => ['Kind', true], 'reason' => ['Reason', true], 'by' => ['Asked by', true], 'status' => ['Status', true],
        ]],
        'students' => ['Students list', 'cols_students', [
            'adm' => ['Admission no.', true], 'surname' => ['Surname', true], 'first' => ['First name(s)', true], 'class' => ['Class', true],
            'gender' => ['Gender', true], 'age' => ['Age', true], 'dob' => ['Date of birth', false], 'parent' => ['Parent / guardian', true],
            'phone' => ['Parent phone', true], 'house' => ['House', false], 'church' => ['Denomination', false], 'admitted' => ['Admitted', false],
            'status' => ['Status', true],
        ]],
    ];

    /** Column keys shown on a list, in list order. The name column is always shown so each row can be opened. */
    public static function on(string $list): array
    {
        [, $key, $cols] = self::LISTS[$list];
        $raw = setting($key, '');
        $chosen = $raw === '' ? array_keys(array_filter($cols, fn($c) => $c[1])) : explode(',', $raw);
        $keep = array_values(array_filter(array_keys($cols), fn($k) => in_array($k, $chosen, true) || self::locked($list, $k)));
        return $keep;
    }

    /** Columns that cannot be switched off. */
    public static function locked(string $list, string $col): bool
    {
        return in_array($list . '.' . $col, ['admission.name', 'students.surname', 'crm.parent', 'leave.student'], true);
    }

    public static function save(string $list, array $cols): void
    {
        [, $key, $all] = self::LISTS[$list];
        $cols = array_values(array_filter(array_keys($all), fn($k) => in_array($k, $cols, true) || self::locked($list, $k)));
        setting_set($key, implode(',', $cols));
    }
}
