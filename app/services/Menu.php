<?php
declare(strict_types=1);

/**
 * The apps that follow the picture tiles on NISDAS APPS: [route, label, permission, group, icon].
 * Apps in the Administration group are shown in the Administration menu on the top menu bar instead.
 * Pages kept off both on purpose (Dashboard, personal pages, setup tools, Teachers, School shop, Marketing) are linked
 * from the top-right "My pages" menu, School setup and related pages instead.
 */
final class Menu
{
    public const ADMIN = 'Administration';

    public static function items(): array
    {
        return [
            ['portal', 'My children', 'portal.parent', 'Admissions & Students', 'sis'],
            ['student', 'My learning', 'chat.use', 'Overview', 'sis'],
            ['learn', 'My quizzes', 'quiz.take', 'Overview', 'quiz'],
            ['homework', 'My homework', 'assignments.submit', 'Overview', 'assignment'],
            ['lms', 'Learning materials', Auth::role() === 'parent' ? 'portal.parent' : 'lms.view', 'Overview', 'lms'],
            ['live', 'Live classes', Auth::role() === 'parent' ? 'portal.parent' : 'live.join', 'Overview', 'live'],
            ['leave', 'Leave requests', 'portal.parent', 'Overview', 'leave'],
            ['events', 'Events', 'portal.parent', 'Overview', 'event'],
            ['appointments', 'Appointments', 'appointments.book', 'Overview', 'appointment'],
            ['admission', 'Admission', 'admission.view', 'Admissions & Students', 'admission'],
            ['crm', 'CRM — enquiries', 'crm.manage', 'Admissions & Students', 'crm'],
            ['pupils', 'Students', 'pupils.view', 'Admissions & Students', 'sis'],
            ['attendance', 'Attendance', 'attendance.view', 'Admissions & Students', 'attendance'],
            ['leave', 'Student leave', 'leave.approve', 'Admissions & Students', 'leave'],
            ['welfare', 'Behaviour & welfare', 'welfare.record', 'Admissions & Students', 'health'],
            ['health', 'Health & sick bay', 'health.view', 'Admissions & Students', 'health'],
            ['assignments', 'Assignments', 'assignments.manage', 'Admissions & Students', 'assignment'],
            ['lms', 'LMS — learning materials', 'lms.manage', 'Admissions & Students', 'lms'],
            ['live', 'Live classes', 'live.manage', 'Admissions & Students', 'live'],
            ['quizzes', 'Quizzes', 'quiz.manage', 'Admissions & Students', 'quiz'],
            ['curriculum', 'Subject Management', 'scheme.plan', 'Admissions & Students', 'course'],
            ['exams', 'Exams & report cards', 'exams.enter', 'Exams & Results', 'gradebook'],
            ['exam-timetable', 'Exam timetable', 'timetable.view', 'Exams & Results', 'exam'],
            ['seating', 'Seating plans', 'seating.manage', 'Exams & Results', 'seating'],
            ['bece', 'BECE', 'bece.view', 'Exams & Results', 'bece'],
            ['timetable', 'Time table', 'timetable.view', 'Timetable & Calendar', 'timetable'],
            ['calendar', 'Academic calendar', 'calendar.view', 'Timetable & Calendar', 'calendar'],
            ['events', 'Events', 'staff', 'Timetable & Calendar', 'event'],
            ['appointments', 'Appointments', 'appointments.offer', 'Timetable & Calendar', 'appointment'],
            ['fees', 'Fees', 'fees.view', 'Finance', 'fees'],
            ['finance', 'Finance (cash book)', 'finance.view', 'Finance', 'financial'],
            ['requisitions', 'Requisitions', 'requisitions.create', 'Finance', 'requisition'],
            ['payroll', 'Payroll', 'payroll.view', 'Finance', 'payroll'],
            ['resources', 'Rooms & resources', 'resources.view', 'School services', 'resources'],
            ['documents', 'Documents', 'documents.view', 'School services', 'document'],
            ['library', 'Library', 'library.view', 'School services', 'library'],
            ['transport', 'Transport', 'transport.view', 'School services', 'transport'],
            ['canteen', 'Canteen', 'canteen.sell', 'School services', 'canteen'],
            ['notices', 'Notice board', 'notices.view', 'Communication', 'notices'],
            ['conversations', can('staff') ? 'Parent messages' : 'Messages', 'chat.use', 'Communication', 'chat'],
            ['messages', 'Notifications & SMS', 'messages.send', 'Communication', 'sms'],
            ['forms', 'Form builder', 'forms.manage', 'Communication', 'forms'],
            ['attendance/staff', 'Staff attendance', 'staff_att.view', 'Staff & HR', 'staffatt'],
            ['timesheets', 'Staff timesheets', 'timesheets.manage', 'Staff & HR', 'timesheet'],
            ['staff-leave', 'Staff leave & training', 'staff', 'Staff & HR', 'leave'],
            ['movements', 'Staff movement book', 'staff', 'Staff & HR', 'staffatt'],
            ['logbook', "Head's log book", 'logbook.manage', 'School records', 'audit'],
            ['gate', 'Gate pick-up', 'pickup.gate', 'School records', 'security'],
            ['visitors', "Visitors' book", 'visitors.record', 'School records', 'users'],
            ['stores', 'Store ledger', 'stores.view', 'School records', 'resources'],
            ['permits', 'Licences & permits', 'permits.view', 'School records', 'document'],
            ['governance', 'Governance & risk', 'governance.view', 'Administration', 'audit'],
            ['setup', 'School setup', 'app.configure', 'Administration', 'setup'],
            ['setup/modules', 'Modules on/off', 'modules.switch', 'Administration', 'setup'],
            ['users', 'Users', 'users.manage', 'Administration', 'users'],
            ['teacher-records', 'Teacher records', 'teachers.profile.manage', 'Administration', 'staffatt'],
            ['security', 'Roles & security', 'security.manage', 'Administration', 'security'],
            ['logins', 'Parent & student logins', 'logins.manage', 'Administration', 'logins'],
            ['backups', 'Backups', 'backups.manage', 'Administration', 'backup'],
            ['setup/app', 'Phone app', 'app.configure', 'Administration', 'phoneapp'],
            ['audit', 'Audit log', 'audit.view', 'Administration', 'audit'],
        ];
    }

