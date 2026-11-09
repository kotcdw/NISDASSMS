-- NISDAS SMS — WhatsApp messages, phone app notifications (web push), notice board numbering and priority,
-- admission merit list, fee instalment plans, bus driver licence / vehicle papers

ALTER TABLE notification_outbox MODIFY channel ENUM('sms','email','whatsapp') NOT NULL DEFAULT 'sms';
ALTER TABLE notification_outbox ADD COLUMN delivery_status VARCHAR(20) NULL;
ALTER TABLE notification_outbox ADD COLUMN read_at DATETIME NULL;
ALTER TABLE notification_outbox ADD INDEX idx_outbox_ref (provider_ref);
INSERT IGNORE INTO settings (`key`, `value`) VALUES ('whatsapp_provider', 'off'), ('whatsapp_mode', 'off');

ALTER TABLE notices ADD COLUMN circular_no VARCHAR(30) NULL;
ALTER TABLE notices ADD COLUMN priority ENUM('normal','important','urgent') NOT NULL DEFAULT 'normal';

CREATE TABLE IF NOT EXISTS push_subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    endpoint TEXT NOT NULL,
    endpoint_hash CHAR(64) NOT NULL,
    p256dh VARCHAR(120) NOT NULL,
    auth VARCHAR(60) NOT NULL,
    user_agent VARCHAR(190) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME NULL,
    UNIQUE KEY uq_push_endpoint (endpoint_hash),
    CONSTRAINT fk_push_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE inapp_notifications ADD COLUMN pushed_at DATETIME NULL;

ALTER TABLE applications ADD COLUMN interview_score DECIMAL(5,2) NULL;

CREATE TABLE IF NOT EXISTS invoice_instalments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    seq TINYINT UNSIGNED NOT NULL,
    due_date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    reminded_at DATETIME NULL,
    UNIQUE KEY uq_inst (invoice_id, seq),
    CONSTRAINT fk_inst_inv FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE transport_routes ADD COLUMN driver_licence_no VARCHAR(40) NULL;
ALTER TABLE transport_routes ADD COLUMN driver_licence_expiry DATE NULL;
ALTER TABLE transport_routes ADD COLUMN roadworthy_expiry DATE NULL;
ALTER TABLE transport_routes ADD COLUMN insurance_expiry DATE NULL;

INSERT IGNORE INTO notification_templates (`key`, name, description, sms_body, placeholders) VALUES
('fee_instalment', 'Instalment due', 'Sent 3 days before (and on) each instalment date of a payment plan', 'Dear {guardian}, instalment {no} of {pupil}''s {title} (GHS {amount}) is due {due}. Pay: {link} - {school}', 'guardian, pupil, no, title, amount, due, link, school');
