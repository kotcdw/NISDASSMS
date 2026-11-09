-- NISDAS SMS — GES school records still missing after the 1 Nov gap check:
-- Head teacher's log book, visitors' book, staff movement book, staff leave & CPD (training) records,
-- store ledger, licences & permits register, staff contract end dates and the data-request log (Act 843).

CREATE TABLE IF NOT EXISTS head_logbook (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entry_date DATE NOT NULL,
    category ENUM('event','visit','inspection','incident','meeting','staff','pupils','premises','other') NOT NULL DEFAULT 'event',
    title VARCHAR(150) NOT NULL,
    body TEXT NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_log_date (entry_date),
    CONSTRAINT fk_hlog_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS visitors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    visitor_name VARCHAR(150) NOT NULL,
    phone VARCHAR(20) NULL,
    organisation VARCHAR(150) NULL,
    purpose ENUM('parent','official','vendor','interview','delivery','other') NOT NULL DEFAULT 'parent',
    details VARCHAR(255) NULL,
    to_see VARCHAR(150) NULL,
    pupil_id INT UNSIGNED NULL,
    badge_no VARCHAR(20) NULL,
    time_in DATETIME NOT NULL,
    time_out DATETIME NULL,
    recorded_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_vis_in (time_in),
    CONSTRAINT fk_visit_pupil FOREIGN KEY (pupil_id) REFERENCES pupils(id) ON DELETE SET NULL,
    CONSTRAINT fk_visit_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_movements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    purpose ENUM('official','personal','medical','other') NOT NULL DEFAULT 'official',
    destination VARCHAR(150) NOT NULL,
    details VARCHAR(255) NULL,
    out_at DATETIME NOT NULL,
    expected_back DATETIME NULL,
    back_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mov_out (out_at),
    INDEX idx_mov_user (user_id, back_at),
    CONSTRAINT fk_smove_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_leave (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ref_no VARCHAR(20) NOT NULL UNIQUE,
    user_id INT UNSIGNED NOT NULL,
    leave_type ENUM('annual','sick','casual','maternity','paternity','study','compassionate') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    days SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    reason VARCHAR(500) NULL,
    relief VARCHAR(150) NULL,
    attachment VARCHAR(255) NULL,
    attachment_name VARCHAR(255) NULL,
    attachment_mime VARCHAR(100) NULL,
    status ENUM('pending','approved','declined','cancelled') NOT NULL DEFAULT 'pending',
    decided_by INT UNSIGNED NULL,
    decided_at DATETIME NULL,
    decision_note VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sl_user (user_id, start_date),
    INDEX idx_sl_status (status, start_date),
    CONSTRAINT fk_sleave_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_sleave_decider FOREIGN KEY (decided_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_cpd (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(150) NOT NULL,
    provider VARCHAR(150) NULL,
    kind ENUM('workshop','inset','course','conference','online','other') NOT NULL DEFAULT 'workshop',
    start_date DATE NOT NULL,
    end_date DATE NULL,
    hours DECIMAL(5,1) NULL,
    points DECIMAL(5,1) NULL,
    certificate VARCHAR(255) NULL,
    certificate_name VARCHAR(255) NULL,
    certificate_mime VARCHAR(100) NULL,
    recorded_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cpd_user (user_id, start_date),
    CONSTRAINT fk_scpd_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_scpd_rec FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    category ENUM('stationery','cleaning','teaching','maintenance','kitchen','sports','medical','other') NOT NULL DEFAULT 'stationery',
    unit VARCHAR(30) NOT NULL DEFAULT 'piece',
    reorder_level DECIMAL(10,2) NOT NULL DEFAULT 0,
    location VARCHAR(80) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_store_item (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_moves (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_id INT UNSIGNED NOT NULL,
    kind ENUM('receive','issue','adjust') NOT NULL,
    qty DECIMAL(10,2) NOT NULL,
    unit_cost DECIMAL(12,2) NULL,
    moved_on DATE NOT NULL,
    reference VARCHAR(60) NULL,
    party VARCHAR(150) NULL,
    note VARCHAR(255) NULL,
    recorded_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sm_item (item_id, moved_on, id),
    CONSTRAINT fk_stmove_item FOREIGN KEY (item_id) REFERENCES store_items(id) ON DELETE CASCADE,
    CONSTRAINT fk_stmove_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS school_permits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    authority VARCHAR(150) NULL,
    ref_no VARCHAR(80) NULL,
    issued_on DATE NULL,
    expires_on DATE NULL,
    notes VARCHAR(500) NULL,
    document VARCHAR(255) NULL,
    document_name VARCHAR(255) NULL,
    document_mime VARCHAR(100) NULL,
    alerted_on DATE NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_permit_exp (expires_on),
    CONSTRAINT fk_spermit_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS data_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ref_no VARCHAR(20) NOT NULL UNIQUE,
    requester VARCHAR(150) NOT NULL,
    relationship ENUM('parent','staff','former_student','student','other') NOT NULL DEFAULT 'parent',
    contact VARCHAR(150) NULL,
    request_type ENUM('access','correction','deletion','objection','complaint') NOT NULL DEFAULT 'access',
    about VARCHAR(150) NULL,
    details VARCHAR(1000) NULL,
    received_on DATE NOT NULL,
    due_on DATE NOT NULL,
    status ENUM('open','closed','refused') NOT NULL DEFAULT 'open',
    response VARCHAR(1000) NULL,
    closed_on DATE NULL,
    handled_by INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_dr_status (status, due_on),
    CONSTRAINT fk_dreq_handler FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_dreq_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Staff contracts: when a contract and a probation period end (alerts 60 days before)
ALTER TABLE staff
    ADD COLUMN contract_end DATE NULL AFTER end_date,
    ADD COLUMN probation_end DATE NULL AFTER contract_end,
    ADD COLUMN contract_alerted_on DATE NULL AFTER probation_end;

INSERT IGNORE INTO settings (`key`, `value`) VALUES
('staff_leave_annual_days', '15'),
('data_request_days', '21');

INSERT IGNORE INTO role_permissions (role_key, permission)
SELECT r.role_key, p.perm FROM roles r JOIN (
    SELECT 'head_teacher' b, 'logbook.manage' perm
    UNION ALL SELECT 'head_teacher', 'visitors.record' UNION ALL SELECT 'administrator', 'visitors.record' UNION ALL SELECT 'admissions_officer', 'visitors.record'
    UNION ALL SELECT 'head_teacher', 'movement.view'
    UNION ALL SELECT 'head_teacher', 'staffleave.approve'
    UNION ALL SELECT 'head_teacher', 'cpd.view' UNION ALL SELECT 'academic_coordinator', 'cpd.view'
    UNION ALL SELECT 'storekeeper', 'stores.manage' UNION ALL SELECT 'bursar', 'stores.manage'
    UNION ALL SELECT 'storekeeper', 'stores.view' UNION ALL SELECT 'bursar', 'stores.view' UNION ALL SELECT 'head_teacher', 'stores.view' UNION ALL SELECT 'auditor', 'stores.view'
    UNION ALL SELECT 'head_teacher', 'permits.manage' UNION ALL SELECT 'administrator', 'permits.manage'
    UNION ALL SELECT 'head_teacher', 'permits.view' UNION ALL SELECT 'administrator', 'permits.view' UNION ALL SELECT 'bursar', 'permits.view'
) p ON p.b = r.role_key
WHERE r.is_system = 1;
