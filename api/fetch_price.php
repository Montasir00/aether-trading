<?php
require_once '../config.php';
require_once 'api_helper.php';

// Record prices for all active trading pairs
$res = $conn->query("SELECT base_asset, symbol FROM trading_pairs WHERE active = 1");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $asset = $row['base_asset'];
        $symbol = $row['symbol'];
        $price = fetchBinancePrice($symbol);
        if ($price !== null) {
            $stmt = $conn->prepare("INSERT INTO price_history (asset, price, recorded_at) VALUES (?, ?, NOW())");
            $stmt->bind_param("sd", $asset, $price);
            $stmt->execute();
            $stmt->close();
        }
    }
}
?>
