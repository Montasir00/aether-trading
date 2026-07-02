<?php
session_start();

if (!isset($_SESSION['id'])) {
    header("Location: index.php");
    exit;
}

require_once "config.php";
require_once "alerts/alerts_core.php";

$message = "";
$messageType = "success";

// Handle simulation actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    try {
        if ($action === 'override') {
            $xau = !empty($_POST['mock_xau']) ? (float)$_POST['mock_xau'] : null;
            $xag = !empty($_POST['mock_xag']) ? (float)$_POST['mock_xag'] : null;

            $stmt = $conn->prepare("UPDATE bot_control SET mock_xau_price = ?, mock_xag_price = ? WHERE id = 1");
            $stmt->bind_param("dd", $xau, $xag);
            $stmt->execute();
            $stmt->close();

            $message = "Mock prices successfully updated! XAU (Gold) = " . ($xau !== null ? "$" . number_format($xau, 2) : "LIVE") . ", XAG (Silver) = " . ($xag !== null ? "$" . number_format($xag, 2) : "LIVE");
        }

        if ($action === 'reset') {
            $conn->query("UPDATE bot_control SET mock_xau_price = NULL, mock_xag_price = NULL WHERE id = 1");
            $message = "All price overrides have been reset to live Binance feeds successfully.";
        }

        if ($action === 'inject_golden_cross') {
            // Delete existing XAU price history
            $conn->query("DELETE FROM price_history WHERE asset = 'XAU'");

            // Prepare history insert
            $baseTime = time();
            $stmt = $conn->prepare("INSERT INTO price_history (asset, price, recorded_at) VALUES ('XAU', ?, FROM_UNIXTIME(?))");

            // Insert 200 stable items at $2000
            for ($i = 0; $i < 200; $i++) {
                $price = 2000.00;
                $timestamp = $baseTime - (205 - $i) * 60;
                $stmt->bind_param("di", $price, $timestamp);
                $stmt->execute();
            }
            $stmt->close();

            // Set current XAU mock price to cause a Golden Cross on the next tick
            $conn->query("UPDATE bot_control SET mock_xau_price = 2550.00 WHERE id = 1");
            // Set user's bot position to NONE so the bot is allowed to BUY
            $conn->query("UPDATE users SET bot_position = 'NONE' WHERE id = " . intval($_SESSION['id']));

            $message = "Golden Cross pattern ready to inject! 200 flat XAU price records inserted at $2000. Mock price set to $2550. Click 'Force Simulation Tick' to execute the BUY crossover trade.";
        }

        if ($action === 'inject_death_cross') {
            // Delete existing XAU price history
            $conn->query("DELETE FROM price_history WHERE asset = 'XAU'");

            // Prepare history insert
            $baseTime = time();
            $stmt = $conn->prepare("INSERT INTO price_history (asset, price, recorded_at) VALUES ('XAU', ?, FROM_UNIXTIME(?))");

            // Insert 200 stable items at $2000
            for ($i = 0; $i < 200; $i++) {
                $price = 2000.00;
                $timestamp = $baseTime - (205 - $i) * 60;
                $stmt->bind_param("di", $price, $timestamp);
                $stmt->execute();
            }
            $stmt->close();

            // Set current XAU mock price to cause a Death Cross on the next tick
            $conn->query("UPDATE bot_control SET mock_xau_price = 1450.00 WHERE id = 1");
            // Set user's bot position to LONG so the bot is allowed to SELL
            $conn->query("UPDATE users SET bot_position = 'LONG' WHERE id = " . intval($_SESSION['id']));

            $message = "Death Cross pattern ready to inject! 200 flat XAU price records inserted at $2000. Mock price set to $1450. Click 'Force Simulation Tick' to execute the SELL crossover trade.";
        }


        if ($action === 'force_tick') {
            require_once 'api/api_helper.php';

            // 1. Fetch current active prices (whether live or mocked)
            $xauPrice = fetchBinancePrice('XAUUSDT');
            $xagPrice = fetchBinancePrice('XAGUSDT');

            // 2. Append to price_history table
            if ($xauPrice !== null && $xauPrice > 0) {
                $stmt = $conn->prepare("INSERT INTO price_history (asset, price, recorded_at) VALUES ('XAU', ?, NOW())");
                $stmt->bind_param("d", $xauPrice);
                $stmt->execute();
                $stmt->close();
            }
            if ($xagPrice !== null && $xagPrice > 0) {
                $stmt = $conn->prepare("INSERT INTO price_history (asset, price, recorded_at) VALUES ('XAG', ?, NOW())");
                $stmt->bind_param("d", $xagPrice);
                $stmt->execute();
                $stmt->close();
            }

            // 3. Run alert engine checks
            run_alert_checks($conn);

            // 4. Run strategy calculations and bot execution if active
            $strategyLog = "";
            if (!empty($_SESSION['strategy_enabled'])) {
                require_once 'trading_engine/StrategyEngine.php';
                require_once 'trading_engine/TradeExecutor.php';

                $strategy = new StrategyEngine($conn);
                $result   = $strategy->generateSignal();

                if ($result['signal'] !== 'HOLD' && $xauPrice !== null) {
                    $executor = new TradeExecutor($_SESSION['id']);
                    $execution = $executor->execute(
                        $result['signal'],
                        $_SESSION['id'],
                        $xauPrice
                    );
                    $strategyLog = " Signal: " . $result['signal'] . " -> Executed: " . $execution;
                } else {
                    $strategyLog = " Signal: " . $result['signal'] . " -> No order executed (HOLD or invalid price).";
                }
            } else {
                $strategyLog = " Strategy bot is INACTIVE (Enable Bot in the dashboard first to execute trades).";
            }

            $message = "Force simulation tick executed! Price history logged, alerts evaluated." . $strategyLog;
        }
    } catch (Throwable $e) {
        $message = "Simulation Error: " . $e->getMessage();
        $messageType = "error";
    }
}

