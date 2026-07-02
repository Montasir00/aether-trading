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

function audit_past_forecasts_standalone($conn, $symbolMap) {
    $stmt = $conn->query("SELECT * FROM ai_forecasts WHERE realized = 0 ORDER BY created_at ASC LIMIT 5");
    if (!$stmt || $stmt->num_rows === 0) {
        echo "No pending forecasts found.\n";
        return;
    }

    $now = time();

    while ($row = $stmt->fetch_assoc()) {
        $createdTime = strtotime($row['created_at']);
        $horizon = (int)$row['forecast_horizon'];
        $interval = $row['interval_val'];
        $intervalSec = get_interval_milliseconds($interval) / 1000;
        
        $realizationTime = $createdTime + ($horizon * $intervalSec);

        // Fetch authority klines
        $binanceSymbol = $symbolMap[$row['symbol']] ?? 'PAXGUSDT';
        $startTimeMs = $createdTime * 1000;
        $url = "https://api.binance.com/api/v3/klines?symbol={$binanceSymbol}&interval={$interval}&startTime={$startTimeMs}&limit={$horizon}";
        $klines = fetchJsonWithRetry($url, 3, 3);

        if ($klines === null || !is_array($klines) || empty($klines)) {
            echo "Failed to fetch klines for ID #{$row['id']}.\n";
            continue;
        }

        $actualCloses = [];
        foreach ($klines as $k) {
            $actualCloses[] = (float)$k[4];
        }

        while (count($actualCloses) < $horizon) {
            $actualCloses[] = count($actualCloses) > 0 ? end($actualCloses) : (float)$row['start_price'];
        }

        $predictedCloses = json_decode($row['predicted_values'], true);
        if (!is_array($predictedCloses)) {
            continue;
        }

        $absoluteErrors = [];
        for ($i = 0; $i < $horizon; $i++) {
            $absoluteErrors[] = abs($actualCloses[$i] - $predictedCloses[$i]);
        }
        $mae = array_sum($absoluteErrors) / $horizon;

        $startPrice = (float)$row['start_price'];
        $actualFinal = end($actualCloses);
        $predictedFinal = end($predictedCloses);

        $actualMove = $actualFinal - $startPrice;
        $predictedMove = $predictedFinal - $startPrice;

        $directionCorrect = 0;
        if (($actualMove > 0 && $predictedMove > 0) || ($actualMove < 0 && $predictedMove < 0) || (abs($actualMove) < 0.0001 && abs($predictedMove) < 0.0001)) {
            $directionCorrect = 1;
        }

        $actualClosesJson = json_encode($actualCloses);
        $update = $conn->prepare("UPDATE ai_forecasts SET realized = 1, realized_values = ?, error_mae = ?, direction_correct = ? WHERE id = ?");
        $update->bind_param('sddi', $actualClosesJson, $mae, $directionCorrect, $row['id']);
        $update->execute();
        $update->close();
        echo "Successfully audited and realized forecast ID #{$row['id']}.\n";
    }
}

echo "Running retrospective accuracy validator...\n";
audit_past_forecasts_standalone($conn, $symbolMap);
echo "Validator complete.\n";

$res = $conn->query("SELECT id, realized, error_mae, direction_correct FROM ai_forecasts");
while ($row = $res->fetch_assoc()) {
    echo "ID #{$row['id']}: realized={$row['realized']}, error_mae={$row['error_mae']}, direction_correct={$row['direction_correct']}\n";
}
?>
