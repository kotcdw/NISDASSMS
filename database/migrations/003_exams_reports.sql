-- NISDAS SMS — Sprint 3: exams & assessments, grading, terminal report cards
-- All weights and grade bands are defaults and can be changed in Exams → Grading setup.

CREATE TABLE grading_schemes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category ENUM('creche','kg','primary','jhs') NOT NULL UNIQUE,
    name VARCHAR(80) NOT NULL,
    class_weight TINYINT UNSIGNED NOT NULL DEFAULT 30,
    exam_weight TINYINT UNSIGNED NOT NULL DEFAULT 70,
    pass_mark DECIMAL(5,2) NOT NULL DEFAULT 50,
    show_position TINYINT(1) NOT NULL DEFAULT 1,
    use_aggregate TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE grade_bands (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scheme_id INT UNSIGNED NOT NULL,
    min_score DECIMAL(5,2) NOT NULL,
    grade VARCHAR(10) NOT NULL,
    remark VARCHAR(60) NOT NULL,
    INDEX idx_bands_scheme (scheme_id, min_score),
    CONSTRAINT fk_bands_scheme FOREIGN KEY (scheme_id) REFERENCES grading_schemes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Which subjects each class takes, and who teaches them
CREATE TABLE class_subjects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id INT UNSIGNED NOT NULL,
    subject_id INT UNSIGNED NOT NULL,
    teacher_id INT UNSIGNED NULL,
    UNIQUE KEY uq_class_subject (class_id, subject_id),
    CONSTRAINT fk_cs_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_cs_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    CONSTRAINT fk_cs_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Score components: several "class" items (exercises, tests, projects) + the end-of-term exam
CREATE TABLE assessments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    term_id INT UNSIGNED NOT NULL,
    class_subject_id INT UNSIGNED NOT NULL,
    kind ENUM('class','exam') NOT NULL,
    title VARCHAR(80) NOT NULL,
    max_mark DECIMAL(6,2) NOT NULL,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_assess (term_id, class_subject_id),
    CONSTRAINT fk_assess_term FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE CASCADE,
    CONSTRAINT fk_assess_cs FOREIGN KEY (class_subject_id) REFERENCES class_subjects(id) ON DELETE CASCADE,
    CONSTRAINT fk_assess_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE assessment_scores (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT UNSIGNED NOT NULL,
    pupil_id INT UNSIGNED NOT NULL,
    score DECIMAL(6,2) NULL,
    is_absent TINYINT(1) NOT NULL DEFAULT 0,
    updated_by INT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_score (assessment_id, pupil_id),
    CONSTRAINT fk_score_assess FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE,
    CONSTRAINT fk_score_pupil FOREIGN KEY (pupil_id) REFERENCES pupils(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Workflow per subject per term: teacher submits, coordinator approves (locks) or returns
CREATE TABLE score_sheets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    term_id INT UNSIGNED NOT NULL,
    class_subject_id INT UNSIGNED NOT NULL,
    status ENUM('draft','submitted','approved') NOT NULL DEFAULT 'draft',
    submitted_by INT UNSIGNED NULL,
    submitted_at DATETIME NULL,
    approved_by INT UNSIGNED NULL,
    approved_at DATETIME NULL,
    return_note VARCHAR(255) NULL,
    UNIQUE KEY uq_sheet (term_id, class_subject_id),
    CONSTRAINT fk_sheet_term FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE CASCADE,
    CONSTRAINT fk_sheet_cs FOREIGN KEY (class_subject_id) REFERENCES class_subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE report_remarks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    term_id INT UNSIGNED NOT NULL,
    pupil_id INT UNSIGNED NOT NULL,
    conduct VARCHAR(60) NULL,
    attitude VARCHAR(60) NULL,
    interest VARCHAR(80) NULL,
    teacher_remark VARCHAR(255) NULL,
    head_remark VARCHAR(255) NULL,
    promotion ENUM('','promoted','repeated','on_trial') NOT NULL DEFAULT '',
    updated_by INT UNSIGNED NULL,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_remarks (term_id, pupil_id),
    CONSTRAINT fk_rem_term FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE CASCADE,
    CONSTRAINT fk_rem_pupil FOREIGN KEY (pupil_id) REFERENCES pupils(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE report_releases (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    term_id INT UNSIGNED NOT NULL,
    class_id INT UNSIGNED NOT NULL,
    released_by INT UNSIGNED NULL,
    released_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_release (term_id, class_id),
    CONSTRAINT fk_rel_term FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE CASCADE,
    CONSTRAINT fk_rel_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Private link sent to parents by SMS to view a released report card
CREATE TABLE report_links (
    token CHAR(24) PRIMARY KEY,
    term_id INT UNSIGNED NOT NULL,
    pupil_id INT UNSIGNED NOT NULL,
    views INT UNSIGNED NOT NULL DEFAULT 0,
    last_viewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_link (term_id, pupil_id),
    CONSTRAINT fk_link_term FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE CASCADE,
    CONSTRAINT fk_link_pupil FOREIGN KEY (pupil_id) REFERENCES pupils(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE subjects ADD COLUMN is_core TINYINT(1) NOT NULL DEFAULT 0 AFTER applies_to;
UPDATE subjects SET is_core = 1 WHERE code IN ('ENG','MATH','SCI','SOC');

-- Default grading. Basic 1–6 / KG / Creche: NaCCA standards-based proficiency levels.
-- JHS: BECE-style 1–9 grades with an aggregate of best six (4 core + 2 others).
INSERT INTO grading_schemes (category, name, class_weight, exam_weight, pass_mark, show_position, use_aggregate) VALUES
('creche', 'Creche', 30, 70, 50, 0, 0),
('kg', 'Kindergarten', 30, 70, 50, 0, 0),
('primary', 'Basic 1–6 (proficiency levels)', 30, 70, 50, 1, 0),
('jhs', 'JHS (BECE grades 1–9)', 30, 70, 40, 1, 1);

INSERT INTO grade_bands (scheme_id, min_score, grade, remark)
SELECT s.id, b.min_score, b.grade, b.remark FROM grading_schemes s
JOIN (
    SELECT 80 AS min_score, 'A' AS grade, 'Advanced' AS remark UNION ALL
    SELECT 75, 'P', 'Proficient' UNION ALL
    SELECT 70, 'AP', 'Approaching Proficiency' UNION ALL
    SELECT 65, 'D', 'Developing' UNION ALL
    SELECT 0, 'B', 'Beginning'
) b WHERE s.category IN ('creche','kg','primary');

INSERT INTO grade_bands (scheme_id, min_score, grade, remark)
SELECT s.id, b.min_score, b.grade, b.remark FROM grading_schemes s
JOIN (
    SELECT 80 AS min_score, '1' AS grade, 'Excellent' AS remark UNION ALL
    SELECT 70, '2', 'Very Good' UNION ALL
    SELECT 60, '3', 'Good' UNION ALL
    SELECT 55, '4', 'Credit' UNION ALL
    SELECT 50, '5', 'Credit' UNION ALL
    SELECT 45, '6', 'Pass' UNION ALL
    SELECT 40, '7', 'Pass' UNION ALL
    SELECT 35, '8', 'Pass' UNION ALL
    SELECT 0, '9', 'Fail'
) b WHERE s.category = 'jhs';

-- Give every class its default subjects (by department); edit in Exams → Subjects & teachers
INSERT IGNORE INTO class_subjects (class_id, subject_id)
SELECT c.id, s.id FROM classes c JOIN levels l ON l.id = c.level_id
JOIN subjects s ON s.is_active = 1 AND FIND_IN_SET(l.category, s.applies_to) > 0;

INSERT INTO notification_templates (`key`, name, description, sms_body, placeholders) VALUES
('results_released', 'Report card released', 'Sent to parents when terminal reports are released', 'Dear {guardian}, {pupil}''s {term} report is ready. Average: {average}%{position}. View it here: {link} - {school}', 'guardian, pupil, term, average, position, link, school');
