<?php
declare(strict_types=1);

/**
 * "Configure the school" wizard: every setting the modules need, in the order to do them,
 * each with its status, where to do it, and (where safe) a one-click default.
 */
final class SetupWizard
{
    /** @return array<int,array{key:string,group:string,title:string,why:string,done:?bool,detail:string,link:?string,action:?array}> */
    public static function steps(): array
    {
        $s = [];
        $add = function (string $key, string $group, string $title, string $why, ?bool $done, string $detail, ?string $link, ?array $action = null) use (&$s): void {
            $s[] = compact('key', 'group', 'title', 'why', 'done', 'detail', 'link', 'action');
        };
        $year = current_year();
        $term = current_term();
        $yid = $year['id'] ?? 0;
        $confirmed = fn(string $k) => (bool) db()->value('SELECT done_at FROM golive_checks WHERE check_key = ?', ['confirm:' . $k]);

        // ---------------------------------------------------------------- 1. The school
        $hasDetails = setting('school_phone') !== '' && setting('school_address') !== '';
        $add('school', 'The school', 'School details & crest', 'Printed on report cards, bills, receipts and payslips.', $hasDetails ? true : false,
            $hasDetails ? (setting('school_logo') ? 'Name, address, phone and uploaded crest' : 'Name, address and phone entered · official crest built in') : 'Add the address and phone number', 'setup');
        $pub = setting('public_url');
        $add('public_url', 'The school', 'Public web address', 'Links in SMS (bills, report cards, parent logins) must open from parents\' phones.', $pub !== '' ? true : null,
            $pub !== '' ? $pub : 'Not set yet — fine while the system only runs inside the school; set it once it is online', 'fees/settings');

        // ---------------------------------------------------------------- 2. Academic structure
        $add('year', 'Academic structure', 'Current academic year and term', 'Attendance, scores, bills and the calendar all belong to a term.', (bool) ($year && $term),
            $year && $term ? $year['name'] . ' · ' . $term['name'] . ' (' . fmt_date($term['start_date']) . ' – ' . fmt_date($term['end_date']) . ')' : 'No current year/term', 'setup/years',
            $year ? null : ['create_year', 'Create ' . self::defaultYearName() . ' with three terms']);
        $classes = (int) db()->value('SELECT COUNT(*) FROM classes WHERE is_active = 1');
        $noCt = (int) db()->value('SELECT COUNT(*) FROM classes WHERE is_active = 1 AND class_teacher_id IS NULL');
        $add('classes', 'Academic structure', 'Classes and class teachers', 'Class teachers take the register and write report remarks.', $classes > 0 && $noCt === 0,
            "$classes classes" . ($noCt ? " · $noCt without a class teacher" : ' · all have a class teacher'), 'setup/classes');
        $noSubj = (int) db()->value('SELECT COUNT(*) FROM classes c WHERE c.is_active = 1 AND NOT EXISTS (SELECT 1 FROM class_subjects cs WHERE cs.class_id = c.id)');
        $add('subjects', 'Academic structure', 'Subjects for each class', 'Score sheets and the time table are made from each class\'s subjects.', $noSubj === 0,
            $noSubj ? "$noSubj class(es) have no subjects" : 'Every class has its subjects', 'setup/subjects', $noSubj ? ['assign_subjects', 'Give every class the standard GES subjects'] : null);
        $noTeacher = (int) db()->value('SELECT COUNT(*) FROM class_subjects cs JOIN classes c ON c.id = cs.class_id WHERE c.is_active = 1 AND cs.teacher_id IS NULL');
        $lowerFix = (int) db()->value("SELECT COUNT(*) FROM class_subjects cs JOIN classes c ON c.id = cs.class_id JOIN levels l ON l.id = c.level_id WHERE c.is_active = 1 AND cs.teacher_id IS NULL AND c.class_teacher_id IS NOT NULL AND l.category IN ('creche','kg','primary')");
        $add('allocations', 'Academic structure', 'Subject teachers', 'Each teacher sees and fills only the score sheets of their subjects.', $noTeacher === 0 ? true : null,
            $noTeacher ? "$noTeacher class subject(s) have no subject teacher (the class teacher fills them)" : 'All subjects have a teacher', 'exams/allocations',
            $lowerFix ? ['class_teachers_teach', "Make class teachers the subject teacher in Creche–Basic 6 ($lowerFix subjects)"] : null);
        $add('grading', 'Academic structure', 'Grading & assessment weights', 'Class 50% + exams 50% with grades 1–9 (90–100 = 1 Excellent … 0–39 = 9 Fail) as on the school\'s printed terminal report, plus NaCCA levels for JHS — confirm.', $confirmed('grading'),
            $confirmed('grading') ? 'Confirmed by the school' : 'Using the default GES settings — not yet confirmed', 'exams/setup', $confirmed('grading') ? null : ['confirm_grading', 'Confirm the grading as shown in Exams → Grading setup']);
        $tt = (int) db()->value('SELECT COUNT(*) FROM timetable_slots');
        $add('timetable', 'Academic structure', 'Time table', 'Teachers see their lessons on the dashboard.', $tt > 0 ? true : null, $tt ? "$tt lessons placed" : 'Not generated yet (optional)', 'timetable');

        // ---------------------------------------------------------------- 3. People
        $staff = (int) db()->value("SELECT COUNT(*) FROM users WHERE is_active = 1 AND role NOT IN ('parent','pupil','super_admin')");
        $add('staff', 'People', 'Staff accounts', 'Every teacher and office staff member signs in with their own account.', $staff > 1, "$staff staff accounts", 'users', null);
        $pupils = (int) db()->value("SELECT COUNT(*) FROM enrolments e JOIN pupils p ON p.id = e.pupil_id WHERE e.academic_year_id = ? AND e.status = 'active' AND p.status = 'active'", [$yid]);
        $add('pupils', 'People', 'Students and parents', 'Import from Excel (fastest) or enter through Admission.', $pupils > 0, "$pupils students enrolled this year", 'imports');
        $logins = (int) db()->value("SELECT COUNT(*) FROM guardians WHERE user_id IS NOT NULL");
        $add('logins', 'People', 'Parent portal logins', 'Parents see bills, report cards and notices on their phones.', $logins > 0 ? true : null, $logins ? "$logins parents have logins" : 'None created yet (optional)', 'logins');

        // ---------------------------------------------------------------- 4. Money
        $add('fees', 'Finance', 'Approved fee schedule', 'Bills are made from the fee schedule — the installed amounts are samples.', setting('fee_schedule_confirmed') === '1',
            setting('fee_schedule_confirmed') === '1' ? 'Marked as the approved amounts' : 'Still the sample figures', 'fees/schedule');
        $bills = $term ? (int) db()->value("SELECT COUNT(*) FROM invoices WHERE term_id = ? AND kind = 'term' AND status <> 'cancelled'", [$term['id']]) : 0;
        $add('bills', 'Finance', 'Bills for this term', 'Parents can only pay once a bill exists.', $bills > 0 ? true : ($pupils ? false : null), $bills ? "$bills term bills issued" : 'No bills for the current term yet', 'fees/generate');
        $noOpening = (int) db()->value('SELECT COUNT(*) FROM fin_accounts WHERE is_active = 1 AND opening_date IS NULL');
        $add('opening', 'Finance', 'Opening balances of the school\'s accounts', 'So the cash book shows what is really in the cash box, bank and MoMo wallet.', $noOpening === 0 ? true : null,
            $noOpening ? "$noOpening account(s) without an opening balance and date" : 'Entered', 'finance/setup');
        $payStaff = (int) db()->value('SELECT COUNT(*) FROM staff WHERE is_active = 1');
        $unlinked = (int) db()->value("SELECT COUNT(*) FROM users u WHERE u.is_active = 1 AND u.role NOT IN ('parent','pupil','super_admin') AND NOT EXISTS (SELECT 1 FROM staff s WHERE s.user_id = u.id)");
        $noSalary = (int) db()->value('SELECT COUNT(*) FROM staff WHERE is_active = 1 AND basic_salary <= 0');
        $add('payroll', 'Finance', 'Staff pay records', 'Needed for payroll, payslips and SSNIT/PAYE schedules.', $payStaff > 0 && $noSalary === 0 ? true : ($payStaff ? false : null),
            $payStaff ? "$payStaff on the payroll" . ($noSalary ? " · $noSalary without a salary" : '') : 'No pay records yet', 'payroll/staff',
            $unlinked ? ['payroll_from_users', "Create pay records for $unlinked staff account(s) (salary to be filled in)"] : null);

        // ---------------------------------------------------------------- 5. SMS & payments
        $prov = SmsGateway::provider();
        $add('sms', 'SMS & online payment', 'SMS provider', 'Absence alerts, receipts, bills and notices go to parents by SMS.', $prov !== 'log',
            $prov === 'log' ? 'Test mode — messages are recorded but not sent' : SmsGateway::PROVIDERS[$prov] . ', sender "' . setting('sms_sender_id') . '"', 'messages/settings');
        $gw = PaymentGateway::active();
        $add('payments', 'SMS & online payment', 'Online fee payment (MoMo / card)', 'Optional — parents can always pay at the school, by bank or direct MoMo.', $gw === 'paystack' || $gw === 'hubtel' ? true : ($gw === 'test' ? false : null),
            PaymentGateway::GATEWAYS[$gw], 'fees/settings');

        // ---------------------------------------------------------------- 6. Safety
        $last = db()->one('SELECT created_at FROM backups ORDER BY id DESC LIMIT 1');
        $add('backups', 'Safety', 'Backups', 'A daily backup, copied off the computer.', $last && setting('backup_copy_dir') !== '' ? true : ($last ? null : false),
            $last ? 'Last backup ' . date('d M Y H:i', strtotime($last['created_at'] . ' UTC')) . (setting('backup_copy_dir') ? '' : ' · no copy folder set') : 'No backup yet', 'backups');
        $cron = setting('cron_last_run');
        $add('cron', 'Safety', 'Scheduled task running', 'Sends queued SMS, reminders and the daily backup.', $cron && strtotime($cron . ' UTC') > time() - 1800,
            $cron ? 'Last ran ' . date('d M H:i', strtotime($cron . ' UTC')) : 'Never ran — see README "Scheduled task"', 'messages/settings');
        $add('trial', 'Safety', 'Trial run with the Head and Bursar', 'Practise the real tasks before parents are told.', (bool) db()->value("SELECT COUNT(*) FROM golive_checks WHERE check_key LIKE 'trial:%' AND done_at IS NOT NULL") >= 10 ? true : null,
            (int) db()->value("SELECT COUNT(*) FROM golive_checks WHERE check_key LIKE 'trial:%' AND done_at IS NOT NULL") . ' of ' . count(GoLive::allTrialKeys()) . ' trial tasks ticked', 'golive');
        return $s;
    }

