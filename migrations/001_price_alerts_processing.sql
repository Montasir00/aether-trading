-- Migration: Add processing and audit columns to price_alerts and create notifications table
-- Run with migrate.php which records applied migrations in migrations_applied

START TRANSACTION;

ALTER TABLE price_alerts
  ADD COLUMN processing TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN processing_started_at DATETIME NULL,
  ADD COLUMN send_attempts INT NOT NULL DEFAULT 0,
  ADD COLUMN last_attempt_at DATETIME NULL;

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alert_id INT NULL,
    user_id INT NULL,
    status ENUM('sent','failed') NOT NULL,
    attempt INT NOT NULL DEFAULT 1,
    error_text TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (alert_id) REFERENCES price_alerts(id) ON DELETE CASCADE,
    INDEX idx_notifications_alert (alert_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
