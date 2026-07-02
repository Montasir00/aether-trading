<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

$pair = strtoupper(trim($_GET['pair'] ?? ''));
$limit = intval($_GET['limit'] ?? 10);
if (!$pair) {
    http_response_code(400);
    echo json_encode(['error' => 'pair required']);
    exit;
}

// Aggregate top bids
$bidsSql = "SELECT price, SUM(qty-filled_qty) as qty FROM orders WHERE pair = ? AND side = 'BUY' AND status IN ('open','partially_filled') GROUP BY price ORDER BY price DESC LIMIT ?";
$asksSql = "SELECT price, SUM(qty-filled_qty) as qty FROM orders WHERE pair = ? AND side = 'SELL' AND status IN ('open','partially_filled') GROUP BY price ORDER BY price ASC LIMIT ?";

$stmt = $conn->prepare($bidsSql);
$stmt->bind_param('si', $pair, $limit);
$stmt->execute();
$bids = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare($asksSql);
$stmt->bind_param('si', $pair, $limit);
$stmt->execute();
$asks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(['pair' => $pair, 'bids' => $bids, 'asks' => $asks]);

?>
