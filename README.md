# NISDAS School Management System

PHP 8 + MySQL (MariaDB) web application for NISDAS, a Seventh-day Adventist basic school in Ghana (Creche to JHS 3). No framework or Composer packages are needed; it runs on a standard XAMPP install.

## Sprint 1 — what's included

| Area | Features |
| --- | --- |
| Foundation | Login with lockout after 5 failed tries, 9 roles, forced password change for new accounts, CSRF protection, audit log |
| School setup | School details, academic years with 3 terms, set the current term, classes/streams with class teachers, GES subjects |
| Users | Create staff accounts with temporary passwords, change roles, deactivate, reset passwords |
| Academic Calendar | Month and list views, 9 event types, Sabbath (Saturday) warning, "school closed" days, export to Google Calendar or a phone (.ics) |
| Admission | Public online form for parents (with document upload), walk-in applications, stage pipeline (Submitted → Screening → Interview → Offered → Accepted → Enrolled), entrance score, application status check for parents |
| Pupils | Enrolment creates the pupil, an admission number (e.g. `NIS/2026/0001`) and the class placement. Includes a pupil profile with photo, guardians, sibling detection by guardian phone, class history and withdrawal/transfer status |

## Sprint 2 — what's included

| Area | Features |
| --- | --- |
| Attendance | Daily class register (Present / Late / Absent / Excused / Sick). It is blocked on Saturdays (Sabbath), Sundays, school-closed calendar days and vacations. Class teachers see only their own class. Includes an attendance overview per day, a term report per class (daily grid, %, CSV download), absentee lists, pupils below 80%, and an attendance summary on each pupil's profile |
| Staff attendance | Sign-in / sign-out from the dashboard; the Head Teacher sees the daily staff list |
| Notifications & SMS | SMS gateway (test mode, Hubtel, Arkesel, mNotify) and an outbox with retries. Messages can go to all parents, a department, a class, staff or typed numbers, with a preview, cost estimate and scheduling. Includes editable SMS templates, in-app notifications (bell icon) and Sabbath quiet hours (Friday 18:00 – Saturday 18:30) |
| Automatic SMS | Absence (and optionally lateness) alerts to parents, plus admission messages (received, interview, offer, not admitted, enrolled with admission number) and calendar event reminders N days before |
| Form Builder | Forms for parents (public link) or staff (sign-in). Field types: text, paragraph, number, email, phone, date, dropdown, multiple choice, checkboxes, file upload, section heading. Includes reorder/remove (answers kept), open/close dates, one response per phone, response tallies, CSV export, duplicate, send the link by SMS, and starter templates (consent, survey, RSVP) |

## Sprint 3 — what's included

| Area | Features |
| --- | --- |
| Grading setup | One scheme per department (Creche, KG, Basic 1–6, JHS) with class score / exam weights (default 30 / 70), pass mark, position on/off and editable grade bands. Defaults: NaCCA proficiency levels (A Advanced 80+, P Proficient 75+, AP Approaching Proficiency 70+, D Developing 65+, B Beginning) for Basic/KG/Creche, and BECE-style grades 1–9 for JHS with an aggregate of the best six (4 core + 2) |
| Subjects & teachers | Subjects per class are created automatically from each department's subject list, and a subject teacher can be assigned to each. Class teachers can enter every subject of their own class |
| Score sheets | Score grid per subject and term, with several class items (exercises, tests, projects) plus the exam, converted automatically to the weights. Type “a” for absent, and Enter moves down like Excel. You can paste scores from Excel by admission number. Totals, grades and subject positions are computed instantly |
| Approval workflow | The teacher submits (the sheet locks). The Academic Coordinator approves, or returns it with a note (the teacher is notified), and can re-open an approved sheet |
| Broadsheet | Class matrix of totals and grades, overall total, average, position and JHS aggregate, plus subject analysis (average, highest, lowest, pass rate) and CSV download |
| Remarks | The class teacher records conduct, attitude, interest and a remark (with suggestions), plus promotion in Term 3. The Head Teacher adds their own remark |
| Terminal report cards | A4 report card per pupil showing school details, attendance (from the registers), vacation date, next-term reopening date, class/exam/total, grade, position, remark, aggregate, remarks, signatures and the grading key. Print the whole class or save as PDF |
| Release to parents | Release per class, with a check that all subjects are approved. Parents can get an SMS with their child's average, position and a private link to view the report card on their phone; withdrawing the release disables the links |

## Sprint 4 — what's included

