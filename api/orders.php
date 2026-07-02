<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../trading_engine/RiskManager.php';
require_once __DIR__ . '/../matching_engine/matching_engine.php';

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'not authenticated']);
    exit;
}

$user_id = $_SESSION['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST ?: json_decode(file_get_contents('php://input'), true);
    $pair = strtoupper(trim($input['pair'] ?? ''));
    $side = strtoupper(trim($input['side'] ?? ''));
    $type = strtolower(trim($input['type'] ?? 'market'));
    $qty  = (float)($input['qty'] ?? 0);
    $price = isset($input['price']) ? (float)$input['price'] : 0.0;

    // Strict per-field whitelist validation
    if (!$pair) {
        http_response_code(400);
        echo json_encode(['error' => 'pair is required']);
        exit;
    }
    if (!in_array($side, ['BUY', 'SELL'], true)) {
        http_response_code(400);
        echo json_encode(['error' => 'side must be BUY or SELL']);
        exit;
    }
    if (!in_array($type, ['market', 'limit'], true)) {
        http_response_code(400);
        echo json_encode(['error' => 'type must be market or limit']);
        exit;
    }
    if ($qty <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'qty must be greater than 0']);
        exit;
    }

    // Dynamically resolve base asset from trading_pairs database table
    $base = 'XAU';
    $stmtPair = $conn->prepare("SELECT base_asset FROM trading_pairs WHERE symbol = ? LIMIT 1");
    if ($stmtPair) {
        $stmtPair->bind_param("s", $pair);
        $stmtPair->execute();
        $pairRow = $stmtPair->get_result()->fetch_assoc();
        $stmtPair->close();
        if ($pairRow) {
            $base = $pairRow['base_asset'];
        } else {
            if (str_ends_with($pair, 'USDT')) {
                $base = substr($pair, 0, -4);
            } else {
                $base = substr($pair, 0, 3);
            }
        }
    } else {
        if (str_ends_with($pair, 'USDT')) {
            $base = substr($pair, 0, -4);
        } else {
            $base = substr($pair, 0, 3);
        }
    }

    $risk = new RiskManager($conn, $user_id);
    $check = $risk->validateTrade($side, $base, $qty, $price);
    if (!$check['allowed']) {
        http_response_code(403);
        echo json_encode(['error' => 'risk_failed', 'details' => $check['errors']]);
        exit;
    }

    $engine = new MatchingEngine($conn);
    $res = $engine->placeOrder($user_id, $pair, $side, $type, $price, $qty);
    if ($res['success'] && isset($res['order_id'])) {
        $orderId = $res['order_id'];
        $executionStatus = "queued";
        
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
            $executionStatus = "queued";
        } catch (Exception $e) {
            // Fallback: If Redis is offline, execute matching synchronously
            $conn->begin_transaction();
            if ($type === 'market') {
                $matchResult = $engine->executeMarketOrderAsynchronously($orderId);
            } else {
                $matchResult = $engine->matchLimitOrderAsynchronously($orderId);
            }
            
            if ($matchResult['success']) {
                $conn->commit();
                $executionStatus = "completed";
            } else {
                $conn->rollback();
                $executionStatus = "failed";
            }
        }
        
        echo json_encode([
            'ok' => true, 
            'order_id' => $orderId, 
            'message' => $res['message'],
            'execution' => $executionStatus
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $res['message']]);
    }
    exit;
}

// GET: list user's recent orders
$stmt = $conn->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 100');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(['orders' => $rows]);

?>
