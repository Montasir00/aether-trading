<?php
// Mocking the session for User ID 2 to run the simulation logic in a script
session_start();
$_SESSION['id'] = 2;
$_SESSION['strategy_enabled'] = true;

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../api_helper.php';
require_once __DIR__ . '/../alerts_core.php';
require_once __DIR__ . '/../StrategyEngine.php';
require_once __DIR__ . '/../TradeExecutor.php';

echo "Running Simulation Step manually for User ID 2...\n";

// 1. Fetch current price (should be the mock price 2500.00)
$xauPrice = fetchBinancePrice('XAUUSDT');
echo "Current XAU Price: $xauPrice\n";

// 2. Insert into price history
if ($xauPrice !== null && $xauPrice > 0) {
    $stmt = $conn->prepare("INSERT INTO price_history (asset, price, recorded_at) VALUES ('XAU', ?, NOW())");
    $stmt->bind_param("d", $xauPrice);
    $stmt->execute();
    $stmt->close();
}

// 3. Generate signal
$strategy = new StrategyEngine($conn);
$result = $strategy->generateSignal();

echo "Signal: {$result['signal']}\n";
echo "SMA 50: {$result['sma50']}\n";
echo "SMA 200: {$result['sma200']}\n";

if ($result['signal'] !== 'HOLD' && $xauPrice !== null) {
    $executor = new TradeExecutor($_SESSION['id']);
    $execution = $executor->execute(
        $result['signal'],
        $_SESSION['id'],
        $xauPrice
    );
    echo "Executed: $execution\n";
} else {
    echo "No order executed (HOLD or invalid price).\n";
}
?>
