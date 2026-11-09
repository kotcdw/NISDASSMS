<?php
declare(strict_types=1);

/**
 * Catalogue of every permission: which module it belongs to, what it allows, how risky it is,
 * and the safety rules the Roles & permissions screen enforces (least privilege, separation of duties).
 */
final class Permissions
{
    /** Module => permissions, in the order they are shown. [key => [label, what it allows, risk: low|medium|high]] */
    public const CATALOG = [
        'Basic access' => [
            'staff'            => ['Staff area', 'Sign in to the staff area and see the staff dashboard.', 'low'],
            'payslips.own'     => ['Own payslips', 'Read their own payslips.', 'low'],
            'calendar.view'    => ['See the calendar', 'Read the academic calendar.', 'low'],
            'notices.view'     => ['Read notices', 'Read circulars and notices meant for them.', 'low'],
        ],
        'Administration' => [
            'setup.manage'     => ['School setup', 'Change school details, years and terms, classes, subjects and data-protection settings.', 'high'],
            'app.configure'    => ['Configure apps', 'Change application, module and feature settings.', 'high'],
            'users.manage'     => ['Users', 'Create staff accounts, change roles, reset passwords.', 'high'],
            'teachers.profile.manage' => ['Teacher records', 'Create, update and archive non-payroll teacher profile details.', 'medium'],
            'security.manage'  => ['Roles & security', 'Change roles, permissions, sign-in rules and see the access review.', 'high'],
            'backups.manage'   => ['Backups', 'Make, download and restore full backups (all records).', 'high'],
            'audit.view'       => ['Audit log', 'Read the audit log of who did what.', 'medium'],
            'imports.manage'   => ['Import from Excel', 'Open Import from Excel (each import also needs its own permission).', 'medium'],
            'golive.manage'    => ['Go-live checklist', 'Tick the go-live confirmations and trial run.', 'medium'],
            'promotion.manage' => ['Year-end promotion', 'Promote or repeat whole classes at year end.', 'high'],
            'logins.manage'    => ['Parent & student logins', 'Create and reset parent and student sign-ins.', 'high'],
        ],
        'Admissions & students' => [
            'admission.view'   => ['See applications', 'Read admission applications.', 'medium'],
            'admission.manage' => ['Handle applications', 'Record applications and move them through the stages.', 'medium'],
            'admission.enrol'  => ['Enrol students', 'Turn an accepted application into a student with an admission number.', 'high'],
            'pupils.view'      => ['See students', 'Open student records (teachers limited to their own classes when the role\'s data scope is "own classes").', 'medium'],
            'pupils.sensitive' => ['Health & special needs', 'See medical notes, special educational needs and the health record link.', 'high'],
            'pupils.manage'    => ['Edit students', 'Edit student records, parents and status (withdrawn, transferred…).', 'high'],
        ],
        'Attendance' => [
            'attendance.take'  => ['Take registers', 'Mark attendance for their class.', 'low'],
            'attendance.view'  => ['Attendance reports', 'See attendance reports.', 'low'],
            'attendance.all'   => ['All classes', 'Take and see registers for every class, not only their own.', 'medium'],
            'staff_att.view'   => ['Staff attendance', 'See staff sign-in times.', 'medium'],
            'timesheets.manage' => ['Staff timesheets', 'Correct staff sign-in times, set shifts and approve monthly timesheets.', 'high'],
            'leave.approve'    => ['Student leave', 'Approve leave asked for by parents (class teachers: their own class).', 'low'],
        ],
        'Exams & reports' => [
            'exams.enter'      => ['Enter scores', 'Enter scores for subjects they teach.', 'low'],
            'exams.manage'     => ['Manage exams', 'Approve score sheets, grading setup, subject allocation, all classes.', 'high'],
            'reports.view'     => ['Report cards', 'See report cards and broadsheets (own class unless they manage exams).', 'medium'],
            'reports.head'     => ['Head\'s remarks', 'Write the head teacher\'s remarks.', 'medium'],
            'bece.view'        => ['See BECE', 'Read BECE candidates and results.', 'medium'],
            'bece.manage'      => ['Manage BECE', 'Register candidates and enter BECE results.', 'medium'],
            'analytics.view'   => ['School reports', 'Whole-school figures: enrolment, census, attendance, results, fees.', 'medium'],
            'examtt.manage'    => ['Exam timetable', 'Plan exam papers, rooms, invigilators and exam seating, and publish the exam timetable.', 'medium'],
        ],
        'Teachers' => [
            'teachers.view'    => ['Teacher profiles', 'See every teacher\'s profile: details, subjects, time table, attendance, class results and appraisals (everyone sees their own).', 'high'],
            'appraisal.manage' => ['Appraise teachers', 'Open termly appraisals, rate teachers and edit the appraisal criteria.', 'high'],
        ],
        'Curriculum & classrooms' => [
            'scheme.plan'      => ['Scheme of work', 'Plan weekly topics and tick them off for the subjects they teach.', 'low'],
            'curriculum.manage'=> ['Curriculum', 'Edit strands and sub-strands, plan or check the scheme of work for every class, and keep the textbooks & materials list.', 'medium'],
            'lessonnotes.vet'  => ['Vet lesson notes', 'Read every teacher\'s weekly lesson notes and approve them or return them with a comment.', 'medium'],
            'seating.manage'   => ['Seating plans', 'Arrange seating for their own class (every class for roles that see all classes).', 'low'],
            'resources.view'   => ['See rooms & resources', 'See rooms, furniture, textbooks, equipment and the shortages report.', 'low'],
            'resources.manage' => ['Manage rooms & resources', 'Add rooms and record items, quantities and condition.', 'medium'],
        ],
        'Time table & calendar' => [
            'timetable.view'   => ['See time table', 'Read class and teacher time tables.', 'low'],
            'timetable.manage' => ['Build time table', 'Change periods, lessons and the time table.', 'medium'],
            'calendar.manage'  => ['Edit calendar', 'Add and change calendar events.', 'medium'],
        ],
        'Fees & finance' => [
            'fees.view'        => ['See fees', 'See bills, balances and payments.', 'medium'],
            'fees.manage'      => ['Bill & receive fees', 'Generate bills, record payments, fee schedule and settings.', 'high'],
            'fees.reverse'     => ['Reverse receipts', 'Reverse a receipt that was recorded wrongly.', 'high'],
            'finance.view'     => ['See finance', 'Read the cash book, accounts and statements.', 'medium'],
            'finance.manage'   => ['Record finance', 'Record income, expenses and transfers.', 'high'],
            'finance.void'     => ['Void transactions', 'Cancel a recorded transaction.', 'high'],
            'finance.budget'   => ['Budgets', 'Set budgets.', 'medium'],
            'fees.approve'     => ['Approve credit notes & refunds', 'Approve or reject credit notes on bills and fee refunds to parents.', 'high'],
            'finance.close'    => ['Close a month', 'Lock a finished month so its money records can no longer change.', 'high'],
            'finance.reopen'   => ['Reopen a closed month', 'Unlock a closed month (with a reason) to correct it.', 'high'],
            'assets.manage'    => ['Fixed assets', 'Add, move and dispose of furniture, computers, vehicles and other school assets.', 'medium'],
            'ledger.journal'   => ['Manual journals', 'Type a correction into the books (it counts only after it is approved).', 'high'],
            'ledger.approve'   => ['Approve manual journals', 'Approve or reject corrections typed into the books by someone else.', 'high'],
            'budget.caps'      => ['Department budget caps', 'Set each department\'s spending cap for a term, raise a cap or move money between departments.', 'high'],
        ],
        'Requisitions' => [
            'requisitions.create'   => ['Request', 'Raise their own requisitions.', 'low'],
            'requisitions.view_all' => ['See all', 'See everyone\'s requisitions.', 'medium'],
            'requisitions.approve'  => ['Approve', 'First approval of requisitions.', 'high'],
            'requisitions.approve2' => ['Second approval', 'Second approval above the limit (School Manager).', 'high'],
            'requisitions.pay'      => ['Pay out', 'Pay approved requisitions from the cash book.', 'high'],
        ],
        'Purchasing' => [
            'purchasing.order'   => ['Purchase orders', 'Turn approved requisitions into purchase orders, send them to suppliers and record the supplier\'s invoice.', 'high'],
            'purchasing.receive' => ['Sign for goods', 'Record goods delivered against a purchase order (goods received note).', 'medium'],
            'purchasing.approve' => ['Approve big payments', 'Approve supplier bills above the payment limit before they can be paid.', 'high'],
        ],
        'Payroll' => [
            'payroll.view'     => ['See payroll', 'Read payroll runs and staff pay.', 'high'],
            'payroll.manage'   => ['Prepare & pay', 'Staff records, prepare payroll and pay it.', 'high'],
            'payroll.approve'  => ['Approve payroll', 'Approve a prepared payroll.', 'high'],
        ],
        'Communication' => [
            'messages.send'    => ['Send SMS', 'Send SMS to parents and staff.', 'medium'],
            'messages.manage'  => ['SMS settings', 'SMS provider, templates and outbox.', 'high'],
            'notices.manage'   => ['Publish notices', 'Publish circulars and notices.', 'medium'],
            'chat.use'         => ['Messages', 'Write and reply to parent ↔ school conversations.', 'low'],
            'chat.oversee'     => ['Oversee messages', 'Read every conversation (safeguarding).', 'high'],
            'forms.manage'     => ['Forms', 'Build forms and read the replies.', 'medium'],
        ],
        'Learning' => [
            'quiz.manage'      => ['Set quizzes', 'Create quizzes and see results.', 'low'],
            'quiz.take'        => ['Take quizzes', 'Take quizzes (students).', 'low'],
            'assignments.manage' => ['Set assignments', 'Set homework for their classes, mark and return it.', 'low'],
            'assignments.submit' => ['Hand in homework', 'See and hand in homework (students).', 'low'],
            'portal.parent'    => ['Parent portal', 'See their own children\'s attendance, results, fees and notices (parents).', 'low'],
        ],
        'Library, transport & health' => [
            'library.view'     => ['See library', 'Search books and loans.', 'low'],
            'library.manage'   => ['Run library', 'Add books, issue and return them.', 'low'],
            'transport.view'   => ['See transport', 'See bus routes and riders.', 'low'],
            'transport.manage' => ['Manage transport', 'Routes, riders and bus fees.', 'medium'],
            'health.view'      => ['See sick bay', 'Read health records and sick-bay visits.', 'high'],
            'health.manage'    => ['Sick bay', 'Record visits and health details.', 'high'],
            'welfare.record'   => ['Behaviour & welfare', 'Record merits and concerns (positive discipline), report accidents and welfare or safeguarding concerns, follow up cases given to them.', 'medium'],
            'welfare.manage'   => ['Run student welfare', 'See and assign every welfare case except safeguarding, close cases.', 'high'],
            'welfare.safeguarding' => ['Safeguarding lead', 'See and handle safeguarding (child-protection) cases. Give this only to the designated safeguarding lead.', 'high'],
            'canteen.sell'     => ['Canteen counter', 'Sell at the canteen, top up student wallets, mark meals served.', 'medium'],
            'canteen.manage'   => ['Run the canteen', 'Items, prices, stock, menu, meal plans and their bills, cancel sales, reports.', 'medium'],
        ],
        'School records' => [
            'logbook.manage'   => ["Head's log book", "Write and read the Head teacher's log book (important events, visits, inspections, incidents).", 'high'],
            'visitors.record'  => ["Visitors' book", 'Sign visitors in and out at the office and read the visitors\' book.', 'medium'],
            'pickup.gate'      => ['Gate pick-up', 'At the gate: find a child, check who is collecting them, record ID, photo and signature, and release the child once the parent approves.', 'medium'],
            'pickup.manage'    => ['Approve pick-ups (office)', 'Approve a pick-up after phoning the parent, add people a parent has allowed, and read the full pick-up register.', 'high'],
            'movement.view'    => ['Staff movement book', 'See when every staff member left and returned during school hours.', 'medium'],
            'staffleave.approve' => ['Approve staff leave', 'Approve or decline staff leave requests and see every staff member\'s leave.', 'high'],
            'cpd.view'         => ['Staff training (CPD)', 'See and record training (CPD) for every staff member.', 'medium'],
            'stores.view'      => ['See store ledger', 'Read the store ledger: stock, items received and issued.', 'low'],
            'stores.manage'    => ['Run the store', 'Add store items, receive and issue stock, correct balances.', 'medium'],
            'permits.view'     => ['See licences & permits', 'Read the school\'s licences and permits register (NaSIA, fire, health…).', 'low'],
            'permits.manage'   => ['Manage licences & permits', 'Add and renew licences and permits and keep their documents.', 'medium'],
        ],
        'Events & appointments' => [
            'events.manage'    => ['Manage events', 'Create events, see registrations, check people in.', 'low'],
            'appointments.offer' => ['Offer appointments', 'Publish times parents can book and manage those meetings.', 'low'],
            'appointments.manage' => ['Appointment types', 'Set the kinds of appointment parents can book.', 'low'],
            'appointments.book' => ['Book appointments', 'Book a meeting with staff (parents).', 'low'],
        ],
        'Documents' => [
            'documents.view'   => ['School documents', 'Read the school document library (staff documents).', 'low'],
            'documents.manage' => ['Manage documents', 'Upload and archive school documents, see management-only ones, keep student files.', 'medium'],
            'documents.staff'  => ['Staff files', 'Keep staff files (certificates, contracts, licences).', 'high'],
        ],
        'Online learning' => [
            'lms.manage'       => ['Learning materials', 'Publish lesson notes, videos and links for their classes.', 'low'],
            'lms.view'         => ['Read materials', 'Read learning materials for their class (students).', 'low'],
            'live.manage'      => ['Live classes', 'Schedule Zoom, Google Meet or Teams classes and see who joined.', 'low'],
            'live.join'        => ['Join live classes', 'Join live classes for their class (students).', 'low'],
        ],
        'Governance & risk' => [
            'governance.view'   => ['Governance (read)', 'See the Board view, board meetings, the policy register and the risk register.', 'medium'],
            'governance.manage' => ['Run governance', 'Record board members, meetings and attendance, policies and their reviews, and risks.', 'high'],
        ],
        'Enquiries, marketing & shop' => [
            'crm.manage'       => ['Enquiries (CRM)', 'Record enquiries from parents and follow them up until they apply.', 'medium'],
            'marketing.send'   => ['Marketing campaigns', 'Send SMS / WhatsApp campaigns to enquirers and past families.', 'medium'],
            'shop.manage'      => ['Run the school shop', 'Products, prices, stock, orders, collection and refunds.', 'medium'],
            'shop.order'       => ['Order from the shop', 'Order uniforms, books and stationery and pay online (parents).', 'low'],
        ],
    ];