    /** Required steps done / total (optional steps — null — are not counted). */
    public static function progress(?array $steps = null): array
    {
        $steps = $steps ?? self::steps();
        $req = array_filter($steps, fn($x) => $x['done'] !== null);
        $done = count(array_filter($req, fn($x) => $x['done'] === true));
        return [$done, count($req)];
    }

    public static function defaultYearName(): string
    {
        $y = (int) date('Y');
        $start = (int) date('n') >= 8 ? $y : $y - 1;
        return $start . '/' . ($start + 1);
    }

    // ---------------------------------------------------------------- one-click defaults

    public static function createYear(): string
    {
        if (current_year()) {
            return 'A current academic year already exists.';
        }
        $name = self::defaultYearName();
        $start = (int) substr($name, 0, 4);
        // Typical GES basic-school calendar: three terms with Christmas and Easter breaks (adjust in Setup).
        $terms = [
            [1, 'Term 1', "$start-09-08", "$start-12-18"],
            [2, 'Term 2', ($start + 1) . '-01-08', ($start + 1) . '-04-02'],
            [3, 'Term 3', ($start + 1) . '-04-28', ($start + 1) . '-07-24'],
        ];
        return db()->transaction(function (Database $d) use ($name, $start, $terms) {
            $yid = $d->value('SELECT id FROM academic_years WHERE name = ?', [$name]);
            if (!$yid) {
                $yid = $d->insert('academic_years', ['name' => $name, 'start_date' => "$start-09-08", 'end_date' => ($start + 1) . '-07-24', 'is_current' => 0]);
                foreach ($terms as [$no, $tn, $ts, $te]) {
                    $d->insert('terms', ['academic_year_id' => $yid, 'term_no' => $no, 'name' => $tn, 'start_date' => $ts, 'end_date' => $te]);
                }
            }
            $d->run('UPDATE academic_years SET is_current = (id = ?)', [$yid]);
            $d->run('UPDATE terms SET is_current = 0');
            $today = date('Y-m-d');
            $cur = $d->value('SELECT id FROM terms WHERE academic_year_id = ? AND ? <= end_date ORDER BY term_no LIMIT 1', [$yid, $today])
                ?: $d->value('SELECT id FROM terms WHERE academic_year_id = ? ORDER BY term_no DESC LIMIT 1', [$yid]);
            $d->run('UPDATE terms SET is_current = 1 WHERE id = ?', [$cur]);
            audit('wizard_year_created', 'academic_years', (int) $yid, ['name' => $name]);
            return "Academic year $name created with three terms (typical GES dates). Check the dates in Setup → Academic years.";
        });
    }

