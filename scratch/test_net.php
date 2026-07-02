<?php
require_once 'config.php';

$url = "https://api.binance.com/api/v3/klines?symbol=PAXGUSDT&interval=1h&startTime=1780502128000&limit=15";
echo "Fetching: $url\n";

$options = [
    'http' => [
        'timeout' => 10,
        'ignore_errors' => true
    ]
];
$context = stream_context_create($options);
$response = file_get_contents($url, false, $context);

echo "HTTP Code / Headers:\n";
print_r($http_response_header);
echo "\nResponse body:\n";
echo $response . "\n";
