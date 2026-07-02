<?php
session_start();

if (!isset($_SESSION['id'])) {
    header("Location: index.php");
    exit;
}

require_once "config.php";

$user_id = $_SESSION['id'];
$sql = "SELECT username, balance, xau_balance, xag_balance, bot_enabled FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

$username = $row['username'] ?? 'User';
$_SESSION['strategy_enabled'] = (bool)($row['bot_enabled'] ?? 0);


$walletStmt = $conn->prepare("SELECT w.id AS wallet_id FROM wallets w WHERE w.user_id = ? LIMIT 1");
$walletStmt->bind_param("i", $user_id);
$walletStmt->execute();
$walletRow = $walletStmt->get_result()->fetch_assoc();
$walletStmt->close();

if ($walletRow) {
    $walletId = (int)$walletRow['wallet_id'];
    $balStmt = $conn->prepare("SELECT asset, balance FROM balances WHERE wallet_id = ?");
    $balStmt->bind_param("i", $walletId);
    $balStmt->execute();
    $balances = $balStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $balStmt->close();

    $usdt = 0.0; $xau = 0.0; $xag = 0.0;
    foreach ($balances as $balanceRow) {
        $asset  = strtoupper((string)($balanceRow['asset'] ?? ''));
        $amount = (float)($balanceRow['balance'] ?? 0);
        if ($asset === 'USDT') $usdt = $amount;
        if ($asset === 'XAU')  $xau  = $amount;
        if ($asset === 'XAG')  $xag  = $amount;
    }
    $usdt_balance = number_format($usdt, 2);
    $xau_balance  = number_format($xau,  6);
    $xag_balance  = number_format($xag,  4);
} else {
    $usdt_balance = number_format((float)($row['balance']     ?? 0), 2);
    $xau_balance  = number_format((float)($row['xau_balance'] ?? 0), 6);
    $xag_balance  = number_format((float)($row['xag_balance'] ?? 0), 4);
}

$stmt_alerts = $conn->prepare("SELECT * FROM price_alerts WHERE user_id = ? ORDER BY created_at DESC");
$stmt_alerts->bind_param("i", $user_id);
$stmt_alerts->execute();
$alerts = $stmt_alerts->get_result();
$stmt_alerts->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Aether</title>
    <meta name="description" content="Aether commodities trading dashboard — monitor Gold (XAU) and Silver (XAG) prices, portfolio, and trade history in real time.">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="global.css?v=<?php echo filemtime('global.css'); ?>">
    <link rel="stylesheet" href="dashboard.css?v=<?php echo filemtime('dashboard.css'); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@2" defer></script>
</head>
<body>
<div id="splash-screen" class="splash-screen">
    <div class="splash-logo">
        <svg aria-hidden="true" width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 2L2 7l10 5 10-5-10-5z" />
            <path d="M2 17l10 5 10-5" />
            <path d="M2 12l10 5 10-5" />
        </svg>
    </div>
    <div class="splash-text">Syncing market data…</div>
</div>

<div class="g-page">
    <div class="g-mesh-glow"></div>

<!-- ── Navbar ── -->
<?php include 'navbar.php'; ?>

