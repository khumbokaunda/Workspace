-- Migration 001: login attempt tracking for brute-force protection.
-- Additive only, the core tables in schema.sql are untouched.
--
-- Apply with:
--   mysql -u root -p wms < database/migrations/001_login_attempts.sql

CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    successful TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username_time (username, attempted_at),
    INDEX idx_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
