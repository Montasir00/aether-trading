-- ─────────────────────────────────────────────────────────────────────────────
-- Aether Trading Platform — Blockchain Integration DB Migration
-- Run this ONCE against your MySQL database before using the ETH trading feature.
-- ─────────────────────────────────────────────────────────────────────────────

-- 1. Add ETH wallet address column to users table
ALTER TABLE users 
    ADD COLUMN eth_address VARCHAR(42) DEFAULT NULL 
    COMMENT 'MetaMask/Ganache wallet address linked to this account';

-- 2. Add tx_hash column to transactions table
ALTER TABLE transactions 
    ADD COLUMN tx_hash VARCHAR(66) DEFAULT NULL 
    COMMENT 'On-chain transaction hash from Ganache (0x + 64 hex chars)';

-- 3. Add a unique index on tx_hash to prevent duplicate settlement
ALTER TABLE transactions 
    ADD UNIQUE INDEX idx_tx_hash (tx_hash);

-- 4. Verify columns were added
SELECT 
    COLUMN_NAME, 
    DATA_TYPE, 
    COLUMN_DEFAULT, 
    IS_NULLABLE, 
    COLUMN_COMMENT
FROM information_schema.COLUMNS
WHERE TABLE_NAME IN ('users', 'transactions')
  AND COLUMN_NAME IN ('eth_address', 'tx_hash')
ORDER BY TABLE_NAME, COLUMN_NAME;

