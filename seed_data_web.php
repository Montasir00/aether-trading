<?php
require 'config.php';

header('Content-Type: text/plain');
echo "Starting database seeding...\n";

// Disable foreign key checks to make insertions clean
$conn->query("SET FOREIGN_KEY_CHECKS = 0");

// 1. Clear any existing records from transactional tables to avoid duplicates
$tables = ['transactions', 'orders', 'trades', 'balances', 'wallets', 'price_alerts'];
foreach ($tables as $table) {
    $conn->query("TRUNCATE TABLE `$table`");
}

// 2. Ensure wallets exist for users: ID 2 (user), ID 4 (seller), ID 5 (buyer)
$conn->query("INSERT INTO wallets (id, user_id) VALUES (1, 2), (2, 4), (3, 5)");

// 3. Populate starting balances
// User ID 2 (user) - starting with mid balances
$conn->query("INSERT INTO balances (wallet_id, asset, balance, reserved) VALUES 
    (1, 'USDT', 8000.00, 0),
    (1, 'XAU', 0.50, 0),
    (1, 'XAG', 20.00, 0),
    (1, 'AAPL', 10.00, 0)
");
$conn->query("UPDATE users SET balance = 8000.00, xau_balance = 0.50, xag_balance = 20.00 WHERE id = 2");

// User ID 4 (seller) - starting with lots of base assets to sell
$conn->query("INSERT INTO balances (wallet_id, asset, balance, reserved) VALUES 
    (2, 'USDT', 5000.00, 0),
    (2, 'XAU', 10.00, 0),
    (2, 'XAG', 1000.00, 0),
    (2, 'AAPL', 200.00, 0)
");
$conn->query("UPDATE users SET balance = 5000.00, xau_balance = 10.00, xag_balance = 1000.00 WHERE id = 4");

// User ID 5 (buyer) - starting with lots of USDT to buy
$conn->query("INSERT INTO balances (wallet_id, asset, balance, reserved) VALUES 
    (3, 'USDT', 50000.00, 0),
    (3, 'XAU', 0.00, 0),
    (3, 'XAG', 0.00, 0),
    (3, 'AAPL', 0.00, 0)
");
$conn->query("UPDATE users SET balance = 50000.00, xau_balance = 0.00, xag_balance = 0.00 WHERE id = 5");


// 4. Populate the ORDER BOOK with active, pending limit orders (spread around market price)
// XAU (Gold) - Sells (Asks) from User 4 (seller)
$conn->query("INSERT INTO orders (user_id, pair, side, type, price, qty, filled_qty, total, status, created_at) VALUES 
    (4, 'XAUUSDT', 'SELL', 'limit', 2510.00, 0.50, 0, 1255.00, 'open', NOW() - INTERVAL 10 MINUTE),
    (4, 'XAUUSDT', 'SELL', 'limit', 2515.50, 1.20, 0, 3018.60, 'open', NOW() - INTERVAL 8 MINUTE),
    (4, 'XAUUSDT', 'SELL', 'limit', 2520.00, 0.80, 0, 2016.00, 'open', NOW() - INTERVAL 5 MINUTE)
");

// XAU (Gold) - Buys (Bids) from User 5 (buyer)
$conn->query("INSERT INTO orders (user_id, pair, side, type, price, qty, filled_qty, total, status, created_at) VALUES 
    (5, 'XAUUSDT', 'BUY', 'limit', 2490.00, 0.40, 0, 996.00, 'open', NOW() - INTERVAL 9 MINUTE),
    (5, 'XAUUSDT', 'BUY', 'limit', 2485.50, 0.95, 0, 2361.23, 'open', NOW() - INTERVAL 7 MINUTE),
    (5, 'XAUUSDT', 'BUY', 'limit', 2480.00, 1.50, 0, 3720.00, 'open', NOW() - INTERVAL 4 MINUTE)
");

// XAG (Silver) - Sells (Asks) from User 4 (seller)
$conn->query("INSERT INTO orders (user_id, pair, side, type, price, qty, filled_qty, total, status, created_at) VALUES 
    (4, 'XAGUSDT', 'SELL', 'limit', 31.50, 150.00, 0, 4725.00, 'open', NOW() - INTERVAL 10 MINUTE),
    (4, 'XAGUSDT', 'SELL', 'limit', 32.00, 300.00, 0, 9600.00, 'open', NOW() - INTERVAL 8 MINUTE),
    (4, 'XAGUSDT', 'SELL', 'limit', 32.50, 500.00, 0, 16250.00, 'open', NOW() - INTERVAL 5 MINUTE)
");

// XAG (Silver) - Buys (Bids) from User 5 (buyer)
$conn->query("INSERT INTO orders (user_id, pair, side, type, price, qty, filled_qty, total, status, created_at) VALUES 
    (5, 'XAGUSDT', 'BUY', 'limit', 29.50, 200.00, 0, 5900.00, 'open', NOW() - INTERVAL 9 MINUTE),
    (5, 'XAGUSDT', 'BUY', 'limit', 29.00, 400.00, 0, 11600.00, 'open', NOW() - INTERVAL 7 MINUTE),
    (5, 'XAGUSDT', 'BUY', 'limit', 28.50, 600.00, 0, 17100.00, 'open', NOW() - INTERVAL 4 MINUTE)
");


// 5. Populate RECENT TRANSACTIONS (Completed fills) for User ID 2 (user)
// We'll record these both in orders (completed status) and transactions table
$conn->query("INSERT INTO orders (user_id, pair, side, type, price, qty, filled_qty, total, status, created_at) VALUES 
    (2, 'XAUUSDT', 'BUY', 'market', 2495.00, 0.25, 0.25, 623.75, 'completed', NOW() - INTERVAL 50 MINUTE),
    (2, 'XAUUSDT', 'SELL', 'market', 2505.00, 0.10, 0.10, 250.50, 'completed', NOW() - INTERVAL 40 MINUTE),
    (2, 'XAGUSDT', 'BUY', 'market', 29.90, 50.00, 50.00, 1495.00, 'completed', NOW() - INTERVAL 30 MINUTE),
    (2, 'XAGUSDT', 'SELL', 'market', 30.50, 20.00, 20.00, 610.00, 'completed', NOW() - INTERVAL 20 MINUTE),
    (2, 'AAPLUSDT', 'BUY', 'market', 175.50, 10.00, 10.00, 1755.00, 'completed', NOW() - INTERVAL 10 MINUTE)
");

$conn->query("INSERT INTO transactions (user_id, type, coin, amount, price, total, order_type, status, created_at) VALUES 
    (2, 'BUY', 'XAU', 0.250000, 2495.00, 623.75, 'market', 'completed', NOW() - INTERVAL 50 MINUTE),
    (2, 'SELL', 'XAU', 0.100000, 2505.00, 250.50, 'market', 'completed', NOW() - INTERVAL 40 MINUTE),
    (2, 'BUY', 'XAG', 50.000000, 29.90, 1495.00, 'market', 'completed', NOW() - INTERVAL 30 MINUTE),
    (2, 'SELL', 'XAG', 20.000000, 30.50, 610.00, 'market', 'completed', NOW() - INTERVAL 20 MINUTE),
    (2, 'BUY', 'AAPL', 10.000000, 175.50, 1755.00, 'market', 'completed', NOW() - INTERVAL 10 MINUTE)
");

// Enable foreign key checks back
$conn->query("SET FOREIGN_KEY_CHECKS = 1");

echo "Database successfully populated with clean simulation data!\n";
?>