| Area | Features |
| --- | --- |
| Bell schedule | One schedule per department, editable. Basic/JHS: worship & assembly 07:30, 8 lessons of 35 minutes, break and lunch. Creche/KG: 6 lessons of 30 minutes, snack break and lunch & rest |
| Lessons per week | Default periods per subject for each class (they fit within the available periods), editable per class and copyable to other streams of the same level |
| Automatic time table | Generate for one, several or all classes, keeping hand-placed lessons or starting fresh. No teacher is ever booked in two places at once, even when KG and JHS bell times differ. Subjects are spread over the week, and English, Maths, Science, Social Studies, Literacy and Numeracy go in the morning |
| Manual editing | Grid editor per class with rooms. A lesson is refused if its teacher is teaching elsewhere at that time, and hand-placed lessons are kept when you generate again |
| Views | Class time table, each teacher's week, “My time table”, and today's lessons on the teacher's dashboard. Print or save as PDF, one class or all classes |
| Check | Teacher clash list (e.g. after changing a subject teacher), subjects with too few or too many lessons, lessons without a teacher, and teacher workload |
| Find a substitute | Pick a day and period to see which teachers are free, least busy first |
| School logo | Upload a crest in School setup. It appears on the top menu bar, sign-in page, online forms, report cards and printed time tables |

The time table is one weekly timetable that applies until you change it. Changing a subject teacher automatically updates the time table, and the Check page then shows any new clashes.

## Sprint 5 — what's included

| Area | Features |
| --- | --- |
| Fee schedule | Amount per class level for each fee item (Tuition, PTA dues, Feeding, ICT, Examination fee, Books & stationery, Admission fee). Items are charged every term, once a year (Term 1) or on admission only. You can add your own items |
| Discounts | Sibling discount applied automatically from the 2nd child of the same parent, plus Staff child, Scholarship or your own types (percentage or fixed). Only *discountable* items (tuition) are reduced, and the larger discount wins |
| Term bills | Generate bills for one, several or all classes in one click. Pupils already billed are skipped, so it is safe to run again after new admissions. Optional SMS to each parent with a link to view and pay. The admission fee bill is raised automatically on enrolment |
| Payments & receipts | Record cash, direct MoMo, bank deposit or cheque (transaction ID required for MoMo/bank/cheque). Prints an official receipt and SMSes the parent with the new balance. Only the Head Teacher can reverse a receipt, and must give a reason |
| Bills | Add or remove lines, one-off bills (excursion, uniform, arrears brought forward), cancel a bill with a reason (only before any payment), print the bill with "how to pay" details |
| Statements | Full account history per pupil with a running balance, printable. Balance also shows on the pupil profile and (optional) as "Arrears" on report cards |
| Reports | Debtors list with CSV download and one-click reminder SMS, collection summary per term (by class, method and item), daily cash report by cashier (printable), dashboard tile |
| Online payment | Parents open the link in their SMS and pay by MTN MoMo, Telecel Cash, AirtelTigo Money or card through **Paystack** or **Hubtel**. The payment is credited and receipted automatically, but only after the system confirms it directly with the gateway. **Test mode** gives a simulated payment page for training |

The fee amounts loaded at installation are **sample figures**. Enter the school's approved fees in **Fees → Fee schedule** and tick "These are the approved amounts" before generating bills.

Roles: Bursar and Head Teacher manage fees. The Admissions Officer can view fees. Only the Head Teacher (or Super Admin) can reverse a payment.

## Sprint 6 — what's included

| Area | Features |
| --- | --- |
| Money accounts | Cash box, school bank account, school MoMo wallet and online collections (add your own). Enter each one's opening balance on the day you start |
| Cash book | Record income (donations, canteen, grants…), expenses (utilities, repairs, feeding, salaries…) and transfers (e.g. banking the cash), with an optional scanned receipt. Running balance per account, printable. Entries are never deleted: the Head Teacher can *void* a wrong entry with a reason |
| Fees feed Finance | Every fee receipt goes into the cash book automatically, into the account that matches how it was paid (cash, MoMo, bank, online). Reversing a receipt in Fees voids its cash book entry |
| Reports | Income & expenditure statement for a term or any dates (surplus/deficit), transactions list with CSV download, 6-month trend, money held in each account |
| Budget | Yearly amount per category set by the Head Teacher, compared with actual spending plus approved requisitions not yet paid. Warnings when a category reaches 90% |
| Requisitions | Any staff member can request money or items (items, quantities, costs, quotation). The Head Teacher approves, reduces, returns or rejects. Above GH₵ 5,000 the School Manager (Super Admin account) must also approve. The Bursar pays out, which records the expense, and then closes it when receipts and any change come back. Full history, printable form, in-app notifications |

Controls built in: nobody can approve or pay out their own requisition. The second approval must come from a different person. Quotations are required above GH₵ 1,000. Both amounts can be changed in **Finance → Accounts & categories**. A Head Teacher's own requisitions are approved by the School Manager (Super Admin).

