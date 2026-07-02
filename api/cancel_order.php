<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'not authenticated']);
    exit;
}

$user_id = $_SESSION['id'];
$orderId = intval($_POST['order_id'] ?? $_GET['order_id'] ?? 0);
if ($orderId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid order id']);
    exit;
}

// Wrap everything in a transaction: balance release + status update must be atomic.
// Without this, a crash between the two statements leaves reserved funds permanently
// locked (balance leak) or frees them without cancelling the order.
$conn->begin_transaction();
try {

$stmt = $conn->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ? FOR UPDATE');
$stmt->bind_param('ii', $orderId, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    $conn->rollback();
    http_response_code(404);
    echo json_encode(['error' => 'order not found']);
    exit;
}

if (!in_array($order['status'], ['open','partially_filled'])) {
    $conn->rollback();
    echo json_encode(['ok' => false, 'message' => 'order not cancellable']);
    exit;
}

// release reserved amounts
$walletStmt = $conn->prepare('SELECT id FROM wallets WHERE user_id = ? LIMIT 1');
$walletStmt->bind_param('i', $user_id);
$walletStmt->execute();
$wid = $walletStmt->get_result()->fetch_assoc();
$walletStmt->close();
$walletId = $wid['id'] ?? null;

$base = 'XAU';
$quote = 'USDT';
$stmtPair = $conn->prepare("SELECT base_asset, quote_asset FROM trading_pairs WHERE symbol = ? LIMIT 1");
if ($stmtPair) {
    $stmtPair->bind_param("s", $order['pair']);
    $stmtPair->execute();
    $pairRow = $stmtPair->get_result()->fetch_assoc();
    $stmtPair->close();
    if ($pairRow) {
        $base  = $pairRow['base_asset'];
        $quote = $pairRow['quote_asset'];
    } else {
        // Fallback: strip USDT suffix first; use remaining as base.
        // This correctly handles stock tickers (AAPL, TSLA) unlike a dumb 3-char substr.
        if (str_ends_with($order['pair'], 'USDT')) {
            $base  = substr($order['pair'], 0, -4);
            $quote = 'USDT';
        } else {
            $base  = substr($order['pair'], 0, 3);
            $quote = substr($order['pair'], 3);
        }
    }
} else {
    if (str_ends_with($order['pair'], 'USDT')) {
        $base  = substr($order['pair'], 0, -4);
        $quote = 'USDT';
    } else {
        $base  = substr($order['pair'], 0, 3);
        $quote = substr($order['pair'], 3);
    }
}
$remaining = (float)$order['qty'] - (float)$order['filled_qty'];

if ($order['side'] === 'BUY') {
    $refund = $remaining * (float)$order['price'];
    $stmt = $conn->prepare('UPDATE balances SET reserved = reserved - ? WHERE wallet_id = ? AND asset = ?');
    $stmt->bind_param('dis', $refund, $walletId, $quote);
    $stmt->execute();
    $stmt->close();
} else {
    $stmt = $conn->prepare('UPDATE balances SET reserved = reserved - ? WHERE wallet_id = ? AND asset = ?');
    $stmt->bind_param('dis', $remaining, $walletId, $base);
    $stmt->execute();
    $stmt->close();
}

$conn->query("UPDATE orders SET status = 'canceled', updated_at = NOW() WHERE id = " . intval($orderId));

$conn->commit();

} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['error' => 'cancel failed: ' . $e->getMessage()]);
    exit;
}

echo json_encode(['ok' => true, 'message' => 'order canceled']);

?>
