<?php
/**
 * Centralized API helper with retry logic, timeout, and rate-limit handling.
 * Used across all PHP files that make external HTTP requests (Binance API).
 */

/**
 * Fetch a URL with retry logic, timeout, and rate-limit/backoff handling.
 *
 * @param string $url       The URL to fetch
 * @param int    $retries   Number of attempts (default 3)
 * @param float  $timeout   Connection timeout in seconds (default 5)
 * @param array  $postData  If provided, sends as POST with JSON body
 * @return string|false     The response body, or false on total failure
 */
function fetchWithRetry(string $url, int $retries = 3, float $timeout = 5, ?array $postData = null)
{
    // If Guzzle is available, prefer it for robust HTTP handling
    if (class_exists('GuzzleHttp\\Client')) {
        try {
            $client = new GuzzleHttp\Client([
                'timeout' => $timeout,
                'connect_timeout' => $timeout,
                'http_errors' => false,
                'headers' => ['Accept' => 'application/json']
            ]);

            $options = [];
            if ($postData !== null) {
                $options['json'] = $postData;
            }

            $attempt = 0;
            while ($attempt < $retries) {
                $attempt++;
                try {
                    $res = $client->request($postData === null ? 'GET' : 'POST', $url, $options);
                } catch (Exception $e) {
                    error_log("[API] Guzzle request failed for $url (attempt $attempt/$retries): " . $e->getMessage());
                    if ($attempt < $retries) {
                        usleep(500000 * $attempt);
                        continue;
                    }
                    return false;
                }

                $code = $res->getStatusCode();
                $body = (string) $res->getBody();

                if ($code === 429) {
                    $wait = 2 * $attempt;
                    error_log("[API] Rate limited on $url (attempt $attempt/$retries). Waiting {$wait}s.");
                    sleep($wait);
                    continue;
                }

                if ($code >= 500 && $attempt < $retries) {
                    error_log("[API] Server error $code from $url (attempt $attempt/$retries).");
                    usleep(500000 * $attempt);
                    continue;
                }

                return $body;
            }

            return false;
        } catch (Throwable $t) {
            error_log('[API] Guzzle unavailable or failed: ' . $t->getMessage());
            // fallthrough to fallback implementation
        }
    }

    // Fallback to stream-based implementation if Guzzle not present
    $lastError = '';

    for ($attempt = 1; $attempt <= $retries; $attempt++) {
        $contextOptions = [
            'http' => [
                'timeout'       => $timeout,
                'ignore_errors' => true,
                'header'        => "Accept: application/json\r\n",
            ]
        ];

        if ($postData !== null) {
            $jsonBody = json_encode($postData);
            $contextOptions['http']['method']  = 'POST';
            $contextOptions['http']['header'] .= "Content-Type: application/json\r\n";
            $contextOptions['http']['content']  = $jsonBody;
        }

        $context  = stream_context_create($contextOptions);
        $response = @file_get_contents($url, false, $context);

        $httpCode = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $header, $m)) {
                    $httpCode = (int) $m[1];
                }
            }
        }

        if ($httpCode === 429) {
            $retryAfter = 2 * $attempt;
            error_log("[API] Rate limited on $url (attempt $attempt/$retries). Waiting {$retryAfter}s.");
            sleep($retryAfter);
            continue;
        }

        if ($httpCode >= 500 && $attempt < $retries) {
            error_log("[API] Server error $httpCode from $url (attempt $attempt/$retries).");
            usleep(500000 * $attempt);
            continue;
        }

        if ($response === false) {
            $lastError = "Connection failed to $url";
            error_log("[API] $lastError (attempt $attempt/$retries).");
            usleep(500000 * $attempt);
            continue;
        }

        return $response;
    }

    error_log("[API] All $retries attempts failed for $url. Last error: $lastError");
    return false;
}

/**
 * Fetch and JSON-decode a URL with retry, returning decoded array or null.
 *
 * @param string $url
 * @param int    $retries
 * @param float  $timeout
 * @return array|null
 */
function fetchJsonWithRetry(string $url, int $retries = 3, float $timeout = 5): ?array
{
    $raw = fetchWithRetry($url, $retries, $timeout);
    if ($raw === false) return null;

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        error_log("[API] Invalid JSON from $url: " . substr($raw, 0, 200));
        return null;
    }

    return $data;
}

/**
 * Fetch a single Binance ticker price (e.g., BTCUSDT).
 *
 * @param string $symbol  e.g., 'BTCUSDT'
 * @return float|null      The price, or null on failure
 */
