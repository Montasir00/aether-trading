<?php
require_once '../config.php';
require_once 'api_helper.php';

// Record top assets: XAU (Gold) and XAG (Silver)
$assets = [
    'XAU' => 'XAUUSDT',
    'XAG' => 'XAGUSDT',
];

$stmt = $conn->prepare("INSERT INTO price_history (asset, price) VALUES (?, ?)");

foreach ($assets as $asset => $symbol) {
    $price = fetchBinancePrice($symbol);
    if ($price === null || $price <= 0) {
        error_log("[record_price] Failed to fetch $asset price after retries.");
        continue;
    }
    $stmt->bind_param("sd", $asset, $price);
    $stmt->execute();
}

$stmt->close();
?>
