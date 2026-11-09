-- NISDAS SMS — Finance additions: family accounts & advance payments, credit notes & fee refunds,
-- bank reconciliation, suppliers & bills to pay (with withholding tax), petty cash (imprest),
-- fixed asset register and month-end close.

-- ------------------------------------------------------------------ family accounts & advance pay
-- A "family" is a parent/guardian and every child linked to them. Money paid beyond what is owed is kept as
-- family credit and used on the next bill. method 'credit' = a bill paid from family credit (no new cash).
ALTER TABLE payments MODIFY method ENUM('cash','momo','bank','cheque','online','credit') NOT NULL;
ALTER TABLE payments ADD COLUMN family_receipt VARCHAR(30) NULL, ADD INDEX idx_pay_family (family_receipt);
-- money paid on a bill that was later moved to family credit (e.g. after a credit note or a double payment)
ALTER TABLE invoices ADD COLUMN credit_out DECIMAL(10,2) NOT NULL DEFAULT 0;

CREATE TABLE family_credits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guardian_id INT UNSIGNED NOT NULL,
    pupil_id INT UNSIGNED NULL,
    entry_date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,               -- + credit added, - credit used / refunded
    kind ENUM('advance','overpayment','applied','refund','credit_note','reversal') NOT NULL,
    receipt_no VARCHAR(30) NULL,
    method VARCHAR(10) NULL,
    reference VARCHAR(100) NULL,
    invoice_id INT UNSIGNED NULL,
    payment_id INT UNSIGNED NULL,
    refund_id INT UNSIGNED NULL,
    note VARCHAR(255) NULL,
    status ENUM('posted','reversed') NOT NULL DEFAULT 'posted',
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fc_guardian (guardian_id, status),
    INDEX idx_fc_date (entry_date),
    CONSTRAINT fk_fc_guardian FOREIGN KEY (guardian_id) REFERENCES guardians(id) ON DELETE CASCADE,
    CONSTRAINT fk_fc_pupil FOREIGN KEY (pupil_id) REFERENCES pupils(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------ credit notes & fee refunds
CREATE TABLE credit_notes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cn_no VARCHAR(30) NOT NULL UNIQUE,
    invoice_id INT UNSIGNED NOT NULL,
    pupil_id INT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    reason_kind VARCHAR(30) NOT NULL DEFAULT 'other',
    reason VARCHAR(255) NOT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    requested_by INT UNSIGNED NULL,
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decided_by INT UNSIGNED NULL,
    decided_at DATETIME NULL,
    decision_note VARCHAR(255) NULL,
    to_credit DECIMAL(10,2) NOT NULL DEFAULT 0,  -- part that became family credit (bill was already paid)
    INDEX idx_cn_status (status),
    CONSTRAINT fk_cn_inv FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    CONSTRAINT fk_cn_pupil FOREIGN KEY (pupil_id) REFERENCES pupils(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE fee_refunds (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rf_no VARCHAR(30) NOT NULL UNIQUE,
    guardian_id INT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    reason VARCHAR(255) NOT NULL,
    method ENUM('cash','momo','bank','cheque') NOT NULL DEFAULT 'cash',
    pay_to VARCHAR(150) NOT NULL,
    pay_details VARCHAR(150) NULL,
    status ENUM('pending','approved','rejected','paid') NOT NULL DEFAULT 'pending',
    requested_by INT UNSIGNED NULL,
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decided_by INT UNSIGNED NULL,
    decided_at DATETIME NULL,
    decision_note VARCHAR(255) NULL,
    account_id INT UNSIGNED NULL,
    paid_on DATE NULL,
    paid_by INT UNSIGNED NULL,
    txn_id INT UNSIGNED NULL,
    reference VARCHAR(100) NULL,
    INDEX idx_rf_status (status),
    CONSTRAINT fk_rf_guardian FOREIGN KEY (guardian_id) REFERENCES guardians(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO fin_categories (kind, code, name, is_system, sort_order) VALUES
('expense', 'FREF', 'Fee refunds', 1, 13),
('expense', 'ASSET', 'Furniture, equipment & vehicles (assets)', 0, 14);

-- ------------------------------------------------------------------ bank reconciliation
ALTER TABLE fin_transactions ADD COLUMN cleared_on DATE NULL, ADD COLUMN recon_id INT UNSIGNED NULL, ADD COLUMN petty_topup_id INT UNSIGNED NULL;

CREATE TABLE fin_reconciliations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id INT UNSIGNED NOT NULL,
    period_end DATE NOT NULL,
    statement_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
    book_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('draft','completed') NOT NULL DEFAULT 'draft',
    notes VARCHAR(255) NULL,
    prepared_by INT UNSIGNED NULL,
    completed_by INT UNSIGNED NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_recon (account_id, period_end),
    CONSTRAINT fk_recon_acct FOREIGN KEY (account_id) REFERENCES fin_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------ suppliers & bills to pay
CREATE TABLE suppliers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(120) NULL,
    phone VARCHAR(20) NULL,
    email VARCHAR(190) NULL,
    address VARCHAR(255) NULL,
    tin VARCHAR(20) NULL,
    supply_type ENUM('goods','works','services','exempt') NOT NULL DEFAULT 'goods',
    pay_details VARCHAR(150) NULL,
    category_id INT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    notes VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_supplier_name (name),
    CONSTRAINT fk_sup_cat FOREIGN KEY (category_id) REFERENCES fin_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE supplier_bills (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bill_no VARCHAR(30) NOT NULL UNIQUE,
    supplier_id INT UNSIGNED NOT NULL,
    supplier_ref VARCHAR(60) NULL,
    bill_date DATE NOT NULL,
    due_date DATE NULL,
    category_id INT UNSIGNED NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    supply_type ENUM('goods','works','services','exempt') NOT NULL DEFAULT 'goods',
    wht_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
    wht_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    paid_gross DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('unpaid','part','paid','cancelled') NOT NULL DEFAULT 'unpaid',
    requisition_id INT UNSIGNED NULL,
    attachment VARCHAR(120) NULL,
    attachment_name VARCHAR(160) NULL,
    attachment_mime VARCHAR(60) NULL,
    cancel_reason VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sb_status (status, due_date),
    CONSTRAINT fk_sb_sup FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    CONSTRAINT fk_sb_cat FOREIGN KEY (category_id) REFERENCES fin_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wht_remittances (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    wr_no VARCHAR(30) NOT NULL UNIQUE,
    remit_date DATE NOT NULL,
    account_id INT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    reference VARCHAR(100) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE supplier_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pv_no VARCHAR(30) NOT NULL UNIQUE,
    bill_id INT UNSIGNED NOT NULL,
    pay_date DATE NOT NULL,
    account_id INT UNSIGNED NOT NULL,
    gross DECIMAL(12,2) NOT NULL,
    wht DECIMAL(12,2) NOT NULL DEFAULT 0,
    net DECIMAL(12,2) NOT NULL,
    reference VARCHAR(100) NULL,
    txn_id INT UNSIGNED NULL,
    wht_remit_id INT UNSIGNED NULL,
    status ENUM('posted','void') NOT NULL DEFAULT 'posted',
    void_reason VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sp_bill (bill_id),
    CONSTRAINT fk_sp_bill FOREIGN KEY (bill_id) REFERENCES supplier_bills(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE requisitions ADD COLUMN supplier_bill_id INT UNSIGNED NULL;

-- ------------------------------------------------------------------ petty cash (imprest)
-- A petty cash box is a money account of kind 'petty' held by a custodian, with a fixed float level.
ALTER TABLE fin_accounts MODIFY kind ENUM('cash','bank','momo','online','petty') NOT NULL DEFAULT 'cash';
ALTER TABLE fin_accounts ADD COLUMN custodian_id INT UNSIGNED NULL, ADD COLUMN float_amount DECIMAL(12,2) NULL, ADD COLUMN spend_limit DECIMAL(12,2) NULL;

-- ------------------------------------------------------------------ fixed asset register
CREATE TABLE fixed_assets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asset_no VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(30) NOT NULL,
    location VARCHAR(120) NULL,
    holder_id INT UNSIGNED NULL,
    serial_no VARCHAR(80) NULL,
    supplier_id INT UNSIGNED NULL,
    funded_by VARCHAR(30) NOT NULL DEFAULT 'school',
    purchase_date DATE NOT NULL,
    cost DECIMAL(12,2) NOT NULL,
    life_years TINYINT UNSIGNED NOT NULL DEFAULT 5,
    residual DECIMAL(12,2) NOT NULL DEFAULT 0,
    condition_state ENUM('good','fair','poor') NOT NULL DEFAULT 'good',
    status ENUM('in_use','repair','disposed','lost') NOT NULL DEFAULT 'in_use',
    disposed_on DATE NULL,
    disposal_amount DECIMAL(12,2) NULL,
    disposal_note VARCHAR(255) NULL,
    notes VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fa_cat (category, status),
    CONSTRAINT fk_fa_sup FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE asset_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asset_id INT UNSIGNED NOT NULL,
    action VARCHAR(30) NOT NULL,
    note VARCHAR(255) NULL,
    user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_al_asset (asset_id),
    CONSTRAINT fk_al_asset FOREIGN KEY (asset_id) REFERENCES fixed_assets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------ month-end close
CREATE TABLE fin_period_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    month CHAR(7) NOT NULL,
    action ENUM('close','reopen') NOT NULL,
    note VARCHAR(255) NULL,
    user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------ permissions
INSERT IGNORE INTO role_permissions (role_key, permission)
SELECT r.role_key, p.perm FROM roles r JOIN (
    SELECT 'head_teacher' b, 'fees.approve' perm
    UNION ALL SELECT 'bursar', 'finance.close'
    UNION ALL SELECT 'head_teacher', 'finance.reopen'
    UNION ALL SELECT 'head_teacher', 'assets.manage'
    UNION ALL SELECT 'bursar', 'assets.manage'
) p ON p.b = r.role_key;

INSERT IGNORE INTO notification_templates (`key`, name, description, sms_body, placeholders) VALUES
('family_receipt', 'Family payment receipt', 'Sent when one payment covers several children or is kept as credit', 'Dear {guardian}, we received {amount} (receipt {receipt}). Applied to bills: {applied}. Credit kept for next bill: {credit}. Thank you. - {school}', 'guardian, amount, receipt, applied, credit, school'),
('fee_refund_paid', 'Fee refund paid', 'Sent when a fee refund is paid to a parent', 'Dear {guardian}, a refund of {amount} ({ref}) has been paid to you by {method}. - {school}', 'guardian, amount, ref, method, school');
