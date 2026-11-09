-- NISDAS SMS — Finance MVP: double-entry books (ledger), purchase orders & goods received (three-way match),
-- department budget caps, reserves, staged fee reminders, online payment charge, Storekeeper and Auditor roles.

-- ------------------------------------------------------------------ the books (double entry)
-- Everyday work never types debits and credits: the system builds journal entries from fees, the cash book,
-- supplier bills, petty cash, payroll and fixed assets (Ledger::sync). Only corrections are typed by hand
-- (manual journals) and they count only after the Head approves them.
CREATE TABLE gl_accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    type ENUM('asset','liability','equity','income','expense') NOT NULL,
    sys_key VARCHAR(20) NULL UNIQUE,
    fin_account_id INT UNSIGNED NULL UNIQUE,
    category_id INT UNSIGNED NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE gl_entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entry_date DATE NOT NULL,
    source VARCHAR(10) NOT NULL,
    source_id INT UNSIGNED NOT NULL,
    ref VARCHAR(40) NULL,
    memo VARCHAR(255) NOT NULL,
    is_change TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_gle_source (source, source_id),
    INDEX idx_gle_date (entry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE gl_lines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entry_id INT UNSIGNED NOT NULL,
    account_id INT UNSIGNED NOT NULL,
    debit DECIMAL(14,2) NOT NULL DEFAULT 0,
    credit DECIMAL(14,2) NOT NULL DEFAULT 0,
    INDEX idx_gll_account (account_id),
    CONSTRAINT fk_gll_entry FOREIGN KEY (entry_id) REFERENCES gl_entries(id) ON DELETE CASCADE,
    CONSTRAINT fk_gll_account FOREIGN KEY (account_id) REFERENCES gl_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE gl_journals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    jnl_no VARCHAR(30) NOT NULL UNIQUE,
    entry_date DATE NOT NULL,
    memo VARCHAR(255) NOT NULL,
    total DECIMAL(14,2) NOT NULL DEFAULT 0,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    drafted_by INT UNSIGNED NULL,
    drafted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decided_by INT UNSIGNED NULL,
    decided_at DATETIME NULL,
    decision_note VARCHAR(255) NULL,
    INDEX idx_gj_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE gl_journal_lines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    journal_id INT UNSIGNED NOT NULL,
    account_id INT UNSIGNED NOT NULL,
    debit DECIMAL(14,2) NOT NULL DEFAULT 0,
    credit DECIMAL(14,2) NOT NULL DEFAULT 0,
    note VARCHAR(150) NULL,
    CONSTRAINT fk_gjl_journal FOREIGN KEY (journal_id) REFERENCES gl_journals(id) ON DELETE CASCADE,
    CONSTRAINT fk_gjl_account FOREIGN KEY (account_id) REFERENCES gl_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fixed accounts (money accounts and cash-book categories get their own account automatically)
INSERT IGNORE INTO gl_accounts (code, name, type, sys_key) VALUES
('1200', 'School fees owed by parents', 'asset', 'ar'),
('1500', 'Buildings, furniture, computers, vehicles (cost)', 'asset', 'fa_cost'),
('1550', 'Assets paid for — waiting for the asset register', 'asset', 'fa_clear'),
('1590', 'Wear to date on assets (depreciation)', 'asset', 'fa_dep'),
('2010', 'Supplier bills not yet paid', 'liability', 'ap'),
('2100', 'Family credit (fees paid ahead)', 'liability', 'family_credit'),
('2200', 'Withholding tax to pay GRA', 'liability', 'wht'),
('3000', 'School fund (built up over the years)', 'equity', 'fund'),
('3200', 'Assets given or funded by others (PTA, donors, Government)', 'equity', 'capital_grants'),
('4900', 'Assets sold or written off (gain / loss)', 'income', 'asset_disposal'),
('4999', 'Income not yet sorted', 'income', 'income_suspense'),
('5900', 'Wear on assets (depreciation)', 'expense', 'depreciation'),
('5999', 'Spending not yet sorted', 'expense', 'expense_suspense');

-- ------------------------------------------------------------------ departments & budget caps
ALTER TABLE fin_transactions ADD COLUMN department VARCHAR(80) NULL, ADD INDEX idx_fin_dept (department, txn_date);

CREATE TABLE fin_departments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO fin_departments (name, sort_order) VALUES
('Creche & KG', 1), ('Lower Primary (B1–B3)', 2), ('Upper Primary (B4–B6)', 3), ('JHS', 4), ('Administration', 5),
('Kitchen / Feeding', 6), ('Maintenance', 7), ('Sports & Clubs', 8), ('Chaplaincy / Worship', 9), ('ICT', 10);

CREATE TABLE fin_dept_caps (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    term_id INT UNSIGNED NOT NULL,
    department VARCHAR(80) NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    UNIQUE KEY uq_dept_cap (term_id, department),
    CONSTRAINT fk_dcap_term FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE fin_dept_cap_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    term_id INT UNSIGNED NOT NULL,
    department VARCHAR(80) NOT NULL,
    kind ENUM('set','raise','move_in','move_out') NOT NULL,
    old_amount DECIMAL(12,2) NULL,
    new_amount DECIMAL(12,2) NOT NULL,
    note VARCHAR(255) NULL,
    user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_dcl_term (term_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------ purchase orders & goods received
CREATE TABLE purchase_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    po_no VARCHAR(30) NOT NULL UNIQUE,
    requisition_id INT UNSIGNED NULL,
    supplier_id INT UNSIGNED NOT NULL,
    order_date DATE NOT NULL,
    expected_date DATE NULL,
    department VARCHAR(80) NULL,
    category_id INT UNSIGNED NULL,
    deliver_to VARCHAR(150) NULL,
    notes VARCHAR(500) NULL,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('draft','sent','part','received','closed','cancelled') NOT NULL DEFAULT 'draft',
    cancel_reason VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    sent_by INT UNSIGNED NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_po_status (status),
    CONSTRAINT fk_po_sup FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    CONSTRAINT fk_po_cat FOREIGN KEY (category_id) REFERENCES fin_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE po_lines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    po_id INT UNSIGNED NOT NULL,
    description VARCHAR(150) NOT NULL,
    unit VARCHAR(20) NULL,
    qty DECIMAL(10,2) NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    received_qty DECIMAL(10,2) NOT NULL DEFAULT 0,
    CONSTRAINT fk_pol_po FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE goods_received (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    grn_no VARCHAR(30) NOT NULL UNIQUE,
    po_id INT UNSIGNED NOT NULL,
    received_date DATE NOT NULL,
    delivery_note VARCHAR(60) NULL,
    note VARCHAR(255) NULL,
    status ENUM('posted','void') NOT NULL DEFAULT 'posted',
    void_reason VARCHAR(255) NULL,
    received_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_grn_po (po_id),
    CONSTRAINT fk_grn_po FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE grn_lines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    grn_id INT UNSIGNED NOT NULL,
    po_line_id INT UNSIGNED NOT NULL,
    qty DECIMAL(10,2) NOT NULL,
    note VARCHAR(150) NULL,
    CONSTRAINT fk_grnl_grn FOREIGN KEY (grn_id) REFERENCES goods_received(id) ON DELETE CASCADE,
    CONSTRAINT fk_grnl_line FOREIGN KEY (po_line_id) REFERENCES po_lines(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE supplier_bills ADD COLUMN po_id INT UNSIGNED NULL, ADD COLUMN department VARCHAR(80) NULL,
    ADD COLUMN pay_approved_by INT UNSIGNED NULL, ADD COLUMN pay_approved_at DATETIME NULL, ADD INDEX idx_sb_po (po_id);

CREATE TABLE supplier_bill_lines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bill_id INT UNSIGNED NOT NULL,
    po_line_id INT UNSIGNED NULL,
    description VARCHAR(150) NOT NULL,
    qty DECIMAL(10,2) NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    CONSTRAINT fk_sbl_bill FOREIGN KEY (bill_id) REFERENCES supplier_bills(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE requisitions ADD COLUMN purchase_order_id INT UNSIGNED NULL;

-- ------------------------------------------------------------------ fees: staged reminders, online charge
CREATE TABLE fee_reminders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    stage TINYINT UNSIGNED NOT NULL,
    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_fee_rem (invoice_id, stage),
    CONSTRAINT fk_frem_inv FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE online_payments ADD COLUMN charge DECIMAL(10,2) NOT NULL DEFAULT 0;

INSERT IGNORE INTO fin_categories (kind, code, name, is_system, sort_order) VALUES
('income', 'OCHG', 'Online payment charges paid by parents', 1, 8);

INSERT IGNORE INTO notification_templates (`key`, name, description, sms_body, placeholders) VALUES
('fee_overdue_1', 'Fees overdue — first reminder', 'Sent when a bill is a few days late (reminder schedule, first step)', 'Dear {guardian}, a friendly reminder that {pupil}''s fees of GHS {balance} were due on {due}. Pay: {link} - {school}', 'guardian, pupil, balance, due, link, school'),
('fee_overdue_2', 'Fees overdue — second reminder', 'Second step of the reminder schedule (SMS and WhatsApp)', 'Dear {guardian}, {pupil}''s fees of GHS {balance} are now {days} days late. Please pay or call the accounts office to agree a plan. Pay: {link} - {school}', 'guardian, pupil, balance, days, link, school'),
('fee_overdue_3', 'Fees overdue — final reminder', 'Last step of the reminder schedule; the Head is told too', 'Dear {guardian}, {pupil}''s fees of GHS {balance} are {days} days late. The Head Teacher has been informed. Please see the accounts office this week. - {school}', 'guardian, pupil, balance, days, school');

-- ------------------------------------------------------------------ users: Auditor (read only) and Storekeeper
ALTER TABLE users ADD COLUMN access_until DATE NULL;
ALTER TABLE roles ADD COLUMN read_only TINYINT(1) NOT NULL DEFAULT 0;

INSERT IGNORE INTO roles (role_key, name, description, base_role, kind, is_system, data_scope, require_2fa, idle_minutes, sort_order, read_only) VALUES
('storekeeper', 'Storekeeper', 'Signs for goods delivered against purchase orders; raises requisitions.', 'storekeeper', 'staff', 1, 'all', 0, 30, 8, 0),
('auditor', 'External Auditor', 'Reads every finance page and report. Cannot save, pay, reverse or close anything. Access ends on the date set on the account.', 'auditor', 'staff', 1, 'all', 0, 30, 9, 1);

INSERT IGNORE INTO role_permissions (role_key, permission)
SELECT r.role_key, p.perm FROM roles r JOIN (
    SELECT 'storekeeper' b, 'staff' perm
    UNION ALL SELECT 'storekeeper', 'calendar.view' UNION ALL SELECT 'storekeeper', 'notices.view'
    UNION ALL SELECT 'storekeeper', 'requisitions.create' UNION ALL SELECT 'storekeeper', 'purchasing.receive'
    UNION ALL SELECT 'auditor', 'staff' UNION ALL SELECT 'auditor', 'finance.view' UNION ALL SELECT 'auditor', 'fees.view'
    UNION ALL SELECT 'auditor', 'payroll.view' UNION ALL SELECT 'auditor', 'requisitions.create' UNION ALL SELECT 'auditor', 'requisitions.view_all'
    UNION ALL SELECT 'auditor', 'audit.view' UNION ALL SELECT 'auditor', 'analytics.view'
    UNION ALL SELECT 'bursar', 'purchasing.order' UNION ALL SELECT 'bursar', 'ledger.journal'
    UNION ALL SELECT 'head_teacher', 'purchasing.approve' UNION ALL SELECT 'head_teacher', 'ledger.approve'
    UNION ALL SELECT 'head_teacher', 'budget.caps'
) p ON p.b = r.role_key;

-- ------------------------------------------------------------------ settings
INSERT IGNORE INTO settings (`key`, `value`) VALUES
('pur_quote3_above', '5000'),
('pur_pay_approval_above', '5000'),
('fee_stage_reminders', '0'),
('fee_remind_days', '7,14,30'),
('fee_gateway_pct', '0'),
('fee_gateway_payer', 'school'),
('reserve_contingency_pct', ''),
('reserve_capital_pct', ''),
('reserve_min_months', '3');
