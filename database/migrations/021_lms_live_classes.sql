-- NISDAS SMS — NISDAS ENGINE: LMS (learning materials: notes, videos, links, files) and
-- Live Classroom (Zoom / Google Meet / Microsoft Teams meeting links with join register)

CREATE TABLE IF NOT EXISTS lms_materials (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id INT UNSIGNED NOT NULL,
    class_subject_id INT UNSIGNED NULL,
    term_id INT UNSIGNED NULL,
    strand_id INT UNSIGNED NULL,
    title VARCHAR(150) NOT NULL,
    kind ENUM('note','video','link','file') NOT NULL DEFAULT 'note',
    body TEXT NULL,
    url VARCHAR(500) NULL,
    attachment VARCHAR(120) NULL,
    attachment_name VARCHAR(160) NULL,
    attachment_mime VARCHAR(60) NULL,
    status ENUM('draft','published') NOT NULL DEFAULT 'published',
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    INDEX idx_lms_class (class_id, status),
    CONSTRAINT fk_lms_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_lms_cs FOREIGN KEY (class_subject_id) REFERENCES class_subjects(id) ON DELETE SET NULL,
    CONSTRAINT fk_lms_term FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE SET NULL,
    CONSTRAINT fk_lms_strand FOREIGN KEY (strand_id) REFERENCES curriculum_strands(id) ON DELETE SET NULL,
    CONSTRAINT fk_lms_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lms_views (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    material_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    pupil_id INT UNSIGNED NULL,
    first_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    times SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    UNIQUE KEY uq_lms_view (material_id, user_id),
    CONSTRAINT fk_lmsv_mat FOREIGN KEY (material_id) REFERENCES lms_materials(id) ON DELETE CASCADE,
    CONSTRAINT fk_lmsv_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS live_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id INT UNSIGNED NOT NULL,
    class_subject_id INT UNSIGNED NULL,
    title VARCHAR(150) NOT NULL,
    provider ENUM('zoom','meet','teams','other') NOT NULL,
    join_url VARCHAR(500) NOT NULL,
    meeting_code VARCHAR(60) NULL,
    passcode VARCHAR(40) NULL,
    starts_at DATETIME NOT NULL,
    duration_min SMALLINT UNSIGNED NOT NULL DEFAULT 40,
    notes VARCHAR(500) NULL,
    status ENUM('scheduled','cancelled') NOT NULL DEFAULT 'scheduled',
    created_by INT UNSIGNED NULL,
    reminded_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_live_class (class_id, starts_at),
    CONSTRAINT fk_live_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_live_cs FOREIGN KEY (class_subject_id) REFERENCES class_subjects(id) ON DELETE SET NULL,
    CONSTRAINT fk_live_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS live_attendance (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id INT UNSIGNED NOT NULL,
    pupil_id INT UNSIGNED NOT NULL,
    joined_by INT UNSIGNED NOT NULL,
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    times SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    UNIQUE KEY uq_live_att (session_id, pupil_id),
    CONSTRAINT fk_la_session FOREIGN KEY (session_id) REFERENCES live_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_la_pupil FOREIGN KEY (pupil_id) REFERENCES pupils(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO notification_templates (`key`, name, description, sms_body, placeholders) VALUES
('live_class', 'Live class', 'Sent to parents when a teacher schedules an online class (if the teacher ticks "text parents")', 'Dear parent, {pupil_class} has an online {provider} class "{title}" on {when}. Join: {link} - {school}', 'pupil_class, provider, title, when, link, school');

INSERT IGNORE INTO role_permissions (role_key, permission)
SELECT r.role_key, p.perm FROM roles r JOIN (
    SELECT 'head_teacher' b, 'lms.manage' perm UNION ALL SELECT 'academic_coordinator', 'lms.manage' UNION ALL SELECT 'class_teacher', 'lms.manage' UNION ALL SELECT 'subject_teacher', 'lms.manage'
    UNION ALL SELECT 'head_teacher', 'live.manage' UNION ALL SELECT 'academic_coordinator', 'live.manage' UNION ALL SELECT 'class_teacher', 'live.manage' UNION ALL SELECT 'subject_teacher', 'live.manage'
    UNION ALL SELECT 'pupil', 'lms.view' UNION ALL SELECT 'pupil', 'live.join'
) p ON p.b = r.role_key
WHERE r.is_system = 1;
