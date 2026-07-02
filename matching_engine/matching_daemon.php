<?php
/**
 * Aether Asynchronous Matching Engine Daemon
 * Long-running process that consumes orders from the Redis queue and executes matching.
 */

// Run continuously in CLI mode
set_time_limit(0);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/matching_engine.php';

use Predis\Client as RedisClient;

echo "Aether Matching Daemon starting...\n";

// Connect to Redis
$redisHost = getenv('REDIS_HOST') ?: '127.0.0.1';
$redisPort = getenv('REDIS_PORT') ?: 6379;

try {
    $redis = new RedisClient([
        'scheme' => 'tcp',
        'host'   => $redisHost,
        'port'   => $redisPort,
        'timeout' => 0.0 // keep connection open indefinitely
    ]);
    $redis->ping();
    echo "Connected to Redis successfully.\n";
} catch (Exception $e) {
    echo "Error connecting to Redis: " . $e->getMessage() . "\n";
    exit(1);
}

// Instantiate matching engine
$engine = new MatchingEngine($conn);

// On startup: Load existing open/pending orders from MySQL to catch up on any missed matches
echo "Syncing unresolved orders from database...\n";
$unresolvedQuery = $conn->query("SELECT id FROM orders WHERE status IN ('open', 'partially_filled') ORDER BY created_at ASC");
if ($unresolvedQuery) {
    $count = 0;
    while ($row = $unresolvedQuery->fetch_assoc()) {
        $redis->lpush('order_ingest_queue', $row['id']);
        $count++;
    }
    echo "Enqueued $count unresolved orders for processing.\n";
}

echo "Matching Daemon is ready and listening for orders...\n";

while (true) {
    try {
        // Pop order ID from queue (blocking pop with no timeout)
        $res = $redis->brpop(['order_ingest_queue'], 0);
        if (!$res) {
            continue;
        }

        $orderId = (int)$res[1];
        echo "Processing order ID: $orderId...\n";

        // Query the order to determine type and execute matching
        $stmt = $conn->prepare("SELECT type, status FROM orders WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $orderId);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } else {
            echo "Failed to prepare statement for fetching order $orderId\n";
            continue;
        }

        if (!$order) {
            echo "Order $orderId not found in database. Skipping.\n";
            continue;
        }

        if ($order['status'] === 'completed' || $order['status'] === 'cancelled') {
            echo "Order $orderId is already resolved ({$order['status']}). Skipping.\n";
            continue;
        }

        // Lock database transaction to match this order safely
        $conn->begin_transaction();
        
        $matchResult = null;
        if ($order['type'] === 'market') {
            // Market orders execute immediately in the daemon
            $matchResult = $engine->executeMarketOrderAsynchronously($orderId);
        } else {
            // Limit orders match against opposite book
            $matchResult = $engine->matchLimitOrderAsynchronously($orderId);
        }

        if ($matchResult['success']) {
            $conn->commit();
            echo "Successfully processed order $orderId: {$matchResult['message']}\n";
        } else {
            $conn->rollback();
            echo "Failed to process order $orderId: {$matchResult['message']}\n";
        }

    } catch (Exception $e) {
        if ($conn && $conn->ping()) {
            $conn->rollback();
        }
        echo "Exception occurred: " . $e->getMessage() . "\n";
        sleep(1); // avoid infinite tight crash loop
    }
}
?>
