<?php
declare(strict_types=1);

/** Validates and imports rows from Excel/CSV: pupils with parents, opening fee balances, staff accounts. */
final class Importer
{
    public const KINDS = [
        'pupils' => [
            'label' => 'Students and parents',
            'perm' => 'pupils.manage',
            'columns' => [
                'first_name' => 'required', 'middle_name' => '', 'last_name' => 'required', 'gender' => 'required — M or F',
                'date_of_birth' => 'required — e.g. 21/03/2016', 'class' => 'required — exactly as in School setup, e.g. Basic 4',
                'admission_no' => 'optional — leave empty to generate', 'admitted_on' => 'optional — date', 'guardian_name' => 'required',
                'guardian_phone' => 'required — e.g. 0244123456', 'guardian_relationship' => 'optional — Mother, Father…',
                'guardian_email' => '', 'guardian_occupation' => '', 'address' => '', 'hometown' => '', 'house' => '', 'denomination' => '',
                'allergies' => '', 'medical_notes' => '', 'previous_school' => '',
                'guardian2_name' => 'optional second parent', 'guardian2_phone' => '', 'guardian2_relationship' => '',
                'special_needs' => 'optional — e.g. Visual, Hearing, Physical, Learning, Speech, Autism (empty = none)',
                'special_needs_support' => 'optional — support the child needs',
            ],
            'example' => ['Ama', '', 'Mensah', 'F', '21/03/2016', 'Basic 4', '', '08/09/2026', 'Akosua Mensah', '0244123456', 'Mother', '', 'Trader', 'Adenta, Accra', 'Kumasi', '', 'SDA', '', '', '', 'Kwame Mensah', '0201234567', 'Father', '', ''],
        ],
        'balances' => [
            'label' => 'Opening fee balances (arrears brought forward)',
            'perm' => 'fees.manage',
            'columns' => ['admission_no' => 'required', 'amount' => 'required — GH₵ owed', 'description' => 'optional — default "Balance brought forward"', 'due_date' => 'optional'],
            'example' => ['NIS/2026/0001', '450.00', 'Balance brought forward from 2025/2026', ''],
        ],
        'staff' => [
            'label' => 'Staff accounts',
            'perm' => 'users.manage',
            'columns' => ['name' => 'required', 'email' => 'required — used to sign in', 'phone' => 'optional', 'role' => 'required — Head Teacher, Class Teacher, Subject Teacher, Bursar, Admissions Officer or Academic Coordinator'],
            'example' => ['Esi Ofori', 'esi.ofori@nisdas.edu.gh', '0244000000', 'Class Teacher'],
        ],
        'staff_records' => [
            'label' => 'Staff records (pay, qualification & NTC licence)',
            'perm' => 'payroll.manage',
            'columns' => [
                'staff_no' => 'optional — fill to UPDATE an existing record (e.g. STF004); empty = new staff member',
                'name' => 'required', 'gender' => 'optional — M or F', 'phone' => 'optional — e.g. 0244123456',
                'position' => 'required for new staff — e.g. Class teacher, Cook', 'category' => 'optional — Teaching or Non-teaching (default Teaching)',
                'employment' => 'optional — Permanent, Contract, Casual or National Service', 'start_date' => 'optional — date',
                'basic_salary' => 'required for new staff — monthly GH₵', 'ssnit_no' => '', 'tin' => '',
                'ghana_card_no' => 'optional — GHA-123456789-0', 'qualification' => 'optional — e.g. Diploma in Basic Education, B.Ed, Cert A, WASSCE',
                'ntc_licence_no' => 'optional — NTC licence number', 'ntc_expiry' => 'needed when a licence number is given — date',
                'pay_method' => 'optional — Bank, Mobile Money or Cash', 'bank_name' => '', 'bank_branch' => '', 'account_no' => '', 'momo_no' => '',
                'login_email' => "optional — the person's NISDAS sign-in email, to link their payslips",
            ],
            'example' => ['', 'Esi Ofori', 'F', '0244000000', 'Class teacher', 'Teaching', 'Permanent', '01/09/2022', '2600', 'C012345678901', '', 'GHA-712345678-9', 'Diploma in Basic Education', 'PT/123456/2024', '31/08/2027', 'Bank', 'GCB', 'Kasoa', '1021234567890', '', 'esi.ofori@nisdas.edu.gh'],
        ],
    ];

