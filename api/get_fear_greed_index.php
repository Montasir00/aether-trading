<?php
/**
 * Returns a custom, cached Fear & Greed Index for the dashboard.
 * The score is recalculated only when the cached value is older than the configured TTL.
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

require_once '../config.php';
require_once 'api_helper.php';
require_once 'get_nsi.php';

function clamp_value(float $value, float $min, float $max): float
{
    return max($min, min($max, $value));
}

function get_cache_ttl_seconds(): int
{
    $ttl = (int) (getenv('FGI_CACHE_TTL') ?: 900); // default 15 minutes
    return max(300, $ttl);
}

function score_from_signed_change(float $changePercent, float $scale = 2.5): int
{
    $normalized = 50 + (($changePercent / $scale) * 50);
    return (int) round(clamp_value($normalized, 0, 100));
}

function score_from_range_position(float $price, float $low, float $high): int
{
    if ($high <= $low) {
        return 50;
    }

    $position = (($price - $low) / ($high - $low)) * 100;
    return (int) round(clamp_value($position, 0, 100));
}

function score_from_volatility(float $volatilityPercent, float $scale = 2.5): int
{
    $score = 100 - (($volatilityPercent / $scale) * 100);
    return (int) round(clamp_value($score, 0, 100));
}

function create_fear_greed_table(mysqli $conn): void
{
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS fear_greed_index_cache (
    cache_key VARCHAR(64) NOT NULL PRIMARY KEY,
    payload LONGTEXT NOT NULL,
    calculated_at DATETIME NOT NULL,
    INDEX idx_fgi_calculated_at (calculated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
    $conn->query($sql);
}

function load_price_history_stats(mysqli $conn, string $asset): array
{
    // The F&G index is a macro market indicator. Relying on local user trades (price_history) 
    // causes massive distortion if users execute trades at unrealistic limit prices (e.g., $1 or $10000).
    // We now solely rely on the authoritative Binance 24h ticker for volatility and momentum.
    return [
        'prices'            => [],
        'first_price'       => null,
        'last_price'        => null,
        'low'               => null,
        'high'              => null,
        'volatility_percent'=> null,
        'change_percent'    => null,
        'row_count'         => 0,
    ];
}

function fetch_tickers(): array
{
    $apiUrl = 'https://api.binance.com/api/v3/ticker/24hr';
    $raw = fetchJsonWithRetry($apiUrl, 3, 5);
    if ($raw === null || !is_array($raw)) {
        return [];
    }

    $tickerDict = [];
    foreach ($raw as $ticker) {
        if (isset($ticker['symbol'])) {
            $tickerDict[$ticker['symbol']] = $ticker;
        }
    }

    $symbolMap = [
        'XAUUSDT' => 'PAXGUSDT',
        'XAGUSDT' => 'LTCUSDT'
    ];

    $mapped = [];
    foreach ($symbolMap as $maskedSymbol => $proxySymbol) {
        if (isset($tickerDict[$proxySymbol])) {
            $mapped[$maskedSymbol] = $tickerDict[$proxySymbol];
            $mapped[$maskedSymbol]['symbol'] = $maskedSymbol;
        }
    }

    return $mapped;
}

/**
 * Use Binance order book depth ratio as trade flow signal.
 * Computes bid pressure vs ask pressure individually per asset to prevent denominator skew.
 */
function load_trade_flow(): array
{
    $proxies = ['PAXGUSDT' => 'XAU', 'LTCUSDT' => 'XAG'];
    $scores = [];
    $details = [];

    foreach ($proxies as $symbol => $label) {
        // Increase depth limit to 100 to get a more stable macro reading of order book pressure,
        // avoiding noise from thin top levels.
        $url  = "https://api.binance.com/api/v3/depth?symbol={$symbol}&limit=100";
        $data = fetchJsonWithRetry($url, 3, 5);
        if (!$data || !isset($data['bids'], $data['asks'])) continue;

        $bidVol = 0.0;
        $askVol = 0.0;
        foreach ($data['bids'] as $bid) $bidVol += (float) ($bid[1] ?? 0);
        foreach ($data['asks'] as $ask) $askVol += (float) ($ask[1] ?? 0);

        $total = $bidVol + $askVol;
        $ratio = $total > 0 ? ($bidVol / $total) * 100 : 50;
        
        $scores[] = $ratio;
        $details[] = sprintf('%s: %d%% bids', $label, (int) round($ratio));
    }

    $avgScore = count($scores) > 0 ? array_sum($scores) / count($scores) : 50;

    return [
        'score'  => (int) round(clamp_value($avgScore, 0, 100)),
        'detail' => implode(' | ', $details)
    ];
}

