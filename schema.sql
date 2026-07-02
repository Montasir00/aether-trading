-- =============================================================
--  Aether Trading Platform – Database Schema
--  Database: aether_trading
--  Engine:   InnoDB (MySQL / MariaDB)
-- =============================================================

CREATE DATABASE IF NOT EXISTS aether_trading
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE aether_trading;

-- -----------------------------------------------------------
--  TABLE: users
--  Stores user accounts, credentials, and commodity balances.
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(100) NOT NULL UNIQUE,
    email         VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,          -- bcrypt hash via password_hash()
    balance       DECIMAL(18,2) NOT NULL DEFAULT 10000.00,   -- USDT balance
    xau_balance   DECIMAL(18,6) NOT NULL DEFAULT 0.000000,   -- Gold (XAU) balance in troy oz
    xag_balance   DECIMAL(18,4) NOT NULL DEFAULT 0.0000,     -- Silver (XAG) balance in troy oz
    otp           VARCHAR(10)  DEFAULT NULL,      -- Email OTP for verification
    otp_expiry    DATETIME     DEFAULT NULL,      -- OTP expiration timestamp
    is_verified   TINYINT(1)   NOT NULL DEFAULT 0,-- Email verified flag
    bot_position  VARCHAR(10)  NOT NULL DEFAULT 'NONE', -- Bot strategy position
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_users_email (email)
) ENGINE=InnoDB;

-- -----------------------------------------------------------
--  TABLE: transactions
--  Logs every trade (market buy/sell, limit orders, etc.)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS transactions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    type        VARCHAR(10)   NOT NULL,           -- 'BUY' or 'SELL'
    coin        VARCHAR(10)   NOT NULL,           -- 'XAU' or 'XAG'
    amount      DECIMAL(18,6) NOT NULL,           -- Quantity in troy oz
    price       DECIMAL(18,2) NOT NULL,           -- Price per oz in USDT
    total       DECIMAL(18,2) DEFAULT NULL,       -- amount * price
    order_type  VARCHAR(10)   DEFAULT 'market',   -- 'market' or 'limit'
    status      VARCHAR(20)   DEFAULT 'completed',-- 'pending', 'completed', 'cancelled'
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_txn_user     (user_id),
    INDEX idx_txn_status   (status),
    INDEX idx_txn_date     (created_at)
) ENGINE=InnoDB;

-- -----------------------------------------------------------
--  TABLE: price_alerts
--  User-configured alerts that trigger email notifications.
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS price_alerts (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT NOT NULL,
    coin          VARCHAR(10)   NOT NULL,          -- 'XAU' or 'XAG'
    target_price  DECIMAL(18,2) NOT NULL,          -- Target price in USDT
    operator      VARCHAR(2)    NOT NULL DEFAULT '>=', -- '>=' or '<='
    notified      TINYINT(1)    NOT NULL DEFAULT 0,-- 0 = pending, 1 = triggered
    processing    TINYINT(1)    NOT NULL DEFAULT 0,
    processing_started_at DATETIME DEFAULT NULL,
    send_attempts INT NOT NULL DEFAULT 0,
    last_attempt_at DATETIME DEFAULT NULL,
    created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_alert_user (user_id),
    INDEX idx_alert_notified (notified)
) ENGINE=InnoDB;

-- -----------------------------------------------------------
--  TABLE: notifications
--  Audit trail for alert delivery attempts.
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    alert_id    INT NULL,
    user_id     INT NULL,
    status      ENUM('sent','failed') NOT NULL,
    attempt     INT NOT NULL DEFAULT 1,
    error_text  TEXT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (alert_id) REFERENCES price_alerts(id) ON DELETE CASCADE,
    INDEX idx_notifications_alert (alert_id),
    INDEX idx_notifications_user (user_id)
) ENGINE=InnoDB;

-- -----------------------------------------------------------
--  TABLE: price_history
--  Recorded XAU/XAG prices for historical analysis & charting.
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS price_history (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    asset       VARCHAR(10)   NOT NULL DEFAULT 'XAU', -- 'XAU' or 'XAG'
    price       DECIMAL(18,2) NOT NULL,
    recorded_at TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ph_asset_date (asset, recorded_at)
) ENGINE=InnoDB;

-- -----------------------------------------------------------
--  TABLE: bot_control
--  Single-row table controlling the automated trading bot.
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS bot_control (
    id        INT NOT NULL DEFAULT 1 PRIMARY KEY,
    is_active TINYINT(1) NOT NULL DEFAULT 0       -- 0 = off, 1 = on
) ENGINE=InnoDB;

-- Insert default row if empty
INSERT IGNORE INTO bot_control (id, is_active) VALUES (1, 0);

-- =============================================================
--  NOTES
-- =============================================================
-- • Default USDT balance for new users: 10,000 USDT (simulated)
-- • XAU = Gold, XAG = Silver — amounts in troy ounces
-- • Prices sourced from Binance XAUUSDT / XAGUSDT perpetual contracts
-- • OTP columns (otp, otp_expiry) used for email verification flow
-- • bot_control has exactly 1 row (id=1) toggled by bot_control.php
-- • Risk management thresholds are application-level (RiskManager.php)
--   and do not require additional DB tables
