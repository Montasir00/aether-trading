<?php
/**
 * Technical Indicators API
 * 
 * Fetches historical kline data from Binance and calculates:
 * - SMA (Simple Moving Average) - periods 20, 50
 * - RSI (Relative Strength Index) - period 14
 * - MACD (Moving Average Convergence Divergence) - 12/26/9
 * 
 * Returns JSON with price data + overlaid indicators for Chart.js
 */

session_start();
if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');
require_once 'api_helper.php';

$symbol   = strtoupper($_GET['symbol'] ?? 'XAUUSDT');
$interval = $_GET['interval'] ?? '1h';

// Validate inputs
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
$validSymbols   = array_keys($symbolMap);
$validIntervals = ['1m', '5m', '15m', '1h', '4h', '1d'];

if (!in_array($symbol, $validSymbols)) {
    echo json_encode(['error' => 'Invalid symbol']);
    exit;
}
if (!in_array($interval, $validIntervals)) {
    echo json_encode(['error' => 'Invalid interval']);
    exit;
}

// Proxy symbols for Binance since it lacks traditional commodities
$binanceSymbol = $symbolMap[$symbol];

// Fetch enough data for MACD (need at least 26+9 = 35 extra candles)
$limit = 200;
$url   = "https://api.binance.com/api/v3/klines?symbol={$binanceSymbol}&interval={$interval}&limit={$limit}";
$json  = fetchWithRetry($url, 3, 10);

if ($json === false) {
    echo json_encode(['error' => 'Failed to fetch data from Binance after retries']);
    exit;
}

$klines = json_decode($json, true);
if (!is_array($klines) || empty($klines) || !isset($klines[0]) || !is_array($klines[0])) {
    echo json_encode(['error' => 'Invalid data from Binance']);
    exit;
}

// Extract OHLCV data
$timestamps = [];
$opens   = [];
$highs   = [];
$lows    = [];
$closes  = [];
$volumes = [];

foreach ($klines as $k) {
    if (!is_array($k) || count($k) < 6) {
        continue;
    }
    $timestamps[] = $k[0]; // open time ms
    $opens[]      = (float)$k[1];
    $highs[]      = (float)$k[2];
    $lows[]       = (float)$k[3];
    $closes[]     = (float)$k[4];
    $volumes[]    = (float)$k[5];
}

// ============================================================
//  SMA - Simple Moving Average
// ============================================================
function calculateSMA(array $data, int $period): array
{
    $sma = [];
    $len = count($data);
    for ($i = 0; $i < $len; $i++) {
        if ($i < $period - 1) {
            $sma[] = null;
        } else {
            $slice = array_slice($data, $i - $period + 1, $period);
            $sma[] = round(array_sum($slice) / $period, 2);
        }
    }
    return $sma;
}

// ============================================================
//  RSI - Relative Strength Index (Wilder's smoothing)
// ============================================================
function calculateRSI(array $closes, int $period = 14): array
{
    $rsi = [];
    $len = count($closes);

    if ($len < $period + 1) {
        return array_fill(0, $len, null);
    }

    // Calculate price changes
    $changes = [];
    for ($i = 1; $i < $len; $i++) {
        $changes[] = $closes[$i] - $closes[$i - 1];
    }

    // Initial average gain/loss (simple average of first 'period' changes)
    $gains  = 0;
    $losses = 0;
    for ($i = 0; $i < $period; $i++) {
        if ($changes[$i] >= 0) {
            $gains += $changes[$i];
        } else {
            $losses += abs($changes[$i]);
        }
    }

    $avgGain = $gains / $period;
    $avgLoss = $losses / $period;

    // First RSI value (at index = period)
    $rsi[] = null; // index 0: no change available
    for ($i = 1; $i <= $period; $i++) {
        $rsi[] = null; // not enough data yet
    }

    if ($avgLoss == 0) {
        $rsi[$period] = ($avgGain == 0) ? 50.00 : 100.00;
    } else {
        $rs = $avgGain / $avgLoss;
        $rsi[$period] = round(100 - (100 / (1 + $rs)), 2);
    }

    // Subsequent RSI values using Wilder's smoothing
    for ($i = $period; $i < count($changes); $i++) {
        $change = $changes[$i];
        $gain   = $change >= 0 ? $change : 0;
        $loss   = $change < 0 ? abs($change) : 0;

        $avgGain = (($avgGain * ($period - 1)) + $gain) / $period;
        $avgLoss = (($avgLoss * ($period - 1)) + $loss) / $period;

        if ($avgLoss == 0) {
            $rsi[] = ($avgGain == 0) ? 50.00 : 100.00;
        } else {
            $rs    = $avgGain / $avgLoss;
            $rsi[] = round(100 - (100 / (1 + $rs)), 2);
        }
    }

    return $rsi;
}

