-- NISDAS SMS — Sprint 7: parent & pupil logins, notice board, parent–teacher messages, quizzes, promotion

-- Parents sign in with their phone number, pupils with their admission number
ALTER TABLE users ADD COLUMN username VARCHAR(60) NULL AFTER email;
ALTER TABLE users ADD UNIQUE KEY uq_users_username (username);
ALTER TABLE pupils ADD COLUMN user_id INT UNSIGNED NULL AFTER application_id;
ALTER TABLE pupils ADD CONSTRAINT fk_pupils_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

-- Notice board and circulars
CREATE TABLE notices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    body TEXT NOT NULL,
    category ENUM('notice','circular','event','urgent') NOT NULL DEFAULT 'notice',
    audience VARCHAR(30) NOT NULL DEFAULT 'all',
    requires_ack TINYINT(1) NOT NULL DEFAULT 0,
    is_pinned TINYINT(1) NOT NULL DEFAULT 0,
    publish_at DATETIME NOT NULL,
    expires_on DATE NULL,
    attachment VARCHAR(120) NULL,
    attachment_name VARCHAR(160) NULL,
    attachment_mime VARCHAR(60) NULL,
    sms_sent_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_notice_pub (publish_at),
    CONSTRAINT fk_notice_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notice_reads (
    notice_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    acknowledged_at DATETIME NULL,
    PRIMARY KEY (notice_id, user_id),
    CONSTRAINT fk_nr_notice FOREIGN KEY (notice_id) REFERENCES notices(id) ON DELETE CASCADE,
    CONSTRAINT fk_nr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Parent–teacher conversations
CREATE TABLE conversations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subject VARCHAR(150) NOT NULL,
    pupil_id INT UNSIGNED NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_message_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_closed TINYINT(1) NOT NULL DEFAULT 0,
    INDEX idx_conv_last (last_message_at),
    CONSTRAINT fk_conv_pupil FOREIGN KEY (pupil_id) REFERENCES pupils(id) ON DELETE SET NULL,
    CONSTRAINT fk_conv_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE conversation_members (
    conversation_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    last_read_at DATETIME NULL,
    PRIMARY KEY (conversation_id, user_id),
    INDEX idx_cm_user (user_id),
    CONSTRAINT fk_cm_conv FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    CONSTRAINT fk_cm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE conversation_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_msg_conv (conversation_id, id),
    CONSTRAINT fk_msg_conv FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    CONSTRAINT fk_msg_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quizzes
CREATE TABLE quizzes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    class_subject_id INT UNSIGNED NOT NULL,
    term_id INT UNSIGNED NULL,
    instructions TEXT NULL,
    time_limit SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    opens_at DATETIME NULL,
    closes_at DATETIME NULL,
    max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 1,
    shuffle TINYINT(1) NOT NULL DEFAULT 1,
    show_answers ENUM('never','after_submit','after_close') NOT NULL DEFAULT 'after_close',
    status ENUM('draft','published','closed') NOT NULL DEFAULT 'draft',
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_quiz_cs (class_subject_id, status),
    CONSTRAINT fk_quiz_cs FOREIGN KEY (class_subject_id) REFERENCES class_subjects(id) ON DELETE CASCADE,
    CONSTRAINT fk_quiz_term FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE SET NULL,
    CONSTRAINT fk_quiz_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quiz_questions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT UNSIGNED NOT NULL,
    type ENUM('mcq','truefalse','short') NOT NULL DEFAULT 'mcq',
    prompt TEXT NOT NULL,
    options TEXT NULL,
    answer VARCHAR(255) NOT NULL,
    marks DECIMAL(5,2) NOT NULL DEFAULT 1,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    CONSTRAINT fk_qq_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quiz_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT UNSIGNED NOT NULL,
    pupil_id INT UNSIGNED NOT NULL,
    attempt_no TINYINT UNSIGNED NOT NULL DEFAULT 1,
    started_at DATETIME NOT NULL,
    deadline_at DATETIME NULL,
    submitted_at DATETIME NULL,
    score DECIMAL(7,2) NULL,
    max_score DECIMAL(7,2) NULL,
    question_order TEXT NULL,
    answers TEXT NULL,
    UNIQUE KEY uq_attempt (quiz_id, pupil_id, attempt_no),
    CONSTRAINT fk_qa_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    CONSTRAINT fk_qa_pupil FOREIGN KEY (pupil_id) REFERENCES pupils(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Year-end promotion log (one row per class processed)
CREATE TABLE promotion_runs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    from_year_id INT UNSIGNED NOT NULL,
    to_year_id INT UNSIGNED NOT NULL,
    class_id INT UNSIGNED NOT NULL,
    promoted SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    repeated SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    graduated SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    left_school SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    details TEXT NULL,
    run_by INT UNSIGNED NULL,
    run_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pr_from FOREIGN KEY (from_year_id) REFERENCES academic_years(id) ON DELETE CASCADE,
    CONSTRAINT fk_pr_to FOREIGN KEY (to_year_id) REFERENCES academic_years(id) ON DELETE CASCADE,
    CONSTRAINT fk_pr_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO notification_templates (`key`, name, description, sms_body, placeholders) VALUES
('parent_login', 'Parent portal login', 'Sent when a parent account is created or its password is reset', 'Dear {guardian}, your {school} parent portal is ready: {link} Username: {username} Password: {password} (you will choose a new password). Keep it private.', 'guardian, school, link, username, password'),
('new_message', 'New message from school', 'Sent to a parent when a teacher writes to them', 'Dear {name}, you have a new message from {sender} at {school}: "{preview}" Reply here: {link}', 'name, sender, school, preview, link'),
('notice_alert', 'Notice board alert', 'Short SMS pointing parents to a new notice or circular', '{school}: {title}. Read it here: {link}', 'school, title, link');
