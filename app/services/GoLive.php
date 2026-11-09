<?php
declare(strict_types=1);

/** Go-live readiness: automatic checks, school confirmations and the trial-run script. */
final class GoLive
{
    /** Decisions the school must confirm (ticked by the Head Teacher). */
    public const CONFIRMATIONS = [
        'grading'       => ['GES grading confirmed', 'Class score / exam weighting and grade bands in Exams → Grading setup agree with what the school uses.', 'exams/setup'],
        'fees'          => ['Fee schedule approved', 'Amounts in Fees → Fee schedule are the approved fees (not the sample figures).', 'fees/schedule'],
        'requisition'   => ['Requisition limits agreed', 'Second approval above the agreed amount, quotation above the agreed amount, and who the School Manager (Super Admin) is.', 'finance/setup'],
        'sms'           => ['SMS account and sender name', 'An SMS provider account is paid up and the sender name "NISDAS" is approved.', 'messages/settings'],
        'payments'      => ['Online payment decision', 'Paystack or Hubtel chosen and the business account opened — or decided to stay with cash, bank and direct MoMo for now.', 'fees/settings'],
        'opening'       => ['Opening balances entered', 'Cash box, bank and MoMo balances on the start date are entered in Finance → Accounts & categories.', 'finance/setup'],
        'privacy'       => ['Parents informed (data protection)', 'Parents have been given the privacy notice (what the school keeps and why — Data Protection Act, 2012, Act 843).', 'setup/privacy'],
        'dpc'           => ['Registered with the Data Protection Commission', 'The school is registered as a data controller and the number is entered in School setup → Data protection.', 'setup/privacy'],
        'ntc'           => ['Teachers licensed (NTC)', 'Every teacher\'s NTC licence number and expiry date are in Payroll → Staff records, and none has expired.', 'payroll/staff'],
        'roles'         => ['Roles reviewed (least privilege)', 'Each role in Roles & security has only the permissions that job needs, teachers see only their own classes, and the Head Teacher and Bursar use 2-step sign-in.', 'security'],
        'trained'       => ['Staff trained', 'Every staff member has signed in, changed their password and practised their own tasks (see Help).', 'help'],
        'trial_cleared' => ['Trial data removed', 'Practice records made during training are removed (fresh install) or clearly cancelled before real use.', null],
    ];

    /** Trial-run script: who, what to do, what should happen. */
    public const TRIAL = [
        'Head Teacher' => [
            'setup'      => ['Check School setup', 'Year, terms, classes (with class teachers) and subjects are correct.'],
            'admit'      => ['Admit a test student', 'Enter an application → move it to "Offered" → Enrol. An admission number is given and the parent gets an SMS.'],
            'approve'    => ['Approve a requisition', 'A teacher submits a request; you approve it. The Bursar is notified.'],
            'notice'     => ['Publish a circular', 'Post a notice to one class with "confirm read". A parent login sees it and confirms.'],
            'reports'    => ['Release a report card', 'Approve one score sheet, add remarks, release the class. The parent link opens the card.'],
            'promotion'  => ['Look at year-end promotion', 'Open Administration → Year-end promotion (do not apply during the trial).'],
        ],
        'Bursar' => [
            'bills'      => ['Generate term bills', 'Generate bills for one class. Totals match the fee schedule; siblings get the discount.'],
            'cash'       => ['Take a cash payment', 'Record cash on a bill → print the receipt → the parent gets an SMS with the new balance.'],
            'reverse'    => ['Reverse a wrong receipt', 'Ask the Head Teacher to reverse it with a reason; the balance goes back up.'],
            'cashbook'   => ['Check the cash book', 'The payment appears in Finance → Cash book in the right account; the daily cash report adds up.'],
            'expense'    => ['Record an expense', 'Enter an ECG bill with a scanned receipt; the account balance goes down.'],
            'paywreq'    => ['Pay a requisition', 'Pay the approved requisition, then close it with receipts and change.'],
        ],
        'Teachers' => [
            'register'   => ['Take the register', 'Mark today\'s attendance; absent students\' parents get an SMS.'],
            'scores'     => ['Enter scores', 'Enter class and exam scores for one subject and submit the sheet.'],
            'quiz'       => ['Set a short quiz', 'Create a 3-question quiz, publish it, take it with a student login, see the results.'],
            'timetable'  => ['Check the time table', 'Open "My time table": lessons match what you teach.'],
        ],
        'Parent (use a staff member\'s phone as a test parent)' => [
            'login'      => ['Sign in as a parent', 'Use the SMS details; choose a new password; see the child\'s attendance and fees.'],
            'pay'        => ['Open the bill link', 'The bill shows the right balance and ways to pay.'],
            'message'    => ['Write to the class teacher', 'The teacher sees and replies; the parent gets an SMS nudge.'],
        ],
        'Office / Super Admin' => [
            'backup'     => ['Make and download a backup', 'Backups → Make a backup now → Download. Copy it to a USB drive.'],
            'restore'    => ['Practise a restore (on a spare computer)', 'Restore the backup with bin\\restore.php on another PC: the data is all there.'],
            'cron'       => ['Scheduled task running', 'Go-live checklist shows the scheduled task ran in the last 30 minutes.'],
        ],
    ];

