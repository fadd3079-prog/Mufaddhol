-- Add read-only demo viewer role support to users table (2026-11-22)
ALTER TABLE users
  ADD COLUMN role ENUM('admin', 'viewer') NOT NULL DEFAULT 'admin' AFTER password_hash;
