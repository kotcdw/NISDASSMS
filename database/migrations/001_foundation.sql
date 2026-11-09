-- NISDAS SMS — Sprint 1: foundation, academic calendar, admission, pupils
-- Charset utf8mb4 so names with Ghanaian characters (Ɛ, Ɔ) store correctly.

CREATE TABLE settings (
    `key` VARCHAR(80) PRIMARY KEY,
    `value` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    phone VARCHAR(20) NULL,
    role VARCHAR(40) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(190) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    attempted_at DATETIME NOT NULL,
    INDEX idx_la_identifier (identifier, attempted_at),
    INDEX idx_la_ip (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE academic_years (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(20) NOT NULL UNIQUE,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_current TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE terms (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    academic_year_id INT UNSIGNED NOT NULL,
    term_no TINYINT UNSIGNED NOT NULL,
    name VARCHAR(30) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_current TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY uq_term (academic_year_id, term_no),
    CONSTRAINT fk_terms_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE levels (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(40) NOT NULL UNIQUE,
    code VARCHAR(10) NOT NULL UNIQUE,
    category ENUM('creche','kg','primary','jhs') NOT NULL,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    min_age_months SMALLINT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE classes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    level_id INT UNSIGNED NOT NULL,
    name VARCHAR(40) NOT NULL UNIQUE,
    section VARCHAR(10) NULL,
    class_teacher_id INT UNSIGNED NULL,
    capacity SMALLINT UNSIGNED NOT NULL DEFAULT 40,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_classes_level FOREIGN KEY (level_id) REFERENCES levels(id),
    CONSTRAINT fk_classes_teacher FOREIGN KEY (class_teacher_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subjects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    code VARCHAR(12) NOT NULL UNIQUE,
    applies_to VARCHAR(60) NOT NULL DEFAULT 'primary,jhs',
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE calendar_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description TEXT NULL,
    category VARCHAR(30) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    start_time TIME NULL,
    end_time TIME NULL,
    audience ENUM('all','staff','parents','level') NOT NULL DEFAULT 'all',
    level_id INT UNSIGNED NULL,
    term_id INT UNSIGNED NULL,
    is_school_closed TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_events_dates (start_date, end_date),
    CONSTRAINT fk_events_level FOREIGN KEY (level_id) REFERENCES levels(id) ON DELETE SET NULL,
    CONSTRAINT fk_events_term FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE SET NULL,
    CONSTRAINT fk_events_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE number_sequences (
    name VARCHAR(60) PRIMARY KEY,
    next_value INT UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pupils (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admission_no VARCHAR(30) NOT NULL UNIQUE,
    first_name VARCHAR(80) NOT NULL,
    middle_name VARCHAR(80) NULL,
    last_name VARCHAR(80) NOT NULL,
    gender ENUM('male','female') NOT NULL,
    date_of_birth DATE NOT NULL,
    photo_path VARCHAR(120) NULL,
    house VARCHAR(40) NULL,
    denomination VARCHAR(80) NULL,
    nationality VARCHAR(60) NULL DEFAULT 'Ghanaian',
    hometown VARCHAR(80) NULL,
    address VARCHAR(255) NULL,
    medical_notes TEXT NULL,
    allergies VARCHAR(255) NULL,
    previous_school VARCHAR(150) NULL,
    status ENUM('active','withdrawn','transferred','graduated') NOT NULL DEFAULT 'active',
    admitted_on DATE NOT NULL,
    left_on DATE NULL,
    application_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    INDEX idx_pupils_name (last_name, first_name),
    INDEX idx_pupils_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE guardians (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    phone_alt VARCHAR(20) NULL,
    email VARCHAR(190) NULL,
    occupation VARCHAR(100) NULL,
    address VARCHAR(255) NULL,
    user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_guardians_phone (phone),
    CONSTRAINT fk_guardians_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pupil_guardian (
    pupil_id INT UNSIGNED NOT NULL,
    guardian_id INT UNSIGNED NOT NULL,
    relationship VARCHAR(40) NOT NULL DEFAULT 'Parent',
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (pupil_id, guardian_id),
    CONSTRAINT fk_pg_pupil FOREIGN KEY (pupil_id) REFERENCES pupils(id) ON DELETE CASCADE,
    CONSTRAINT fk_pg_guardian FOREIGN KEY (guardian_id) REFERENCES guardians(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE enrolments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pupil_id INT UNSIGNED NOT NULL,
    class_id INT UNSIGNED NOT NULL,
    academic_year_id INT UNSIGNED NOT NULL,
    status ENUM('active','promoted','repeated','left') NOT NULL DEFAULT 'active',
    enrolled_on DATE NOT NULL,
    UNIQUE KEY uq_enrol (pupil_id, academic_year_id),
    INDEX idx_enrol_class (class_id, academic_year_id),
    CONSTRAINT fk_enrol_pupil FOREIGN KEY (pupil_id) REFERENCES pupils(id) ON DELETE CASCADE,
    CONSTRAINT fk_enrol_class FOREIGN KEY (class_id) REFERENCES classes(id),
    CONSTRAINT fk_enrol_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE applications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ref_no VARCHAR(30) NOT NULL UNIQUE,
    academic_year_id INT UNSIGNED NOT NULL,
    level_id INT UNSIGNED NOT NULL,
    first_name VARCHAR(80) NOT NULL,
    middle_name VARCHAR(80) NULL,
    last_name VARCHAR(80) NOT NULL,
    gender ENUM('male','female') NOT NULL,
    date_of_birth DATE NOT NULL,
    nationality VARCHAR(60) NULL DEFAULT 'Ghanaian',
    hometown VARCHAR(80) NULL,
    denomination VARCHAR(80) NULL,
    previous_school VARCHAR(150) NULL,
    medical_notes TEXT NULL,
    allergies VARCHAR(255) NULL,
    guardian_name VARCHAR(150) NOT NULL,
    guardian_relationship VARCHAR(40) NOT NULL,
    guardian_phone VARCHAR(20) NOT NULL,
    guardian_phone_alt VARCHAR(20) NULL,
    guardian_email VARCHAR(190) NULL,
    guardian_occupation VARCHAR(100) NULL,
    guardian_address VARCHAR(255) NULL,
    stage ENUM('submitted','screening','interview','offered','accepted','enrolled','rejected','withdrawn') NOT NULL DEFAULT 'submitted',
    source ENUM('online','walk_in') NOT NULL DEFAULT 'online',
    entrance_score DECIMAL(5,2) NULL,
    interview_date DATE NULL,
    notes TEXT NULL,
    pupil_id INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_app_stage (stage),
    INDEX idx_app_phone (guardian_phone),
    CONSTRAINT fk_app_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id),
    CONSTRAINT fk_app_level FOREIGN KEY (level_id) REFERENCES levels(id),
    CONSTRAINT fk_app_pupil FOREIGN KEY (pupil_id) REFERENCES pupils(id) ON DELETE SET NULL,
    CONSTRAINT fk_app_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE pupils ADD CONSTRAINT fk_pupils_application FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE SET NULL;

CREATE TABLE application_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED NOT NULL,
    doc_type VARCHAR(40) NOT NULL,
    original_name VARCHAR(160) NOT NULL,
    stored_name VARCHAR(120) NOT NULL,
    mime VARCHAR(60) NOT NULL,
    size INT UNSIGNED NOT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_doc_app FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE application_stage_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED NOT NULL,
    from_stage VARCHAR(20) NULL,
    to_stage VARCHAR(20) NOT NULL,
    note VARCHAR(255) NULL,
    changed_by INT UNSIGNED NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_log_app FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    CONSTRAINT fk_log_user FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(60) NOT NULL,
    entity VARCHAR(60) NULL,
    entity_id INT UNSIGNED NULL,
    details TEXT NULL,
    ip VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_entity (entity, entity_id),
    INDEX idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ghana basic-school levels, Creche to JHS 3
INSERT INTO levels (name, code, category, sort_order, min_age_months) VALUES
('Creche', 'CR', 'creche', 1, 6),
('KG 1', 'KG1', 'kg', 2, 48),
('KG 2', 'KG2', 'kg', 3, 60),
('Basic 1', 'B1', 'primary', 4, 72),
('Basic 2', 'B2', 'primary', 5, NULL),
('Basic 3', 'B3', 'primary', 6, NULL),
('Basic 4', 'B4', 'primary', 7, NULL),
('Basic 5', 'B5', 'primary', 8, NULL),
('Basic 6', 'B6', 'primary', 9, NULL),
('JHS 1', 'JHS1', 'jhs', 10, NULL),
('JHS 2', 'JHS2', 'jhs', 11, NULL),
('JHS 3', 'JHS3', 'jhs', 12, NULL);

-- One class (stream) per level to start; add sections (e.g. "JHS 1B") in Setup
INSERT INTO classes (level_id, name, section) SELECT id, name, NULL FROM levels ORDER BY sort_order;

-- Default subjects (edit to match the GES curriculum the school follows)
INSERT INTO subjects (name, code, applies_to) VALUES
('Language and Literacy', 'LL', 'creche,kg'),
('Numeracy', 'NUM', 'creche,kg'),
('Our World Our People', 'OWOP', 'kg,primary'),
('English Language', 'ENG', 'primary,jhs'),
('Ghanaian Language', 'GHL', 'primary,jhs'),
('Mathematics', 'MATH', 'primary,jhs'),
('Science', 'SCI', 'primary,jhs'),
('History', 'HIST', 'primary'),
('Social Studies', 'SOC', 'jhs'),
('Religious and Moral Education', 'RME', 'primary,jhs'),
('Creative Arts', 'CA', 'kg,primary'),
('Creative Arts and Design', 'CAD', 'jhs'),
('Physical and Health Education', 'PHE', 'kg,primary,jhs'),
('Computing', 'COMP', 'primary,jhs'),
('Career Technology', 'CT', 'jhs'),
('French', 'FRE', 'primary,jhs'),
('Bible Studies', 'BIB', 'creche,kg,primary,jhs');

INSERT INTO settings (`key`, `value`) VALUES
('school_name', 'NISDAS'),
('school_motto', ''),
('school_address', ''),
('school_phone', ''),
('school_email', ''),
('admission_prefix', 'NIS'),
('online_admission_open', '1'),
('sabbath_guard', '1');