// Read current mock overrides from database
$mock_xau = null;
$mock_xag = null;
$res = $conn->query("SELECT mock_xau_price, mock_xag_price FROM bot_control WHERE id = 1");
if ($res && $row = $res->fetch_assoc()) {
    $mock_xau = $row['mock_xau_price'];
    $mock_xag = $row['mock_xag_price'];
}

// Fetch active price alerts for quick testing reference
$alertsStmt = $conn->prepare("SELECT a.id, a.coin, a.target_price, a.operator, u.username FROM price_alerts a JOIN users u ON a.user_id = u.id WHERE a.notified = 0 ORDER BY a.created_at DESC");
$alertsStmt->execute();
$alerts = $alertsStmt->get_result();
$alertsStmt->close();

// Fetch current indicators for summary
require_once 'StrategyEngine.php';
$strategy = new StrategyEngine($conn);
$sigResult = $strategy->generateSignal();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aether — Presentation Simulation Panel</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="global.css">
    <style>
        .sim-container {
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .sim-header {
            margin-bottom: var(--space-xl);
            border-bottom: 1px solid var(--border-strong);
            padding-bottom: var(--space-lg);
        }
        .sim-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--accent);
            letter-spacing: -0.5px;
            margin-bottom: var(--space-xs);
        }
        .sim-subtitle {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }
        .sim-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--space-lg);
            align-items: start;
        }
        .sim-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: var(--space-xl);
            box-shadow: var(--shadow-md);
            transition: var(--transition);
        }
        .sim-card:hover {
            border-color: var(--border-strong);
            box-shadow: var(--shadow-lg), var(--shadow-glow);
        }
        .sim-card-title {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: var(--space-md);
            color: var(--text-primary);
            border-bottom: 1px solid rgba(212, 175, 55, 0.05);
            padding-bottom: var(--space-xs);
        }
        .form-group {
            margin-bottom: var(--space-md);
        }
        .form-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: var(--space-xs);
        }
        .form-input {
            width: 100%;
            background: var(--bg-primary);
            border: 1px solid var(--border-strong);
            color: var(--text-primary);
            padding: var(--space-sm) var(--space-md);
            border-radius: var(--radius-sm);
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.95rem;
            transition: var(--transition);
        }
        .form-input:focus {
            border-color: var(--accent);
            outline: none;
            box-shadow: var(--shadow-glow);
        }
        .alert-box {
            padding: var(--space-md);
            border-radius: var(--radius-sm);
            margin-bottom: var(--space-lg);
            font-size: 0.9rem;
            font-weight: 600;
            line-height: 1.4;
        }
        .alert-success {
            background: var(--green-bg);
            color: var(--green);
            border: 1px solid rgba(0, 200, 83, 0.2);
        }
        .alert-error {
            background: var(--red-bg);
            color: var(--red);
            border: 1px solid rgba(213, 0, 0, 0.2);
        }
        .btn-flex {
            display: flex;
            gap: var(--space-sm);
            margin-top: var(--space-lg);
        }
        .badge-live {
            background: rgba(41, 182, 246, 0.1);
            color: var(--blue);
            padding: 2px 6px;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
        }
        .badge-mock {
            background: var(--accent-glow);
            color: var(--accent);
            padding: 2px 6px;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            margin-top: var(--space-md);
        }
        .data-table th, .data-table td {
            padding: var(--space-sm) var(--space-md);
            text-align: left;
            border-bottom: 1px solid rgba(212, 175, 55, 0.05);
        }
        .data-table th {
            color: var(--text-secondary);
            font-weight: 600;
        }
        .data-table td {
            font-family: 'JetBrains Mono', monospace;
        }
        .stat-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--space-md);
            margin-top: var(--space-md);
        }
        .stat-item {
            background: var(--bg-primary);
            padding: var(--space-md);
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
        }
        .stat-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-weight: 600;
        }
        .stat-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
            font-family: 'JetBrains Mono', monospace;
            margin-top: var(--space-xs);
        }
        .signal-buy {
            color: var(--green);
        }
        .signal-sell {
            color: var(--red);
        }
        .signal-hold {
            color: var(--blue);
        }
        .btn-cross {
            flex: 1;
            padding: var(--space-md);
            text-align: center;
            font-size: 0.9rem;
            font-weight: 700;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: var(--transition);
        }
        .btn-golden {
            background: var(--green-bg);
            color: var(--green);
            border: 1px solid rgba(0, 200, 83, 0.2);
        }
        .btn-golden:hover {
            background: var(--green);
            color: #000;
            box-shadow: 0 0 15px rgba(0, 200, 83, 0.4);
        }
        .btn-death {
            background: var(--red-bg);
            color: var(--red);
            border: 1px solid rgba(213, 0, 0, 0.2);
        }
        .btn-death:hover {
            background: var(--red);
            color: #fff;
            box-shadow: 0 0 15px rgba(213, 0, 0, 0.4);
        }
        .force-tick-card {
            grid-column: 1 / -1;
            background: linear-gradient(135deg, var(--bg-card) 0%, rgba(212, 175, 55, 0.03) 100%);
            border: 1px solid var(--accent-glow-strong);
            text-align: center;
        }
        .back-dash-link {
            display: inline-block;
            margin-top: var(--space-lg);
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: var(--transition);
        }
        .back-dash-link:hover {
            color: var(--accent-hover);
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="sim-container g-animate-in">

    <div class="sim-header">
        <h1 class="sim-title">Aether Presentation Simulation Engine</h1>
        <div class="sim-subtitle">Manipulate commodity pricing feeds, inject synthetic historical trends, and force tick alert evaluations instantly.</div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="g-alert <?= $messageType === 'success' ? 'g-alert-success' : 'g-alert-error' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="sim-grid">

        <!-- CARD 1: Price Override Controller -->
        <div class="g-double-bezel">
          <div class="g-double-bezel-inner">
            <span class="g-eyebrow" style="text-align:left; margin-bottom: 2px;">Price Feeds</span>
            <h2 class="sim-card-title">Live Feed Overrides</h2>
            <form method="POST">
                <input type="hidden" name="action" value="override">
                
                <div class="form-group">
                    <label class="form-label" for="mock_xau">
                        Gold (XAU) Price (USDT/oz)
                        <?php if ($mock_xau !== null): ?>
                            <span class="badge-mock">MOCKED: $<?= number_format($mock_xau, 2) ?></span>
                        <?php else: ?>
                            <span class="badge-live">BINANCE LIVE</span>
                        <?php endif; ?>
                    </label>
                    <input type="number" step="0.01" class="form-input" id="mock_xau" name="mock_xau" placeholder="Leave empty for live Binance feed" value="<?= $mock_xau !== null ? htmlspecialchars($mock_xau) : '' ?>">
                </div>

                <div class="form-group" style="margin-top: var(--space-md);">
                    <label class="form-label" for="mock_xag">
                        Silver (XAG) Price (USDT/oz)
                        <?php if ($mock_xag !== null): ?>
                            <span class="badge-mock">MOCKED: $<?= number_format($mock_xag, 2) ?></span>
                        <?php else: ?>
                            <span class="badge-live">BINANCE LIVE</span>
                        <?php endif; ?>
                    </label>
                    <input type="number" step="0.01" class="form-input" id="mock_xag" name="mock_xag" placeholder="Leave empty for live Binance feed" value="<?= $mock_xag !== null ? htmlspecialchars($mock_xag) : '' ?>">
                </div>

                <div class="btn-flex">
                    <button type="submit" class="g-btn-pill g-btn-pill-primary" style="flex:1;">Apply Overrides</button>
            </form>
            <form method="POST" style="flex:1;">
                <input type="hidden" name="action" value="reset">
                <button type="submit" class="g-btn-pill g-btn-outline" style="width:100%; border-radius: 9999px;">Reset to Live Feed</button>
            </form>
                </div>
            </div>
        </div>

        <!-- CARD 2: Crossover Strategy Generator -->
        <div class="g-double-bezel">
          <div class="g-double-bezel-inner">
            <span class="g-eyebrow" style="text-align:left; margin-bottom: 2px;">SMA Generator</span>
            <h2 class="sim-card-title">Crossover Signals Generator</h2>
            <p style="font-size: 0.85rem; color: var(--text-secondary); line-height: 1.4; margin-bottom: var(--space-md);">
                Inject a synthetic history pattern (210 prices) to instantly force the Golden/Death crossover SMA signals. This bypasses days of real market wait times.
            </p>
            <div class="btn-flex">
                <form method="POST" style="flex: 1;">
                    <input type="hidden" name="action" value="inject_golden_cross">
                    <button type="submit" class="btn-cross btn-golden" style="width: 100%;">Inject Golden Cross (BUY)</button>
                </form>
                <form method="POST" style="flex: 1;">
                    <input type="hidden" name="action" value="inject_death_cross">
                    <button type="submit" class="btn-cross btn-death" style="width: 100%;">Inject Death Cross (SELL)</button>
                </form>
            </div>

            <div class="stat-grid" style="margin-top: var(--space-lg);">
                <div class="stat-item">
                    <div class="stat-label">Active Signal</div>
                    <div class="stat-value <?= $sigResult['signal'] === 'BUY' ? 'signal-buy' : ($sigResult['signal'] === 'SELL' ? 'signal-sell' : 'signal-hold') ?>">
                        <?= $sigResult['signal'] ?>
                    </div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">History Window Count</div>
                    <div class="stat-value"><?= $sigResult['prices_count'] ?> / 201</div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Computed SMA(50)</div>
                    <div class="stat-value"><?= $sigResult['sma50'] !== null ? number_format($sigResult['sma50'], 2) : 'N/A' ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Computed SMA(200)</div>
                    <div class="stat-value"><?= $sigResult['sma200'] !== null ? number_format($sigResult['sma200'], 2) : 'N/A' ?></div>
                </div>
            </div>
            </div>
        </div>

        <!-- CARD 3: Active Pending Alerts Monitor -->
        <div class="g-double-bezel" style="grid-column: 1 / -1;">
          <div class="g-double-bezel-inner">
            <span class="g-eyebrow" style="text-align:left; margin-bottom: 2px;">Alert Node</span>
            <h2 class="sim-card-title">Active Pending Price Alerts</h2>
            <p style="font-size: 0.85rem; color: var(--text-secondary); line-height: 1.4; margin-bottom: var(--space-xs);">
                Use these target parameters as references for setting mock price values to test immediate email triggers.
            </p>
            <div style="max-height: 250px; overflow-y: auto;">
                <table class="g-table">
                    <thead>
                        <tr>
                            <th>Alert ID</th>
                            <th>User</th>
                            <th>Asset</th>
                            <th>Trigger Parameter</th>
                            <th>Trigger Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($alerts->num_rows === 0): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: var(--text-muted); padding: var(--space-lg);">No active pending alerts set.</td>
                            </tr>
                        <?php else: ?>
                            <?php while ($row = $alerts->fetch_assoc()): ?>
                                <tr>
                                    <td class="g-numeric">#<?= $row['id'] ?></td>
                                    <td><?= htmlspecialchars($row['username']) ?></td>
                                    <td><strong><?= htmlspecialchars($row['coin']) ?></strong></td>
                                    <td>Price is <?= htmlspecialchars($row['operator']) ?></td>
                                    <td class="g-numeric">$<?= number_format($row['target_price'], 2) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            </div>
        </div>

        <!-- CARD 4: Instant Action Engine -->
        <div class="g-double-bezel" style="grid-column: 1 / -1;">
          <div class="g-double-bezel-inner force-tick-card">
            <span class="g-eyebrow" style="margin-bottom: 2px;">Simulation Engine</span>
            <h2 class="sim-card-title" style="color: var(--accent); border-bottom: none; margin-bottom: var(--space-sm);">Action Execution Engine</h2>
            <p style="font-size: 0.9rem; color: var(--text-secondary); max-width: 600px; margin: 0 auto var(--space-lg) auto;">
                Clicking <strong>"Force Simulation Tick"</strong> forces an immediate price history update, checks all pending price alerts, and triggers the automated trading strategy. Orders will execute if the strategy bot is enabled in the dashboard.
            </p>
            <form method="POST">
                <input type="hidden" name="action" value="force_tick">
                <button type="submit" class="g-btn-pill g-btn-pill-primary g-btn-lg" style="padding: 14px 40px; font-weight: 800; font-size: 1.05rem;">Force Simulation Tick</button>
            </form>
            </div>
        </div>

    </div>

    <div style="text-align: center;">
        <a href="dashboard.php" class="back-dash-link">⬅ Return to Main Dashboard</a>
    </div>

</div>

</body>
</html>