    /** Only the Super Admin may hold these (never grantable to another role). */
    public const LOCKED = ['requisitions.approve2'];

    /** The Administration apps: only the Super Admin and the Administrator role may hold these. */
    public const ADMIN_ROLE = 'administrator';
    public const ADMIN_ONLY = ['app.configure', 'modules.switch', 'setup.manage', 'users.manage', 'teachers.profile.manage', 'security.manage', 'logins.manage', 'backups.manage', 'audit.view', 'governance.view', 'governance.manage'];

    /** Every staff role must have these; portal roles (parents, pupils) never. */
    public const STAFF_REQUIRED = ['staff'];

    /** The only permissions a parent-type or pupil-type role may hold. */
    public const PORTAL_ALLOWED = [
        'parent' => ['portal.parent', 'chat.use', 'notices.view', 'calendar.view', 'appointments.book', 'shop.order', 'transport.view'],
        'pupil'  => ['quiz.take', 'assignments.submit', 'notices.view', 'calendar.view', 'lms.view', 'live.join', 'chat.use', 'transport.view'],
    ];

    /** Portal-only permissions a staff role may not hold. */
    public const PORTAL_ONLY = ['portal.parent', 'quiz.take', 'assignments.submit', 'appointments.book', 'shop.order', 'lms.view', 'live.join'];

