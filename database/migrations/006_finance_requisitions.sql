-- NISDAS SMS — Sprint 6: finance (cash book, budgets) and requisition & approval
-- Cash-basis bookkeeping: every receipt and payment of money is a transaction in one money account.
-- Fee payments are posted automatically; requisitions are paid out through the cash book.

CREATE TABLE fin_accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    kind ENUM('cash','bank','momo','online') NOT NULL DEFAULT 'cash',
    account_no VARCHAR(60) NULL,
    opening_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
    opening_date DATE NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE fin_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kind ENUM('income','expense') NOT NULL,
    code VARCHAR(12) NOT NULL UNIQUE,
    name VARCHAR(80) NOT NULL,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_fin_cat (kind, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- kind: income (money in), expense (money out), refund (money back that reduces an expense category),
--       transfer (between two of the school's accounts, e.g. banking the cash)
CREATE TABLE fin_transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    txn_no VARCHAR(30) NOT NULL UNIQUE,
    kind ENUM('income','expense','refund','transfer') NOT NULL,
    txn_date DATE NOT NULL,
    account_id INT UNSIGNED NOT NULL,
    to_account_id INT UNSIGNED NULL,
    category_id INT UNSIGNED NULL,
    amount DECIMAL(12,2) NOT NULL,
    description VARCHAR(255) NOT NULL,
    party VARCHAR(150) NULL,
    reference VARCHAR(100) NULL,
    term_id INT UNSIGNED NULL,
    source VARCHAR(20) NOT NULL DEFAULT 'manual',
    source_id INT UNSIGNED NULL,
    status ENUM('posted','void') NOT NULL DEFAULT 'posted',
    void_reason VARCHAR(255) NULL,
    voided_by INT UNSIGNED NULL,
    voided_at DATETIME NULL,
    attachment VARCHAR(120) NULL,
    attachment_name VARCHAR(160) NULL,
    attachment_mime VARCHAR(60) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_fin_source (source, source_id),
    INDEX idx_fin_date (txn_date),
    INDEX idx_fin_account (account_id, txn_date),
    INDEX idx_fin_cat (category_id, txn_date),
    CONSTRAINT fk_fin_acct FOREIGN KEY (account_id) REFERENCES fin_accounts(id),
    CONSTRAINT fk_fin_to FOREIGN KEY (to_account_id) REFERENCES fin_accounts(id),
    CONSTRAINT fk_fin_cat FOREIGN KEY (category_id) REFERENCES fin_categories(id),
    CONSTRAINT fk_fin_term FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE SET NULL,
    CONSTRAINT fk_fin_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Annual budget per category for an academic year
CREATE TABLE fin_budgets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    academic_year_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    UNIQUE KEY uq_budget (academic_year_id, category_id),
    CONSTRAINT fk_bud_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE,
    CONSTRAINT fk_bud_cat FOREIGN KEY (category_id) REFERENCES fin_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Requisitions: draft → submitted → (approved_l1 → ) approved → paid → retired; or returned / rejected / cancelled
CREATE TABLE requisitions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    req_no VARCHAR(30) NOT NULL UNIQUE,
    title VARCHAR(150) NOT NULL,
    purpose TEXT NULL,
    department VARCHAR(80) NULL,
    category_id INT UNSIGNED NULL,
    needed_by DATE NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    approved_amount DECIMAL(12,2) NULL,
    paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    spent_amount DECIMAL(12,2) NULL,
    status ENUM('draft','submitted','approved_l1','approved','returned','rejected','paid','retired','cancelled') NOT NULL DEFAULT 'draft',
    needs_second TINYINT(1) NOT NULL DEFAULT 0,
    requested_by INT UNSIGNED NOT NULL,
    submitted_at DATETIME NULL,
    approved_by INT UNSIGNED NULL,
    approved_at DATETIME NULL,
    second_by INT UNSIGNED NULL,
    second_at DATETIME NULL,
    paid_by INT UNSIGNED NULL,
    paid_at DATETIME NULL,
    retired_at DATETIME NULL,
    retire_note VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_req_status (status),
    INDEX idx_req_user (requested_by),
    CONSTRAINT fk_req_cat FOREIGN KEY (category_id) REFERENCES fin_categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_req_user FOREIGN KEY (requested_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE requisition_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    requisition_id INT UNSIGNED NOT NULL,
    description VARCHAR(150) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit VARCHAR(20) NULL,
    unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
    CONSTRAINT fk_ri_req FOREIGN KEY (requisition_id) REFERENCES requisitions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE requisition_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    requisition_id INT UNSIGNED NOT NULL,
    action VARCHAR(30) NOT NULL,
    from_status VARCHAR(20) NULL,
    to_status VARCHAR(20) NULL,
    note VARCHAR(500) NULL,
    user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rl_req (requisition_id),
    CONSTRAINT fk_rl_req FOREIGN KEY (requisition_id) REFERENCES requisitions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE requisition_files (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    requisition_id INT UNSIGNED NOT NULL,
    kind ENUM('quote','receipt','other') NOT NULL DEFAULT 'quote',
    original_name VARCHAR(160) NOT NULL,
    stored_name VARCHAR(120) NOT NULL,
    mime VARCHAR(60) NOT NULL,
    size INT UNSIGNED NOT NULL,
    uploaded_by INT UNSIGNED NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rf_req FOREIGN KEY (requisition_id) REFERENCES requisitions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO fin_accounts (name, kind, sort_order) VALUES
('Cash box (Accounts office)', 'cash', 1),
('School bank account', 'bank', 2),
('School MoMo wallet', 'momo', 3),
('Online collections (Paystack / Hubtel)', 'online', 4);

INSERT INTO fin_categories (kind, code, name, is_system, sort_order) VALUES
('income', 'FEES', 'School fees', 1, 1),
('income', 'DON', 'Donations & gifts', 0, 2),
('income', 'CANT', 'Canteen / tuck shop', 0, 3),
('income', 'GRANT', 'Grants & subsidies', 0, 4),
('income', 'OINC', 'Other income', 0, 9),
('expense', 'SAL', 'Salaries & allowances', 0, 1),
('expense', 'SSNIT', 'SSNIT & statutory deductions', 0, 2),
('expense', 'TLM', 'Teaching & learning materials', 0, 3),
('expense', 'FOOD', 'Feeding & kitchen supplies', 0, 4),
('expense', 'UTIL', 'Utilities (ECG, water, internet)', 0, 5),
('expense', 'REP', 'Repairs & maintenance', 0, 6),
('expense', 'STAT', 'Stationery & printing', 0, 7),
('expense', 'TRANS', 'Transport & fuel', 0, 8),
('expense', 'CLEAN', 'Cleaning & sanitation', 0, 9),
('expense', 'EVENT', 'Events, sports & excursions', 0, 10),
('expense', 'CONF', 'Conference / Union levies', 0, 11),
('expense', 'BANK', 'Bank & MoMo charges', 0, 12),
('expense', 'OEXP', 'Other expenses', 0, 19);

INSERT INTO settings (`key`, `value`) VALUES
('fin_acct_cash', (SELECT id FROM fin_accounts WHERE kind = 'cash' ORDER BY id LIMIT 1)),
('fin_acct_bank', (SELECT id FROM fin_accounts WHERE kind = 'bank' ORDER BY id LIMIT 1)),
('fin_acct_momo', (SELECT id FROM fin_accounts WHERE kind = 'momo' ORDER BY id LIMIT 1)),
('fin_acct_online', (SELECT id FROM fin_accounts WHERE kind = 'online' ORDER BY id LIMIT 1)),
('req_second_threshold', '5000'),
('req_require_quote_above', '1000');

-- Post fee payments recorded before this update (if any) into the cash book
INSERT INTO fin_transactions (txn_no, kind, txn_date, account_id, category_id, amount, description, party, reference, term_id, source, source_id, status, created_by)
SELECT pay.receipt_no, 'income', pay.paid_on,
       CASE pay.method WHEN 'cash' THEN (SELECT value FROM settings WHERE `key` = 'fin_acct_cash')
                       WHEN 'momo' THEN (SELECT value FROM settings WHERE `key` = 'fin_acct_momo')
                       WHEN 'online' THEN (SELECT value FROM settings WHERE `key` = 'fin_acct_online')
                       ELSE (SELECT value FROM settings WHERE `key` = 'fin_acct_bank') END,
       (SELECT id FROM fin_categories WHERE code = 'FEES'), pay.amount,
       CONCAT('Fees: ', p.first_name, ' ', p.last_name, ' (', i.invoice_no, ')'),
       pay.payer_name, pay.reference, i.term_id, 'fee_payment', pay.id,
       IF(pay.status = 'completed', 'posted', 'void'), pay.received_by
FROM payments pay JOIN invoices i ON i.id = pay.invoice_id JOIN pupils p ON p.id = pay.pupil_id;