function format_currency(float $value): string
{
    return '$' . number_format($value, 2);
}

function build_component(string $label, int $value, string $detail): array
{
    return [
        'label' => $label,
        'value' => $value,
        'detail' => $detail
    ];
}

create_fear_greed_table($conn);
$cacheTtl = get_cache_ttl_seconds();

$cacheKey = 'global';
$cacheQuery = $conn->prepare('SELECT payload, calculated_at FROM fear_greed_index_cache WHERE cache_key = ? LIMIT 1');
$cacheQuery->bind_param('s', $cacheKey);
$cacheQuery->execute();
$cacheRow = $cacheQuery->get_result()->fetch_assoc();
$cacheQuery->close();

if ($cacheRow && strtotime($cacheRow['calculated_at']) >= time() - $cacheTtl) {
    $payload = json_decode($cacheRow['payload'], true);
    if (is_array($payload)) {
        $payload['calculated_at'] = $cacheRow['calculated_at'];
        echo json_encode($payload);
        exit;
    }
}

$tickers = fetch_tickers();
if (!isset($tickers['XAUUSDT']) || !isset($tickers['XAGUSDT'])) {
    if ($cacheRow && !empty($cacheRow['payload'])) {
        $payload = json_decode($cacheRow['payload'], true);
        if (is_array($payload)) {
            $payload['warning'] = 'Using stale cached Fear & Greed index because market data is unavailable.';
            $payload['calculated_at'] = $cacheRow['calculated_at'];
            echo json_encode($payload);
            exit;
        }
    }
    echo json_encode(['error' => 'Unable to fetch market data for the custom index.']);
    exit;
}

$assets = [
    'XAU' => load_price_history_stats($conn, 'XAU'),
    'XAG' => load_price_history_stats($conn, 'XAG')
];

$assetSnapshots = [];
$momentumScores = [];
$rangeScores = [];
$volatilityScores = [];

foreach (['XAUUSDT' => 'XAU', 'XAGUSDT' => 'XAG'] as $tickerSymbol => $assetCode) {
    $ticker  = $tickers[$tickerSymbol];
    $history = $assets[$assetCode];

    $currentPrice = (float) ($ticker['lastPrice'] ?? 0);

    // --- 24h high / low: Binance ticker is always the authoritative source. ---
    // Our price_history only contains prices from executed trades on this platform
    // which may be very sparse (0–1 rows). Using ONLY our history would give a
    // degenerate range where high==low==currentPrice → Range Position always 50 or 100.
    // Solution: always start from Binance ticker's real 24h range, then expand if
    // our history has a wider spread.
    $tickerHigh  = (float) ($ticker['highPrice'] ?? $currentPrice);
    $tickerLow   = (float) ($ticker['lowPrice']  ?? $currentPrice);
    $historyHigh = ($history['high'] !== null) ? (float) $history['high'] : 0.0;
    $historyLow  = ($history['low']  !== null) ? (float) $history['low']  : PHP_INT_MAX;

    // Binance ticker is the floor/ceiling; history can only widen the range.
    $high = ($historyHigh > $tickerHigh) ? $historyHigh : $tickerHigh;
    $low  = ($historyLow  > 0 && $historyLow < $tickerLow) ? $historyLow : $tickerLow;

    // --- Momentum: prefer our own DB change, fall back to Binance ticker ---
    $changePercent = $history['change_percent'];
    if ($changePercent === null) {
        // Binance 24h priceChangePercent is computed from Binance open price.
        // For our proxy mapping this is a reasonable stand-in.
        $changePercent = (float) ($ticker['priceChangePercent'] ?? 0.0);
    }

    $momentumScale = ($assetCode === 'XAU') ? 3.5 : 6.0;
    $volatilityScale = ($assetCode === 'XAU') ? 8.0 : 12.0;

    $momentumScore = score_from_signed_change((float) $changePercent, $momentumScale);
    $rangeScore    = score_from_range_position($currentPrice, $low, $high);

    // --- Volatility: use our DB stat when we have enough data, otherwise
    //     derive from the Binance 24h (high-low)/midpoint range, which is a
    //     reliable proxy for daily realized move.
    $volatilityPercent = $history['volatility_percent'];
    if ($volatilityPercent === null) {
        // Fallback: Binance intraday range as a percentage of the midpoint.
        // This correctly reflects real market volatility even when we have no
        // internal trade history (rather than pretending volatility is 0%).
        $midpoint = ($high + $low) / 2;
        $volatilityPercent = ($midpoint > 0) ? (($high - $low) / $midpoint) * 100 : 0.0;
    }
    $volatilityScore = score_from_volatility((float) $volatilityPercent, $volatilityScale);

    $assetSnapshots[] = [
        'asset'                => $assetCode,
        'price'                => $currentPrice,
        'change_percent'       => round((float) $changePercent, 2),
        'range_position_score' => $rangeScore,
        'volatility_percent'   => round((float) $volatilityPercent, 2),
        'momentum_score'       => $momentumScore,
        'volatility_score'     => $volatilityScore,
        'high'                 => $high,
        'low'                  => $low,
        'data_points'          => (int) $history['row_count'],
    ];

    $momentumScores[]    = $momentumScore;
    $rangeScores[]       = $rangeScore;
    $volatilityScores[]  = $volatilityScore;
}

