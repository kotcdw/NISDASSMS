-- NISDAS SMS — Analytics Dashboard: each person's own choice of widgets, order and size

CREATE TABLE IF NOT EXISTS dashboard_layouts (
    user_id INT UNSIGNED NOT NULL PRIMARY KEY,
    layout TEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_dashlayout_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