    public static function assignSubjects(): string
    {
        $n = db()->run(
            'INSERT IGNORE INTO class_subjects (class_id, subject_id)
             SELECT c.id, s.id FROM classes c JOIN levels l ON l.id = c.level_id JOIN subjects s ON s.is_active = 1 AND FIND_IN_SET(l.category, s.applies_to) > 0
             WHERE c.is_active = 1 AND NOT EXISTS (SELECT 1 FROM class_subjects x WHERE x.class_id = c.id)'
        )->rowCount();
        audit('wizard_subjects_assigned', 'class_subjects', null, ['rows' => $n]);
        return "$n class subjects added. Remove any the school does not teach in Exams → Teachers & subjects.";
    }

    public static function classTeachersTeach(): string
    {
        $n = db()->run(
            "UPDATE class_subjects cs JOIN classes c ON c.id = cs.class_id JOIN levels l ON l.id = c.level_id
             SET cs.teacher_id = c.class_teacher_id
             WHERE c.is_active = 1 AND cs.teacher_id IS NULL AND c.class_teacher_id IS NOT NULL AND l.category IN ('creche','kg','primary')"
        )->rowCount();
        if (class_exists('Timetable')) {
            Timetable::syncTeachers();
        }
        audit('wizard_class_teachers_teach', 'class_subjects', null, ['rows' => $n]);
        return "$n subjects given to their class teacher. JHS subject teachers are set in Exams → Teachers & subjects.";
    }

