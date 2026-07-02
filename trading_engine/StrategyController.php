<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(['status' => 'STRATEGY_DISABLED']);
    exit;
}

require_once '../config.php'; // provides $conn

$user_id = (int)$_SESSION['id'];
$stmt = $conn->prepare("SELECT bot_enabled FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (empty($row['bot_enabled'])) {
    echo json_encode(['status' => 'STRATEGY_DISABLED']);
    exit;
}

// Unlock session early since we only read from it
session_write_close();

require_once 'StrategyEngine.php';
require_once 'TradeExecutor.php';
require_once '../api/api_helper.php';

// Use Gold (XAU) as the primary strategy asset
$price = fetchBinancePrice('XAUUSDT');

if (!$price || $price <= 0) {
    echo json_encode(['status' => 'INVALID_PRICE']);
    exit;
}

// Strategy reads price history from DB — no addPrice() call needed
$strategy = new StrategyEngine($conn);
$result   = $strategy->generateSignal();

$execution = 'NO_ACTION';

// Only execute trades when explicitly requested via POST.
// Dashboard polling uses GET and only reads the computed signal.
$shouldExecute = $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['execute']);
if ($shouldExecute) {
    $executor = new TradeExecutor($_SESSION['id']);
    $execution = $executor->execute(
        $result['signal'],
        $_SESSION['id'],
        $price
    );
}

echo json_encode([
    'status'    => 'OK',
    'price'     => $price,
    'signal'    => $result['signal'],
    'sma50'     => $result['sma50'],
    'sma200'    => $result['sma200'],
    'execution' => is_string($execution) ? $execution : 'NO_ACTION'
]);
