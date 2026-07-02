<?php
/**
 * cron_worker.php
 * Long-running PHP worker to run alert checks on a schedule.
 */
require_once 'config.php';
require_once 'alerts/alerts_core.php';
// Ensure logs dir exists and set PHP error_log to file for persistent logging
if (!is_dir(__DIR__ . '/logs')) {
    @mkdir(__DIR__ . '/logs', 0755, true);
}
ini_set('error_log', __DIR__ . '/logs/check_alerts.log');

// Simple log rotation: rotate when file exceeds 5MB
function rotate_log_if_needed(string $path, int $maxBytes = 5242880): void {
    if (!file_exists($path)) {
        return;
    }
    clearstatcache(true, $path);
    $size = filesize($path);
    if ($size === false) {
        return;
    }
    if ($size > $maxBytes) {
        $dest = $path . '.' . date('Ymd_His');
        @rename($path, $dest);
        // New log file will be created by PHP when next error_log call happens
        error_log('[cron_worker] rotated log to ' . $dest);
    }
}

echo "Starting cron_worker: running alert checks every 120 seconds\n";

$stop = false;
$interval = 120; // seconds between successful runs
$backoff = $interval; // current wait time
$maxBackoff = 900; // 15 minutes

// Setup signal handling if available
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
}
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, function() use (&$stop) { $stop = true; });
    pcntl_signal(SIGINT, function() use (&$stop) { $stop = true; });
}

// Run loop with responsive sleep so we can shutdown quickly
while (!$stop) {
    try {
        rotate_log_if_needed(__DIR__ . '/logs/check_alerts.log');
        
        if (!@$conn->ping()) {
            error_log('[cron_worker] DB connection lost. Reconnecting...');
            @$conn->close();
            $conn = new mysqli($host, $user, $password, $dbname, $port);
        }

        // Record current prices (live or mocked) to price_history table on each tick
        include 'api/fetch_price.php';

        run_alert_checks($conn);
        
        // --- SMA Bot Background Real-Time Execution ---
        require_once 'trading_engine/StrategyEngine.php';
        require_once 'trading_engine/TradeExecutor.php';
        require_once 'api/api_helper.php';

        $xauPrice = fetchBinancePrice('XAUUSDT');
        if ($xauPrice !== null && $xauPrice > 0) {
            $strategy = new StrategyEngine($conn);
            $result   = $strategy->generateSignal();
            
            if ($result['signal'] !== 'HOLD') {
                // Find all users who have enabled the SMA bot
                $usersRes = $conn->query("SELECT id FROM users WHERE bot_enabled = 1");
                if ($usersRes && $usersRes->num_rows > 0) {
                    while ($uRow = $usersRes->fetch_assoc()) {
                        $botUserId = (int)$uRow['id'];
                        $executor  = new TradeExecutor($botUserId);
                        $execution = $executor->execute($result['signal'], $botUserId, $xauPrice);
                        error_log("[cron_worker] Bot Action for User $botUserId: Signal={$result['signal']}, Price=$xauPrice -> Executed: $execution");
                    }
                }
            }
        }
        
        error_log('[cron_worker] price history recorded, alert checks and bot logic executed at ' . date('c'));
        // reset backoff after success
        $backoff = $interval;
    } catch (Throwable $e) {
        error_log('[cron_worker] Unexpected error: ' . $e->getMessage());
        // exponential backoff on error
        $backoff = min((int)($backoff * 2), $maxBackoff);
        error_log('[cron_worker] backing off for ' . $backoff . ' seconds');
    }

    // Sleep in 1-second increments to remain responsive to signals
    $slept = 0;
    while ($slept < $backoff && !$stop) {
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }
        sleep(1);
        $slept++;
    }
}

error_log('[cron_worker] shutdown requested, exiting');
echo "cron_worker exiting\n";
