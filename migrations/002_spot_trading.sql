-- Migration: Add spot trading tables (wallets, balances, trading_pairs, orders, trades, order_book)
-- Run this against your MySQL database to create the required tables.

CREATE TABLE IF NOT EXISTS wallets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY ux_wallet_user (user_id),
  INDEX idx_wallet_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS balances (
  wallet_id INT UNSIGNED NOT NULL,
  asset VARCHAR(16) NOT NULL,
  balance DECIMAL(28,8) NOT NULL DEFAULT 0,
  reserved DECIMAL(28,8) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (wallet_id, asset),
  FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS trading_pairs (
  symbol VARCHAR(32) NOT NULL PRIMARY KEY,
  base_asset VARCHAR(16) NOT NULL,
  quote_asset VARCHAR(16) NOT NULL,
  price_precision INT NOT NULL DEFAULT 8,
  qty_precision INT NOT NULL DEFAULT 8,
  min_qty DECIMAL(28,8) DEFAULT 0,
  min_price DECIMAL(28,8) DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  pair VARCHAR(32) NOT NULL,
  side ENUM('BUY','SELL') NOT NULL,
  type ENUM('market','limit') NOT NULL,
  price DECIMAL(28,8) DEFAULT NULL,
  qty DECIMAL(28,8) NOT NULL,
  filled_qty DECIMAL(28,8) NOT NULL DEFAULT 0,
  total DECIMAL(28,8) NOT NULL DEFAULT 0,
  status ENUM('open','partially_filled','completed','canceled') NOT NULL DEFAULT 'open',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_orders_user (user_id),
  INDEX idx_orders_pair_status (pair, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS trades (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  buy_order_id BIGINT UNSIGNED DEFAULT NULL,
  sell_order_id BIGINT UNSIGNED DEFAULT NULL,
  pair VARCHAR(32) NOT NULL,
  price DECIMAL(28,8) NOT NULL,
  qty DECIMAL(28,8) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_trades_pair (pair),
  INDEX idx_trades_time (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_book_snapshots (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  pair VARCHAR(32) NOT NULL,
  payload LONGTEXT NOT NULL,
  captured_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_obs_pair_time (pair, captured_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
