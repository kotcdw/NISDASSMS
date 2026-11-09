<?php
declare(strict_types=1);

/**
 * Modules on/off (Administration → App settings → Modules & features, Administrator and Super Admin only).
 * A module that is switched off disappears for everyone: menus, NISDAS ENGINE tiles, the module cards,
 * dashboard widgets, top-bar alerts, links on every page and the parent portal; its pages answer
 * "This module is switched off" and its automatic reminders stop. Nothing is deleted: switching it on
 * again brings everything back as it was.
 * Core parts are always on: Dashboard, Students, parent/student logins and portal, Administration, account pages.
 */
final class ModuleSwitch
{
    /** Setting that holds the switched-off module keys, comma separated. */
    private const SETTING = 'modules_off';

    /**
     * key => [label, menu group, what it covers, [route prefixes], [permission keys or prefixes ending in "."]]
     * A page belongs to the module with the longest matching route prefix (Staff attendance "attendance/staff"
     * is separate from Student attendance "attendance").
     */
    public const MODULES = [
        // Admissions & Students
        'admission'        => ['Admission', 'Admissions & Students', 'Applications, the online application form, offers and enrolment', ['admission', 'apply'], ['admission.']],
        'crm'              => ['CRM — enquiries', 'Admissions & Students', 'Enquiries from parents and the public enquiry form', ['crm', 'enquire'], ['crm.']],
        'marketing'        => ['Marketing campaigns', 'Admissions & Students', 'SMS / WhatsApp campaigns and the do-not-message list', ['marketing'], ['marketing.']],
        'attendance'       => ['Student attendance', 'Admissions & Students', 'Daily class registers, absence SMS and attendance reports', ['attendance'], ['attendance.']],
        'leave'            => ['Student leave', 'Admissions & Students', 'Leave requests from parents and approvals', ['leave'], ['leave.']],
        'welfare'          => ['Behaviour & welfare', 'Admissions & Students', 'Behaviour records, welfare cases and safeguarding', ['welfare'], ['welfare.']],
        'health'           => ['Health & sick bay', 'Admissions & Students', 'Sick bay visits and health records', ['health'], ['health.']],
        // Teaching & Learning
        'assignments'      => ['Assignments & homework', 'Teaching & Learning', 'Homework set by teachers and handed in by students', ['assignments', 'homework'], ['assignments.']],
        'lms'              => ['LMS — learning materials', 'Teaching & Learning', 'Notes, videos and files for students', ['lms'], ['lms.']],
        'live'             => ['Live classes', 'Teaching & Learning', 'Meeting links for Zoom, Google Meet and Teams', ['live'], ['live.']],
        'quizzes'          => ['Quizzes', 'Teaching & Learning', 'Quizzes set by teachers and taken by students', ['quizzes', 'learn'], ['quiz.']],
        'curriculum'       => ['Subject Management', 'Teaching & Learning', 'NaCCA strands, scheme of work and weekly lesson notes', ['curriculum', 'lesson-notes'], ['scheme.', 'curriculum.', 'lessonnotes.']],
        'teachers'         => ['Faculty (teachers & appraisal)', 'Teaching & Learning', 'Teacher profiles, NTC licences and termly appraisals', ['teachers'], ['teachers.', 'appraisal.']],
        // Exams & Results
        'exams'            => ['Exams & report cards', 'Exams & Results', 'Score sheets, approval, broadsheets and report cards', ['exams', 'reports', 'r'], ['exams.', 'reports.']],
        'exam_timetable'   => ['Exam timetable', 'Exams & Results', 'Exam papers, halls and invigilators', ['exam-timetable'], []],
        'seating'          => ['Seating plans', 'Exams & Results', 'Seating plans for classes and exam halls', ['seating'], ['seating.']],
        'bece'             => ['BECE', 'Exams & Results', 'BECE registration and results for JHS 3', ['bece'], ['bece.']],
        // Timetable & Calendar
        'timetable'        => ['Time table', 'Timetable & Calendar', 'Class and teacher timetables', ['timetable'], ['timetable.']],
        'calendar'         => ['Academic calendar', 'Timetable & Calendar', 'Term dates, holidays and event reminders', ['calendar'], ['calendar.']],
        'events'           => ['Events', 'Timetable & Calendar', 'School events with registration and fees', ['events'], ['events.']],
        'appointments'     => ['Appointments', 'Timetable & Calendar', 'Parents booking time with teachers and the office', ['appointments'], ['appointments.']],
        // Finance
        'fees'             => ['Fees', 'Finance', 'Bills, receipts, online payment and fee reminders (also used by Canteen, Events and the School shop)', ['fees', 'pay'], ['fees.']],
        'finance'          => ['Finance (cash book)', 'Finance', 'Cash book, budgets, ledger, purchasing, suppliers, petty cash and fixed assets', ['finance', 'ledger', 'fixed-assets', 'petty-cash', 'purchasing', 'suppliers'], ['finance.', 'ledger.', 'purchasing.', 'budget.']],
        'requisitions'     => ['Requisitions', 'Finance', 'Requests to spend money and their approval', ['requisitions'], ['requisitions.']],
        'payroll'          => ['Payroll', 'Finance', 'Monthly payroll, SSNIT, PAYE and payslips', ['payroll', 'my/payslips'], ['payroll.', 'payslips.']],
        'shop'             => ['School shop', 'Finance', 'Uniforms, books and other items ordered by parents', ['shop'], ['shop.']],
        // School services
        'resources'        => ['Rooms & resources', 'School services', 'Classrooms and what is in them', ['resources'], ['resources.']],
        'documents'        => ['Documents', 'School services', 'School documents and student / staff files', ['documents'], ['documents.']],
        'library'          => ['Library', 'School services', 'Books, loans and returns', ['library'], ['library.']],
        'transport'        => ['Transport', 'School services', 'Bus routes, drivers and bus fees', ['transport'], ['transport.']],
        'canteen'          => ['Canteen', 'School services', 'Meal plans, prepaid wallets and canteen sales', ['canteen'], ['canteen.']],
        // Communication
        'notices'          => ['Notice board', 'Communication', 'Notices and circulars, including those on the sign-in page', ['notices', 'login/notices'], ['notices.']],
        'conversations'    => ['Parent messages', 'Communication', 'Conversations between parents and the school', ['conversations'], ['chat.']],
        'messages'         => ['Notifications & SMS', 'Communication', 'Sending SMS to groups, the outbox, templates and SMS settings (automatic SMS from other modules still go out)', ['messages'], ['messages.']],
        'forms'            => ['Form builder', 'Communication', 'Forms and surveys for parents and staff', ['forms', 'f'], ['forms.']],
        // Staff & HR
        'staff_attendance' => ['Staff attendance', 'Staff & HR', 'Staff sign-in / sign-out and the staff attendance report', ['attendance/staff'], ['staff_att.']],
        'timesheets'       => ['Staff timesheets', 'Staff & HR', 'Hours worked and shifts', ['timesheets'], ['timesheets.']],
        'staff_leave'      => ['Staff leave', 'Staff & HR', 'Leave requests from staff and their approval by the Head', ['staff-leave'], ['staffleave.']],
        'cpd'              => ['Staff training (CPD)', 'Staff & HR', 'Workshops, INSET and courses attended by staff, with certificates', ['cpd'], ['cpd.']],
        'movements'        => ['Staff movement book', 'Staff & HR', 'Staff leaving and returning to school during the day', ['movements'], ['movement.']],
        // School records
        'logbook'          => ["Head's log book", 'School records', "The Head teacher's record of important events, visits and inspections", ['logbook'], ['logbook.']],
        'visitors'         => ["Visitors' book", 'School records', 'Visitors signed in and out at the office', ['visitors'], ['visitors.']],
        'pickup'           => ['Gate pick-up', 'School records', 'Who collects each child: approved people, parent approval by SMS, ID, photo and signature', ['gate', 'pickup', 'p'], ['pickup.']],
        'stores'           => ['Store ledger', 'School records', 'Store items received and issued, with ledger cards', ['stores'], ['stores.']],
        'permits'          => ['Licences & permits', 'School records', 'NaSIA licence, fire and health permits with expiry reminders', ['permits'], ['permits.']],
        'governance'       => ['Governance & risk', 'Administration', 'Board members and meetings, policies and the risk register', ['governance'], ['governance.']],
    ];

