ALTER TABLE orders ADD COLUMN escrowed_eth_wei VARCHAR(78) DEFAULT NULL;
ALTER TABLE orders ADD COLUMN escrow_tx_hash VARCHAR(100) DEFAULT NULL;
ALTER TABLE orders ADD COLUMN on_chain_settled TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE orders ADD COLUMN settle_tx_hash VARCHAR(100) DEFAULT NULL;
ALTER TABLE orders ADD COLUMN cancel_tx_hash VARCHAR(100) DEFAULT NULL;
ALTER TABLE orders ADD COLUMN eth_wallet_address VARCHAR(100) DEFAULT NULL;

CREATE TABLE IF NOT EXISTS limit_order_escrows (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    order_id        INT          NOT NULL UNIQUE,
    user_id         INT          NOT NULL,
    eth_wallet      VARCHAR(100) NOT NULL,
    eth_wei         VARCHAR(78)  NOT NULL,
    eth_ether       DECIMAL(18,8) NOT NULL,
    escrow_tx_hash  VARCHAR(100) NOT NULL,
    status          ENUM('locked','settled','refunded') NOT NULL DEFAULT 'locked',
    settle_tx_hash  VARCHAR(100) DEFAULT NULL,
    cancel_tx_hash  VARCHAR(100) DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
    INDEX idx_escrow_status (status),
    INDEX idx_escrow_user   (user_id)
);

SELECT 'Migration complete' AS result;
