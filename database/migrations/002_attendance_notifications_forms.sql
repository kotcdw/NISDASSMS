-- NISDAS SMS — Sprint 2: attendance, notification engine (SMS / in-app), form builder

-- ---------------------------------------------------------------- Attendance
CREATE TABLE attendance_registers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id INT UNSIGNED NOT NULL,
    term_id INT UNSIGNED NULL,
    date DATE NOT NULL,
    taken_by INT UNSIGNED NULL,
    taken_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_register (class_id, date),
    INDEX idx_register_date (date),
    CONSTRAINT fk_reg_class FOREIGN KEY (class_id) REFERENCES classes(id),
    CONSTRAINT fk_reg_term FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE SET NULL,
    CONSTRAINT fk_reg_user FOREIGN KEY (taken_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE attendance_records (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    register_id INT UNSIGNED NOT NULL,
    pupil_id INT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    status ENUM('present','absent','late','excused','sick') NOT NULL DEFAULT 'present',
    note VARCHAR(160) NULL,
    UNIQUE KEY uq_att (pupil_id, date),
    INDEX idx_att_register (register_id),
    CONSTRAINT fk_att_register FOREIGN KEY (register_id) REFERENCES attendance_registers(id) ON DELETE CASCADE,
    CONSTRAINT fk_att_pupil FOREIGN KEY (pupil_id) REFERENCES pupils(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE staff_attendance (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    time_in TIME NULL,
    time_out TIME NULL,
    ip VARCHAR(45) NULL,
    UNIQUE KEY uq_staff_att (user_id, date),
    CONSTRAINT fk_staffatt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Notifications
CREATE TABLE notification_templates (
    `key` VARCHAR(60) PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    sms_body VARCHAR(480) NOT NULL,
    placeholders VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notification_outbox (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    channel ENUM('sms','email') NOT NULL DEFAULT 'sms',
    recipient VARCHAR(190) NOT NULL,
    recipient_name VARCHAR(150) NULL,
    subject VARCHAR(190) NULL,
    body TEXT NOT NULL,
    event_key VARCHAR(60) NULL,
    entity VARCHAR(60) NULL,
    entity_id INT UNSIGNED NULL,
    batch_id INT UNSIGNED NULL,
    is_urgent TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('queued','sent','failed','cancelled') NOT NULL DEFAULT 'queued',
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    last_error VARCHAR(255) NULL,
    provider VARCHAR(30) NULL,
    provider_ref VARCHAR(120) NULL,
    scheduled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_outbox_queue (status, scheduled_at),
    INDEX idx_outbox_entity (entity, entity_id, event_key),
    INDEX idx_outbox_batch (batch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE message_batches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    audience VARCHAR(60) NOT NULL,
    audience_label VARCHAR(150) NULL,
    body TEXT NOT NULL,
    recipients INT UNSIGNED NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_batch_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inapp_notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(150) NOT NULL,
    body VARCHAR(500) NULL,
    link VARCHAR(190) NULL,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_inapp_user (user_id, read_at),
    CONSTRAINT fk_inapp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE calendar_events
    ADD COLUMN notify_parents TINYINT(1) NOT NULL DEFAULT 0 AFTER is_school_closed,
    ADD COLUMN reminder_days TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER notify_parents,
    ADD COLUMN reminded_at DATETIME NULL AFTER reminder_days;

-- ---------------------------------------------------------------- Form builder
CREATE TABLE forms (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description TEXT NULL,
    public_key CHAR(24) NOT NULL UNIQUE,
    audience ENUM('public','staff') NOT NULL DEFAULT 'public',
    status ENUM('draft','published','closed') NOT NULL DEFAULT 'draft',
    collect_contact TINYINT(1) NOT NULL DEFAULT 1,
    one_per_phone TINYINT(1) NOT NULL DEFAULT 0,
    closes_on DATE NULL,
    confirmation_message VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_forms_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE form_fields (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    form_id INT UNSIGNED NOT NULL,
    label VARCHAR(190) NOT NULL,
    type ENUM('text','textarea','number','email','phone','date','select','radio','checkbox','file','section') NOT NULL,
    options TEXT NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    help_text VARCHAR(255) NULL,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    is_archived TINYINT(1) NOT NULL DEFAULT 0,
    INDEX idx_fields_form (form_id, sort_order),
    CONSTRAINT fk_fields_form FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE form_responses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    form_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    respondent_name VARCHAR(150) NULL,
    respondent_phone VARCHAR(20) NULL,
    ip VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_resp_form (form_id, created_at),
    INDEX idx_resp_phone (form_id, respondent_phone),
    CONSTRAINT fk_resp_form FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE,
    CONSTRAINT fk_resp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE form_answers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    response_id INT UNSIGNED NOT NULL,
    field_id INT UNSIGNED NOT NULL,
    value TEXT NULL,
    file_name VARCHAR(160) NULL,
    file_stored VARCHAR(120) NULL,
    file_mime VARCHAR(60) NULL,
    UNIQUE KEY uq_answer (response_id, field_id),
    CONSTRAINT fk_ans_resp FOREIGN KEY (response_id) REFERENCES form_responses(id) ON DELETE CASCADE,
    CONSTRAINT fk_ans_field FOREIGN KEY (field_id) REFERENCES form_fields(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Default SMS templates
-- Placeholders in {braces} are filled in automatically. Keep messages short: 160 characters = 1 SMS.
INSERT INTO notification_templates (`key`, name, description, sms_body, placeholders) VALUES
('attendance_absent', 'Pupil absent', 'Sent to the primary guardian when a pupil is marked absent', 'Dear {guardian}, {pupil} ({class}) was marked ABSENT today, {date}. Please contact the school if this is unexpected. - {school}', 'guardian, pupil, class, date, school'),
('attendance_late', 'Pupil late', 'Sent when a pupil is marked late', 'Dear {guardian}, {pupil} ({class}) arrived LATE to school today, {date}. - {school}', 'guardian, pupil, class, date, school'),
('application_received', 'Application received', 'Sent after an online or walk-in application is recorded', 'Dear {guardian}, we have received the admission application for {pupil} ({level}). Your reference is {ref}. - {school}', 'guardian, pupil, level, ref, school'),
('application_interview', 'Assessment / interview invitation', 'Sent when an application moves to screening or interview', 'Dear {guardian}, {pupil} is invited for the entrance assessment/interview on {interview_date}. Ref {ref}. - {school}', 'guardian, pupil, interview_date, ref, school'),
('application_offered', 'Admission offered', 'Sent when admission is offered', 'Congratulations! {pupil} has been offered admission to {level} at {school}. Please visit the school office to accept. Ref {ref}.', 'guardian, pupil, level, ref, school'),
('application_rejected', 'Not admitted', 'Sent when an application is not successful', 'Dear {guardian}, we regret that we cannot offer {pupil} admission at this time. Ref {ref}. Thank you for choosing {school}.', 'guardian, pupil, ref, school'),
('pupil_enrolled', 'Welcome / enrolled', 'Sent when a pupil is enrolled', 'Welcome to {school}! {pupil} is enrolled in {class}. Admission number: {admission_no}. God bless you.', 'guardian, pupil, class, admission_no, school'),
('event_reminder', 'Calendar event reminder', 'Sent to parents before calendar events marked "notify parents"', 'Reminder from {school}: {event} on {event_date}. {details}', 'event, event_date, details, school');

INSERT INTO settings (`key`, `value`) VALUES
('sms_provider', 'log'),
('sms_sender_id', 'NISDAS'),
('sms_api_key', ''),
('sms_client_id', ''),
('sms_client_secret', ''),
('sms_mnotify_url', 'https://api.mnotify.com/api/sms/quick'),
('notify_absent', '1'),
('notify_late', '0'),
('notify_admission', '1'),
('sabbath_quiet', '1'),
('sabbath_quiet_start', '18:00'),
('sabbath_quiet_end', '18:30'),
('sms_cost', '0.035');