function fetchBinancePrice(string $symbol): ?float
{
    global $conn;
    if (!isset($conn) || !($conn instanceof mysqli)) {
        if (file_exists(__DIR__ . '/../config.php')) {
            @include_once __DIR__ . '/../config.php';
        }
    }

    if (isset($conn) && $conn instanceof mysqli) {
        try {
            $res = $conn->query("SELECT mock_xau_price, mock_xag_price FROM bot_control WHERE id = 1");
            if ($res && $row = $res->fetch_assoc()) {
                if ($symbol === 'XAUUSDT' && $row['mock_xau_price'] !== null) {
                    return (float)$row['mock_xau_price'];
                }
                if ($symbol === 'XAGUSDT' && $row['mock_xag_price'] !== null) {
                    return (float)$row['mock_xag_price'];
                }
            }
        } catch (Throwable $e) {
            // Ignore database errors during price fetch fallback
        }
    }

    // Binance doesn't support XAU/XAG natively. Use proxies: PAXG (Pax Gold) and LTC (Digital Silver)
    $mappedSymbol = $symbol;
    if ($symbol === 'XAUUSDT') $mappedSymbol = 'PAXGUSDT';
    elseif ($symbol === 'XAGUSDT') $mappedSymbol = 'LTCUSDT';
    elseif ($symbol === 'AAPLUSDT') $mappedSymbol = 'OPUSDT';
    elseif ($symbol === 'TSLAUSDT') $mappedSymbol = 'ARBUSDT';
    elseif ($symbol === 'MSFTUSDT') $mappedSymbol = 'APTUSDT';
    elseif ($symbol === 'AMZNUSDT') $mappedSymbol = 'SUIUSDT';
    elseif ($symbol === 'GOOGUSDT') $mappedSymbol = 'NEARUSDT';
    elseif ($symbol === 'NVDAUSDT') $mappedSymbol = 'FTMUSDT';
    elseif ($symbol === 'METAUSDT') $mappedSymbol = 'GRTUSDT';
    elseif ($symbol === 'NFLXUSDT') $mappedSymbol = 'LDOUSDT';
    elseif ($symbol === 'AMDUSDT') $mappedSymbol = 'FILUSDT';
    elseif ($symbol === 'BABAUSDT') $mappedSymbol = 'INJUSDT';
    elseif ($symbol === 'COALUSDT') $mappedSymbol = 'SOLUSDT';
    elseif ($symbol === 'COCOAUSDT') $mappedSymbol = 'DOTUSDT';
    elseif ($symbol === 'COFFEEUSDT') $mappedSymbol = 'ADAUSDT';
    elseif ($symbol === 'COPPERUSDT') $mappedSymbol = 'AVAXUSDT';
    elseif ($symbol === 'CORNUSDT') $mappedSymbol = 'LINKUSDT';
    elseif ($symbol === 'COTTONUSDT') $mappedSymbol = 'UNIUSDT';
    elseif ($symbol === 'GASUSDT') $mappedSymbol = 'ICPUSDT';
    elseif ($symbol === 'IRONUSDT') $mappedSymbol = 'MATICUSDT';
    elseif ($symbol === 'LEADUSDT') $mappedSymbol = 'ETCUSDT';
    elseif ($symbol === 'LUMBERUSDT') $mappedSymbol = 'FILUSDT';
    elseif ($symbol === 'NICKELUSDT') $mappedSymbol = 'ATOMUSDT';
    elseif ($symbol === 'OILUSDT') $mappedSymbol = 'XLMUSDT';
    elseif ($symbol === 'PLATUSDT') $mappedSymbol = 'LTCUSDT';
    elseif ($symbol === 'STEELUSDT') $mappedSymbol = 'VETUSDT';
    elseif ($symbol === 'SUGARUSDT') $mappedSymbol = 'TRXUSDT';
    elseif ($symbol === 'TINUSDT') $mappedSymbol = 'FTMUSDT';
    elseif ($symbol === 'WHEATUSDT') $mappedSymbol = 'ALGOUSDT';
    elseif ($symbol === 'ZINCUSDT') $mappedSymbol = 'THETAUSDT';

    $cacheFile = sys_get_temp_dir() . "/binance_price_" . $mappedSymbol . ".json";
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 5) {
        $data = json_decode(file_get_contents($cacheFile), true);
    } else {
        $data = fetchJsonWithRetry(
            "https://api.binance.com/api/v3/ticker/price?symbol=$mappedSymbol"
        );
        if ($data !== null) {
            file_put_contents($cacheFile, json_encode($data), LOCK_EX);
        }
    }

    return isset($data['price']) ? (float) $data['price'] : null;
}
