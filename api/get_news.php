<?php
// Proxy that fetches Kitco precious-metals news RSS and caches results (30 min).
// Kitco is the leading gold & silver news source — directly relevant to XAU/XAG trading.
header('Content-Type: application/json');

require_once '../config.php';
require_once '../nlp_engine.php';

$cacheDir = __DIR__ . '/../tmp';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
$cacheFile = $cacheDir . '/news_cache.json';
$cacheTtl  = (int)(getenv('NEWS_CACHE_TTL') ?: 1800); // default 30 minutes

// Return cache if fresh
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTtl)) {
    echo file_get_contents($cacheFile);
    exit;
}

function curl_get($url, $headers = [], $timeout = 10) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; AetherBot/1.0)');
    if (!empty($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$resp, $err, $code];
}

function parse_rss(string $xmlRaw, string $sourceName): ?array
{
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xmlRaw);
    if ($xml === false) return null;

    $items = [];
    $channel = $xml->channel ?? $xml;
    foreach ($channel->item as $it) {
        $title    = trim((string)$it->title);
        $link     = trim((string)$it->link);
        $pubDate  = trim((string)$it->pubDate);
        $timestamp = strtotime($pubDate) ?: time();
        if (!$title) continue;
        $items[] = [
            'title'        => $title,
            'source'       => $sourceName,
            'published_on' => $timestamp,
            'url'          => $link,
        ];
    }
    return $items;
}

$result = null;
$items  = [];

// --- Source 1: Mining.com Gold News RSS (Active Precious Metals Commodities News) ---
$goldUrl = 'https://www.mining.com/commodity/gold/feed/';
[$xmlRaw1, $err1, $code1] = curl_get($goldUrl);
if ($xmlRaw1 && $code1 >= 200 && $code1 < 300) {
    $parsed1 = parse_rss($xmlRaw1, 'Mining.com (Gold)');
    if ($parsed1) {
        $items = array_merge($items, $parsed1);
    }
}

// --- Source 2: Mining.com Silver News RSS (Active Precious Metals Commodities News) ---
$silverUrl = 'https://www.mining.com/commodity/silver/feed/';
[$xmlRaw2, $err2, $code2] = curl_get($silverUrl);
if ($xmlRaw2 && $code2 >= 200 && $code2 < 300) {
    $parsed2 = parse_rss($xmlRaw2, 'Mining.com (Silver)');
    if ($parsed2) {
        $items = array_merge($items, $parsed2);
    }
}

// --- Source 3: MarketWatch Top Stories RSS (Active Macro / Stocks Market News) ---
$mwUrl = 'http://feeds.marketwatch.com/marketwatch/topstories';
[$xmlRaw3, $err3, $code3] = curl_get($mwUrl);
if ($xmlRaw3 && $code3 >= 200 && $code3 < 300) {
    $parsed3 = parse_rss($xmlRaw3, 'MarketWatch');
    if ($parsed3) {
        $items = array_merge($items, $parsed3);
    }
}

if (!empty($items)) {
    // Deduplicate by URL
    $uniqueUrls = [];
    $dedupedItems = [];
    foreach ($items as $item) {
        $urlKey = strtolower(trim($item['url']));
        if (!isset($uniqueUrls[$urlKey])) {
            $uniqueUrls[$urlKey] = true;
            $dedupedItems[] = $item;
        }
    }
    $items = $dedupedItems;

    // Sort by newest first
    usort($items, fn($a, $b) => $b['published_on'] - $a['published_on']);
    $items = array_slice($items, 0, 20); // only keep top 20 newest

    // Initialize NLP Engine
    $nlp = new NLPEngine();
    $fetched_at = time();

    // Prepare DB Insert
    $stmt = $conn->prepare("
        INSERT IGNORE INTO news_sentiment (headline, score, confidence, source, url, published_at, fetched_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $filteredItems = [];

    foreach ($items as $item) {
        $analysis = $nlp->analyze($item['title']);
        
        // Smart Relevance Filter: Skip articles with zero matched market keywords
        if (empty($analysis['is_relevant'])) {
            continue;
        }

        $item['score'] = $analysis['score'];
        $item['tag'] = $analysis['tag'];
        $item['confidence'] = $analysis['confidence'];

        // Write to DB
        $stmt->bind_param(
            "sdsssii", 
            $item['title'], 
            $analysis['score'], 
            $analysis['confidence'], 
            $item['source'], 
            $item['url'], 
            $item['published_on'], 
            $fetched_at
        );
        $stmt->execute();
        
        $filteredItems[] = $item;
    }
    $stmt->close();

    $payload = [
        'Response' => 'Success',
        'Message'  => 'Fetched from Mining.com & MarketWatch Top Stories RSS',
        'Type'     => 200,
        'Data'     => $filteredItems,
    ];
    $result = json_encode($payload);
}

if ($result === null) {
    // Return stale cache if available
    if (file_exists($cacheFile)) {
        $cached = file_get_contents($cacheFile);
        if ($cached) {
            echo $cached;
            exit;
        }
    }
    http_response_code(502);
    echo json_encode(["Response" => "Error", "Message" => "All news sources unavailable"]);
    exit;
}

// Save to cache (best-effort)
@file_put_contents($cacheFile, $result);

echo $result;
?>