## Sprint 7 — what's included

| Area | Features |
| --- | --- |
| Parent & pupil logins | **Administration → Parent & pupil logins.** Parents sign in with their **phone number**, pupils with their **admission number**. Logins are created for a whole class at once. Parents get their details by SMS; for pupils you print sign-in slips (passwords are shown only once). Everyone chooses their own password at first sign-in. A login can be disabled, or reset with a new temporary password |
| Parent portal | "My children": each child's attendance this term, fees owed with the pay link, report cards (once released), absences, payments, class time table, quiz scores, plus the notice board and school calendar |
| Parent messages | Private messages between parents and the class teacher, subject teachers, the Head Teacher or the Bursar. When staff reply, the parent gets an SMS nudge (at most one an hour). The Head Teacher can read any conversation (safeguarding); this is recorded in the audit log |
| Notice board | Notices and circulars for everyone, staff only, all parents, a department or one class. They can be scheduled and expire automatically, and can carry a PDF/photo. Parents can be asked to confirm they read a circular, and you can see who has. An optional one-off SMS alert with a link goes to each family |
| Quizzes | Teachers set multiple-choice, true/false and short-answer quizzes for their own classes. Options: opening and closing times, time limit, number of attempts, shuffled questions, and when answers are shown. Pupils' answers are saved every 20 seconds and the quiz submits itself when time is up. Marking is automatic, and correcting a question re-marks everyone. Results include a per-question analysis and CSV download, and can be copied (scaled) into a class assessment on the score sheet |
| Year-end promotion | **Administration → Year-end promotion.** Class by class, it suggests promoting each pupil (or repeating, if the report card says so). JHS 3 is marked as completed. You can change each pupil's next class, or mark them as leaving. Pupils are enrolled into next year's classes, and a class can be undone until the new year is made current |

How to do the year-end move: create next academic year and its terms in School setup, but keep this year current. Run the promotion from JHS 3 down to Creche. Then make the new year current and generate Term 1 bills.

## Sprint 8 — getting ready to go live

