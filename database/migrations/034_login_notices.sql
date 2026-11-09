-- NISDAS SMS — notice board on the sign-in page: a notice shows there (for anyone to read) only when ticked.
ALTER TABLE notices ADD COLUMN on_login TINYINT(1) NOT NULL DEFAULT 0 AFTER is_pinned;
