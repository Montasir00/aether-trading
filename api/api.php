<?php
require_once '../config.php';
require_once 'api_helper.php';

// Check for mock price overrides in database
$mock_xau = null;
$mock_xag = null;
try {
    $res = $conn->query("SELECT mock_xau_price, mock_xag_price FROM bot_control WHERE id = 1");
    if ($res && $row = $res->fetch_assoc()) {
        $mock_xau = $row['mock_xau_price'] !== null ? (float)$row['mock_xau_price'] : null;
        $mock_xag = $row['mock_xag_price'] !== null ? (float)$row['mock_xag_price'] : null;
    }
} catch (Throwable $e) {
    // ignore
}

$cacheFile = __DIR__ . '/api_cache.json';
$cacheTime = 5; // Cache for 5 seconds

// Serve from cache if it exists, is fresh, and no mock overrides are active
if ($mock_xau === null && $mock_xag === null && file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
    header('Content-Type: application/json');
    echo file_get_contents($cacheFile);
    exit;
}

// Binance doesn't support traditional commodities. We map them to crypto proxies.
$symbolMap = [
    'XAUUSDT'    => 'PAXGUSDT', // Pax Gold
    'XAGUSDT'    => 'LTCUSDT',  // Digital Silver
    'OILUSDT'    => 'ETHUSDT',
    'GASUSDT'    => 'BNBUSDT',
    'CORNUSDT'   => 'SOLUSDT',
    'WHEATUSDT'  => 'XRPUSDT',
    'COFFEEUSDT' => 'ADAUSDT',
    'SUGARUSDT'  => 'DOGEUSDT',
    'COPPERUSDT' => 'AVAXUSDT',
    'PLATUSDT'   => 'DOTUSDT',
    'ZINCUSDT'   => 'LINKUSDT',
    'IRONUSDT'   => 'TRXUSDT',
    'COALUSDT'   => 'MATICUSDT',
    'LUMBERUSDT' => 'SHIBUSDT',
    'COCOAUSDT'  => 'UNIUSDT',
    'COTTONUSDT' => 'ICPUSDT',
    'STEELUSDT'  => 'BCHUSDT',
    'LEADUSDT'   => 'ATOMUSDT',
    'NICKELUSDT' => 'XLMUSDT',
    'TINUSDT'    => 'ALGOUSDT',
    'AAPLUSDT'   => 'OPUSDT',
    'TSLAUSDT'   => 'ARBUSDT',
    'MSFTUSDT'   => 'APTUSDT',
    'AMZNUSDT'   => 'SUIUSDT',
    'GOOGUSDT'   => 'NEARUSDT',
    'NVDAUSDT'   => 'FTMUSDT',
    'METAUSDT'   => 'GRTUSDT',
    'NFLXUSDT'   => 'LDOUSDT',
    'AMDUSDT'    => 'FILUSDT',
    'BABAUSDT'   => 'INJUSDT'
];

$proxySymbols = array_values($symbolMap);
$symbolsParam = urlencode(json_encode($proxySymbols));
$api_url = "https://api.binance.com/api/v3/ticker/24hr?symbols={$symbolsParam}";
$data = [];
$isFullData = false;

// Fetch ALL tickers in 1 request to save rate limits. Use 10s timeout and 1 attempt to avoid long hangs.
$allTickers = fetchJsonWithRetry($api_url, 1, 10.0);

if ($allTickers !== null && is_array($allTickers)) {
    $isFullData = true;
    // Index by symbol for fast lookup
    $tickerDict = [];
    foreach ($allTickers as $t) {
        if (isset($t['symbol'])) {
            $tickerDict[$t['symbol']] = $t;
        }
    }

    foreach ($symbolMap as $commodity => $cryptoProxy) {
        if (isset($tickerDict[$cryptoProxy])) {
            $data[$commodity] = $tickerDict[$cryptoProxy];
            $data[$commodity]['symbol'] = $commodity; // mask the real symbol
        } else {
            $data[$commodity] = ['error' => "Failed to fetch proxy $cryptoProxy"];
        }
    }
} else {
    // Fallback if the massive request fails: fetch individual proxies for XAU and XAG
    foreach (['XAUUSDT' => 'PAXGUSDT', 'XAGUSDT' => 'LTCUSDT'] as $commodity => $cryptoProxy) {
        $ticker = fetchJsonWithRetry("https://api.binance.com/api/v3/ticker/24hr?symbol=$cryptoProxy", 2, 4.0);
        if ($ticker !== null && is_array($ticker)) {
            $data[$commodity] = $ticker;
            $data[$commodity]['symbol'] = $commodity;
        } else {
            $data[$commodity] = ['error' => "Failed to fetch proxy $cryptoProxy"];
        }
    }
}

if ($mock_xau !== null) {
    if (!isset($data['XAUUSDT']) || !is_array($data['XAUUSDT']) || isset($data['XAUUSDT']['error'])) {
        $data['XAUUSDT'] = [
            'symbol' => 'XAUUSDT',
            'lastPrice' => $mock_xau,
            'highPrice' => $mock_xau,
            'lowPrice' => $mock_xau,
            'volume' => '1523.50',
            'priceChangePercent' => '0.00'
        ];
    } else {
        $data['XAUUSDT']['lastPrice'] = $mock_xau;
        $data['XAUUSDT']['highPrice'] = max((float)$mock_xau, (float)($data['XAUUSDT']['highPrice'] ?? $mock_xau));
        $data['XAUUSDT']['lowPrice'] = min((float)$mock_xau, (float)($data['XAUUSDT']['lowPrice'] ?? $mock_xau));
        $data['XAUUSDT']['priceChangePercent'] = '0.00';
    }
}
if ($mock_xag !== null) {
    if (!isset($data['XAGUSDT']) || !is_array($data['XAGUSDT']) || isset($data['XAGUSDT']['error'])) {
        $data['XAGUSDT'] = [
            'symbol' => 'XAGUSDT',
            'lastPrice' => $mock_xag,
            'highPrice' => $mock_xag,
            'lowPrice' => $mock_xag,
            'volume' => '12402.10',
            'priceChangePercent' => '0.00'
        ];
    } else {
        $data['XAGUSDT']['lastPrice'] = $mock_xag;
        $data['XAGUSDT']['highPrice'] = max((float)$mock_xag, (float)($data['XAGUSDT']['highPrice'] ?? $mock_xag));
        $data['XAGUSDT']['lowPrice'] = min((float)$mock_xag, (float)($data['XAGUSDT']['lowPrice'] ?? $mock_xag));
        $data['XAGUSDT']['priceChangePercent'] = '0.00';
    }
}

$json_output = json_encode($data);

if ($isFullData) {
    // Save to cache atomically only if we fetched the full commodities list
    file_put_contents($cacheFile, $json_output, LOCK_EX);
}

header('Content-Type: application/json');
echo $json_output;

