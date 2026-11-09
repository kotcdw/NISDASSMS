-- Exams integrity: grading frozen per term once reports are released, release history (for "corrected report" SMS).

CREATE TABLE IF NOT EXISTS grading_snapshots (
    term_id INT UNSIGNED NOT NULL,
    category VARCHAR(20) NOT NULL,
    data TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (term_id, category),
    CONSTRAINT fk_gsnap_term FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_release_history (
    term_id INT UNSIGNED NOT NULL,
    class_id INT UNSIGNED NOT NULL,
    times INT UNSIGNED NOT NULL DEFAULT 0,
    last_released_at DATETIME NULL,
    PRIMARY KEY (term_id, class_id),
    CONSTRAINT fk_rrh_term FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE CASCADE,
    CONSTRAINT fk_rrh_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Classes already released count as released once
INSERT IGNORE INTO report_release_history (term_id, class_id, times, last_released_at)
SELECT term_id, class_id, 1, released_at FROM report_releases;

INSERT IGNORE INTO notification_templates (`key`, name, description, sms_body, placeholders) VALUES
('results_corrected', 'Corrected report card', 'Sent to parents when a released report card is corrected and released again', 'Dear {guardian}, {pupil}''s {term} report has been corrected. Average: {average}%{position}. View it here: {link} - {school}', 'guardian, pupil, term, average, position, link, school');