    /** Separation of duties: one role must not hold both (blocked). */
    public const CONFLICTS = [
        ['requisitions.approve', 'requisitions.pay', 'The person who approves a requisition must not also pay it out.'],
        ['payroll.manage', 'payroll.approve', 'The person who prepares payroll must not also approve it.'],
    ];

    /** Combinations allowed but flagged in the access review. */
    public const WARNINGS = [
        ['fees.manage', 'fees.reverse', 'Can record and also reverse fee receipts.'],
        ['fees.manage', 'fees.approve', 'Can ask for and approve credit notes & refunds (the system still stops anyone approving their own).'],
        ['finance.manage', 'finance.void', 'Can record and also cancel cash-book entries.'],
        ['purchasing.order', 'purchasing.receive', 'Can order goods and also sign for them (the system still stops anyone signing for an order they made).'],
        ['ledger.journal', 'ledger.approve', 'Can type and approve journals (the system still stops anyone approving their own).'],
        ['admission.manage', 'admission.enrol', 'Can both move applications and enrol students.'],
    ];

    /** Flat list: permission => [module, label, description, risk]. */
    public static function all(): array
    {
        static $flat = null;
        if ($flat === null) {
            $flat = [];
            foreach (self::CATALOG as $module => $perms) {
                foreach ($perms as $k => [$label, $desc, $risk]) {
                    $flat[$k] = [$module, $label, $desc, $risk];
                }
            }
        }
        return $flat;
    }