    /** Apps this person may open, in menu order: [[route, label, group, icon], …] (group '' for parents and pupils). */
    public static function visible(): array
    {
        $user = auth();
        $role = $user['role'] ?? '';
        $out = [];
        $seen = [];
        foreach (self::items() as [$r, $label, $perm, $grp, $icon]) {
            if ($perm !== 'auth' && !can($perm)) continue;
            if (ModuleSwitch::blocked($r) !== null) continue; // switched off in School setup → Modules on/off
            // Parent and pupil pages: parents/pupils, and Super Admin as previews; other staff do not need them.
            if (($perm === 'portal.parent' && !in_array($role, ['parent', 'super_admin'], true)) || ($perm === 'quiz.take' && !in_array($role, ['pupil', 'super_admin'], true))) continue;
            if ($r === 'student' && $role !== 'pupil') continue;
            if ($perm === 'assignments.submit' && $role !== 'pupil') continue;
            if (in_array($r, ['leave', 'events'], true) && $perm === 'portal.parent' && $role !== 'parent') continue;
            if ($perm === 'appointments.book' && $role !== 'parent') continue;
            if (in_array($r, ['lms', 'live'], true) && in_array($perm, ['lms.view', 'live.join', 'portal.parent'], true) && !in_array($role, ['pupil', 'parent'], true)) continue;
            if (isset($seen[$r])) continue;
            $seen[$r] = true;
            if ($role === 'super_admin' && $perm === 'portal.parent') $label = 'Parent portal (preview)';
            if ($role === 'super_admin' && $perm === 'quiz.take') $label = 'Student quizzes (preview)';
            $out[] = [$r, $label, can('staff') ? $grp : '', $icon, self::description($r)];
        }
        return $out;
    }

