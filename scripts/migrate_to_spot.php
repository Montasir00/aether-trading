<?php
/**
 * Migration helper: runs spot trading migration and migrates user balances
 * Usage: php scripts/migrate_to_spot.php
 */
require_once __DIR__ . "/../config.php";

$sqlFile = __DIR__ . "/../migrations/002_spot_trading.sql";
if (!file_exists($sqlFile)) {
    echo "Migration SQL not found: $sqlFile\n";
    exit(1);
}

$sql = file_get_contents($sqlFile);
if ($sql === false) {
    echo "Failed to read migration SQL.\n";
    exit(1);
}

// Execute multi-statement SQL
if ($conn->multi_query($sql)) {
    do {
        if ($res = $conn->store_result()) {
            $res->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    echo "Migration tables created/verified.\n";
} else {
    echo "Migration SQL failed: " . $conn->error . "\n";
    exit(1);
}

// Create wallets for users and migrate existing xau/xag balances if present
$usersRes = $conn->query("SELECT id, xau_balance, xag_balance FROM users");
if (!$usersRes) {
    echo "Failed to fetch users: " . $conn->error . "\n";
    exit(1);
}

$created = 0;
while ($u = $usersRes->fetch_assoc()) {
    $uid = (int)$u['id'];

    // create wallet if not exists
    $stmt = $conn->prepare("INSERT IGNORE INTO wallets (user_id) VALUES (?)");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $stmt->close();

    // fetch wallet id
    $stmt = $conn->prepare("SELECT id FROM wallets WHERE user_id = ? LIMIT 1");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $rid = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $walletId = $rid['id'] ?? null;
    if (!$walletId) continue;

    // migrate XAU/XAG if columns exist and values non-zero
    foreach (['XAU' => 'xau_balance', 'XAG' => 'xag_balance'] as $asset => $col) {
        if (!array_key_exists($col, $u)) continue;
        $val = (float)$u[$col];
        if ($val <= 0) continue;

        // insert or update balances
        $stmt = $conn->prepare(
            "INSERT INTO balances (wallet_id, asset, balance, reserved) VALUES (?, ?, ?, 0)
             ON DUPLICATE KEY UPDATE balance = GREATEST(balance, VALUES(balance))"
        );
        $stmt->bind_param('isd', $walletId, $asset, $val);
        $stmt->execute();
        $stmt->close();
        $created++;
    }
}

echo "Wallets ensured for users; migrated $created asset balances.\n";

// Seed minimal trading pairs (extend later)
$pairs = [
    ['XAUUSDT', 'XAU', 'USDT'],
    ['XAGUSDT', 'XAG', 'USDT']
];
$stmt = $conn->prepare("INSERT IGNORE INTO trading_pairs (symbol, base_asset, quote_asset, price_precision, qty_precision, min_qty, min_price, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
foreach ($pairs as $p) {
    [$sym, $base, $quote] = $p;
    $pp = 8; $qp = 8; $minq = 0.0001; $minp = 0.0001; $act = 1;
    $stmt->bind_param('sssiiddi', $sym, $base, $quote, $pp, $qp, $minq, $minp, $act);
    $stmt->execute();
}
$stmt->close();

echo "Seeded initial trading pairs.\n";

echo "Migration complete. Review tables and run reconciliation tests.\n";

return 0;

?>
