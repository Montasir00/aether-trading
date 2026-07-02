<?php
require_once 'config.php';

// Subtract exactly 2 years (730 days) from created_at for all forecasts to align with the real-world Binance timeline
$sql = "UPDATE ai_forecasts SET created_at = DATE_SUB(created_at, INTERVAL 2 YEAR) WHERE realized = 0";
if ($conn->query($sql)) {
    echo "Successfully updated " . $conn->affected_rows . " pending forecasts to real-world timestamps.\n";
} else {
    echo "Database error: " . $conn->error . "\n";
}
?>
