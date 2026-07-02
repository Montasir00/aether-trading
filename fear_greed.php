<?php
session_start();

if (!isset($_SESSION['id'])) {
    header("Location: index.php");
    exit;
}

require_once "config.php";

$user_id = $_SESSION['id'];
$sql = "SELECT username FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$username = $row['username'] ?? 'User';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sentiment — Aether</title>
    <meta name="description" content="Custom Fear & Greed Index for Aether, recalculated daily from price momentum, volatility, range position, and trade flow.">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="stylesheet" href="global.css?v=<?php echo filemtime('global.css'); ?>">
    <link rel="stylesheet" href="dashboard.css?v=<?php echo filemtime('dashboard.css'); ?>">
    <link rel="stylesheet" href="fear_greed.css?v=<?php echo filemtime('fear_greed.css'); ?>">
</head>
<body>
<div class="g-page">
<?php include 'navbar.php'; ?>


<main class="sentiment-page">
    <section class="sentiment-hero g-animate-in">
        <div>
            <p class="sentiment-eyebrow">Daily Market Sentiment</p>
            <h1>Custom Fear & Greed Index</h1>
            <p class="sentiment-copy">A real-time sentiment gauge calculated from price momentum, daily range, volatility, and order book depth. Updates every 15 minutes.</p>
        </div>
        <div class="sentiment-hero-meta">
            <div class="sentiment-user">Signed in as <strong><?php echo htmlspecialchars($username); ?></strong></div>
            <a href="dashboard.php" class="g-btn g-btn-outline">Back to Dashboard</a>
        </div>
    </section>

    <section class="sentiment-grid">
        <div class="g-card sentiment-main g-animate-in" style="animation-delay:0.05s">
            <div class="dash-card-header">
                <div class="dash-card-title">Index Reading</div>
                <span id="fear-greed-label" class="g-badge g-badge-yellow">Loading</span>
            </div>

            <div class="sentiment-meter-wrap">
                <div class="gauge-container fear-greed-gauge">
                    <div class="gauge-ring">
                        <div id="fear-greed-fill" class="gauge-fill"></div>
                        <div class="gauge-center">
                            <span id="fear-greed-value">50</span>
                            <small>INDEX</small>
                        </div>
                    </div>
                </div>
                <div>
                    <p id="fear-greed-status" class="dash-panel-status is-loading">Loading market sentiment...</p>
                    <p id="fear-greed-last-updated" class="dash-last-updated">Last updated: --</p>
                    <div class="sentiment-scale">
                        <span>Extreme Fear</span>
                        <span>Fear</span>
                        <span>Neutral</span>
                        <span>Greed</span>
                        <span>Extreme Greed</span>
                    </div>
                </div>
            </div>

            <div id="fear-greed-components" class="fear-greed-components sentiment-components"></div>
        </div>

        <div class="g-card sentiment-main g-animate-in" style="animation-delay:0.08s">
            <div class="dash-card-header" style="display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;">
                <div class="dash-card-title">Market News Feed</div>
                <select id="news-filter" class="g-select" style="padding: 6px 32px 6px 12px; font-size: 0.8rem; height: 32px; width: auto; min-width: 140px; margin: 0;">
                    <option value="all">All News</option>
                    <option value="gold">Gold News</option>
                    <option value="silver">Silver News</option>
                    <option value="macro">Macro / MarketWatch</option>
                </select>
            </div>
            <p class="dash-card-subtitle">Recent commodity news with automated sentiment tags.</p>
            <div id="news-feed" class="news-list">
                <div class="skeleton skeleton-text"></div>
                <div class="skeleton skeleton-text"></div>
                <div class="skeleton skeleton-text"></div>
            </div>
        </div>

        <div class="g-card sentiment-side g-animate-in" style="animation-delay:0.1s">
            <div class="dash-card-header">
                <div class="dash-card-title">How It Works</div>
            </div>
            <div class="sentiment-explainer">
                <article>
                    <h3>Price Momentum</h3>
                    <p>Tracks whether the 24-hour trend is bullish or bearish.</p>
                </article>
                <article>
                    <h3>Daily Range</h3>
                    <p>Measures where the current price sits relative to the 24-hour high and low.</p>
                </article>
                <article>
                    <h3>Market Volatility</h3>
                    <p>Tracks price fluctuations; high volatility lowers index scores.</p>
                </article>
                <article>
                    <h3>Order Book Depth</h3>
                    <p>Analyzes active buy and sell orders on the order book.</p>
                </article>
            </div>
        </div>
    </section>
</main>

<footer class="g-footer">
    <p>&copy; 2026 Aether — Commodities Trading Simulator</p>
</footer>
</div>
<script src="global.js?v=<?php echo filemtime('global.js'); ?>" defer></script>
<script src="fear_greed.js?v=<?php echo filemtime('fear_greed.js'); ?>" defer></script>
</body>
</html>