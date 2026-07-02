-- =============================================================
--  Admin Panel Migration — Aether Trading Platform
--  Run this query once in phpMyAdmin (or any MySQL client)
--  against the `aether_trading` database.
-- =============================================================

USE aether_trading;

-- Add is_admin flag to users table (safe: only adds if not already present)
ALTER TABLE users
  ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0
  COMMENT '1 = administrator, 0 = regular user';

-- ---------------------------------------------------------------
--  HOW TO GRANT ADMIN ACCESS TO A USER
--  Replace 1 with the actual user ID you want to promote.
-- ---------------------------------------------------------------
-- UPDATE users SET is_admin = 1 WHERE id = 1;

-- Verify the column was added
-- SELECT id, username, email, is_admin FROM users LIMIT 10;
