<?php
require_once __DIR__ . '/../config.php';

echo "=== DATABASE DIAGNOSTICS ===\n";

// 1. Check users
$res = $conn->query("SELECT id, username, balance, xau_balance, bot_position FROM users");
while ($row = $res->fetch_assoc()) {
    echo "User ID: {$row['id']} | Username: {$row['username']} | USDT: {$row['balance']} | XAU: {$row['xau_balance']} | Bot Position: {$row['bot_position']}\n";
}

// 2. Check price history count
$res = $conn->query("SELECT count(*) as count FROM price_history WHERE asset = 'XAU'");
$row = $res->fetch_assoc();
echo "XAU Price History Count: {$row['count']}\n";

// 3. Check bot control status
$res = $conn->query("SELECT * FROM bot_control WHERE id = 1");
if ($res && $row = $res->fetch_assoc()) {
    echo "Bot Control Active (DB): " . ($row['is_active'] ? 'YES' : 'NO') . " | Mock XAU: {$row['mock_xau_price']}\n";
}
?>
