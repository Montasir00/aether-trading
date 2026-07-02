<?php
session_start();

if (!isset($_SESSION['id'])) {
    // Support both JSON responses (for AJAX calls) and page redirects
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || (($_SERVER['HTTP_ACCEPT'] ?? '') && str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'json'))) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Login required']);
        exit;
    }
    $_SESSION['error'] = "Login required";
    header("Location: ../index.php");
    exit;
}

require '../config.php';
require_once '../trading_engine/RiskManager.php';
require_once '../api/api_helper.php';
require_once '../matching_engine/matching_engine.php';

$user_id    = $_SESSION['id'];
$type       = $_POST['type'] ?? '';
$coin       = $_POST['coin'] ?? '';
$amount     = floatval($_POST['amount'] ?? 0);
$price      = floatval($_POST['price'] ?? 0);
$order_type = $_POST['order_type'] ?? 'market';
// MetaMask wallet address (supplied by JS for on-chain limit orders)
$eth_address = trim($_POST['eth_address'] ?? '');

// NORMALIZATION
$type       = strtoupper(trim($type));
$order_type = strtolower(trim($order_type));
$coin       = strtoupper(trim($coin));

// Detect if this request expects JSON (called from AJAX)
$wantsJson = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
    || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/x-www-form-urlencoded')
       && ($order_type === 'limit');  // Limit orders are always placed via AJAX fetch()

// BASIC VALIDATION
if (!$type || !$coin || $amount <= 0) {
    if ($wantsJson) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid input']);
        exit;
    }
    $_SESSION['error'] = "Invalid input";
    header("Location: buy_sell_form.php");
    exit;
}

// Validate if the coin is an active base asset
$stmtCheck = $conn->prepare("SELECT 1 FROM trading_pairs WHERE base_asset = ? AND active = 1 LIMIT 1");
$stmtCheck->bind_param("s", $coin);
$stmtCheck->execute();
$isValidCoin = $stmtCheck->get_result()->fetch_row();
$stmtCheck->close();

if (!$isValidCoin) {
    if ($wantsJson) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unsupported asset selected']);
        exit;
    }
    $_SESSION['error'] = "Unsupported asset selected.";
    header("Location: buy_sell_form.php");
    exit;
}


// MARKET PRICE FETCH
if ($order_type === 'market') {
    $symbol = $coin . 'USDT'; // e.g. XAUUSDT or XAGUSDT
    $fetchedPrice = fetchBinancePrice($symbol);
    if ($fetchedPrice === null) {
        if ($wantsJson) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => "Failed to fetch market price for $coin"]);
            exit;
        }
        $_SESSION['error'] = "Failed to fetch market price for $coin after retries. Please try again.";
        header("Location: buy_sell_form.php");
        exit;
    }
    $price = $fetchedPrice;
}

// LIMIT ORDER: require explicit price
if ($order_type === 'limit' && $price <= 0) {
    if ($wantsJson) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Limit orders require a valid price']);
        exit;
    }
    $_SESSION['error'] = "Limit orders require a valid price.";
    header("Location: buy_sell_form.php");
    exit;
}

$total = $amount * $price;

// RISK MANAGEMENT VALIDATION
$riskManager = new RiskManager($conn, $user_id);
$riskCheck   = $riskManager->validateTrade($type, $coin, $amount, $price);

if (!$riskCheck['allowed']) {
    $errMsg = "Risk check failed: " . implode(" | ", $riskCheck['errors']);
    if ($wantsJson) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $errMsg]);
        exit;
    }
    $_SESSION['error'] = $errMsg;
    header("Location: buy_sell_form.php");
    exit;
}

// Use matching engine
$engine = new MatchingEngine($conn);
$pairSymbol = strtoupper($coin) . 'USDT';
$result = $engine->placeOrder($user_id, $pairSymbol, $type, $order_type, $price, $amount);

if ($result['success'] && isset($result['order_id'])) {
    $orderId = $result['order_id'];

    // Store the MetaMask wallet address on the order row for on-chain limit orders
    if ($order_type === 'limit' && $eth_address) {
        $stmtEth = $conn->prepare("UPDATE orders SET eth_wallet_address = ? WHERE id = ?");
        $stmtEth->bind_param("si", $eth_address, $orderId);
        $stmtEth->execute();
        $stmtEth->close();
    }

    // ── ON-CHAIN LIMIT ORDERS: return JSON immediately so the JS can proceed
    //    to EIP-712 signing + escrow step. Do NOT enqueue to Redis yet —
    //    the order will be enqueued by save_limit_order_onchain.php after
    //    the escrow tx is confirmed.
    if ($order_type === 'limit' && $wantsJson) {
        header('Content-Type: application/json');
        echo json_encode([
            'success'  => true,
            'order_id' => $orderId,
            'message'  => 'Limit order created. Proceed with on-chain signing.'
        ]);
        exit;
    }

    // Enqueue order to Redis matching queue asynchronously (market orders)
    try {
        require_once __DIR__ . '/../vendor/autoload.php';
        $redisHost = getenv('REDIS_HOST') ?: '127.0.0.1';
        $redisPort = getenv('REDIS_PORT') ?: 6379;
        $redis = new Predis\Client([
            'scheme' => 'tcp',
            'host'   => $redisHost,
            'port'   => $redisPort
        ]);
        $redis->lpush('order_ingest_queue', $orderId);
        $_SESSION['flash'] = $result['message'] . " (Queued for execution)";
    } catch (Exception $e) {
        // Fallback: If Redis is offline, execute matching synchronously
        $conn->begin_transaction();
        if ($order_type === 'market') {
            $matchResult = $engine->executeMarketOrderAsynchronously($orderId);
        } else {
            $matchResult = $engine->matchLimitOrderAsynchronously($orderId);
        }

        if ($matchResult['success']) {
            $conn->commit();
            $_SESSION['flash'] = $result['message'] . " (Execution fallback: completed)";
        } else {
            $conn->rollback();
            $_SESSION['error'] = "Failed to process order: " . $matchResult['message'];
        }
    }
} else {
    $errMsg = 'Error: ' . $result['message'];
    if ($wantsJson) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $errMsg]);
        exit;
    }
    $_SESSION['error'] = $errMsg;
}
header("Location: ../buy_sell_form.php");
exit;