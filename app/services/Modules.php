<?php
declare(strict_types=1);

/**
 * The module catalogue behind the "Modules" page and the dashboard cards: every feature of the system
 * grouped as Daily Operations, Student Success and School Life, each with where to open it for the
 * person signed in (or hidden when they have no access).
 */
final class Modules
{
    public const GROUPS = [
        'daily'   => ['Daily Operations', 'The tools staff use every day: attendance, assignments, timetables, classrooms and gradebooks that keep the school running smoothly.'],
        'success' => ['Student Success', 'Everything that helps students succeed, from the day they apply until they leave: admissions, records, courses, exams and teachers in one place.'],
        'life'    => ['School Life & Communication', 'Keeping families informed and the school community connected: portal, notices, transport, library, sick bay and reports.'],
    ];

    /**
     * key => [group, title, short description, icon, tile colour, [targets], [what you can do], who uses it]
     * targets: list of [route, permission or role check] — the first one the person may open is used.
     */
    public static function all(): array
    {
        return [
            // ---------------------------------------------------------------- Daily Operations (9)
            'classrooms' => ['daily', 'Classroom and Facility Management', 'Organise rooms, furniture, textbooks and equipment for every class, see shortages at a glance and plan seating.',
                'monitor', 'teal', [['resources', 'resources.view'], ['seating', 'seating.manage']],
                ['Rooms with capacity and the class that uses each one', 'Furniture, textbooks and equipment per class with their condition', 'Shortages report for planning purchases', 'Seating plans for classes and exam halls'], 'Head, coordinator, class teachers, Bursar'],
            'gradebook' => ['daily', 'Gradebook and Grading System', 'Record class scores and exam marks, work out grades and positions automatically and print terminal report cards.',
                'check', 'blue', [['exams', 'exams.enter']],
                ['Score sheets per class and subject (class work and exam)', 'Grades, remarks and positions worked out for you', 'Head approval and release of report cards to parents', 'Progress charts across terms'], 'Teachers, coordinator, Head'],
            'applications' => ['daily', 'Student Application & Admission', 'A secure online form where parents apply, upload documents and track the application without coming to school.',
                'form', 'orange', [['admission', 'admission.view']],
                ['Online application form to share by WhatsApp or SMS', 'Birth certificate and photo uploads', 'Each application tracked from received to enrolled', 'Parents told by SMS at each step'], 'Admissions officer, Head; parents apply online'],
            'timesheets' => ['daily', 'Staff Timesheets', 'Track staff hours from sign-in and sign-out, manage shifts and approve monthly timesheets that feed the payroll.',
                'clockdoc', 'amber', [['timesheets', 'timesheets.manage'], ['timesheets/my', 'staff']],
                ['Hours worked per day and per month', 'Shifts for kitchen, cleaning and security staff', 'Late arrivals, early leaving and forgotten sign-outs', 'Head approval; staff paid by the day are paid from it'], 'Head (approves); every staff member sees their own'],
            'timetable' => ['daily', 'Timetable Management', 'Create clash-free class timetables, balance teachers and rooms, and show everyone their week.',
                'calclock', 'red', [['timetable', 'timetable.view']],
                ['Automatic timetable generation without clashes', 'Teacher, class and room timetables', 'Lessons per week per subject', 'Parents see their child’s timetable'], 'Coordinator, teachers, parents'],
            'fees' => ['daily', 'Fee Payment and Collection', 'Bill school fees each term, take cash, MoMo or online payments, print receipts and follow up debtors.',
                'card', 'green', [['fees', 'fees.view'], ['portal', 'portal.parent']],
                ['Termly bills from the fee schedule with discounts', 'Cash, Mobile Money and online payment with receipts', 'Debtors list and reminder SMS', 'Parents see balances and pay online'], 'Bursar, Head; parents pay online'],
            'attendance' => ['daily', 'Attendance Management', 'Take the daily register in seconds, text parents of absent students automatically and spot attendance problems early.',
                'personcheck', 'blue', [['attendance', 'attendance.view'], ['portal', 'portal.parent']],
                ['Daily class register on phone or computer', 'Absence SMS to parents', 'Weekly and termly attendance reports', 'Staff sign-in and sign-out'], 'Class teachers, Head, parents'],
            'assignments' => ['daily', 'Assignment Management', 'Set homework, let students hand it in online or on paper, mark it with feedback and keep parents in the loop.',
                'book', 'indigo', [['assignments', 'assignments.manage'], ['homework', 'assignments.submit'], ['portal', 'portal.parent']],
                ['Homework per class and subject with due dates and worksheets', 'Students hand in typed answers or a photo of their work', 'Scores and feedback returned to student and parent', 'Who has and has not handed in, at a glance'], 'Teachers, students, parents'],
            'leave' => ['daily', 'Student Leave Management', 'Parents ask for leave online; the class teacher approves and the days show as excused, not absent.',
                'personcal', 'teal', [['leave', 'leave.approve'], ['leave', 'portal.parent']],
                ['Leave requests from the parent portal with a doctor’s note', 'Class teacher or Head approves or declines with a note', 'Approved days marked Excused on the register', 'Full history per student'], 'Parents, class teachers, Head'],
            // ---------------------------------------------------------------- Student Success (6)
            'exams' => ['success', 'Examination System', 'Schedule exam papers without clashes, seat classes in halls, assign invigilators and publish the exam timetable.',
                'examdoc', 'teal', [['exam-timetable', 'timetable.view'], ['bece', 'bece.view']],
                ['Exam timetable with clash checks', 'Exam hall seating that mixes classes', 'Invigilators per paper', 'BECE registration and results for JHS 3'], 'Coordinator, Head, teachers'],
            'courses' => ['success', 'Subject Management', 'Everything about subjects in one place: subjects and teachers, NaCCA strands, the termly scheme of work, weekly lesson notes with vetting, results per subject and approved textbooks.',
                'bookblue', 'sky', [['curriculum', 'scheme.plan'], ['curriculum', 'curriculum.manage']],
                ['Subjects and subject teachers per class', 'NaCCA strands and termly scheme of work', 'Weekly lesson notes vetted by the Head', 'Results and approved textbooks per subject'], 'Coordinator, Head, teachers'],
            'admissions' => ['success', 'Admission Management', 'Review applications, run entrance assessments, offer places and enrol new students with their parents in one step.',
                'personred', 'rose', [['admission', 'admission.view']],
                ['Applications pipeline by stage', 'Assessment results and offers', 'Enrol with class, parents and login in one step', 'Admission letters and SMS'], 'Admissions officer, Head'],
            'faculty' => ['success', 'Faculty Management', 'Teacher profiles, workload, attendance, class results, licences and termly appraisals.',
                'faculty', 'sky', [['teachers', 'teachers.view'], ['teachers/me', 'staff']],
                ['Teacher profiles with NTC licence tracking', 'Lessons per week and subjects taught', 'Class results and scheme coverage', 'Self-assessment and Head appraisal each term'], 'Head, coordinator; each teacher sees their own'],
            'finance' => ['success', 'Financial Management', 'Cash book, budgets, requisitions and payroll with SSNIT and PAYE, all linked to the fees received.',
                'card', 'green', [['finance', 'finance.view'], ['requisitions', 'requisitions.create']],
                ['Cash book for cash, bank and MoMo accounts', 'Budget against actual spending', 'Requisitions with approval and payment', 'Monthly payroll with SSNIT and PAYE'], 'Bursar, Head; staff raise requisitions'],
            'students' => ['success', 'Student Management', 'One record for every pupil: personal details, parents, health, documents, attendance, results and fees.',
                'person', 'sky', [['pupils', 'pupils.view'], ['portal', 'portal.parent']],
                ['Student profiles with parents and emergency contacts', 'Health notes and allergies', 'Documents, printouts and ID cards', 'Promotion at the end of the year'], 'Head, admissions, teachers, Bursar'],
            // ---------------------------------------------------------------- School life & communication
            'portal' => ['life', 'Parent Portal and Login System', 'Parents check attendance, results, fee balances, homework and teacher messages any time from their phone.',
                'family', 'indigo', [['portal', 'portal.parent'], ['logins', 'logins.manage']],
                ['Attendance, results and report cards', 'Fee balances and online payment', 'Homework and leave requests', 'Messages with the class teacher'], 'Parents; office sets up logins'],
            'notices' => ['life', 'Notice Board & Announcements', 'Publish notices and circulars to chosen classes, staff or parents, with SMS alerts and read receipts.',
                'megaphone', 'amber', [['notices', 'notices.view']],
                ['Notices for everyone or chosen classes', 'Circulars that parents must acknowledge', 'Publish and expiry dates', 'Who has read it'], 'Head, coordinator; everyone reads'],
            'messages' => ['life', 'SMS & Messages', 'Text parents and staff, keep conversations with parents in one place and manage SMS templates.',
                'chat', 'blue', [['conversations', 'chat.use'], ['messages', 'messages.send']],
                ['Parent ↔ school conversations', 'Bulk SMS to classes or staff', 'Automatic SMS for absence, fees and results', 'Delivery log'], 'Teachers, office, parents'],
            'transport' => ['life', 'Transportation Management', 'Bus routes, stops, riders and bus fees linked to student records.',
                'bus', 'orange', [['transport', 'transport.view']],
                ['Routes with stops and pick-up times', 'Students assigned to routes', 'Bus fees on the bill', 'Rider lists for drivers'], 'Office, Bursar, Head'],
            'analytics' => ['life', 'Dashboard & Analytics', 'Live figures for enrolment, attendance, results and fees, with charts and alerts for what needs attention.',
                'chart', 'indigo', [['dashboard', 'staff']],
                ['Enrolment by level and gender', 'Attendance and fee collection trends', 'Results by class and subject', 'Alerts for what needs attention'], 'Head, coordinator, Bursar'],
            'calendar' => ['life', 'Academic Calendar & Events', 'Terms, holidays, Sabbath and school events for everyone, with closures respected by the register.',
                'calendar', 'red', [['calendar', 'calendar.view']],
                ['Terms, holidays and vacations', 'School events and PTA meetings', 'Closed days left out of registers', 'Shared with parents'], 'Everyone'],
            'canteen' => ['life', 'Canteen Management', 'Menus, counter sales from prepaid student wallets or cash, stock, termly meal plans billed with fees, and allergy warnings at the till.',
                'canteen', 'orange', [['canteen', 'canteen.sell'], ['portal', 'portal.parent']],
                ['Daily menu that parents can see', 'Counter sales from a prepaid wallet, cash or MoMo', 'Termly meal plans billed with school fees', 'Allergy warning before selling', 'Stock, low-stock alerts and sales reports'], 'Bursar, canteen staff; parents see the wallet and menu'],
            'events' => ['life', 'Events Management', 'Plan school events, let parents register their children, bill event fees, keep a waiting list, check people in and report on the day.',
                'calendar', 'rose', [['events', 'events.manage'], ['events', 'staff'], ['events', 'portal.parent']],
                ['Events on the calendar for chosen audiences', 'Online registration with places and a waiting list', 'Event fees added to the child’s bills', 'Check-in on the day and a CSV list'], 'Head, coordinator; parents register'],
            'appointments' => ['life', 'Online Appointments', 'Parents book meetings with the class teacher, Head or accounts office from the times staff offer; both sides can cancel.',
                'calclock', 'teal', [['appointments', 'appointments.offer'], ['appointments', 'appointments.book']],
                ['Appointment types with their length and a question', 'Staff publish weekly free times', 'Parents pick a free slot; no double booking', 'Cancel with a note; mark held or missed'], 'Parents, teachers, Head, Bursar'],
            'whatsapp' => ['life', 'WhatsApp Integration', 'Send fee reminders, absence alerts, results and notices to parents on WhatsApp, with SMS as a back-up and read receipts.',
                'chat', 'green', [['messages/settings', 'messages.manage'], ['messages/outbox', 'messages.send']],
                ['Meta Cloud API, Twilio or Hubtel — chosen in settings', 'WhatsApp and SMS, or WhatsApp first with SMS if it fails', 'Read receipts in the outbox', 'Test mode before going live'], 'Head, office'],
            'mobileapp' => ['life', 'Mobile Application', 'Install the system on Android or iPhone from the browser, with phone notifications and recent pages available offline — ready for Google Play.',
                'phone', 'indigo', [['setup/app', 'app.configure'], ['notifications', 'auth']],
                ['Installs from the browser — no app store needed', 'Phone notifications for approvals, homework, fees and messages', 'Recent pages open without a network', 'Google Play package via PWABuilder when ready'], 'Everyone: staff, parents and students'],
            'library' => ['life', 'Library', 'Books, copies and loans for students and staff with overdue reminders.',
                'books', 'teal', [['library', 'library.view']],
                ['Book catalogue and copies', 'Issue and return', 'Overdue list', 'Reading history per student'], 'Teachers, librarian'],
            'health' => ['life', 'Health & Sick Bay', 'Sick-bay visits, health details and allergies, with parents told when a child is unwell.',
                'health', 'rose', [['health', 'health.view']],
                ['Sick-bay visits and treatment', 'Allergies and conditions', 'Parents notified', 'Health summary per student'], 'Head, class teachers'],
            'forms' => ['life', 'Form Builder', 'Make surveys, consent forms and registrations, share them with parents or staff and read the replies.',
                'form', 'amber', [['forms', 'forms.manage']],
                ['Forms with many kinds of questions', 'Share a link with parents or staff', 'Replies in a table and CSV', 'Close a form when done'], 'Head, coordinator, office'],
            'lms' => ['life', 'LMS — Learning Materials', 'Teachers post lesson notes, videos, links and worksheets for each class; students and parents read them at home.',
                'monitor', 'indigo', [['lms', 'lms.manage'], ['lms', 'lms.view'], ['lms', 'portal.parent']],
                ['Lesson notes, YouTube videos, web links and worksheets', 'Per class and subject, linked to NaCCA strands', 'Students and parents told when something new is posted', 'See which students have opened each material'], 'Teachers, students, parents'],
            'live' => ['life', 'Live Classroom (Zoom, Google Meet, Teams)', 'Schedule online classes with a Zoom, Google Meet or Teams link; students join from the school system and a join register is kept.',
                'calclock', 'teal', [['live', 'live.manage'], ['live', 'live.join'], ['live', 'portal.parent']],
                ['Paste the meeting link made in Zoom, Google Meet or Teams', 'Students and parents see it, with a reminder 30 minutes before', 'Join button opens 15 minutes before the start', 'Register of who joined; optional SMS to parents'], 'Teachers, students, parents'],
            'crm' => ['life', 'CRM — Admission Enquiries', 'Every parent who asks about the school followed up until the child applies: follow-up dates, calls and visits, and a website enquiry form.',
                'personred', 'indigo', [['crm', 'crm.manage']],
                ['Enquiries from calls, walk-ins, church, events and the website form', 'Stages from new enquiry to enrolled, with follow-up dates', 'Application link sent by SMS; linked automatically when they apply', 'Where enquiries come from and how many join'], 'Admissions officer, Head'],
            'marketing' => ['life', 'Marketing Campaigns', 'SMS and WhatsApp campaigns to enquirers and past or current families — open days, admissions, school news — with results.',
                'megaphone', 'rose', [['marketing', 'marketing.send']],
                ['Choose who: open enquiries, a stage, past or current parents, or typed numbers', 'See how many people before sending', 'Delivered and read counts; applications that followed', 'Do-not-message list respected'], 'Admissions officer, Head'],
            'shop' => ['life', 'E-Commerce — School Shop', 'Parents order uniforms, books and stationery and pay online by Mobile Money; the Bursar prepares and hands over, with stock kept up to date.',
                'card', 'orange', [['shop/admin', 'shop.manage'], ['shop', 'shop.order']],
                ['Products with sizes, prices and stock', 'Parents order for their child and pay by MoMo online', 'Order ready SMS; collection recorded', 'Counter sales with receipts; money in the cash book under School shop'], 'Bursar, Head; parents order'],
            'documents' => ['life', 'Document Management', 'The school document library plus each student’s and staff member’s files, with reminders before anything expires.',
                'form', 'amber', [['documents', 'documents.view']],
                ['Policies, circulars, GES letters, minutes and licences in one library', 'Management-only documents', 'Student files: birth certificate, NHIS card, previous reports', 'Staff files: certificates, contracts, NTC licence', 'Reminders 30 days before a document expires'], 'Head, coordinator, office, Bursar; staff read'],
            'quizzes' => ['life', 'Quizzes', 'Online quizzes that mark themselves, for practice at school or at home.',
                'check', 'indigo', [['quizzes', 'quiz.manage'], ['learn', 'quiz.take']],
                ['Multiple-choice and short-answer questions', 'Timed quizzes marked automatically', 'Results per class', 'Students see their score at once'], 'Teachers, students'],
        ];
    }