// ============================================================
//  MACD - Moving Average Convergence Divergence
// ============================================================
function calculateEMA(array $data, int $period): array
{
    $ema        = [];
    $multiplier = 2 / ($period + 1);
    $len        = count($data);

    for ($i = 0; $i < $len; $i++) {
        if ($i < $period - 1) {
            $ema[] = null;
        } elseif ($i === $period - 1) {
            // Seed EMA with SMA of first 'period' values
            $ema[] = round(array_sum(array_slice($data, 0, $period)) / $period, 2);
        } else {
            $prev   = $ema[$i - 1];
            $ema[]  = round(($data[$i] - $prev) * $multiplier + $prev, 2);
        }
    }
    return $ema;
}

function calculateMACD(array $closes, int $fastPeriod = 12, int $slowPeriod = 26, int $signalPeriod = 9): array
{
    $emaFast = calculateEMA($closes, $fastPeriod);
    $emaSlow = calculateEMA($closes, $slowPeriod);
    $len     = count($closes);

    // MACD line = EMA(fast) - EMA(slow)
    $macdLine = [];
    for ($i = 0; $i < $len; $i++) {
        if ($emaFast[$i] === null || $emaSlow[$i] === null) {
            $macdLine[] = null;
        } else {
            $macdLine[] = round($emaFast[$i] - $emaSlow[$i], 2);
        }
    }

    // Signal line = EMA of MACD line (period 9)
    // Only use non-null MACD values for EMA calculation
    $nonNullMACD = [];
    $macdStartIndex = null;
    for ($i = 0; $i < $len; $i++) {
        if ($macdLine[$i] !== null) {
            $nonNullMACD[] = $macdLine[$i];
            if ($macdStartIndex === null) $macdStartIndex = $i;
        }
    }

    $signalRaw = calculateEMA($nonNullMACD, $signalPeriod);

    // Map signal back to full-length array
    $signalLine = array_fill(0, $len, null);
    for ($i = 0; $i < count($signalRaw); $i++) {
        $signalLine[$macdStartIndex + $i] = $signalRaw[$i];
    }

    // Histogram = MACD - Signal
    $histogram = [];
    for ($i = 0; $i < $len; $i++) {
        if ($macdLine[$i] === null || $signalLine[$i] === null) {
            $histogram[] = null;
        } else {
            $histogram[] = round($macdLine[$i] - $signalLine[$i], 2);
        }
    }

    return [
        'macd_line'   => $macdLine,
        'signal_line' => $signalLine,
        'histogram'   => $histogram
    ];
}

// ============================================================
//  CALCULATE ALL INDICATORS
// ============================================================

$sma20 = calculateSMA($closes, 20);
$sma50 = calculateSMA($closes, 50);
$rsi   = calculateRSI($closes, 14);
$macd  = calculateMACD($closes, 12, 26, 9);

// Format timestamps to human-readable based on interval
$labels = array_map(function ($ts) use ($interval) {
    $seconds = $ts / 1000;
    if ($interval === '1d') {
        return date('M d', $seconds);
    } elseif ($interval === '1h' || $interval === '4h') {
        return date('M d H:i', $seconds);
    } else {
        return date('H:i', $seconds);
    }
}, $timestamps);

// Return everything
echo json_encode([
    'symbol'     => $symbol,
    'interval'   => $interval,
    'labels'     => $labels,
    'timestamps' => $timestamps,
    'ohlcv' => [
        'open'   => $opens,
        'high'   => $highs,
        'low'    => $lows,
        'close'  => $closes,
        'volume' => $volumes
    ],
    'indicators' => [
        'sma20'       => $sma20,
        'sma50'       => $sma50,
        'rsi'         => $rsi,
        'macd_line'   => $macd['macd_line'],
        'signal_line' => $macd['signal_line'],
        'histogram'   => $macd['histogram']
    ]
]);
