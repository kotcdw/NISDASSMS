<?php
declare(strict_types=1);

/**
 * Rules: required, email, date, phone, int, max:N, min:N, in:a,b,c, after_or_equal:field, unique:table,column[,ignoreId]
 */
final class Validator
{
    public static function check(array $input, array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $ruleString) {
            $value = $input[$field] ?? null;
            $value = is_string($value) ? trim($value) : $value;
            $empty = $value === null || $value === '';
            foreach (explode('|', $ruleString) as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $label = ucfirst(str_replace('_', ' ', $field));
                $msg = null;
                switch ($name) {
                    case 'required':
                        if ($empty) {
                            $msg = "$label is required.";
                        }
                        break;
                    case 'email':
                        if (!$empty && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $msg = "$label must be a valid email address.";
                        }
                        break;
                    case 'date':
                        if (!$empty && !self::isDate((string) $value)) {
                            $msg = "$label must be a valid date.";
                        }
                        break;
                    case 'phone':
                        // Ghana numbers: 0XXXXXXXXX or +233XXXXXXXXX
                        if (!$empty && !preg_match('/^(\+?233|0)[235]\d{8}$/', preg_replace('/[\s-]/', '', (string) $value))) {
                            $msg = "$label must be a valid Ghana phone number (e.g. 0244123456).";
                        }
                        break;
                    case 'int':
                        if (!$empty && filter_var($value, FILTER_VALIDATE_INT) === false) {
                            $msg = "$label must be a whole number.";
                        }
                        break;
                    case 'max':
                        if (!$empty && mb_strlen((string) $value) > (int) $arg) {
                            $msg = "$label must be at most $arg characters.";
                        }
                        break;
                    case 'min':
                        if (!$empty && mb_strlen((string) $value) < (int) $arg) {
                            $msg = "$label must be at least $arg characters.";
                        }
                        break;
                    case 'in':
                        if (!$empty && !in_array((string) $value, explode(',', (string) $arg), true)) {
                            $msg = "$label has an invalid value.";
                        }
                        break;
                    case 'after_or_equal':
                        $other = $input[$arg] ?? null;
                        if (!$empty && $other && self::isDate((string) $value) && self::isDate((string) $other) && $value < $other) {
                            $msg = "$label must be on or after " . str_replace('_', ' ', (string) $arg) . '.';
                        }
                        break;
                    case 'past':
                        if (!$empty && self::isDate((string) $value) && $value > date('Y-m-d')) {
                            $msg = "$label cannot be in the future.";
                        }
                        break;
                    case 'unique':
                        if (!$empty) {
                            $parts = explode(',', (string) $arg);
                            [$table, $col] = $parts;
                            $ignore = isset($parts[2]) ? (int) $parts[2] : 0;
                            if (!preg_match('/^\w+$/', $table) || !preg_match('/^\w+$/', $col)) {
                                throw new InvalidArgumentException('Bad unique rule');
                            }
                            $n = (int) db()->value("SELECT COUNT(*) FROM `$table` WHERE `$col` = ? AND id <> ?", [$value, $ignore]);
                            if ($n > 0) {
                                $msg = "$label is already in use.";
                            }
                        }
                        break;
                }
                if ($msg) {
                    $errors[$field] = $msg;
                    break;
                }
            }
        }
        return $errors;
    }

    public static function isDate(string $v): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $v);
        return $d && $d->format('Y-m-d') === $v;
    }
}
