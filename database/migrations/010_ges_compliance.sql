-- NISDAS SMS — Sprint 11: GES / NaCCA / NaSIA / Data Protection compliance
--  * JHS termly reports also show the NaCCA proficiency level (HP, P, AP, D, E); BECE grades 1–9 stay
--  * Special educational needs on pupil records and admission applications
--  * Staff: gender, Ghana Card, highest qualification, NTC licence number and expiry
--  * Privacy notice, Data Protection Commission registration and record-retention settings

-- 1. Second band set per grading scheme: 'grade' (existing) and 'level' (proficiency level shown beside it)
ALTER TABLE grade_bands ADD COLUMN band_set VARCHAR(10) NOT NULL DEFAULT 'grade' AFTER scheme_id;
ALTER TABLE grading_schemes ADD COLUMN show_levels TINYINT(1) NOT NULL DEFAULT 0 AFTER use_aggregate;

INSERT INTO grade_bands (scheme_id, band_set, min_score, grade, remark)
SELECT s.id, 'level', b.min_score, b.grade, b.remark FROM grading_schemes s
JOIN (
    SELECT 80 AS min_score, 'HP' AS grade, 'Highly Proficient' AS remark UNION ALL
    SELECT 68, 'P', 'Proficient' UNION ALL
    SELECT 54, 'AP', 'Approaching Proficiency' UNION ALL
    SELECT 40, 'D', 'Developing' UNION ALL
    SELECT 0, 'E', 'Emerging'
) b WHERE s.category = 'jhs';

UPDATE grading_schemes SET show_levels = 1, name = 'JHS (NaCCA levels + BECE grades 1–9)' WHERE category = 'jhs';

-- 2. Special educational needs (NaSIA: schools must make provision for learners with special needs)
ALTER TABLE pupils
    ADD COLUMN sen_category VARCHAR(30) NULL AFTER allergies,
    ADD COLUMN sen_support VARCHAR(500) NULL AFTER sen_category,
    ADD COLUMN anonymised_at DATETIME NULL AFTER deleted_at;

ALTER TABLE applications
    ADD COLUMN sen_category VARCHAR(30) NULL AFTER allergies,
    ADD COLUMN sen_support VARCHAR(500) NULL AFTER sen_category;

-- 3. Staff records for NaSIA / NTC
ALTER TABLE staff
    ADD COLUMN gender ENUM('male','female') NULL AFTER name,
    ADD COLUMN ghana_card_no VARCHAR(20) NULL AFTER tin,
    ADD COLUMN qualification VARCHAR(20) NULL AFTER ghana_card_no,
    ADD COLUMN ntc_licence_no VARCHAR(30) NULL AFTER qualification,
    ADD COLUMN ntc_expiry DATE NULL AFTER ntc_licence_no;

-- 4. Data protection settings
INSERT IGNORE INTO settings (`key`, `value`) VALUES
('retention_years', '7'),
('dpc_registration_no', ''),
('privacy_notice', ''),
('privacy_contact', '');

-- NTC licence reminder (to the Bursar / Head) — sent by the scheduled task
INSERT IGNORE INTO notification_templates (`key`, name, description, sms_body, placeholders) VALUES
('ntc_expiring', 'NTC licence expiring', 'Sent to a teacher 60 and 14 days before their NTC licence expires.',
 'Dear {name}, your NTC teaching licence ({licence}) expires on {date}. Please renew it and give the new details to the school office. - {school}',
 'name, licence, date, school');
