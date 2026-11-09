-- Subject Management: weekly lesson notes with Head/Coordinator vetting, and the approved textbooks & materials register.

CREATE TABLE IF NOT EXISTS lesson_notes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    term_id INT UNSIGNED NOT NULL,
    class_subject_id INT UNSIGNED NOT NULL,
    week_no TINYINT UNSIGNED NOT NULL,
    strand_id INT UNSIGNED NULL,
    topic VARCHAR(200) NOT NULL,
    content_standard VARCHAR(255) NULL,
    indicators VARCHAR(500) NULL,
    core_competencies VARCHAR(255) NULL,
    resources VARCHAR(500) NULL,
    starter TEXT NULL,
    main_activities TEXT NULL,
    plenary TEXT NULL,
    assessment TEXT NULL,
    references_text VARCHAR(500) NULL,
    reflection TEXT NULL,
    status ENUM('draft','submitted','approved','returned') NOT NULL DEFAULT 'draft',
    submitted_at DATETIME NULL,
    reviewed_by INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    review_comment VARCHAR(500) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_note_week (term_id, class_subject_id, week_no),
    INDEX idx_note_status (term_id, status),
    CONSTRAINT fk_ln_term FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE CASCADE,
    CONSTRAINT fk_ln_cs FOREIGN KEY (class_subject_id) REFERENCES class_subjects(id) ON DELETE CASCADE,
    CONSTRAINT fk_ln_strand FOREIGN KEY (strand_id) REFERENCES curriculum_strands(id) ON DELETE SET NULL,
    CONSTRAINT fk_ln_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_ln_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subject_materials (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subject_id INT UNSIGNED NOT NULL,
    level_id INT UNSIGNED NULL,
    kind ENUM('textbook','workbook','teacher_guide','reader','other') NOT NULL DEFAULT 'textbook',
    title VARCHAR(200) NOT NULL,
    author_publisher VARCHAR(200) NULL,
    isbn VARCHAR(20) NULL,
    approved_by ENUM('nacca','ges','school') NOT NULL DEFAULT 'nacca',
    per_student TINYINT(1) NOT NULL DEFAULT 1,
    copies_held SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    notes VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mat_subject (subject_id, level_id),
    CONSTRAINT fk_mat_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    CONSTRAINT fk_mat_level FOREIGN KEY (level_id) REFERENCES levels(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO role_permissions (role_key, permission)
SELECT r.role_key, p.perm FROM roles r JOIN (
    SELECT 'head_teacher' b, 'lessonnotes.vet' perm UNION ALL SELECT 'academic_coordinator', 'lessonnotes.vet'
) p ON p.b = r.role_key;

INSERT IGNORE INTO notification_templates (`key`, name, description, sms_body, placeholders) VALUES
('lesson_notes_late', 'Lesson notes reminder', 'Reminder to a teacher whose lesson notes for the week are not submitted', 'Dear {teacher}, your lesson notes for week {week} ({subjects}) are not yet submitted. Please submit them today. - {school}', 'teacher, week, subjects, school');