    /** @return array<int,array{group:string,label:string,ok:?bool,detail:string,link:?string}> ok null = warning */
    public static function checks(): array
    {
        $c = [];
        $add = function (string $group, string $label, ?bool $ok, string $detail, ?string $link = null) use (&$c): void {
            $c[] = compact('group', 'label', 'ok', 'detail', 'link');
        };

        // ---- Computer / server
        $add('Computer & server', 'PHP version', version_compare(PHP_VERSION, '8.0', '>='), 'PHP ' . PHP_VERSION . ' (8.0 or newer needed)');
        foreach (['pdo_mysql' => 'database', 'curl' => 'SMS and payments', 'openssl' => 'secure connections', 'mbstring' => 'Ghanaian names', 'fileinfo' => 'safe uploads', 'zip' => 'backups with documents, Excel import'] as $ext => $why) {
            $add('Computer & server', 'PHP ' . $ext, extension_loaded($ext), extension_loaded($ext) ? 'On' : "Off — needed for $why. Turn on extension=$ext in php.ini and restart Apache.");
        }
        foreach (['storage/uploads', 'storage/backups'] as $d) {
            $p = BASE_PATH . '/' . $d;
            if (!is_dir($p)) {
                @mkdir($p, 0775, true);
            }
            $add('Computer & server', $d . ' writable', is_writable($p), is_writable($p) ? 'OK' : 'The web server cannot write here.');
        }
        $add('Computer & server', 'Debug mode off', !config('debug'), config('debug') ? 'Set \'debug\' => false in config/config.local.php (errors must not be shown to visitors).' : 'OK');
        $cron = setting('cron_last_run');
        $cronOk = $cron && strtotime($cron . ' UTC') > time() - 1800;
        $add('Computer & server', 'Scheduled task (bin/cron.php)', $cronOk, $cron ? 'Last ran ' . date('d M H:i', strtotime($cron . ' UTC')) : (PHP_OS_FAMILY === 'Windows'
            ? 'Never ran — set up Windows Task Scheduler: program ' . PHP_BINARY . ', arguments ' . str_replace('/', '\\', BASE_PATH) . '\\bin\\cron.php, every 10 minutes.'
            : 'Never ran — add a cron job every 10 minutes (hosting panel → Cron Jobs): /usr/bin/php ' . BASE_PATH . '/bin/cron.php'));
        $last = db()->one('SELECT * FROM backups ORDER BY id DESC LIMIT 1');
        $add('Computer & server', 'Recent backup', $last && strtotime($last['created_at'] . ' UTC') > time() - 2 * 86400, $last ? 'Last backup ' . date('d M Y H:i', strtotime($last['created_at'] . ' UTC')) : 'No backup yet', 'backups');
        $add('Computer & server', 'Backup copy off this computer', setting('backup_copy_dir') !== '' ? true : null, setting('backup_copy_dir') !== '' ? 'Copied to ' . setting('backup_copy_dir') : 'Set a USB / Google Drive folder, or download backups regularly.', 'backups');

        // ---- Access control (least privilege)
        $add('Access control', '2-step sign-in switched on', Security::mfaOn(), Security::mfaOn() ? 'On' : 'Off — review the policy in Roles & security → Sign-in rules before go-live.', 'security/settings');
        if (Security::ready()) {
            $admins = db()->all("SELECT name, totp_enabled_at FROM users WHERE role = 'super_admin' AND is_active = 1");
            $no2fa = array_filter($admins, fn($a) => !$a['totp_enabled_at']);
            $add('Access control', 'Super Admin 2-step sign-in', !$no2fa, $no2fa ? 'Not set up yet: ' . implode(', ', array_column($no2fa, 'name')) : 'On for every Super Admin', 'account/security');
            $add('Access control', 'Number of Super Admins', count($admins) >= 1 && count($admins) <= 2 ? true : null, count($admins) . ' active (1–2 recommended: the School Manager and one deputy)', 'users');
            $highNo2fa = 0;
            foreach (db()->all("SELECT role, COUNT(*) n FROM users WHERE is_active = 1 AND totp_enabled_at IS NULL AND role IN ('head_teacher','bursar') GROUP BY role") as $r) {
                $highNo2fa += (int) $r['n'];
            }
            $add('Access control', 'Head Teacher and Bursar use 2-step sign-in', $highNo2fa === 0 ? true : null, $highNo2fa ? "$highNo2fa account(s) without it — review the sign-in policy for these roles" : 'Yes', 'security');
            $reviewed = !empty(self::ticks()['confirm:roles']['done_at']);
            $add('Access control', 'Roles reviewed', $reviewed ? true : null, $reviewed ? 'Confirmed' : 'Check each role in Roles & security, then tick "Roles reviewed" in the confirmations below', 'security');
            $add('Access control', '2-step sign-in key backed up', is_file(Crypto::keyFile()) ? (db()->value('SELECT 1 FROM backups ORDER BY id DESC LIMIT 1') ? true : null) : null, 'storage/app.key protects 2-step sign-in secrets; it is copied into every backup', 'backups');
        }

        // ---- Internet access
        $pub = (string) setting('public_url');
        $https = is_https();
        $add('Internet access', 'Public web address', $pub !== '' ? str_starts_with($pub, 'https://') : null, $pub !== '' ? $pub . (str_starts_with($pub, 'https://') ? '' : ' — should start with https://') : 'Not set: links in SMS point to this computer\'s address, which parents cannot open from home.', 'fees/settings');
        $add('Internet access', 'This page opened over HTTPS', $https ? true : null, $https ? 'Secure connection' : 'Plain http — fine inside the school network, but use https once on the internet.');
        $add('Internet access', 'Force HTTPS', config('force_https') ? true : ($pub !== '' && str_starts_with($pub, 'https://') ? false : null), config('force_https') ? 'On' : 'Off — turn on \'force_https\' in config.local.php after the SSL certificate works.');

        // ---- School data
        $year = current_year();
        $term = current_term();
        $add('School data', 'School details & logo', setting('school_phone') !== '' ? true : null, setting('school_logo') ? 'Logo uploaded' : 'Add the phone number, address and crest in School setup.', 'setup');
        $add('School data', 'Current year and term', (bool) ($year && $term), $year && $term ? $year['name'] . ' · ' . $term['name'] : 'Set them in School setup → Academic years.', 'setup/years');
        $noTeacher = (int) db()->value('SELECT COUNT(*) FROM classes WHERE is_active = 1 AND class_teacher_id IS NULL');
        $add('School data', 'Class teachers assigned', $noTeacher === 0, $noTeacher ? "$noTeacher class(es) without a class teacher" : 'All classes', 'setup/classes');
        if (Registers::ready()) {
            $lic = db()->one("SELECT COUNT(*) n, SUM(expires_on < CURDATE()) expired, SUM(name LIKE '%NaSIA%') nasia FROM school_permits");
            $add('School data', 'Licences & permits recorded', (int) $lic['n'] === 0 ? null : ((int) $lic['expired'] === 0), (int) $lic['n'] === 0
                ? 'None yet — add the NaSIA licence and other permits in School records → Licences & permits.'
                : (int) $lic['n'] . ' recorded' . ((int) $lic['nasia'] ? '' : ' (no NaSIA licence yet)') . ((int) $lic['expired'] ? ' · ' . (int) $lic['expired'] . ' expired' : ''), 'permits');
        }
        $noSubj = (int) db()->value('SELECT COUNT(*) FROM class_subjects cs JOIN classes c ON c.id = cs.class_id WHERE c.is_active = 1 AND cs.teacher_id IS NULL AND c.class_teacher_id IS NULL');
        $add('School data', 'Subject teachers allocated', $noSubj === 0 ? true : null, $noSubj ? "$noSubj class subject(s) with no teacher" : 'All subjects have a teacher', 'exams/allocations');
        $pupils = (int) db()->value("SELECT COUNT(*) FROM enrolments e JOIN pupils p ON p.id = e.pupil_id WHERE e.academic_year_id = ? AND e.status = 'active' AND p.status = 'active'", [$year['id'] ?? 0]);
        $add('School data', 'Students entered', $pupils > 0, "$pupils students enrolled this year", 'imports');
        $noPhone = (int) db()->value("SELECT COUNT(*) FROM pupils p WHERE p.status = 'active' AND p.deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM pupil_guardian pg JOIN guardians g ON g.id = pg.guardian_id WHERE pg.pupil_id = p.id AND g.phone <> '')");
        $add('School data', 'Every student has a parent phone', $noPhone === 0, $noPhone ? "$noPhone student(s) without a parent phone number" : 'Yes', 'pupils');
        $staff = (int) db()->value("SELECT COUNT(*) FROM users WHERE is_active = 1 AND role NOT IN ('parent','pupil','super_admin')");
        $add('School data', 'Staff accounts', $staff > 0, "$staff staff accounts", 'users');
        $pending = (int) db()->value("SELECT COUNT(*) FROM users WHERE is_active = 1 AND role NOT IN ('parent','pupil') AND (must_change_password = 1 OR last_login_at IS NULL)");
        $add('School data', 'Staff have signed in', $pending === 0 ? true : null, $pending ? "$pending staff member(s) have not signed in / changed their temporary password yet" : 'All', 'users');
        $ntc = Payroll::ntcProblems();
        $teachers = (int) db()->value("SELECT COUNT(*) FROM staff WHERE is_active = 1 AND category = 'teaching'");
        $add('School data', 'Teachers\' NTC licences', $teachers === 0 ? null : $ntc === 0, $teachers === 0 ? 'No teaching staff records yet (Payroll → Staff records)' : ($ntc ? "$ntc teacher(s) with a missing, expired or soon-expiring licence" : 'All recorded and valid'), 'payroll/staff');
        $add('School data', 'Data Protection Commission registration', setting('dpc_registration_no') !== '' ? true : null, setting('dpc_registration_no') !== '' ? 'No. ' . setting('dpc_registration_no') : 'Not entered — register with the DPC and add the number', 'setup/privacy');
        $tt = (int) db()->value('SELECT COUNT(*) FROM timetable_slots');
        $add('School data', 'Time table', $tt > 0 ? true : null, $tt ? "$tt lessons placed" : 'Not generated yet', 'timetable');

        // ---- Money & messages
        $add('Fees, money & SMS', 'Fee schedule confirmed', setting('fee_schedule_confirmed') === '1', setting('fee_schedule_confirmed') === '1' ? 'Marked as the approved amounts' : 'Still the sample figures', 'fees/schedule');
        $prov = (string) setting('sms_provider', 'log');
        $add('Fees, money & SMS', 'SMS provider', $prov !== 'log', $prov === 'log' ? 'Test mode — no SMS leaves the computer' : ucfirst($prov) . ', sender ' . setting('sms_sender_id'), 'messages/settings');
        $gw = PaymentGateway::active();
        $add('Fees, money & SMS', 'Online payment', $gw === 'test' ? false : ($gw === 'none' ? null : true), $gw === 'test' ? 'Still in Test mode — switch to Paystack/Hubtel or Off before real use' : ($gw === 'none' ? 'Off (cash, bank and direct MoMo only)' : ucfirst($gw)), 'fees/settings');
        $noOpening = (int) db()->value('SELECT COUNT(*) FROM fin_accounts WHERE is_active = 1 AND opening_date IS NULL');
        $add('Fees, money & SMS', 'Account opening balances', $noOpening === 0 ? true : null, $noOpening ? "$noOpening account(s) without an opening date/balance" : 'Entered', 'finance/setup');
        return $c;
    }

    public static function ticks(): array
    {
        $out = [];
        foreach (db()->all('SELECT g.*, u.name FROM golive_checks g LEFT JOIN users u ON u.id = g.done_by') as $r) {
            $out[$r['check_key']] = $r;
        }
        return $out;
    }

    public static function allTrialKeys(): array
    {
        $k = [];
        foreach (self::TRIAL as $items) {
            foreach (array_keys($items) as $key) {
                $k[] = 'trial:' . $key;
            }
        }
        return $k;
    }
}
