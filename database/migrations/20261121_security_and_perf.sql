-- Security & Performance Migration (2026-11-21)
-- K1: Persistent rate-limit table for admin authentication and credential checks.
CREATE TABLE IF NOT EXISTS login_attempts (
  username VARCHAR(150) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  last_attempt DATETIME NOT NULL,
  PRIMARY KEY (username, ip),
  INDEX idx_last_attempt (last_attempt)
) ENGINE=InnoDB;

-- P3: Persist computed aspect_ratio on portfolio_details to avoid runtime getimagesize() disk I/O.
ALTER TABLE portfolio_details
  ADD COLUMN aspect_ratio DECIMAL(6,4) NULL AFTER file_path;
