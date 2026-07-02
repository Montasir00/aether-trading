<?php
require 'config.php';

// Helper function to run query and swallow duplicate column errors safely
function addColumnSafe($conn, $table, $column, $definition) {
    try {
        $q = "ALTER TABLE `$table` ADD COLUMN `$column` $definition";
        if ($conn->query($q)) {
            echo "Successfully added column `$column` to `$table` table.\n";
        }
    } catch (mysqli_sql_exception $e) {
        // If error is duplicate column (code 1060), it's already there, so we can ignore it safely
        if ($e->getCode() == 1060 || strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "Column `$column` already exists in `$table` table. Skipping.\n";
        } else {
            echo "Failed to add column `$column` to `$table`: " . $e->getMessage() . "\n";
        }
    }
}

echo "Starting migrations...\n";

// 1. Add bot_position to users if missing
addColumnSafe($conn, 'users', 'bot_position', "VARCHAR(10) NOT NULL DEFAULT 'NONE'");

// 2. Add required alert columns if missing
addColumnSafe($conn, 'price_alerts', 'operator', "VARCHAR(2) NOT NULL DEFAULT '>= ' AFTER target_price");
addColumnSafe($conn, 'price_alerts', 'processing', "TINYINT(1) NOT NULL DEFAULT 0 AFTER notified");
addColumnSafe($conn, 'price_alerts', 'processing_started_at', "DATETIME DEFAULT NULL AFTER processing");
addColumnSafe($conn, 'price_alerts', 'send_attempts', "INT NOT NULL DEFAULT 0 AFTER processing_started_at");
addColumnSafe($conn, 'price_alerts', 'last_attempt_at', "DATETIME DEFAULT NULL AFTER send_attempts");

// 3. Add simulation mock price override columns to bot_control
addColumnSafe($conn, 'bot_control', 'mock_xau_price', "DECIMAL(18,2) DEFAULT NULL");
addColumnSafe($conn, 'bot_control', 'mock_xag_price', "DECIMAL(18,2) DEFAULT NULL");

echo "Migrations completed successfully.\n";
?>
