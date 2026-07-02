<?php
/**
 * Calculates the News Sentiment Index (NSI) using an exponential time-decay
 * aggregate over the last 24 hours of fetched headlines.
 */

function get_rolling_nsi($conn): float {
    $now = time();
    $twentyFourHoursAgo = $now - (24 * 3600);

    $stmt = $conn->prepare("
        SELECT score, published_at 
        FROM news_sentiment 
        WHERE published_at >= ?
    ");
    $stmt->bind_param("i", $twentyFourHoursAgo);
    $stmt->execute();
    $result = $stmt->get_result();

    $decayConstantLambda = 0.15; // loses ~14% weight per hour
    $weightedSum = 0.0;
    $totalWeight = 0.0;
    $count = 0;

    while ($row = $result->fetch_assoc()) {
        $score = (float)$row['score'];
        $publishedAt = (int)$row['published_at'];
        
        $ageInSeconds = max(0, $now - $publishedAt);
        $ageInHours = $ageInSeconds / 3600.0;

        $weight = exp(-$decayConstantLambda * $ageInHours);
        
        $weightedSum += ($score * $weight);
        $totalWeight += $weight;
        $count++;
    }
    $stmt->close();

    if ($totalWeight > 0) {
        return $weightedSum / $totalWeight;
    }

    return 0.0; // Default Neutral
}

// If called directly via HTTP, output JSON
if (basename($_SERVER['PHP_SELF']) === 'get_nsi.php') {
    require_once '../config.php';
    header('Content-Type: application/json');
    
    $nsi = get_rolling_nsi($conn);
    echo json_encode([
        'nsi' => round($nsi, 4),
        'message' => 'Calculated using exponential time decay'
    ]);
}
?>