    /** The route this person should open for a module, or null when they have no access. */
    public static function target(array $m): ?string
    {
        foreach ($m[5] as [$route, $perm]) {
            if (($perm !== 'auth' && !can($perm)) || ModuleSwitch::blocked($route) !== null) {
                continue;
            }
            // Parent-only and pupil-only pages: only for those roles (Super Admin uses the staff pages)
            if ((in_array($perm, ['portal.parent', 'shop.order'], true) && Auth::role() !== 'parent') || (in_array($perm, ['quiz.take', 'assignments.submit', 'lms.view', 'live.join'], true) && Auth::role() !== 'pupil')) {
                continue;
            }
            return $route;
        }
        return null;
    }

    /** Modules grouped for display: group => [key => module + 'route']. $onlyOpen = hide those without access. */
    public static function grouped(bool $onlyOpen = true): array
    {
        $out = [];
        foreach (self::all() as $k => $m) {
            $route = self::target($m);
            if ($onlyOpen && $route === null) {
                continue;
            }
            $out[$m[0]][$k] = $m + ['route' => $route];
        }
        return $out;
    }

    /** Inline icon (simple shapes on a soft tile). */
    public static function icon(string $name, string $tone = 'blue', int $size = 48): string
    {
        $c = ['blue' => ['#e7effc', '#2f6fd6'], 'teal' => ['#e3f5f3', '#138a7e'], 'orange' => ['#fdf0e1', '#d9822b'], 'amber' => ['#fdf3dc', '#c98a0c'],
              'red' => ['#fde9e7', '#d6493b'], 'green' => ['#e4f4ea', '#1f8f55'], 'indigo' => ['#ecebfd', '#5a55d6'], 'sky' => ['#e3f2fc', '#2b8fd6'], 'rose' => ['#fde8ee', '#d6436d']][$tone] ?? ['#e7effc', '#2f6fd6'];
        [$bg, $fg] = $c;
        $g = [
            'monitor'     => '<rect x="5" y="6" width="22" height="15" rx="2" fill="FG"/><rect x="8" y="9" width="16" height="9" rx="1" fill="#fff" opacity=".35"/><rect x="12" y="23" width="8" height="3" rx="1" fill="FG"/>',
            'check'       => '<circle cx="16" cy="16" r="10" fill="none" stroke="FG" stroke-width="2.4"/><path d="M11.5 16.5l3 3 6-6.5" fill="none" stroke="FG" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>',
            'form'        => '<rect x="6" y="5" width="20" height="22" rx="2.5" fill="FG"/><rect x="9" y="10" width="14" height="2.4" rx="1" fill="#fff"/><rect x="9" y="15" width="14" height="2.4" rx="1" fill="#fff" opacity=".8"/><rect x="9" y="20" width="9" height="2.4" rx="1" fill="#fff" opacity=".8"/>',
            'clockdoc'    => '<rect x="7" y="5" width="17" height="21" rx="2.5" fill="FG"/><circle cx="14" cy="14" r="5" fill="#fff"/><path d="M14 11.5V14l1.8 1.3" stroke="FG" stroke-width="1.6" fill="none" stroke-linecap="round"/><rect x="10" y="23" width="16" height="4" rx="1.5" fill="#7a3b14"/>',
            'calclock'    => '<rect x="5" y="7" width="22" height="19" rx="3" fill="FG"/><rect x="5" y="7" width="22" height="5" rx="2" fill="#fff" opacity=".35"/><circle cx="16" cy="19" r="4.5" fill="#fff"/><path d="M16 17v2.2l1.5 1" stroke="FG" stroke-width="1.5" fill="none" stroke-linecap="round"/>',
            'card'        => '<rect x="4" y="8" width="24" height="16" rx="3" fill="FG"/><rect x="4" y="12" width="24" height="3.5" fill="#fff" opacity=".45"/><rect x="7" y="19" width="7" height="2" rx="1" fill="#fff" opacity=".8"/>',
            'personcheck' => '<circle cx="13" cy="11" r="5" fill="FG"/><path d="M4 26c0-5 4-8 9-8s9 3 9 8z" fill="FG" opacity=".85"/><circle cx="23" cy="22" r="5" fill="#2f6fd6" stroke="#fff" stroke-width="1.5"/><path d="M20.8 22l1.6 1.6 2.8-3" stroke="#fff" stroke-width="1.6" fill="none" stroke-linecap="round"/>',
            'book'        => '<rect x="8" y="4" width="16" height="24" rx="2.5" fill="FG"/><rect x="11" y="4" width="3" height="24" fill="#000" opacity=".18"/><rect x="16" y="9" width="5" height="2" rx="1" fill="#fff" opacity=".8"/>',
            'personcal'   => '<rect x="4" y="7" width="24" height="19" rx="3" fill="FG"/><circle cx="13" cy="15" r="3" fill="#fff"/><path d="M8 22c0-2.6 2.2-4 5-4s5 1.4 5 4z" fill="#fff"/><circle cx="23" cy="21" r="3.5" fill="#f3b23a"/>',
            'examdoc'     => '<rect x="7" y="4" width="18" height="22" rx="2.5" fill="FG"/><circle cx="16" cy="13" r="5" fill="#fff"/><path d="M16 10.5V13l1.8 1.2" stroke="FG" stroke-width="1.6" fill="none" stroke-linecap="round"/><rect x="5" y="24" width="22" height="4" rx="1.5" fill="FG" opacity=".6"/>',
            'bookblue'    => '<rect x="8" y="4" width="17" height="24" rx="2.5" fill="FG"/><rect x="8" y="4" width="4" height="24" rx="1" fill="#000" opacity=".2"/><rect x="15" y="16" width="7" height="2" rx="1" fill="#fff"/><rect x="15" y="20" width="7" height="2" rx="1" fill="#fff"/>',
            'personred'   => '<rect x="7" y="4" width="18" height="24" rx="2.5" fill="FG"/><circle cx="13" cy="10" r="2.6" fill="#fff"/><rect x="10" y="14" width="6" height="2" rx="1" fill="#fff"/><rect x="7" y="24" width="18" height="4" rx="1.5" fill="#7d1d34"/>',
            'faculty'     => '<circle cx="16" cy="10" r="5.5" fill="FG"/><path d="M6 27c0-6 4.5-9 10-9s10 3 10 9z" fill="FG"/><path d="M16 18l-2 4 2 5 2-5z" fill="#fff"/>',
            'person'      => '<circle cx="16" cy="11" r="6" fill="FG"/><path d="M5 27c0-6 5-9.5 11-9.5S27 21 27 27z" fill="FG"/>',
            'family'      => '<circle cx="11" cy="10" r="4" fill="FG"/><circle cx="22" cy="12" r="3.2" fill="FG" opacity=".8"/><path d="M3 26c0-5 3.6-8 8-8s8 3 8 8z" fill="FG"/><path d="M16 26c0-4 2.6-6.5 6-6.5s6 2.5 6 6.5z" fill="FG" opacity=".8"/>',
            'megaphone'   => '<path d="M6 13h5l11-6v18l-11-6H6z" fill="FG"/><rect x="9" y="19" width="4" height="7" rx="1.5" fill="FG" opacity=".7"/><path d="M25 12.5a5 5 0 010 7" stroke="FG" stroke-width="2" fill="none" stroke-linecap="round"/>',
            'chat'        => '<path d="M5 8a3 3 0 013-3h16a3 3 0 013 3v10a3 3 0 01-3 3H13l-6 5v-5H8a3 3 0 01-3-3z" fill="FG"/><rect x="9" y="10" width="14" height="2" rx="1" fill="#fff"/><rect x="9" y="14" width="9" height="2" rx="1" fill="#fff" opacity=".8"/>',
            'bus'         => '<rect x="5" y="5" width="22" height="18" rx="3.5" fill="FG"/><rect x="8" y="8" width="16" height="7" rx="1.5" fill="#fff" opacity=".85"/><circle cx="10" cy="25" r="2.5" fill="FG"/><circle cx="22" cy="25" r="2.5" fill="FG"/>',
            'chart'       => '<rect x="5" y="17" width="5" height="10" rx="1.5" fill="FG" opacity=".6"/><rect x="13.5" y="10" width="5" height="17" rx="1.5" fill="FG"/><rect x="22" y="5" width="5" height="22" rx="1.5" fill="FG" opacity=".8"/>',
            'calendar'    => '<rect x="5" y="7" width="22" height="20" rx="3" fill="FG"/><rect x="5" y="7" width="22" height="6" rx="2" fill="#fff" opacity=".35"/><rect x="9" y="16" width="4" height="4" rx="1" fill="#fff"/><rect x="15" y="16" width="4" height="4" rx="1" fill="#fff" opacity=".7"/>',
            'books'       => '<rect x="5" y="7" width="5" height="20" rx="1.5" fill="FG"/><rect x="11.5" y="5" width="5" height="22" rx="1.5" fill="FG" opacity=".75"/><rect x="18" y="8" width="5" height="19" rx="1.5" fill="FG" transform="rotate(-12 20.5 17.5)"/>',
            'canteen'     => '<path d="M6 14h20a10 9 0 01-20 0z" fill="FG"/><rect x="5" y="23" width="22" height="3" rx="1.5" fill="FG" opacity=".6"/><path d="M12 5c-1.5 2 1.5 3 0 5M17 5c-1.5 2 1.5 3 0 5M22 5c-1.5 2 1.5 3 0 5" stroke="FG" stroke-width="1.6" fill="none" stroke-linecap="round"/>',
            'phone'       => '<rect x="9" y="3" width="14" height="26" rx="3" fill="FG"/><rect x="11.5" y="6.5" width="9" height="16" rx="1" fill="#fff" opacity=".85"/><circle cx="16" cy="25.5" r="1.3" fill="#fff"/>',
            'health'      => '<rect x="5" y="5" width="22" height="22" rx="5" fill="FG"/><rect x="14" y="9" width="4" height="14" rx="1" fill="#fff"/><rect x="9" y="14" width="14" height="4" rx="1" fill="#fff"/>',
        ][$name] ?? '<circle cx="16" cy="16" r="9" fill="FG"/>';
        return '<span class="mod-ico" style="background:' . $bg . ';width:' . $size . 'px;height:' . $size . 'px" aria-hidden="true"><svg viewBox="0 0 32 32" width="' . (int) ($size * 0.6) . '" height="' . (int) ($size * 0.6) . '">' . str_replace('FG', $fg, $g) . '</svg></span>';
    }
}
