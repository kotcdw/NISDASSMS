-- NISDAS SMS — Sprint 16: teachers, curriculum & classrooms
--  * Teacher profile & termly appraisal (teacher's self-assessment + head's rating, editable criteria)
--  * Curriculum strands / sub-strands (preloaded from the NaCCA standards-based curriculum, editable) and a termly scheme of work
--  * Class and exam seating plans
--  * Rooms & class resources register (furniture, textbooks, equipment) with shortfalls
--  * Exam timetable (papers, rooms, invigilators) published to parents

-- ---------------------------------------------------------------- Appraisal
CREATE TABLE IF NOT EXISTS appraisal_criteria (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO appraisal_criteria (name, description, sort_order)
SELECT d.n, d.d, d.o FROM (
    SELECT 'Lesson planning and preparation' n, 'Lesson notes prepared and vetted on time; teaching aids ready.' d, 1 o
    UNION ALL SELECT 'Subject knowledge and lesson delivery', 'Clear, accurate, learner-centred lessons that follow the curriculum.', 2
    UNION ALL SELECT 'Classroom management and discipline', 'Orderly, safe and positive classroom; fair use of the school''s discipline policy.', 3
    UNION ALL SELECT 'Assessment and record keeping', 'Exercises marked promptly; scores, registers and report remarks complete and on time.', 4
    UNION ALL SELECT 'Punctuality and attendance', 'Arrives on time, attends classes, assemblies and staff meetings.', 5
    UNION ALL SELECT 'Learner progress and results', 'Pupils make progress; struggling pupils get extra support.', 6
    UNION ALL SELECT 'Relationships with pupils, parents and colleagues', 'Respectful, caring and cooperative; communicates well with parents.', 7
    UNION ALL SELECT 'Professional conduct and SDA values', 'Dress, speech and example in line with the school''s Christian values and the GES code of conduct.', 8
    UNION ALL SELECT 'Co-curricular involvement', 'Clubs, sports, worship, Pathfinders/Adventurers and school events.', 9
    UNION ALL SELECT 'Professional development', 'Takes part in INSET and PLC sessions and applies what is learnt.', 10
) d WHERE NOT EXISTS (SELECT 1 FROM appraisal_criteria x);

CREATE TABLE IF NOT EXISTS appraisals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    term_id INT UNSIGNED NOT NULL,
    status ENUM('open','self_done','completed') NOT NULL DEFAULT 'open',
    self_comment TEXT NULL,
    self_submitted_at DATETIME NULL,
    strengths TEXT NULL,
    improvements TEXT NULL,
    targets TEXT NULL,
    head_comment TEXT NULL,
    overall DECIMAL(3,1) NULL,
    appraised_by INT UNSIGNED NULL,
    completed_at DATETIME NULL,
    teacher_response TEXT NULL,
    acknowledged_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_appraisal (user_id, term_id),
    CONSTRAINT fk_appr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_appr_term FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE CASCADE,
    CONSTRAINT fk_appr_by FOREIGN KEY (appraised_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS appraisal_scores (
    appraisal_id INT UNSIGNED NOT NULL,
    criterion_id INT UNSIGNED NOT NULL,
    self_score TINYINT UNSIGNED NULL,
    head_score TINYINT UNSIGNED NULL,
    PRIMARY KEY (appraisal_id, criterion_id),
    CONSTRAINT fk_apsc_appr FOREIGN KEY (appraisal_id) REFERENCES appraisals(id) ON DELETE CASCADE,
    CONSTRAINT fk_apsc_crit FOREIGN KEY (criterion_id) REFERENCES appraisal_criteria(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Curriculum & scheme of work
CREATE TABLE IF NOT EXISTS curriculum_strands (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subject_id INT UNSIGNED NOT NULL,
    parent_id INT UNSIGNED NULL,
    name VARCHAR(150) NOT NULL,
    levels VARCHAR(40) NOT NULL DEFAULT 'primary,jhs',
    sort_order SMALLINT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    INDEX idx_cstr_subject (subject_id, parent_id),
    CONSTRAINT fk_cstr_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    CONSTRAINT fk_cstr_parent FOREIGN KEY (parent_id) REFERENCES curriculum_strands(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scheme_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    term_id INT UNSIGNED NOT NULL,
    class_subject_id INT UNSIGNED NOT NULL,
    week_no TINYINT UNSIGNED NOT NULL,
    strand_id INT UNSIGNED NULL,
    topic VARCHAR(200) NOT NULL,
    indicators VARCHAR(255) NULL,
    resources VARCHAR(200) NULL,
    taught_on DATE NULL,
    taught_by INT UNSIGNED NULL,
    remark VARCHAR(200) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sow (term_id, class_subject_id, week_no),
    CONSTRAINT fk_sow_term FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE CASCADE,
    CONSTRAINT fk_sow_cs FOREIGN KEY (class_subject_id) REFERENCES class_subjects(id) ON DELETE CASCADE,
    CONSTRAINT fk_sow_strand FOREIGN KEY (strand_id) REFERENCES curriculum_strands(id) ON DELETE SET NULL,
    CONSTRAINT fk_sow_taughtby FOREIGN KEY (taught_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO curriculum_strands (subject_id, parent_id, name, levels, sort_order)
SELECT s.id, NULL, d.n, d.lv, d.o FROM subjects s JOIN (
    SELECT 'LL' c, 'creche,kg' lv, 'Oral Language' n, 1 o
    UNION ALL SELECT 'LL' c, 'creche,kg' lv, 'Reading' n, 2 o
    UNION ALL SELECT 'LL' c, 'creche,kg' lv, 'Writing' n, 3 o
    UNION ALL SELECT 'LL' c, 'creche,kg' lv, 'Writing Conventions' n, 4 o
    UNION ALL SELECT 'NUM' c, 'creche,kg' lv, 'Number' n, 1 o
    UNION ALL SELECT 'NUM' c, 'creche,kg' lv, 'Algebra' n, 2 o
    UNION ALL SELECT 'NUM' c, 'creche,kg' lv, 'Geometry and Measurement' n, 3 o
    UNION ALL SELECT 'NUM' c, 'creche,kg' lv, 'Data' n, 4 o
    UNION ALL SELECT 'OWOP' c, 'kg,primary' lv, 'All About Us' n, 1 o
    UNION ALL SELECT 'OWOP' c, 'kg,primary' lv, 'All Around Us' n, 2 o
    UNION ALL SELECT 'OWOP' c, 'kg,primary' lv, 'Our Beliefs and Values' n, 3 o
    UNION ALL SELECT 'OWOP' c, 'kg,primary' lv, 'Our Nation Ghana' n, 4 o
    UNION ALL SELECT 'OWOP' c, 'kg,primary' lv, 'My Global Community' n, 5 o
    UNION ALL SELECT 'ENG' c, 'primary' lv, 'Oral Language' n, 1 o
    UNION ALL SELECT 'ENG' c, 'primary' lv, 'Reading' n, 2 o
    UNION ALL SELECT 'ENG' c, 'primary' lv, 'Grammar Usage' n, 3 o
    UNION ALL SELECT 'ENG' c, 'primary' lv, 'Writing' n, 4 o
    UNION ALL SELECT 'ENG' c, 'primary' lv, 'Extensive Reading' n, 5 o
    UNION ALL SELECT 'ENG' c, 'jhs' lv, 'Oral Language' n, 1 o
    UNION ALL SELECT 'ENG' c, 'jhs' lv, 'Reading' n, 2 o
    UNION ALL SELECT 'ENG' c, 'jhs' lv, 'Grammar' n, 3 o
    UNION ALL SELECT 'ENG' c, 'jhs' lv, 'Writing' n, 4 o
    UNION ALL SELECT 'ENG' c, 'jhs' lv, 'Literature' n, 5 o
    UNION ALL SELECT 'GHL' c, 'primary' lv, 'Oral Language' n, 1 o
    UNION ALL SELECT 'GHL' c, 'primary' lv, 'Reading' n, 2 o
    UNION ALL SELECT 'GHL' c, 'primary' lv, 'Grammar Usage' n, 3 o
    UNION ALL SELECT 'GHL' c, 'primary' lv, 'Writing' n, 4 o
    UNION ALL SELECT 'GHL' c, 'primary' lv, 'Extensive Reading' n, 5 o
    UNION ALL SELECT 'GHL' c, 'jhs' lv, 'Oral Literature' n, 1 o
    UNION ALL SELECT 'GHL' c, 'jhs' lv, 'Reading' n, 2 o
    UNION ALL SELECT 'GHL' c, 'jhs' lv, 'Grammar' n, 3 o
    UNION ALL SELECT 'GHL' c, 'jhs' lv, 'Writing' n, 4 o
    UNION ALL SELECT 'GHL' c, 'jhs' lv, 'Literature' n, 5 o
    UNION ALL SELECT 'MATH' c, 'primary' lv, 'Number' n, 1 o
    UNION ALL SELECT 'MATH' c, 'primary' lv, 'Algebra' n, 2 o
    UNION ALL SELECT 'MATH' c, 'primary' lv, 'Geometry and Measurement' n, 3 o
    UNION ALL SELECT 'MATH' c, 'primary' lv, 'Data' n, 4 o
    UNION ALL SELECT 'MATH' c, 'jhs' lv, 'Number' n, 1 o
    UNION ALL SELECT 'MATH' c, 'jhs' lv, 'Algebra' n, 2 o
    UNION ALL SELECT 'MATH' c, 'jhs' lv, 'Geometry and Measurement' n, 3 o
    UNION ALL SELECT 'MATH' c, 'jhs' lv, 'Handling Data' n, 4 o
    UNION ALL SELECT 'SCI' c, 'primary,jhs' lv, 'Diversity of Matter' n, 1 o
    UNION ALL SELECT 'SCI' c, 'primary,jhs' lv, 'Cycles' n, 2 o
    UNION ALL SELECT 'SCI' c, 'primary,jhs' lv, 'Systems' n, 3 o
    UNION ALL SELECT 'SCI' c, 'primary,jhs' lv, 'Forces and Energy' n, 4 o
    UNION ALL SELECT 'SCI' c, 'primary,jhs' lv, 'Humans and the Environment' n, 5 o
    UNION ALL SELECT 'HIST' c, 'primary' lv, 'History as a Subject' n, 1 o
    UNION ALL SELECT 'HIST' c, 'primary' lv, 'My Country Ghana' n, 2 o
    UNION ALL SELECT 'HIST' c, 'primary' lv, 'Europeans in Ghana' n, 3 o
    UNION ALL SELECT 'HIST' c, 'primary' lv, 'Colonisation and Developments under Colonial Rule' n, 4 o
    UNION ALL SELECT 'HIST' c, 'primary' lv, 'Journey to Independence' n, 5 o
    UNION ALL SELECT 'HIST' c, 'primary' lv, 'Independent Ghana' n, 6 o
    UNION ALL SELECT 'SOC' c, 'jhs' lv, 'Environment' n, 1 o
    UNION ALL SELECT 'SOC' c, 'jhs' lv, 'Governance, Politics and Stability' n, 2 o
    UNION ALL SELECT 'SOC' c, 'jhs' lv, 'Social and Economic Development' n, 3 o
    UNION ALL SELECT 'RME' c, 'primary,jhs' lv, 'God, His Creation and Attributes' n, 1 o
    UNION ALL SELECT 'RME' c, 'primary,jhs' lv, 'Religious Practices and Their Moral Implications' n, 2 o
    UNION ALL SELECT 'RME' c, 'primary,jhs' lv, 'Religious Leaders' n, 3 o
    UNION ALL SELECT 'RME' c, 'primary,jhs' lv, 'The Family and the Community' n, 4 o
    UNION ALL SELECT 'CA' c, 'kg,primary' lv, 'Visual Arts' n, 1 o
    UNION ALL SELECT 'CA' c, 'kg,primary' lv, 'Performing Arts' n, 2 o
    UNION ALL SELECT 'CAD' c, 'jhs' lv, 'Visual Arts' n, 1 o
    UNION ALL SELECT 'CAD' c, 'jhs' lv, 'Performing Arts' n, 2 o
    UNION ALL SELECT 'PHE' c, 'kg,primary,jhs' lv, 'Motor Skill and Movement Patterns' n, 1 o
    UNION ALL SELECT 'PHE' c, 'kg,primary,jhs' lv, 'Movement Concepts, Principles and Strategies' n, 2 o
    UNION ALL SELECT 'PHE' c, 'kg,primary,jhs' lv, 'Physical Fitness' n, 3 o
    UNION ALL SELECT 'PHE' c, 'kg,primary,jhs' lv, 'Values in Physical Education' n, 4 o
    UNION ALL SELECT 'PHE' c, 'jhs' lv, 'Health' n, 1 o
    UNION ALL SELECT 'COMP' c, 'primary,jhs' lv, 'Introduction to Computing' n, 1 o
    UNION ALL SELECT 'COMP' c, 'primary,jhs' lv, 'Productivity Software' n, 2 o
    UNION ALL SELECT 'COMP' c, 'primary,jhs' lv, 'Communication Networks' n, 3 o
    UNION ALL SELECT 'COMP' c, 'primary,jhs' lv, 'Computational Thinking' n, 4 o
    UNION ALL SELECT 'CT' c, 'jhs' lv, 'Health and Safety' n, 1 o
    UNION ALL SELECT 'CT' c, 'jhs' lv, 'Materials for Production' n, 2 o
    UNION ALL SELECT 'CT' c, 'jhs' lv, 'Tools, Equipment and Processes' n, 3 o
    UNION ALL SELECT 'CT' c, 'jhs' lv, 'Technical Drawing' n, 4 o
    UNION ALL SELECT 'CT' c, 'jhs' lv, 'Entrepreneurship' n, 5 o
    UNION ALL SELECT 'FRE' c, 'primary,jhs' lv, 'Oral Communication' n, 1 o
    UNION ALL SELECT 'FRE' c, 'primary,jhs' lv, 'Reading' n, 2 o
    UNION ALL SELECT 'FRE' c, 'primary,jhs' lv, 'Writing' n, 3 o
    UNION ALL SELECT 'FRE' c, 'primary,jhs' lv, 'Grammar' n, 4 o
    UNION ALL SELECT 'BIB' c, 'creche,kg,primary,jhs' lv, 'Old Testament' n, 1 o
    UNION ALL SELECT 'BIB' c, 'creche,kg,primary,jhs' lv, 'New Testament' n, 2 o
    UNION ALL SELECT 'BIB' c, 'creche,kg,primary,jhs' lv, 'Memory Verses' n, 3 o
    UNION ALL SELECT 'BIB' c, 'creche,kg,primary,jhs' lv, 'Christian Living' n, 4 o
) d ON d.c = s.code
WHERE NOT EXISTS (SELECT 1 FROM curriculum_strands x);

INSERT INTO curriculum_strands (subject_id, parent_id, name, levels, sort_order)
SELECT p.subject_id, p.id, d.n, p.levels, d.o FROM curriculum_strands p JOIN subjects s ON s.id = p.subject_id JOIN (
    SELECT 'LL' c, 'creche,kg' lv, 'Oral Language' st, 'Listening and speaking' n, 1 o
    UNION ALL SELECT 'LL' c, 'creche,kg' lv, 'Oral Language' st, 'Songs, rhymes and poems' n, 2 o
    UNION ALL SELECT 'LL' c, 'creche,kg' lv, 'Oral Language' st, 'Story telling' n, 3 o
    UNION ALL SELECT 'LL' c, 'creche,kg' lv, 'Oral Language' st, 'Conversation' n, 4 o
    UNION ALL SELECT 'LL' c, 'creche,kg' lv, 'Reading' st, 'Pre-reading skills' n, 1 o
    UNION ALL SELECT 'LL' c, 'creche,kg' lv, 'Reading' st, 'Letter and sound knowledge (phonics)' n, 2 o
    UNION ALL SELECT 'LL' c, 'creche,kg' lv, 'Reading' st, 'Vocabulary' n, 3 o
    UNION ALL SELECT 'LL' c, 'creche,kg' lv, 'Reading' st, 'Comprehension' n, 4 o
    UNION ALL SELECT 'LL' c, 'creche,kg' lv, 'Writing' st, 'Pre-writing (scribbling and patterns)' n, 1 o
    UNION ALL SELECT 'LL' c, 'creche,kg' lv, 'Writing' st, 'Letter formation' n, 2 o
    UNION ALL SELECT 'LL' c, 'creche,kg' lv, 'Writing' st, 'Writing names and simple words' n, 3 o
    UNION ALL SELECT 'LL' c, 'creche,kg' lv, 'Writing Conventions' st, 'Capital letters and full stops' n, 1 o
    UNION ALL SELECT 'NUM' c, 'creche,kg' lv, 'Number' st, 'Counting' n, 1 o
    UNION ALL SELECT 'NUM' c, 'creche,kg' lv, 'Number' st, 'Number recognition and writing' n, 2 o
    UNION ALL SELECT 'NUM' c, 'creche,kg' lv, 'Number' st, 'Comparing and ordering' n, 3 o
    UNION ALL SELECT 'NUM' c, 'creche,kg' lv, 'Number' st, 'Adding and taking away' n, 4 o
    UNION ALL SELECT 'NUM' c, 'creche,kg' lv, 'Algebra' st, 'Patterns' n, 1 o
    UNION ALL SELECT 'NUM' c, 'creche,kg' lv, 'Geometry and Measurement' st, 'Shapes' n, 1 o
    UNION ALL SELECT 'NUM' c, 'creche,kg' lv, 'Geometry and Measurement' st, 'Position and direction' n, 2 o
    UNION ALL SELECT 'NUM' c, 'creche,kg' lv, 'Geometry and Measurement' st, 'Measurement (length, mass, capacity, time)' n, 3 o
    UNION ALL SELECT 'NUM' c, 'creche,kg' lv, 'Geometry and Measurement' st, 'Money' n, 4 o
    UNION ALL SELECT 'NUM' c, 'creche,kg' lv, 'Data' st, 'Sorting and classifying' n, 1 o
    UNION ALL SELECT 'NUM' c, 'creche,kg' lv, 'Data' st, 'Simple pictographs' n, 2 o
    UNION ALL SELECT 'OWOP' c, 'kg,primary' lv, 'All About Us' st, 'Self' n, 1 o
    UNION ALL SELECT 'OWOP' c, 'kg,primary' lv, 'All About Us' st, 'Family' n, 2 o
    UNION ALL SELECT 'OWOP' c, 'kg,primary' lv, 'All About Us' st, 'Home and school' n, 3 o
    UNION ALL SELECT 'OWOP' c, 'kg,primary' lv, 'All Around Us' st, 'The environment and the weather' n, 1 o
    UNION ALL SELECT 'OWOP' c, 'kg,primary' lv, 'All Around Us' st, 'Plants and animals' n, 2 o
    UNION ALL SELECT 'OWOP' c, 'kg,primary' lv, 'All Around Us' st, 'Map making and landmarks' n, 3 o
    UNION ALL SELECT 'OWOP' c, 'kg,primary' lv, 'Our Beliefs and Values' st, 'Belief in God' n, 1 o
    UNION ALL SELECT 'OWOP' c, 'kg,primary' lv, 'Our Beliefs and Values' st, 'Worship' n, 2 o
    UNION ALL SELECT 'OWOP' c, 'kg,primary' lv, 'Our Beliefs and Values' st, 'Festivals' n, 3 o
    UNION ALL SELECT 'OWOP' c, 'kg,primary' lv, 'Our Beliefs and Values' st, 'Being a leader' n, 4 o
    UNION ALL SELECT 'OWOP' c, 'kg,primary' lv, 'Our Nation Ghana' st, 'Being a citizen' n, 1 o
    UNION ALL SELECT 'OWOP' c, 'kg,primary' lv, 'Our Nation Ghana' st, 'Authority and power' n, 2 o
    UNION ALL SELECT 'OWOP' c, 'kg,primary' lv, 'Our Nation Ghana' st, 'Ghanaian culture' n, 3 o
    UNION ALL SELECT 'OWOP' c, 'kg,primary' lv, 'My Global Community' st, 'Ghana and her neighbours' n, 1 o
    UNION ALL SELECT 'OWOP' c, 'kg,primary' lv, 'My Global Community' st, 'Travel and communication' n, 2 o
    UNION ALL SELECT 'ENG' c, 'primary' lv, 'Oral Language' st, 'Songs and rhymes' n, 1 o
    UNION ALL SELECT 'ENG' c, 'primary' lv, 'Oral Language' st, 'Listening comprehension' n, 2 o
    UNION ALL SELECT 'ENG' c, 'primary' lv, 'Oral Language' st, 'Story telling' n, 3 o
    UNION ALL SELECT 'ENG' c, 'primary' lv, 'Oral Language' st, 'Conversation' n, 4 o
    UNION ALL SELECT 'ENG' c, 'primary' lv, 'Oral Language' st, 'Asking and answering questions' n, 5 o
    UNION ALL SELECT 'ENG' c, 'primary' lv, 'Oral Language' st, 'Dramatisation and role play' n, 6 o
    UNION ALL SELECT 'ENG' c, 'primary' lv, 'Reading' st, 'Phonics (letter and sound knowledge)' n, 1 o
    UNION ALL SELECT 'ENG' c, 'primary' lv, 'Reading' st, 'Vocabulary' n, 2 o
    UNION ALL SELECT 'ENG' c, 'primary' lv, 'Reading' st, 'Comprehension' n, 3 o
    UNION ALL SELECT 'ENG' c, 'primary' lv, 'Reading' st, 'Fluency' n, 4 o
    UNION ALL SELECT 'ENG' c, 'primary' lv, 'Reading' st, 'Silent reading' n, 5 o
    UNION ALL SELECT 'ENG' c, 'primary' lv, 'Grammar Usage' st, 'Nouns and pronouns' n, 1 o
    UNION ALL SELECT 'ENG' c, 'primary' lv, 'Grammar Usage' st, 'Verbs and tenses' n, 2 o
    UNION ALL SELECT 'ENG' c, 'primary' lv, 'Grammar Usage' st, 'Adjectives and adverbs' n, 3 o
    UNION ALL SELECT 'ENG' c, 'primary' lv, 'Grammar Usage' st, 'Prepositions and conjunctions' n, 4 o
    UNION ALL SELECT 'ENG' c, 'primary' lv, 'Grammar Usage' st, 'Punctuation and capitalisation' n, 5 o
    UNION ALL SELECT 'ENG' c, 'primary' lv, 'Writing' st, 'Penmanship (handwriting)' n, 1 o
    UNION ALL SELECT 'ENG' c, 'primary' lv, 'Writing' st, 'Sentences and paragraphs' n, 2 o
    UNION ALL SELECT 'ENG' c, 'primary' lv, 'Writing' st, 'Narrative writing' n, 3 o
    UNION ALL SELECT 'ENG' c, 'primary' lv, 'Writing' st, 'Descriptive writing' n, 4 o
    UNION ALL SELECT 'ENG' c, 'primary' lv, 'Writing' st, 'Letter writing' n, 5 o
    UNION ALL SELECT 'ENG' c, 'primary' lv, 'Extensive Reading' st, 'Building the love and culture of reading' n, 1 o
    UNION ALL SELECT 'ENG' c, 'jhs' lv, 'Oral Language' st, 'Speech sounds (vowels and consonants)' n, 1 o
    UNION ALL SELECT 'ENG' c, 'jhs' lv, 'Oral Language' st, 'Stress and intonation' n, 2 o
    UNION ALL SELECT 'ENG' c, 'jhs' lv, 'Oral Language' st, 'Listening comprehension' n, 3 o
    UNION ALL SELECT 'ENG' c, 'jhs' lv, 'Oral Language' st, 'Conversation and debate' n, 4 o
    UNION ALL SELECT 'ENG' c, 'jhs' lv, 'Reading' st, 'Comprehension' n, 1 o
    UNION ALL SELECT 'ENG' c, 'jhs' lv, 'Reading' st, 'Summary' n, 2 o
    UNION ALL SELECT 'ENG' c, 'jhs' lv, 'Reading' st, 'Vocabulary' n, 3 o
    UNION ALL SELECT 'ENG' c, 'jhs' lv, 'Grammar' st, 'Parts of speech' n, 1 o
    UNION ALL SELECT 'ENG' c, 'jhs' lv, 'Grammar' st, 'Sentence structure' n, 2 o
    UNION ALL SELECT 'ENG' c, 'jhs' lv, 'Grammar' st, 'Tenses and concord' n, 3 o
    UNION ALL SELECT 'ENG' c, 'jhs' lv, 'Grammar' st, 'Direct and indirect speech' n, 4 o
    UNION ALL SELECT 'ENG' c, 'jhs' lv, 'Writing' st, 'Text types (narrative, descriptive, expository, argumentative)' n, 1 o
    UNION ALL SELECT 'ENG' c, 'jhs' lv, 'Writing' st, 'Letter writing' n, 2 o
    UNION ALL SELECT 'ENG' c, 'jhs' lv, 'Writing' st, 'Reports and articles' n, 3 o
    UNION ALL SELECT 'ENG' c, 'jhs' lv, 'Literature' st, 'Prose' n, 1 o
    UNION ALL SELECT 'ENG' c, 'jhs' lv, 'Literature' st, 'Poetry' n, 2 o
    UNION ALL SELECT 'ENG' c, 'jhs' lv, 'Literature' st, 'Drama' n, 3 o
    UNION ALL SELECT 'GHL' c, 'primary' lv, 'Oral Language' st, 'Songs, rhymes and folk tales' n, 1 o
    UNION ALL SELECT 'GHL' c, 'primary' lv, 'Oral Language' st, 'Listening comprehension' n, 2 o
    UNION ALL SELECT 'GHL' c, 'primary' lv, 'Oral Language' st, 'Conversation' n, 3 o
    UNION ALL SELECT 'GHL' c, 'primary' lv, 'Oral Language' st, 'Proverbs and riddles' n, 4 o
    UNION ALL SELECT 'GHL' c, 'primary' lv, 'Reading' st, 'Letter and sound knowledge' n, 1 o
    UNION ALL SELECT 'GHL' c, 'primary' lv, 'Reading' st, 'Vocabulary' n, 2 o
    UNION ALL SELECT 'GHL' c, 'primary' lv, 'Reading' st, 'Comprehension' n, 3 o
    UNION ALL SELECT 'GHL' c, 'primary' lv, 'Reading' st, 'Fluency' n, 4 o
    UNION ALL SELECT 'GHL' c, 'primary' lv, 'Grammar Usage' st, 'Nouns, pronouns and verbs' n, 1 o
    UNION ALL SELECT 'GHL' c, 'primary' lv, 'Grammar Usage' st, 'Sentence structure' n, 2 o
    UNION ALL SELECT 'GHL' c, 'primary' lv, 'Writing' st, 'Handwriting' n, 1 o
    UNION ALL SELECT 'GHL' c, 'primary' lv, 'Writing' st, 'Composition' n, 2 o
    UNION ALL SELECT 'GHL' c, 'primary' lv, 'Writing' st, 'Letter writing' n, 3 o
    UNION ALL SELECT 'GHL' c, 'primary' lv, 'Extensive Reading' st, 'Reading for pleasure' n, 1 o
    UNION ALL SELECT 'GHL' c, 'jhs' lv, 'Oral Literature' st, 'Folk tales' n, 1 o
    UNION ALL SELECT 'GHL' c, 'jhs' lv, 'Oral Literature' st, 'Proverbs and idioms' n, 2 o
    UNION ALL SELECT 'GHL' c, 'jhs' lv, 'Oral Literature' st, 'Customs and institutions' n, 3 o
    UNION ALL SELECT 'GHL' c, 'jhs' lv, 'Reading' st, 'Comprehension' n, 1 o
    UNION ALL SELECT 'GHL' c, 'jhs' lv, 'Reading' st, 'Summary' n, 2 o
    UNION ALL SELECT 'GHL' c, 'jhs' lv, 'Grammar' st, 'Parts of speech' n, 1 o
    UNION ALL SELECT 'GHL' c, 'jhs' lv, 'Grammar' st, 'Sentence structure' n, 2 o
    UNION ALL SELECT 'GHL' c, 'jhs' lv, 'Writing' st, 'Composition' n, 1 o
    UNION ALL SELECT 'GHL' c, 'jhs' lv, 'Writing' st, 'Letter writing' n, 2 o
    UNION ALL SELECT 'GHL' c, 'jhs' lv, 'Literature' st, 'Prose' n, 1 o
    UNION ALL SELECT 'GHL' c, 'jhs' lv, 'Literature' st, 'Poetry' n, 2 o
    UNION ALL SELECT 'GHL' c, 'jhs' lv, 'Literature' st, 'Drama' n, 3 o
    UNION ALL SELECT 'MATH' c, 'primary' lv, 'Number' st, 'Counting, representation and cardinality' n, 1 o
    UNION ALL SELECT 'MATH' c, 'primary' lv, 'Number' st, 'Number operations' n, 2 o
    UNION ALL SELECT 'MATH' c, 'primary' lv, 'Number' st, 'Fractions, decimals and percentages' n, 3 o
    UNION ALL SELECT 'MATH' c, 'primary' lv, 'Algebra' st, 'Patterns and relationships' n, 1 o
    UNION ALL SELECT 'MATH' c, 'primary' lv, 'Algebra' st, 'Variables, expressions and equations' n, 2 o
    UNION ALL SELECT 'MATH' c, 'primary' lv, 'Geometry and Measurement' st, 'Shape and space' n, 1 o
    UNION ALL SELECT 'MATH' c, 'primary' lv, 'Geometry and Measurement' st, 'Measurement (length, mass, capacity, time, money)' n, 2 o
    UNION ALL SELECT 'MATH' c, 'primary' lv, 'Geometry and Measurement' st, 'Position and transformation' n, 3 o
    UNION ALL SELECT 'MATH' c, 'primary' lv, 'Data' st, 'Data collection, organisation, presentation and analysis' n, 1 o
    UNION ALL SELECT 'MATH' c, 'primary' lv, 'Data' st, 'Chance (probability)' n, 2 o
    UNION ALL SELECT 'MATH' c, 'jhs' lv, 'Number' st, 'Number and numeration systems' n, 1 o
    UNION ALL SELECT 'MATH' c, 'jhs' lv, 'Number' st, 'Number operations' n, 2 o
    UNION ALL SELECT 'MATH' c, 'jhs' lv, 'Number' st, 'Fractions, decimals and percentages' n, 3 o
    UNION ALL SELECT 'MATH' c, 'jhs' lv, 'Number' st, 'Ratios and proportion' n, 4 o
    UNION ALL SELECT 'MATH' c, 'jhs' lv, 'Algebra' st, 'Patterns and relations' n, 1 o
    UNION ALL SELECT 'MATH' c, 'jhs' lv, 'Algebra' st, 'Algebraic expressions' n, 2 o
    UNION ALL SELECT 'MATH' c, 'jhs' lv, 'Algebra' st, 'Variables and equations' n, 3 o
    UNION ALL SELECT 'MATH' c, 'jhs' lv, 'Geometry and Measurement' st, 'Shapes and space' n, 1 o
    UNION ALL SELECT 'MATH' c, 'jhs' lv, 'Geometry and Measurement' st, 'Measurement' n, 2 o
    UNION ALL SELECT 'MATH' c, 'jhs' lv, 'Geometry and Measurement' st, 'Position and transformation' n, 3 o
    UNION ALL SELECT 'MATH' c, 'jhs' lv, 'Handling Data' st, 'Data' n, 1 o
    UNION ALL SELECT 'MATH' c, 'jhs' lv, 'Handling Data' st, 'Chance or probability' n, 2 o
    UNION ALL SELECT 'SCI' c, 'primary,jhs' lv, 'Diversity of Matter' st, 'Living and non-living things' n, 1 o
    UNION ALL SELECT 'SCI' c, 'primary,jhs' lv, 'Diversity of Matter' st, 'Materials' n, 2 o
    UNION ALL SELECT 'SCI' c, 'primary,jhs' lv, 'Cycles' st, 'Earth science' n, 1 o
    UNION ALL SELECT 'SCI' c, 'primary,jhs' lv, 'Cycles' st, 'Life cycles of organisms' n, 2 o
    UNION ALL SELECT 'SCI' c, 'primary,jhs' lv, 'Cycles' st, 'Crop production' n, 3 o
    UNION ALL SELECT 'SCI' c, 'primary,jhs' lv, 'Systems' st, 'The human body systems' n, 1 o
    UNION ALL SELECT 'SCI' c, 'primary,jhs' lv, 'Systems' st, 'The solar system' n, 2 o
    UNION ALL SELECT 'SCI' c, 'primary,jhs' lv, 'Systems' st, 'Ecosystems' n, 3 o
    UNION ALL SELECT 'SCI' c, 'primary,jhs' lv, 'Forces and Energy' st, 'Sources and forms of energy' n, 1 o
    UNION ALL SELECT 'SCI' c, 'primary,jhs' lv, 'Forces and Energy' st, 'Electricity and electronics' n, 2 o
    UNION ALL SELECT 'SCI' c, 'primary,jhs' lv, 'Forces and Energy' st, 'Conversion and conservation of energy' n, 3 o
    UNION ALL SELECT 'SCI' c, 'primary,jhs' lv, 'Forces and Energy' st, 'Forces and movement' n, 4 o
    UNION ALL SELECT 'SCI' c, 'primary,jhs' lv, 'Humans and the Environment' st, 'Personal hygiene and sanitation' n, 1 o
    UNION ALL SELECT 'SCI' c, 'primary,jhs' lv, 'Humans and the Environment' st, 'Diseases' n, 2 o
    UNION ALL SELECT 'SCI' c, 'primary,jhs' lv, 'Humans and the Environment' st, 'Science and industry' n, 3 o
    UNION ALL SELECT 'SCI' c, 'primary,jhs' lv, 'Humans and the Environment' st, 'Climate change' n, 4 o
    UNION ALL SELECT 'HIST' c, 'primary' lv, 'History as a Subject' st, 'Why and how we study history' n, 1 o
    UNION ALL SELECT 'HIST' c, 'primary' lv, 'History as a Subject' st, 'My family history' n, 2 o
    UNION ALL SELECT 'HIST' c, 'primary' lv, 'My Country Ghana' st, 'The people of Ghana' n, 1 o
    UNION ALL SELECT 'HIST' c, 'primary' lv, 'My Country Ghana' st, 'Inter-group relations' n, 2 o
    UNION ALL SELECT 'HIST' c, 'primary' lv, 'My Country Ghana' st, 'Major historical locations' n, 3 o
    UNION ALL SELECT 'HIST' c, 'primary' lv, 'Europeans in Ghana' st, 'Arrival of Europeans' n, 1 o
    UNION ALL SELECT 'HIST' c, 'primary' lv, 'Europeans in Ghana' st, 'International trade, including the slave trade' n, 2 o
    UNION ALL SELECT 'HIST' c, 'primary' lv, 'Colonisation and Developments under Colonial Rule' st, 'Establishment of colonial rule' n, 1 o
    UNION ALL SELECT 'HIST' c, 'primary' lv, 'Colonisation and Developments under Colonial Rule' st, 'Social, economic and political developments' n, 2 o
    UNION ALL SELECT 'HIST' c, 'primary' lv, 'Journey to Independence' st, 'Early protest movements' n, 1 o
    UNION ALL SELECT 'HIST' c, 'primary' lv, 'Journey to Independence' st, 'Formation of political parties' n, 2 o
    UNION ALL SELECT 'HIST' c, 'primary' lv, 'Journey to Independence' st, 'Independence' n, 3 o
    UNION ALL SELECT 'HIST' c, 'primary' lv, 'Independent Ghana' st, 'The republics' n, 1 o
    UNION ALL SELECT 'HIST' c, 'primary' lv, 'Independent Ghana' st, 'Military rule' n, 2 o
    UNION ALL SELECT 'SOC' c, 'jhs' lv, 'Environment' st, 'The environment' n, 1 o
    UNION ALL SELECT 'SOC' c, 'jhs' lv, 'Environment' st, 'Physical features and climate' n, 2 o
    UNION ALL SELECT 'SOC' c, 'jhs' lv, 'Environment' st, 'Natural resources and disasters' n, 3 o
    UNION ALL SELECT 'SOC' c, 'jhs' lv, 'Governance, Politics and Stability' st, 'Constitution, democracy and citizenship' n, 1 o
    UNION ALL SELECT 'SOC' c, 'jhs' lv, 'Governance, Politics and Stability' st, 'Rights and responsibilities' n, 2 o
    UNION ALL SELECT 'SOC' c, 'jhs' lv, 'Governance, Politics and Stability' st, 'Leadership and conflict resolution' n, 3 o
    UNION ALL SELECT 'SOC' c, 'jhs' lv, 'Social and Economic Development' st, 'Population and development' n, 1 o
    UNION ALL SELECT 'SOC' c, 'jhs' lv, 'Social and Economic Development' st, 'Economic activities and entrepreneurship' n, 2 o
    UNION ALL SELECT 'SOC' c, 'jhs' lv, 'Social and Economic Development' st, 'Tourism and transport' n, 3 o
    UNION ALL SELECT 'RME' c, 'primary,jhs' lv, 'God, His Creation and Attributes' st, 'God the Creator' n, 1 o
    UNION ALL SELECT 'RME' c, 'primary,jhs' lv, 'God, His Creation and Attributes' st, 'Humans and the environment' n, 2 o
    UNION ALL SELECT 'RME' c, 'primary,jhs' lv, 'God, His Creation and Attributes' st, 'Attributes of God' n, 3 o
    UNION ALL SELECT 'RME' c, 'primary,jhs' lv, 'Religious Practices and Their Moral Implications' st, 'Worship' n, 1 o
    UNION ALL SELECT 'RME' c, 'primary,jhs' lv, 'Religious Practices and Their Moral Implications' st, 'Festivals' n, 2 o
    UNION ALL SELECT 'RME' c, 'primary,jhs' lv, 'Religious Practices and Their Moral Implications' st, 'Rites of passage' n, 3 o
    UNION ALL SELECT 'RME' c, 'primary,jhs' lv, 'Religious Leaders' st, 'Early life of the leaders' n, 1 o
    UNION ALL SELECT 'RME' c, 'primary,jhs' lv, 'Religious Leaders' st, 'Teachings and examples of the leaders' n, 2 o
    UNION ALL SELECT 'RME' c, 'primary,jhs' lv, 'The Family and the Community' st, 'Roles and responsibilities' n, 1 o
    UNION ALL SELECT 'RME' c, 'primary,jhs' lv, 'The Family and the Community' st, 'Relationships' n, 2 o
    UNION ALL SELECT 'RME' c, 'primary,jhs' lv, 'The Family and the Community' st, 'Moral values (honesty, obedience, respect)' n, 3 o
    UNION ALL SELECT 'CA' c, 'kg,primary' lv, 'Visual Arts' st, 'Thinking and exploring ideas' n, 1 o
    UNION ALL SELECT 'CA' c, 'kg,primary' lv, 'Visual Arts' st, 'Planning, making and composing' n, 2 o
    UNION ALL SELECT 'CA' c, 'kg,primary' lv, 'Visual Arts' st, 'Displaying and sharing' n, 3 o
    UNION ALL SELECT 'CA' c, 'kg,primary' lv, 'Visual Arts' st, 'Appreciating and appraising' n, 4 o
    UNION ALL SELECT 'CA' c, 'kg,primary' lv, 'Performing Arts' st, 'Thinking and exploring ideas' n, 1 o
    UNION ALL SELECT 'CA' c, 'kg,primary' lv, 'Performing Arts' st, 'Planning, making and composing' n, 2 o
    UNION ALL SELECT 'CA' c, 'kg,primary' lv, 'Performing Arts' st, 'Displaying and sharing' n, 3 o
    UNION ALL SELECT 'CA' c, 'kg,primary' lv, 'Performing Arts' st, 'Appreciating and appraising' n, 4 o
    UNION ALL SELECT 'CAD' c, 'jhs' lv, 'Visual Arts' st, 'Thinking and exploring ideas' n, 1 o
    UNION ALL SELECT 'CAD' c, 'jhs' lv, 'Visual Arts' st, 'Planning, making and composing' n, 2 o
    UNION ALL SELECT 'CAD' c, 'jhs' lv, 'Visual Arts' st, 'Displaying and sharing' n, 3 o
    UNION ALL SELECT 'CAD' c, 'jhs' lv, 'Visual Arts' st, 'Appreciating and appraising' n, 4 o
    UNION ALL SELECT 'CAD' c, 'jhs' lv, 'Performing Arts' st, 'Thinking and exploring ideas' n, 1 o
    UNION ALL SELECT 'CAD' c, 'jhs' lv, 'Performing Arts' st, 'Planning, making and composing' n, 2 o
    UNION ALL SELECT 'CAD' c, 'jhs' lv, 'Performing Arts' st, 'Displaying and sharing' n, 3 o
    UNION ALL SELECT 'CAD' c, 'jhs' lv, 'Performing Arts' st, 'Appreciating and appraising' n, 4 o
    UNION ALL SELECT 'PHE' c, 'kg,primary,jhs' lv, 'Motor Skill and Movement Patterns' st, 'Locomotor skills' n, 1 o
    UNION ALL SELECT 'PHE' c, 'kg,primary,jhs' lv, 'Motor Skill and Movement Patterns' st, 'Non-locomotor skills' n, 2 o
    UNION ALL SELECT 'PHE' c, 'kg,primary,jhs' lv, 'Motor Skill and Movement Patterns' st, 'Manipulative skills' n, 3 o
    UNION ALL SELECT 'PHE' c, 'kg,primary,jhs' lv, 'Movement Concepts, Principles and Strategies' st, 'Space and effort awareness' n, 1 o
    UNION ALL SELECT 'PHE' c, 'kg,primary,jhs' lv, 'Movement Concepts, Principles and Strategies' st, 'Strategies and tactics in games' n, 2 o
    UNION ALL SELECT 'PHE' c, 'kg,primary,jhs' lv, 'Physical Fitness' st, 'Health-related fitness' n, 1 o
    UNION ALL SELECT 'PHE' c, 'kg,primary,jhs' lv, 'Physical Fitness' st, 'Fitness activities' n, 2 o
    UNION ALL SELECT 'PHE' c, 'kg,primary,jhs' lv, 'Values in Physical Education' st, 'Safety' n, 1 o
    UNION ALL SELECT 'PHE' c, 'kg,primary,jhs' lv, 'Values in Physical Education' st, 'Fair play and cooperation' n, 2 o
    UNION ALL SELECT 'PHE' c, 'jhs' lv, 'Health' st, 'Personal hygiene' n, 1 o
    UNION ALL SELECT 'PHE' c, 'jhs' lv, 'Health' st, 'Nutrition' n, 2 o
    UNION ALL SELECT 'PHE' c, 'jhs' lv, 'Health' st, 'Drug and substance abuse' n, 3 o
    UNION ALL SELECT 'COMP' c, 'primary,jhs' lv, 'Introduction to Computing' st, 'Parts of a computer and other technology tools' n, 1 o
    UNION ALL SELECT 'COMP' c, 'primary,jhs' lv, 'Introduction to Computing' st, 'Computer care and safety' n, 2 o
    UNION ALL SELECT 'COMP' c, 'primary,jhs' lv, 'Introduction to Computing' st, 'History and generations of computers' n, 3 o
    UNION ALL SELECT 'COMP' c, 'primary,jhs' lv, 'Productivity Software' st, 'Word processing' n, 1 o
    UNION ALL SELECT 'COMP' c, 'primary,jhs' lv, 'Productivity Software' st, 'Spreadsheets' n, 2 o
    UNION ALL SELECT 'COMP' c, 'primary,jhs' lv, 'Productivity Software' st, 'Presentations' n, 3 o
    UNION ALL SELECT 'COMP' c, 'primary,jhs' lv, 'Communication Networks' st, 'The Internet and the World Wide Web' n, 1 o
    UNION ALL SELECT 'COMP' c, 'primary,jhs' lv, 'Communication Networks' st, 'Email and online safety' n, 2 o
    UNION ALL SELECT 'COMP' c, 'primary,jhs' lv, 'Computational Thinking' st, 'Algorithms' n, 1 o
    UNION ALL SELECT 'COMP' c, 'primary,jhs' lv, 'Computational Thinking' st, 'Programming (block-based)' n, 2 o
    UNION ALL SELECT 'CT' c, 'jhs' lv, 'Health and Safety' st, 'Safety in the workshop and at home' n, 1 o
    UNION ALL SELECT 'CT' c, 'jhs' lv, 'Health and Safety' st, 'First aid' n, 2 o
    UNION ALL SELECT 'CT' c, 'jhs' lv, 'Materials for Production' st, 'Wood, metal, clay and plastics' n, 1 o
    UNION ALL SELECT 'CT' c, 'jhs' lv, 'Materials for Production' st, 'Food and fabrics' n, 2 o
    UNION ALL SELECT 'CT' c, 'jhs' lv, 'Tools, Equipment and Processes' st, 'Hand tools' n, 1 o
    UNION ALL SELECT 'CT' c, 'jhs' lv, 'Tools, Equipment and Processes' st, 'Measuring and marking out' n, 2 o
    UNION ALL SELECT 'CT' c, 'jhs' lv, 'Tools, Equipment and Processes' st, 'Food preparation and sewing' n, 3 o
    UNION ALL SELECT 'CT' c, 'jhs' lv, 'Technical Drawing' st, 'Drawing instruments and lines' n, 1 o
    UNION ALL SELECT 'CT' c, 'jhs' lv, 'Technical Drawing' st, 'Plane geometry' n, 2 o
    UNION ALL SELECT 'CT' c, 'jhs' lv, 'Technical Drawing' st, 'Pictorial and orthographic drawing' n, 3 o
    UNION ALL SELECT 'CT' c, 'jhs' lv, 'Entrepreneurship' st, 'Business ideas' n, 1 o
    UNION ALL SELECT 'CT' c, 'jhs' lv, 'Entrepreneurship' st, 'Costing and pricing' n, 2 o
    UNION ALL SELECT 'FRE' c, 'primary,jhs' lv, 'Oral Communication' st, 'Greetings and introductions' n, 1 o
    UNION ALL SELECT 'FRE' c, 'primary,jhs' lv, 'Oral Communication' st, 'Listening and speaking' n, 2 o
    UNION ALL SELECT 'FRE' c, 'primary,jhs' lv, 'Reading' st, 'Reading comprehension' n, 1 o
    UNION ALL SELECT 'FRE' c, 'primary,jhs' lv, 'Reading' st, 'Vocabulary' n, 2 o
    UNION ALL SELECT 'FRE' c, 'primary,jhs' lv, 'Writing' st, 'Sentence writing' n, 1 o
    UNION ALL SELECT 'FRE' c, 'primary,jhs' lv, 'Writing' st, 'Short compositions' n, 2 o
    UNION ALL SELECT 'FRE' c, 'primary,jhs' lv, 'Grammar' st, 'Articles and nouns' n, 1 o
    UNION ALL SELECT 'FRE' c, 'primary,jhs' lv, 'Grammar' st, 'Verbs and tenses' n, 2 o
    UNION ALL SELECT 'BIB' c, 'creche,kg,primary,jhs' lv, 'Old Testament' st, 'Creation' n, 1 o
    UNION ALL SELECT 'BIB' c, 'creche,kg,primary,jhs' lv, 'Old Testament' st, 'The patriarchs' n, 2 o
    UNION ALL SELECT 'BIB' c, 'creche,kg,primary,jhs' lv, 'Old Testament' st, 'Kings and prophets' n, 3 o
    UNION ALL SELECT 'BIB' c, 'creche,kg,primary,jhs' lv, 'New Testament' st, 'The life of Jesus' n, 1 o
    UNION ALL SELECT 'BIB' c, 'creche,kg,primary,jhs' lv, 'New Testament' st, 'Parables and miracles' n, 2 o
    UNION ALL SELECT 'BIB' c, 'creche,kg,primary,jhs' lv, 'New Testament' st, 'The early church' n, 3 o
    UNION ALL SELECT 'BIB' c, 'creche,kg,primary,jhs' lv, 'Memory Verses' st, 'Weekly memory verses' n, 1 o
    UNION ALL SELECT 'BIB' c, 'creche,kg,primary,jhs' lv, 'Christian Living' st, 'The Sabbath' n, 1 o
    UNION ALL SELECT 'BIB' c, 'creche,kg,primary,jhs' lv, 'Christian Living' st, 'Health and temperance' n, 2 o
    UNION ALL SELECT 'BIB' c, 'creche,kg,primary,jhs' lv, 'Christian Living' st, 'Prayer and worship' n, 3 o
) d ON d.c = s.code AND d.st = p.name AND d.lv = p.levels
WHERE p.parent_id IS NULL AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM curriculum_strands WHERE parent_id IS NOT NULL) y);

-- ---------------------------------------------------------------- Rooms & resources
CREATE TABLE IF NOT EXISTS rooms (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(60) NOT NULL UNIQUE,
    kind ENUM('classroom','lab','hall','library','office','store','other') NOT NULL DEFAULT 'classroom',
    capacity SMALLINT UNSIGNED NOT NULL DEFAULT 40,
    class_id INT UNSIGNED NULL,
    notes VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_room_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS resource_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id INT UNSIGNED NOT NULL,
    category ENUM('furniture','textbook','equipment','teaching_aid','ict','other') NOT NULL DEFAULT 'furniture',
    name VARCHAR(120) NOT NULL,
    subject_id INT UNSIGNED NULL,
    qty_good INT UNSIGNED NOT NULL DEFAULT 0,
    qty_repair INT UNSIGNED NOT NULL DEFAULT 0,
    qty_bad INT UNSIGNED NOT NULL DEFAULT 0,
    per_pupil DECIMAL(4,2) NULL,
    needed INT UNSIGNED NULL,
    notes VARCHAR(255) NULL,
    updated_by INT UNSIGNED NULL,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_res_room (room_id),
    CONSTRAINT fk_res_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    CONSTRAINT fk_res_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Exam timetable
CREATE TABLE IF NOT EXISTS exam_series (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    term_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    instructions TEXT NULL,
    status ENUM('draft','published') NOT NULL DEFAULT 'draft',
    notice_id INT UNSIGNED NULL,
    published_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_exs_term FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE CASCADE,
    CONSTRAINT fk_exs_notice FOREIGN KEY (notice_id) REFERENCES notices(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_papers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    series_id INT UNSIGNED NOT NULL,
    class_id INT UNSIGNED NOT NULL,
    subject_id INT UNSIGNED NOT NULL,
    paper VARCHAR(40) NULL,
    exam_date DATE NOT NULL,
    start_time TIME NOT NULL,
    duration_min SMALLINT UNSIGNED NOT NULL DEFAULT 60,
    room_id INT UNSIGNED NULL,
    invigilator_id INT UNSIGNED NULL,
    notes VARCHAR(200) NULL,
    INDEX idx_exp_series (series_id, exam_date, start_time),
    CONSTRAINT fk_exp_series FOREIGN KEY (series_id) REFERENCES exam_series(id) ON DELETE CASCADE,
    CONSTRAINT fk_exp_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_exp_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    CONSTRAINT fk_exp_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL,
    CONSTRAINT fk_exp_invig FOREIGN KEY (invigilator_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Seating plans
CREATE TABLE IF NOT EXISTS seating_plans (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kind ENUM('class','exam') NOT NULL DEFAULT 'class',
    class_id INT UNSIGNED NULL,
    series_id INT UNSIGNED NULL,
    room_id INT UNSIGNED NULL,
    title VARCHAR(120) NOT NULL,
    class_list VARCHAR(255) NULL,
    seat_rows TINYINT UNSIGNED NOT NULL DEFAULT 6,
    seat_cols TINYINT UNSIGNED NOT NULL DEFAULT 6,
    method VARCHAR(20) NOT NULL DEFAULT 'alpha',
    updated_by INT UNSIGNED NULL,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_seat_class (kind, class_id),
    CONSTRAINT fk_seatp_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_seatp_series FOREIGN KEY (series_id) REFERENCES exam_series(id) ON DELETE CASCADE,
    CONSTRAINT fk_seatp_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seating_seats (
    plan_id INT UNSIGNED NOT NULL,
    row_no TINYINT UNSIGNED NOT NULL,
    col_no TINYINT UNSIGNED NOT NULL,
    pupil_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (plan_id, row_no, col_no),
    UNIQUE KEY uq_seat_pupil (plan_id, pupil_id),
    CONSTRAINT fk_seat_plan FOREIGN KEY (plan_id) REFERENCES seating_plans(id) ON DELETE CASCADE,
    CONSTRAINT fk_seat_pupil FOREIGN KEY (pupil_id) REFERENCES pupils(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Permissions for the built-in roles only (custom roles get them only if the administrator ticks them)
INSERT IGNORE INTO role_permissions (role_key, permission)
SELECT r.role_key, p.perm FROM roles r JOIN (
    SELECT 'head_teacher' b, 'teachers.view' perm UNION ALL SELECT 'academic_coordinator', 'teachers.view'
    UNION ALL SELECT 'head_teacher', 'appraisal.manage'
    UNION ALL SELECT 'head_teacher', 'curriculum.manage' UNION ALL SELECT 'academic_coordinator', 'curriculum.manage'
    UNION ALL SELECT 'head_teacher', 'scheme.plan' UNION ALL SELECT 'academic_coordinator', 'scheme.plan'
    UNION ALL SELECT 'class_teacher', 'scheme.plan' UNION ALL SELECT 'subject_teacher', 'scheme.plan'
    UNION ALL SELECT 'head_teacher', 'seating.manage' UNION ALL SELECT 'academic_coordinator', 'seating.manage' UNION ALL SELECT 'class_teacher', 'seating.manage'
    UNION ALL SELECT 'head_teacher', 'resources.view' UNION ALL SELECT 'academic_coordinator', 'resources.view' UNION ALL SELECT 'class_teacher', 'resources.view'
    UNION ALL SELECT 'subject_teacher', 'resources.view' UNION ALL SELECT 'bursar', 'resources.view'
    UNION ALL SELECT 'head_teacher', 'resources.manage' UNION ALL SELECT 'academic_coordinator', 'resources.manage' UNION ALL SELECT 'bursar', 'resources.manage'
    UNION ALL SELECT 'head_teacher', 'examtt.manage' UNION ALL SELECT 'academic_coordinator', 'examtt.manage'
) p ON p.b = r.role_key
WHERE r.kind = 'staff' AND r.is_system = 1;
