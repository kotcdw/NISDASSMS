-- Portal access requested for student and parent self-service, plus class-scoped teacher tools.
ALTER TABLE calendar_events
    MODIFY audience ENUM('all','staff','parents','level','class') NOT NULL DEFAULT 'all',
    ADD COLUMN class_id INT UNSIGNED NULL AFTER level_id,
    ADD CONSTRAINT fk_events_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL;

INSERT IGNORE INTO role_permissions (role_key, permission) VALUES
('administrator', 'admission.view'), ('administrator', 'admission.manage'), ('administrator', 'admission.enrol'),
('administrator', 'pupils.view'), ('administrator', 'pupils.manage'),
('administrator', 'attendance.take'), ('administrator', 'attendance.view'), ('administrator', 'attendance.all'),
('administrator', 'exams.enter'), ('administrator', 'exams.manage'), ('administrator', 'reports.view'),
('administrator', 'teachers.view'), ('administrator', 'timetable.view'), ('administrator', 'timetable.manage'),
('administrator', 'calendar.manage'), ('administrator', 'transport.view'), ('administrator', 'transport.manage'),
('administrator', 'events.manage'), ('administrator', 'messages.send'), ('administrator', 'notices.manage'), ('administrator', 'forms.manage'),
('class_teacher', 'pupils.manage'), ('class_teacher', 'calendar.manage'), ('class_teacher', 'notices.manage'),
('subject_teacher', 'pupils.manage'), ('subject_teacher', 'calendar.manage'), ('subject_teacher', 'notices.manage'),
('parent', 'transport.view'), ('pupil', 'transport.view'), ('pupil', 'chat.use');
