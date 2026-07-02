<?php
header('Content-Type: application/json');
require_once '../config.php';
require_once 'api_helper.php';

$coin = strtoupper(trim($_GET['coin'] ?? ''));
// Log incoming request for debugging
error_log("get_market_price request coin=" . ($coin === '' ? '<empty>' : $coin));
$stmt = $conn->prepare("SELECT COUNT(*) FROM trading_pairs WHERE base_asset = ? AND active = 1");
$stmt->bind_param("s", $coin);
$stmt->execute();
$count = 0;
$stmt->bind_result($count);
$stmt->fetch();
$stmt->close();

error_log("get_market_price lookup count=" . intval($count));

if ($coin !== 'ETH' && $count === 0) {
    http_response_code(400);
    $resp = ['error' => 'invalid coin', 'received' => $coin];
    echo json_encode($resp);
    exit;
}
$symbol = $coin . 'USDT';
$price = fetchBinancePrice($symbol);
if ($price === null) {
    http_response_code(502);
    echo json_encode(['error' => 'unable to fetch price']);
    exit;
}

echo json_encode(['price' => $price]);
