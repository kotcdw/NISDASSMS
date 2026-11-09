-- NISDAS SMS — Sprint 8: go-live (backups, imports, readiness checklist, trial run)

CREATE TABLE backups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_name VARCHAR(120) NOT NULL UNIQUE,
    size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    tables_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    kind ENUM('auto','manual') NOT NULL DEFAULT 'manual',
    copied_to VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_backup_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE import_batches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kind VARCHAR(20) NOT NULL,
    file_name VARCHAR(160) NOT NULL,
    rows_total INT UNSIGNED NOT NULL DEFAULT 0,
    rows_imported INT UNSIGNED NOT NULL DEFAULT 0,
    rows_skipped INT UNSIGNED NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_import_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Go-live confirmations and trial-run (user acceptance test) ticks
CREATE TABLE golive_checks (
    check_key VARCHAR(60) PRIMARY KEY,
    done_by INT UNSIGNED NULL,
    done_at DATETIME NULL,
    note VARCHAR(255) NULL,
    CONSTRAINT fk_golive_user FOREIGN KEY (done_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (`key`, `value`) VALUES
('backup_auto', '1'),
('backup_keep', '14'),
('backup_hour', '18'),
('backup_copy_dir', ''),
('backup_last_auto', '');
