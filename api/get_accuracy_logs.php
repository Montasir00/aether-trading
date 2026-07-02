<?php
/**
 * Aether AI Accuracy Logs API
 * Queries the historical `ai_forecasts` database ledger and returns realized metrics
 * for retrospective performance tracking (Phase 15).
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

require_once '../config.php';

$symbol = strtoupper($_GET['symbol'] ?? '');

$queryStr = "SELECT id, symbol, interval_val, start_price, predicted_change, confidence_level, direction, error_mae, direction_correct, realized, created_at FROM ai_forecasts";
$params = [];
$types = "";

if (!empty($symbol)) {
    $queryStr .= " WHERE symbol = ?";
    $params[] = $symbol;
    $types .= "s";
}

$queryStr .= " ORDER BY created_at DESC LIMIT 15";

$stmt = $conn->prepare($queryStr);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$logs = [];
while ($row = $result->fetch_assoc()) {
    $logs[] = [
        'id' => (int)$row['id'],
        'symbol' => $row['symbol'],
        'interval' => $row['interval_val'],
        'start_price' => (float)$row['start_price'],
        'predicted_change' => (float)$row['predicted_change'],
        'confidence' => $row['confidence_level'],
        'direction' => $row['direction'],
        'mae' => $row['error_mae'] !== null ? (float)$row['error_mae'] : null,
        'direction_correct' => $row['direction_correct'] !== null ? (int)$row['direction_correct'] : null,
        'realized' => (int)$row['realized'],
        'created_at' => $row['created_at']
    ];
}
$stmt->close();

echo json_encode(['status' => 'OK', 'logs' => $logs]);
?>
