-- NISDAS SMS — Sprint 9: staff payroll, library, transport, health records, BECE registration

-- ---------------------------------------------------------------- Payroll
-- Every person the school pays (teaching and non-teaching: cooks, cleaners, drivers, security).
-- user_id links the record to a system login so the person can see their own payslips.
CREATE TABLE staff (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_no VARCHAR(20) NOT NULL UNIQUE,
    user_id INT UNSIGNED NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(20) NULL,
    position VARCHAR(80) NOT NULL,
    category ENUM('teaching','non_teaching') NOT NULL DEFAULT 'teaching',
    employment ENUM('permanent','contract','casual','national_service') NOT NULL DEFAULT 'permanent',
    start_date DATE NULL,
    end_date DATE NULL,
    ssnit_no VARCHAR(30) NULL,
    tin VARCHAR(30) NULL,
    pay_method ENUM('bank','momo','cash') NOT NULL DEFAULT 'bank',
    bank_name VARCHAR(80) NULL,
    bank_branch VARCHAR(80) NULL,
    account_no VARCHAR(40) NULL,
    momo_no VARCHAR(20) NULL,
    basic_salary DECIMAL(10,2) NOT NULL DEFAULT 0,
    pays_ssnit TINYINT(1) NOT NULL DEFAULT 1,
    pays_paye TINYINT(1) NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    notes VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_staff_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Standing monthly allowances and deductions for one staff member
CREATE TABLE staff_pay_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id INT UNSIGNED NOT NULL,
    kind ENUM('allowance','deduction') NOT NULL,
    name VARCHAR(80) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    taxable TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_spi_staff FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One payroll per month: draft (Bursar) → approved (Head) → paid (Bursar)
CREATE TABLE payroll_runs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    period CHAR(7) NOT NULL UNIQUE,
    status ENUM('draft','approved','paid') NOT NULL DEFAULT 'draft',
    staff_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    gross DECIMAL(12,2) NOT NULL DEFAULT 0,
    net DECIMAL(12,2) NOT NULL DEFAULT 0,
    ssnit_employee DECIMAL(12,2) NOT NULL DEFAULT 0,
    ssnit_employer DECIMAL(12,2) NOT NULL DEFAULT 0,
    paye DECIMAL(12,2) NOT NULL DEFAULT 0,
    deductions DECIMAL(12,2) NOT NULL DEFAULT 0,
    notes VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_by INT UNSIGNED NULL,
    approved_at DATETIME NULL,
    paid_by INT UNSIGNED NULL,
    paid_at DATETIME NULL,
    paid_on DATE NULL,
    account_id INT UNSIGNED NULL,
    statutory_paid_on DATE NULL,
    CONSTRAINT fk_pr_created FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_pr_approved FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_pr_paid FOREIGN KEY (paid_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_pr_acct FOREIGN KEY (account_id) REFERENCES fin_accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payslips (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    run_id INT UNSIGNED NOT NULL,
    staff_id INT UNSIGNED NOT NULL,
    basic DECIMAL(10,2) NOT NULL DEFAULT 0,
    allowances DECIMAL(10,2) NOT NULL DEFAULT 0,
    gross DECIMAL(10,2) NOT NULL DEFAULT 0,
    ssnit_employee DECIMAL(10,2) NOT NULL DEFAULT 0,
    ssnit_employer DECIMAL(10,2) NOT NULL DEFAULT 0,
    taxable DECIMAL(10,2) NOT NULL DEFAULT 0,
    paye DECIMAL(10,2) NOT NULL DEFAULT 0,
    deductions DECIMAL(10,2) NOT NULL DEFAULT 0,
    net DECIMAL(10,2) NOT NULL DEFAULT 0,
    breakdown TEXT NULL,
    UNIQUE KEY uq_payslip (run_id, staff_id),
    CONSTRAINT fk_ps_run FOREIGN KEY (run_id) REFERENCES payroll_runs(id) ON DELETE CASCADE,
    CONSTRAINT fk_ps_staff FOREIGN KEY (staff_id) REFERENCES staff(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Library
CREATE TABLE library_books (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    author VARCHAR(150) NULL,
    isbn VARCHAR(20) NULL,
    publisher VARCHAR(120) NULL,
    category VARCHAR(60) NULL,
    level_band VARCHAR(30) NULL,
    shelf VARCHAR(30) NULL,
    copies SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    copies_lost SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_lib_title (title),
    INDEX idx_lib_isbn (isbn)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE library_loans (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    book_id INT UNSIGNED NOT NULL,
    pupil_id INT UNSIGNED NULL,
    staff_user_id INT UNSIGNED NULL,
    issued_on DATE NOT NULL,
    due_on DATE NOT NULL,
    returned_on DATE NULL,
    status ENUM('out','returned','lost') NOT NULL DEFAULT 'out',
    note VARCHAR(160) NULL,
    issued_by INT UNSIGNED NULL,
    received_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_loan_status (status, due_on),
    INDEX idx_loan_pupil (pupil_id),
    CONSTRAINT fk_loan_book FOREIGN KEY (book_id) REFERENCES library_books(id),
    CONSTRAINT fk_loan_pupil FOREIGN KEY (pupil_id) REFERENCES pupils(id) ON DELETE CASCADE,
    CONSTRAINT fk_loan_staff FOREIGN KEY (staff_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_loan_issued FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_loan_received FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Transport
CREATE TABLE transport_routes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    stops VARCHAR(500) NULL,
    vehicle VARCHAR(60) NULL,
    driver_name VARCHAR(100) NULL,
    driver_phone VARCHAR(20) NULL,
    attendant_name VARCHAR(100) NULL,
    attendant_phone VARCHAR(20) NULL,
    capacity SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    fee_per_term DECIMAL(10,2) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transport_assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pupil_id INT UNSIGNED NOT NULL,
    route_id INT UNSIGNED NOT NULL,
    academic_year_id INT UNSIGNED NOT NULL,
    stop VARCHAR(80) NULL,
    trips ENUM('both','morning','afternoon') NOT NULL DEFAULT 'both',
    started_on DATE NOT NULL,
    ended_on DATE NULL,
    UNIQUE KEY uq_ta (pupil_id, academic_year_id),
    INDEX idx_ta_route (route_id, academic_year_id),
    CONSTRAINT fk_ta_pupil FOREIGN KEY (pupil_id) REFERENCES pupils(id) ON DELETE CASCADE,
    CONSTRAINT fk_ta_route FOREIGN KEY (route_id) REFERENCES transport_routes(id),
    CONSTRAINT fk_ta_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bus fees billed per term (one bill per pupil per term)
CREATE TABLE transport_bills (
    pupil_id INT UNSIGNED NOT NULL,
    term_id INT UNSIGNED NOT NULL,
    invoice_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (pupil_id, term_id),
    CONSTRAINT fk_tb_pupil FOREIGN KEY (pupil_id) REFERENCES pupils(id) ON DELETE CASCADE,
    CONSTRAINT fk_tb_term FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE CASCADE,
    CONSTRAINT fk_tb_inv FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Health
CREATE TABLE pupil_health (
    pupil_id INT UNSIGNED PRIMARY KEY,
    blood_group VARCHAR(5) NULL,
    genotype VARCHAR(5) NULL,
    nhis_no VARCHAR(30) NULL,
    nhis_expiry DATE NULL,
    conditions VARCHAR(255) NULL,
    medication VARCHAR(255) NULL,
    clinic VARCHAR(120) NULL,
    emergency_name VARCHAR(120) NULL,
    emergency_phone VARCHAR(20) NULL,
    immunisation_ok TINYINT(1) NULL,
    updated_by INT UNSIGNED NULL,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ph_pupil FOREIGN KEY (pupil_id) REFERENCES pupils(id) ON DELETE CASCADE,
    CONSTRAINT fk_ph_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sick bay / first aid visits
CREATE TABLE health_visits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pupil_id INT UNSIGNED NOT NULL,
    seen_at DATETIME NOT NULL,
    complaint VARCHAR(255) NOT NULL,
    temperature DECIMAL(4,1) NULL,
    action_taken VARCHAR(255) NULL,
    outcome ENUM('back_to_class','rested','sent_home','referred') NOT NULL DEFAULT 'back_to_class',
    guardian_notified TINYINT(1) NOT NULL DEFAULT 0,
    recorded_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_hv_pupil (pupil_id, seen_at),
    INDEX idx_hv_date (seen_at),
    CONSTRAINT fk_hv_pupil FOREIGN KEY (pupil_id) REFERENCES pupils(id) ON DELETE CASCADE,
    CONSTRAINT fk_hv_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- BECE
CREATE TABLE bece_candidates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pupil_id INT UNSIGNED NOT NULL,
    academic_year_id INT UNSIGNED NOT NULL,
    index_no VARCHAR(20) NULL,
    nia_no VARCHAR(30) NULL,
    results TEXT NULL,
    aggregate TINYINT UNSIGNED NULL,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_bece (pupil_id, academic_year_id),
    UNIQUE KEY uq_bece_index (index_no),
    CONSTRAINT fk_bece_pupil FOREIGN KEY (pupil_id) REFERENCES pupils(id) ON DELETE CASCADE,
    CONSTRAINT fk_bece_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Settings & templates
-- PAYE: monthly bands for resident individuals as "band width:rate" pairs, last band open-ended.
-- These are the GRA bands in force from January 2024. The Bursar must confirm them with GRA.
INSERT INTO settings (`key`, `value`) VALUES
('paye_bands', '490:0,110:5,130:10,3166.67:17.5,16000:25,30520:30,0:35'),
('ssnit_employee_rate', '5.5'),
('ssnit_employer_rate', '13'),
('payroll_account', (SELECT id FROM fin_accounts WHERE kind = 'bank' ORDER BY id LIMIT 1)),
('library_loan_days', '14'),
('library_max_loans', '2'),
('bece_core_subjects', 'ENG,MATH,SCI,SOC');

INSERT INTO notification_templates (`key`, name, description, sms_body, placeholders) VALUES
('sick_bay', 'Sick bay visit', 'Sent to a parent when their child is seen at the sick bay (if the staff member ticks "inform parent")', 'Dear {guardian}, {pupil} was seen at the {school} sick bay today at {time}: {complaint}. {outcome} Please call the school if you have questions.', 'guardian, pupil, school, time, complaint, outcome'),
('library_overdue', 'Library book overdue', 'Reminder to return a library book', 'Dear {guardian}, {pupil} has a library book "{title}" that was due on {due}. Kindly remind them to return it. {school}', 'guardian, pupil, title, due, school'),
('salary_paid', 'Salary paid', 'Sent to a staff member when the month''s salary is paid', 'Dear {name}, your {month} salary of GHS {net} has been paid by {method}. {school}', 'name, month, net, method, school');
