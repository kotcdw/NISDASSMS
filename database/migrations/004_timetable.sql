-- NISDAS SMS — Sprint 4: time table (bell schedules, weekly lesson plan, class timetables)

-- Bell schedule per department. Creche/KG have shorter days than Basic/JHS.
CREATE TABLE timetable_periods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category ENUM('creche','kg','primary','jhs') NOT NULL,
    sort_order SMALLINT NOT NULL,
    label VARCHAR(40) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    kind ENUM('lesson','worship','break','lunch','assembly','other') NOT NULL DEFAULT 'lesson',
    INDEX idx_periods_cat (category, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One weekly timetable per class (Mon–Fri). The teacher is copied from the subject
-- allocation so teacher clashes can be checked with a simple unique lookup.
CREATE TABLE timetable_slots (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id INT UNSIGNED NOT NULL,
    day TINYINT UNSIGNED NOT NULL,
    period_id INT UNSIGNED NOT NULL,
    class_subject_id INT UNSIGNED NULL,
    teacher_id INT UNSIGNED NULL,
    room VARCHAR(40) NULL,
    is_locked TINYINT(1) NOT NULL DEFAULT 0,
    updated_by INT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_slot (class_id, day, period_id),
    INDEX idx_slot_teacher (teacher_id, day, period_id),
    CONSTRAINT fk_slot_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_slot_period FOREIGN KEY (period_id) REFERENCES timetable_periods(id) ON DELETE CASCADE,
    CONSTRAINT fk_slot_cs FOREIGN KEY (class_subject_id) REFERENCES class_subjects(id) ON DELETE CASCADE,
    CONSTRAINT fk_slot_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE class_subjects ADD COLUMN periods_per_week TINYINT UNSIGNED NOT NULL DEFAULT 2 AFTER teacher_id;

-- Default bell schedule: Basic 1–6 and JHS (8 lessons of 35 minutes)
INSERT INTO timetable_periods (category, sort_order, label, start_time, end_time, kind)
SELECT c.cat, p.sort_order, p.label, p.start_time, p.end_time, p.kind FROM
(SELECT 'primary' AS cat UNION ALL SELECT 'jhs') c
JOIN (
    SELECT 1 AS sort_order, 'Worship & assembly' AS label, '07:30:00' AS start_time, '08:00:00' AS end_time, 'worship' AS kind UNION ALL
    SELECT 2, 'Period 1', '08:00:00', '08:35:00', 'lesson' UNION ALL
    SELECT 3, 'Period 2', '08:35:00', '09:10:00', 'lesson' UNION ALL
    SELECT 4, 'Period 3', '09:10:00', '09:45:00', 'lesson' UNION ALL
    SELECT 5, 'Break', '09:45:00', '10:15:00', 'break' UNION ALL
    SELECT 6, 'Period 4', '10:15:00', '10:50:00', 'lesson' UNION ALL
    SELECT 7, 'Period 5', '10:50:00', '11:25:00', 'lesson' UNION ALL
    SELECT 8, 'Period 6', '11:25:00', '12:00:00', 'lesson' UNION ALL
    SELECT 9, 'Lunch', '12:00:00', '12:40:00', 'lunch' UNION ALL
    SELECT 10, 'Period 7', '12:40:00', '13:15:00', 'lesson' UNION ALL
    SELECT 11, 'Period 8', '13:15:00', '13:50:00', 'lesson'
) p;

-- Creche and KG (6 lessons of 30 minutes)
INSERT INTO timetable_periods (category, sort_order, label, start_time, end_time, kind)
SELECT c.cat, p.sort_order, p.label, p.start_time, p.end_time, p.kind FROM
(SELECT 'creche' AS cat UNION ALL SELECT 'kg') c
JOIN (
    SELECT 1 AS sort_order, 'Worship & assembly' AS label, '07:30:00' AS start_time, '08:00:00' AS end_time, 'worship' AS kind UNION ALL
    SELECT 2, 'Period 1', '08:00:00', '08:30:00', 'lesson' UNION ALL
    SELECT 3, 'Period 2', '08:30:00', '09:00:00', 'lesson' UNION ALL
    SELECT 4, 'Snack break', '09:00:00', '09:30:00', 'break' UNION ALL
    SELECT 5, 'Period 3', '09:30:00', '10:00:00', 'lesson' UNION ALL
    SELECT 6, 'Period 4', '10:00:00', '10:30:00', 'lesson' UNION ALL
    SELECT 7, 'Lunch & rest', '10:30:00', '11:30:00', 'lunch' UNION ALL
    SELECT 8, 'Period 5', '11:30:00', '12:00:00', 'lesson' UNION ALL
    SELECT 9, 'Period 6', '12:00:00', '12:30:00', 'lesson'
) p;

-- Default lessons per week (fits 40 lessons for Basic/JHS and 30 for KG/Creche); edit in Time table → Lessons per week
UPDATE class_subjects cs
JOIN subjects s ON s.id = cs.subject_id
JOIN classes c ON c.id = cs.class_id
JOIN levels l ON l.id = c.level_id
SET cs.periods_per_week = CASE
    WHEN l.category = 'creche' AND s.code IN ('LL','NUM') THEN 10
    WHEN l.category = 'creche' THEN 5
    WHEN l.category = 'kg' AND s.code IN ('LL','NUM') THEN 6
    WHEN l.category = 'kg' AND s.code IN ('OWOP','CA') THEN 5
    WHEN l.category = 'kg' THEN 4
    WHEN s.code IN ('ENG','MATH') THEN 5
    WHEN s.code IN ('SCI','SOC') THEN 4
    WHEN s.code IN ('GHL','RME','CA','CAD','OWOP','CT') THEN 3
    WHEN s.code = 'COMP' AND l.category = 'jhs' THEN 3
    ELSE 2
END;

INSERT INTO settings (`key`, `value`) VALUES ('school_logo', '');
