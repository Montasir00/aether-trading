<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../matching_engine/matching_engine.php";
require_once __DIR__ . "/../trading_engine/RiskManager.php";

echo "Smoke test: fetch a wallet and place a small order\n";

$w = $conn->query("SELECT id, user_id FROM wallets LIMIT 1");
if (!$w || ($row = $w->fetch_assoc()) === null) {
    echo "No wallets found. Run migrate_to_spot.php first.\n";
    exit(1);
}

$walletId = (int)$row['id'];
$userId = (int)$row['user_id'];

echo "Using wallet id={$walletId} user_id={$userId}\n";

// Make the smoke test deterministic even if prior simulations consumed funds.
$topUp = 200000.0;
$stmt = $conn->prepare("UPDATE balances SET balance = balance + ? WHERE wallet_id = ? AND asset = 'USDT'");
$stmt->bind_param('di', $topUp, $walletId);
$stmt->execute();
$stmt->close();

echo "Topped up USDT by {$topUp} for the smoke test.\n";

$balRes = $conn->prepare("SELECT asset, balance, reserved FROM balances WHERE wallet_id = ?");
$balRes->bind_param('i', $walletId);
$balRes->execute();
$brows = $balRes->get_result()->fetch_all(MYSQLI_ASSOC);
$balRes->close();

echo "Balances:\n";
foreach ($brows as $b) {
    printf(" - %s: %s (reserved %s)\n", $b['asset'], $b['balance'], $b['reserved']);
}

// Place a tiny market buy for XAUUSDT
$pair = 'XAUUSDT';
$side = 'BUY';
$type = 'market';
$qty = 0.01;
$price = 0.0;

$engine = new MatchingEngine($conn);
$res = $engine->placeOrder($userId, $pair, $side, $type, $price, $qty);

echo "Place order result:\n";
print_r($res);

exit(0);

?>