| Area | Features |
| --- | --- |
| Backups | **Administration → Backups** (Super Admin). One file holds the whole database plus all uploaded documents and photos. A backup is made automatically every evening by the scheduled task, and the last 14 are kept. Each one can also be copied to a USB drive or Google Drive folder. You can also make a backup by hand and download it. Restoring is done with `bin\restore.php`, which makes a safety backup first. The backup's `database.sql` can also be imported with phpMyAdmin |
| Import from Excel | **Administration → Import from Excel.** Three imports: pupils with their parents (enrolled in the current year, siblings linked by phone number), opening fee balances (arrears brought forward), and staff accounts (printable sign-in slips). Download the template, fill it in Excel, upload the .xlsx or .csv, and every row is checked before anything is saved |
| Go-live checklist | **Administration → Go-live checklist** (Head Teacher). Automatic checks of the computer, internet access, school data, fees and SMS (green / amber / red, each with a link to fix it). School decisions to confirm. A 22-task trial-run script for the Head, Bursar, teachers, a test parent and the office, with ticks, notes and a printable sign-off sheet |
| Help | The **Help** button at the top: a short guide for each role (sign-in, register, scores, quizzes, requisitions, fees, finance, approvals, backups, parents, pupils). It can be printed, and staff can show every role for training sessions |
| Production settings | `force_https` (sends everyone to https:// and adds HSTS) and `trust_proxy` for hosting behind a proxy. All SMS links (bills, report cards, forms, notices, logins) use the **Public web address**. The installer works on hosting accounts where the database is created in cPanel first |

## Sprint 9 — payroll, library, transport, health, BECE

| Area | Features |
| --- | --- |
| Payroll | **Payroll** (Bursar prepares and pays, Head approves; someone other than the preparer must approve). Staff records for everyone the school pays, including non-teaching staff: basic salary, bank or MoMo details, SSNIT number and TIN, and standing allowances (taxable or tax-free) and deductions. Each month: prepare, check, approve, pay. SSNIT is worked out on basic salary (5.5% staff share and 13% school share). PAYE uses the GRA monthly bands kept in settings. The pages show printable payslips, a bank/MoMo payment schedule (with CSV) and an SSNIT & PAYE schedule. Net salaries and later the SSNIT/PAYE payment are posted to the cash book automatically. An optional "salary paid" SMS can be sent. Staff with a linked login see **My payslips** |
| Library | Catalogue (copies, shelf, category, level), lending to pupils (by admission number) and staff, returns, renewals and lost copies. A loan limit applies per pupil, and a pupil with an overdue book cannot borrow another. There is a printable overdue list and an SMS reminder to parents (at most weekly per book) |
| Transport | Bus routes (stops, vehicle, driver/attendant, seats, fee per term). Riders are added by admission number, with their stop and morning/afternoon trips. A printable bus list with parents' phones and AM/PM tick boxes is available. **Bill bus fees** adds a "Bus fees" bill per rider per term, which parents can pay like other bills |
| Health & sick bay | Each pupil's health record holds blood group, genotype, NHIS number and expiry, allergies, conditions, medication, clinic, emergency contact and immunisations. Sick bay visits record the complaint, temperature, action taken and outcome. The parent gets an SMS automatically when a child is sent home or referred, or when staff tick "inform parent". Printable class health registers and NHIS renewal lists are available. Class teachers see only their own class |
| BECE | JHS 3 candidates are checked for a missing photo, Ghana Card (NIA) number, an unlikely date of birth or unusual characters. You can export the candidate CSV and a photos zip for WAEC registration, keep index numbers, and enter results with the aggregate worked out automatically (4 core subjects plus the best 2 others) |

**After updating**, sign in as Super Admin and click **Update database now** (migration 009).

**Confirm with the school:**

- PAYE bands with GRA (Payroll → Tax & SSNIT rates)
- Each staff member's salary and allowances
- Loan period and limit (settings `library_loan_days`, `library_max_loans`)
- Bus fees per route

## Sprint 10 — configure, report, train

| Area | Features |
| --- | --- |
| Configure the school | **Administration → Configure the school**. Every setting is listed in order: school details, public address, year and terms, classes and class teachers, subjects, subject teachers, grading, time table, staff, pupils, parent logins, fee schedule, term bills, opening balances, staff pay records, SMS, online payment, backups, scheduled task and trial run. Each shows a status and an Open button. Some steps have one-click defaults: create the academic year with typical GES term dates, give every class the standard subjects, make class teachers the subject teachers in Creche–Basic 6, confirm grading, and create pay records from staff accounts. The dashboard shows setup progress until everything is done |
| Activating SMS & payments | **Messages → Settings → Check account** confirms the SMS key and shows the credit left (Arkesel, mNotify). **Fees → Settings → Test connection** confirms the Paystack or Hubtel keys without moving money. You type the keys yourself |
| Dashboard | A role-aware **Needs your attention** list: score sheets to approve, new applications, payroll to approve or pay, SSNIT/PAYE to record, overdue bills, unread parent messages, overdue library books, sick-bay visits today, failed SMS, pupils without a parent phone, and a stale backup. It also shows an attendance-rate trend (last 15 school days against a 90% target) and fees received per week this term |
| School reports | **School reports** (Head, Coordinator, Bursar). For any term: enrolment by class (boys/girls, capacity, new admissions, SDA, ages), attendance by class plus pupils below 85%, academic performance (class averages, pass rates, subject averages across the school, top 10 pupils, score-sheet progress), and fee collection by class (billed/paid/outstanding, % collected, arrears, payment methods). Charts, Print and CSV |
| Pupil documents | Class list, a blank mark sheet (class list with empty columns), pupil ID cards (8 per A4, credit-card size, with photo), and letters (confirmation of studentship, transfer, testimonial) on the school letterhead. A school-services summary on the pupil page shows bus, library books, sick bay and BECE |
| Attendance | A printable monthly register for each class: pupils × school days with P/L/A/E/S marks and totals |
| Payroll | **Year summary**: totals per staff member for the year (gross, SSNIT both shares, PAYE, net), with print and CSV, for annual returns |
| Practice copy | **Administration → Practice copy** builds a training copy at **http://localhost/NISDASSMS/demo/** in its own database (`nisdas_sms_demo`). It has about 140 made-up pupils, 23 staff records, term bills and receipts, attendance, scores, library, bus, health and a paid payroll. The fixture provides at least 15 administrator, teacher, parent and student logins; the Practice copy page lists their sign-in names. Every practice login uses the password `DemoPass@2026`. SMS are never sent and online payment is always in test mode. Reset it before each training session, and remove it when done. The command `php bin\demo.php` does the same from the command line. On hosting where the system cannot create databases, first create an empty database named `<your database>_demo` in cPanel. Set `'practice_copy' => false` in config.local.php to switch the practice copy off |

## Going live

Work through **Administration → Go-live checklist** until it shows no red items. The steps below explain each part.

### Option A — on a computer in the school (XAMPP)

Best when the internet is unreliable. Staff use it on the school network. Parents can only reach it from home if you add a public address (Option B, or a router port-forward with HTTPS).

1. Use a dedicated computer that stays on during school hours, with a UPS (power cuts corrupt databases).
2. Install XAMPP for Windows (PHP 8.1 or newer). In the XAMPP Control Panel, tick **Service** for Apache and MySQL so they start with Windows.
3. Secure MySQL: in phpMyAdmin give the `root` user a password, then put it in `config\config.local.php` (copy `config.local.example.php`). Keep `'debug' => false`.
4. Give the computer a fixed IP address on the network (e.g. 192.168.1.10) and allow Apache through Windows Firewall (private networks only). Staff then open `http://192.168.1.10/NISDASSMS/`.
5. Set up the scheduled task (Windows Task Scheduler → Create Task):
   - Run it every 10 minutes, whether the user is signed in or not.
   - Program: `C:\xampp\php\php.exe`. Arguments: `C:\xampp\htdocs\NISDASSMS\bin\cron.php`.
   - It sends SMS and reminders, re-checks online payments and makes the evening backup.
6. Backups: in **Backups → Settings** set a copy folder on a USB drive or inside a Google Drive / OneDrive folder. Each week, take a backup home or keep it in a safe place.

### Option B — on internet hosting (recommended for parents and online payment)

Any Ghana or international host with **PHP 8.1+, MySQL/MariaDB, cron jobs and free SSL (Let's Encrypt)** works, e.g. a cPanel shared-hosting plan.

1. Buy or point a domain, e.g. `sms.nisdas.edu.gh` (a subdomain of the school website is fine). Turn on SSL in cPanel (AutoSSL / Let's Encrypt).
2. In cPanel → MySQL Databases, create a database and a user with **All privileges** on it.
3. Upload this folder (zip it, upload with File Manager, extract) into the domain's folder, e.g. `public_html/sms`.
4. Create `config/config.local.php` with the database name, user and password from step 2, and `'debug' => false`.
5. Open `https://sms.nisdas.edu.gh/install.php` and install (or restore your school-PC backup with `php bin/restore.php` over SSH, then sign in).
6. Turn on HTTPS everywhere: add `'force_https' => true` to `config.local.php`, and set **Fees → Settings → Public web address** to `https://sms.nisdas.edu.gh`.
7. cPanel → Cron Jobs: every 10 minutes run `/usr/local/bin/php /home/USER/public_html/sms/bin/cron.php`. Your host can confirm the PHP path.
8. Check that `https://sms.nisdas.edu.gh/config/config.php` and `/storage/` show "Forbidden". The included `.htaccess` files block them. If they do not, ask the host to allow `.htaccess` (AllowOverride All).
9. Download a backup every week to a computer in the school as well. The host's own backups are not enough.

### Moving existing records in

1. School setup: years and terms, classes with class teachers, subjects and teacher allocations.
2. **Import from Excel:**
   - First staff (Super Admin), then pupils and parents.
   - Then opening fee balances (only arrears from before; current-term fees are billed with Generate term bills).
3. Finance → Accounts & categories: enter the cash box, bank and MoMo balances on the start date.
4. Parent & pupil logins: create parent logins class by class (they arrive by SMS).

### Trial run and launch

1. Do the trial-run tasks on the Go-live checklist with the Head Teacher and Bursar, with real classes but test money (Test mode for online payment).
2. Remove the practice data before real use. Either reinstall (drop the database and run the installer, then import again), or cancel the test bills and reverse test receipts so they are clearly marked.
3. Tick the school decisions, print the checklist and sign it.
4. Switch SMS from test mode to the real provider, and online payment from Test to Paystack/Hubtel (or Off).

## Setting up online fee payment (Mobile Money)

Online payment only works when the system is **hosted on the internet with HTTPS**. A laptop running XAMPP cannot receive confirmations from Paystack or Hubtel. Until then, use **Test mode** for training, and parents pay at the office or by direct MoMo.

1. Choose a gateway and open a business account:
   - **Paystack:** paystack.com. You need business registration documents. MoMo and cards are supported.
   - **Hubtel:** hubtel.com, Online Checkout. You get an API ID, API key and merchant account number.
2. In **Fees → Settings**, enter the **Public web address** (for example `https://sms.nisdas.edu.gh`), choose the gateway and paste the keys. Secret keys are never shown again; leave the box blank to keep the saved one.
3. **Paystack:** in Paystack → Settings → API Keys & Webhooks, set the webhook URL shown on the settings page (`…/webhooks/paystack`).
   **Hubtel:** callbacks go to `…/webhooks/hubtel`. Ask Hubtel to enable the transaction-status API for your merchant account and whitelist your server's IP address. The system uses that API to confirm every payment, because Hubtel callbacks are not signed.
4. Keep the scheduled task (`bin/cron.php`) running. It re-checks payments whose confirmation was missed, and sends the weekly overdue reminders if you switch them on.
5. Make a small real payment first and check that it appears under **Fees → Online payments** with a receipt.

If a parent pays online for a bill that was cancelled meanwhile, the Bursar gets a notification. The money is not credited automatically: refund it, or record it against the correct bill as a MoMo payment using the same reference.

## Setting up SMS

1. Open an account with Hubtel, Arkesel or mNotify and register the sender ID `NISDAS` (approval can take a few days).
2. In the app go to **Notifications & SMS → SMS settings**, choose the provider and paste the API key (Hubtel: Client ID + Client Secret).
3. Use **Send a test SMS** to check it works. Until a provider is chosen, the system runs in **test mode**: messages appear in the Outbox as sent but never leave the server.
4. **XAMPP on Windows:** if the test fails with an "SSL certificate problem" error, open `C:\xampp\php\php.ini`. Set `curl.cainfo = "C:\xampp\apache\bin\curl-ca-bundle.crt"` and `openssl.cafile` to the same file, then restart Apache.
5. Set up the scheduled task so reminders and scheduled messages go out automatically. Use Windows Task Scheduler, run it every 10 minutes, with program `C:\xampp\php\php.exe` and arguments `C:\xampp\htdocs\NISDASSMS\bin\cron.php`. Without it, use **Send waiting messages now** in the Outbox.

The mNotify endpoint (`https://api.mnotify.com/api/sms/quick`) can be changed in SMS settings if mNotify/BMS gives you a different one.

## Teachers, curriculum & classrooms

Added in migration `014_teachers_curriculum_classrooms.sql` (click **Update database now** once):

* **Teachers** (Head, Coordinator): one profile per teacher with details, subjects, time table, sign-in attendance, class results, scheme-of-work coverage and invigilation duties. Every staff member sees their own under **My profile & appraisal**.
* **Termly appraisal**: the Head opens it, the teacher rates themselves (1–5), the Head rates and comments, the teacher acknowledges. Criteria are editable under **Teachers → Appraisal criteria**.
* **Curriculum & scheme of work**: NaCCA strands and sub-strands preloaded for every subject (edit them under **Curriculum strands**). Teachers draft the term's scheme from the curriculum, adjust it, and tick topics as taught; red rows are behind schedule.
* **Progress chart** on each pupil's profile and in the parent portal (parents see only terms whose report cards were released).
* **Seating plans** per class (alphabetical, boys/girls mixed or random, then drag to adjust) and **exam seating** that alternates classes.
* **Rooms & resources**: rooms, furniture, textbooks and equipment with condition and shortfalls against class size; printable shortages list.
* **Exam timetable**: papers per class or whole level, rooms, invigilators, clash checks (double-booked class or invigilator, crowded room, Sabbath), print by class or invigilator, publish to the notice board.
* New permissions appear in **Roles & security**; only the built-in roles receive them automatically.

## Official crest & school name

The school's official crest (taken from its printed terminal report) is built in at `assets/img/crest.png`. It is used for the browser-tab icon (`assets/favicon*.png`, `favicon.ico`, `apple-touch-icon.png`), the top menu bar, the sign-in and public pages, report cards, receipts, bills, statements, ID cards, timetables and every other printout.

* The school name shown everywhere is **Naylor International S.D.A School** (migration `013_official_crest_name.sql` changes the old default "NISDAS"; a name the school typed itself is left alone).
* SMS messages and the 2-step sign-in app still use the short name **NISDAS** (setting `school_short_name`) to save SMS credit.
* To use a sharper or coloured copy of the crest, upload it in **School setup → Logo**. **Use the built-in crest again** switches back.
* For a new browser-tab icon, replace the files in `assets/` with the same names.

## Updating to a new version

Copy the new files over the old ones (your `config/config.local.php` and `storage/` are kept). Then sign in as Super Admin and click **Update database now** in the yellow banner (or run `C:\xampp\php\php.exe database\migrate.php`).

## Install on XAMPP (Windows)

1. Make sure the project is in `C:\xampp\htdocs\NISDASSMS` (this folder).
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open <http://localhost/NISDASSMS/> in a browser. You will be sent to the installer.
4. Enter the school name, your Super Admin account and the current academic year, then click **Install**.
   - The installer creates the `nisdas_sms` database, all tables, the 12 levels (Creche, KG 1–2, Basic 1–6, JHS 1–3), one class per level and the default subjects.
5. Sign in, then go to **School setup → Years & terms** and enter the exact GES reopening and vacation dates.

### If your MySQL root user has a password

Copy `config/config.local.example.php` to `config/config.local.php` and set the password there. Do not edit `config/config.php`.

### If pages other than the dashboard show "Not Found"

Apache's `mod_rewrite` is off. Either enable it in `C:\xampp\apache\conf\httpd.conf` (`LoadModule rewrite_module …` and `AllowOverride All`), or set `'pretty_urls' => false` in `config/config.local.php`.

## Useful links

- Sign-in for everyone: `http://localhost/NISDASSMS/login` (staff: email · parents: phone number · pupils: admission number)
- Parents' online application: `http://localhost/NISDASSMS/apply`
- Parents' status check: `http://localhost/NISDASSMS/apply/status`
- Parents' bill / pay page: the link in the bill SMS (`…/pay/<code>`)

## Project layout

```
index.php              front controller (all pages go through here)
install.php            one-time installer (locks itself afterwards)
app/core/              Router, Database (PDO), Auth, Csrf, Validator, Upload, Migrator
app/controllers/       one controller per module
app/models/            Admission and CalendarEvent rules and constants
app/views/             PHP templates (escaped output with e())
app/routes.php         every URL and the permission it needs
assets/                CSS, JS, favicon (no internet connection needed)
database/migrations/   numbered SQL files; new sprints add 002_*.sql, 003_*.sql …
database/migrate.php   apply new migrations: C:\xampp\php\php.exe database\migrate.php
bin/cron.php           scheduled task: reminders, missed online-payment checks, queued SMS, daily backup
bin/restore.php        restore a backup (asks for YES, makes a safety backup first)
app/services/          Notifier (SMS queue), SmsGateway (Hubtel/Arkesel/mNotify), Audience, Scheduler, Http, Results (grading engine), Timetable, Fees, PaymentGateway (Paystack/Hubtel), Finance (cash book), Accounts, Notices, Quiz, Backup, SheetReader + Importer (Excel/CSV), GoLive
assets/css/report.css  A4 terminal report card layout
storage/uploads/       uploaded documents and photos (not web-accessible)
storage/backups/       automatic and manual backups (not web-accessible)
```

## Security notes

- All SQL goes through prepared statements, and all output is HTML-escaped.
- Every form carries a CSRF token, and permissions are checked per route in `app/routes.php` / `Auth::PERMISSIONS`.
- Uploads are checked by their real file type (PDF/JPG/PNG, at most 5 MB), renamed randomly, stored outside the web root folders and served only to signed-in staff.
- Passwords use PHP's `password_hash` (bcrypt). The account and the IP are locked for 15 minutes after repeated failed sign-ins.
- Temporary passwords in login SMS are hidden in the SMS outbox once sent. Parents only ever see their own children; pupils only their own quizzes.
- Online payments are credited only after a server-to-server check with the gateway. Paystack webhooks must carry a valid HMAC-SHA512 signature, and a payment can never be credited twice.
- Backups contain everything (including password hashes): only the Super Admin can make or download them, and they are stored outside the web folder.
- Before going live on the internet, serve the site over HTTPS (`force_https`). Pupil records are personal data under Ghana's Data Protection Act, 2012 (Act 843).
- Data protection (Act 843): School setup → Data protection holds the Data Protection Commission number, the privacy notice parents see at `/privacy` (linked on every public page and the admission form) and the retention period. The Super Admin can anonymise pupils who left longer ago than that period: contact, health, special-needs details and photos are removed; names, admission numbers, class history, results and fee records are kept.

## GES / NaCCA / NaSIA compliance (Sprint 11, migration 010)

- **Grading**: KG–Basic 6 use the NaCCA proficiency levels; JHS report cards show the NaCCA JHS level (HP 80+, P 68–79, AP 54–67, D 40–53, E 0–39) beside the BECE grade 1–9, which still gives the aggregate. Both are editable in Exams → Grading setup. The class/exam split (default 30/70) must be confirmed by the Head.
- **Special educational needs**: on the admission form, the application and the pupil record; carried over on enrolment; counted in the census.
- **Staff**: gender, Ghana Card, highest qualification (trained/untrained), NTC licence number and expiry in Payroll → Staff records. Teachers get an SMS 60 and 14 days before expiry; the dashboard and Go-live checklist flag missing/expiring licences.
- **School census (EMIS)**: School reports → School census — enrolment by level and gender, repeaters, ages, special needs, staff by gender/trained/licensed. Print or Export CSV.

## Sprint 12 (no database change)

- **Excel import**: the pupils template has two new columns, *Special needs* and *Special needs support*. A new import, **Staff records (pay, qualification & NTC licence)**, adds people to Payroll → Staff records with gender, Ghana Card, qualification and NTC licence — or updates existing people when their staff no. is in the first column (empty cells are left unchanged).
- **Practice copy** now also has admission applications in every stage, requisitions waiting for approval/payment/returned, an open consent form with replies and a closed PTA poll, a published JHS 1 Maths quiz with results (pupil login `pupil1`) and a draft Basic 6 quiz, and parent ↔ teacher conversations. Reset it from Administration → Practice copy to get the new data.
- An expired form now returns a standard 403 "Session expired" page with a link back to the form.

## Least privilege & role-based access (Sprint 13, migration 011)

- **Roles & permissions** are now in the database and edited by the Super Admin in *Administration → Roles & security*: edit a role, use the *Permission matrix*, or create **custom roles** (e.g. Deputy Head, Librarian, School Nurse) that "work like" a built-in role but start with no permissions. The update keeps exactly the access each role had before, plus a new *Health & special needs* permission (not given to the Bursar).
- **Safety rules**: Users, Roles & security, Backups and the second requisition approval stay with the Super Admin; parents/pupils can only hold portal permissions; one role cannot both approve and pay requisitions, or prepare and approve payroll.
- **Data scope**: Class and Subject Teachers now see only pupils in classes they teach (changeable per role).
- **2-step sign-in** (authenticator app, recovery codes): always on for the Super Admin (asked at the next sign-in after the update), optional or required per role. Lost phone: another Super Admin resets it in Users, or run `bin\reset-admin.php` on the school computer. The key protecting these secrets is `storage\app.key` — it is copied into every backup.
- **Sign-in rules**: password length/complexity/history/expiry, lock-out, automatic sign-out per role, maximum session length, one device at a time, new-device alerts, dormant accounts, school hours and school network per role. *My security* shows each person's devices; administrators can sign anyone out everywhere.
- **Risky pages** (Users, Roles & security, Backups, Data protection, Practice copy) ask for the password again after 10 minutes. Denied pages, role changes and sign-in events are in the audit log and the *Access review*.
- The database updater now resumes safely if an update stopped half-way.

## School report format (Sprint 14, migration 012)

- Report cards follow the school's printed terminal report: page 1 with Class (50%) / Exams (50%) / Total / Grade / Position / Remarks, a blank row, TOTAL, the GRADING SCHEME grid (90–100 = 1 Excellent … 0–39 = 9 Fail), attendance, promoted to / repeat, class teacher's and head of school's comments with signature lines; page 2 is the **Child's Personality Development Report** (11 traits with comments).
- Section headings: Creche, Kindergarten, Lower Primary School (Basic 1–3), Upper Primary School (Basic 4–6), Junior High School. JHS also shows the NaCCA level column and the aggregate (best 6).
- The update switches every level still on the original grading to class 50% + exams 50% and grades 1–9; levels the school had already changed are left alone. Any level can be switched in Exams → Grading setup ("Use the school's report grading").
- Class teachers enter page 2 in Report cards → class → Remarks → *Personality report (page 2)*, one pupil at a time with quick-pick comments. Traits, page 2, overall position and a fees line are set in Grading setup → Report card (position and fees are off, as on the school's form).
- Set the school name to "Naylor International S.D.A School" and upload the crest in School setup so they print on every report.

## Modules page, school life and the phone app (Sprints 17–19, migrations 016–019)

- **Part-month pay** by calendar days; staff paid by the day are paid from approved timesheets.
- **Assignments**, **pupil leave** (approved days are Excused on the register) and **staff timesheets & shifts**.
- **Canteen** (prepaid wallets, counter, menu, meal plans billed with fees, stock, allergy check), **events** with registration, **online appointments**.
- **WhatsApp** (Meta / Twilio / Hubtel, off by default, SMS fallback), **installable phone app** with notifications (needs https), notice numbering and priority, **Admission List** (merit ranking), **fee payment plans**, bus licence / roadworthy / insurance alerts.

## NISDAS APPS (Sprints 20–22, migrations 020–022)

- The menu item **NISDAS APPS** opens the launcher: 24 icons (SIS, Course, Financial, Online Admission, Gradebook, Exam, LMS, Attendance, Assignment, Campus, Live Classroom, Timetable, Library, Faculty, CRM, E-Commerce, Event, Document, Marketing, WhatsApp, Zoom, Google Meet, Teams, View All). Each person sees only what they may open.
- **Campus**: blocks, rooms, room booking without clashes, repair requests with photo, cost and follow-up.
- **Documents**: school document library (with management-only documents), pupil files and staff files, reminders 30 days before expiry.
- **LMS**: lesson notes, YouTube videos, links and worksheets per class/subject; who has opened each one.
- **Live classes**: paste a Zoom, Google Meet or Teams link; reminders, Join button 15 minutes before, join register.
- **CRM** (admission enquiries with follow-ups; public form at `/enquire`), **marketing campaigns** (SMS / WhatsApp, do-not-message list), **school shop** (orders paid by MoMo online, counter sales, stock; money under “School shop” in the cash book).
- There is no sidebar: the top menu bar has Dashboard (staff), NISDAS APPS (every app as a big icon) and Administration (a drop-down of School setup, Users, Roles & security, Parent & student logins, Backups, Phone app, Audit log and Governance & risk, for those allowed).
