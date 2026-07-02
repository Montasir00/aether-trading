<?php
session_start();
$_SESSION['id'] = 2;
$_SESSION['strategy_enabled'] = true;

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../api_helper.php';
require_once __DIR__ . '/../alerts_core.php';
require_once __DIR__ . '/../StrategyEngine.php';
require_once __DIR__ . '/../TradeExecutor.php';

echo "Simulating multiple ticks at 2500.00 to find when crossover triggers...\n";

$xauPrice = 2500.00;

for ($tick = 1; $tick <= 30; $tick++) {
    // Insert into price history
    $stmt = $conn->prepare("INSERT INTO price_history (asset, price, recorded_at) VALUES ('XAU', ?, NOW())");
    $stmt->bind_param("d", $xauPrice);
    $stmt->execute();
    $stmt->close();

    // Check signal
    $strategy = new StrategyEngine($conn);
    $result = $strategy->generateSignal();

    echo "Tick #$tick | Price: $xauPrice | SMA 50: {$result['sma50']} | SMA 200: {$result['sma200']} | Signal: {$result['signal']}\n";

    if ($result['signal'] === 'BUY') {
        $executor = new TradeExecutor($_SESSION['id']);
        $execution = $executor->execute(
            $result['signal'],
            $_SESSION['id'],
            $xauPrice
        );
        echo "--> SUCCESS! Executed: $execution\n";
        break;
    }
}
?>