    public static function label(string $perm): string
    {
        $p = self::all()[$perm] ?? null;
        return $p ? $p[0] . ' → ' . $p[1] : $perm;
    }

    /**
     * Can this permission be ticked for a role of this kind? Returns null when allowed, or the reason it is locked.
     * $kind: 'staff', 'parent' or 'pupil'.
     */
    public static function lockReason(string $perm, string $kind, string $roleKey = ''): ?string
    {
        if (in_array($perm, self::LOCKED, true)) {
            return 'Super Admin only';
        }
        if (in_array($perm, self::ADMIN_ONLY, true) && $roleKey !== self::ADMIN_ROLE) {
            return 'Super Admin and Administrator only';
        }
        if ($kind !== 'staff') {
            return in_array($perm, self::PORTAL_ALLOWED[$kind] ?? [], true) ? null : 'Not for ' . ($kind === 'parent' ? 'parent' : 'pupil') . ' accounts';
        }
        if (in_array($perm, self::PORTAL_ONLY, true)) {
            return in_array($perm, ['quiz.take', 'assignments.submit'], true) ? 'Students only' : 'Parents only';
        }
        return null;
    }

    /**
     * Check a role's permission set. Returns [clean list, errors]. Required permissions are added,
     * locked/unknown ones removed, separation-of-duty conflicts reported as errors.
     */
    public static function sanitise(array $perms, string $kind, string $roleKey = ''): array
    {
        $all = self::all();
        $clean = [];
        foreach (array_unique(array_map('strval', $perms)) as $p) {
            if (isset($all[$p]) && self::lockReason($p, $kind, $roleKey) === null) {
                $clean[] = $p;
            }
        }
        if ($kind === 'staff') {
            $clean = array_values(array_unique(array_merge($clean, self::STAFF_REQUIRED)));
        }
        $errors = [];
        foreach (self::CONFLICTS as [$a, $b, $why]) {
            if (in_array($a, $clean, true) && in_array($b, $clean, true)) {
                $errors[] = self::label($a) . ' + ' . self::label($b) . ': ' . $why;
            }
        }
        sort($clean);
        return [$clean, $errors];
    }

    /** Warnings (allowed but worth reviewing) for a permission set. */
    public static function warnings(array $perms): array
    {
        $out = [];
        foreach (self::WARNINGS as [$a, $b, $why]) {
            if (in_array($a, $perms, true) && in_array($b, $perms, true)) {
                $out[] = $why;
            }
        }
        return $out;
    }
}
