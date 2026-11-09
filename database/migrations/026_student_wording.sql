-- NISDAS SMS — the school calls its learners "students": update the wording people read in SMS templates and the
-- role list. Placeholders such as {pupil} and all database names stay the same.

UPDATE notification_templates SET
    name = REGEXP_REPLACE(REGEXP_REPLACE(name, '(?-i)(?<![{_a-z])Pupil(s?)(?![}_a-z])', 'Student\\1'), '(?-i)(?<![{_a-z])pupil(s?)(?![}_a-z])', 'student\\1'),
    description = REGEXP_REPLACE(REGEXP_REPLACE(description, '(?-i)(?<![{_a-z])Pupil(s?)(?![}_a-z])', 'Student\\1'), '(?-i)(?<![{_a-z])pupil(s?)(?![}_a-z])', 'student\\1'),
    sms_body = REGEXP_REPLACE(REGEXP_REPLACE(sms_body, '(?-i)(?<![{_a-z])Pupil(s?)(?![}_a-z])', 'Student\\1'), '(?-i)(?<![{_a-z])pupil(s?)(?![}_a-z])', 'student\\1');

UPDATE roles SET name = 'Student' WHERE role_key = 'pupil' AND name = 'Pupil';
UPDATE roles SET description = REGEXP_REPLACE(description, '(?-i)(?<![{_a-z])pupil(s?)(?![}_a-z])', 'student\\1') WHERE description IS NOT NULL;