    /** Short navigation help shown consistently across the app modules. */
    public static function description(string $route): string
    {
        return [
            'portal' => 'Attendance, results, bills and messages for your children.',
            'student' => 'Your class, timetable, marks and attendance.',
            'learn' => 'Take a published class quiz and review your results.',
            'homework' => 'View homework, submit work and read teacher feedback.',
            'lms' => 'Class learning materials, videos and worksheets.',
            'live' => 'Scheduled online classes and joining details.',
            'leave' => 'Request student leave or review pending requests.',
            'events' => 'School events, registrations and check-in.',
            'appointments' => 'Offer or book a meeting with school staff.',
            'admission' => 'Review applications and manage admission stages.',
            'crm' => 'Track family enquiries and follow-ups.',
            'pupils' => 'Student records, guardians, documents and enrolment.',
            'attendance' => 'Daily registers, attendance summaries and reports.',
            'welfare' => 'Record and follow up on student welfare cases.',
            'health' => 'Sick-bay visits, health details and alerts.',
            'assignments' => 'Set class work, mark submissions and share feedback.',
            'quizzes' => 'Create and manage online class quizzes.',
            'curriculum' => 'Subjects, schemes of work and lesson notes.',
            'exams' => 'Enter marks, approve score sheets and release results.',
            'exam-timetable' => 'Plan exam papers, rooms and invigilators.',
            'seating' => 'Arrange class and examination seating plans.',
            'bece' => 'Manage BECE candidates and examination results.',
            'timetable' => 'View or prepare class and teacher timetables.',
            'calendar' => 'School terms, holidays and calendar events.',
            'fees' => 'Bills, balances and fee payments for the current term.',
            'finance' => 'Cash, bank and Mobile Money balances and entries.',
            'requisitions' => 'Request, approve and track school purchases.',
            'payroll' => 'Prepare staff pay, deductions and payslips.',
            'resources' => 'Classrooms, equipment, textbooks and seating.',
            'documents' => 'School, student and staff document records.',
            'library' => 'Book catalogue, loans, returns and overdue items.',
            'transport' => 'School bus routes, stops, riders and charges.',
            'canteen' => 'Menus, sales, student wallets and meal plans.',
            'notices' => 'Publish and read school notices and circulars.',
            'conversations' => 'Read and respond to school-family messages.',
            'messages' => 'Send SMS and review delivery status.',
            'forms' => 'Create forms and review submitted responses.',
            'attendance/staff' => 'Staff sign-in, daily attendance and lateness.',
            'timesheets' => 'Review hours, shifts and monthly timesheets.',
            'staff-leave' => 'Record staff leave and training activity.',
            'movements' => 'Record staff movements during the school day.',
            'logbook' => 'Record key events in the school log book.',
            'gate' => 'Verify authorised student pick-ups at the gate.',
            'visitors' => 'Record and review visitors to the school.',
            'stores' => 'Track school supplies, stock and store activity.',
            'permits' => 'Track school licences, permits and expiry dates.',
            'governance' => 'Review governance records, risks and actions.',
            'setup' => 'Configure school details and academic setup.',
            'setup/modules' => 'Enable or disable available school modules.',
            'users' => 'Create staff accounts and manage access.',
            'teacher-records' => 'Maintain teacher profiles and credentials.',
            'security' => 'Review roles, permissions and security activity.',
            'logins' => 'Create and manage parent and student sign-ins.',
            'backups' => 'Create, restore and review system backups.',
            'setup/app' => 'Configure the installable phone app.',
            'audit' => 'Review recorded system changes and sign-ins.',
        ][$route] ?? '';
    }
}
