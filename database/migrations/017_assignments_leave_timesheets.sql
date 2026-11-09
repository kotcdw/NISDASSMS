-- NISDAS SMS — Assignments (homework), pupil leave requests, staff shifts & timesheets
-- * Teachers set assignments for a class/subject; pupils hand in online or on paper; teachers mark and return them
-- * Parents ask for leave for a child; the class teacher or Head approves; approved days show as Excused on the register
-- * Staff shifts (start/end times), monthly timesheets from sign-in/out, approved by the Head;
--   staff paid by the day are paid from their approved timesheet

CREATE TABLE IF NOT EXISTS assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id INT UNSIGNED NOT NULL,
    class_subject_id INT UNSIGNED NULL,
    term_id INT UNSIGNED NULL,
    title VARCHAR(150) NOT NULL,
    instructions TEXT NULL,
    attachment VARCHAR(120) NULL,
    attachment_name VARCHAR(160) NULL,
    attachment_mime VARCHAR(60) NULL,
    set_on DATE NOT NULL,
    due_on DATE NOT NULL,
    max_score DECIMAL(6,2) NULL,
    submit_online TINYINT(1) NOT NULL DEFAULT 1,
    status ENUM('draft','published','closed') NOT NULL DEFAULT 'published',
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_asg_class (class_id, due_on),
    CONSTRAINT fk_asg_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_asg_cs FOREIGN KEY (class_subject_id) REFERENCES class_subjects(id) ON DELETE SET NULL,
    CONSTRAINT fk_asg_term FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE SET NULL,
    CONSTRAINT fk_asg_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assignment_submissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT UNSIGNED NOT NULL,
    pupil_id INT UNSIGNED NOT NULL,
    status ENUM('submitted','late','marked','returned','missing') NOT NULL DEFAULT 'submitted',
    body TEXT NULL,
    file VARCHAR(120) NULL,
    file_name VARCHAR(160) NULL,
    file_mime VARCHAR(60) NULL,
    on_paper TINYINT(1) NOT NULL DEFAULT 0,
    submitted_at DATETIME NULL,
    score DECIMAL(6,2) NULL,
    feedback VARCHAR(1000) NULL,
    marked_by INT UNSIGNED NULL,
    marked_at DATETIME NULL,
    UNIQUE KEY uq_asg_pupil (assignment_id, pupil_id),
    CONSTRAINT fk_asub_asg FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    CONSTRAINT fk_asub_pupil FOREIGN KEY (pupil_id) REFERENCES pupils(id) ON DELETE CASCADE,
    CONSTRAINT fk_asub_marker FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leave_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pupil_id INT UNSIGNED NOT NULL,
    from_date DATE NOT NULL,
    to_date DATE NOT NULL,
    kind ENUM('sick','family','travel','funeral','religious','other') NOT NULL DEFAULT 'other',
    reason VARCHAR(500) NOT NULL,
    attachment VARCHAR(120) NULL,
    attachment_name VARCHAR(160) NULL,
    attachment_mime VARCHAR(60) NULL,
    requested_by INT UNSIGNED NULL,
    status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    decided_by INT UNSIGNED NULL,
    decided_at DATETIME NULL,
    decision_note VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_leave_pupil (pupil_id, from_date),
    INDEX idx_leave_status (status),
    CONSTRAINT fk_leave_pupil FOREIGN KEY (pupil_id) REFERENCES pupils(id) ON DELETE CASCADE,
    CONSTRAINT fk_leave_req FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_leave_dec FOREIGN KEY (decided_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_shifts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(60) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    days VARCHAR(20) NOT NULL DEFAULT '1,2,3,4,5',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_shift_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO staff_shifts (name, start_time, end_time, days) VALUES
('Teaching day', '07:30:00', '15:30:00', '1,2,3,4,5'),
('Office day', '08:00:00', '16:30:00', '1,2,3,4,5'),
('Early (kitchen & cleaning)', '06:00:00', '14:00:00', '1,2,3,4,5'),
('Security night', '18:00:00', '06:00:00', '1,2,3,4,5,6,7');

CREATE TABLE IF NOT EXISTS staff_shift_assign (
    user_id INT UNSIGNED NOT NULL PRIMARY KEY,
    shift_id INT UNSIGNED NOT NULL,
    CONSTRAINT fk_ssa_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ssa_shift FOREIGN KEY (shift_id) REFERENCES staff_shifts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS timesheets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    period CHAR(7) NOT NULL,
    days_worked DECIMAL(5,1) NOT NULL DEFAULT 0,
    hours DECIMAL(7,2) NOT NULL DEFAULT 0,
    late_days INT NOT NULL DEFAULT 0,
    status ENUM('approved','returned') NOT NULL DEFAULT 'approved',
    note VARCHAR(255) NULL,
    approved_by INT UNSIGNED NULL,
    approved_at DATETIME NULL,
    UNIQUE KEY uq_ts (user_id, period),
    CONSTRAINT fk_ts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ts_appr FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE staff_attendance ADD COLUMN note VARCHAR(160) NULL;
ALTER TABLE staff_attendance ADD COLUMN corrected_by INT UNSIGNED NULL;
ALTER TABLE staff ADD COLUMN pay_basis ENUM('monthly','daily') NOT NULL DEFAULT 'monthly';
ALTER TABLE staff ADD COLUMN daily_rate DECIMAL(10,2) NULL;

INSERT IGNORE INTO role_permissions (role_key, permission)
SELECT r.role_key, p.perm FROM roles r JOIN (
    SELECT 'head_teacher' b, 'assignments.manage' perm UNION ALL SELECT 'academic_coordinator', 'assignments.manage'
    UNION ALL SELECT 'class_teacher', 'assignments.manage' UNION ALL SELECT 'subject_teacher', 'assignments.manage'
    UNION ALL SELECT 'head_teacher', 'leave.approve' UNION ALL SELECT 'academic_coordinator', 'leave.approve' UNION ALL SELECT 'class_teacher', 'leave.approve'
    UNION ALL SELECT 'head_teacher', 'timesheets.manage'
    UNION ALL SELECT 'pupil', 'assignments.submit'
) p ON p.b = r.role_key
WHERE r.is_system = 1;
