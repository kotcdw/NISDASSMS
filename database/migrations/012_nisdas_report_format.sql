-- NISDAS SMS — Sprint 14: terminal report in the school's own format (Naylor International S.D.A School)
--  * Page 1: terminal report (class 50% / exams 50%, grades 1–9, subject positions, total, average, grading scheme grid,
--    attendance, promoted to / repeat, class teacher's and head of school's comments with signatures)
--  * Page 2: Child's Personality Development Report (11 traits with the class teacher's comments)

ALTER TABLE report_remarks ADD COLUMN personality TEXT NULL AFTER head_remark;

INSERT IGNORE INTO settings (`key`, `value`) VALUES
('report_personality', '1'),
('report_overall_position', '0'),
('fee_show_on_report', '0'),
('personality_traits', 'Courtesy\nNeatness\nEmotional Control\nSociability\nInitiative\nDependability\nCooperative Spirit\nCreativity\nVolunteerism\nHonesty\nLeadership Qualities');

-- The school's printed report has no fees line (can be switched back on in Grading setup → Report card)
UPDATE settings SET `value` = '0' WHERE `key` = 'fee_show_on_report';

-- All levels: the school's grading (as on its printed terminal report). Applied only to levels still on their
-- untouched factory defaults (30/70 with the original bands), so nothing the school has already set is overwritten.
-- JHS keeps its NaCCA level column and BECE-style aggregate (now worked out from these grades).
UPDATE grading_schemes SET class_weight = 50, exam_weight = 50, pass_mark = 40, show_position = 1,
       name = CASE category WHEN 'creche' THEN 'Creche (school grades 1–9)' WHEN 'kg' THEN 'Kindergarten (school grades 1–9)'
                            WHEN 'primary' THEN 'Basic 1–6 (school grades 1–9)' ELSE 'JHS (school grades 1–9 + NaCCA levels)' END
WHERE class_weight = 30 AND exam_weight = 70
  AND (SELECT GROUP_CONCAT(CONCAT(b.grade, ':', CAST(b.min_score AS UNSIGNED)) ORDER BY b.min_score DESC SEPARATOR ',')
       FROM grade_bands b WHERE b.scheme_id = grading_schemes.id AND b.band_set = 'grade')
      IN ('A:80,P:75,AP:70,D:65,B:0', '1:80,2:70,3:60,4:55,5:50,6:45,7:40,8:35,9:0');

DELETE FROM grade_bands WHERE band_set = 'grade'
  AND scheme_id IN (SELECT id FROM (SELECT id FROM grading_schemes WHERE name LIKE '%(school grades 1–9%') x)
  AND scheme_id NOT IN (SELECT scheme_id FROM (SELECT scheme_id FROM grade_bands WHERE band_set = 'grade' AND grade = '1' AND min_score = 90) y);

INSERT INTO grade_bands (scheme_id, band_set, min_score, grade, remark)
SELECT s.id, 'grade', b.min_score, b.grade, b.remark FROM grading_schemes s
JOIN (
    SELECT 90 AS min_score, '1' AS grade, 'Excellent' AS remark UNION ALL
    SELECT 79, '2', 'Very Good' UNION ALL
    SELECT 65, '3', 'Good' UNION ALL
    SELECT 60, '4', 'Credit' UNION ALL
    SELECT 55, '5', 'Pass' UNION ALL
    SELECT 50, '6', 'Satisfactory' UNION ALL
    SELECT 45, '7', 'Weak' UNION ALL
    SELECT 40, '8', 'Buck Up' UNION ALL
    SELECT 0, '9', 'Fail'
) b WHERE s.name LIKE '%(school grades 1–9%'
  AND NOT EXISTS (SELECT 1 FROM grade_bands g WHERE g.scheme_id = s.id AND g.band_set = 'grade');
