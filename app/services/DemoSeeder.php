<?php
declare(strict_types=1);

/**
 * Builds the PRACTICE COPY: a separate database (<name>_demo) filled with realistic,
 * made-up records so staff can train without touching real data.
 * Every login in the practice copy uses the password DemoPass@2026.
 */
final class DemoSeeder
{
    public const PASSWORD = 'DemoPass@2026';

    private Database $d;

    public static function dbName(): string
    {
        $base = (string) config('db.name');
        return str_ends_with($base, '_demo') ? $base : $base . '_demo';
    }

    public static function exists(): bool
    {
        try {
            $c = config('db');
            $pdo = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $c['host'], (int) $c['port']), $c['user'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = 'users'");
            $st->execute([self::dbName()]);
            return (int) $st->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    /** Delete the practice copy's database (training finished). */
    public static function remove(): void
    {
        $c = config('db');
        $name = self::dbName();
        if ($name === $c['name']) {
            throw new RuntimeException('Refusing to remove the real database.');
        }
        $d = Database::connect(['name' => $name] + $c);
        $d->run('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($d->pdo()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
            $d->run('DROP TABLE `' . str_replace('`', '', $t) . '`');
        }
        $d->run('SET FOREIGN_KEY_CHECKS = 1');
    }

    /** Create (or wipe and re-create) the practice copy. Returns a summary line. */
    public static function build(): string
    {
        @set_time_limit(300);
        $c = config('db');
        $name = self::dbName();
        if ($name === $c['name']) {
            throw new RuntimeException('Refusing to seed the real database.');
        }
        $server = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $c['host'], (int) $c['port']), $c['user'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $q = '`' . str_replace('`', '', $name) . '`';
        try {
            $server->exec("DROP DATABASE IF EXISTS $q");
            $server->exec("CREATE DATABASE $q CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (Throwable $e) {
            // Hosting accounts often cannot create databases: empty an existing one instead
            try {
                $pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], (int) $c['port'], $name), $c['user'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            } catch (Throwable $e2) {
                throw new RuntimeException("Could not create the database \"$name\". On hosting, create an empty database with exactly that name (same user and password) in cPanel, then try again.");
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
                $pdo->exec('DROP TABLE `' . str_replace('`', '', $t) . '`');
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
        $d = Database::connect(['name' => $name] + $c);
        Migrator::run($d->pdo());
        $s = new self($d);
        $summary = $d->transaction(fn() => $s->seed());
        // A full time table for every class (the same generator the school uses), so profiles and substitutes have data
        Database::using($d, function () use ($d) {
            Auth::flushCache();
            Timetable::generate(array_map('intval', array_column($d->all('SELECT id FROM classes WHERE is_active = 1'), 'id')), 'replace');
        });
        Auth::flushCache();
        foreach (['storage/demo_uploads', 'storage/demo_backups'] as $dir) {
            if (!is_dir(BASE_PATH . '/' . $dir)) {
                @mkdir(BASE_PATH . '/' . $dir, 0775, true);
            }
        }
        return $summary;
    }

    private function __construct(Database $d)
    {
        $this->d = $d;
        mt_srand(2026);
    }

    private const BOYS = ['Kofi', 'Kwame', 'Kwabena', 'Kwaku', 'Yaw', 'Kwesi', 'Kojo', 'Kodwo', 'Emmanuel', 'Daniel', 'Samuel', 'Prince', 'Michael', 'Isaac', 'Joseph', 'Elikem', 'Selorm', 'Nana', 'Fiifi', 'Ebo', 'Nii', 'Joshua', 'Caleb', 'Godwin'];
    private const GIRLS = ['Ama', 'Akosua', 'Adwoa', 'Abena', 'Akua', 'Yaa', 'Afua', 'Esi', 'Efua', 'Adjoa', 'Araba', 'Abigail', 'Grace', 'Priscilla', 'Mercy', 'Esther', 'Naa', 'Dzifa', 'Elorm', 'Deborah', 'Comfort', 'Gifty', 'Ruth', 'Hannah'];
    private const SURNAMES = ['Mensah', 'Owusu', 'Boateng', 'Asante', 'Osei', 'Agyeman', 'Addo', 'Darko', 'Appiah', 'Amoah', 'Ofori', 'Quaye', 'Tetteh', 'Annan', 'Acheampong', 'Ansah', 'Frimpong', 'Nkrumah', 'Badu', 'Gyamfi', 'Kumi', 'Sarpong', 'Danso', 'Quartey', 'Adjei', 'Lamptey', 'Amponsah', 'Bonsu', 'Ocran', 'Agbeko'];
    private const DENOMS = ['Seventh-day Adventist', 'Seventh-day Adventist', 'Seventh-day Adventist', 'Methodist', 'Catholic', 'Presbyterian', 'Church of Pentecost', 'Anglican', 'Islam'];
    private const TOWNS = ['Kasoa', 'Ofankor', 'Pokuase', 'Amasaman', 'Dome', 'Taifa', 'Achimota', 'Madina', 'Adenta', 'Weija'];

    private function pick(array $a)
    {
        return $a[mt_rand(0, count($a) - 1)];
    }

    private function seed(): string
    {
        $d = $this->d;
        $hash = password_hash(self::PASSWORD, PASSWORD_DEFAULT);
        $set = fn($k, $v) => $d->run('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)', [$k, $v]);

        foreach ([
            'school_name' => 'Naylor International S.D.A School (PRACTICE)', 'school_motto' => 'Training copy — not real records',
            'school_address' => 'P. O. Box 123, Kasoa, Central Region', 'school_phone' => '0302 000 000', 'school_email' => 'office@example.com',
            'sms_provider' => 'log', 'pay_gateway' => 'test', 'fee_schedule_confirmed' => '1', 'backup_auto' => '0',
        ] as $k => $v) {
            $set($k, $v);
        }

        // ---- year and terms (typical GES dates), current term by today's date
        $start = (int) date('n') >= 8 ? (int) date('Y') : (int) date('Y') - 1;
        $yid = $d->insert('academic_years', ['name' => $start . '/' . ($start + 1), 'start_date' => "$start-09-08", 'end_date' => ($start + 1) . '-07-24', 'is_current' => 1]);
        $terms = [[1, "$start-09-08", "$start-12-18"], [2, ($start + 1) . '-01-08', ($start + 1) . '-04-02'], [3, ($start + 1) . '-04-28', ($start + 1) . '-07-24']];
        $today = date('Y-m-d');
        $curNo = 3;
        foreach ($terms as [$no, $ts, $te]) {
            if ($today <= $te) {
                $curNo = min($curNo, $no);
            }
        }
        $termIds = [];
        foreach ($terms as [$no, $ts, $te]) {
            $termIds[$no] = $d->insert('terms', ['academic_year_id' => $yid, 'term_no' => $no, 'name' => "Term $no", 'start_date' => $ts, 'end_date' => $te, 'is_current' => $no === $curNo ? 1 : 0]);
        }
        $term = $d->one('SELECT t.*, y.name AS year_name FROM terms t JOIN academic_years y ON y.id = t.academic_year_id WHERE t.id = ?', [$termIds[$curNo]]);

        // ---- staff accounts
        $user = function (string $name, string $email, string $role, ?string $phone = null) use ($d, $hash): int {
            return $d->insert('users', ['name' => $name, 'email' => $email, 'phone' => $phone, 'role' => $role, 'password_hash' => $hash, 'must_change_password' => 0]);
        };
        $admin = $user('Practice Administrator', 'admin@demo.school', 'super_admin');
        $head = $user('Mrs. Comfort Asante (Head)', 'head@demo.school', 'head_teacher', '0200000001');
        $coord = $user('Mr. Isaac Ofori (Coordinator)', 'coordinator@demo.school', 'academic_coordinator', '0200000002');
        $bursar = $user('Mr. Samuel Addo (Bursar)', 'bursar@demo.school', 'bursar', '0200000003');
        $user('Miss Gifty Badu (Admissions)', 'admissions@demo.school', 'admissions_officer', '0200000004');

        $classes = $d->all('SELECT c.id, c.name, l.category, l.code, l.sort_order FROM classes c JOIN levels l ON l.id = c.level_id WHERE c.is_active = 1 ORDER BY l.sort_order');
        $teachers = [];
        foreach ($classes as $i => $c) {
            $female = $i % 3 !== 2;
            $nm = ($female ? 'Mrs. ' . $this->pick(self::GIRLS) : 'Mr. ' . $this->pick(self::BOYS)) . ' ' . $this->pick(self::SURNAMES);
            $teachers[$c['id']] = $user($nm, 'teacher' . ($i + 1) . '@demo.school', 'class_teacher', sprintf('02000001%02d', $i + 1));
            $d->update('classes', ['class_teacher_id' => $teachers[$c['id']]], 'id = ?', [$c['id']]);
        }
        $subjTeachers = [];
        foreach (['Mr. Kwesi Annan (Maths & Science)', 'Mrs. Esi Quaye (English & French)', 'Mr. Nii Lamptey (Social Studies & RME)', 'Miss Dzifa Agbeko (Computing & CAD)'] as $i => $nm) {
            $subjTeachers[] = $user($nm, 'subject' . ($i + 1) . '@demo.school', 'subject_teacher', sprintf('02000002%02d', $i + 1));
        }

        // ---- subjects per class; lower classes taught by the class teacher, JHS by subject teachers
        $d->run('INSERT IGNORE INTO class_subjects (class_id, subject_id) SELECT c.id, s.id FROM classes c JOIN levels l ON l.id = c.level_id JOIN subjects s ON s.is_active = 1 AND FIND_IN_SET(l.category, s.applies_to) > 0 WHERE c.is_active = 1');
        $d->run("UPDATE class_subjects cs JOIN classes c ON c.id = cs.class_id JOIN levels l ON l.id = c.level_id SET cs.teacher_id = c.class_teacher_id WHERE l.category <> 'jhs'");
        $map = ['MATH' => 0, 'SCI' => 0, 'ENG' => 1, 'FRE' => 1, 'SOC' => 2, 'RME' => 2, 'COMP' => 3, 'CAD' => 3, 'CT' => 3];
        foreach ($d->all("SELECT cs.id, s.code, c.class_teacher_id FROM class_subjects cs JOIN subjects s ON s.id = cs.subject_id JOIN classes c ON c.id = cs.class_id JOIN levels l ON l.id = c.level_id WHERE l.category = 'jhs'") as $r) {
            $d->update('class_subjects', ['teacher_id' => isset($map[$r['code']]) ? $subjTeachers[$map[$r['code']]] : $r['class_teacher_id']], 'id = ?', [$r['id']]);
        }

        // ---- pupils, parents (shared by siblings), enrolments
        $ageFor = ['creche' => 2, 'kg' => 4, 'primary' => 6, 'jhs' => 12];
        $seq = 0;
        $pupils = [];
        $guardians = [];
        foreach ($classes as $ci => $c) {
            $n = $c['category'] === 'creche' ? 8 : mt_rand(10, 14);
            $baseAge = $c['category'] === 'kg' ? 4 + ($c['code'] === 'KG2' ? 1 : 0) : ($c['category'] === 'primary' ? 5 + (int) substr($c['code'], 1) : ($c['category'] === 'jhs' ? 11 + (int) substr($c['code'], 3) : 2));
            for ($k = 0; $k < $n; $k++) {
                $male = mt_rand(0, 1) === 1;
                $sur = $this->pick(self::SURNAMES);
                // Every 6th pupil is a sibling of an earlier pupil with the same surname
                $g = null;
                if ($guardians && mt_rand(1, 6) === 1) {
                    $g = $guardians[array_rand($guardians)];
                    $sur = $g['surname'];
                }
                if (!$g) {
                    $gid = $d->insert('guardians', [
                        'name' => (mt_rand(0, 1) ? 'Mr. ' . $this->pick(self::BOYS) : 'Mrs. ' . $this->pick(self::GIRLS)) . ' ' . $sur,
                        'phone' => sprintf('020%07d', 1000 + count($guardians)), 'occupation' => $this->pick(['Trader', 'Teacher', 'Nurse', 'Driver', 'Civil servant', 'Farmer', 'Seamstress', 'Mechanic', 'Banker', 'Pastor']),
                        'address' => $this->pick(self::TOWNS),
                    ]);
                    $g = ['id' => $gid, 'surname' => $sur];
                    $guardians[] = $g;
                }
                $seq++;
                $dob = ($start - $baseAge) . sprintf('-%02d-%02d', mt_rand(1, 12), mt_rand(1, 28));
                $pid = $d->insert('pupils', [
                    'admission_no' => sprintf('NIS/%d/%04d', $start - mt_rand(0, min(5, $ci)), $seq), 'first_name' => $male ? $this->pick(self::BOYS) : $this->pick(self::GIRLS),
                    'last_name' => $sur, 'gender' => $male ? 'male' : 'female', 'date_of_birth' => $dob, 'denomination' => $this->pick(self::DENOMS),
                    'nationality' => 'Ghanaian', 'hometown' => $this->pick(self::TOWNS), 'address' => $this->pick(self::TOWNS),
                    'allergies' => mt_rand(1, 15) === 1 ? $this->pick(['Groundnuts', 'Penicillin', 'Seafood']) : null,
                    'sen_category' => ($sen = mt_rand(1, 30) === 1 ? $this->pick(['visual', 'hearing', 'learning', 'speech']) : null),
                    'sen_support' => $sen ? $this->pick(['Sits in the front row', 'Extra time in tests', 'Large-print worksheets', 'Speech therapy on Fridays']) : null,
                    'house' => $this->pick(['Blue', 'Green', 'Red', 'Yellow']), 'admitted_on' => ($start - mt_rand(0, min(5, $ci))) . '-09-08',
                ]);
                $d->insert('pupil_guardian', ['pupil_id' => $pid, 'guardian_id' => $g['id'], 'relationship' => 'Parent', 'is_primary' => 1]);
                $d->insert('enrolments', ['pupil_id' => $pid, 'class_id' => $c['id'], 'academic_year_id' => $yid, 'enrolled_on' => "$start-09-08"]);
                $pupils[] = ['id' => $pid, 'class_id' => $c['id'], 'category' => $c['category'], 'code' => $c['code'], 'level_sort' => $c['sort_order']];
            }
        }

        // A parent login to try the portal: username = phone of the first parent
        // (a family with a child in Basic 5 or above, so the portal shows results and the progress chart)
        $firstG = $d->one("SELECT g.* FROM guardians g JOIN pupil_guardian pg ON pg.guardian_id = g.id JOIN enrolments e ON e.pupil_id = pg.pupil_id JOIN classes c ON c.id = e.class_id JOIN levels l ON l.id = c.level_id
                           WHERE l.category IN ('primary','jhs') AND l.sort_order >= (SELECT sort_order FROM levels WHERE code = 'B5' LIMIT 1)
                           ORDER BY (SELECT COUNT(*) FROM pupil_guardian x WHERE x.guardian_id = g.id) DESC, g.id LIMIT 1")
            ?: $d->one('SELECT * FROM guardians ORDER BY id LIMIT 1');
        // Keep the practice parent's username the same (0200001000): swap phone numbers with the first family
        $g0 = $d->one('SELECT * FROM guardians ORDER BY id LIMIT 1');
        if ((int) $g0['id'] !== (int) $firstG['id']) {
            $d->update('guardians', ['phone' => $firstG['phone']], 'id = ?', [$g0['id']]);
            $d->update('guardians', ['phone' => $g0['phone']], 'id = ?', [$firstG['id']]);
            $firstG['phone'] = $g0['phone'];
        }
        $pu = $d->insert('users', ['name' => $firstG['name'], 'email' => 'parent1@parents.demo', 'username' => $firstG['phone'], 'phone' => $firstG['phone'], 'role' => 'parent', 'password_hash' => $hash]);
        $d->update('guardians', ['user_id' => $pu], 'id = ?', [$firstG['id']]);

        // ---- attendance: every school day of the current term so far
        $days = [];
        for ($t = strtotime($term['start_date']); $t <= min(strtotime($today), strtotime($term['end_date'])); $t += 86400) {
            if ((int) date('N', $t) <= 5) {
                $days[] = date('Y-m-d', $t);
            }
        }
        $byClass = [];
        foreach ($pupils as $p) {
            $byClass[$p['class_id']][] = $p['id'];
        }
        foreach ($byClass as $cid => $pids) {
            foreach ($days as $day) {
                $rid = $d->insert('attendance_registers', ['class_id' => $cid, 'term_id' => $term['id'], 'date' => $day, 'taken_by' => $teachers[$cid] ?? null]);
                $vals = [];
                foreach ($pids as $pid) {
                    $r = mt_rand(1, 100);
                    $st = $r <= 88 ? 'present' : ($r <= 93 ? 'late' : ($r <= 98 ? 'absent' : 'sick'));
                    $vals[] = '(' . $rid . ',' . $pid . ",'" . $day . "','" . $st . "')";
                }
                $d->run('INSERT INTO attendance_records (register_id, pupil_id, date, status) VALUES ' . implode(',', $vals));
            }
        }

        // ---- bills for the current term from the fee schedule, and payments (posted to the cash book)
        $feeCat = $d->value("SELECT id FROM fin_categories WHERE code = 'FEES'");
        $acct = ['cash' => (int) $d->value("SELECT id FROM fin_accounts WHERE kind = 'cash' LIMIT 1"), 'momo' => (int) $d->value("SELECT id FROM fin_accounts WHERE kind = 'momo' LIMIT 1"), 'bank' => (int) $d->value("SELECT id FROM fin_accounts WHERE kind = 'bank' LIMIT 1")];
        $d->run("UPDATE fin_accounts SET opening_balance = CASE kind WHEN 'cash' THEN 1500 WHEN 'bank' THEN 42000 WHEN 'momo' THEN 3200 ELSE 0 END, opening_date = ?", [$term['start_date']]);
        $levelOf = [];
        foreach ($d->all('SELECT c.id, c.level_id FROM classes c') as $r) {
            $levelOf[$r['id']] = $r['level_id'];
        }
        $freq = (int) $term['term_no'] === 1 ? "('termly','yearly')" : "('termly')";
        $inv = 0;
        $rct = 0;
        $txn = 0;
        foreach ($pupils as $p) {
            $items = $d->all("SELECT f.id, f.name, fs.amount FROM fee_structures fs JOIN fee_items f ON f.id = fs.fee_item_id WHERE fs.level_id = ? AND f.is_active = 1 AND f.frequency IN $freq AND fs.amount > 0 ORDER BY f.sort_order", [$levelOf[$p['class_id']]]);
            if (!$items) {
                continue;
            }
            $inv++;
            $total = array_sum(array_column($items, 'amount'));
            $iid = $d->insert('invoices', [
                'invoice_no' => sprintf('INV-%s-%05d', date('Y'), $inv), 'public_key' => bin2hex(random_bytes(12)), 'pupil_id' => $p['id'], 'term_id' => $term['id'],
                'class_id' => $p['class_id'], 'kind' => 'term', 'title' => $term['name'] . ' fees ' . $term['year_name'], 'issue_date' => $term['start_date'],
                'due_date' => date('Y-m-d', strtotime($term['start_date'] . ' +21 days')), 'subtotal' => $total, 'total' => $total, 'created_by' => $bursar,
            ]);
            foreach ($items as $it) {
                $d->insert('invoice_lines', ['invoice_id' => $iid, 'fee_item_id' => $it['id'], 'description' => $it['name'], 'amount' => $it['amount']]);
            }
            $r = mt_rand(1, 100);
            $paid = $r <= 45 ? $total : ($r <= 80 ? round($total * mt_rand(30, 80) / 100, -1) : 0.0);
            $left = $paid;
            $parts = $paid > 0 ? mt_rand(1, 2) : 0;
            for ($k = 0; $k < $parts; $k++) {
                $amt = $k === $parts - 1 ? $left : round($left / 2, -1);
                $left -= $amt;
                $method = $this->pick(['cash', 'cash', 'momo', 'momo', 'bank']);
                $on = $days ? $days[mt_rand(0, max(0, min(count($days) - 1, 25)))] : $term['start_date'];
                $rct++;
                $no = sprintf('RCT-%s-%05d', date('Y'), $rct);
                $payId = $d->insert('payments', ['receipt_no' => $no, 'invoice_id' => $iid, 'pupil_id' => $p['id'], 'amount' => $amt, 'method' => $method,
                    'reference' => $method === 'cash' ? null : 'REF' . mt_rand(100000, 999999), 'paid_on' => $on, 'received_by' => $bursar]);
                $d->insert('fin_transactions', ['txn_no' => $no, 'kind' => 'income', 'txn_date' => $on, 'account_id' => $acct[$method], 'category_id' => $feeCat, 'amount' => $amt,
                    'description' => 'Fees (' . sprintf('INV-%s-%05d', date('Y'), $inv) . ')', 'term_id' => $term['id'], 'source' => 'fee_payment', 'source_id' => $payId, 'created_by' => $bursar]);
            }
            $d->update('invoices', ['paid' => $paid, 'status' => $paid <= 0 ? 'unpaid' : ($paid + 0.004 >= $total ? 'paid' : 'part')], 'id = ?', [$iid]);
        }
        // A few everyday expenses
        $exp = [['UTIL', 'ECG prepaid electricity', 850, 'cash'], ['TLM', 'Exercise books and chalk', 1240, 'momo'], ['FOOD', 'Kitchen supplies for the week', 960, 'cash'], ['REP', 'Repair of JHS block louvres', 640, 'cash'], ['CLEAN', 'Cleaning materials', 310, 'cash']];
        foreach ($exp as $i => [$code, $desc, $amt, $m]) {
            $txn++;
            $d->insert('fin_transactions', ['txn_no' => sprintf('TXN-%s-%05d', date('Y'), $txn), 'kind' => 'expense', 'txn_date' => $days[min(count($days) - 1, 3 + $i * 4)] ?? $term['start_date'],
                'account_id' => $acct[$m === 'momo' ? 'momo' : 'cash'], 'category_id' => $d->value('SELECT id FROM fin_categories WHERE code = ?', [$code]), 'amount' => $amt,
                'description' => $desc, 'term_id' => $term['id'], 'source' => 'manual', 'created_by' => $bursar]);
        }

        // ---- scores: two class exercises + mid-term for every Basic/JHS subject (sheets submitted, some approved)
        $scoreRows = 0;
        foreach ($d->all("SELECT cs.id, cs.class_id, cs.teacher_id FROM class_subjects cs JOIN classes c ON c.id = cs.class_id JOIN levels l ON l.id = c.level_id WHERE l.category IN ('primary','jhs')") as $cs) {
            $pids = $byClass[$cs['class_id']] ?? [];
            if (!$pids) {
                continue;
            }
            foreach ([['class', 'Class exercise 1', 20], ['class', 'Class test 1', 20], ['exam', 'End-of-term exam', 100]] as $si => [$kind, $title, $max]) {
                $aid = $d->insert('assessments', ['term_id' => $term['id'], 'class_subject_id' => $cs['id'], 'kind' => $kind, 'title' => $title, 'max_mark' => $max, 'sort_order' => $si, 'created_by' => $cs['teacher_id']]);
                $vals = [];
                foreach ($pids as $pid) {
                    $ability = 40 + ($pid * 37 % 50); // stable per pupil
                    $v = max(0, min($max, round(($ability + mt_rand(-12, 12)) * $max / 100)));
                    $vals[] = "($aid,$pid,$v)";
                    $scoreRows++;
                }
                $d->run('INSERT INTO assessment_scores (assessment_id, pupil_id, score) VALUES ' . implode(',', $vals));
            }
            $st = mt_rand(1, 3) === 1 ? 'submitted' : 'approved';
            $d->insert('score_sheets', ['term_id' => $term['id'], 'class_subject_id' => $cs['id'], 'status' => $st, 'submitted_by' => $cs['teacher_id'], 'submitted_at' => date('Y-m-d H:i:s'),
                'approved_by' => $st === 'approved' ? $coord : null, 'approved_at' => $st === 'approved' ? date('Y-m-d H:i:s') : null]);
        }

        // ---- report remarks and the Child's Personality Development Report for the current term
        $comments = ['A quick learner with excellent comprehension skills. Reads well and explains topics in own words.', 'Hardworking and attentive in class. Keep it up.',
            'Good effort this term. Must pay more attention to Mathematics.', 'Shows steady improvement. More reading at home will help.', 'Well behaved and helpful to classmates.'];
        $heads = ['Well done, keep the lamp burning.', 'Good work. Aim higher next term.', 'You can do better. Work harder.', 'Excellent! Keep it up.'];
        foreach ($pupils as $i => $p) {
            $pers = [];
            foreach (ReportController::TRAIT_SUGGESTIONS as $trait => $opts) {
                $pers[$trait] = $opts[($i + strlen($trait)) % 4 === 3 ? 3 : ($i + strlen($trait)) % 3];
            }
            $d->insert('report_remarks', ['term_id' => $term['id'], 'pupil_id' => $p['id'], 'teacher_remark' => $comments[$i % count($comments)],
                'head_remark' => $heads[$i % count($heads)], 'personality' => json_encode($pers, JSON_UNESCAPED_UNICODE), 'updated_by' => $head]);
        }

        // ---- calendar and notices
        foreach ([['Inter-house sports', 'sports', 14], ['PTA General Meeting', 'pta', 21], ['Week of Prayer', 'sda', 30], ['Mid-term break', 'holiday', 40], ['End-of-term examinations', 'exam', 70]] as [$t, $cat, $off]) {
            $sd = date('Y-m-d', strtotime($term['start_date'] . " +$off days"));
            $d->insert('calendar_events', ['title' => $t, 'category' => $cat, 'start_date' => $sd, 'end_date' => $cat === 'sda' || $cat === 'exam' ? date('Y-m-d', strtotime($sd . ' +4 days')) : $sd, 'audience' => 'all', 'term_id' => $term['id'], 'is_school_closed' => $cat === 'holiday' ? 1 : 0, 'created_by' => $head]);
        }
        $d->insert('notices', ['title' => 'Welcome to the new term', 'body' => "Dear parents,\n\nWe welcome all students back. School fees are due within three weeks of the start of term. Kindly check your child's bill on the parent portal.\n\nGod bless you.", 'category' => 'circular', 'requires_ack' => 1, 'is_pinned' => 1, 'publish_at' => $term['start_date'] . ' 08:00:00', 'created_by' => $head]);
        $prayer = $d->insert('notices', ['title' => 'Week of Prayer', 'body' => 'The school will hold its Week of Prayer. Parents are invited to the closing programme on Friday at 9 a.m.', 'category' => 'event', 'publish_at' => date('Y-m-d H:i:s', strtotime('-2 days')), 'created_by' => $head]);
        if (Notices::loginReady()) {
            $d->update('notices', ['on_login' => 1], 'id = ?', [$prayer]); // also on the sign-in page
        }

        // ---- library, transport, health
        $books = ['Ananse Stories', 'The Lion and the Jewel', 'Things Fall Apart', 'Integrated Science for JHS 1', 'Mathematics for JHS 2', 'English Grammar in Use', 'Our World Our People B4', 'The Bible Story (Vol. 1)', 'Steps to Christ', 'Atlas for Ghana Schools', 'Fun with Phonics', 'My First Twi Reader'];
        $bookIds = [];
        foreach ($books as $i => $b) {
            $bookIds[] = $d->insert('library_books', ['title' => $b, 'category' => $i < 3 ? 'Story book / reader' : ($i < 7 ? 'Textbook' : ($i < 9 ? 'Bible & devotional' : 'Reference')), 'copies' => mt_rand(2, 8), 'shelf' => chr(65 + $i % 4) . (1 + $i % 3)]);
        }
        $upper = array_values(array_filter($pupils, fn($p) => in_array($p['category'], ['primary', 'jhs'], true)));
        for ($i = 0; $i < 12 && $upper; $i++) {
            $p = $upper[mt_rand(0, count($upper) - 1)];
            $issued = date('Y-m-d', strtotime('-' . mt_rand(3, 25) . ' days'));
            $d->insert('library_loans', ['book_id' => $bookIds[$i % count($bookIds)], 'pupil_id' => $p['id'], 'issued_on' => $issued, 'due_on' => date('Y-m-d', strtotime($issued . ' +14 days')), 'issued_by' => $coord]);
        }
        $routes = [];
        foreach ([['Route A — Kasoa', 'Kasoa Junction, Old Barrier, Galilea', 'GR 1234-20', 'Mr. Yaw Danso', 450], ['Route B — Weija', 'Weija Junction, SCC, Mallam', 'GT 5678-19', 'Mr. Kojo Bonsu', 400]] as [$n, $stops, $v, $drv, $fee]) {
            $routes[] = [$d->insert('transport_routes', ['name' => $n, 'stops' => $stops, 'vehicle' => $v, 'driver_name' => $drv, 'driver_phone' => '02000003' . sprintf('%02d', count($routes) + 1), 'capacity' => 30, 'fee_per_term' => $fee]), explode(', ', $stops)];
        }
        foreach (array_slice($pupils, 3, 24) as $i => $p) {
            [$rid, $stops] = $routes[$i % 2];
            $d->insert('transport_assignments', ['pupil_id' => $p['id'], 'route_id' => $rid, 'academic_year_id' => $yid, 'stop' => $stops[$i % count($stops)], 'started_on' => $term['start_date']]);
        }
        foreach (array_slice($pupils, 0, 40) as $i => $p) {
            $d->insert('pupil_health', ['pupil_id' => $p['id'], 'blood_group' => $this->pick(['O+', 'O+', 'A+', 'B+', 'AB+']), 'genotype' => $this->pick(['AA', 'AA', 'AS']),
                'nhis_no' => (string) mt_rand(10000000, 99999999), 'nhis_expiry' => date('Y-m-d', strtotime(($i % 7 === 0 ? '-' : '+') . mt_rand(10, 300) . ' days'))]);
        }
        foreach ([['Headache and fever', 38.2, 'sent_home'], ['Stomach ache', null, 'rested'], ['Cut on knee during break', null, 'back_to_class']] as $i => [$cmp, $tmp, $out]) {
            $d->insert('health_visits', ['pupil_id' => $pupils[$i * 7]['id'], 'seen_at' => date('Y-m-d H:i:s', strtotime('-' . ($i * 3 + 1) . ' days 10:30')), 'complaint' => $cmp, 'temperature' => $tmp, 'outcome' => $out, 'guardian_notified' => $out === 'sent_home' ? 1 : 0, 'recorded_by' => $head]);
        }

        // ---- payroll: everyone on the payroll, last month paid
        $no = 0;
        foreach ($d->all("SELECT * FROM users WHERE role NOT IN ('parent','pupil','super_admin') ORDER BY id") as $u) {
            $no++;
            $basic = ['head_teacher' => 4200, 'academic_coordinator' => 3600, 'bursar' => 3300, 'admissions_officer' => 2200, 'class_teacher' => 2600, 'subject_teacher' => 2800][$u['role']] ?? 2000;
            $sid = $d->insert('staff', ['staff_no' => sprintf('STF%03d', $no), 'user_id' => $u['id'], 'name' => $u['name'], 'phone' => $u['phone'], 'position' => Auth::roleLabel($u['role']),
                'category' => in_array($u['role'], ['bursar', 'admissions_officer'], true) ? 'non_teaching' : 'teaching', 'pay_method' => 'bank', 'bank_name' => $this->pick(['GCB', 'Ecobank', 'ADB', 'Fidelity']),
                'account_no' => (string) mt_rand(1000000000, 1999999999), 'ssnit_no' => 'C' . mt_rand(10000000000, 99999999999), 'basic_salary' => $basic, 'start_date' => ($start - mt_rand(1, 8)) . '-09-01',
                'gender' => $this->pick(['male', 'female']), 'qualification' => $this->pick(['dbe', 'bed', 'bed', 'cert_a', 'degree', 'wassce']),
                'ghana_card_no' => sprintf('GHA-%09d-%d', mt_rand(700000000, 799999999), mt_rand(0, 9)),
                'ntc_licence_no' => ($no % 9 === 0 || in_array($u['role'], ['bursar', 'admissions_officer'], true)) ? null : sprintf('PT/%06d/%d', mt_rand(100000, 999999), $start - 1),
                'ntc_expiry' => ($no % 9 === 0 || in_array($u['role'], ['bursar', 'admissions_officer'], true)) ? null : date('Y-m-d', strtotime(($no % 7 === 0 ? '+' . mt_rand(10, 50) : '+' . mt_rand(120, 900)) . ' days'))]);
            $d->insert('staff_pay_items', ['staff_id' => $sid, 'kind' => 'allowance', 'name' => 'Transport allowance', 'amount' => 200, 'taxable' => 1]);
            $d->insert('staff_pay_items', ['staff_id' => $sid, 'kind' => 'deduction', 'name' => 'Staff welfare', 'amount' => 20, 'taxable' => 1]);
        }
        foreach (['Kitchen staff', 'Cleaner', 'Security'] as $i => $pos) {
            $no++;
            $sid = $d->insert('staff', ['staff_no' => sprintf('STF%03d', $no), 'name' => $this->pick(self::GIRLS) . ' ' . $this->pick(self::SURNAMES), 'position' => $pos, 'category' => 'non_teaching',
                'employment' => 'casual', 'pay_method' => 'momo', 'momo_no' => sprintf('02000004%02d', $i + 1), 'basic_salary' => 900, 'pays_ssnit' => 0]);
        }
        $period = date('Y-m', strtotime('first day of last month'));
        $runId = $d->insert('payroll_runs', ['period' => $period, 'status' => 'paid', 'created_by' => $bursar, 'approved_by' => $head, 'approved_at' => date('Y-m-d H:i:s'), 'paid_by' => $bursar, 'paid_at' => date('Y-m-d H:i:s'), 'paid_on' => date('Y-m-t', strtotime($period . '-01')), 'account_id' => $acct['bank']]);
        $tot = ['n' => 0, 'gross' => 0, 'net' => 0, 'se' => 0, 'sr' => 0, 'paye' => 0, 'ded' => 0];
        foreach ($d->all('SELECT * FROM staff') as $s) {
            $slip = Payroll::compute($s, $d->all('SELECT * FROM staff_pay_items WHERE staff_id = ?', [$s['id']]));
            $d->insert('payslips', ['run_id' => $runId, 'staff_id' => $s['id']] + $slip);
            $tot['n']++;
            $tot['gross'] += $slip['gross'];
            $tot['net'] += $slip['net'];
            $tot['se'] += $slip['ssnit_employee'];
            $tot['sr'] += $slip['ssnit_employer'];
            $tot['paye'] += $slip['paye'];
            $tot['ded'] += $slip['deductions'];
        }
        $d->update('payroll_runs', ['staff_count' => $tot['n'], 'gross' => $tot['gross'], 'net' => $tot['net'], 'ssnit_employee' => $tot['se'], 'ssnit_employer' => $tot['sr'], 'paye' => $tot['paye'], 'deductions' => $tot['ded']], 'id = ?', [$runId]);
        $txn++;
        $d->insert('fin_transactions', ['txn_no' => sprintf('TXN-%s-%05d', date('Y'), $txn), 'kind' => 'expense', 'txn_date' => date('Y-m-t', strtotime($period . '-01')), 'account_id' => $acct['bank'],
            'category_id' => $d->value("SELECT id FROM fin_categories WHERE code = 'SAL'"), 'amount' => $tot['net'], 'description' => 'Staff salaries (net) ' . date('F Y', strtotime($period . '-01')),
            'party' => 'Staff payroll', 'source' => 'payroll', 'source_id' => $runId, 'created_by' => $bursar]);
        $y = date('Y');
        foreach (['inv' => $inv, 'rct' => $rct, 'txn' => $txn] as $k => $n) {
            $d->run('INSERT INTO number_sequences (name, next_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE next_value = GREATEST(next_value, VALUES(next_value))', [$k . '_' . $y, $n + 1]);
        }

        // ---- admissions in every stage (for training the Admissions Officer)
        $admUser = (int) $d->value("SELECT id FROM users WHERE role = 'admissions_officer' LIMIT 1");
        $levels = array_column($d->all('SELECT id, code FROM levels'), 'id', 'code');
        $apps = [
            ['submitted', 'online', 'KG1', null, null, null], ['submitted', 'walk_in', 'B1', null, null, null],
            ['screening', 'online', 'B4', null, null, ['hearing', 'Hearing aid — please seat near the teacher']],
            ['interview', 'online', 'JHS1', null, '+5 days', null], ['offered', 'walk_in', 'B2', 72.5, '-6 days', null],
            ['accepted', 'online', 'KG2', 81.0, '-10 days', null], ['rejected', 'online', 'JHS2', 31.0, '-12 days', null],
        ];
        $flow = ['submitted', 'screening', 'interview', 'offered', 'accepted'];
        foreach ($apps as $i => [$stage, $src, $lv, $score, $when, $sen]) {
            $male = $i % 2 === 0;
            $sur = $this->pick(self::SURNAMES);
            $ageYears = ['KG1' => 4, 'KG2' => 5, 'B1' => 6, 'B2' => 7, 'B4' => 9, 'JHS1' => 12, 'JHS2' => 13][$lv];
            $aid = $d->insert('applications', [
                'ref_no' => Admission::nextRef($d), 'academic_year_id' => $yid, 'level_id' => $levels[$lv],
                'first_name' => $male ? $this->pick(self::BOYS) : $this->pick(self::GIRLS), 'last_name' => $sur, 'gender' => $male ? 'male' : 'female',
                'date_of_birth' => ($start - $ageYears) . sprintf('-%02d-%02d', mt_rand(1, 12), mt_rand(1, 28)), 'nationality' => 'Ghanaian',
                'hometown' => $this->pick(self::TOWNS), 'denomination' => $this->pick(self::DENOMS), 'previous_school' => $lv === 'KG1' ? null : $this->pick(['Bright Future Academy', 'Kasoa Methodist Primary', 'Royal Kids School']),
                'sen_category' => $sen[0] ?? null, 'sen_support' => $sen[1] ?? null,
                'guardian_name' => ($male ? 'Mrs. ' : 'Mr. ') . $this->pick($male ? self::GIRLS : self::BOYS) . ' ' . $sur, 'guardian_relationship' => $male ? 'Mother' : 'Father',
                'guardian_phone' => sprintf('02000009%02d', $i + 1), 'guardian_address' => $this->pick(self::TOWNS),
                'stage' => $stage, 'source' => $src, 'entrance_score' => $score,
                'interview_date' => $when ? date('Y-m-d', strtotime($when)) : null, 'created_by' => $src === 'walk_in' ? $admUser : null,
                'created_at' => date('Y-m-d H:i:s', strtotime('-' . (14 - $i) . ' days')),
            ]);
            $prev = null;
            foreach ($stage === 'rejected' ? ['submitted', 'screening', 'interview', 'rejected'] : array_slice($flow, 0, array_search($stage, $flow, true) + 1) as $k => $st) {
                $d->insert('application_stage_log', ['application_id' => $aid, 'from_stage' => $prev, 'to_stage' => $st, 'note' => $k === 0 ? ($src === 'walk_in' ? 'Walk-in application recorded' : 'Applied online') : null,
                    'changed_by' => $k === 0 && $src === 'online' ? null : $admUser, 'changed_at' => date('Y-m-d H:i:s', strtotime('-' . (14 - $i - $k) . ' days'))]);
                $prev = $st;
            }
        }

        // ---- requisitions at each step (teacher → Head → Bursar)
        $tlm = (int) $d->value("SELECT id FROM fin_categories WHERE code = 'TLM'");
        $stat = (int) $d->value("SELECT id FROM fin_categories WHERE code = 'STAT'");
        $rep = (int) $d->value("SELECT id FROM fin_categories WHERE code = 'REP'");
        $reqs = [
            ['Science practical materials', 'JHS science practicals for this term', 'JHS', $tlm, 'submitted', $subjTeachers[0], [['Test tubes', 24, 'pcs', 6.50], ['Methylated spirit (1 L)', 2, 'bottles', 35.00], ['Litmus paper', 5, 'packs', 12.00]]],
            ['Exercise books for Basic 3', 'Students without exercise books', 'Lower Primary (B1–B3)', $stat, 'approved', $teachers[$classes[5]['id']] ?? $head, [['Exercise books (40 leaves)', 60, 'pcs', 4.00]]],
            ['Repair of classroom door', 'Basic 5 door hinge broken', 'Administration', $rep, 'returned', $teachers[$classes[7]['id']] ?? $head, [['Hinges and screws', 1, 'set', 85.00], ['Carpenter workmanship', 1, 'job', 150.00]]],
            ['Chalk and markers', 'For the second half of term', 'Lower Primary (B1–B3)', $stat, 'draft', $teachers[$classes[3]['id']] ?? $head, [['White chalk', 10, 'boxes', 18.00], ['Whiteboard markers', 12, 'pcs', 9.00]]],
        ];
        foreach ($reqs as $i => [$title, $purpose, $dept, $cat, $status, $by, $items]) {
            $amount = array_sum(array_map(fn($it) => $it[1] * $it[3], $items));
            $rid = $d->insert('requisitions', [
                'req_no' => Fees::nextNumber($d, 'REQ'), 'title' => $title, 'purpose' => $purpose, 'department' => $dept, 'category_id' => $cat,
                'needed_by' => date('Y-m-d', strtotime('+' . (7 + $i * 3) . ' days')), 'amount' => $amount, 'status' => $status, 'requested_by' => $by,
                'submitted_at' => $status === 'draft' ? null : date('Y-m-d H:i:s', strtotime('-' . (3 + $i) . ' days')),
                'approved_by' => $status === 'approved' ? $head : null, 'approved_at' => $status === 'approved' ? date('Y-m-d H:i:s', strtotime('-1 day')) : null,
                'approved_amount' => $status === 'approved' ? $amount : null,
            ]);
            foreach ($items as [$desc, $qty, $unit, $cost]) {
                $d->insert('requisition_items', ['requisition_id' => $rid, 'description' => $desc, 'quantity' => $qty, 'unit' => $unit, 'unit_cost' => $cost]);
            }
            if ($status !== 'draft') {
                $d->insert('requisition_log', ['requisition_id' => $rid, 'action' => 'submitted', 'from_status' => 'draft', 'to_status' => 'submitted', 'user_id' => $by]);
            }
            if ($status === 'approved') {
                $d->insert('requisition_log', ['requisition_id' => $rid, 'action' => 'approved', 'from_status' => 'submitted', 'to_status' => 'approved', 'user_id' => $head]);
            }
            if ($status === 'returned') {
                $d->insert('requisition_log', ['requisition_id' => $rid, 'action' => 'returned', 'from_status' => 'submitted', 'to_status' => 'returned', 'note' => 'Please attach a quotation from the carpenter.', 'user_id' => $head]);
            }
        }

        // ---- forms: an open consent form with replies, and a closed PTA poll
        $fid = $d->insert('forms', ['title' => 'Excursion consent — Basic 5 & 6', 'description' => 'Trip to the Cape Coast Castle on Friday. Cost GH₵ 80 including transport and lunch.',
            'public_key' => bin2hex(random_bytes(12)), 'audience' => 'public', 'status' => 'published', 'collect_contact' => 1, 'one_per_phone' => 0,
            'closes_on' => date('Y-m-d', strtotime('+10 days')), 'confirmation_message' => 'Thank you. The class teacher will confirm the trip by SMS.', 'created_by' => $head]);
        $fields = [];
        foreach ([["Child's full name", 'text', null, 1], ['Class', 'select', "Basic 5\nBasic 6", 1], ['Do you allow your child to go?', 'radio', "Yes\nNo", 1], ['Medical conditions or allergies we should know about', 'textarea', null, 0]] as $k => [$label, $type, $opts, $req]) {
            $fields[$k] = $d->insert('form_fields', ['form_id' => $fid, 'label' => $label, 'type' => $type, 'options' => $opts, 'is_required' => $req, 'sort_order' => $k + 1]);
        }
        $b56 = $d->all("SELECT p.first_name, p.last_name, c.name AS class_name, g.name AS gname, g.phone FROM pupils p JOIN enrolments e ON e.pupil_id = p.id JOIN classes c ON c.id = e.class_id JOIN levels l ON l.id = c.level_id
                         JOIN pupil_guardian pg ON pg.pupil_id = p.id AND pg.is_primary = 1 JOIN guardians g ON g.id = pg.guardian_id WHERE l.code IN ('B5','B6') ORDER BY p.id LIMIT 9");
        foreach ($b56 as $k => $r) {
            $resp = $d->insert('form_responses', ['form_id' => $fid, 'respondent_name' => $r['gname'], 'respondent_phone' => $r['phone'], 'created_at' => date('Y-m-d H:i:s', strtotime('-' . (9 - $k) . ' hours'))]);
            foreach ([$r['first_name'] . ' ' . $r['last_name'], $r['class_name'], $k % 5 === 4 ? 'No' : 'Yes', $k === 2 ? 'Asthma — has an inhaler' : ''] as $j => $val) {
                if ($val !== '') {
                    $d->insert('form_answers', ['response_id' => $resp, 'field_id' => $fields[$j], 'value' => $val]);
                }
            }
        }
        $pid2 = $d->insert('forms', ['title' => 'PTA meeting — preferred day', 'description' => 'Help us choose a day for the next PTA meeting.', 'public_key' => bin2hex(random_bytes(12)),
            'audience' => 'public', 'status' => 'closed', 'collect_contact' => 1, 'one_per_phone' => 1, 'created_by' => $head]);
        $pf = $d->insert('form_fields', ['form_id' => $pid2, 'label' => 'Which day suits you best?', 'type' => 'radio', 'options' => "Saturday morning\nSunday afternoon\nWednesday evening", 'is_required' => 1, 'sort_order' => 1]);
        foreach ($d->all('SELECT name, phone FROM guardians ORDER BY id LIMIT 14') as $k => $g) {
            $resp = $d->insert('form_responses', ['form_id' => $pid2, 'respondent_name' => $g['name'], 'respondent_phone' => $g['phone'], 'created_at' => date('Y-m-d H:i:s', strtotime('-' . (20 - $k) . ' days'))]);
            $d->insert('form_answers', ['response_id' => $resp, 'field_id' => $pf, 'value' => ['Saturday morning', 'Saturday morning', 'Sunday afternoon', 'Wednesday evening'][$k % 4]]);
        }

        // ---- quizzes: a published JHS 1 Mathematics quiz with results, a draft Basic 6 Science quiz, and a pupil login to try it
        $csMath = $d->one("SELECT cs.id, cs.teacher_id, c.id AS class_id FROM class_subjects cs JOIN subjects s ON s.id = cs.subject_id JOIN classes c ON c.id = cs.class_id JOIN levels l ON l.id = c.level_id WHERE l.code = 'JHS1' AND s.code = 'MATH' LIMIT 1");
        $csSci = $d->one("SELECT cs.id, cs.teacher_id FROM class_subjects cs JOIN subjects s ON s.id = cs.subject_id JOIN classes c ON c.id = cs.class_id JOIN levels l ON l.id = c.level_id WHERE l.code = 'B6' AND s.code = 'SCI' LIMIT 1");
        if ($csMath) {
            $qz = $d->insert('quizzes', ['title' => 'Fractions and percentages', 'class_subject_id' => $csMath['id'], 'term_id' => $term['id'], 'instructions' => 'Answer all questions. You have 15 minutes.',
                'time_limit' => 15, 'opens_at' => date('Y-m-d H:i:s', strtotime('-3 days')), 'closes_at' => date('Y-m-d H:i:s', strtotime('+7 days')), 'max_attempts' => 1,
                'show_answers' => 'after_submit', 'status' => 'published', 'created_by' => $csMath['teacher_id']]);
            $qs = [
                ['mcq', 'What is 3/4 as a percentage?', ['34%', '75%', '43%', '7.5%'], '1'],
                ['mcq', 'Which fraction is the largest?', ['1/2', '2/5', '3/8', '5/12'], '0'],
                ['truefalse', '0.2 is the same as 1/5.', null, 'true'],
                ['short', 'Simplify 12/16 (write it as a fraction, e.g. 1/2).', null, '3/4'],
            ];
            $qIds = [];
            foreach ($qs as $k => [$type, $prompt, $opts, $ans]) {
                $qIds[$k] = $d->insert('quiz_questions', ['quiz_id' => $qz, 'type' => $type, 'prompt' => $prompt, 'options' => $opts ? json_encode($opts) : null, 'answer' => $ans, 'marks' => 1, 'sort_order' => $k + 1]);
            }
            $jhs1 = $d->all("SELECT p.id FROM enrolments e JOIN pupils p ON p.id = e.pupil_id WHERE e.class_id = ? AND e.academic_year_id = ? ORDER BY p.id", [$csMath['class_id'], $yid]);
            foreach (array_slice($jhs1, 1, 7) as $k => $pp) {
                $ans = [$qIds[0] => (string) ($k % 3 === 0 ? 2 : 1), $qIds[1] => (string) ($k % 4 === 1 ? 3 : 0), $qIds[2] => $k % 2 ? 'true' : 'false', $qIds[3] => $k % 3 ? '3/4' : '6/8'];
                $score = ($ans[$qIds[0]] === '1') + ($ans[$qIds[1]] === '0') + ($ans[$qIds[2]] === 'true') + ($ans[$qIds[3]] === '3/4');
                $at = strtotime('-' . (2 - intdiv($k, 4)) . ' days 10:' . sprintf('%02d', 5 + $k * 3));
                $d->insert('quiz_attempts', ['quiz_id' => $qz, 'pupil_id' => $pp['id'], 'attempt_no' => 1, 'started_at' => date('Y-m-d H:i:s', $at), 'deadline_at' => date('Y-m-d H:i:s', $at + 900),
                    'submitted_at' => date('Y-m-d H:i:s', $at + 420 + $k * 30), 'score' => $score, 'max_score' => 4, 'question_order' => json_encode(array_values($qIds)), 'answers' => json_encode($ans)]);
            }
            // Pupil login "pupil1" (a JHS 1 pupil who has not taken the quiz yet; real pupils sign in with their admission no.)
            if ($jhs1) {
                $pp = $d->one('SELECT * FROM pupils WHERE id = ?', [$jhs1[0]['id']]);
                $puid = $d->insert('users', ['name' => $pp['first_name'] . ' ' . $pp['last_name'], 'email' => 'pupil' . $pp['id'] . '@users.nisdas.local', 'username' => 'pupil1',
                    'role' => 'pupil', 'password_hash' => $hash, 'must_change_password' => 0]);
                $d->update('pupils', ['user_id' => $puid], 'id = ?', [$pp['id']]);
            }
        }
        if ($csSci) {
            $qz2 = $d->insert('quizzes', ['title' => 'Living and non-living things', 'class_subject_id' => $csSci['id'], 'term_id' => $term['id'], 'time_limit' => 10, 'max_attempts' => 2,
                'show_answers' => 'after_close', 'status' => 'draft', 'created_by' => $csSci['teacher_id']]);
            $d->insert('quiz_questions', ['quiz_id' => $qz2, 'type' => 'mcq', 'prompt' => 'Which of these is a living thing?', 'options' => json_encode(['Stone', 'Goat', 'Chair', 'Water']), 'answer' => '1', 'marks' => 1, 'sort_order' => 1]);
            $d->insert('quiz_questions', ['quiz_id' => $qz2, 'type' => 'truefalse', 'prompt' => 'Plants need sunlight to make food.', 'answer' => 'true', 'marks' => 1, 'sort_order' => 2]);
        }

        // ---- parent ↔ teacher conversations (the practice parent and their child's class teacher)
        $child = $d->one("SELECT p.id, p.first_name, e.class_id FROM pupil_guardian pg JOIN pupils p ON p.id = pg.pupil_id JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ? WHERE pg.guardian_id = ? ORDER BY p.id LIMIT 1", [$yid, $firstG['id']]);
        if ($child && !empty($teachers[$child['class_id']])) {
            $ct = $teachers[$child['class_id']];
            $threads = [
                ['Homework question', [[$pu, "Good afternoon. {$child['first_name']} says the Maths homework is on page 42 but the book stops at page 40. Please which page?", '-2 days 16:10'],
                    [$ct, 'Good afternoon. Sorry, it is page 24, exercise 3. I will remind the class tomorrow.', '-2 days 17:02'], [$pu, 'Thank you very much.', '-2 days 17:15']], true],
                ['Absence on Monday', [[$pu, "{$child['first_name']} will not come to school on Monday — we are travelling for a funeral. We return on Tuesday.", '-5 hours']], false],
            ];
            foreach ($threads as [$subject, $msgs, $read]) {
                $last = end($msgs)[2];
                $cid = $d->insert('conversations', ['subject' => $subject, 'pupil_id' => $child['id'], 'created_by' => $pu, 'created_at' => date('Y-m-d H:i:s', strtotime($msgs[0][2])), 'last_message_at' => date('Y-m-d H:i:s', strtotime($last))]);
                $d->insert('conversation_members', ['conversation_id' => $cid, 'user_id' => $pu, 'last_read_at' => date('Y-m-d H:i:s', strtotime($last))]);
                $d->insert('conversation_members', ['conversation_id' => $cid, 'user_id' => $ct, 'last_read_at' => $read ? date('Y-m-d H:i:s', strtotime($last)) : null]);
                foreach ($msgs as [$uid, $body, $when]) {
                    $d->insert('conversation_messages', ['conversation_id' => $cid, 'user_id' => $uid, 'body' => $body, 'created_at' => date('Y-m-d H:i:s', strtotime($when))]);
                }
            }
        }

        // ---- Sprint 16 ------------------------------------------------------------------
        $staffIds = array_map('intval', array_column($d->all("SELECT id FROM users WHERE role NOT IN ('parent','pupil','super_admin') AND is_active = 1"), 'id'));

        // Staff sign-in times for every school day so far (a few late arrivals and absences)
        foreach ($days as $day) {
            $vals = [];
            foreach ($staffIds as $uid) {
                if (mt_rand(1, 100) > 96) {
                    continue;
                }
                $in = date('H:i:s', strtotime('06:45') + mt_rand(0, ($uid % 7 === 0 ? 75 : 50)) * 60);
                $out = $day < $today ? date('H:i:s', strtotime('15:00') + mt_rand(0, 90) * 60) : null;
                $vals[] = '(' . $uid . ",'" . $day . "','" . $in . "'," . ($out ? "'$out'" : 'NULL') . ')';
            }
            if ($vals) {
                $d->run('INSERT INTO staff_attendance (user_id, date, time_in, time_out) VALUES ' . implode(',', $vals));
            }
        }

        // Last year: three terms of results (pupils were one level lower) so progress charts have a history
        $py = $start - 1;
        $pyid = $d->insert('academic_years', ['name' => $py . '/' . $start, 'start_date' => "$py-09-08", 'end_date' => "$start-07-24", 'is_current' => 0]);
        $pTerms = [];
        foreach ([[1, "$py-09-08", "$py-12-18"], [2, "$start-01-08", "$start-04-02"], [3, "$start-04-28", "$start-07-24"]] as [$no, $ts, $te]) {
            $pTerms[$no] = $d->insert('terms', ['academic_year_id' => $pyid, 'term_no' => $no, 'name' => "Term $no", 'start_date' => $ts, 'end_date' => $te, 'is_current' => 0]);
        }
        $levelOrder = array_values(array_unique(array_map('intval', array_column($classes, 'sort_order'))));
        sort($levelOrder);
        $firstOfLevel = [];
        foreach ($classes as $c) {
            $firstOfLevel[(int) $c['sort_order']] ??= $c;
        }
        $prevPupils = [];
        foreach ($pupils as $p) {
            $ix = array_search((int) $p['level_sort'], $levelOrder, true);
            if ($ix === false || $ix === 0) {
                continue;
            }
            $pc = $firstOfLevel[$levelOrder[$ix - 1]];
            $d->insert('enrolments', ['pupil_id' => $p['id'], 'class_id' => $pc['id'], 'academic_year_id' => $pyid, 'status' => 'promoted', 'enrolled_on' => "$py-09-08"]);
            $prevPupils[$pc['id']][] = $p['id'];
        }
        foreach ($d->all("SELECT cs.id, cs.class_id, cs.teacher_id FROM class_subjects cs JOIN classes c ON c.id = cs.class_id JOIN levels l ON l.id = c.level_id WHERE l.category IN ('primary','jhs')") as $cs) {
            $pids = $prevPupils[$cs['class_id']] ?? [];
            if (!$pids) {
                continue;
            }
            foreach ($pTerms as $no => $tid) {
                foreach ([['class', 'Class work', 50, 0], ['exam', 'End-of-term exam', 100, 1]] as [$kind, $title, $max, $so]) {
                    $aid = $d->insert('assessments', ['term_id' => $tid, 'class_subject_id' => $cs['id'], 'kind' => $kind, 'title' => $title, 'max_mark' => $max, 'sort_order' => $so, 'created_by' => $cs['teacher_id']]);
                    $vals = [];
                    foreach ($pids as $pid) {
                        $ability = 40 + ($pid * 37 % 50) + (($pid % 5) - 2) * 1.6 * ($no - 4) + (($pid + (int) $cs['id']) % 11 - 5);
                        $vals[] = "($aid,$pid," . max(0, min($max, round(($ability + mt_rand(-8, 8)) * $max / 100))) . ')';
                    }
                    $d->run('INSERT INTO assessment_scores (assessment_id, pupil_id, score) VALUES ' . implode(',', $vals));
                }
                $d->insert('score_sheets', ['term_id' => $tid, 'class_subject_id' => $cs['id'], 'status' => 'approved', 'submitted_by' => $cs['teacher_id'], 'submitted_at' => date('Y-m-d H:i:s'), 'approved_by' => $coord, 'approved_at' => date('Y-m-d H:i:s')]);
            }
        }
        foreach (array_keys($prevPupils) as $pcid) {
            foreach ($pTerms as $tid) {
                $d->insert('report_releases', ['term_id' => $tid, 'class_id' => $pcid, 'released_by' => $head]);
            }
        }

        // Teacher appraisals: last year's Term 3 completed for everyone; this term at every stage
        $crit = array_map('intval', array_column($d->all('SELECT id FROM appraisal_criteria WHERE is_active = 1 ORDER BY sort_order'), 'id'));
        $teachIds = array_values(array_unique(array_merge([$coord], array_values($teachers), $subjTeachers)));
        $appraise = function (int $uid, int $tid, string $stage, int $i) use ($d, $crit, $head) {
            $base = 3 + ($uid % 3 === 0 ? 1 : 0);
            $aid = $d->insert('appraisals', ['user_id' => $uid, 'term_id' => $tid, 'status' => $stage, 'created_by' => $head]);
            $heads = [];
            foreach ($crit as $k => $cid) {
                $self = $stage === 'open' ? null : min(5, $base + (($k + $i) % 3 === 0 ? 1 : 0));
                $hd = $stage === 'completed' ? max(2, min(5, $base + (($k * 7 + $i) % 4 === 0 ? 1 : (($k + $i) % 5 === 0 ? -1 : 0)))) : null;
                if ($hd) {
                    $heads[] = $hd;
                }
                $d->insert('appraisal_scores', ['appraisal_id' => $aid, 'criterion_id' => $cid, 'self_score' => $self, 'head_score' => $hd]);
            }
            $upd = [];
            if ($stage !== 'open') {
                $upd += ['self_comment' => 'I covered most topics on time and my class performed well in the mid-term test. I need more practice with ICT tools and would like support with large-class management.', 'self_submitted_at' => date('Y-m-d H:i:s', strtotime('-6 days'))];
            }
            if ($stage === 'completed') {
                $upd += [
                    'strengths' => 'Well-prepared lesson notes; warm and orderly classroom; marks exercises promptly.',
                    'improvements' => 'Use more group work and teaching aids; involve quieter students.',
                    'targets' => 'Complete the scheme of work by week 11; attend the ICT-in-teaching INSET.',
                    'head_comment' => 'A dependable teacher. Keep up the good work.',
                    'overall' => round(array_sum($heads) / max(1, count($heads)), 1), 'appraised_by' => $head, 'completed_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
                ];
                if ($i % 2 === 0) {
                    $upd += ['acknowledged_at' => date('Y-m-d H:i:s', strtotime('-1 day')), 'teacher_response' => 'Thank you. I will work on the targets.'];
                }
            }
            if ($upd) {
                $d->update('appraisals', $upd, 'id = ?', [$aid]);
            }
        };
        foreach ($teachIds as $i => $uid) {
            $appraise($uid, $pTerms[3], 'completed', $i);
            $appraise($uid, (int) $term['id'], ['completed', 'self_done', 'open', 'self_done'][$i % 4], $i);
        }

        // Scheme of work for this term: one topic per sub-strand, spread over the weeks; past weeks mostly ticked
        $weeks = max(1, min(20, (int) ceil(((strtotime($term['end_date']) - strtotime($term['start_date'])) / 86400 + 1) / 7)));
        $curWeek = $today < $term['start_date'] ? 0 : (int) floor((strtotime(min($today, $term['end_date'])) - strtotime($term['start_date'])) / 86400 / 7) + 1;
        $subsBy = [];
        foreach ($d->all('SELECT s.id, s.subject_id, s.levels, s.name FROM curriculum_strands s JOIN curriculum_strands p ON p.id = s.parent_id WHERE s.is_active = 1 ORDER BY p.sort_order, s.sort_order') as $s) {
            foreach (explode(',', $s['levels']) as $lv) {
                $subsBy[$s['subject_id'] . ':' . $lv][] = $s;
            }
        }
        foreach ($d->all('SELECT cs.id, cs.subject_id, cs.teacher_id, l.category FROM class_subjects cs JOIN classes c ON c.id = cs.class_id JOIN levels l ON l.id = c.level_id') as $cs) {
            $subs = $subsBy[$cs['subject_id'] . ':' . $cs['category']] ?? [];
            $n = count($subs);
            $vals = [];
            foreach ($subs as $i => $s) {
                $wk = min(max(1, $weeks - 1), (int) floor($i * max(1, $weeks - 1) / $n) + 1);
                $taught = $wk < $curWeek && mt_rand(1, 100) <= 85;
                $on = $taught ? date('Y-m-d', strtotime($term['start_date'] . ' +' . (($wk - 1) * 7 + mt_rand(0, 4)) . ' days')) : null;
                $vals[] = sprintf('(%d,%d,%d,%d,%s,%s,%s)', $term['id'], $cs['id'], $wk, $s['id'], $d->pdo()->quote($s['name']), $on ? "'$on'" : 'NULL', $on && $cs['teacher_id'] ? (int) $cs['teacher_id'] : 'NULL');
            }
            if ($vals) {
                $d->run('INSERT INTO scheme_items (term_id, class_subject_id, week_no, strand_id, topic, taught_on, taught_by) VALUES ' . implode(',', $vals));
            }
        }

        // Rooms & resources: a classroom per class plus lab, ICT room, hall and library
        $rollOf = [];
        foreach ($pupils as $p) {
            $rollOf[$p['class_id']] = ($rollOf[$p['class_id']] ?? 0) + 1;
        }
        $roomOf = [];
        foreach ($classes as $i => $c) {
            $rid = $d->insert('rooms', ['name' => $c['name'] . ' classroom', 'kind' => 'classroom', 'capacity' => 40, 'class_id' => $c['id']]);
            $roomOf[$c['id']] = $rid;
            $roll = $rollOf[$c['id']] ?? 0;
            foreach ([['furniture', 'Student desks', null, 1.0, null, $roll - ($i % 3 === 0 ? 3 : 0), $i % 4 === 0 ? 2 : 0], ['furniture', 'Student chairs', null, 1.0, null, $roll - ($i % 5 === 1 ? 2 : 0), 1],
                      ['furniture', "Teacher's table", null, null, 1, 1, 0], ['furniture', "Teacher's chair", null, null, 1, 1, 0], ['teaching_aid', 'Whiteboard / chalkboard', null, null, 1, 1, 0]] as [$cat, $nm, $sid, $per, $need, $good, $rep]) {
                $d->insert('resource_items', ['room_id' => $rid, 'category' => $cat, 'name' => $nm, 'subject_id' => $sid, 'per_pupil' => $per, 'needed' => $need, 'qty_good' => max(0, $good), 'qty_repair' => $rep, 'qty_bad' => $rep ? 1 : 0, 'updated_by' => $bursar]);
            }
            foreach ($d->all("SELECT s.id, s.name FROM class_subjects cs JOIN subjects s ON s.id = cs.subject_id WHERE cs.class_id = ? AND s.code IN ('ENG','MATH','SCI','OWOP','SOC')", [$c['id']]) as $k => $s) {
                $d->insert('resource_items', ['room_id' => $rid, 'category' => 'textbook', 'name' => $s['name'] . ' textbooks', 'subject_id' => $s['id'], 'per_pupil' => 1.0, 'qty_good' => max(0, $roll - (($i + $k) % 3) * 3), 'qty_repair' => ($i + $k) % 4, 'updated_by' => $bursar]);
            }
        }
        $lab = $d->insert('rooms', ['name' => 'Science lab', 'kind' => 'lab', 'capacity' => 40]);
        $ict = $d->insert('rooms', ['name' => 'ICT room', 'kind' => 'lab', 'capacity' => 30]);
        $hall = $d->insert('rooms', ['name' => 'Assembly hall', 'kind' => 'hall', 'capacity' => 200]);
        $d->insert('rooms', ['name' => 'Library', 'kind' => 'library', 'capacity' => 30]);
        foreach ([[$lab, 'equipment', 'Microscopes', 10, 6, 1], [$lab, 'equipment', 'Beakers and test tubes (sets)', 20, 20, 0], [$ict, 'ict', 'Desktop computers', 30, 18, 4], [$ict, 'ict', 'Projector', 1, 1, 0], [$hall, 'furniture', 'Chairs', 200, 176, 9], [$hall, 'equipment', 'Public address system', 1, 0, 1]] as [$rid, $cat, $nm, $need, $good, $rep]) {
            $d->insert('resource_items', ['room_id' => $rid, 'category' => $cat, 'name' => $nm, 'needed' => $need, 'qty_good' => $good, 'qty_repair' => $rep, 'updated_by' => $bursar]);
        }

        // Exam timetable for the end of this term (Monday–Friday), published to the notice board
        $exStart = strtotime($term['start_date'] . ' +70 days');
        $exStart = strtotime('monday this week', $exStart) < $exStart ? strtotime('next monday', $exStart) : $exStart;
        $sid = $d->insert('exam_series', ['term_id' => $term['id'], 'name' => 'End of ' . $term['name'] . ' Examination', 'start_date' => date('Y-m-d', $exStart), 'end_date' => date('Y-m-d', $exStart + 4 * 86400),
            'instructions' => 'Students should arrive by 7:30 a.m. with pens, pencils, an eraser and a ruler. JHS students also need a mathematical set.', 'created_by' => $coord]);
        $plan = [[0, 'ENG', '08:00', 90], [0, 'MATH', '10:30', 90], [1, 'SCI', '08:00', 90], [1, 'SOC', '10:30', 90], [1, 'OWOP', '10:30', 90], [2, 'RME', '08:00', 60], [2, 'COMP', '09:30', 60],
                 [3, 'GHL', '08:00', 60], [3, 'CAD', '09:30', 60], [3, 'CA', '09:30', 60], [3, 'CT', '11:00', 60], [3, 'HIST', '11:00', 60], [4, 'FRE', '08:00', 60], [4, 'BIB', '09:30', 45]];
        $invPool = array_values($teachers);
        $lines = [];
        $k = 0;
        foreach ($plan as [$dayOff, $code, $time, $dur]) {
            foreach ($d->all("SELECT cs.class_id, cs.subject_id, cs.teacher_id, c.name AS class_name, s.name AS subject_name FROM class_subjects cs JOIN classes c ON c.id = cs.class_id JOIN levels l ON l.id = c.level_id JOIN subjects s ON s.id = cs.subject_id
                              WHERE s.code = ? AND (l.category = 'jhs' OR (l.category = 'primary' AND l.sort_order >= (SELECT sort_order FROM levels WHERE code = 'B4' LIMIT 1))) ORDER BY l.sort_order", [$code]) as $r) {
                $inv = $invPool[$k++ % count($invPool)];
                if ($inv === (int) $r['teacher_id']) {
                    $inv = $invPool[$k++ % count($invPool)];
                }
                $date = date('Y-m-d', $exStart + $dayOff * 86400);
                $d->insert('exam_papers', ['series_id' => $sid, 'class_id' => $r['class_id'], 'subject_id' => $r['subject_id'], 'exam_date' => $date, 'start_time' => $time . ':00', 'duration_min' => $dur, 'room_id' => $roomOf[$r['class_id']] ?? null, 'invigilator_id' => $inv]);
                $lines[$date][] = '  ' . $r['class_name'] . ': ' . $r['subject_name'] . ' — ' . $time . ' to ' . date('H:i', strtotime($time) + $dur * 60);
            }
        }
        $body = "End of {$term['name']} Examination — " . date('j M', $exStart) . ' to ' . date('j M Y', $exStart + 4 * 86400) . ".\n\nPupils should arrive by 7:30 a.m. with pens, pencils, an eraser and a ruler.\n";
        foreach ($lines as $date => $ls) {
            $body .= "\n" . strtoupper(date('l j F', strtotime($date))) . "\n" . implode("\n", $ls);
        }
        $nid = $d->insert('notices', ['title' => 'End of ' . $term['name'] . ' Examination timetable', 'body' => $body, 'category' => 'event', 'audience' => 'all', 'is_pinned' => 1, 'publish_at' => date('Y-m-d H:i:s', strtotime('-1 day')), 'expires_on' => date('Y-m-d', $exStart + 4 * 86400), 'created_by' => $coord]);
        if (Notices::loginReady()) {
            $d->update('notices', ['on_login' => 1], 'id = ?', [$nid]);
        }
        $d->update('exam_series', ['status' => 'published', 'published_at' => date('Y-m-d H:i:s', strtotime('-1 day')), 'notice_id' => $nid], 'id = ?', [$sid]);

        // Seating: every class (lower classes alphabetical, upper mixed), and one exam hall plan for JHS
        $classPupils = function (array $classIds) use ($d, $yid): array {
            $in = implode(',', array_map('intval', $classIds));
            $out = [];
            foreach ($d->all("SELECT p.id, p.first_name, p.last_name, p.gender, e.class_id FROM enrolments e JOIN pupils p ON p.id = e.pupil_id WHERE e.academic_year_id = ? AND e.class_id IN ($in) ORDER BY FIELD(e.class_id, $in), p.last_name, p.first_name", [$yid]) as $p) {
                $out[(int) $p['id']] = $p;
            }
            return $out;
        };
        $seatIt = function (int $planId, array $map) use ($d) {
            $vals = [];
            foreach ($map as $key => $pid) {
                [$r, $c] = array_map('intval', explode('-', $key));
                $vals[] = "($planId,$r,$c,$pid)";
            }
            if ($vals) {
                $d->run('INSERT INTO seating_seats (plan_id, row_no, col_no, pupil_id) VALUES ' . implode(',', $vals));
            }
        };
        foreach ($classes as $c) {
            $ps = $classPupils([$c['id']]);
            [$rows, $cols] = Seating::suggest(count($ps));
            $method = in_array($c['category'], ['primary', 'jhs'], true) ? 'mixed' : 'alpha';
            $pl = $d->insert('seating_plans', ['kind' => 'class', 'class_id' => $c['id'], 'title' => $c['name'] . ' seating', 'seat_rows' => $rows, 'seat_cols' => $cols, 'method' => $method, 'updated_by' => $teachers[$c['id']] ?? $head]);
            $seatIt($pl, Seating::arrange($ps, $rows, $cols, $method, false));
        }
        $jhs = array_map('intval', array_column(array_filter($classes, fn($c) => $c['category'] === 'jhs'), 'id'));
        if ($jhs) {
            $ps = $classPupils($jhs);
            $pl = $d->insert('seating_plans', ['kind' => 'exam', 'series_id' => $sid, 'room_id' => $hall, 'title' => 'Assembly hall — JHS 1 to 3', 'class_list' => implode(',', $jhs), 'seat_rows' => (int) ceil(count($ps) / 8), 'seat_cols' => 8, 'method' => 'alpha', 'updated_by' => $coord]);
            $seatIt($pl, Seating::arrange($ps, (int) ceil(count($ps) / 8), 8, 'alpha', true));
        }

        // ---- Sprint 17: homework and pupil leave ------------------------------------------------
        $yid = (int) $term['academic_year_id'];
        $mkAsg = function (array $cs, string $title, string $setOn, string $dueOn, ?float $max, string $status) use ($d, $term) {
            return $d->insert('assignments', ['class_id' => $cs['class_id'], 'class_subject_id' => $cs['id'], 'term_id' => $term['id'], 'title' => $title,
                'instructions' => 'Answer all the questions in your exercise book or type your answers here. Show your working.', 'set_on' => $setOn, 'due_on' => $dueOn,
                'max_score' => $max, 'status' => $status, 'created_by' => $cs['teacher_id']]);
        };
        if ($csMath) {
            $roll = array_column($d->all("SELECT pupil_id FROM enrolments WHERE class_id = ? AND academic_year_id = ? AND status = 'active' ORDER BY pupil_id", [$csMath['class_id'], $yid]), 'pupil_id');
            $old = $mkAsg($csMath, 'Fractions — exercise 3A', date('Y-m-d', strtotime('-9 days')), date('Y-m-d', strtotime('-6 days')), 20, 'closed');
            foreach ($roll as $k => $pid) {
                $missing = $k % 7 === 6;
                $d->insert('assignment_submissions', ['assignment_id' => $old, 'pupil_id' => $pid, 'status' => $missing ? 'missing' : 'returned', 'on_paper' => $missing ? 0 : 1,
                    'submitted_at' => $missing ? null : date('Y-m-d 08:00:00', strtotime('-6 days')), 'score' => $missing ? null : 10 + ($k * 3) % 11,
                    'feedback' => $missing ? null : ($k % 2 ? 'Good work — check question 4.' : 'Well done.'), 'marked_by' => $missing ? null : $csMath['teacher_id'], 'marked_at' => $missing ? null : date('Y-m-d H:i:s', strtotime('-4 days'))]);
            }
            $new = $mkAsg($csMath, 'Ratio and proportion — 10 questions', date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('+3 days')), 10, 'published');
            foreach (array_slice($roll, 1, 5) as $k => $pid) {
                $d->insert('assignment_submissions', ['assignment_id' => $new, 'pupil_id' => $pid, 'status' => 'submitted', 'body' => 'Q1: 3:4  Q2: 12  Q3: 2/5 ...', 'submitted_at' => date('Y-m-d H:i:s', strtotime('-' . (3 + $k) . ' hours'))]);
            }
        }
        $kid = $d->one("SELECT p.id, e.class_id FROM pupils p JOIN pupil_guardian pg ON pg.pupil_id = p.id JOIN enrolments e ON e.pupil_id = p.id AND e.academic_year_id = ? WHERE pg.guardian_id = ? ORDER BY p.date_of_birth LIMIT 1", [$yid, $firstG['id']]);
        if ($kid) {
            foreach ($d->all("SELECT cs.id, cs.class_id, cs.teacher_id, s.code FROM class_subjects cs JOIN subjects s ON s.id = cs.subject_id WHERE cs.class_id = ? AND s.code IN ('ENG','SCI') ORDER BY s.code", [$kid['class_id']]) as $i => $cs) {
                $aid = $mkAsg($cs, $cs['code'] === 'ENG' ? 'Composition: My favourite festival' : 'Parts of a plant — label the diagram', date('Y-m-d', strtotime('-' . (2 + $i * 5) . ' days')), date('Y-m-d', strtotime(($i ? '-1' : '+2') . ' days')), $cs['code'] === 'ENG' ? null : 10, 'published');
                if ($i) {
                    $d->insert('assignment_submissions', ['assignment_id' => $aid, 'pupil_id' => $kid['id'], 'status' => 'returned', 'on_paper' => 1, 'submitted_at' => date('Y-m-d 08:00:00', strtotime('-2 days')), 'score' => 8, 'feedback' => 'Neat labels. Revise "stamen".', 'marked_by' => $cs['teacher_id'], 'marked_at' => date('Y-m-d H:i:s', strtotime('-1 day'))]);
                }
            }
            $d->insert('leave_requests', ['pupil_id' => $kid['id'], 'from_date' => date('Y-m-d', strtotime('-12 days')), 'to_date' => date('Y-m-d', strtotime('-11 days')), 'kind' => 'sick', 'reason' => 'Malaria — doctor advised two days of rest.', 'requested_by' => $pu,
                'status' => 'approved', 'decided_by' => $teachers[$kid['class_id']] ?? null, 'decided_at' => date('Y-m-d H:i:s', strtotime('-12 days')), 'created_at' => date('Y-m-d H:i:s', strtotime('-13 days'))]);
            $d->run("UPDATE attendance_records SET status = 'excused', note = 'Approved leave: Sick / hospital' WHERE pupil_id = ? AND date BETWEEN ? AND ?", [$kid['id'], date('Y-m-d', strtotime('-12 days')), date('Y-m-d', strtotime('-11 days'))]);
            $d->insert('leave_requests', ['pupil_id' => $kid['id'], 'from_date' => date('Y-m-d', strtotime('next friday')), 'to_date' => date('Y-m-d', strtotime('next friday')), 'kind' => 'funeral', 'reason' => 'Grandmother’s funeral in Kumasi.', 'requested_by' => $pu, 'status' => 'pending']);
        }

        // ---- Sprint 18: canteen, events, appointments ---------------------------------------------
        $itemIds = array_column($d->all('SELECT id, name FROM canteen_items'), 'id', 'name');
        $mon = date('Y-m-d', strtotime('monday this week'));
        $lunches = ['Jollof rice with chicken', 'Waakye with egg', 'Banku and okro stew', 'Rice and groundnut soup', 'Jollof rice with chicken'];
        for ($i = 0; $i < 5; $i++) {
            $day = date('Y-m-d', strtotime($mon . " +$i days"));
            $d->run('INSERT IGNORE INTO canteen_menu (menu_date, meal, item_id) VALUES (?, ?, ?)', [$day, 'breakfast', $itemIds['Hausa koko and bread']]);
            $d->run('INSERT IGNORE INTO canteen_menu (menu_date, meal, item_id) VALUES (?, ?, ?)', [$day, 'lunch', $itemIds[$lunches[$i]]]);
            $d->run('INSERT IGNORE INTO canteen_menu (menu_date, meal, item_id) VALUES (?, ?, ?)', [$day, 'snack', $itemIds['Meat pie']]);
        }
        $bursarId = (int) $d->value("SELECT id FROM users WHERE email = 'bursar@demo.school'");
        $cash = (int) setting('fin_acct_cash', '1');
        $canteenCat = $d->value("SELECT id FROM fin_categories WHERE code = 'CANT'");
        $wPupils = array_column($d->all("SELECT e.pupil_id FROM enrolments e JOIN classes c ON c.id = e.class_id JOIN levels l ON l.id = c.level_id WHERE e.academic_year_id = ? AND e.status = 'active' AND l.category IN ('primary','jhs') ORDER BY e.pupil_id LIMIT 24", [$yid]), 'pupil_id');
        if ($kid) {
            array_unshift($wPupils, $kid['id']);
        }
        $saleN = 0;
        foreach (array_unique($wPupils) as $k => $wp) {
            $top = [50, 30, 20, 40][$k % 4];
            $d->run('INSERT INTO canteen_wallets (pupil_id, balance) VALUES (?, ?)', [$wp, $top]);
            $tid = $d->insert('canteen_wallet_txns', ['pupil_id' => $wp, 'kind' => 'topup', 'amount' => $top, 'balance_after' => $top, 'method' => 'cash', 'created_by' => $bursarId, 'created_at' => date('Y-m-d 07:20:00', strtotime('-3 days'))]);
            Finance::post($d, ['kind' => 'income', 'txn_date' => date('Y-m-d', strtotime('-3 days')), 'account_id' => $cash, 'category_id' => $canteenCat, 'amount' => $top, 'description' => 'Canteen wallet top-up', 'source' => 'canteen_topup', 'source_id' => $tid, 'created_by' => $bursarId]);
            $bal = $top;
            foreach ([[2, 'Meat pie', 1, 'Bottled water (500 ml)', 1], [1, 'Plantain chips', 2, 'Sobolo', 1]] as $j => [$ago, $i1, $q1, $i2, $q2]) {
                if (($k + $j) % 3 === 2) {
                    continue;
                }
                $p1 = (float) $d->value('SELECT price FROM canteen_items WHERE id = ?', [$itemIds[$i1]]);
                $p2 = (float) $d->value('SELECT price FROM canteen_items WHERE id = ?', [$itemIds[$i2]]);
                $tot = $p1 * $q1 + $p2 * $q2;
                $at = date('Y-m-d 10:' . sprintf('%02d', 10 + $k) . ':00', strtotime("-$ago days"));
                $sid2 = $d->insert('canteen_sales', ['sale_no' => Fees::nextNumber($d, 'CNT'), 'pupil_id' => $wp, 'paid_by' => 'wallet', 'total' => $tot, 'sold_by' => $bursarId, 'created_at' => $at]);
                $d->insert('canteen_sale_lines', ['sale_id' => $sid2, 'item_id' => $itemIds[$i1], 'name' => $i1, 'qty' => $q1, 'price' => $p1]);
                $d->insert('canteen_sale_lines', ['sale_id' => $sid2, 'item_id' => $itemIds[$i2], 'name' => $i2, 'qty' => $q2, 'price' => $p2]);
                $d->run('UPDATE canteen_items SET stock_qty = stock_qty - ? WHERE id = ?', [$q1, $itemIds[$i1]]);
                $d->run('UPDATE canteen_items SET stock_qty = stock_qty - ? WHERE id = ?', [$q2, $itemIds[$i2]]);
                $bal -= $tot;
                $d->insert('canteen_wallet_txns', ['pupil_id' => $wp, 'kind' => 'sale', 'amount' => -$tot, 'balance_after' => $bal, 'sale_id' => $sid2, 'created_by' => $bursarId, 'created_at' => $at]);
            }
            $d->update('canteen_wallets', ['balance' => $bal], 'pupil_id = ?', [$wp]);
        }
        // Lunch plan for Basic 1 (not yet billed)
        $b1 = $d->value("SELECT c.id FROM classes c JOIN levels l ON l.id = c.level_id WHERE l.code = 'B1' LIMIT 1");
        $plan = $d->value("SELECT id FROM canteen_plans ORDER BY price LIMIT 1");
        foreach ($d->all("SELECT pupil_id FROM enrolments WHERE class_id = ? AND academic_year_id = ? AND status = 'active' LIMIT 8", [$b1, $yid]) as $r) {
            $d->insert('canteen_plan_members', ['term_id' => $term['id'], 'pupil_id' => $r['pupil_id'], 'plan_id' => $plan]);
        }
        // Events
        $head = (int) $d->value("SELECT id FROM users WHERE email = 'head@demo.school'");
        $mkEv = function (array $e) use ($d, $head) {
            $cal = $d->insert('calendar_events', ['title' => $e['title'], 'category' => 'event', 'start_date' => substr($e['start_at'], 0, 10), 'end_date' => substr($e['end_at'] ?? $e['start_at'], 0, 10), 'audience' => $e['audience'] ?? 'all', 'created_by' => $head]);
            return $d->insert('school_events', $e + ['calendar_event_id' => $cal, 'created_by' => $head]);
        };
        $sports = $mkEv(['title' => 'Inter-house sports day', 'description' => "Athletics and games for every class from Basic 1 to JHS 3. Parents are welcome.\nPupils wear house colours.", 'venue' => 'School field',
            'start_at' => date('Y-m-d 08:00:00', strtotime('+12 days')), 'end_at' => date('Y-m-d 14:00:00', strtotime('+12 days')), 'audience' => 'parents', 'capacity' => 120, 'fee' => 20, 'reg_deadline' => date('Y-m-d', strtotime('+9 days'))]);
        $mkEv(['title' => 'PTA general meeting', 'description' => 'Termly report from the Head, fees, and the new canteen.', 'venue' => 'Assembly hall', 'start_at' => date('Y-m-d 15:00:00', strtotime('next saturday +7 days')), 'audience' => 'parents', 'registration' => 0]);
        $mkEv(['title' => 'Visit to Kakum National Park', 'venue' => 'Kakum, Central Region', 'start_at' => date('Y-m-d 06:00:00', strtotime('-20 days')), 'audience' => 'all', 'fee' => 0, 'status' => 'completed', 'registration' => 1]);
        foreach (array_slice($wPupils, 1, 6) as $wp) {
            $d->insert('event_registrations', ['event_id' => $sports, 'pupil_id' => $wp, 'status' => 'registered']);
        }
        // Appointments: the practice child's class teacher and the Head offer times
        $ctId = $kid ? ($teachers[$kid['class_id']] ?? null) : null;
        $tTeacher = $d->value("SELECT id FROM appointment_types WHERE name = 'Meet the class teacher'");
        $tHead = $d->value("SELECT id FROM appointment_types WHERE name = 'Meet the Head Teacher'");
        foreach ([2, 4] as $wd) {
            if ($ctId) {
                $d->insert('appointment_availability', ['user_id' => $ctId, 'type_id' => $tTeacher, 'weekday' => $wd, 'start_time' => '14:30:00', 'end_time' => '15:30:00']);
            }
            $d->insert('appointment_availability', ['user_id' => $head, 'type_id' => $tHead, 'weekday' => $wd + 1, 'start_time' => '10:00:00', 'end_time' => '11:30:00']);
        }
        if ($ctId && $kid) {
            $nextTue = date('Y-m-d', strtotime('next tuesday'));
            $d->insert('appointments', ['type_id' => $tTeacher, 'staff_id' => $ctId, 'booked_by' => $pu, 'pupil_id' => $kid['id'], 'start_at' => "$nextTue 14:30:00", 'end_at' => "$nextTue 14:50:00", 'answer' => 'Progress in Mathematics this term.']);
        }

        // ---- Sprint 19: payment plan, merit scores, bus papers, numbered circular -------------------
        if ($pi = $d->one("SELECT * FROM invoices WHERE status IN ('unpaid','part') AND kind = 'term' ORDER BY total - paid DESC LIMIT 1")) {
            $left = round((float) $pi['total'] - (float) $pi['paid'], 2);
            $seq = 0;
            if ((float) $pi['paid'] > 0) {
                $d->insert('invoice_instalments', ['invoice_id' => $pi['id'], 'seq' => ++$seq, 'due_date' => date('Y-m-d', strtotime('-20 days')), 'amount' => $pi['paid'], 'reminded_at' => date('Y-m-d H:i:s')]);
            }
            $third = floor($left / 3 * 100) / 100;
            foreach ([-5, 25, 55] as $k => $off) {
                $d->insert('invoice_instalments', ['invoice_id' => $pi['id'], 'seq' => ++$seq, 'due_date' => date('Y-m-d', strtotime("$off days")), 'amount' => $k === 2 ? round($left - 2 * $third, 2) : $third, 'reminded_at' => $off < 0 ? date('Y-m-d H:i:s') : null]);
            }
        }
        foreach ($d->all("SELECT id FROM applications WHERE stage IN ('submitted','screening','interview')") as $k => $ap) {
            $d->update('applications', ['entrance_score' => 48 + ($k * 17) % 50, 'interview_score' => 55 + ($k * 11) % 40], 'id = ?', [$ap['id']]);
        }
        $d->run("UPDATE transport_routes SET driver_licence_no = CONCAT('DL-', id, '-2291'), driver_licence_expiry = CURDATE() + INTERVAL (id * 20) DAY, roadworthy_expiry = CURDATE() + INTERVAL 200 DAY, insurance_expiry = CURDATE() + INTERVAL 150 DAY");
        $d->run("UPDATE notices SET circular_no = CONCAT('NIS/CIR/', YEAR(publish_at), '/', LPAD(id, 3, '0')), priority = 'important' WHERE category = 'circular'");

        // ---- Pupil welfare: behaviour log and a few cases ---------------------------------------
        $ctOf = fn(int $classId) => (int) $d->value('SELECT class_teacher_id FROM classes WHERE id = ?', [$classId]) ?: $head;
        foreach (array_slice($pupils, 0, 60) as $i => $p) {
            $daysAgo = 3 + ($i * 7) % 45;
            $merit = $i % 3 !== 0;
            $d->insert('behaviour_records', ['pupil_id' => $p['id'], 'term_id' => $term['id'], 'kind' => $merit ? 'merit' : 'concern',
                'category' => $merit ? \Welfare::MERITS[$i % 7] : \Welfare::CONCERNS[$i % 8], 'action_taken' => $merit ? null : array_keys(\Welfare::ACTIONS)[$i % 6],
                'points' => 1, 'occurred_on' => date('Y-m-d', strtotime("-$daysAgo days")), 'recorded_by' => $ctOf((int) $p['class_id'])]);
        }
        foreach ([[1, 'incident', 'Fell in the playground, grazed knee', 'low', 'open', 'Cleaned and plaster applied at the sick bay', 2], [2, 'counselling', 'Withdrawn and tearful since mid-term', 'medium', 'monitoring', null, 4],
                  [3, 'absence', '3 days absent in the last week', 'medium', 'open', null, -1], [4, 'safeguarding', 'Concern raised by class teacher', 'high', 'open', null, 1]] as [$k, $kind, $title, $risk, $st, $fa, $fu]) {
            $pp = $pupils[($k * 13) % count($pupils)];
            $cid = $d->insert('welfare_cases', ['ref_no' => sprintf('WEL-%s-%05d', date('Y'), $k), 'pupil_id' => $pp['id'], 'kind' => $kind, 'title' => $title, 'risk' => $risk, 'status' => $st,
                'assigned_to' => $kind === 'safeguarding' ? null : $ctOf((int) $pp['class_id']), 'first_aid' => $fa, 'parent_informed' => $kind === 'incident' ? 1 : 0,
                'follow_up_on' => date('Y-m-d', strtotime("$fu days")), 'opened_by' => $kind === 'absence' ? null : $ctOf((int) $pp['class_id'])]);
            $d->insert('welfare_notes', ['case_id' => $cid, 'note' => 'Case opened and parent contacted.', 'created_by' => $kind === 'safeguarding' ? $head : $ctOf((int) $pp['class_id'])]);
        }
        $d->run('INSERT IGNORE INTO number_sequences (name, next_value) VALUES (?, 5)', ['wel_' . date('Y')]);

        // ---- Board view: spending budget, board members & meetings, policy and risk registers -------
        foreach (['SAL' => 160000, 'TLM' => 9000, 'FOOD' => 12000, 'UTIL' => 7500, 'REP' => 6000, 'CLEAN' => 3000, 'STAT' => 2500] as $code => $amt) {
            if ($cid = $d->value("SELECT id FROM fin_categories WHERE code = ? AND kind = 'expense'", [$code])) {
                $d->run('INSERT IGNORE INTO fin_budgets (academic_year_id, category_id, amount) VALUES (?, ?, ?)', [$yid, $cid, $amt]);
            }
        }
        $members = [];
        foreach ([['Pastor Daniel Owusu', 'Chairman', '0200009001'], ['Mrs. Grace Mensah', 'Vice Chair', '0200009002'], ['Mr. Kofi Boateng', 'Secretary', '0200009003'],
                  ['Dr. Ama Serwaa', 'Member (Education)', '0200009004'], ['Elder Yaw Darko', 'Member (Finance)', '0200009005'], ['Mrs. Comfort Asante', 'Head Teacher (ex officio)', '0200000001']] as [$n, $pos, $ph]) {
            $members[] = $d->insert('board_members', ['name' => $n, 'position' => $pos, 'phone' => $ph]);
        }
        foreach ([[-150, 'Board of Governors — Term 3 review'], [-40, 'Board of Governors — Term 1 opening'], [12, 'Board of Governors — mid-term finance review']] as $k => [$off, $title]) {
            $mid = $d->insert('board_meetings', ['meeting_date' => date('Y-m-d', strtotime("$off days")), 'start_time' => '16:00:00', 'title' => $title, 'kind' => 'regular', 'venue' => 'Staff common room',
                'agenda' => "1. Opening prayer\n2. Head's report\n3. Finance report\n4. Any other business", 'created_by' => $head]);
            if ($off < 0) {
                foreach ($members as $j => $bm) {
                    $d->insert('board_attendance', ['meeting_id' => $mid, 'member_id' => $bm, 'status' => ($j + $k) % 6 === 4 ? 'apology' : 'present']);
                }
            }
        }
        foreach ([['Child protection & safeguarding policy', 'Safeguarding & child protection', 12, -300], ['Fees & bursary policy', 'Finance', 12, -200], ['Admissions policy', 'Admissions', 24, -400],
                  ['Behaviour & discipline policy', 'Discipline & conduct', 12, -380], ['Health & safety policy', 'Health & safety', 12, -100], ['Data protection policy (Act 843)', 'Data protection', 24, -700],
                  ['Staff code of conduct', 'Human resources', 24, -500], ['Procurement & requisitions policy', 'Finance', 12, -340]] as [$t, $c, $mo, $off]) {
            $last = date('Y-m-d', strtotime("$off days"));
            $d->insert('school_policies', ['title' => $t, 'category' => $c, 'owner' => 'Head Teacher', 'approved_on' => $last, 'last_reviewed' => $last, 'review_months' => $mo,
                'next_review' => date('Y-m-d', strtotime("$last +$mo months")), 'created_by' => $head]);
        }
        foreach ([['Fee arrears above 30% put salaries at risk', 'financial', 4, 4, 'Bursar', 'Payment plans, SMS reminders, termly debtors review by the Board.', 'in_progress', 20],
                  ['Teachers without a current NTC licence', 'compliance', 3, 4, 'Head Teacher', 'Licence check each term; dashboard alert 60 days before expiry.', 'in_place', 45],
                  ['Student injury during break or sports', 'health_safety', 3, 4, 'Head Teacher', 'Duty rota, first-aid trained staff, sick bay log.', 'in_place', 60],
                  ['Loss of school records (no off-site backup)', 'ict', 2, 5, 'Super Admin', 'Daily backup with a copy kept off the premises.', 'in_progress', -5],
                  ['Falling enrolment in Creche & KG', 'strategic', 3, 3, 'Admissions Officer', 'Open day, church announcements, enquiry follow-up.', 'not_started', 30],
                  ['Bus breakdown or expired papers', 'operational', 2, 3, 'Transport officer', 'Roadworthy and insurance tracked with reminders.', 'in_place', 90]] as $k => [$t, $c, $l, $i, $o, $mit, $ms, $rev]) {
            $d->insert('risks', ['ref_no' => sprintf('RSK-%s-%05d', date('Y'), $k + 1), 'title' => $t, 'category' => $c, 'likelihood' => $l, 'impact' => $i, 'owner' => $o, 'mitigation' => $mit,
                'mitigation_status' => $ms, 'review_date' => date('Y-m-d', strtotime("$rev days")), 'created_by' => $head]);
        }
        $d->run("INSERT IGNORE INTO number_sequences (name, next_value) VALUES (?, 7)", ['rsk_' . date('Y')]);

        // ---- Subject Management: weekly lesson notes (from the scheme of work) and the textbooks list --------
        $coord = (int) $d->value("SELECT id FROM users WHERE role = 'academic_coordinator' LIMIT 1") ?: $head;
        $k = 0;
        foreach ($d->all('SELECT cs.id, cs.teacher_id, c.class_teacher_id FROM class_subjects cs JOIN classes c ON c.id = cs.class_id JOIN levels l ON l.id = c.level_id WHERE l.sort_order >= 4 ORDER BY cs.id') as $cs) {
            $k++;
            for ($wk = 1; $wk <= min($curWeek, $weeks); $wk++) {
                $roll = ($k * 7 + $wk * 3) % 10;
                if ($wk === $curWeek && $roll > 3) {
                    continue; // this week: most not written yet
                }
                if ($wk === $curWeek - 1 && $roll > 7) {
                    continue; // last week: a few late
                }
                $st = $wk === $curWeek ? 'draft' : ($wk === $curWeek - 1 ? ($roll < 4 ? 'submitted' : 'approved') : ($roll === 9 ? 'returned' : 'approved'));
                $top = $d->one('SELECT topic, strand_id FROM scheme_items WHERE term_id = ? AND class_subject_id = ? AND week_no = ? LIMIT 1', [$term['id'], $cs['id'], $wk]);
                if (!$top) {
                    continue;
                }
                $by = (int) ($cs['teacher_id'] ?: $cs['class_teacher_id']) ?: null;
                $d->insert('lesson_notes', ['term_id' => $term['id'], 'class_subject_id' => $cs['id'], 'week_no' => $wk, 'strand_id' => $top['strand_id'], 'topic' => $top['topic'],
                    'indicators' => 'Learners can explain and apply ' . mb_strtolower(mb_substr($top['topic'], 0, 80)) . '.', 'core_competencies' => 'Critical thinking & problem solving, Communication & collaboration',
                    'resources' => 'Textbook, chalkboard illustrations, real objects', 'starter' => 'Recall the previous lesson with quick oral questions.',
                    'main_activities' => "1. Teacher introduces the topic with examples.\n2. Learners work in small groups on the activity.\n3. Groups present; teacher corrects misconceptions.",
                    'plenary' => 'Learners summarise what they learnt.', 'assessment' => 'Five short exercises; exit question.', 'status' => $st,
                    'submitted_at' => $st === 'draft' ? null : date('Y-m-d H:i:s', strtotime($term['start_date'] . ' +' . (($wk - 1) * 7) . ' days')),
                    'reviewed_by' => in_array($st, ['approved', 'returned'], true) ? $coord : null, 'reviewed_at' => in_array($st, ['approved', 'returned'], true) ? date('Y-m-d H:i:s', strtotime($term['start_date'] . ' +' . (($wk - 1) * 7 + 1) . ' days')) : null,
                    'review_comment' => $st === 'returned' ? 'Add a clear exit question to the assessment.' : null, 'created_by' => $by]);
            }
        }
        $lvl = fn(string $n) => (int) $d->value('SELECT id FROM levels WHERE name = ?', [$n]) ?: null;
        $sub = fn(string $code) => (int) $d->value('SELECT id FROM subjects WHERE code = ?', [$code]);
        foreach ([['ENG', 'Basic 4', 'English Language for Basic 4 — Learner\'s Book', 'Sedco / NaCCA', 'nacca', 1, 9], ['MATH', 'Basic 4', 'Mathematics for Basic 4 — Learner\'s Book', 'Aki-Ola / NaCCA', 'nacca', 1, 14],
                  ['SCI', 'Basic 4', 'Science for Basic 4 — Learner\'s Book', 'Approachers / NaCCA', 'nacca', 1, 5], ['MATH', 'JHS 1', 'Mathematics for JHS 1 — Learner\'s Book', 'Golden Publications / NaCCA', 'nacca', 1, 12],
                  ['ENG', 'JHS 1', 'English Language for JHS 1 — Learner\'s Book', 'Sedco / NaCCA', 'nacca', 1, 12], ['SCI', 'JHS 3', 'Integrated Science for JHS 3', 'Aki-Ola', 'ges', 1, 8],
                  ['SOC', 'JHS 2', 'Social Studies for JHS 2', 'Smartline', 'nacca', 1, 13], ['MATH', 'Basic 4', 'Mathematics Teacher\'s Guide Basic 4', 'NaCCA', 'nacca', 0, 2],
                  ['COMP', null, 'Computing for Basic Schools', 'Kingdom Books', 'school', 0, 6], ['BIB', null, 'Bible Story Book (Primary)', 'Advent Press', 'school', 0, 20]] as [$code, $ln, $t, $pub, $ap, $per, $held]) {
            if ($sid = $sub($code)) {
                $d->insert('subject_materials', ['subject_id' => $sid, 'level_id' => $ln ? $lvl($ln) : null, 'kind' => str_contains($t, 'Guide') ? 'teacher_guide' : 'textbook', 'title' => $t,
                    'author_publisher' => $pub, 'approved_by' => $ap, 'per_student' => $per, 'copies_held' => $held, 'created_by' => $coord]);
            }
        }

        // ---- finance extras: suppliers & bills, petty cash, fixed assets (when the database has them)
        if ($d->value("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'suppliers'")) {
            $cat = fn(string $c) => (int) $d->value('SELECT id FROM fin_categories WHERE code = ?', [$c]) ?: null;
            $sup = [];
            foreach ([['Kasoa Timber & Furniture Works', 'goods', 'REP', 'C0004455667', '0244123456'], ['Bright Stationery Ltd', 'goods', 'STAT', 'C0001122334', '0201234567'], ['Mensah Electrical Services', 'services', 'REP', 'P0009988776', '0277654321']] as [$n, $ty, $c, $tin, $ph]) {
                $sup[] = $d->insert('suppliers', ['name' => $n, 'supply_type' => $ty, 'category_id' => $cat($c), 'tin' => $tin, 'phone' => $ph, 'pay_details' => 'MoMo ' . $ph]);
            }
            $bn = 0;
            $bill = function (int $sid, string $desc, float $amt, string $ty, string $c, string $date, string $due, string $ref) use ($d, $cat, $bursar, &$bn): int {
                $rate = $amt > 2000 && $ty !== 'exempt' ? ['goods' => 3, 'works' => 5, 'services' => 7.5][$ty] : 0;
                return $d->insert('supplier_bills', ['bill_no' => sprintf('SB-%s-%05d', date('Y'), ++$bn), 'supplier_id' => $sid, 'supplier_ref' => $ref, 'bill_date' => $date, 'due_date' => $due,
                    'category_id' => $cat($c), 'description' => $desc, 'amount' => $amt, 'supply_type' => $ty, 'wht_rate' => $rate, 'wht_amount' => round($amt * $rate / 100, 2), 'created_by' => $bursar]);
            };
            $b1 = $bill($sup[0], '30 dual desks for JHS 1', 9000, 'goods', 'REP', date('Y-m-d', strtotime('-20 days')), date('Y-m-d', strtotime('+10 days')), 'KTF/0912');
            $bill($sup[1], 'Exercise books and printing paper', 1850, 'goods', 'STAT', date('Y-m-d', strtotime('-6 days')), date('Y-m-d', strtotime('+24 days')), 'BS-2231');
            $b3 = $bill($sup[2], 'Rewiring of the Basic block', 4200, 'services', 'REP', date('Y-m-d', strtotime('-35 days')), date('Y-m-d', strtotime('-5 days')), 'MES-77');
            // part payment on the desks: 4,000 settled (WHT 3% = 120)
            $txn++;
            $pv = $d->insert('supplier_payments', ['pv_no' => sprintf('PV-%s-%05d', date('Y'), 1), 'bill_id' => $b1, 'pay_date' => date('Y-m-d', strtotime('-12 days')), 'account_id' => $acct['bank'], 'gross' => 4000, 'wht' => 120, 'net' => 3880, 'reference' => 'CHQ 000412', 'created_by' => $bursar]);
            $tid = $d->insert('fin_transactions', ['txn_no' => sprintf('PV-%s-%05d', date('Y'), 1), 'kind' => 'expense', 'txn_date' => date('Y-m-d', strtotime('-12 days')), 'account_id' => $acct['bank'], 'category_id' => $cat('REP'),
                'amount' => 3880, 'description' => 'Kasoa Timber & Furniture Works: 30 dual desks for JHS 1 (SB-' . date('Y') . '-00001, WHT 120.00 withheld)', 'party' => 'Kasoa Timber & Furniture Works', 'source' => 'supplier_payment', 'source_id' => $pv, 'created_by' => $bursar]);
            $d->update('supplier_payments', ['txn_id' => $tid], 'id = ?', [$pv]);
            $d->update('supplier_bills', ['paid_gross' => 4000, 'status' => 'part'], 'id = ?', [$b1]);
            unset($b3);
            // petty cash box held by the class teacher of Basic 1
            $t1 = (int) $d->value("SELECT id FROM users WHERE email = 'teacher1@demo.school'");
            $box = $d->insert('fin_accounts', ['name' => 'Petty cash — Head\'s office', 'kind' => 'petty', 'opening_balance' => 0, 'opening_date' => $term['start_date'], 'custodian_id' => $head, 'float_amount' => 500, 'spend_limit' => 200, 'sort_order' => 50]);
            $d->insert('fin_transactions', ['txn_no' => sprintf('IMP-%s-%05d', date('Y'), 1), 'kind' => 'transfer', 'txn_date' => $term['start_date'], 'account_id' => $acct['cash'], 'to_account_id' => $box, 'amount' => 500, 'description' => 'Petty cash float issued', 'source' => 'petty_topup', 'created_by' => $bursar]);
            foreach ([['Chalk and markers', 'STAT', 85], ['Fuel for the generator', 'UTIL', 150], ['Soap and tissue for the washrooms', 'CLEAN', 60]] as $i => [$desc, $c, $amt]) {
                $d->insert('fin_transactions', ['txn_no' => sprintf('PCV-%s-%05d', date('Y'), $i + 1), 'kind' => 'expense', 'txn_date' => date('Y-m-d', strtotime('-' . (9 - $i * 3) . ' days')), 'account_id' => $box, 'category_id' => $cat($c), 'amount' => $amt, 'description' => $desc, 'source' => 'petty_spend', 'created_by' => $head]);
            }
            unset($t1);
            // fixed assets
            $fa = 0;
            foreach ([['Classroom block (Basic 1–6)', 'buildings', 'Main compound', 2016, 420000, 40, 1, 'church'], ['School bus (Toyota Coaster)', 'vehicles', 'Bus park', 2022, 310000, 8, 1, 'pta'],
                      ['Dual desk', 'furniture', 'JHS 1 classroom', 2023, 650, 10, 12, 'school'], ['Desktop computer (ICT lab)', 'ict', 'ICT lab', 2024, 5200, 4, 6, 'getfund'],
                      ['Photocopier', 'equipment', 'Office', 2025, 14500, 5, 1, 'school'], ['Land — school compound', 'land', 'Kasoa', 2010, 150000, 0, 1, 'church']] as [$n, $c, $loc, $y, $cost, $life, $qty, $fund]) {
                for ($i = 0; $i < $qty; $i++) {
                    $aid = $d->insert('fixed_assets', ['asset_no' => sprintf('FA-%s-%05d', date('Y'), ++$fa), 'name' => $n, 'category' => $c, 'location' => $loc, 'funded_by' => $fund, 'purchase_date' => $y . '-02-15', 'cost' => $cost, 'life_years' => $life, 'created_by' => $bursar]);
                    $d->insert('asset_log', ['asset_id' => $aid, 'action' => 'added', 'note' => 'At ' . $loc, 'user_id' => $bursar]);
                }
            }
            $d->run("INSERT IGNORE INTO number_sequences (name, next_value) VALUES (?, ?), (?, ?), (?, ?), (?, ?), (?, ?)", ['sb_' . date('Y'), $bn + 1, 'pv_' . date('Y'), 2, 'imp_' . date('Y'), 2, 'pcv_' . date('Y'), 4, 'fa_' . date('Y'), $fa + 1]);

            // ---- Finance MVP (migration 032): departments on bills, term caps, a purchase order part-delivered,
            // a manual journal waiting for the Head, a Storekeeper and an External Auditor
            if ($d->value("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'purchase_orders'")) {
                $store = $user('Mr. Kwabena Asare (Storekeeper)', 'storekeeper@demo.school', 'storekeeper', '0200000005');
                $aud = $user('Mrs. Abena Owusu (External Auditor)', 'auditor@demo.school', 'auditor', '0200000006');
                if ($d->value("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'pickups'")) {
                    $user('Mr. Yaw Mensah (Security)', 'security@demo.school', 'security', '0200000007'); // gate pick-up check
                }
                $d->update('users', ['access_until' => date('Y-m-d', strtotime('+60 days'))], 'id = ?', [$aud]);
                $d->update('supplier_bills', ['department' => 'JHS'], 'id = ?', [$b1]);
                $d->update('supplier_bills', ['department' => 'Maintenance'], "description LIKE 'Rewiring%'");
                $d->update('supplier_bills', ['department' => 'Administration'], "description LIKE 'Exercise books and printing%'");
                $d->update('fin_transactions', ['department' => 'JHS'], "source = 'supplier_payment'");
                foreach (['Creche & KG' => 4000, 'Lower Primary (B1–B3)' => 3000, 'Upper Primary (B4–B6)' => 8000, 'JHS' => 10000, 'Kitchen / Feeding' => 12000, 'Maintenance' => 6000, 'Administration' => 3000] as $dn => $cap) {
                    $d->insert('fin_dept_caps', ['term_id' => $term['id'], 'department' => $dn, 'amount' => $cap]);
                    $d->insert('fin_dept_cap_log', ['term_id' => $term['id'], 'department' => $dn, 'kind' => 'set', 'new_amount' => $cap, 'note' => 'Term caps agreed at the management meeting', 'user_id' => $head]);
                }
                // requisition → approved → PO sent → 8 of 10 shelves received → supplier invoices all 10
                $up = $teachers[$classes[8]['id'] ?? $classes[0]['id']] ?? $head;
                $rid = $d->insert('requisitions', [
                    'req_no' => Fees::nextNumber($d, 'REQ'), 'title' => 'Library shelves and reading tables', 'purpose' => 'Reading corner for Basic 4–6', 'department' => 'Upper Primary (B4–B6)',
                    'category_id' => $cat('ASSET') ?: $cat('REP'), 'needed_by' => date('Y-m-d', strtotime('+5 days')), 'amount' => 6000, 'approved_amount' => 6000, 'status' => 'approved',
                    'requested_by' => $up, 'submitted_at' => date('Y-m-d H:i:s', strtotime('-16 days')), 'approved_by' => $head, 'approved_at' => date('Y-m-d H:i:s', strtotime('-15 days')),
                ]);
                foreach ([['Library shelf (5-tier)', 10, 'pcs', 450], ['Reading table', 2, 'pcs', 750]] as [$desc, $qty, $unit, $cost]) {
                    $d->insert('requisition_items', ['requisition_id' => $rid, 'description' => $desc, 'quantity' => $qty, 'unit' => $unit, 'unit_cost' => $cost]);
                }
                $d->insert('requisition_log', ['requisition_id' => $rid, 'action' => 'submitted', 'from_status' => 'draft', 'to_status' => 'submitted', 'user_id' => $up]);
                $d->insert('requisition_log', ['requisition_id' => $rid, 'action' => 'approve', 'from_status' => 'submitted', 'to_status' => 'approved', 'user_id' => $head]);
                $po = $d->insert('purchase_orders', [
                    'po_no' => Fees::nextNumber($d, 'PO'), 'requisition_id' => $rid, 'supplier_id' => $sup[0], 'order_date' => date('Y-m-d', strtotime('-14 days')), 'expected_date' => date('Y-m-d', strtotime('-4 days')),
                    'department' => 'Upper Primary (B4–B6)', 'category_id' => $cat('ASSET') ?: $cat('REP'), 'deliver_to' => 'School stores', 'total' => 6000, 'status' => 'part',
                    'created_by' => $bursar, 'sent_by' => $bursar, 'sent_at' => date('Y-m-d H:i:s', strtotime('-14 days')),
                ]);
                $d->update('requisitions', ['purchase_order_id' => $po], 'id = ?', [$rid]);
                $l1 = $d->insert('po_lines', ['po_id' => $po, 'description' => 'Library shelf (5-tier)', 'unit' => 'pcs', 'qty' => 10, 'unit_price' => 450, 'received_qty' => 8]);
                $l2 = $d->insert('po_lines', ['po_id' => $po, 'description' => 'Reading table', 'unit' => 'pcs', 'qty' => 2, 'unit_price' => 750, 'received_qty' => 2]);
                $g = $d->insert('goods_received', ['grn_no' => Fees::nextNumber($d, 'GRN'), 'po_id' => $po, 'received_date' => date('Y-m-d', strtotime('-5 days')), 'delivery_note' => 'KTF-DN-118', 'note' => '2 shelves to follow', 'received_by' => $store]);
                $d->insert('grn_lines', ['grn_id' => $g, 'po_line_id' => $l1, 'qty' => 8]);
                $d->insert('grn_lines', ['grn_id' => $g, 'po_line_id' => $l2, 'qty' => 2]);
                $sb = $d->insert('supplier_bills', ['bill_no' => Fees::nextNumber($d, 'SB'), 'supplier_id' => $sup[0], 'supplier_ref' => 'KTF/0930', 'bill_date' => date('Y-m-d', strtotime('-3 days')), 'due_date' => date('Y-m-d', strtotime('+27 days')),
                    'category_id' => $cat('ASSET') ?: $cat('REP'), 'description' => 'Goods on PO', 'amount' => 6000, 'supply_type' => 'goods', 'wht_rate' => 3, 'wht_amount' => 180, 'po_id' => $po, 'department' => 'Upper Primary (B4–B6)', 'created_by' => $bursar]);
                $d->update('supplier_bills', ['description' => 'Goods on ' . $d->value('SELECT po_no FROM purchase_orders WHERE id = ?', [$po])], 'id = ?', [$sb]);
                $d->insert('supplier_bill_lines', ['bill_id' => $sb, 'po_line_id' => $l1, 'description' => 'Library shelf (5-tier)', 'qty' => 10, 'unit_price' => 450]);
                $d->insert('supplier_bill_lines', ['bill_id' => $sb, 'po_line_id' => $l2, 'description' => 'Reading table', 'qty' => 2, 'unit_price' => 750]);
                // a correction typed by the Bursar, waiting for the Head
                $d->run('INSERT INTO gl_accounts (code, name, type, category_id) SELECT ?, name, kind, id FROM fin_categories WHERE code = ? AND NOT EXISTS (SELECT 1 FROM gl_accounts WHERE category_id = fin_categories.id)', ['5080', 'CLEAN']);
                $d->run('INSERT INTO gl_accounts (code, name, type, category_id) SELECT ?, name, kind, id FROM fin_categories WHERE code = ? AND NOT EXISTS (SELECT 1 FROM gl_accounts WHERE category_id = fin_categories.id)', ['5050', 'REP']);
                $jn = $d->insert('gl_journals', ['jnl_no' => Fees::nextNumber($d, 'JNL'), 'entry_date' => date('Y-m-d', strtotime('-2 days')), 'memo' => 'Cleaning materials entered under Repairs by mistake', 'total' => 310, 'drafted_by' => $bursar]);
                $d->insert('gl_journal_lines', ['journal_id' => $jn, 'account_id' => (int) $d->value("SELECT g.id FROM gl_accounts g JOIN fin_categories c ON c.id = g.category_id WHERE c.code = 'CLEAN'"), 'debit' => 310, 'credit' => 0, 'note' => 'Soap, bleach, mops']);
                $d->insert('gl_journal_lines', ['journal_id' => $jn, 'account_id' => (int) $d->value("SELECT g.id FROM gl_accounts g JOIN fin_categories c ON c.id = g.category_id WHERE c.code = 'REP'"), 'debit' => 0, 'credit' => 310, 'note' => null]);
                unset($store);
            }
        }

        // The Administrator (runs Administration) — added last so every other practice account keeps its number.
        if (!$d->value("SELECT 1 FROM roles WHERE role_key = 'administrator'")) {
            throw new RuntimeException('The administrator role is not configured in the practice database.');
        }
        if (!$d->value("SELECT 1 FROM users WHERE email = 'administrator@demo.school'")) {
            $user('Mr. Kwame Boateng (Administrator)', 'administrator@demo.school', 'administrator', '0200000009');
        }

        $adminCount = (int) $d->value("SELECT COUNT(*) FROM users WHERE role = 'administrator'");
        for ($i = 2; $adminCount < 15; $i++) {
            $email = 'administrator' . $i . '@demo.school';
            if ($d->value('SELECT 1 FROM users WHERE email = ? OR username = ?', [$email, $email])) {
                continue;
            }
            $user('Practice Administrator ' . $i, $email, 'administrator');
            $adminCount++;
        }

        $teacherCount = (int) $d->value("SELECT COUNT(*) FROM users WHERE role IN ('class_teacher', 'subject_teacher')");
        if ($teacherCount < 15) {
            throw new RuntimeException('The practice copy needs at least 15 class or subject teacher accounts.');
        }

        $parentCount = (int) $d->value("SELECT COUNT(*) FROM users WHERE role = 'parent'");
        foreach ($d->all('SELECT g.* FROM guardians g WHERE g.user_id IS NULL AND EXISTS (SELECT 1 FROM pupil_guardian pg WHERE pg.guardian_id = g.id) ORDER BY g.id') as $guardian) {
            if ($parentCount >= 15) {
                break;
            }
            $phone = trim((string) $guardian['phone']);
            $email = 'parent' . $guardian['id'] . '@parents.demo';
            if (!preg_match('/^0\d{9}$/', $phone)
                || $d->value('SELECT 1 FROM users WHERE username = ? OR email = ?', [$phone, $email])) {
                continue;
            }
            $parentId = $d->insert('users', [
                'name' => $guardian['name'], 'email' => $email, 'username' => $phone, 'phone' => $phone,
                'role' => 'parent', 'password_hash' => $hash, 'must_change_password' => 0,
            ]);
            $d->update('guardians', ['user_id' => $parentId], 'id = ?', [$guardian['id']]);
            $parentCount++;
        }
        if ($parentCount < 15) {
            throw new RuntimeException('The practice copy needs at least 15 guardians with usable phone numbers.');
        }

        $studentCount = (int) $d->value("SELECT COUNT(*) FROM users WHERE role = 'pupil'");
        foreach ($d->all(
            "SELECT p.* FROM pupils p JOIN enrolments e ON e.pupil_id = p.id
             WHERE p.user_id IS NULL AND p.status = 'active' AND p.deleted_at IS NULL
               AND e.academic_year_id = ? AND e.status = 'active'
             ORDER BY p.id",
            [$yid]
        ) as $pupil) {
            if ($studentCount >= 15) {
                break;
            }
            $username = strtolower((string) $pupil['admission_no']);
            $email = 'pupil' . $pupil['id'] . '@users.nisdas.local';
            if ($d->value('SELECT 1 FROM users WHERE username = ? OR email = ?', [$username, $email])) {
                continue;
            }
            $pupilId = $d->insert('users', [
                'name' => trim($pupil['first_name'] . ' ' . $pupil['last_name']), 'email' => $email, 'username' => $username,
                'role' => 'pupil', 'password_hash' => $hash, 'must_change_password' => 0,
            ]);
            $d->update('pupils', ['user_id' => $pupilId], 'id = ?', [$pupil['id']]);
            $studentCount++;
        }
        if ($studentCount < 15) {
            throw new RuntimeException('The practice copy needs at least 15 active, enrolled pupils.');
        }

        $d->run("INSERT INTO audit_log (user_id, action, entity, details) VALUES (?, 'practice_copy_created', 'system', ?)", [
            $admin,
            json_encode(['pupils' => count($pupils), 'test_logins' => ['administrators' => $adminCount, 'teachers' => $teacherCount, 'parents' => $parentCount, 'students' => $studentCount]]),
        ]);
        return sprintf(
            'Practice copy ready: %d students in %d classes, %d staff records, %d administrator, %d teacher, %d parent and %d student logins, %d bills, %d receipts, %d score entries, %d school days of attendance.',
            count($pupils), count($classes), (int) $d->value('SELECT COUNT(*) FROM staff'), $adminCount, $teacherCount, $parentCount, $studentCount, $inv, $rct, $scoreRows, count($days)
        );
    }
}
