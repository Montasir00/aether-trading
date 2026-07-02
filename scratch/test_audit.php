<?php
require_once 'config.php';
require_once 'api_helper.php';

$symbolMap = [
    'XAUUSDT'    => 'PAXGUSDT',
    'XAGUSDT'    => 'LTCUSDT',
    'OILUSDT'    => 'ETHUSDT',
    'GASUSDT'    => 'BNBUSDT',
    'CORNUSDT'   => 'SOLUSDT',
    'WHEATUSDT'  => 'XRPUSDT',
    'COFFEEUSDT' => 'ADAUSDT',
    'SUGARUSDT'  => 'DOGEUSDT',
    'COPPERUSDT' => 'AVAXUSDT',
    'PLATUSDT'   => 'DOTUSDT'
];

function get_interval_milliseconds(string $interval): int {
    switch ($interval) {
        case '1m':  return 60000;
        case '5m':  return 300000;
        case '15m': return 900000;
        case '1h':  return 3600000;
        case '4h':  return 14400000;
        case '1d':  return 86400000;
        default:    return 3600000;
    }
}

// Select unresolved forecasts
$stmt = $conn->query("SELECT * FROM ai_forecasts WHERE realized = 0 ORDER BY created_at ASC");
if (!$stmt) {
    die("Query error: " . $conn->error);
}

echo "Found " . $stmt->num_rows . " pending forecasts.\n";
$now = time();

while ($row = $stmt->fetch_assoc()) {
    $createdTime = strtotime($row['created_at']);
    $horizon = (int)$row['forecast_horizon'];
    $interval = $row['interval_val'];
    $intervalSec = get_interval_milliseconds($interval) / 1000;
    
    $realizationTime = $createdTime + ($horizon * $intervalSec);
    echo "ID #{$row['id']} ({$row['symbol']}, interval={$interval}): created_at={$row['created_at']}, realizationTime=" . date('Y-m-d H:i:s', $realizationTime) . "\n";
    
    if ($now < $realizationTime) {
        echo "  Skipping: not realized yet (requires " . ($realizationTime - $now) . " more seconds)\n";
        continue;
    }

    $binanceSymbol = $symbolMap[$row['symbol']] ?? 'PAXGUSDT';
    $startTimeMs = $createdTime * 1000;
    $url = "https://api.binance.com/api/v3/klines?symbol={$binanceSymbol}&interval={$interval}&startTime={$startTimeMs}&limit={$horizon}";
    echo "  Fetching klines from Binance: $url\n";
    
    $klinesJson = fetchWithRetry($url, 3, 3);
    if ($klinesJson === false) {
        echo "  Error: fetchWithRetry returned false.\n";
        continue;
    }
    
    $klines = json_decode($klinesJson, true);
    if (!is_array($klines)) {
        echo "  Error: klines is not an array.\n";
        continue;
    }
    
    echo "  Fetched " . count($klines) . " klines.\n";
    if (empty($klines)) {
        echo "  Error: klines array is empty.\n";
        continue;
    }
    
    foreach ($klines as $idx => $k) {
        echo "    Kline $idx: Open time=" . date('Y-m-d H:i:s', $k[0]/1000) . ", Close price=" . $k[4] . "\n";
    }
}
