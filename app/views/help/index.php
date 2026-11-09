<?php
// Show a section when the person has the permission (or "all" is ticked by staff for training sessions)
$show = fn(string $perm) => $all || can($perm);
$school = school_name();
?>
<div class="page-head no-print">
    <div><h1>Help &amp; training guide</h1><p class="muted">Short steps for the jobs you do in <?= e($school) ?> SMS. Print this page (Ctrl+P) to keep a paper copy.</p></div>
    <?php if (can('staff')): ?><div class="actions"><a class="btn" href="<?= e(url('help', $all ? [] : ['all' => 1])) ?>"><?= $all ? 'Show only my sections' : 'Show every role (for training)' ?></a></div><?php endif; ?>
</div>
<div class="card help" style="max-width:900px">

<h2>Signing in</h2>
<ul>
    <li><strong>Staff</strong> sign in with their email address. <strong>Parents</strong> use their phone number (e.g. 0244123456). <strong>Students</strong> use their admission number (e.g. nis/2026/0001).</li>
    <li>The first time, you must choose a new password. The rules (length, no common passwords, not your name) are shown on the form. Never share it. Use <em>My security</em> at the top to change it any time.</li>
    <li><strong>2-step sign-in</strong> (required for the Super Admin, and for any role the administrator chooses): after your password you type a 6-digit code from a free authenticator app (Google or Microsoft Authenticator) on your phone. Set it up in <em>My security</em>. Keep your printed recovery codes safe in case you lose your phone.</li>
    <li>For safety you are signed out after some minutes without activity, and some pages (Users, Roles &amp; security, Backups, Data protection) ask for your password again. <em>My security</em> shows every device signed in to your account — sign out any you do not recognise.</li>
    <li>Forgotten password? Staff: ask the Super Admin (Users &amp; roles → reset). Parents and students: ask the school office (Parent &amp; student logins → reset).</li>
    <li>The bell 🔔 at the top shows messages for you (approvals waiting, new parent messages, online payments…).</li>
    <li>On a phone, tap ☰ to open the menu. Always <em>Sign out</em> on a shared computer.</li>
</ul>

<?php if ($show('attendance.take')): ?>
<h2>Class teachers — daily register</h2>
<ol>
    <li>Dashboard → the button with your class name (or Attendance → your class).</li>
    <li>Everyone starts as Present. Tap Absent, Late or Excused for those students; add a note if you know the reason.</li>
    <li>Save before 9:00. Parents of absent students get an SMS automatically (not during Sabbath hours).</li>
</ol>
<div class="tip">Saturdays and school holidays in the Academic Calendar are closed — no register is needed.</div>
<?php endif; ?>

<?php if ($show('assignments.manage')): ?>
<h2>Teachers — assignments (homework)</h2>
<ol>
    <li>Assignments → <em>New assignment</em>: choose the class and subject, write the instructions, set the due date and (if you will score it) the mark it is out of. Attach a worksheet if you have one.</li>
    <li><em>Publish</em>: students with a login see it under <em>My homework</em> and parents see it on their child's page.</li>
    <li>Students hand in by typing or attaching a photo/PDF. For work handed in on paper, press <em>Handed in / save</em> beside the student.</li>
    <li>Type a score and feedback and press <em>Return</em> — the student and parent see it. <em>Close</em> the assignment when done: anyone who did not hand in is marked “Not handed in”.</li>
</ol>
<?php endif; ?>