$tradeFlow = load_trade_flow();

$momentumScore = (int) round(array_sum($momentumScores) / max(count($momentumScores), 1));
$rangeScore = (int) round(array_sum($rangeScores) / max(count($rangeScores), 1));
$volatilityScore = (int) round(array_sum($volatilityScores) / max(count($volatilityScores), 1));
$tradeScore = $tradeFlow['score'];

// --- Step 6: C3 Feed Integration (News Sentiment Index) ---
$nsiFloat = get_rolling_nsi($conn);
$clamp = 1.5;
$nsiScore = (($nsiFloat + $clamp) / ($clamp * 2)) * 100;
$nsiScore = (int) round(clamp_value($nsiScore, 0, 100));

$finalScore = (int) round(
    ($volatilityScore * 0.25) +
    ($momentumScore * 0.25) +
    ($rangeScore * 0.20) +
    ($tradeScore * 0.15) +
    ($nsiScore * 0.15)
);
$finalScore = (int) clamp_value($finalScore, 0, 100);

if ($finalScore >= 80) {
    $classification = 'EXTREME GREED';
    $description = 'Prices are holding near session highs, volatility is contained, and trade flow is leaning aggressively bullish.';
} elseif ($finalScore >= 60) {
    $classification = 'GREED';
    $description = '';
} elseif ($finalScore >= 40) {
    $classification = 'NEUTRAL';
    $description = 'The market is balanced. Momentum, volatility, and trade flow are roughly offsetting each other.';
} elseif ($finalScore >= 20) {
    $classification = 'FEAR';
    $description = 'Price pressure and market swings are leaning cautious, with sellers slightly more in control.';
} else {
    $classification = 'EXTREME FEAR';
    $description = 'Heavy downside pressure and elevated volatility are dominating the 24-hour market profile.';
}

$components = [
    build_component(
        'Momentum',
        $momentumScore,
        sprintf(
            '24h change: XAU %s%%, XAG %s%%',
            number_format((float) $assetSnapshots[0]['change_percent'], 2),
            number_format((float) $assetSnapshots[1]['change_percent'], 2)
        )
    ),
    build_component(
        'Range Position',
        $rangeScore,
        sprintf(
            'Current prices are sitting in %s of the 24h range on average.',
            $rangeScore >= 50 ? 'the upper half' : 'the lower half'
        )
    ),
    build_component(
        'Volatility',
        $volatilityScore,
        sprintf(
            'Realized volatility: XAU %s%%, XAG %s%%',
            number_format((float) $assetSnapshots[0]['volatility_percent'], 2),
            number_format((float) $assetSnapshots[1]['volatility_percent'], 2)
        )
    ),
    build_component(
        'Order Book Pressure',
        $tradeScore,
        sprintf(
            'Normalized bid/ask depth across order books. %s',
            $tradeFlow['detail'] ?? 'Balanced'
        )
    ),
    build_component(
        'News Sentiment',
        $nsiScore,
        sprintf(
            'Aggregated NLP index from top-tier financial RSS feeds (Raw NSI: %s).',
            number_format($nsiFloat, 2)
        )
    )
];

$payload = [
    'score' => $finalScore,
    'classification' => $classification,
    'description' => $description,
    'summary' => sprintf('Mom %d | Vol %d | Rng %d | Flw %d | NSI %d', $momentumScore, $volatilityScore, $rangeScore, $tradeScore, $nsiScore),
    'components' => $components,
    'assets' => $assetSnapshots,
    'calculated_at' => date('Y-m-d H:i:s')
];

$payloadJson = json_encode($payload);
$save = $conn->prepare('INSERT INTO fear_greed_index_cache (cache_key, payload, calculated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE payload = VALUES(payload), calculated_at = VALUES(calculated_at)');
$save->bind_param('ss', $cacheKey, $payloadJson);
$save->execute();
$save->close();

echo $payloadJson;