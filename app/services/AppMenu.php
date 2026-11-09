<?php
declare(strict_types=1);

/**
 * Left panel for each app group (same style as the Finance panel): group => [module => [section => [route, label, description, permission]]].
 * Generated from the route list (permissions match app/routes.php). Finance uses FinanceMenu. Items the person cannot open are left out.
 */
final class AppMenu
{
    public const APPS = [
        'Admissions & Students' => [
            'Admission' => [
                '' => [
                    ['admission', 'Applications', 'Review applications and move them through the stages', 'admission.view'],
                    ['admission/create', 'New application', 'Enter an application taken at the office', 'admission.manage'],
                    ['admission/merit', 'Merit list', 'Entrance scores and offering places', 'admission.view'],
                ],
                'Enquiries' => [
                    ['crm', 'Enquiries', 'Family enquiries and follow-ups', 'crm.manage'],
                    ['marketing', 'Campaigns', 'SMS and email campaigns to families', 'marketing.send'],
                    ['marketing/optouts', 'Do not message', 'Numbers that asked not to be contacted', 'marketing.send'],
                ],
            ],
            'Students' => [
                '' => [
                    ['pupils', 'Student list', 'Find a student; records, guardians and documents', 'pupils.view'],
                    ['pupils/class-list', 'Class lists', 'Print a list of each class', 'pupils.view'],
                    ['pupils/id-cards', 'ID cards', 'Print student identity cards', 'pupils.view'],
                    ['promotion', 'Promotion', 'Move classes up at the end of the year', 'promotion.manage'],
                    ['imports', 'Import from Excel', 'Bring in many students at once', 'imports.manage'],
                ],
            ],
            'Attendance' => [
                '' => [
                    ['attendance', 'Class registers', 'Mark and review daily attendance', 'attendance.view'],
                    ['attendance/absentees', 'Absentees', 'Who is absent today and how often', 'attendance.view'],
                    ['leave', 'Student leave', 'Leave requests from parents', 'auth'],
                ],
            ],
            'Welfare & health' => [
                'Welfare' => [
                    ['welfare', 'Overview', 'Merits, concerns and open cases', 'welfare.record'],
                    ['welfare/behaviour/new', 'Record behaviour', 'A merit or a concern for a student', 'welfare.record'],
                    ['welfare/behaviour', 'Behaviour records', 'Every merit and concern recorded', 'welfare.record'],
                    ['welfare/cases', 'Welfare cases', 'Counselling, incidents and safeguarding', 'welfare.record'],
                ],
                'Health' => [
                    ['health', 'Sick bay', 'Sick-bay visits and health alerts', 'health.view'],
                    ['health/report', 'Class health register', 'Health details class by class', 'health.view'],
                ],
            ],
            'Teaching & learning' => [
                '' => [
                    ['assignments', 'Assignments', 'Set class work and mark submissions', 'assignments.manage'],
                    ['lms', 'Learning materials', 'Notes, videos and worksheets for classes', 'auth'],
                    ['live', 'Live classes', 'Online lessons and joining details', 'auth'],
                    ['quizzes', 'Quizzes', 'Create and run online class quizzes', 'quiz.manage'],
                ],
            ],
        ],
        'Subject Management' => [
            'Planning' => [
                '' => [
                    ['curriculum', 'Scheme of work', 'Topics planned week by week', 'scheme.plan'],
                    ['lesson-notes', 'Lesson notes', 'Weekly notes and the head\'s vetting', 'scheme.plan'],
                    ['curriculum/performance', 'Performance', 'How each subject is doing', 'scheme.plan'],
                ],
            ],
            'Resources' => [
                '' => [
                    ['curriculum/materials', 'Textbooks & materials', 'Books and materials for each subject', 'scheme.plan'],
                    ['curriculum/strands', 'Curriculum strands', 'NaCCA strands and sub-strands', 'scheme.plan'],
                ],
            ],
            'Subjects' => [
                '' => [
                    ['setup/subjects', 'Subjects', 'The subjects taught in each class', 'app.configure'],
                    ['exams/allocations', 'Subject teachers', 'Who teaches each subject', 'exams.manage'],
                ],
            ],
        ],
        'Exams & Results' => [
            'Score sheets' => [
                '' => [
                    ['exams', 'Score sheets', 'Enter, check and approve exam scores', 'exams.enter'],
                    ['exams/setup', 'Grading setup', 'Grades, proficiency levels and weights', 'app.configure'],
                ],
            ],
            'Report cards' => [
                '' => [
                    ['reports', 'Report cards', 'Print and release term reports', 'reports.view'],
                ],
            ],
            'Exam timetable' => [
                '' => [
                    ['exam-timetable', 'Exam timetable', 'Papers, rooms and invigilators', 'timetable.view'],
                ],
            ],
            'Seating plans' => [
                '' => [
                    ['seating', 'Seating plans', 'Who sits where in each room', 'seating.manage'],
                    ['seating/exam/create', 'New exam seating', 'Seat students for an exam', 'examtt.manage'],
                ],
            ],
            'BECE' => [
                '' => [
                    ['bece', 'Registration', 'JHS 3 candidates and their details', 'bece.view'],
                    ['bece/results', 'Results', 'BECE grades when they are released', 'bece.view'],
                    ['bece/print', 'Print list', 'Candidate list for the district office', 'bece.view'],
                ],
            ],
        ],
        'Timetable & Calendar' => [
            'Time table' => [
                '' => [
                    ['timetable', 'Classes', 'Each class\'s week', 'timetable.view'],
                    ['timetable/my', 'My time table', 'Your own lessons', 'timetable.view'],
                    ['timetable/free', 'Find a substitute', 'Teachers free in a period', 'timetable.view'],
                    ['timetable/lessons', 'Lessons per week', 'How many lessons each subject gets', 'timetable.manage'],
                    ['timetable/periods', 'Bell schedule', 'Periods and break times', 'timetable.manage'],
                    ['timetable/check', 'Check', 'Clashes and gaps', 'timetable.manage'],
                ],
            ],
            'Academic calendar' => [
                '' => [
                    ['calendar', 'Month view', 'Terms, holidays and school events', 'calendar.view'],
                    ['calendar/list', 'List', 'Every date in the term, one after another', 'calendar.view'],
                    ['calendar/create', 'Add a date', 'Put an event on the calendar', 'calendar.manage'],
                ],
            ],
            'Events' => [
                '' => [
                    ['events', 'Events', 'Registrations, payments and check-in', 'auth'],
                    ['events/create', 'New event', 'Open registration for an event', 'events.manage'],
                ],
            ],
            'Appointments' => [
                '' => [
                    ['appointments', 'Appointments', 'Meetings booked with parents', 'auth'],
                    ['appointments/availability', 'My availability', 'Times parents can book you', 'appointments.offer'],
                    ['appointments/types', 'Meeting types', 'Kinds of meeting and their length', 'appointments.manage'],
                ],
            ],
        ],
        'School services' => [
            'Rooms & resources' => [
                '' => [
                    ['resources', 'Rooms & resources', 'Book rooms, halls and equipment', 'resources.view'],
                ],
            ],
            'Documents' => [
                '' => [
                    ['documents', 'Documents', 'School files and policies', 'documents.view'],
                    ['documents/staff', 'Staff files', 'Contracts and certificates of staff', 'documents.staff'],
                    ['documents/expiring', 'Expiring soon', 'Documents that need renewing', 'documents.manage'],
                    ['documents/my', 'My files', 'Your own documents', 'staff'],
                ],
            ],
            'Library' => [
                '' => [
                    ['library', 'Lending', 'Books out and books due back', 'library.view'],
                    ['library/books', 'Catalogue', 'Every book in the library', 'library.view'],
                    ['library/report', 'Report', 'Most borrowed and overdue books', 'library.view'],
                ],
            ],
            'Transport' => [
                '' => [
                    ['transport', 'Transport', 'Buses, routes and riders', 'transport.view'],
                ],
            ],
            'Canteen' => [
                '' => [
                    ['canteen', 'Counter', 'Sell meals and snacks', 'canteen.sell'],
                    ['canteen/wallets', 'Wallets', 'Students\' canteen balances', 'canteen.sell'],
                    ['canteen/menu', 'Menu', 'What is served each day', 'canteen.sell'],
                    ['canteen/served', 'Meals served', 'Meals given out today', 'canteen.sell'],
                    ['canteen/plans', 'Meal plans', 'Termly meal plans', 'canteen.manage'],
                    ['canteen/items', 'Items & stock', 'Items for sale and stock left', 'canteen.manage'],
                    ['canteen/report', 'Report', 'Sales by day and item', 'canteen.manage'],
                ],
            ],
            'School shop' => [
                '' => [
                    ['shop/admin', 'Orders', 'Orders from parents', 'shop.manage'],
                    ['shop/admin/sell', 'Sell at the counter', 'Uniforms, books and other items', 'shop.manage'],
                    ['shop/admin/products', 'Products', 'Items, prices and stock', 'shop.manage'],
                    ['shop/admin/report', 'Report', 'Sales and stock report', 'shop.manage'],
                ],
            ],
        ],
        'Communication' => [
            'Notice board' => [
                '' => [
                    ['notices', 'Notice board', 'Notices and circulars to parents and staff', 'notices.view'],
                    ['notices/create', 'New notice', 'Post a notice or circular', 'notices.manage'],
                ],
            ],
            'Parent messages' => [
                '' => [
                    ['conversations', 'Messages', 'Conversations with parents', 'chat.use'],
                    ['conversations/create', 'New message', 'Write to a parent', 'chat.use'],
                ],
            ],
            'Notifications & SMS' => [
                '' => [
                    ['messages/compose', 'Send message', 'SMS or WhatsApp to parents and staff', 'messages.send'],
                    ['messages', 'History', 'Every message sent', 'messages.send'],
                    ['messages/outbox', 'Outbox', 'Messages waiting or failed', 'messages.send'],
                    ['messages/templates', 'Templates', 'Ready wording for common messages', 'app.configure'],
                    ['messages/settings', 'Settings', 'SMS provider and sender name', 'app.configure'],
                ],
            ],
            'Form builder' => [
                '' => [
                    ['forms', 'Forms', 'Surveys, consent forms and their responses', 'forms.manage'],
                ],
            ],
        ],
        'Staff & HR' => [
            'Staff attendance' => [
                '' => [
                    ['attendance/staff', 'One day', 'Who signed in and out today', 'staff_att.view'],
                    ['attendance/staff/summary', 'Summary & export', 'Attendance over a month', 'staff_att.view'],
                ],
            ],
            'Timesheets' => [
                '' => [
                    ['timesheets', 'Monthly timesheets', 'Hours worked by each staff member', 'timesheets.manage'],
                    ['timesheets/shifts', 'Shifts', 'Shift times for non-teaching staff', 'timesheets.manage'],
                    ['timesheets/my', 'My timesheet', 'Your own hours', 'staff'],
                ],
            ],
            'Leave & training' => [
                '' => [
                    ['staff-leave', 'Staff leave', 'Leave requests and balances', 'staff'],
                    ['cpd', 'Training (CPD)', 'Workshops and courses attended', 'staff'],
                ],
            ],
            'Movement book' => [
                '' => [
                    ['movements', 'Movement book', 'Staff going out during school hours', 'staff'],
                ],
            ],
            'Teachers' => [
                '' => [
                    ['teachers', 'Teacher appraisal', 'Termly appraisal of teachers', 'teachers.view'],
                    ['teachers/criteria', 'Appraisal criteria', 'What teachers are scored on', 'appraisal.manage'],
                ],
            ],
        ],
        'School records' => [
            'Head\'s log book' => [
                '' => [
                    ['logbook', 'Head\'s log book', 'Daily record of school events', 'logbook.manage'],
                ],
            ],
            'Gate pick-up' => [
                '' => [
                    ['gate', 'Gate pick-up', 'Check who collects each student', 'pickup.gate'],
                    ['gate/register', 'Authorised collectors', 'People allowed to collect students', 'pickup.manage'],
                ],
            ],
            'Visitors\' book' => [
                '' => [
                    ['visitors', 'Visitors\' book', 'Visitors in and out', 'visitors.record'],
                ],
            ],
            'Store ledger' => [
                '' => [
                    ['stores', 'Store ledger', 'Stock received and issued', 'stores.view'],
                ],
            ],
            'Licences & permits' => [
                '' => [
                    ['permits', 'Licences & permits', 'Renewal dates for licences', 'permits.view'],
                ],
            ],
        ],
        'Administration' => [
            'Users & access' => [
                '' => [
                    ['users', 'Users', 'Staff sign-in accounts', 'users.manage'],
                    ['teacher-records', 'Teacher records', 'Teacher profiles and qualifications', 'teachers.profile.manage'],
                    ['logins', 'Parent & student logins', 'Accounts for parents and students', 'logins.manage'],
                ],
            ],
            'Roles & security' => [
                '' => [
                    ['security', 'Roles', 'What each role can do', 'security.manage'],
                    ['security/matrix', 'Permission matrix', 'Every permission for every role', 'security.manage'],
                    ['security/settings', 'Sign-in rules', 'Passwords and 2-step sign-in', 'app.configure'],
                    ['security/review', 'Access review', 'Check who has access to what', 'security.manage'],
                    ['audit', 'Audit log', 'Who did what, and when', 'audit.view'],
                ],
            ],
            'School setup' => [
                '' => [
                    ['setup', 'School details', 'Name, crest, address and contacts', 'app.configure'],
                    ['setup/years', 'Years & terms', 'Academic years and term dates', 'app.configure'],
                    ['setup/classes', 'Classes', 'Classes and class teachers', 'app.configure'],
                    ['setup/wizard', 'Setup checklist', 'Steps to get the school ready', 'app.configure'],
                    ['golive', 'Go-live checklist', 'Final checks before going live', 'golive.manage'],
                    ['setup/modules', 'Modules on/off', 'Switch apps on or off', 'modules.switch'],
                    ['setup/columns', 'List columns', 'Columns shown in lists', 'app.configure'],
                    ['setup/app', 'Phone app', 'Install the app on phones', 'app.configure'],
                ],
            ],
            'Data protection' => [
                '' => [
                    ['setup/privacy', 'Data protection', 'Privacy notice and retention', 'app.configure'],
                    ['setup/privacy/requests', 'Data requests', 'Requests to see or delete data', 'app.configure'],
                    ['backups', 'Backups', 'Copies of the school records', 'backups.manage'],
                ],
            ],
            'Governance & risk' => [
                '' => [
                    ['governance', 'Overview', 'Board, policies and risks', 'governance.view'],
                    ['governance/meetings', 'Board meetings', 'Minutes and decisions', 'governance.view'],
                    ['governance/policies', 'Policy register', 'School policies and review dates', 'governance.view'],
                    ['governance/risks', 'Risk register', 'Risks and how they are managed', 'governance.view'],
                    ['governance/members', 'Board members', 'Members and their terms', 'governance.view'],
                ],
            ],
        ],
    ];

