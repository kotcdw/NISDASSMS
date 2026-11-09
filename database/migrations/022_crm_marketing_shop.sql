-- NISDAS SMS — NISDAS ENGINE: CRM (admission enquiries and follow-ups), Marketing (SMS / WhatsApp campaigns)
-- and E-Commerce (school shop: uniforms, books, stationery — paid online by Mobile Money)

CREATE TABLE IF NOT EXISTS crm_enquiries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ref_no VARCHAR(20) NOT NULL UNIQUE,
    parent_name VARCHAR(150) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(150) NULL,
    child_name VARCHAR(150) NULL,
    child_dob DATE NULL,
    level_id INT UNSIGNED NULL,
    source ENUM('phone','walk_in','website','referral','social','church','event','other') NOT NULL DEFAULT 'phone',
    stage ENUM('new','contacted','visit_booked','visited','applied','enrolled','lost') NOT NULL DEFAULT 'new',
    next_follow_up DATE NULL,
    visit_at DATETIME NULL,
    assigned_to INT UNSIGNED NULL,
    lost_reason VARCHAR(255) NULL,
    notes VARCHAR(1000) NULL,
    application_id INT UNSIGNED NULL,
    opted_out TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    INDEX idx_crm_stage (stage, next_follow_up),
    INDEX idx_crm_phone (phone),
    CONSTRAINT fk_crm_level FOREIGN KEY (level_id) REFERENCES levels(id) ON DELETE SET NULL,
    CONSTRAINT fk_crm_assigned FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_crm_app FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_activities (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    enquiry_id INT UNSIGNED NOT NULL,
    kind ENUM('call','sms','whatsapp','visit','email','note','stage') NOT NULL DEFAULT 'note',
    note VARCHAR(1000) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_crma_enq (enquiry_id, created_at),
    CONSTRAINT fk_crma_enq FOREIGN KEY (enquiry_id) REFERENCES crm_enquiries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mkt_campaigns (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    audience VARCHAR(30) NOT NULL,
    audience_value VARCHAR(60) NULL,
    custom_phones TEXT NULL,
    message VARCHAR(480) NOT NULL,
    status ENUM('draft','sent') NOT NULL DEFAULT 'draft',
    recipients INT UNSIGNED NOT NULL DEFAULT 0,
    sent_at DATETIME NULL,
    sent_by INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mkt_recipients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT UNSIGNED NOT NULL,
    phone VARCHAR(20) NOT NULL,
    name VARCHAR(150) NULL,
    enquiry_id INT UNSIGNED NULL,
    outbox_id INT UNSIGNED NULL,
    UNIQUE KEY uq_mkt_rcp (campaign_id, phone),
    CONSTRAINT fk_mktr_c FOREIGN KEY (campaign_id) REFERENCES mkt_campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mkt_optouts (
    phone VARCHAR(20) NOT NULL PRIMARY KEY,
    reason VARCHAR(120) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shop_products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    category ENUM('uniform','book','stationery','sports','other') NOT NULL DEFAULT 'other',
    description VARCHAR(255) NULL,
    price DECIMAL(10,2) NOT NULL,
    sizes VARCHAR(120) NULL,
    for_levels VARCHAR(40) NULL,
    track_stock TINYINT(1) NOT NULL DEFAULT 1,
    stock_qty INT NOT NULL DEFAULT 0,
    reorder_level INT NOT NULL DEFAULT 5,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shop_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_no VARCHAR(20) NOT NULL UNIQUE,
    pupil_id INT UNSIGNED NOT NULL,
    ordered_by INT UNSIGNED NULL,
    channel ENUM('online','counter') NOT NULL DEFAULT 'online',
    status ENUM('pending_payment','paid','ready','collected','cancelled') NOT NULL DEFAULT 'pending_payment',
    total DECIMAL(10,2) NOT NULL DEFAULT 0,
    invoice_id INT UNSIGNED NULL,
    notes VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at DATETIME NULL,
    ready_at DATETIME NULL,
    collected_at DATETIME NULL,
    collected_by VARCHAR(120) NULL,
    cancelled_at DATETIME NULL,
    cancel_reason VARCHAR(255) NULL,
    INDEX idx_shop_status (status, created_at),
    CONSTRAINT fk_shopo_pupil FOREIGN KEY (pupil_id) REFERENCES pupils(id) ON DELETE CASCADE,
    CONSTRAINT fk_shopo_inv FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shop_order_lines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL,
    size VARCHAR(20) NULL,
    qty SMALLINT UNSIGNED NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_shopl_order FOREIGN KEY (order_id) REFERENCES shop_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_shopl_prod FOREIGN KEY (product_id) REFERENCES shop_products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shop_stock_moves (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    qty INT NOT NULL,
    reason VARCHAR(120) NOT NULL,
    order_id INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_shops_prod FOREIGN KEY (product_id) REFERENCES shop_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE invoices ADD COLUMN source VARCHAR(20) NULL;
INSERT IGNORE INTO fin_categories (kind, code, name, is_system, sort_order) VALUES ('income', 'SHOP', 'School shop (uniforms, books)', 1, 5);

INSERT IGNORE INTO shop_products (name, category, description, price, sizes, for_levels, stock_qty, reorder_level) VALUES
('School uniform (dress / shirt and shorts)', 'uniform', 'Official NISDAS uniform', 120.00, '4-5,6-7,8-9,10-11,12-13,14-15', 'kg,primary,jhs', 40, 8),
('Sabbath / worship uniform', 'uniform', 'White uniform for Sabbath and special days', 110.00, '4-5,6-7,8-9,10-11,12-13,14-15', 'kg,primary,jhs', 30, 6),
('Sports T-shirt (house colour)', 'sports', 'House colours: tell us the house in the note', 60.00, 'S,M,L,XL', NULL, 50, 10),
('School crest badge', 'uniform', NULL, 15.00, NULL, NULL, 100, 20),
('Exercise books (pack of 10)', 'stationery', '80-page exercise books', 45.00, NULL, NULL, 80, 15),
('Mathematical set', 'stationery', NULL, 25.00, NULL, 'primary,jhs', 30, 5),
('Creche / KG activity pack', 'book', 'Crayons, colouring book and workbook', 85.00, NULL, 'creche,kg', 20, 5);

INSERT IGNORE INTO notification_templates (`key`, name, description, sms_body, placeholders) VALUES
('shop_ready', 'Shop order ready', 'Sent to the parent when a school shop order is ready to collect', 'Dear {guardian}, {pupil}''s school shop order {order} is ready. Collect it at the accounts office. - {school}', 'guardian, pupil, order, school'),
('crm_apply_link', 'Enquiry: application link', 'Sent from an enquiry to give the parent the online application form', 'Dear {parent}, thank you for your interest in {school}. Apply online here: {link} . For help call {school_phone}.', 'parent, link, school, school_phone');

INSERT IGNORE INTO role_permissions (role_key, permission)
SELECT r.role_key, p.perm FROM roles r JOIN (
    SELECT 'head_teacher' b, 'crm.manage' perm UNION ALL SELECT 'admissions_officer', 'crm.manage'
    UNION ALL SELECT 'head_teacher', 'marketing.send' UNION ALL SELECT 'admissions_officer', 'marketing.send'
    UNION ALL SELECT 'head_teacher', 'shop.manage' UNION ALL SELECT 'bursar', 'shop.manage'
    UNION ALL SELECT 'parent', 'shop.order'
) p ON p.b = r.role_key
WHERE r.is_system = 1;