    /** Switched-off module keys. */
    public static function off(): array
    {
        static $cache = [];
        $raw = setting(self::SETTING, '');
        return $cache[$raw] ??= array_values(array_intersect(array_filter(explode(',', $raw)), array_keys(self::MODULES)));
    }

    public static function isOn(string $key): bool
    {
        return !in_array($key, self::off(), true);
    }

    /** Save which modules are switched off. */
    public static function save(array $offKeys): void
    {
        $offKeys = array_values(array_intersect(array_keys(self::MODULES), $offKeys));
        setting_set(self::SETTING, implode(',', $offKeys));
    }

    /** The module a page belongs to (longest matching route prefix), or null for core pages. */
    public static function moduleOf(string $route): ?string
    {
        $route = trim($route, '/');
        $best = null;
        $len = -1;
        foreach (self::MODULES as $k => $m) {
            foreach ($m[3] as $p) {
                if (($route === $p || str_starts_with($route, $p . '/')) && strlen($p) > $len) {
                    $best = $k;
                    $len = strlen($p);
                }
            }
        }
        return $best;
    }

    /** Switched-off module that owns this page, or null when the page may be used. */
    public static function blocked(string $route): ?string
    {
        if (!self::off()) {
            return null;
        }
        $m = self::moduleOf($route);
        return $m !== null && !self::isOn($m) ? $m : null;
    }

    /** True when every permission given belongs to a switched-off module (used for dashboard widgets). */
    public static function permsOff(array $perms): bool
    {
        if (!self::off() || !$perms) {
            return false;
        }
        foreach ($perms as $perm) {
            $owner = null;
            foreach (self::MODULES as $k => $m) {
                foreach ($m[4] as $p) {
                    if ($perm === $p || (str_ends_with($p, '.') && str_starts_with($perm, $p))) {
                        $owner = $k;
                        break 2;
                    }
                }
            }
            if ($owner === null || self::isOn($owner)) {
                return false;
            }
        }
        return true;
    }

    /** Modules grouped for the on/off page: group => [key => module]. */
    public static function grouped(): array
    {
        $out = [];
        foreach (self::MODULES as $k => $m) {
            $out[$m[1]][$k] = $m;
        }
        return $out;
    }

    public static function label(string $key): string
    {
        return self::MODULES[$key][0] ?? $key;
    }
}
