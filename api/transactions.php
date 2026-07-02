<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'not authenticated']);
    exit;
}

$user_id = (int)$_SESSION['id'];

$stmt_tx = $conn->prepare("
    SELECT type, coin, amount, price, total, created_at FROM (
        SELECT type COLLATE utf8mb4_unicode_ci AS type, coin COLLATE utf8mb4_unicode_ci AS coin, amount, price, total, created_at FROM transactions WHERE user_id = ?
        UNION ALL
        SELECT o.side COLLATE utf8mb4_unicode_ci AS type, t.base_asset COLLATE utf8mb4_unicode_ci AS coin, o.filled_qty AS amount, o.price, o.total, o.created_at FROM orders o JOIN trading_pairs t ON o.pair = t.symbol WHERE o.user_id = ? AND o.filled_qty > 0
    ) combined ORDER BY created_at DESC LIMIT 10
");

if ($stmt_tx) {
    $stmt_tx->bind_param("ii", $user_id, $user_id);
    $stmt_tx->execute();
    $rows = $stmt_tx->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_tx->close();
    echo json_encode(['success' => true, 'transactions' => $rows]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database query failed']);
}
?>