<?php if ($show('leave.approve')): ?>
<h2>Student leave</h2>
<ul>
    <li>Parents ask for leave in the portal (with a doctor's note if they have one). The class teacher gets a notice and sees it under <em>Student leave</em> and on the dashboard.</li>
    <li>Approve, or decline with a short note. Approved days appear on the register as <strong>Excused</strong> (already-marked absences on those days change to Excused).</li>
    <li>A parent told you in person? Use <em>Record leave for a student</em> — it is approved at once.</li>
</ul>
<?php endif; ?>

<?php if ($show('timesheets.manage')): ?>
<h2>Staff timesheets and shifts (Head)</h2>
<ul>
    <li>Staff attendance → <em>Monthly timesheets</em> shows each person's days, hours, late arrivals and forgotten sign-outs. Open a person to correct a day (a reason is required; you cannot correct your own).</li>
    <li>Approve each month. Staff paid by the day (Payroll → staff record → Pay basis) are paid from the approved days when the Bursar prepares the payroll.</li>
    <li><em>Shifts</em>: set working hours for kitchen, cleaning or security staff; their late arrivals count from their shift start.</li>
</ul>
<?php endif; ?>

<?php if ($show('exams.enter')): ?>
<h2>Teachers — scores and report cards</h2>
<ol>
    <li>Exams &amp; Report Cards → your subject and class → the score sheet.</li>
    <li>Add the class assessments you gave (class test, project, exercise…) with their maximum marks; then enter marks. Press Enter to move down to the next student.</li>
    <li>Tick Absent for students who missed a test. When all marks are in, click <em>Submit</em>. The sheet locks and the Academic Coordinator approves it.</li>
    <li>Class teachers: Reports → Remarks for conduct, attitude, interest and your remark; the Head adds the head's remark and promotion decision in Term 3.</li>
</ol>
<?php endif; ?>

<?php if ($show('quiz.manage')): ?>
<h2>Teachers — quizzes</h2>
<ol>
    <li>Quizzes → New quiz: choose class and subject, a time limit and when it opens and closes.</li>
    <li>Add questions: multiple choice (tick the right answer), true/false, or short answer (type accepted answers separated by |, e.g. <code>Kumasi|Kumase</code>).</li>
    <li>Click <em>Publish</em>. Students with a login see it under <em>My quizzes</em>.</li>
    <li>Results shows each student's score and which questions most students got wrong. <em>Copy scores</em> puts the marks into a class assessment on your score sheet.</li>
</ol>
<?php endif; ?>

<?php if ($show('timetable.view')): ?>
<h2>Time table</h2>
<ul>
    <li><em>Time Table → My time table</em> shows your week; today's lessons are also on your dashboard.</li>
    <li><em>Find a substitute</em> lists teachers who are free in a given period.</li>
</ul>
<?php endif; ?>

<?php if (can('staff')): ?>
<h2>My profile &amp; appraisal</h2>
<ul>
    <li><em>My profile &amp; appraisal</em> shows your subjects, time table, sign-in record, your classes' results, scheme-of-work progress and invigilation duties.</li>
    <li>When the head opens the term's appraisal you get a notice. Rate yourself on each point (1 = Poor … 5 = Excellent), add a comment and click <em>Send to the head</em>.</li>
    <li>When the head completes it, read the ratings and comments and click <em>I have read this appraisal</em>.</li>
</ul>
<?php endif; ?>

<?php if ($show('teachers.view') || $show('appraisal.manage')): ?>
<h2>Teachers and appraisals (Head)</h2>
<ul>
    <li><em>Teachers</em> lists every teacher with workload, attendance, class results average, scheme coverage and appraisal status. Click a name for the full profile.</li>
    <li><em>Open appraisals for this term</em> starts everyone's appraisal. Rate each teacher after their self-assessment, write strengths, areas to improve and targets, then <em>Complete appraisal</em>.</li>
    <li><em>Appraisal criteria</em> lets you change, add or switch off the points teachers are rated on.</li>
</ul>
<?php endif; ?>

<?php if ($show('scheme.plan')): ?>
<h2>Subject Management</h2>
<ul>
    <li><strong>Scheme of work:</strong> open a subject. If it is empty, <em>Draft the scheme</em> makes one topic per curriculum sub-strand spread over the term, or copy another class's scheme. Click <em>✓ Taught today</em> as you teach each topic; red rows are behind schedule.</li>
    <li><strong>Lesson notes:</strong> pick the week and click <em>Write</em> for each subject. The note starts from that week's scheme of work topics. <em>Submit for vetting</em> when the main activities and assessment are filled in. The Head or Coordinator approves it or returns it with a comment; notes not in by their week show as <em>Late</em>.</li>
    <li><strong>Performance:</strong> average, pass rate, best and weakest class, scheme covered and lesson notes for every subject. Click a subject to see each class.</li>
    <li><strong>Textbooks &amp; materials:</strong> the approved books for each subject and level, copies held (including the library) and how many are short.</li>
    <li><strong>Curriculum strands, Subjects and Subject teachers</strong> hold the NaCCA strands, the subject list and who teaches what.</li>
</ul>
<?php endif; ?>

<?php if ($show('seating.manage') || $show('examtt.manage')): ?>
<h2>Seating plans and exam timetable</h2>
<ul>
    <li><em>Seating plans</em>: set rows and seats, choose alphabetical, boys and girls mixed, or random, then drag students to move them and <em>Save</em>. On a phone, tap a student then tap the seat.</li>
    <li><em>Exam timetable</em>: create the exam, add papers for a class or a whole level, fix any clashes shown under <em>Checks</em>, then <em>Publish</em> to pin it on the notice board.</li>
    <li>From the exam timetable, <em>New exam seating plan</em> mixes classes in a hall so no student sits beside a classmate.</li>
</ul>
<?php endif; ?>

<?php if ($show('resources.view')): ?>
<h2>Rooms &amp; resources</h2>
<ul>
    <li><em>Add a classroom for each class</em>, then open each room and <em>Add standard items</em> (desks, chairs, board, textbooks).</li>
    <li>Count what is there and enter how many are good, need repair or are broken. The shortage is worked out from the number of students.</li>
    <li><em>Print shortages</em> gives the list for the PTA, the board or the GES.</li>
</ul>
<?php endif; ?>

<?php if ($show('chat.use') && can('staff')): ?>
<h2>Parent messages</h2>
<ul>
    <li>Parent messages → New message → find the student → write. Parents who have a login read it in their portal and get an SMS telling them to look.</li>
    <li>Be polite and brief; the Head Teacher can see every conversation. For a quick message to many parents use Notifications &amp; SMS or the Notice board instead.</li>
</ul>
<?php endif; ?>

<?php if ($show('requisitions.create')): ?>
<h2>Asking for money or items (requisitions)</h2>
<ol>
    <li>Requisitions → New requisition: what it is for, the items with quantity and cost, and attach a quotation (needed above <?= e(Fees::money(setting('req_require_quote_above', '1000'))) ?>).</li>
    <li><em>Save &amp; send for approval</em>. You are told when the Head Teacher approves, returns or rejects it.</li>
    <li>After the Bursar pays you, bring the receipts and any change. The Bursar closes the requisition.</li>
</ol>
<?php endif; ?>

<?php if ($show('admission.manage')): ?>
<h2>Admissions officer</h2>
<ol>
    <li>Parents can apply online at <code><?= e(absolute_url('apply')) ?></code>, or you enter the form in Admission → New application.</li>
    <li>Move the application through the stages (screening, interview, offered…). Parents get an SMS at each important step.</li>
    <li>When the parent accepts: <em>Enrol</em> → choose the class. The student gets an admission number and the admission fee bill is raised.</li>
    <li>Parent &amp; student logins: create parent logins for a class; parents get their sign-in by SMS.</li>
</ol>
<?php endif; ?>

<?php if ($show('fees.manage')): ?>
<h2>Bursar — fees</h2>
<ol>
    <li><strong>Start of term:</strong> Fees → More ▾ → Make term bills → choose the term and classes → tick "SMS each bill". Students already billed are skipped.</li>
    <li><strong>Taking money:</strong> Fees → search the student → open the bill → Record a payment (cash, MoMo with transaction ID, bank slip or cheque) → Print receipt. The parent gets an SMS with the new balance.</li>
    <li><strong>Mistake?</strong> Only the Head Teacher can reverse a receipt (with a reason). Never record the money twice.</li>
    <li><strong>End of day:</strong> Fees → Daily cash: the cash total must equal the money in your hand. Bank it and record the transfer in Finance.</li>
    <li><strong>Who owes:</strong> Fees → Who owes → Send reminders (one SMS per family per day at most).</li>
</ol>
<h2>Families, credit notes and refunds</h2>
<ol>
    <li><strong>One payment for several children:</strong> Fees → Families → open the parent → type the amount. It is shared over the open bills, oldest first (change the amounts if the parent asks). Anything extra is kept as <em>family credit</em> and used on the next bill by itself. Print the one family receipt.</li>
    <li><strong>Reduce a bill already issued</strong> (child left, wrong charge, service not used): open the bill → Tools ▾ → Credit note. The Head approves it. If the bill was already paid, the amount becomes family credit.</li>
    <li><strong>Give money back:</strong> family account → Tools ▾ → Ask for a refund. The Head approves, then the Bursar pays it from Fees → More ▾ → Refunds &amp; credit notes (⋯ → Pay refund) and prints the voucher for the parent to sign.</li>
    <li>Nobody can approve a credit note or refund they asked for themselves.</li>
</ol>
<h2>Bursar — finance</h2>
<ol>
    <li>Fee payments appear in the cash book by themselves. Record other income and every expense with <em>+ New ▾</em>, and attach a scan or photo of the receipt.</li>
    <li>Banking the cash: <em>+ New ▾ → Transfer</em> from Cash box to the bank account.</li>
    <li>Requisitions → To pay: pay approved requests, then close them when the receipts and change come back. To pay the supplier directly, turn the requisition into a supplier bill.</li>
    <li><strong>Check the bank statement</strong> (every month): Cash book → Check bank statement → Reconcile. Tick each entry that is on the bank or MoMo statement, type the statement's closing balance, and Complete when the difference is 0. Bank charges missing from the cash book: <em>Add a missing entry</em>.</li>
</ol>
<h2>Suppliers, withholding tax, petty cash and assets</h2>
<ol>
    <li><strong>Supplier bills:</strong> Purchasing → Bills to pay → + New bill (attach the supplier's invoice). Pay it in full or in parts. Withholding tax is kept back by itself on bills above the threshold; print the payment voucher and give the supplier the WHT certificate.</li>
    <li><strong>Withholding tax to GRA:</strong> Purchasing → More ▾ → Tax kept back (WHT) → Record payment to GRA (by the 15th of the next month), and print the schedule. Put the school's GRA TIN under School setup.</li>
    <li><strong>Petty cash:</strong> the holder records every small spend as a voucher (with the receipt). When the box runs low, the Bursar checks the vouchers and tops it back up to its float; print the top-up claim.</li>
    <li><strong>Fixed assets:</strong> add desks, computers, the bus, buildings and land with their cost. The register shows their value after wear each year; sold or lost items are taken off with a reason.</li>
</ol>
<h2>Month-end and the Board finance report</h2>
<ol>
    <li>Finance → Month-end shows what is still open for last month (bank reconciliations, petty cash, overdue bills, payroll, requisitions).</li>
    <li>Close the month: after that nobody can add, change or cancel money records dated in it. Only the Head can reopen the latest closed month, with a reason.</li>
    <li>Board pack ▾ prints the month's finance report: cash and bank, income and spending against budget, fees owed, what the school owes, petty cash and assets.</li>
</ol>
<h2>Purchasing, department caps, the books and reports</h2>
<ol>
    <li><strong>Purchasing:</strong> an approved requisition becomes a <em>purchase order</em> to one supplier (Make purchase order). Requests above the quotation limits need one or three quotations attached. Mark the order as sent and print it for the supplier.</li>
    <li>When the goods arrive, the <strong>Storekeeper</strong> signs for what actually came (Goods received). The person who made the order cannot sign for it.</li>
    <li>Record the supplier's invoice on the order. The bill is checked line by line: only what was ordered, received and invoiced, at no more than the order price, can be paid. Bills above the approval limit need the Head's “Approve for payment” first.</li>
    <li><strong>Department caps</strong> (Cash book → Budget → By department): each department's spending cap for the term. A requisition that would go over what is left is stopped until the Head raises the cap or moves money from another department.</li>
    <li><strong>Ledger</strong> (the books): every money record is also kept as a double entry, by itself. Check “Do the books agree?”, the trial balance and the balance sheet. Corrections are typed as a manual journal and count only after the Head approves them.</li>
    <li><strong>Reports:</strong> cash flow, income &amp; spending, budget vs actual, fees owed by age and taxes — each prints (save as PDF) or downloads for Excel. Cash runway and the students needed to cover spending are on the Analytics Dashboard.</li>
    <li><strong>External auditor:</strong> give them a user with the role “External Auditor” and an “Access ends on” date. They can read every finance page but cannot save anything.</li>
</ol>
<?php endif; ?>

<?php if ($show('analytics.view') || $show('setup.manage')): ?>
<h2>Running the school: reports, setup and training</h2>
<ul>
    <?php if ($show('setup.manage')): ?><li><strong>Administration → Configure the school</strong> lists every setting in order (year and terms, classes, subjects, grading, fees, SMS, payments, payroll, backups) with a green/amber/red status and one-click defaults. The dashboard shows how far you are.</li><?php endif; ?>
    <?php if ($show('staff')): ?><li><strong>Analytics Dashboard</strong>: one page for every staff member. Six headline figures (students on roll, attendance today, fees collected, cash &amp; runway, school average, what needs your attention), a <em>Today</em> strip and panels for attendance, fee collection, academic performance, admissions, budget against spending, and compliance &amp; welfare. You only see the cards your role allows (teachers see their own classes); switched-off modules disappear. Figures refresh by themselves every minute. Choose another term at the top; every figure opens its page, and Print / Excel under a panel gives its full table.</li><?php endif; ?>
    <?php if ($show('analytics.view')): ?><li><strong>School census preparation</strong>: at the foot of the Dashboard, <em>Printable school reports → School census preparation</em> summarizes enrolment, repeaters, ages, learners with special needs, and staff qualification and NTC licence details. It is a local reporting aid, not a validated GES/EMIS return; confirm the current official template and instructions before submission.</li><?php endif; ?>
    <?php if ($show('setup.manage')): ?><li><strong>School setup → Data protection</strong>: enter the Data Protection Commission number, edit the privacy notice parents see, and set how many years personal details are kept after a student leaves. The Super Admin can then anonymise former students past that period.</li><?php endif; ?>
    <?php if ($show('payroll.manage')): ?><li><strong>NTC licences</strong>: record each teacher's gender, Ghana Card, highest qualification, NTC licence number and expiry in Payroll → Staff records. Teachers get an SMS 60 and 14 days before expiry and the dashboard lists missing or expiring licences.</li><?php endif; ?>
    <li><strong>Students</strong>: choose a class, then print the <em>Class list</em>, a <em>Blank mark sheet</em> or <em>ID cards</em>. On a student's page: ID card and letters (confirmation, transfer, testimonial). <strong>Attendance → a class → Monthly register</strong> prints the month's register.</li>
    <?php if ($show('users.manage')): ?><li><strong>Administration → Practice copy</strong> builds a separate training copy at <code>/demo</code> full of made-up students, fees and scores. Staff practise there; nothing reaches real records and no SMS are sent. Reset it before each training session.</li><?php endif; ?>
</ul>
<?php endif; ?>

<?php if ($show('payroll.view')): ?>
<h2>Payroll (Bursar prepares, Head approves)</h2>
<ol>
    <li>Payroll → Staff records: add every paid person once, with basic salary, bank or MoMo details, SSNIT number, and standing allowances and deductions.</li>
    <li>Each month: Payroll → choose the month → <em>Prepare payroll</em>. Check the figures; fix staff records and press <em>Recalculate</em> if needed.</li>
    <li>The Head opens the payroll and presses <em>Approve</em> (the preparer cannot approve their own payroll).</li>
    <li>Pay the staff using the <em>Bank / MoMo schedule</em>, then press <em>Mark as paid</em> — net salaries go into the cash book. Print payslips for each staff member.</li>
    <li>When you pay SSNIT (by the 14th) and PAYE (by the 15th), press <em>Record SSNIT &amp; PAYE payment</em>. Confirm the PAYE bands with GRA whenever the budget changes them.</li>
</ol>
<?php endif; ?>

<?php if ($show('library.manage') || $show('health.manage') || $show('transport.manage')): ?>
<h2>Library, sick bay and school bus</h2>
<ul>
    <?php if ($show('library.manage')): ?><li><strong>Library</strong>: add books in the Catalogue; to lend, type the book number and the student's admission number. Press <em>Returned</em> when it comes back. A student with an overdue book cannot borrow another.</li><?php endif; ?>
    <?php if ($show('health.manage')): ?><li><strong>Health &amp; sick bay</strong>: record every visit with the admission number. When a child is sent home or referred, the parent gets an SMS automatically. Keep allergies, conditions and NHIS details on each child's Health record.</li><?php endif; ?>
    <?php if ($show('transport.manage')): ?><li><strong>Transport</strong>: create each bus route, add riders by admission number, print the bus list for the driver. The Bursar presses <em>Bill bus fees</em> each term.</li><?php endif; ?>
</ul>
<?php endif; ?>

<?php if ($show('bece.manage')): ?>
<h2>BECE (JHS 3)</h2>
<ul>
    <li>BECE → Registration lists JHS 3 students and flags missing photos, Ghana Card numbers and unlikely dates of birth. Correct them on the student record.</li>
    <li>Export the CSV and the photos zip for WAEC registration; type index numbers in once WAEC issues them.</li>
    <li>When results come out, enter the grades under BECE → Results; the aggregate (best 6) is worked out for you.</li>
</ul>
<?php endif; ?>

<?php if ($show('setup.manage')): ?>
<h2>Head Teacher</h2>
<ul>
    <li><strong>Approvals:</strong> the dashboard shows requisitions waiting; open each, check the budget box, then approve, reduce, return or reject.</li>
    <li><strong>Report cards:</strong> Reports → class → broadsheet → remarks → <em>Release</em>. Parents get an SMS link and see the card in their portal.</li>
    <li><strong>Notice board:</strong> New notice → choose who sees it; tick "confirm read" for circulars, and "SMS alert" for important ones.</li>
    <li><strong>Receipts:</strong> only you can reverse a fee receipt or void a cash book entry — always with a reason (it is recorded in the audit log).</li>
    <li><strong>End of year:</strong> create next year in School setup, then Administration → Year-end promotion, class by class from JHS 3 down; then make the new year current.</li>
    <li><strong>Go-live checklist</strong> (Administration) shows what is still missing before full use.</li>
</ul>
<?php endif; ?>

<?php if ($show('users.manage')): ?>
<h2>Super Admin</h2>
<ul>
    <li><strong>Users:</strong> add staff, choose their role, reset passwords, switch off staff who leave (they are signed out at once), reset someone's 2-step sign-in if they lose their phone.</li>
    <li><strong>Roles &amp; security</strong> (least privilege): give each role only what the job needs. Edit a role or use the <em>Permission matrix</em>; create custom roles such as Deputy Head, Librarian or School Nurse; choose "own classes" so teachers see only their students; require 2-step sign-in, an automatic sign-out time, school hours or the school network per role. <em>Sign-in rules</em> sets password and session rules for everyone. Check the <em>Access review</em> each term.</li>
    <li>Lost your own phone? On the school computer run <code>bin\reset-admin.php</code>: it resets the password and can switch off 2-step sign-in. Keep at least one other Super Admin or your recovery codes.</li>
    <li><strong>Backups:</strong> one is made every evening if the scheduled task runs. Download one each week and keep it off the computer. Practise a restore on a spare computer once a term.</li>
    <li><strong>Updates:</strong> copy the new files over the old ones, sign in, and click <em>Update database now</em> in the yellow banner.</li>
    <li><strong>Import from Excel:</strong> students and parents, opening fee balances and staff accounts — download the template, fill it in, upload and check before importing.</li>
</ul>
<?php endif; ?>

<?php if ($all || Auth::role() === 'parent'): ?>
<h2>Parents</h2>
<ul>
    <li><strong>My children</strong> shows attendance this term, fees owed, report cards and the class time table for each child.</li>
    <li><strong>Events</strong>: register your children for sports days, trips and meetings. <strong>Appointments</strong>: book a time with the class teacher, the Head or the accounts office.</li>
    <li>Your child's page also lists <strong>homework</strong> with the teacher's marks. To keep your child home, press <strong>Ask for leave</strong> — the class teacher approves and the days are not counted as absences.</li>
    <li><strong>View bill &amp; pay</strong> opens your child's bill. Pay at the accounts office, by MoMo to the school number<?= PaymentGateway::active() !== 'none' ? ', or online with Mobile Money' : '' ?>. You always get an SMS receipt.</li>
    <li>If you pay more than you owe, the extra is kept as <strong>family credit</strong> and used on your next bill by itself — your child's page shows it, with any bill reductions and refunds.</li>
    <li><strong>Messages</strong> → Write to the school: choose your child and the teacher, the Head Teacher or the Bursar.</li>
    <li><strong>Notice board</strong>: school news and circulars. When asked, tap "I have read and understood this".</li>
</ul>
<?php endif; ?>

<?php if ($all || Auth::role() === 'pupil'): ?>
<h2>Students</h2>
<ul>
    <li><strong>My homework</strong> lists the work your teachers set. Open one, type your answer or take a photo of your work, and press <em>Hand in</em>. You can change it until your teacher marks it.</li>
    <li><strong>My quizzes</strong> lists the quizzes your teachers have set. Press <em>Start quiz</em> only when you are ready — the timer starts at once.</li>
    <li>Your answers are saved as you go. When time is up the quiz is handed in by itself.</li>
    <li>Your score shows at once; the correct answers appear when your teacher allows.</li>
</ul>
<?php endif; ?>

<?php if ($show('canteen.sell')): ?>
<h2>Canteen</h2>
<ul>
    <li><strong>Counter:</strong> find the student by name or admission number → <em>Sell</em>, enter quantities, choose Wallet, Cash or MoMo. For visitors use <em>Cash sale (no student)</em>.</li>
    <li>A red <strong>ALLERGY WARNING</strong> stops a sale that contains something the child is allergic to (from the student's health details). Sell only if the parent has agreed, and tick the box.</li>
    <li><strong>Wallets:</strong> parents pay money in at the office (cash or MoMo) — press <em>Top up</em>. The money goes into the cash book; purchases then come off the wallet. Parents can ask for a daily limit.</li>
    <li><strong>Meal plans</strong> (Bursar): put students on a termly plan class by class, then <em>Bill</em> — each family gets a meal-plan bill with their fees. <em>Meals served</em> records who ate.</li>
</ul>
<?php endif; ?>

<?php if ($show('events.manage')): ?>
<h2>Events</h2>
<ul>
    <li>Events → <em>New event</em>: date, venue, who it is for, places and a fee (if any). Publishing puts it on the Academic Calendar.</li>
    <li>Parents register their children in the portal; a fee becomes a bill they pay like school fees. When full, families go on a waiting list and move up automatically if someone cancels.</li>
    <li>On the day, press <em>Check in</em> beside each child; download the list as CSV. Cancelling an event cancels unpaid event bills and tells every family.</li>
</ul>
<?php endif; ?>

<?php if ($show('appointments.offer')): ?>
<h2>Appointments with parents</h2>
<ul>
    <li>Appointments → <em>My availability</em>: add the weekly times parents may book (e.g. Tuesday 14:30–15:30, “Meet the class teacher”). Closed days are skipped.</li>
    <li>You get a notice for each booking with what the parent wants to discuss. Cancel with a short reason if you must; afterwards mark it <em>Held</em> or <em>Did not come</em>.</li>
</ul>
<?php endif; ?>

<h2>Phone app and phone notifications</h2>
<ul>
    <li>On the live https address, open the system in Chrome (Android) → menu ⋮ → <em>Install app</em>; on iPhone (Safari) → Share → <em>Add to Home Screen</em>.</li>
    <li>Then open the bell 🔔 → <em>Turn on phone notifications</em>. Approvals, homework, leave answers, fees and messages then arrive as phone notifications.</li>
</ul>

<?php if ($show('admission.manage')): ?>
<h2>Admission List</h2>
<p>Admission → <em>Admission List</em>: choose the level, type each applicant's entrance assessment and interview scores (0–100) and press <em>Save scores</em>. Applicants are ranked (entrance 70%, interview 30%). Tick the best and press <em>Offer places</em> — parents get the offer SMS. The page shows how many places are still free.</p>
<?php endif; ?>

<?php if ($show('fees.manage')): ?>
<h2>Fee payment plans</h2>
<p>Open a bill → <em>Agree a payment plan with the parent</em>: split what is owed into 2–6 dated instalments. The parent sees the plan on the bill link and gets an SMS 3 days before each date; the dashboard warns when a family falls behind.</p>
<?php endif; ?>

<?php if ($show('messages.manage')): ?>
<h2>WhatsApp</h2>
<p>Notifications &amp; SMS → Settings → <em>WhatsApp</em>: choose the provider (Meta Cloud API, Twilio or Hubtel) once the school has a WhatsApp Business account, and how automatic messages go out (WhatsApp and SMS, or WhatsApp first with SMS if it fails). Try <em>Test mode</em> first. The outbox shows WhatsApp messages and when they were read.</p>
<?php endif; ?>

<h2>Export and import</h2>
<p>Every <em>Export ▾</em> button offers <strong>Excel (.xlsx)</strong>, <strong>Word (.docx)</strong>, <strong>PDF</strong> and <strong>CSV</strong> (for other systems such as the bank or GES). Lists you can filter (Students, Users, Families …) export exactly the rows and columns on the screen. <em>Import from Excel</em> also accepts a Word document: the first table is read, with the headings in its first row.</p>

<h2>NISDAS APPS</h2>
<p>The menu bar at the top has <em>Dashboard</em>, <em>NISDAS APPS</em> and <em>Administration</em>. Click <em>NISDAS APPS</em> to see every part of the system as a big icon; all the apps share the same records, so a student added in SIS is the same student in Fees, Attendance and Gradebook. You only see the icons you are allowed to open. <em>Administration</em> holds School setup, Users, Roles &amp; security, logins, backups and the audit log. <em>View All</em> shows the full list of features with a “Learn more” page for each.</p>

<?php if ($show('documents.view')): ?>
<h2>Documents</h2>
<p><em>Documents</em> is the school library of policies, circulars, GES letters, minutes and licences (some can be “management only”). Student files (birth certificate, NHIS card…) are on each student's record → <em>Files</em>; staff files (certificates, NTC licence, contract) are kept by the Head and Bursar, and each staff member sees their own under <em>My files</em>. You are reminded 30 days before anything expires.</p>
<?php endif; ?>

<?php if ($show('lms.manage') || $show('lms.view') || $show('portal.parent')): ?>
<h2>LMS and live classes</h2>
<p><em>LMS — learning materials</em>: teachers post lesson notes, YouTube videos, links and worksheets for a class or subject; students and parents are told and can read them at home, and the teacher sees who opened each one. <em>Live classes</em>: create the meeting in Zoom, Google Meet or Teams, paste its link, and students and parents get a reminder 30 minutes before; the Join button opens 15 minutes before the start and the system records who joined.</p>
<?php endif; ?>

<?php if ($show('crm.manage') || $show('marketing.send')): ?>
<h2>CRM (enquiries) and marketing</h2>
<p><em>CRM — enquiries</em>: record every parent who calls or visits (or uses the website enquiry form at <?= e(absolute_url('enquire')) ?>), set a follow-up date, log calls and visits, and send the online application link by SMS. When the parent applies with the same phone number, the enquiry moves to “Applied” by itself. <em>Marketing campaigns</em>: choose who (open enquiries, past or current parents, or typed numbers), check how many people, then send; numbers on the do-not-message list are always left out.</p>
<?php endif; ?>

<?php if ($show('shop.manage') || $show('shop.order')): ?>
<h2>School shop (E-Commerce)</h2>
<p>Parents open <em>School shop</em>, add uniforms, books or stationery to the basket, choose the child and pay online with Mobile Money. The Bursar sees paid orders under <em>School shop</em>, marks them ready (the parent gets an SMS) and records who collected them. Unpaid orders are cancelled after 3 days and the stock comes back. Counter sales for cash or MoMo give a receipt at once; shop money appears in the cash book under “School shop”.</p>
<?php endif; ?>

<h2>Getting help</h2>
<p>Something not working? Note what you clicked and what you saw (a photo of the screen helps) and tell the <?= can('staff') ? 'Head Teacher or the system administrator' : 'school office' ?>. <?= setting('school_phone') ? 'School office: ' . e(setting('school_phone')) . '.' : '' ?></p>
</div>
