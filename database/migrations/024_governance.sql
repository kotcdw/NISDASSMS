-- NISDAS SMS — Governance & risk (Board view): board members and meetings with attendance,
-- a policy register with review dates, and a risk register with mitigation status.

CREATE TABLE IF NOT EXISTS board_members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    position VARCHAR(80) NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(120) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS board_meetings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meeting_date DATE NOT NULL,
    start_time TIME NULL,
    title VARCHAR(150) NOT NULL,
    kind ENUM('regular','special','agm','committee') NOT NULL DEFAULT 'regular',
    venue VARCHAR(120) NULL,
    agenda TEXT NULL,
    decisions TEXT NULL,
    reminded_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_bm_date (meeting_date),
    CONSTRAINT fk_bm_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS board_attendance (
    meeting_id INT UNSIGNED NOT NULL,
    member_id INT UNSIGNED NOT NULL,
    status ENUM('present','apology','absent') NOT NULL,
    PRIMARY KEY (meeting_id, member_id),
    CONSTRAINT fk_ba_meeting FOREIGN KEY (meeting_id) REFERENCES board_meetings(id) ON DELETE CASCADE,
    CONSTRAINT fk_ba_member FOREIGN KEY (member_id) REFERENCES board_members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS school_policies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    category VARCHAR(60) NULL,
    owner VARCHAR(120) NULL,
    approved_on DATE NULL,
    review_months SMALLINT UNSIGNED NOT NULL DEFAULT 12,
    last_reviewed DATE NULL,
    next_review DATE NOT NULL,
    status ENUM('active','retired') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    reminded_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pol_review (status, next_review),
    CONSTRAINT fk_pol_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS risks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ref_no VARCHAR(20) NOT NULL UNIQUE,
    title VARCHAR(150) NOT NULL,
    category VARCHAR(40) NOT NULL DEFAULT 'operational',
    likelihood TINYINT UNSIGNED NOT NULL DEFAULT 3,
    impact TINYINT UNSIGNED NOT NULL DEFAULT 3,
    owner VARCHAR(120) NULL,
    mitigation TEXT NULL,
    mitigation_status ENUM('not_started','in_progress','in_place') NOT NULL DEFAULT 'not_started',
    review_date DATE NULL,
    status ENUM('open','closed') NOT NULL DEFAULT 'open',
    reminded_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_risk_status (status),
    CONSTRAINT fk_risk_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO role_permissions (role_key, permission)
SELECT r.role_key, p.perm FROM roles r JOIN (
    SELECT 'head_teacher' b, 'governance.view' perm UNION ALL SELECT 'head_teacher', 'governance.manage'
    UNION ALL SELECT 'bursar', 'governance.view'
) p ON p.b = r.role_key
WHERE r.is_system = 1;
