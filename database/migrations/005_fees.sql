-- NISDAS SMS — Sprint 5: fees, invoices, payments and Mobile Money
-- IMPORTANT: the fee amounts below are SAMPLE figures so the module can be tried out.
-- The Bursar must replace them with the approved fee schedule in Fees → Fee schedule.

CREATE TABLE fee_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    code VARCHAR(12) NOT NULL UNIQUE,
    frequency ENUM('termly','yearly','once') NOT NULL DEFAULT 'termly',
    discountable TINYINT(1) NOT NULL DEFAULT 0,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fee schedule: amount per level per item (termly items are billed every term,
-- yearly items in Term 1, "once" items only when a pupil is admitted)
CREATE TABLE fee_structures (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    level_id INT UNSIGNED NOT NULL,
    fee_item_id INT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    UNIQUE KEY uq_fee_structure (level_id, fee_item_id),
    CONSTRAINT fk_fs_level FOREIGN KEY (level_id) REFERENCES levels(id) ON DELETE CASCADE,
    CONSTRAINT fk_fs_item FOREIGN KEY (fee_item_id) REFERENCES fee_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE discounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    kind ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
    value DECIMAL(10,2) NOT NULL,
    is_sibling TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Discounts given to individual pupils (staff child, scholarship, bursary)
CREATE TABLE pupil_discounts (
    pupil_id INT UNSIGNED NOT NULL,
    discount_id INT UNSIGNED NOT NULL,
    note VARCHAR(160) NULL,
    PRIMARY KEY (pupil_id, discount_id),
    CONSTRAINT fk_pd_pupil FOREIGN KEY (pupil_id) REFERENCES pupils(id) ON DELETE CASCADE,
    CONSTRAINT fk_pd_discount FOREIGN KEY (discount_id) REFERENCES discounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(30) NOT NULL UNIQUE,
    public_key CHAR(24) NOT NULL UNIQUE,
    pupil_id INT UNSIGNED NOT NULL,
    term_id INT UNSIGNED NULL,
    class_id INT UNSIGNED NULL,
    kind ENUM('term','admission','other') NOT NULL DEFAULT 'term',
    title VARCHAR(150) NOT NULL,
    issue_date DATE NOT NULL,
    due_date DATE NULL,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
    discount DECIMAL(10,2) NOT NULL DEFAULT 0,
    total DECIMAL(10,2) NOT NULL DEFAULT 0,
    paid DECIMAL(10,2) NOT NULL DEFAULT 0,
    status ENUM('unpaid','part','paid','cancelled') NOT NULL DEFAULT 'unpaid',
    notes VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_inv_pupil (pupil_id, status),
    INDEX idx_inv_term (term_id, kind),
    CONSTRAINT fk_inv_pupil FOREIGN KEY (pupil_id) REFERENCES pupils(id) ON DELETE CASCADE,
    CONSTRAINT fk_inv_term FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE SET NULL,
    CONSTRAINT fk_inv_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL,
    CONSTRAINT fk_inv_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invoice_lines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    fee_item_id INT UNSIGNED NULL,
    description VARCHAR(150) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) NOT NULL DEFAULT 0,
    discount_note VARCHAR(120) NULL,
    CONSTRAINT fk_line_inv FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    CONSTRAINT fk_line_item FOREIGN KEY (fee_item_id) REFERENCES fee_items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    receipt_no VARCHAR(30) NOT NULL UNIQUE,
    invoice_id INT UNSIGNED NOT NULL,
    pupil_id INT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    method ENUM('cash','momo','bank','cheque','online') NOT NULL,
    reference VARCHAR(100) NULL,
    payer_name VARCHAR(150) NULL,
    payer_phone VARCHAR(20) NULL,
    paid_on DATE NOT NULL,
    status ENUM('completed','reversed') NOT NULL DEFAULT 'completed',
    reversal_reason VARCHAR(255) NULL,
    reversed_by INT UNSIGNED NULL,
    reversed_at DATETIME NULL,
    gateway VARCHAR(20) NULL,
    gateway_ref VARCHAR(100) NULL UNIQUE,
    received_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pay_date (paid_on),
    INDEX idx_pay_pupil (pupil_id),
    CONSTRAINT fk_pay_inv FOREIGN KEY (invoice_id) REFERENCES invoices(id),
    CONSTRAINT fk_pay_pupil FOREIGN KEY (pupil_id) REFERENCES pupils(id),
    CONSTRAINT fk_pay_user FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every attempt to pay online (Paystack / Hubtel). A payment is only recorded after
-- the gateway confirms it server-to-server.
CREATE TABLE online_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(40) NOT NULL UNIQUE,
    invoice_id INT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    gateway VARCHAR(20) NOT NULL,
    status ENUM('initiated','success','failed') NOT NULL DEFAULT 'initiated',
    payer_phone VARCHAR(20) NULL,
    checkout_url VARCHAR(500) NULL,
    gateway_message VARCHAR(255) NULL,
    payment_id INT UNSIGNED NULL,
    ip VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_op_invoice (invoice_id),
    CONSTRAINT fk_op_inv FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    CONSTRAINT fk_op_pay FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO fee_items (name, code, frequency, discountable, sort_order) VALUES
('Tuition', 'TUI', 'termly', 1, 1),
('PTA dues', 'PTA', 'termly', 0, 2),
('Feeding', 'FEED', 'termly', 0, 3),
('ICT / Computer lab', 'ICT', 'termly', 0, 4),
('Examination fee', 'EXAM', 'termly', 0, 5),
('Books & stationery', 'BOOKS', 'yearly', 0, 6),
('Admission fee', 'ADM', 'once', 0, 7);

-- SAMPLE amounts in GH₵ (replace with the school's approved schedule)
INSERT INTO fee_structures (level_id, fee_item_id, amount)
SELECT l.id, f.id,
    CASE f.code
        WHEN 'TUI' THEN CASE l.category WHEN 'creche' THEN 900 WHEN 'kg' THEN 1000 WHEN 'jhs' THEN 1400
                        ELSE IF(l.sort_order <= 6, 1100, 1200) END
        WHEN 'PTA' THEN 50
        WHEN 'FEED' THEN 600
        WHEN 'ICT' THEN IF(l.category = 'jhs' OR l.sort_order >= 7, 80, 0)
        WHEN 'EXAM' THEN IF(l.category = 'jhs', 50, 0)
        WHEN 'BOOKS' THEN IF(l.category IN ('primary','jhs'), 300, 150)
        WHEN 'ADM' THEN 200
    END
FROM levels l CROSS JOIN fee_items f;

INSERT INTO discounts (name, kind, value, is_sibling) VALUES
('Sibling discount (2nd child onwards)', 'percent', 10, 1),
('Staff child', 'percent', 50, 0),
('Scholarship', 'percent', 100, 0);

INSERT INTO notification_templates (`key`, name, description, sms_body, placeholders) VALUES
('fee_invoice', 'Fee bill issued', 'Sent when a term bill is issued', 'Dear {guardian}, {pupil}''s {title} is GHS {total}, due {due}. View bill / pay: {link} - {school}', 'guardian, pupil, title, total, due, link, school'),
('fee_receipt', 'Payment received', 'Sent after every payment', 'Dear {guardian}, we have received GHS {amount} for {pupil}. Receipt {receipt}. Balance: GHS {balance}. Thank you. - {school}', 'guardian, pupil, amount, receipt, balance, school'),
('fee_reminder', 'Fee reminder', 'Sent to parents with an outstanding balance', 'Dear {guardian}, kindly note that {pupil} has an outstanding fee balance of GHS {balance}. View / pay: {link} - {school}', 'guardian, pupil, balance, link, school');

INSERT INTO settings (`key`, `value`) VALUES
('pay_gateway', 'none'),
('paystack_secret', ''),
('hubtel_api_id', ''),
('hubtel_api_key', ''),
('hubtel_merchant', ''),
('fee_due_days', '21'),
('fee_auto_reminders', '0'),
('fee_last_auto_reminder', ''),
('fee_show_on_report', '1'),
('bursar_momo_number', ''),
('bank_details', '');