    /** Every app with its panel: Finance keeps its own menu (FinanceMenu). */
    private static function all(): array
    {
        return self::APPS + ['Finance' => FinanceMenu::GROUPS]; // on a tie the app's own panel wins
    }

    /** The panel this person sees for one app: module => [section => [items]]. */
    public static function visible(string $app): array
    {
        if ($app === 'Finance') {
            return FinanceMenu::visible();
        }
        $out = [];
        foreach (self::APPS[$app] ?? [] as $mod => $secs) {
            foreach ($secs as $sec => $items) {
                foreach ($items as $it) {
                    if (($it[3] === 'auth' || can($it[3])) && ModuleSwitch::blocked(strtok($it[0], '#')) === null) {
                        $out[$mod][$sec][] = $it;
                    }
                }
            }
        }
        return $out;
    }

    /** Length of the item route if it is this page or a page under it, else 0. */
    public static function matchLen(string $item, string $route): int
    {
        $p = strtok($item, '#?');
        return ($route === $p || str_starts_with($route, $p . '/')) ? strlen($p) : 0;
    }

    /** Which app this page belongs to: the item with the longest matching route wins (null = no panel). */
    public static function forRoute(string $route): ?string
    {
        $route = trim($route, '/');
        $best = null;
        $len = 0;
        foreach (self::all() as $app => $mods) {
            if ($app === 'Finance' && $route === 'fees/settings') {
                continue;
            }
            foreach ($mods as $secs) {
                foreach ($secs as $items) {
                    foreach ($items as $it) {
                        if (($n = self::matchLen($it[0], $route)) > $len) {
                            [$best, $len] = [$app, $n];
                        }
                    }
                }
            }
        }
        if ($app = FinanceMenu::groupOf($route)) { // purchasing, suppliers, payroll pages… under Finance prefixes
            $f = 0;
            foreach (['finance', 'fees', 'petty-cash', 'requisitions', 'purchasing', 'suppliers', 'payroll', 'ledger', 'fixed-assets', 'transport/bill'] as $p) {
                $f = max($f, self::matchLen($p, $route));
            }
            if ($f > $len) {
                $best = 'Finance';
            }
        }
        return $best;
    }

    /** The module (collapsible group) and the single link that are "here". */
    public static function here(array $panel, string $route): array
    {
        [$mod, $link, $len] = [null, null, 0];
        foreach ($panel as $m => $secs) {
            foreach ($secs as $items) {
                foreach ($items as $it) {
                    if (($n = self::matchLen($it[0], $route)) > $len) {
                        [$mod, $link, $len] = [$m, $it[0], $n];
                    }
                }
            }
        }
        return [$mod, $link];
    }

    /** Every link path in a panel (no #anchor or ?query). */
    public static function paths(array $panel): array
    {
        $r = [];
        foreach ($panel as $secs) {
            foreach ($secs as $items) {
                foreach ($items as $it) {
                    $r[] = strtok($it[0], '#?');
                }
            }
        }
        return $r;
    }
}
