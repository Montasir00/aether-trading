<?php
/**
 * Database Seeder: Add stock trading pairs to the trading_pairs table.
 * Run this from the root directory: php scripts/seed_stocks.php
 */
require_once __DIR__ . "/../config.php";

$stocks = [
    ['AAPLUSDT', 'AAPL', 'USDT'],
    ['TSLAUSDT', 'TSLA', 'USDT'],
    ['MSFTUSDT', 'MSFT', 'USDT'],
    ['AMZNUSDT', 'AMZN', 'USDT'],
    ['GOOGUSDT', 'GOOG', 'USDT'],
    ['NVDAUSDT', 'NVDA', 'USDT'],
    ['METAUSDT', 'META', 'USDT'],
    ['NFLXUSDT', 'NFLX', 'USDT'],
    ['AMDUSDT', 'AMD', 'USDT'],
    ['BABAUSDT', 'BABA', 'USDT']
];

$stmt = $conn->prepare("INSERT IGNORE INTO trading_pairs (symbol, base_asset, quote_asset, price_precision, qty_precision, min_qty, min_price, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

if (!$stmt) {
    die("Database prepare failed: " . $conn->error . "\n");
}

$price_precision = 2;
$qty_precision = 4;
$min_qty = 0.0001;
$min_price = 0.01;
$active = 1;

$inserted = 0;
foreach ($stocks as $s) {
    [$symbol, $base, $quote] = $s;
    $stmt->bind_param(
        'sssiiddi',
        $symbol,
        $base,
        $quote,
        $price_precision,
        $qty_precision,
        $min_qty,
        $min_price,
        $active
    );
    if ($stmt->execute()) {
        if ($conn->affected_rows > 0) {
            $inserted++;
            echo "Seeded trading pair: $symbol ($base/$quote)\n";
        }
    } else {
        echo "Failed to seed $symbol: " . $stmt->error . "\n";
    }
}
$stmt->close();

echo "Database seeding finished. Inserted $inserted new stock trading pairs.\n";
?>
