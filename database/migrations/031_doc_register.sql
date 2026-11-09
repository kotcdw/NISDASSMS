-- NISDAS SMS — printed money documents: original vs reprint (COPY) and a check code for the QR "is this genuine?" page.
CREATE TABLE doc_register (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doc_type VARCHAR(20) NOT NULL,
    doc_no VARCHAR(30) NOT NULL,
    token CHAR(24) NOT NULL UNIQUE,
    prints INT UNSIGNED NOT NULL DEFAULT 0,
    first_printed_at DATETIME NULL,
    first_printed_by INT UNSIGNED NULL,
    last_printed_at DATETIME NULL,
    last_printed_by INT UNSIGNED NULL,
    UNIQUE KEY uq_doc (doc_type, doc_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
