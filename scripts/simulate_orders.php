<?php
/**
 * Simulate orders across wallets using the MatchingEngine.
 * Usage: php scripts/simulate_orders.php [count] [--seed]
 */
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../matching_engine/matching_engine.php";

$argc = $_SERVER['argc'];
$argv = $_SERVER['argv'];
$count = 20;
$seedBalances = false;
if ($argc >= 2) $count = intval($argv[1]) ?: 20;
if (in_array('--seed', $argv)) $seedBalances = true;

echo "Simulate $count orders" . ($seedBalances ? " (with seeding)" : "") . "\n";

$pairs = [];
$res = $conn->query("SELECT symbol, base_asset FROM trading_pairs WHERE active = 1");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $pairs[] = $r;
    }
}
if (empty($pairs)) {
    echo "No trading pairs available.\n";
    exit(1);
}

$wallets = [];
$wr = $conn->query("SELECT id, user_id FROM wallets LIMIT 20");
if ($wr) {
    while ($w = $wr->fetch_assoc()) $wallets[] = $w;
}
if (empty($wallets)) {
    echo "No wallets found. Run scripts/migrate_to_spot.php first.\n";
    exit(1);
}

if ($seedBalances) {
    echo "Seeding balances for test wallets...\n";
    foreach ($wallets as $w) {
        $wid = (int)$w['id'];
        // grant USDT, small XAU/XAG balances
        $stmt = $conn->prepare("INSERT INTO balances (wallet_id, asset, balance, reserved) VALUES (?, ?, ?, 0) ON DUPLICATE KEY UPDATE balance = VALUES(balance)");
        $usdt = 50000.0; $xau = 10.0; $xag = 200.0;
        $asset = 'USDT'; $stmt->bind_param('isd', $wid, $asset, $usdt); $stmt->execute();
        $asset = 'XAU'; $stmt->bind_param('isd', $wid, $asset, $xau); $stmt->execute();
        $asset = 'XAG'; $stmt->bind_param('isd', $wid, $asset, $xag); $stmt->execute();
        $stmt->close();
    }
}

$engine = new MatchingEngine($conn);

$summary = ['success' => 0, 'fail' => 0, 'messages' => []];

for ($i = 0; $i < $count; $i++) {
    $w = $wallets[array_rand($wallets)];
    $walletId = (int)$w['id'];
    $userId = (int)$w['user_id'];
    $pairRow = $pairs[array_rand($pairs)];
    $pair = $pairRow['symbol'];
    $base = $pairRow['base_asset'];

    $side = (rand(0,1) === 0) ? 'BUY' : 'SELL';
    $type = (rand(1,100) <= 60) ? 'market' : 'limit';

    // determine qty heuristically by base asset
    if ($base === 'XAU') {
        $qty = round(mt_rand(1, 1000) / 1000, 4); // 0.001 - 1.000
    } else {
        $qty = round(mt_rand(1, 5000) / 100, 4); // 0.01 - 50.00
    }

    $price = 0.0;
    if ($type === 'limit') {
        // pick price around market
        $mp = null;
        $mpRaw = @file_get_contents(__DIR__ . '/../tmp/market_prices.json');
        $json = $mpRaw ? json_decode($mpRaw, true) : null;
        if ($json && isset($json['prices'][$pair]['price'])) $mp = (float)$json['prices'][$pair]['price'];
        if (!$mp) $mp = 100.0; // fallback
        $delta = ($base === 'XAU') ? ($mp * 0.01) : ($mp * 0.02);
        $price = round($mp + (mt_rand(-100,100)/100.0) * $delta, 4);
    }

    $res = $engine->placeOrder($userId, $pair, $side, $type, $price, $qty);
    if ($res['success']) {
        $summary['success']++;
        echo "[OK] {$type} {$side} {$qty} {$pair} -> {$res['message']} (order {$res['order_id']})\n";
    } else {
        $summary['fail']++;
        echo "[ERR] {$type} {$side} {$qty} {$pair} -> {$res['message']}\n";
        $summary['messages'][] = $res['message'];
    }
    // small sleep to avoid hammering
    usleep(100000);
}

echo "Simulation complete: Success={$summary['success']}, Fail={$summary['fail']}\n";
if (!empty($summary['messages'])) {
    echo "Errors sample: " . implode(' | ', array_slice($summary['messages'], 0, 5)) . "\n";
}

return 0;

?>
