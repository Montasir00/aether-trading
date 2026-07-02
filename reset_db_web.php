<?php
require 'config.php';

header('Content-Type: text/plain');
echo "Starting database cleanup...\n";

// Disable foreign key checks to truncate/delete cleanly
$conn->query("SET FOREIGN_KEY_CHECKS = 0");

// Truncate transactional tables
$tables = ['transactions', 'orders', 'trades', 'balances', 'wallets', 'price_alerts', 'notifications'];
foreach ($tables as $table) {
    if ($conn->query("TRUNCATE TABLE `$table`")) {
        echo "Truncated table: $table\n";
    } else {
        echo "Failed to truncate $table: " . $conn->error . "\n";
    }
}

// Reset users table balances to defaults
if ($conn->query("UPDATE users SET balance = 10000.00, xau_balance = 0.000000, xag_balance = 0.0000, bot_position = 'NONE'")) {
    echo "Reset all users balances to 10,000.00 USDT, 0 XAU, 0 XAG.\n";
} else {
    echo "Failed to reset user balances: " . $conn->error . "\n";
}

// Enable foreign key checks back
$conn->query("SET FOREIGN_KEY_CHECKS = 1");

echo "Database successfully reset to a fresh state!\n";
?>
