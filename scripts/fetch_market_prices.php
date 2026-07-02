<?php
/**
 * Fetch current market prices for active trading pairs and cache them.
 * Intended to be run from cron or manually: php scripts/fetch_market_prices.php
 */
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../api/api_helper.php";

$cacheDir = __DIR__ . "/../tmp";
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
$cacheFile = $cacheDir . "/market_prices.json";

$pairs = [];
$res = $conn->query("SELECT symbol FROM trading_pairs WHERE active = 1");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $pairs[] = $r['symbol'];
    }
}

if (empty($pairs)) {
    // default fallback pairs
    $pairs = ['XAUUSDT','XAGUSDT'];
}

$existing = [];
if (file_exists($cacheFile)) {
    $raw = @file_get_contents($cacheFile);
    $existing = $raw ? json_decode($raw, true) : [];
    if (!is_array($existing)) $existing = [];
}

$mockPrices = [];
try {
    $res = $conn->query("SELECT mock_xau_price, mock_xag_price FROM bot_control WHERE id = 1");
    if ($res && ($row = $res->fetch_assoc())) {
        if ($row['mock_xau_price'] !== null) {
            $mockPrices['XAUUSDT'] = (float)$row['mock_xau_price'];
        }
        if ($row['mock_xag_price'] !== null) {
            $mockPrices['XAGUSDT'] = (float)$row['mock_xag_price'];
        }
    }
} catch (Throwable $e) {
    // Ignore mock price lookup errors and fall back to cached/default prices.
}

$defaultPrices = [
    'XAUUSDT' => 2500.00,
    'XAGUSDT' => 30.00,
];

$out = [ 'calculated_at' => date('Y-m-d H:i:s'), 'prices' => [] ];

foreach ($pairs as $pair) {
    // Prefer local mock prices, then cached values, then live fetch, then deterministic defaults.
    $price = $mockPrices[$pair] ?? null;
    if ($price === null) {
        $price = fetchBinancePrice($pair);
    }
    if ($price !== null) {
        $out['prices'][$pair] = [ 'price' => (float)$price, 'fetched_at' => date('Y-m-d H:i:s') ];
    } else {
        // keep previous cached value if available
        if (isset($existing['prices'][$pair]['price'])) {
            $out['prices'][$pair] = $existing['prices'][$pair];
        } else {
            $fallback = $defaultPrices[$pair] ?? null;
            $out['prices'][$pair] = [
                'price' => $fallback,
                'fetched_at' => $fallback !== null ? date('Y-m-d H:i:s') : null,
            ];
        }
    }
}

@file_put_contents($cacheFile, json_encode($out));
echo "Market prices cached to: $cacheFile\n";

return 0;

?>
