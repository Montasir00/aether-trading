<?php
/**
 * Public endpoint to return cached market prices for trading pairs.
 * Optional query param `pair` returns a single pair.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

$cacheDir = __DIR__ . '/tmp';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
$cacheFile = $cacheDir . '/market_prices.json';
$cacheTtl = (int) (getenv('MARKET_CACHE_TTL') ?: 15); // seconds

$data = null;
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTtl)) {
    $raw = file_get_contents($cacheFile);
    $data = $raw ? json_decode($raw, true) : null;
}

// If no fresh cache, attempt to compute on-demand (best-effort)
if (!$data) {
    // Try to run the scripts/fetch_market_prices.php logic inline
    if (file_exists(__DIR__ . '/scripts/fetch_market_prices.php')) {
        // include will run the script which writes the cache file
        include __DIR__ . '/scripts/fetch_market_prices.php';
        if (file_exists($cacheFile)) {
            $raw = file_get_contents($cacheFile);
            $data = $raw ? json_decode($raw, true) : null;
        }
    }
}

if (!$data) {
    http_response_code(503);
    echo json_encode(['error' => 'market data unavailable']);
    exit;
}

$pair = strtoupper(trim($_GET['pair'] ?? ''));
if ($pair) {
    $val = $data['prices'][$pair] ?? null;
    if ($val === null) {
        http_response_code(404);
        echo json_encode(['error' => 'pair not found']);
        exit;
    }
    echo json_encode(['pair' => $pair, 'price' => $val['price'], 'fetched_at' => $val['fetched_at']]);
    exit;
}

echo json_encode($data);

?>