    public static function confirmGrading(): string
    {
        db()->run('INSERT INTO golive_checks (check_key, done_by, done_at, note) VALUES (?, ?, NOW(), ?) ON DUPLICATE KEY UPDATE done_by = VALUES(done_by), done_at = NOW(), note = VALUES(note)',
            ['confirm:grading', Auth::id(), 'Confirmed in the setup wizard']);
        audit('wizard_grading_confirmed', 'golive_checks');
        return 'Grading marked as confirmed.';
    }

    public static function payrollFromUsers(): string
    {
        $n = 0;
        $positions = ['head_teacher' => 'Head Teacher', 'academic_coordinator' => 'Academic Coordinator', 'class_teacher' => 'Class Teacher', 'subject_teacher' => 'Subject Teacher', 'bursar' => 'Bursar / Accountant', 'admissions_officer' => 'Admissions Officer'];
        db()->transaction(function (Database $d) use (&$n, $positions) {
            foreach ($d->all("SELECT u.* FROM users u WHERE u.is_active = 1 AND u.role NOT IN ('parent','pupil','super_admin') AND NOT EXISTS (SELECT 1 FROM staff s WHERE s.user_id = u.id) ORDER BY u.name") as $u) {
                $phone = $u['phone'] ? Admission::normalisePhone($u['phone']) : null;
                $d->insert('staff', [
                    'staff_no' => Payroll::nextStaffNo($d), 'user_id' => $u['id'], 'name' => $u['name'], 'phone' => $phone && preg_match('/^0[2-5]\d{8}$/', $phone) ? $phone : null,
                    'position' => $positions[$u['role']] ?? 'Staff', 'category' => in_array($u['role'], ['bursar', 'admissions_officer'], true) ? 'non_teaching' : 'teaching',
                    'employment' => 'permanent', 'pay_method' => 'cash', 'basic_salary' => 0,
                ]);
                $n++;
            }
        });
        audit('wizard_payroll_records', 'staff', null, ['created' => $n]);
        return "$n pay record(s) created from staff accounts. Open each one in Payroll → Staff records to enter the salary and bank/MoMo details.";
    }
}
