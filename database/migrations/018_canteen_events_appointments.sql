-- NISDAS SMS — Canteen, school events and online appointments
-- * Canteen: food and drink items with prices, allergens and stock; daily menu; termly meal plans billed with fees;
--   prepaid pupil wallets topped up by cash or MoMo; counter sales from the wallet or for cash; meals served
-- * Events: registration (with optional fee billed to the pupil), capacity and waiting list, check-in and report
-- * Appointments: types (e.g. "Meet the class teacher"), staff weekly availability, parents book a free time

CREATE TABLE IF NOT EXISTS canteen_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    category ENUM('meal','snack','drink','other') NOT NULL DEFAULT 'meal',
    price DECIMAL(8,2) NOT NULL DEFAULT 0,
    allergens VARCHAR(160) NULL,
    track_stock TINYINT(1) NOT NULL DEFAULT 0,
    stock_qty INT NOT NULL DEFAULT 0,
    reorder_level INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_canteen_item (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO canteen_items (name, category, price, allergens, track_stock, stock_qty, reorder_level) VALUES
('Jollof rice with chicken', 'meal', 15.00, NULL, 0, 0, 0),
('Waakye with egg', 'meal', 12.00, 'egg', 0, 0, 0),
('Banku and okro stew', 'meal', 12.00, 'fish', 0, 0, 0),
('Rice and groundnut soup', 'meal', 12.00, 'groundnut', 0, 0, 0),
('Hausa koko and bread', 'meal', 6.00, 'groundnut, wheat', 0, 0, 0),
('Meat pie', 'snack', 5.00, 'wheat, egg', 1, 40, 10),
('Plantain chips', 'snack', 3.00, NULL, 1, 60, 15),
('Bottled water (500 ml)', 'drink', 2.00, NULL, 1, 120, 30),
('Sobolo', 'drink', 3.00, NULL, 1, 50, 10),
('Fan Yogo', 'drink', 4.00, 'milk', 1, 48, 12);

CREATE TABLE IF NOT EXISTS canteen_stock_moves (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_id INT UNSIGNED NOT NULL,
    qty INT NOT NULL,
    kind ENUM('in','sale','adjust') NOT NULL,
    note VARCHAR(160) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_csm_item FOREIGN KEY (item_id) REFERENCES canteen_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS canteen_menu (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    menu_date DATE NOT NULL,
    meal ENUM('breakfast','lunch','snack') NOT NULL DEFAULT 'lunch',
    item_id INT UNSIGNED NOT NULL,
    UNIQUE KEY uq_menu (menu_date, meal, item_id),
    CONSTRAINT fk_menu_item FOREIGN KEY (item_id) REFERENCES canteen_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS canteen_plans (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    meals VARCHAR(160) NULL,
    price DECIMAL(10,2) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_plan (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO canteen_plans (name, meals, price) VALUES ('Lunch every school day', 'Lunch, Monday to Friday', 650.00), ('Breakfast and lunch', 'Breakfast and lunch, Monday to Friday', 900.00);

CREATE TABLE IF NOT EXISTS canteen_plan_members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    term_id INT UNSIGNED NOT NULL,
    pupil_id INT UNSIGNED NOT NULL,
    plan_id INT UNSIGNED NOT NULL,
    invoice_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_plan_member (term_id, pupil_id),
    CONSTRAINT fk_cpm_term FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE CASCADE,
    CONSTRAINT fk_cpm_pupil FOREIGN KEY (pupil_id) REFERENCES pupils(id) ON DELETE CASCADE,
    CONSTRAINT fk_cpm_plan FOREIGN KEY (plan_id) REFERENCES canteen_plans(id),
    CONSTRAINT fk_cpm_inv FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS canteen_wallets (
    pupil_id INT UNSIGNED NOT NULL PRIMARY KEY,
    balance DECIMAL(10,2) NOT NULL DEFAULT 0,
    daily_limit DECIMAL(8,2) NULL,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cw_pupil FOREIGN KEY (pupil_id) REFERENCES pupils(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS canteen_sales (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sale_no VARCHAR(20) NOT NULL,
    pupil_id INT UNSIGNED NULL,
    paid_by ENUM('wallet','cash','momo') NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    status ENUM('completed','void') NOT NULL DEFAULT 'completed',
    void_reason VARCHAR(160) NULL,
    sold_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sale_no (sale_no),
    INDEX idx_sale_date (created_at),
    CONSTRAINT fk_cs_pupil FOREIGN KEY (pupil_id) REFERENCES pupils(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS canteen_sale_lines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sale_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NULL,
    name VARCHAR(80) NOT NULL,
    qty INT NOT NULL,
    price DECIMAL(8,2) NOT NULL,
    CONSTRAINT fk_csl_sale FOREIGN KEY (sale_id) REFERENCES canteen_sales(id) ON DELETE CASCADE,
    CONSTRAINT fk_csl_item FOREIGN KEY (item_id) REFERENCES canteen_items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS canteen_wallet_txns (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pupil_id INT UNSIGNED NOT NULL,
    kind ENUM('topup','sale','refund','adjust') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    balance_after DECIMAL(10,2) NOT NULL,
    method VARCHAR(20) NULL,
    reference VARCHAR(100) NULL,
    sale_id INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cwt_pupil (pupil_id, created_at),
    CONSTRAINT fk_cwt_pupil FOREIGN KEY (pupil_id) REFERENCES pupils(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS canteen_served (
    served_on DATE NOT NULL,
    pupil_id INT UNSIGNED NOT NULL,
    meal ENUM('breakfast','lunch') NOT NULL DEFAULT 'lunch',
    marked_by INT UNSIGNED NULL,
    PRIMARY KEY (served_on, pupil_id, meal),
    CONSTRAINT fk_csv_pupil FOREIGN KEY (pupil_id) REFERENCES pupils(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS school_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description TEXT NULL,
    venue VARCHAR(120) NULL,
    start_at DATETIME NOT NULL,
    end_at DATETIME NULL,
    audience ENUM('all','parents','staff','level') NOT NULL DEFAULT 'all',
    level_id INT UNSIGNED NULL,
    capacity INT NULL,
    fee DECIMAL(10,2) NOT NULL DEFAULT 0,
    registration TINYINT(1) NOT NULL DEFAULT 1,
    reg_deadline DATE NULL,
    status ENUM('draft','published','cancelled','completed') NOT NULL DEFAULT 'published',
    calendar_event_id INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sev_start (start_at),
    CONSTRAINT fk_sev_level FOREIGN KEY (level_id) REFERENCES levels(id) ON DELETE SET NULL,
    CONSTRAINT fk_sev_cal FOREIGN KEY (calendar_event_id) REFERENCES calendar_events(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_registrations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id INT UNSIGNED NOT NULL,
    pupil_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    guests INT NOT NULL DEFAULT 0,
    status ENUM('registered','waitlist','cancelled') NOT NULL DEFAULT 'registered',
    invoice_id INT UNSIGNED NULL,
    attended TINYINT(1) NOT NULL DEFAULT 0,
    checked_in_at DATETIME NULL,
    note VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_reg_pupil (event_id, pupil_id),
    UNIQUE KEY uq_reg_user (event_id, user_id, pupil_id),
    CONSTRAINT fk_er_event FOREIGN KEY (event_id) REFERENCES school_events(id) ON DELETE CASCADE,
    CONSTRAINT fk_er_pupil FOREIGN KEY (pupil_id) REFERENCES pupils(id) ON DELETE CASCADE,
    CONSTRAINT fk_er_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_er_inv FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS appointment_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    duration_min INT NOT NULL DEFAULT 20,
    description VARCHAR(255) NULL,
    question VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_appt_type (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO appointment_types (name, duration_min, description, question) VALUES
('Meet the class teacher', 20, 'Talk about your child''s progress, behaviour or welfare.', 'What would you like to discuss?'),
('Meet the Head Teacher', 30, 'Concerns, requests or feedback for the Head.', 'What is the meeting about?'),
('Fees and payments', 15, 'Questions about bills, payment plans or receipts.', 'Which bill or question?'),
('Counselling', 30, 'A confidential conversation with the school counsellor or chaplain.', NULL);

CREATE TABLE IF NOT EXISTS appointment_availability (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    type_id INT UNSIGNED NOT NULL,
    weekday TINYINT NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    INDEX idx_aa_user (user_id),
    CONSTRAINT fk_aa_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_aa_type FOREIGN KEY (type_id) REFERENCES appointment_types(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS appointments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type_id INT UNSIGNED NOT NULL,
    staff_id INT UNSIGNED NOT NULL,
    booked_by INT UNSIGNED NOT NULL,
    pupil_id INT UNSIGNED NULL,
    start_at DATETIME NOT NULL,
    end_at DATETIME NOT NULL,
    answer VARCHAR(500) NULL,
    status ENUM('booked','cancelled','done','no_show') NOT NULL DEFAULT 'booked',
    staff_note VARCHAR(500) NULL,
    cancelled_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_appt_staff (staff_id, start_at),
    CONSTRAINT fk_ap_type FOREIGN KEY (type_id) REFERENCES appointment_types(id),
    CONSTRAINT fk_ap_staff FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ap_by FOREIGN KEY (booked_by) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ap_pupil FOREIGN KEY (pupil_id) REFERENCES pupils(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO role_permissions (role_key, permission)
SELECT r.role_key, p.perm FROM roles r JOIN (
    SELECT 'head_teacher' b, 'canteen.manage' perm UNION ALL SELECT 'bursar', 'canteen.manage'
    UNION ALL SELECT 'head_teacher', 'canteen.sell' UNION ALL SELECT 'bursar', 'canteen.sell' UNION ALL SELECT 'admissions_officer', 'canteen.sell'
    UNION ALL SELECT 'head_teacher', 'events.manage' UNION ALL SELECT 'academic_coordinator', 'events.manage'
    UNION ALL SELECT 'head_teacher', 'appointments.manage'
    UNION ALL SELECT 'head_teacher', 'appointments.offer' UNION ALL SELECT 'academic_coordinator', 'appointments.offer' UNION ALL SELECT 'class_teacher', 'appointments.offer'
    UNION ALL SELECT 'subject_teacher', 'appointments.offer' UNION ALL SELECT 'bursar', 'appointments.offer' UNION ALL SELECT 'admissions_officer', 'appointments.offer'
    UNION ALL SELECT 'parent', 'appointments.book'
) p ON p.b = r.role_key
WHERE r.is_system = 1;
