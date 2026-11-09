-- NISDAS SMS — NISDAS ENGINE: Campus (blocks, rooms, room booking, maintenance requests)
-- and Documents (school document library + pupil and staff files with expiry reminders)

CREATE TABLE IF NOT EXISTS campus_blocks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(60) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    sort_order SMALLINT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE rooms MODIFY kind ENUM('classroom','lab','hall','library','office','store','other','ict_lab','chapel','staff_room','kitchen','sickbay','field') NOT NULL DEFAULT 'classroom';
ALTER TABLE rooms ADD COLUMN block_id INT UNSIGNED NULL, ADD CONSTRAINT fk_room_block FOREIGN KEY (block_id) REFERENCES campus_blocks(id) ON DELETE SET NULL;
ALTER TABLE rooms ADD COLUMN floor VARCHAR(20) NULL;
ALTER TABLE rooms ADD COLUMN bookable TINYINT(1) NOT NULL DEFAULT 0;
UPDATE rooms SET bookable = 1 WHERE kind IN ('lab','hall','library','ict_lab','chapel','field') AND bookable = 0;

CREATE TABLE IF NOT EXISTS room_bookings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id INT UNSIGNED NOT NULL,
    title VARCHAR(150) NOT NULL,
    booking_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    attendees SMALLINT UNSIGNED NULL,
    notes VARCHAR(255) NULL,
    status ENUM('booked','cancelled') NOT NULL DEFAULT 'booked',
    booked_by INT UNSIGNED NOT NULL,
    cancelled_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rb_room (room_id, booking_date),
    CONSTRAINT fk_rb_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    CONSTRAINT fk_rb_user FOREIGN KEY (booked_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS maintenance_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ref_no VARCHAR(20) NOT NULL UNIQUE,
    room_id INT UNSIGNED NULL,
    location_note VARCHAR(120) NULL,
    title VARCHAR(150) NOT NULL,
    details VARCHAR(1000) NULL,
    category ENUM('electrical','plumbing','furniture','building','ict','cleaning','security','other') NOT NULL DEFAULT 'other',
    priority ENUM('low','normal','urgent') NOT NULL DEFAULT 'normal',
    status ENUM('open','in_progress','done','cancelled') NOT NULL DEFAULT 'open',
    assigned_to VARCHAR(100) NULL,
    cost DECIMAL(10,2) NULL,
    resolution VARCHAR(500) NULL,
    photo VARCHAR(120) NULL,
    photo_name VARCHAR(160) NULL,
    photo_mime VARCHAR(60) NULL,
    reported_by INT UNSIGNED NOT NULL,
    resolved_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    resolved_at DATETIME NULL,
    INDEX idx_mr_status (status, priority),
    CONSTRAINT fk_mr_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL,
    CONSTRAINT fk_mr_user FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS school_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(160) NOT NULL,
    category ENUM('policy','circular','ges','minutes','form','certificate','other') NOT NULL DEFAULT 'other',
    reference_no VARCHAR(60) NULL,
    description VARCHAR(500) NULL,
    audience ENUM('all_staff','management') NOT NULL DEFAULT 'all_staff',
    file VARCHAR(120) NOT NULL,
    file_name VARCHAR(160) NOT NULL,
    file_mime VARCHAR(60) NOT NULL,
    file_size INT UNSIGNED NOT NULL DEFAULT 0,
    expires_on DATE NULL,
    reminded_at DATETIME NULL,
    uploaded_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    archived_at DATETIME NULL,
    INDEX idx_sd_cat (category, archived_at),
    CONSTRAINT fk_sd_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS person_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_type ENUM('pupil','staff') NOT NULL,
    owner_id INT UNSIGNED NOT NULL,
    doc_type VARCHAR(30) NOT NULL,
    title VARCHAR(160) NULL,
    file VARCHAR(120) NOT NULL,
    file_name VARCHAR(160) NOT NULL,
    file_mime VARCHAR(60) NOT NULL,
    file_size INT UNSIGNED NOT NULL DEFAULT 0,
    expires_on DATE NULL,
    reminded_at DATETIME NULL,
    uploaded_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pd_owner (owner_type, owner_id),
    INDEX idx_pd_exp (expires_on),
    CONSTRAINT fk_pd_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO role_permissions (role_key, permission)
SELECT r.role_key, p.perm FROM roles r JOIN (
    SELECT 'head_teacher' b, 'campus.view' perm UNION ALL SELECT 'academic_coordinator', 'campus.view' UNION ALL SELECT 'class_teacher', 'campus.view'
    UNION ALL SELECT 'subject_teacher', 'campus.view' UNION ALL SELECT 'bursar', 'campus.view' UNION ALL SELECT 'admissions_officer', 'campus.view'
    UNION ALL SELECT 'head_teacher', 'campus.manage' UNION ALL SELECT 'bursar', 'campus.manage'
    UNION ALL SELECT 'head_teacher', 'documents.view' UNION ALL SELECT 'academic_coordinator', 'documents.view' UNION ALL SELECT 'class_teacher', 'documents.view'
    UNION ALL SELECT 'subject_teacher', 'documents.view' UNION ALL SELECT 'bursar', 'documents.view' UNION ALL SELECT 'admissions_officer', 'documents.view'
    UNION ALL SELECT 'head_teacher', 'documents.manage' UNION ALL SELECT 'academic_coordinator', 'documents.manage' UNION ALL SELECT 'admissions_officer', 'documents.manage'
    UNION ALL SELECT 'head_teacher', 'documents.staff' UNION ALL SELECT 'bursar', 'documents.staff'
) p ON p.b = r.role_key
WHERE r.is_system = 1;