<main id="main-content">
    <div class="dash-wrap g-animate-in">

        <?php if (isset($_SESSION['flash'])): ?>
            <div class="g-alert g-alert-success" style="margin-bottom: var(--space-lg);">
                <?= htmlspecialchars($_SESSION['flash']) ?>
            </div>
            <?php unset($_SESSION['flash']); ?>
        <?php endif; ?>

    <div class="dash-grid">

        <!-- ── Row 1: Topbar — Welcome + Bot Toggle ── -->
        <div class="dash-topbar">
            <div class="dash-topbar-left">
                <h1 class="dash-welcome-title">Welcome back, <?php echo htmlspecialchars($username); ?></h1>
                <p class="dash-welcome-sub">Here's your portfolio at a glance.</p>
            </div>
            <div class="dash-topbar-actions">
                <a href="sandbox/index.php" class="g-btn-pill g-btn-outline" style="font-size:0.82rem;padding:8px 20px;">
                    <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                    Sandbox
                </a>
                <a href="buy_sell_form.php" class="g-btn-pill g-btn-outline" style="font-size:0.82rem;padding:8px 20px;">
                    <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
                    Trade
                </a>
                <form method="POST" action="toggle_strategy.php" style="display:inline;">
                    <button type="submit" class="g-btn-pill <?= $_SESSION['strategy_enabled'] ? 'g-btn-pill-danger' : 'g-btn-pill-primary' ?>" style="font-size:0.82rem;padding:8px 20px; display: inline-flex; align-items: center;">
                        <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px;">
                            <rect x="3" y="11" width="18" height="10" rx="2" ry="2"></rect>
                            <path d="M12 2v4M12 5H8m8 0h-4M8 15h.01M16 15h.01"></path>
                        </svg>
                        <?= $_SESSION['strategy_enabled'] ? "Disable Bot" : "Enable Bot" ?>
                    </button>
                </form>
            </div>
        </div>

        <!-- ── Row 2: Portfolio Balance ── -->
        <div class="g-double-bezel g-animate-in" style="animation-delay:0.1s">
            <div class="g-double-bezel-inner">
                <div class="dash-card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h2 class="g-eyebrow" style="text-align:left;margin-bottom:0;font-size:inherit;display:inline;">Portfolio Balance</h2>
                    <span id="portfolio-total-val" style="font-size:0.85rem; font-weight:700; color:var(--accent); letter-spacing:0.5px; text-transform:uppercase;">
                        Total: <span class="skeleton skeleton-text" style="width: 80px; display: inline-block;"></span>
                    </span>
                </div>
                <div class="wallet-grid">
                    <div class="wallet-item">
                        <div class="coin-avatar" style="background: rgba(38, 161, 123, 0.1); border-color: rgba(38, 161, 123, 0.2);">
                            <svg viewBox="0 0 24 24" width="24" height="24">
                                <circle cx="12" cy="12" r="12" fill="#26A17B" />
                                <path d="M12 5.5v3.6m0 0h4.2v2.5H12v6.9h-2.5v-6.9H5.3v-2.5h4.2v-3.6H12z M4 5.5h16v1.3H4V5.5z" fill="white"/>
                            </svg>
                        </div>
                        <div class="coin-details">
                            <div class="coin-label">USDT</div>
                            <div class="coin-value" id="usdt-amount"><span class="skeleton skeleton-text"></span></div>
                        </div>
                    </div>
                    <div class="wallet-item">
                        <div class="coin-avatar" style="background: rgba(229, 169, 59, 0.1); border-color: rgba(229, 169, 59, 0.2);">
                            <svg viewBox="0 0 24 24" width="24" height="24">
                                <circle cx="12" cy="12" r="12" fill="#E5A93B" />
                                <path d="M7 16h10l1.2-3H5.8L7 16zm4.5-9h6l1.2-3H10.3l1.2 3zm-6 4.5h10.4l1.2-3H4.8l1.2 3z" fill="white" />
                            </svg>
                        </div>
                        <div class="coin-details">
                            <div class="coin-label">XAU — Gold</div>
                            <div class="coin-value" id="xau-amount"><span class="skeleton skeleton-text"></span></div>
                        </div>
                    </div>
                    <div class="wallet-item">
                        <div class="coin-avatar" style="background: rgba(166, 166, 166, 0.1); border-color: rgba(166, 166, 166, 0.2);">
                            <svg viewBox="0 0 24 24" width="24" height="24">
                                <circle cx="12" cy="12" r="12" fill="#A6A6A6" />
                                <circle cx="12" cy="12" r="8" stroke="white" stroke-width="1.8" fill="none"/>
                                <circle cx="12" cy="12" r="4" fill="white" fill-opacity="0.6"/>
                            </svg>
                        </div>
                        <div class="coin-details">
                            <div class="coin-label">XAG — Silver <span class="proxy-tag" title="Priced via LTC proxy">proxy</span></div>
                            <div class="coin-value" id="xag-amount"><span class="skeleton skeleton-text"></span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Row 3: Risk + Strategy side by side ── -->
        <div class="dash-grid-top">
            <!-- Risk Management -->
            <div class="g-double-bezel g-animate-in" style="animation-delay:0.2s">
                <div class="g-double-bezel-inner">
                    <div class="dash-card-header">
                        <h2 class="g-eyebrow" style="text-align:left;margin-bottom:0;font-size:inherit;display:inline;">Risk Management</h2>
                        <span id="risk-level" class="g-badge g-badge-green">—</span>
                    </div>
                    <div class="dash-panel-meta">
                        <p id="risk-panel-status" class="dash-panel-status is-loading" aria-live="polite">Loading risk metrics…</p>
                        <p id="risk-last-updated" class="dash-last-updated">Last updated: --</p>
                    </div>
                    <div class="risk-grid">
                        <div class="risk-metric"><span class="metric-label">Value</span><span id="risk-portfolio-value">—</span></div>
                        <div class="risk-metric"><span class="metric-label">XAU Exposure</span><span id="risk-btc-exposure">—</span></div>
                        <div class="risk-metric"><span class="metric-label">Daily Volume</span><span id="risk-daily-volume">—</span></div>
                        <div class="risk-metric"><span class="metric-label">Trades</span><span id="risk-daily-trades">—</span></div>
                        <div class="risk-metric"><span class="metric-label">Drawdown</span><span id="risk-drawdown">—</span></div>
                    </div>
                </div>
            </div>

            <!-- Strategy Bot -->
            <div class="g-double-bezel g-animate-in" style="animation-delay:0.25s">
                <div class="g-double-bezel-inner">
                    <div class="dash-card-header">
                        <h2 class="g-eyebrow" style="text-align:left;margin-bottom:0;font-size:inherit;display:inline;">Strategy Bot (XAU)</h2>
                        <span id="strategy-ui-status" class="g-badge <?= $_SESSION['strategy_enabled'] ? 'g-badge-green' : 'g-badge-red' ?>">
                            <?= $_SESSION['strategy_enabled'] ? 'ACTIVE' : 'INACTIVE' ?>
                        </span>
                    </div>
                    <div class="dash-panel-meta">
                        <p id="strategy-panel-status" class="dash-panel-status is-loading" aria-live="polite">Checking strategy…</p>
                        <p id="strategy-last-updated" class="dash-last-updated">Last updated: --</p>
                    </div>
                    <div class="strategy-grid">
                        <div class="strategy-stat"><div class="stat-label">Signal</div><div class="stat-value" id="strategy-signal">—</div></div>
                        <div class="strategy-stat"><div class="stat-label">SMA(50)</div><div class="stat-value" id="strategy-sma50">—</div></div>
                        <div class="strategy-stat"><div class="stat-label">SMA(200)</div><div class="stat-value" id="strategy-sma200">—</div></div>
                        <div class="strategy-stat"><div class="stat-label">Action</div><div class="stat-value" id="strategy-action">—</div></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Row 4: Market Data (24h) ── -->
        <div class="g-double-bezel g-animate-in" style="animation-delay:0.3s">
            <div class="g-double-bezel-inner">
                <div class="dash-card-header">
                    <h2 class="g-eyebrow" style="text-align:left;margin-bottom:0;font-size:inherit;display:inline;">Market Data (24h)</h2>
                </div>
                <p class="dash-card-subtitle">Snapshot of the latest 24-hour move, range, and volume context.</p>
                <div class="dash-panel-meta">
                    <p id="market-panel-status" class="dash-panel-status is-loading" aria-live="polite">Fetching market data…</p>
                    <p id="market-last-updated" class="dash-last-updated">Last updated: --</p>
                </div>
                <div class="dash-table-wrap">
                    <table class="market-table">
                        <thead>
                            <tr><th>Commodity</th><th>Price</th><th>24h High</th><th>24h Low</th><th>Volume</th></tr>
                        </thead>
                        <tbody id="market-data">
                            <tr><td><span class="skeleton skeleton-text"></span></td><td><span class="skeleton skeleton-text"></span></td><td><span class="skeleton skeleton-text"></span></td><td><span class="skeleton skeleton-text"></span></td><td><span class="skeleton skeleton-text"></span></td></tr>
                            <tr><td><span class="skeleton skeleton-text"></span></td><td><span class="skeleton skeleton-text"></span></td><td><span class="skeleton skeleton-text"></span></td><td><span class="skeleton skeleton-text"></span></td><td><span class="skeleton skeleton-text"></span></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ── Row 5: Commodities Heatmap ── -->
        <div class="g-double-bezel g-animate-in" style="animation-delay:0.35s">
            <div class="g-double-bezel-inner">
                <div class="dash-card-header">
                    <h2 class="g-eyebrow" style="text-align:left;margin-bottom:0;font-size:inherit;display:inline;">Commodities Heatmap</h2>
                </div>
                <div id="market-heatmap" class="heatmap-container">
                    <div class="skeleton-block" style="height:100%;width:100%;"></div>
                </div>
            </div>
        </div>

        <!-- ── Row 6: Price Alerts ── -->
        <div class="g-double-bezel g-animate-in" style="animation-delay:0.4s">
            <div class="g-double-bezel-inner">
                <div class="dash-card-header">
                    <h2 class="g-eyebrow" style="text-align:left;margin-bottom:0;font-size:inherit;display:inline;">Price Alerts</h2>
                    <a href="alerts/create_alert.php" class="g-btn-pill g-btn-outline alert-new-btn" style="border-radius:9999px;padding:6px 16px;font-size:0.78rem;">+ New</a>
                </div>
                <p class="dash-card-subtitle">Active alerts and their current trigger state.</p>
                <div class="dash-table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr><th>Asset</th><th>Target Price</th><th>Status</th><th>Created</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php if ($alerts->num_rows === 0): ?>
                                <tr><td colspan="5" class="dash-empty-row">No alerts set</td></tr>
                            <?php endif; ?>
                            <?php while ($alert = $alerts->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($alert['coin']) ?></strong></td>
                                    <td>$<?= number_format((float)$alert['target_price'], 2) ?> / oz</td>
                                    <td>
                                        <span class="g-badge <?= $alert['notified'] ? 'g-badge-green' : 'g-badge-yellow' ?>">
                                            <?= $alert['notified'] ? 'Triggered' : 'Pending' ?>
                                        </span>
                                    </td>
                                    <td class="dash-date-cell"><?= $alert['created_at'] ?></td>
                                    <td>
                                        <?php if (!$alert['notified']): ?>
                                            <form method="POST" action="alerts/delete_alert.php" onsubmit="return confirm('Delete this alert?');" class="inline-form">
                                                <input type="hidden" name="alert_id" value="<?= $alert['id'] ?>">
                                                <button type="submit" class="delete-alert-btn">Delete</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="dash-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div><!-- /dash-grid -->
    </div><!-- /dash-wrap -->
</main>

<footer class="g-footer">
    <p>&copy; 2026 Aether — Commodities Trading Simulator</p>
</footer>

</div><!-- /g-page -->

<script>
document.addEventListener('DOMContentLoaded', () => {
    const splash = document.getElementById('splash-screen');
    if (splash) {
        if (!sessionStorage.getItem('dashSplashSeen')) {
            sessionStorage.setItem('dashSplashSeen', '1');
            setTimeout(() => { splash.classList.add('fade-out'); setTimeout(() => splash.remove(), 600); }, 600);
        } else { splash.remove(); }
    }
});
</script>
<script src="global.js?v=<?php echo filemtime('global.js'); ?>" defer></script>
<script src="engine.js?v=<?php echo filemtime('engine.js'); ?>" defer></script>
<script src="heatmap.js?v=<?php echo filemtime('heatmap.js'); ?>" defer></script>
<script src="dashboard.js?v=<?php echo filemtime('dashboard.js'); ?>" defer></script>
</body>
</html>
