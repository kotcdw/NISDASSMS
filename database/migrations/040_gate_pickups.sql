-- Gate pick-up check: a Security role, people parents allow to collect a child, one-time pick-up codes,
-- and the pick-up register (who collected which child, with ID, photo, signature and the parent's approval).

INSERT IGNORE INTO roles (role_key, name, description, base_role, kind, is_system, data_scope, require_2fa, idle_minutes, sort_order, read_only) VALUES
('security', 'Security', 'Gate officer: checks who is collecting a child, records ID, photo and signature, waits for the parent''s approval, and signs visitors in and out. Sees nothing else.', 'security', 'staff', 1, 'all', 0, 30, 10, 0);

INSERT IGNORE INTO role_permissions (role_key, permission)
SELECT r.role_key, p.perm FROM roles r JOIN (
    SELECT 'security' b, 'pickup.gate' perm
    UNION ALL SELECT 'security', 'visitors.record'
    UNION ALL SELECT 'head_teacher', 'pickup.gate'
    UNION ALL SELECT 'head_teacher', 'pickup.manage'
    UNION ALL SELECT 'administrator', 'pickup.manage'
    UNION ALL SELECT 'admissions_officer', 'pickup.manage'
) p ON p.b = r.role_key;

CREATE TABLE IF NOT EXISTS pickup_people (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    phone VARCHAR(20) NULL,
    relationship VARCHAR(60) NULL,
    id_type VARCHAR(30) NULL,
    id_number VARCHAR(40) NULL,
    photo VARCHAR(120) NULL,
    photo_mime VARCHAR(40) NULL,
    added_by INT UNSIGNED NULL,
    added_via VARCHAR(10) NOT NULL DEFAULT 'parent',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_gpp_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pickup_people_pupils (
    person_id INT UNSIGNED NOT NULL,
    pupil_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (person_id, pupil_id),
    KEY idx_gppp_pupil (pupil_id),
    CONSTRAINT fk_gate_ppp_person FOREIGN KEY (person_id) REFERENCES pickup_people (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pickup_codes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pupil_id INT UNSIGNED NOT NULL,
    code_hash CHAR(64) NOT NULL,
    for_name VARCHAR(120) NULL,
    created_by INT UNSIGNED NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    used_pickup_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_gpc_pupil (pupil_id, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pickups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ref_no VARCHAR(20) NOT NULL,
    pupil_id INT UNSIGNED NOT NULL,
    person_id INT UNSIGNED NULL,
    guardian_id INT UNSIGNED NULL,
    collector_name VARCHAR(120) NOT NULL,
    phone VARCHAR(20) NULL,
    relationship VARCHAR(60) NULL,
    id_type VARCHAR(30) NULL,
    id_number VARCHAR(40) NULL,
    photo VARCHAR(120) NULL,
    photo_mime VARCHAR(40) NULL,
    signature VARCHAR(120) NULL,
    kind VARCHAR(10) NOT NULL DEFAULT 'other',
    status VARCHAR(10) NOT NULL DEFAULT 'waiting',
    approval_via VARCHAR(10) NULL,
    decided_by INT UNSIGNED NULL,
    decided_note VARCHAR(200) NULL,
    decided_at DATETIME NULL,
    token VARCHAR(40) NULL,
    token_expires DATETIME NULL,
    recorded_by INT UNSIGNED NULL,
    released_by INT UNSIGNED NULL,
    released_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pickups_ref (ref_no),
    UNIQUE KEY uq_pickups_token (token),
    KEY idx_pickups_pupil (pupil_id, created_at),
    KEY idx_pickups_day (created_at, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO notification_templates (`key`, name, description, sms_body, placeholders) VALUES
('pickup_request', 'Pick-up — approval needed', 'Sent to the parent when someone else comes to collect their child', 'Dear {guardian}, {collector} ({relationship}) is at the school gate to pick up {pupil}. Approve or refuse: {link} - {school}', 'guardian, collector, relationship, pupil, link, school'),
('pickup_done', 'Pick-up — child released', 'Sent to the parent when the child leaves with someone', 'Dear {guardian}, {pupil} was picked up by {collector} at {time}. - {school}', 'guardian, pupil, collector, time, school');