    /** "Visual impairment", "blind", "deaf", "dyslexia" … → Admission::SEN key; '' for none; null when not recognised. */
    public static function senKey(string $v): ?string
    {
        $v = mb_strtolower(trim($v));
        if ($v === '' || in_array($v, ['none', 'no', 'nil', 'n/a', '-'], true)) {
            return '';
        }
        if (isset(Admission::SEN[$v])) {
            return $v;
        }
        $words = [
            'multiple' => ['multiple'], 'visual' => ['visual', 'blind', 'sight', 'vision'], 'hearing' => ['hearing', 'deaf'],
            'physical' => ['physical', 'wheelchair', 'mobility'], 'intellectual' => ['intellectual'], 'speech' => ['speech', 'language', 'stammer'],
            'learning' => ['learning', 'dyslexi', 'dyscalcul'], 'autism' => ['autis'], 'behaviour' => ['behaviour', 'behavior', 'emotional', 'adhd'],
            'other' => ['other'],
        ];
        foreach ($words as $k => $list) {
            foreach ($list as $w) {
                if (str_contains($v, $w)) {
                    return $k;
                }
            }
        }
        return null;
    }

    /** "B.Ed", "Diploma in Basic Education", "cert a" … → Payroll::QUALIFICATION key; '' for empty; null when not recognised. */
    public static function qualificationKey(string $v): ?string
    {
        $raw = trim($v);
        $v = preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($raw));
        $v = trim($v);
        if ($v === '') {
            return '';
        }
        if (isset(Payroll::QUALIFICATION[$v])) {
            return $v;
        }
        foreach (Payroll::QUALIFICATION as $k => [$label]) {
            if ($v === trim(preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($label)))) {
                return $k;
            }
        }
        $map = [
            'pgde' => ['pgde', 'pgce', 'pgdip', 'post graduate diploma in education', 'postgraduate diploma in education'],
            'med' => ['m ed', 'med', 'm phil ed', 'mphil ed', 'master of education', 'm phil education', 'mphil education'],
            'masters' => ['msc', 'm sc', 'ma', 'm a', 'mba', 'mphil', 'm phil', 'master'],
            'bed' => ['b ed', 'bed', 'bachelor of education'],
            'degree' => ['bsc', 'b sc', 'ba', 'b a', 'bcom', 'b com', 'btech', 'b tech', 'hnd', 'degree', 'bachelor'],
            'dbe' => ['dbe', 'diploma in basic education', 'diploma in education', 'dip ed', 'diped'],
            'cert_a' => ['cert a', 'certificate a', 'teacher s certificate a', 'teachers certificate a', '3 year cert a', '4 year cert a'],
            'diploma' => ['diploma'],
            'wassce' => ['wassce', 'sssce', 'ssce', 'gce a level', 'a level', 'o level', 'gce'],
            'basic' => ['bece', 'mslc', 'jhs'],
        ];
        foreach ($map as $k => $list) {
            foreach ($list as $w) {
                if ($v === $w || str_starts_with($v, $w . ' ')) {
                    return $k;
                }
            }
        }
        return str_contains($v, 'other') ? 'other' : null;
    }

    /** Validate one staff-record row. @return array{0: array, 1: string[]} */
    private static function staffRecordRow(array $r, array &$seen): array
    {
        $e = [];
        $s = fn(string $k, int $len) => mb_substr(trim((string) ($r[$k] ?? '')), 0, $len);
        $d = ['staff_no' => strtoupper($s('staff_no', 20)), 'name' => $s('name', 150)];
        $existing = null;
        if ($d['staff_no'] !== '') {
            $existing = db()->one('SELECT * FROM staff WHERE staff_no = ?', [$d['staff_no']]);
            if (!$existing) {
                return [$d + ['update_id' => null, 'set' => []], ['no staff record with staff no. ' . $d['staff_no'] . ' (leave staff no. empty to add a new person)']];
            }
        }
        $d['update_id'] = $existing ? (int) $existing['id'] : null;
        if ($d['name'] === '' && !$existing) {
            $e[] = 'name is missing';
        }
        $set = []; // only the cells that were filled in
        if ($d['name'] !== '') {
            $set['name'] = $d['name'];
        }
        $g = strtolower(substr($s('gender', 10), 0, 1));
        if ($g !== '') {
            if ($g === 'm' || $g === 'f') {
                $set['gender'] = $g === 'm' ? 'male' : 'female';
            } else {
                $e[] = 'gender must be M or F';
            }
        }
        if ($s('phone', 30) !== '') {
            $p = SheetReader::phone($s('phone', 30));
            if ($p) {
                $set['phone'] = $p;
            } else {
                $e[] = 'phone is not a Ghana number';
            }
        }
        if ($s('position', 80) !== '') {
            $set['position'] = $s('position', 80);
        }
        $map = fn(string $v, array $opts) => $opts[str_replace(['-', ' '], '_', mb_strtolower(trim($v)))] ?? null;
        if ($s('category', 30) !== '') {
            $c = $map($s('category', 30), ['teaching' => 'teaching', 'non_teaching' => 'non_teaching', 'nonteaching' => 'non_teaching', 'non' => 'non_teaching']);
            if ($c) {
                $set['category'] = $c;
            } else {
                $e[] = 'category must be Teaching or Non-teaching';
            }
        }
        if ($s('employment', 30) !== '') {
            $c = $map($s('employment', 30), ['permanent' => 'permanent', 'contract' => 'contract', 'casual' => 'casual', 'national_service' => 'national_service', 'nss' => 'national_service', 'service' => 'national_service']);
            if ($c) {
                $set['employment'] = $c;
            } else {
                $e[] = 'employment must be Permanent, Contract, Casual or National Service';
            }
        }
        foreach (['start_date' => 'start date', 'ntc_expiry' => 'NTC licence expiry'] as $f => $label) {
            if ($s($f, 30) !== '') {
                $dt = SheetReader::date($s($f, 30));
                if ($dt) {
                    $set[$f] = $dt;
                } else {
                    $e[] = "$label is not a valid date";
                }
            }
        }
        if ($s('basic_salary', 30) !== '') {
            $amt = str_replace([',', 'GH₵', 'GHS', 'GHC', ' '], '', $s('basic_salary', 30));
            if (is_numeric($amt) && (float) $amt >= 0 && (float) $amt <= 200000) {
                $set['basic_salary'] = round((float) $amt, 2);
            } else {
                $e[] = 'basic salary must be an amount in GH₵';
            }
        }
        foreach (['ssnit_no' => 30, 'tin' => 30, 'ntc_licence_no' => 30, 'bank_name' => 80, 'bank_branch' => 80, 'account_no' => 40] as $f => $len) {
            if ($s($f, $len) !== '') {
                $set[$f] = $s($f, $len);
            }
        }
        if ($s('ghana_card_no', 30) !== '') {
            $gc = Payroll::normaliseGhanaCard($s('ghana_card_no', 30));
            if ($gc) {
                $set['ghana_card_no'] = $gc;
            } else {
                $e[] = 'Ghana Card number should look like GHA-123456789-0';
            }
        }
        if ($s('qualification', 80) !== '') {
            $q = self::qualificationKey($s('qualification', 80));
            if ($q) {
                $set['qualification'] = $q;
            } else {
                $e[] = 'qualification "' . $s('qualification', 80) . '" not recognised (e.g. Cert A, Diploma in Basic Education, B.Ed, PGDE, WASSCE)';
            }
        }
        if ($s('pay_method', 30) !== '') {
            $pm = $map($s('pay_method', 30), ['bank' => 'bank', 'mobile_money' => 'momo', 'momo' => 'momo', 'cash' => 'cash']);
            if ($pm) {
                $set['pay_method'] = $pm;
            } else {
                $e[] = 'pay method must be Bank, Mobile Money or Cash';
            }
        }
        if ($s('momo_no', 30) !== '') {
            $p = SheetReader::phone($s('momo_no', 30));
            if ($p) {
                $set['momo_no'] = $p;
            } else {
                $e[] = 'MoMo number is not a Ghana number';
            }
        }
        if ($s('login_email', 190) !== '') {
            $uid = (int) db()->value("SELECT id FROM users WHERE email = ? AND role NOT IN ('parent','pupil')", [mb_strtolower($s('login_email', 190))]);
            if (!$uid) {
                $e[] = 'no staff login with email ' . $s('login_email', 190);
            } elseif (db()->value('SELECT id FROM staff WHERE user_id = ? AND id <> ?', [$uid, (int) ($existing['id'] ?? 0)]) || isset($seen['uid:' . $uid])) {
                $e[] = 'that login is already linked to another staff record';
            } else {
                $set['user_id'] = $uid;
                $seen['uid:' . $uid] = $r['_line'];
            }
        }
        // The record as it will be after import, to apply the same rules as the staff form
        $after = $set + ($existing ?? []) + ['category' => 'teaching', 'employment' => 'permanent', 'pay_method' => 'cash'];
        if (!$existing) {
            if (empty($set['position'])) {
                $e[] = 'position is missing';
            }
            if (!isset($set['basic_salary'])) {
                $e[] = 'basic salary is missing';
            }
            if (!isset($set['pay_method'])) {
                $set['pay_method'] = !empty($set['account_no']) ? 'bank' : (!empty($set['momo_no']) ? 'momo' : 'cash');
                $after['pay_method'] = $set['pay_method'];
            }
            $dupe = db()->value('SELECT staff_no FROM staff WHERE LOWER(name) = ?', [mb_strtolower($d['name'])]);
            if ($dupe) {
                $e[] = "already in staff records as $dupe — put $dupe in the staff_no column to update it";
            } elseif (isset($seen['name:' . mb_strtolower($d['name'])])) {
                $e[] = 'same name appears twice in the file';
            }
            $seen['name:' . mb_strtolower($d['name'])] = $r['_line'];
        } elseif (isset($seen['no:' . $d['staff_no']])) {
            $e[] = 'staff no. appears twice in the file';
        }
        if ($d['staff_no'] !== '') {
            $seen['no:' . $d['staff_no']] = $r['_line'];
        }
        if (($after['pay_method'] ?? '') === 'bank' && (empty($after['bank_name']) || empty($after['account_no']))) {
            $e[] = 'bank name and account number are needed for bank payment';
        }
        if (($after['pay_method'] ?? '') === 'momo' && empty($after['momo_no'])) {
            $e[] = 'MoMo number is needed for Mobile Money payment';
        }
        if (!empty($after['ntc_licence_no']) && empty($after['ntc_expiry'])) {
            $e[] = 'NTC licence expiry date is needed';
        }
        // Columns shown on the check screen
        $d += ['gender' => isset($set['gender']) ? ucfirst($set['gender']) : '', 'phone' => $set['phone'] ?? '', 'position' => $set['position'] ?? ($existing['position'] ?? ''),
               'category' => isset($set['category']) ? Payroll::CATEGORY[$set['category']] : '', 'employment' => isset($set['employment']) ? Payroll::EMPLOYMENT[$set['employment']] : ''];
        if ($existing) {
            $d['name'] = ($d['name'] !== '' ? $d['name'] : $existing['name']) . ' (update: ' . ($set ? implode(', ', array_map(fn($k) => str_replace('_', ' ', $k), array_keys($set))) : 'nothing to change') . ')';
        }
        $d['set'] = $set;
        return [$d, $e];
    }

    /**
     * Check every row. Returns rows with 'errors' (array) and 'data' (clean values).
     * Duplicates inside the file are detected too.
     */
    public static function validate(string $kind, array $rows): array
    {
        $out = [];
        $seen = [];
        $classes = [];
        foreach (db()->all('SELECT c.id, c.name, c.level_id, l.code, l.name AS level_name FROM classes c JOIN levels l ON l.id = c.level_id WHERE c.is_active = 1') as $c) {
            $classes[mb_strtolower($c['name'])] = $c;
            $classes[mb_strtolower(str_replace(' ', '', $c['name']))] = $c;
        }
        foreach ($rows as $r) {
            $e = [];
            $d = [];
            if ($kind === 'pupils') {
                foreach (['first_name', 'last_name', 'guardian_name'] as $f) {
                    $d[$f] = mb_substr((string) ($r[$f] ?? ''), 0, $f === 'guardian_name' ? 150 : 80);
                    if ($d[$f] === '') {
                        $e[] = str_replace('_', ' ', $f) . ' is missing';
                    }
                }
                $d['middle_name'] = mb_substr((string) ($r['middle_name'] ?? ''), 0, 80) ?: null;
                $g = strtolower(substr((string) ($r['gender'] ?? ''), 0, 1));
                $d['gender'] = $g === 'm' ? 'male' : ($g === 'f' ? 'female' : null);
                if (!$d['gender']) {
                    $e[] = 'gender must be M or F';
                }
                $d['date_of_birth'] = SheetReader::date((string) ($r['date_of_birth'] ?? $r['dob'] ?? ''));
                if (!$d['date_of_birth'] || $d['date_of_birth'] > date('Y-m-d') || $d['date_of_birth'] < date('Y-m-d', strtotime('-25 years'))) {
                    $e[] = 'date of birth missing or not a valid date';
                }
                $cls = mb_strtolower(trim((string) ($r['class'] ?? '')));
                $c = $classes[$cls] ?? $classes[str_replace(' ', '', $cls)] ?? null;
                if (!$c) {
                    $e[] = 'class "' . ($r['class'] ?? '') . '" not found in School setup';
                }
                $d['class_id'] = $c['id'] ?? null;
                $d['class_name'] = $c['name'] ?? '';
                $d['admission_no'] = strtoupper(trim((string) ($r['admission_no'] ?? ''))) ?: null;
                if ($d['admission_no'] && db()->value('SELECT id FROM pupils WHERE admission_no = ?', [$d['admission_no']])) {
                    $e[] = 'admission no. ' . $d['admission_no'] . ' already exists';
                }
                $d['admitted_on'] = SheetReader::date((string) ($r['admitted_on'] ?? '')) ?: date('Y-m-d');
                $d['guardian_phone'] = SheetReader::phone((string) ($r['guardian_phone'] ?? ''));
                if (!$d['guardian_phone']) {
                    $e[] = 'guardian phone missing or not a Ghana mobile number';
                }
                $d['guardian_relationship'] = mb_substr((string) ($r['guardian_relationship'] ?? ''), 0, 40) ?: 'Parent';
                $em = (string) ($r['guardian_email'] ?? '');
                $d['guardian_email'] = filter_var($em, FILTER_VALIDATE_EMAIL) ? strtolower($em) : null;
                foreach (['guardian_occupation' => 100, 'address' => 255, 'hometown' => 80, 'house' => 40, 'denomination' => 80, 'allergies' => 255, 'medical_notes' => 2000, 'previous_school' => 150] as $f => $len) {
                    $d[$f] = mb_substr((string) ($r[$f] ?? ''), 0, $len) ?: null;
                }
                $d['guardian2_name'] = mb_substr((string) ($r['guardian2_name'] ?? ''), 0, 150) ?: null;
                $d['guardian2_phone'] = $d['guardian2_name'] ? SheetReader::phone((string) ($r['guardian2_phone'] ?? '')) : null;
                if ($d['guardian2_name'] && !$d['guardian2_phone']) {
                    $e[] = 'second guardian phone missing or invalid';
                }
                $d['guardian2_relationship'] = mb_substr((string) ($r['guardian2_relationship'] ?? ''), 0, 40) ?: 'Parent';
                $sen = self::senKey((string) ($r['special_needs'] ?? ''));
                if ($sen === null) {
                    $e[] = 'special needs "' . ($r['special_needs'] ?? '') . '" not recognised (e.g. Visual, Hearing, Physical, Learning, Speech, Autism, Other)';
                }
                $d['sen_category'] = $sen ?: null;
                $d['sen_support'] = $d['sen_category'] ? (mb_substr((string) ($r['special_needs_support'] ?? ''), 0, 500) ?: null) : null;
                if (!$e) {
                    $dupKey = mb_strtolower($d['first_name'] . '|' . $d['last_name'] . '|' . $d['date_of_birth']);
                    if (isset($seen[$dupKey])) {
                        $e[] = 'same student appears twice in the file (row ' . $seen[$dupKey] . ')';
                    } elseif (db()->value('SELECT id FROM pupils WHERE first_name = ? AND last_name = ? AND date_of_birth = ? AND deleted_at IS NULL', [$d['first_name'], $d['last_name'], $d['date_of_birth']])) {
                        $e[] = 'already in the system (same name and date of birth)';
                    }
                    $seen[$dupKey] = $r['_line'];
                    if ($d['admission_no']) {
                        if (isset($seen['adm:' . $d['admission_no']])) {
                            $e[] = 'admission no. repeated in the file';
                        }
                        $seen['adm:' . $d['admission_no']] = $r['_line'];
                    }
                }
            } elseif ($kind === 'balances') {
                $adm = strtoupper(trim((string) ($r['admission_no'] ?? '')));
                $p = $adm !== '' ? db()->one('SELECT p.id, p.first_name, p.last_name FROM pupils p WHERE p.admission_no = ? AND p.deleted_at IS NULL', [$adm]) : null;
                if (!$p) {
                    $e[] = 'no student with admission no. "' . $adm . '"';
                }
                $amt = round((float) str_replace([',', 'GH₵', 'GHS', ' '], '', (string) ($r['amount'] ?? '')), 2);
                if ($amt <= 0 || $amt > 100000) {
                    $e[] = 'amount must be above 0';
                }
                $d = ['pupil_id' => $p['id'] ?? null, 'pupil' => $p ? $p['first_name'] . ' ' . $p['last_name'] : '', 'admission_no' => $adm, 'amount' => $amt,
                      'description' => mb_substr((string) ($r['description'] ?? ''), 0, 150) ?: 'Balance brought forward', 'due_date' => SheetReader::date((string) ($r['due_date'] ?? ''))];
                if ($p && !$e) {
                    if (db()->value("SELECT id FROM invoices WHERE pupil_id = ? AND kind = 'other' AND title = ? AND status <> 'cancelled'", [$p['id'], $d['description']])) {
                        $e[] = 'this balance was already imported for the student';
                    } elseif (isset($seen[$adm])) {
                        $e[] = 'student appears twice in the file';
                    }
                    $seen[$adm] = $r['_line'];
                }
            } elseif ($kind === 'staff_records') {
                [$d, $e] = self::staffRecordRow($r, $seen);
            } else { // staff
                $roles = [];
                foreach (Auth::roles('staff') as $k => $l) {
                    if ($k !== 'super_admin') {
                        $roles[mb_strtolower($l)] = $k;
                        $roles[$k] = $k;
                        $roles[mb_strtolower(explode(' / ', $l)[0])] = $k;
                    }
                }
                $d['name'] = mb_substr((string) ($r['name'] ?? ''), 0, 150);
                $d['email'] = strtolower((string) ($r['email'] ?? ''));
                $d['phone'] = SheetReader::phone((string) ($r['phone'] ?? ''));
                $d['role'] = $roles[mb_strtolower(trim((string) ($r['role'] ?? '')))] ?? $roles[str_replace(' ', '_', mb_strtolower(trim((string) ($r['role'] ?? ''))))] ?? null;
                if ($d['name'] === '') {
                    $e[] = 'name is missing';
                }
                if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
                    $e[] = 'email missing or invalid';
                } elseif (db()->value('SELECT id FROM users WHERE email = ?', [$d['email']]) || isset($seen[$d['email']])) {
                    $e[] = 'email already used';
                }
                if (!$d['role']) {
                    $e[] = 'role "' . ($r['role'] ?? '') . '" not recognised';
                }
                $seen[$d['email']] = $r['_line'];
            }
            $out[] = ['line' => $r['_line'], 'data' => $d, 'errors' => $e];
        }
        return $out;
    }

    /** Import the valid rows. Returns [imported, skipped, slips] (slips = staff passwords to print once). */
    public static function run(string $kind, array $rows): array
    {
        $done = 0;
        $skipped = 0;
        $slips = [];
        $year = current_year();
        foreach (self::validate($kind, $rows) as $v) {
            if ($v['errors']) {
                $skipped++;
                continue;
            }
            $d = $v['data'];
            if ($kind === 'pupils') {
                db()->transaction(function (Database $db) use ($d, $year) {
                    $adm = $d['admission_no'] ?: Admission::nextAdmissionNo($db, substr((string) $year['name'], 0, 4));
                    $pid = $db->insert('pupils', [
                        'admission_no' => $adm, 'first_name' => $d['first_name'], 'middle_name' => $d['middle_name'], 'last_name' => $d['last_name'],
                        'gender' => $d['gender'], 'date_of_birth' => $d['date_of_birth'], 'admitted_on' => $d['admitted_on'],
                        'address' => $d['address'], 'hometown' => $d['hometown'], 'house' => $d['house'], 'denomination' => $d['denomination'],
                        'allergies' => $d['allergies'], 'medical_notes' => $d['medical_notes'], 'previous_school' => $d['previous_school'],
                        'sen_category' => $d['sen_category'], 'sen_support' => $d['sen_support'],
                    ]);
                    $guardians = [[$d['guardian_name'], $d['guardian_phone'], $d['guardian_relationship'], $d['guardian_email'], $d['guardian_occupation'], 1]];
                    if ($d['guardian2_name']) {
                        $guardians[] = [$d['guardian2_name'], $d['guardian2_phone'], $d['guardian2_relationship'], null, null, 0];
                    }
                    foreach ($guardians as [$name, $phone, $rel, $email, $occ, $primary]) {
                        // Same phone = same parent (siblings share the guardian record and parent login)
                        $gid = (int) $db->value('SELECT id FROM guardians WHERE phone = ? ORDER BY id LIMIT 1', [$phone]);
                        if (!$gid) {
                            $gid = $db->insert('guardians', ['name' => $name, 'phone' => $phone, 'email' => $email, 'occupation' => $occ, 'address' => $d['address']]);
                        }
                        $db->run('INSERT IGNORE INTO pupil_guardian (pupil_id, guardian_id, relationship, is_primary) VALUES (?, ?, ?, ?)', [$pid, $gid, $rel, $primary]);
                    }
                    $db->insert('enrolments', ['pupil_id' => $pid, 'class_id' => $d['class_id'], 'academic_year_id' => $year['id'], 'enrolled_on' => max($d['admitted_on'], $year['start_date'])]);
                });
            } elseif ($kind === 'balances') {
                db()->transaction(function (Database $db) use ($d, $year) {
                    $class = $db->value('SELECT class_id FROM enrolments WHERE pupil_id = ? AND academic_year_id = ?', [$d['pupil_id'], $year['id'] ?? 0]);
                    $iid = Fees::insertInvoice($db, ['id' => $d['pupil_id'], 'class_id' => $class ?: null], current_term(), 'other', $d['description'], $d['due_date']);
                    $db->insert('invoice_lines', ['invoice_id' => $iid, 'description' => $d['description'], 'amount' => $d['amount']]);
                    Fees::recalc($iid, $db);
                });
            } elseif ($kind === 'staff_records') {
                db()->transaction(function (Database $db) use ($d) {
                    if ($d['update_id']) {
                        if ($d['set']) {
                            $db->update('staff', $d['set'], 'id = ?', [$d['update_id']]);
                        }
                        return;
                    }
                    $db->insert('staff', $d['set'] + ['staff_no' => Payroll::nextStaffNo($db)]);
                });
            } else {
                $pw = Accounts::tempPassword(8);
                db()->insert('users', [
                    'name' => $d['name'], 'email' => $d['email'], 'phone' => $d['phone'], 'role' => $d['role'],
                    'password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'must_change_password' => 1, 'is_active' => 1,
                ]);
                $slips[] = ['name' => $d['name'], 'who' => Auth::roleLabel($d['role']), 'username' => $d['email'], 'password' => $pw];
            }
            $done++;
        }
        return [$done, $skipped, $slips];
    }
}
