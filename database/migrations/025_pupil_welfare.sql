-- NISDAS SMS — Pupil welfare: positive-discipline behaviour log (merits and concerns, no corporal punishment),
-- welfare cases (counselling, safeguarding, accidents/incidents, persistent absence) with a notes timeline.

CREATE TABLE IF NOT EXISTS behaviour_records (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pupil_id INT UNSIGNED NOT NULL,
    term_id INT UNSIGNED NULL,
    kind ENUM('merit','concern') NOT NULL,
    category VARCHAR(40) NOT NULL,
    description VARCHAR(500) NULL,
    action_taken VARCHAR(40) NULL,
    points TINYINT NOT NULL DEFAULT 1,
    occurred_on DATE NOT NULL,
    parent_informed TINYINT(1) NOT NULL DEFAULT 0,
    recorded_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_beh_pupil (pupil_id, occurred_on),
    INDEX idx_beh_date (occurred_on),
    CONSTRAINT fk_beh_pupil FOREIGN KEY (pupil_id) REFERENCES pupils(id) ON DELETE CASCADE,
    CONSTRAINT fk_beh_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS welfare_cases (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ref_no VARCHAR(20) NOT NULL UNIQUE,
    pupil_id INT UNSIGNED NOT NULL,
    kind ENUM('counselling','safeguarding','incident','absence','behaviour') NOT NULL,
    title VARCHAR(150) NOT NULL,
    details TEXT NULL,
    risk ENUM('low','medium','high') NOT NULL DEFAULT 'low',
    status ENUM('open','monitoring','closed') NOT NULL DEFAULT 'open',
    assigned_to INT UNSIGNED NULL,
    referred_to VARCHAR(40) NULL,
    first_aid VARCHAR(255) NULL,
    parent_informed TINYINT(1) NOT NULL DEFAULT 0,
    follow_up_on DATE NULL,
    outcome VARCHAR(500) NULL,
    auto_key VARCHAR(60) NULL,
    opened_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    closed_at DATETIME NULL,
    UNIQUE KEY uq_wel_auto (auto_key),
    INDEX idx_wel_status (status, kind),
    INDEX idx_wel_pupil (pupil_id),
    CONSTRAINT fk_wel_pupil FOREIGN KEY (pupil_id) REFERENCES pupils(id) ON DELETE CASCADE,
    CONSTRAINT fk_wel_assigned FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_wel_opened FOREIGN KEY (opened_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS welfare_notes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id INT UNSIGNED NOT NULL,
    note TEXT NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_wn_case (case_id),
    CONSTRAINT fk_wn_case FOREIGN KEY (case_id) REFERENCES welfare_cases(id) ON DELETE CASCADE,
    CONSTRAINT fk_wn_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO notification_templates (`key`, name, description, sms_body, placeholders) VALUES
('behaviour_merit', 'Behaviour: merit', 'Sent to the parent when a teacher records a merit (if ticked)', 'Dear {guardian}, well done to {pupil}: {category}. {note} - {school}', 'guardian, pupil, category, note, school'),
('behaviour_concern', 'Behaviour: concern', 'Sent to the parent when a behaviour concern is recorded (if ticked)', 'Dear {guardian}, we would like to let you know about {pupil}: {category}. Action taken: {action}. Please call the class teacher. - {school}', 'guardian, pupil, category, action, school'),
('incident_parent', 'Accident / incident', 'Sent to the parent when an accident or incident is recorded (if ticked)', 'Dear {guardian}, {pupil} had an incident at school today: {title}. First aid: {first_aid}. Please call the school on {school_phone}. - {school}', 'guardian, pupil, title, first_aid, school, school_phone');

INSERT IGNORE INTO role_permissions (role_key, permission)
SELECT r.role_key, p.perm FROM roles r JOIN (
    SELECT 'head_teacher' b, 'welfare.record' perm UNION ALL SELECT 'academic_coordinator', 'welfare.record'
    UNION ALL SELECT 'class_teacher', 'welfare.record' UNION ALL SELECT 'subject_teacher', 'welfare.record'
    UNION ALL SELECT 'head_teacher', 'welfare.manage' UNION ALL SELECT 'academic_coordinator', 'welfare.manage'
    UNION ALL SELECT 'head_teacher', 'welfare.safeguarding'
) p ON p.b = r.role_key
WHERE r.is_system = 1;
