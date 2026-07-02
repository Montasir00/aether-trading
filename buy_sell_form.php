<?php
session_start();
if (!isset($_SESSION['id'])) {
    header("Location: index.php");
    exit;
}
require_once 'config.php';
$user_id = $_SESSION['id'];

$pairsStmt = $conn->query("SELECT base_asset FROM trading_pairs WHERE active = 1 ORDER BY base_asset ASC");
$activeCoins = [];
if ($pairsStmt) {
    while ($pRow = $pairsStmt->fetch_assoc()) {
        $base = $pRow['base_asset'];
        $name = ($base === 'XAU') ? 'Gold' : (($base === 'XAG') ? 'Silver' : $base);
        $activeCoins[] = ['base_asset' => $base, 'name' => $name];
    }
}

// Fetch recent transactions
$stmt_tx = $conn->prepare("
    SELECT type, coin, amount, price, total, created_at FROM (
        SELECT type COLLATE utf8mb4_unicode_ci AS type, coin COLLATE utf8mb4_unicode_ci AS coin, amount, price, total, created_at FROM transactions WHERE user_id = ?
        UNION ALL
        SELECT o.side COLLATE utf8mb4_unicode_ci AS type, t.base_asset COLLATE utf8mb4_unicode_ci AS coin, o.filled_qty AS amount, o.price, o.total, o.created_at FROM orders o JOIN trading_pairs t ON o.pair = t.symbol WHERE o.user_id = ? AND o.filled_qty > 0
    ) combined ORDER BY created_at DESC LIMIT 10
");
$stmt_tx->bind_param("ii", $user_id, $user_id);
$stmt_tx->execute();
$transactions = $stmt_tx->get_result();
$stmt_tx->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Trade — Aether</title>
  <meta name="description" content="Trade Gold (XAU) and Silver (XAG) commodities instantly with Aether's premium simulated order matching engine.">
  <link rel="icon" type="image/svg+xml" href="favicon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="global.css?v=<?php echo filemtime('global.css'); ?>">
  <link rel="stylesheet" href="dashboard.css?v=<?php echo filemtime('dashboard.css'); ?>">
  <link rel="stylesheet" href="buy_sell_form.css?v=<?php echo filemtime('buy_sell_form.css'); ?>">
  <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@2" defer></script>
  <!-- Web3.js for MetaMask / Ganache integration -->
  <script src="https://cdn.jsdelivr.net/npm/web3@4.4.0/dist/web3.min.js"></script>
</head>
<body>
<div class="g-page">
<div class="g-mesh-glow"></div>

<?php include 'navbar.php'; ?>

<main id="main-content">
  <div class="trade-split-layout">

    <!-- ══════════════════════════════════
         LEFT: Technical Analysis Panel
    ══════════════════════════════════ -->
    <section class="trade-ta-panel" aria-label="Technical Analysis">
      <div class="g-double-bezel h-full">
        <div class="g-double-bezel-inner">

          <div class="dash-card-header">
            <span class="g-eyebrow" style="text-align:left; margin-bottom:0;">Technical Analysis</span>
          </div>
          <p class="dash-card-subtitle">Compare trend, momentum, and signal overlays for the selected commodity.</p>

          <!-- Asset selector -->
          <div class="technical-selector-wrap">
            <div class="technical-selector-head">
              <span class="technical-selector-label">Top 20 Commodities</span>
              <span class="technical-selector-hint">Select an asset to load its chart</span>
            </div>
            <label class="technical-symbol-select-wrap" for="technical-symbol-select">
              <span class="technical-symbol-select-label">Asset</span>
              <select id="technical-symbol-select" class="technical-symbol-select">
                <option value="">Loading assets…</option>
              </select>
            </label>
            <div id="technical-symbol-summary" class="technical-symbol-summary">Choose an asset to view its technical chart.</div>
          </div>

          <!-- Hidden Inputs for Backwards Compatibility -->
          <input type="checkbox" id="show-sma" style="display: none;">
          <input type="checkbox" id="show-rsi" style="display: none;">
          <input type="checkbox" id="show-macd" style="display: none;">
          <input type="checkbox" id="enable-ai" style="display: none;">
          <input type="checkbox" id="enable-forecast" style="display: none;">

          <!-- Chart controls -->
          <div class="chart-controls" style="display: flex; gap: var(--space-sm); align-items: center; flex-wrap: wrap;">
            <div class="control-group" style="display: inline-flex; align-items: center; gap: var(--space-xs);">
              <label for="chart-interval" style="font-size: 0.72rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px;">Interval</label>
              <select id="chart-interval" style="padding: 6px 12px; font-size: 0.78rem; font-weight: 600; color: var(--text-primary); background: var(--bg-secondary); border: 1px solid var(--border-strong); border-radius: var(--radius-sm); outline: none;">
                <option value="1m">1m</option>
                <option value="5m">5m</option>
                <option value="15m">15m</option>
                <option value="1h" selected>1H</option>
                <option value="4h">4H</option>
                <option value="1d">1D</option>
              </select>
            </div>
            
            <div class="g-divider-v" style="height: 18px; width: 1px; background: var(--border-strong); margin: 0 4px;"></div>
            
            <div class="btn-group-toggle" style="display: flex; gap: 6px; flex-wrap: wrap;">
              <button type="button" id="btn-toggle-sma" class="g-btn-pill-toggle">SMA</button>
              <button type="button" id="btn-toggle-rsi" class="g-btn-pill-toggle">RSI</button>
              <button type="button" id="btn-toggle-macd" class="g-btn-pill-toggle">MACD</button>
              <button type="button" id="btn-toggle-pivots" class="g-btn-pill-toggle">Pivots H/L</button>
              <button type="button" id="btn-toggle-forecast" class="g-btn-pill-toggle-ai">AI Forecast</button>
            </div>
          </div>

          <!-- AI Forecast sub-panel -->
          <div id="ai-forecast-panel" class="ai-forecast-panel collapsible-panel">
            <div class="forecast-settings-grid">
              <div class="forecast-setting-item">
                <label for="forecast-window">Input Window</label>
                <select id="forecast-window">
                  <option value="30">30 candles</option>
                  <option value="50" selected>50 candles (Standard)</option>
                  <option value="80">80 candles</option>
                  <option value="100">100 candles (Deep)</option>
                </select>
              </div>
              <div class="forecast-setting-item">
                <label for="forecast-horizon">Forecast Horizon</label>
                <select id="forecast-horizon">
                  <option value="3">3 candles</option>
                  <option value="5" selected>5 candles (Default)</option>
                  <option value="10">10 candles</option>
                  <option value="15">15 candles (Max)</option>
                </select>
              </div>
              <button type="button" id="btn-generate-forecast" class="forecast-btn">
                <span>Run Predictive Model</span>
              </button>
            </div>
            <div id="forecast-loader" class="forecast-loader" style="display:none;">
              <div class="loader-spinner"></div>
              <span id="forecast-loader-text">Ingesting historical price sequence…</span>
            </div>
            <div id="forecast-summary-widget" class="forecast-summary-widget" style="display:none;">
              <div class="widget-header">
                <span class="widget-title">Predictive Price Forecast</span>
                <span id="forecast-cache-badge" class="forecast-badge-cache">LIVE</span>
              </div>
              <div class="widget-metrics">
                <div class="widget-metric-card"><div class="metric-label">Forecast Direction</div><div id="widget-direction" class="metric-value direction-neutral">NEUTRAL</div></div>
                <div class="widget-metric-card"><div class="metric-label">Expected Move</div><div id="widget-move" class="metric-value">—</div></div>
                <div class="widget-metric-card"><div class="metric-label">Model Confidence</div><div id="widget-confidence" class="metric-value">—</div></div>
                <div class="widget-metric-card"><div class="metric-label">Expected Volatility</div><div id="widget-volatility" class="metric-value">—</div></div>
              </div>
              <div class="widget-footer">
                <span>Generated at: <span id="widget-timestamp">--</span></span>
                <span>Model: Google TimesFM (1.0-Decoder)</span>
              </div>
            </div>
          </div>

          <!-- Accuracy sub-panel -->
          <div id="ai-accuracy-panel" class="ai-forecast-panel collapsible-panel" style="border-color:rgba(171,71,188,0.2);">
            <div class="technical-selector-head" style="margin-bottom:var(--space-sm); display:flex; justify-content:space-between; align-items:center;">
              <span class="technical-selector-label" style="color:var(--purple);">Prediction Accuracy History</span>
              <span class="technical-selector-hint">Compare previous forecasts with actual price movements.</span>
            </div>
            <div id="accuracy-loading" class="chart-hint" style="padding:10px 0;">Loading accuracy data…</div>
            <div id="accuracy-table-container" style="display:none; max-height:250px; overflow-y:auto;">
              <table class="data-table" style="font-size:0.8rem; width:100%;">
                <thead><tr><th>Forecast ID</th><th>Asset</th><th>Interval</th><th>Direction (Pred vs Act)</th><th>Expected %</th><th>Error (MAE)</th><th>Status</th></tr></thead>
                <tbody id="accuracy-table-body"></tbody>
              </table>
            </div>
          </div>

          <!-- Chart canvases -->
          <div id="technical-chart-container" class="chart-container" style="display:none;">
            <h3 id="technical-chart-title">Select an asset to load its chart</h3>
            <canvas id="technicalChart"></canvas>
          </div>
          <div id="rsi-chart-container" class="chart-container collapsible-panel">
            <h3>RSI (14)</h3>
            <canvas id="rsiChart" height="100"></canvas>
          </div>
          <div id="macd-chart-container" class="chart-container collapsible-panel">
            <h3>MACD (12, 26, 9)</h3>
            <canvas id="macdChart" height="100"></canvas>
          </div>
          <p id="chart-loading-hint" class="chart-hint">Select an asset above to load its technical chart.</p>

        </div>
      </div>
    </section>

    <!-- ══════════════════════════════════
         RIGHT: Order Form + Wallet
    ══════════════════════════════════ -->
    <section class="trade-order-panel" aria-label="Place Order">
      <div class="g-double-bezel">
        <div class="g-double-bezel-inner">

          <div class="dash-card-header" style="margin-bottom:var(--space-lg);">
            <span class="g-eyebrow" style="text-align:left; margin-bottom:0;">
              <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle;margin-right:4px;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
              Execution Node
            </span>
          </div>

          <?php if (isset($_SESSION['error'])): ?>
            <div style="background:var(--red-bg);color:var(--red);padding:var(--space-sm) var(--space-md);border-radius:var(--radius-sm);margin-bottom:var(--space-md);text-align:center;font-size:0.85rem;font-weight:600;">
              <?= htmlspecialchars($_SESSION['error']) ?>
            </div>
            <?php unset($_SESSION['error']); ?>
          <?php endif; ?>
          <?php if (isset($_SESSION['flash'])): ?>
            <div style="background:var(--green-bg);color:var(--green);padding:var(--space-sm) var(--space-md);border-radius:var(--radius-sm);margin-bottom:var(--space-md);text-align:center;font-size:0.85rem;font-weight:600;">
              <?= htmlspecialchars($_SESSION['flash']) ?>
            </div>
            <?php unset($_SESSION['flash']); ?>
          <?php endif; ?>

          <div id="form-message" aria-live="polite" style="display:none;padding:var(--space-sm) var(--space-md);border-radius:var(--radius-sm);margin-bottom:var(--space-md);text-align:center;font-size:0.85rem;font-weight:600;"></div>

          <form method="POST" action="#" id="buy-sell-form" class="trade-form">

            <!-- Buy / Sell Toggle -->
            <div class="type-toggle">
              <input type="radio" name="type" value="buy" id="type-buy" checked>
              <label for="type-buy" class="toggle-label-buy">Buy</label>
              <input type="radio" name="type" value="sell" id="type-sell">
              <label for="type-sell" class="toggle-label-sell">Sell</label>
            </div>

            <!-- Order Type Toggle: Market vs Limit -->
            <div class="type-toggle" style="margin-top: var(--space-sm);">
              <input type="radio" name="order_type_ui" value="market" id="ot-market" checked>
              <label for="ot-market" style="border-radius: var(--radius-sm) 0 0 var(--radius-sm); background: var(--bg-tertiary); color: var(--text-secondary); font-size: 0.78rem; padding: 6px 16px; cursor: pointer; border: 1px solid var(--border-strong); transition: all 0.2s;">Market</label>
              <input type="radio" name="order_type_ui" value="limit" id="ot-limit">
              <label for="ot-limit" style="border-radius: 0 var(--radius-sm) var(--radius-sm) 0; background: var(--bg-tertiary); color: var(--text-secondary); font-size: 0.78rem; padding: 6px 16px; cursor: pointer; border: 1px solid var(--border-strong); border-left: none; transition: all 0.2s;">Limit</label>
            </div>

            <!-- Integrated MetaMask Status Card -->
            <div id="form-metamask-status" style="margin-bottom: var(--space-md); padding: var(--space-sm) var(--space-md); background: var(--bg-secondary); border: 1.5px solid var(--border-strong); border-radius: var(--radius-md);">
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 0.75rem; font-weight: 800; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px;">🦊 MetaMask Wallet</span>
                <span id="form-connection-badge" style="font-size: 0.75rem; color: var(--red); font-weight: 700;">Disconnected</span>
              </div>
              <div id="form-wallet-details" style="display: none; margin-top: 8px; border-top: 1px dashed var(--border-strong); padding-top: 6px;">
                <div style="font-family: 'JetBrains Mono', monospace; font-size: 0.85rem; color: var(--text-primary); font-weight: 600;" id="eth-address-display">—</div>
                <div style="margin-top: 4px; font-weight: 700; color: var(--accent); font-size: 0.95rem; font-family: 'JetBrains Mono', monospace;" id="eth-balance-display">Loading balance…</div>
              </div>
              <button type="button" id="btn-connect-metamask" class="g-btn-pill g-btn-pill-primary" style="width: 100%; margin-top: 8px; font-size: 0.75rem; height: 32px; padding: 0;">
                Connect MetaMask
              </button>
            </div>

            <div class="form-group">
              <label for="coin">Asset / Commodity</label>
              <div class="select-with-icon-wrapper" style="display: flex; align-items: center; gap: var(--space-sm);">
                <div id="selected-coin-icon" class="selected-asset-icon-preview"></div>
                <select name="coin" id="coin" class="g-select" style="flex: 1;">
                  <?php foreach ($activeCoins as $coinInfo): ?>
                    <option value="<?= htmlspecialchars($coinInfo['base_asset']) ?>"><?= htmlspecialchars($coinInfo['base_asset']) ?> — <?= htmlspecialchars($coinInfo['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="form-group">
              <label for="amount">Amount (troy oz)</label>
              <input type="number" step="0.0001" min="0.0001" name="amount" id="amount" required autocomplete="off" class="g-input" placeholder="0.0001">
            </div>

            <!-- Limit Price Field — shown only when Limit Order is selected -->
            <div class="form-group" id="limit-price-group" style="display: none;">
              <label for="limit-price" style="display: flex; align-items: center; gap: 6px;">
                Limit Price (USDT)
                <span style="font-size: 0.72rem; font-weight: 600; color: var(--accent); background: rgba(139,92,246,0.12); padding: 2px 8px; border-radius: 20px;">ON-CHAIN</span>
              </label>
              <input type="number" step="0.01" min="0.01" id="limit-price" autocomplete="off" class="g-input" placeholder="e.g. 2000.00">
              <p style="font-size: 0.73rem; color: var(--text-muted); margin-top: 4px; line-height: 1.5;">
                Your ETH will be locked in the smart contract until this order is matched or cancelled.
              </p>
            </div>

            <div class="form-group">
              <span class="form-label">Estimated Total</span>
              <div class="total-display" id="totalDisplay" style="font-family: 'JetBrains Mono', monospace; line-height: 1.4; font-size: 1.1rem; font-weight: 700; color: var(--text-primary);">
                $0.00 <span style="font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); display: block;">≈ 0.0000 ETH</span>
              </div>
            </div>

            <button type="submit" class="g-btn-pill g-btn-pill-primary submit-btn" style="width:100%;">
              Submit Order
              <span class="g-btn-icon-circle">
                <svg aria-hidden="true" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
              </span>
            </button>

          </form>

        </div>
      </div>
    </section>

  </div><!-- /trade-split-layout -->

  <!-- Bottom Layout: Recent Transactions + Wallet Balances side by side -->
  <div class="trade-bottom-layout">
    
    <!-- Left: Recent Transactions -->
    <div class="trade-transactions-wrap">
      <div class="g-double-bezel">
        <div class="g-double-bezel-inner">
          <div class="dash-card-header">
            <span class="g-eyebrow" style="text-align:left; margin-bottom:0;">Recent Transactions</span>
          </div>
          <p class="dash-card-subtitle" style="margin-bottom: var(--space-md); text-align:left;">Most recent fills, ordered from newest to oldest.</p>
          <div class="dash-table-wrap">
            <table class="data-table">
              <thead>
                <tr><th>Type</th><th>Asset</th><th>Amount (oz)</th><th>Price</th><th>Total</th><th>Date</th></tr>
              </thead>
              <tbody id="recentTransactionsTableBody">
                <?php if ($transactions->num_rows === 0): ?>
                  <tr><td colspan="6" class="dash-empty-row" style="text-align:center; padding:var(--space-md); color:var(--text-muted);">No transactions yet</td></tr>
                <?php endif; ?>
                <?php while ($tx = $transactions->fetch_assoc()): ?>
                  <tr>
                    <td>
                      <span class="g-badge <?= strtoupper($tx['type']) === 'BUY' ? 'g-badge-green' : 'g-badge-red' ?>">
                        <?= htmlspecialchars($tx['type']) ?>
                      </span>
                    </td>
                    <td><strong><?= htmlspecialchars($tx['coin']) ?></strong></td>
                    <td><?= number_format((float)$tx['amount'], 4) ?></td>
                    <td>$<?= number_format((float)$tx['price'], 2) ?></td>
                    <td>$<?= number_format((float)$tx['total'], 2) ?></td>
                    <td class="dash-date-cell"><?= $tx['created_at'] ?></td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Right: Wallet Balances -->
    <div class="trade-wallet-wrap">
      <div class="g-double-bezel" style="height: 100%;">
        <div class="g-double-bezel-inner" style="height: 100%; display: flex; flex-direction: column;">
          <div class="dash-card-header">
            <span class="g-eyebrow" style="text-align:left; margin-bottom:0;">Wallet Balances</span>
          </div>
          <p class="dash-card-subtitle" style="margin-bottom: var(--space-md); text-align:left;">Your live available and reserved assets context.</p>
          <div id="walletBalances" class="wallets-grid" style="flex: 1;"></div>
        </div>
      </div>
    </div>

  </div>



</main>

<footer class="g-footer">
  <p>&copy; 2026 Aether — Engineered for High Performance</p>
</footer>
</div><!-- /g-page -->

<script src="global.js?v=<?php echo filemtime('global.js'); ?>" defer></script>
<script src="engine.js?v=<?php echo filemtime('engine.js'); ?>" defer></script>
<script src="dashboard.js?v=<?php echo filemtime('dashboard.js'); ?>" defer></script>

<?php
// ── Inject blockchain config into the page as a JS global ─────────────────
// The ABI is read from the compiled Truffle artefact after `truffle migrate`.
// If the file doesn't exist yet (before first deploy), we inject an empty ABI
// so the page loads without errors.
$abiPath = __DIR__ . '/blockchain/build/contracts/AetherTrade.json';
$contractABI = [];
if (file_exists($abiPath)) {
    $truffleArtifact = json_decode(file_get_contents($abiPath), true);
    $contractABI     = $truffleArtifact['abi'] ?? [];
}
?>
<script>
  // Blockchain configuration injected by PHP — do not edit manually.
  // CONTRACT_ADDRESS is set in config.php after running: truffle migrate
  window.AETHER_BLOCKCHAIN = {
    contractAddress: "<?= defined('CONTRACT_ADDRESS') ? htmlspecialchars(CONTRACT_ADDRESS) : '' ?>",
    contractABI:     <?= json_encode($contractABI) ?>
  };
</script>

<!-- Load MetaMask / Web3 trading logic -->
<script src="blockchain/web3_trade.js?v=<?php echo filemtime('blockchain/web3_trade.js'); ?>"></script>
<script>
  /* ── Trade Form Logic ── */
  document.addEventListener('DOMContentLoaded', () => {
    // Sync Toggle Buttons with Hidden Checkboxes
    ['sma', 'rsi', 'macd', 'pivots', 'forecast'].forEach(name => {
      const btn = document.getElementById('btn-toggle-' + name);
      const inputId = name === 'pivots' ? 'enable-ai' : (name === 'forecast' ? 'enable-forecast' : 'show-' + name);
      const input = document.getElementById(inputId);
      if (btn && input) {
        btn.addEventListener('click', () => {
          input.checked = !input.checked;
          btn.classList.toggle('active', input.checked);
          input.dispatchEvent(new Event('change'));
        });
        btn.classList.toggle('active', input.checked);
      }
    });

    const amtInput     = document.getElementById('amount');
    const totalDisplay = document.getElementById('totalDisplay');
    const coinSelect   = document.getElementById('coin');

    let cachedPrices = {};

    async function fetchPrices() {
      const coin = coinSelect.value;
      try {
        const coinRes = await fetch(`api/get_market_price.php?coin=${coin}`);
        const coinData = await coinRes.json();
        const ethRes = await fetch(`api/get_market_price.php?coin=ETH`);
        const ethData = await ethRes.json();

        cachedPrices[coin] = parseFloat(coinData.price) || 0;
        cachedPrices['ETH'] = parseFloat(ethData.price) || 3000;
        updateTotal();
      } catch (_) {}
    }

    function updateTotal() {
      const amt = parseFloat(amtInput.value) || 0;
      const coin = coinSelect.value;
      const coinPrice = cachedPrices[coin] || 0;
      const ethPrice = cachedPrices['ETH'] || 3000;

      const totalUsdt = amt * coinPrice;
      const totalEth = totalUsdt / ethPrice;

      totalDisplay.innerHTML = `$${totalUsdt.toFixed(2)} <span style="font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); display: block;">≈ ${totalEth.toFixed(4)} ETH</span>`;
      totalDisplay.classList.toggle('active', totalUsdt > 0);
    }

    function updateSelectedCoinIcon() {
      const iconContainer = document.getElementById('selected-coin-icon');
      if (iconContainer && window.getAssetIcon) {
        iconContainer.innerHTML = window.getAssetIcon(coinSelect.value);
      }
    }

    coinSelect.addEventListener('change', () => {
      fetchPrices();
      updateSelectedCoinIcon();
    });
    amtInput.addEventListener('input', updateTotal);
    
    // Initial fetch on load
    fetchPrices();
    updateSelectedCoinIcon();

    async function fetchWallets() {
      try {
        const res = await fetch('api/wallets.php');
        if (!res.ok) return;
        const data = await res.json();
        const container = document.getElementById('walletBalances');
        container.innerHTML = '';
        const items = Array.isArray(data?.balances)
          ? data.balances
          : data && data.wallet
            ? Object.entries(data.wallet).map(([asset, value]) => ({ 
                asset, 
                balance: value.balance, 
                reserved: value.reserved,
                price: value.price,
                available_value: value.available_value,
                reserved_value: value.reserved_value,
                avg_buy_price: value.avg_buy_price,
                pnl: value.pnl,
                pnl_percent: value.pnl_percent
              }))
            : [];
        if (items.length > 0) {
          items.forEach(b => {
            const balanceVal = Number(b.balance);
            const reservedVal = Number(b.reserved);

            // Hide minor assets that have a 0 balance to keep the wallet view clean
            if (b.asset !== 'USDT' && b.asset !== 'ETH' && balanceVal === 0 && reservedVal === 0) {
              return;
            }

            const el = document.createElement('div');
            el.className = 'mini-wallet-card';
            
            const price = b.price !== undefined ? Number(b.price) : 0;
            const availValuation = b.available_value !== undefined ? Number(b.available_value) : ((balanceVal - reservedVal) * price);
            const resValuation = b.reserved_value !== undefined ? Number(b.reserved_value) : (reservedVal * price);
            const avgBuyPrice = b.avg_buy_price !== undefined ? Number(b.avg_buy_price) : 0;
            const pnl = b.pnl !== undefined ? Number(b.pnl) : 0;
            const pnlPct = b.pnl_percent !== undefined ? Number(b.pnl_percent) : 0;

            let valuationRows = '';
            let pnlRow = '';
            if (b.asset !== 'USDT') {
              valuationRows = `
                <span class="wallet-lbl" style="color:var(--accent);">Val (USDT)</span>
                <span class="wallet-val" style="color:var(--accent);">≈ ${availValuation.toFixed(2)}</span>
              `;
              
              let pnlColor = 'var(--text-secondary)';
              let pnlSign = '';
              if (pnl > 0) {
                pnlColor = '#2ecc71';
                pnlSign = '+';
              } else if (pnl < 0) {
                pnlColor = '#e74c3c';
              }
              
              pnlRow = avgBuyPrice > 0 
                ? `
                  <span class="wallet-lbl">Avg Buy</span>
                  <span class="wallet-val">$${avgBuyPrice.toFixed(2)}</span>
                  <span class="wallet-lbl" style="color:${pnlColor};">PnL</span>
                  <span class="wallet-val" style="color:${pnlColor}; font-weight:700;">${pnlSign}$${pnl.toFixed(2)} (${pnlSign}${pnlPct.toFixed(2)}%)</span>
                `
                : `
                  <span class="wallet-lbl">Avg Buy</span>
                  <span class="wallet-val">—</span>
                  <span class="wallet-lbl">PnL</span>
                  <span class="wallet-val" style="color:var(--text-muted);">No purchases</span>
                `;
            }
            
            let reservedValuationRows = '';
            if (b.asset !== 'USDT') {
              reservedValuationRows = `
                <span class="wallet-lbl" style="color:var(--accent);">Val (USDT)</span>
                <span class="wallet-val" style="color:var(--accent);">≈ ${resValuation.toFixed(2)}</span>
              `;
            }

            el.innerHTML = `
              ${window.getAssetIcon ? window.getAssetIcon(b.asset) : ''}
              <div class="wallet-details">
                <strong>${b.asset}</strong>
                <div class="wallet-info-row">
                  <span class="wallet-lbl">Available</span>
                  <span class="wallet-val">${balanceVal.toFixed(4)}</span>
                  ${valuationRows}
                  <span class="wallet-lbl">Reserved</span>
                  <span class="wallet-val">${reservedVal.toFixed(4)}</span>
                  ${reservedValuationRows}
                  ${pnlRow}
                </div>
              </div>
            `;
            container.appendChild(el);
          });
        } else {
          container.innerHTML = '<div style="color:var(--text-muted);font-size:0.85rem;padding:var(--space-md) 0;">No wallet data available.</div>';
        }
      } catch (_) {}
    }

    async function fetchTransactions() {
      try {
        const res = await fetch('api/transactions.php');
        if (!res.ok) return;
        const data = await res.json();
        if (data.success && Array.isArray(data.transactions)) {
          const tbody = document.getElementById('recentTransactionsTableBody');
          if (!tbody) return;
          tbody.innerHTML = '';
          if (data.transactions.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="dash-empty-row" style="text-align:center; padding:var(--space-md); color:var(--text-muted);">No transactions yet</td></tr>';
            return;
          }
          data.transactions.forEach(tx => {
            const tr = document.createElement('tr');
            const isBuy = tx.type.toUpperCase() === 'BUY';
            const badgeClass = isBuy ? 'g-badge-green' : 'g-badge-red';
            const amt = Number(tx.amount).toFixed(4);
            const price = Number(tx.price).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const total = Number(tx.total).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            tr.innerHTML = `
              <td>
                <span class="g-badge ${badgeClass}">
                  ${tx.type}
                </span>
              </td>
              <td><strong>${tx.coin}</strong></td>
              <td>${amt}</td>
              <td>$${price}</td>
              <td>$${total}</td>
              <td class="dash-date-cell">${tx.created_at}</td>
            `;
            tbody.appendChild(tr);
          });
        }
      } catch (_) {}
    }

    // Expose functions globally so external web3_trade.js can trigger refreshes
    window.fetchWallets = fetchWallets;
    window.fetchTransactions = fetchTransactions;

    function showMessage(msg, isSuccess) {
      const msgBox = document.getElementById('form-message');
      msgBox.style.display = 'block';
      msgBox.textContent = msg;
      msgBox.style.background = isSuccess ? 'var(--green-bg)' : 'var(--red-bg)';
      msgBox.style.color      = isSuccess ? 'var(--green)'    : 'var(--red)';
      msgBox.style.border     = isSuccess ? '1px solid rgba(16,185,129,0.2)' : '1px solid rgba(239,68,68,0.2)';
      msgBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // ── Order Type Toggle: Show/Hide Limit Price Field ──────────────────────
    const otMarket = document.getElementById('ot-market');
    const otLimit  = document.getElementById('ot-limit');
    const limitPriceGroup = document.getElementById('limit-price-group');

    function updateOrderTypeUI() {
      const isLimit = otLimit && otLimit.checked;
      if (limitPriceGroup) limitPriceGroup.style.display = isLimit ? 'block' : 'none';
      // Update label styles to reflect active selection
      const marketLabel = document.querySelector('label[for="ot-market"]');
      const limitLabel  = document.querySelector('label[for="ot-limit"]');
      if (marketLabel) {
        marketLabel.style.background = isLimit ? 'var(--bg-tertiary)' : 'var(--accent)';
        marketLabel.style.color      = isLimit ? 'var(--text-secondary)' : '#fff';
      }
      if (limitLabel) {
        limitLabel.style.background = isLimit ? 'var(--accent)' : 'var(--bg-tertiary)';
        limitLabel.style.color      = isLimit ? '#fff' : 'var(--text-secondary)';
      }
    }

    if (otMarket) otMarket.addEventListener('change', updateOrderTypeUI);
    if (otLimit)  otLimit.addEventListener('change', updateOrderTypeUI);
    updateOrderTypeUI(); // Initialize on page load

    document.getElementById('buy-sell-form').addEventListener('submit', async function(ev) {
      ev.preventDefault();
      document.getElementById('form-message').style.display = 'none';
      const btn = this.querySelector('.submit-btn');
      btn.disabled = true;
      const origHTML = btn.innerHTML;
      btn.textContent = 'Processing…';

      const side      = document.querySelector('input[name="type"]:checked').id === 'type-buy' ? 'BUY' : 'SELL';
      const coin      = coinSelect.value;
      const qty       = parseFloat(amtInput.value) || 0;
      const isLimit   = otLimit && otLimit.checked;
      const limitPrice = isLimit ? (parseFloat(document.getElementById('limit-price')?.value) || 0) : 0;

      if (!window.ethereum || !connectedAccount) {
        showMessage('Please connect MetaMask first!', false);
        btn.disabled = false;
        btn.innerHTML = origHTML;
        return;
      }

      // ── LIMIT ORDER FLOW ──────────────────────────────────────────────────
      if (isLimit) {
        if (limitPrice <= 0) {
          showMessage('Please enter a valid limit price.', false);
          btn.disabled = false;
          btn.innerHTML = origHTML;
          return;
        }

        try {
          btn.textContent = 'Creating limit order…';

          // Step A: POST to submit_order.php to create the order row in MySQL first
          // We need the MySQL order ID before we can sign it on-chain
          const formPayload = new URLSearchParams({
            type:       side,
            coin:       coin,
            amount:     qty,
            price:      limitPrice,
            order_type: 'limit',
            eth_address: connectedAccount
          });

          const orderResp = await fetch('orders/submit_order.php', {
            method:      'POST',
            credentials: 'same-origin',
            headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:        formPayload.toString()
          });

          const orderData = await orderResp.json().catch(() => null);

          if (!orderData || !orderData.success || !orderData.order_id) {
            showMessage('Failed to create limit order: ' + (orderData?.error || 'Server error'), false);
            btn.disabled  = false;
            btn.innerHTML = origHTML;
            return;
          }

          // Step B: Sign + escrow on-chain using the MySQL order ID
          btn.textContent = 'Signing on MetaMask…';
          await placeLimitOrderOnChain(coin, qty, limitPrice, side, orderData.order_id);

          amtInput.value = '';
          document.getElementById('limit-price').value = '';
          updateTotal();

        } catch (err) {
          showMessage('Limit order failed: ' + err.message, false);
        }

      // ── MARKET ORDER FLOW (unchanged) ─────────────────────────────────────
      } else if (side === 'BUY') {
        try {
          btn.textContent = 'Fetching prices…';
          const coinRes = await fetch(`api/get_market_price.php?coin=${coin}`);
          const coinData = await coinRes.json();
          const ethRes = await fetch(`api/get_market_price.php?coin=ETH`);
          const ethData = await ethRes.json();

          const coinPrice = parseFloat(coinData.price) || 0;
          const ethPrice  = parseFloat(ethData.price)  || 3000;
          const ethAmt    = (coinPrice * qty) / ethPrice;

          btn.textContent = 'Confirming in MetaMask…';
          await buyWithETH(coin, ethAmt, qty);

          amtInput.value = '';
          updateTotal();
        } catch (err) {
          showMessage('Failed to complete on-chain buy: ' + err.message, false);
        }
      } else {
        try {
          btn.textContent = 'Settling on-chain…';
          await sellWithETH(coin, qty);
          amtInput.value = '';
          updateTotal();
        } catch (err) {
          showMessage('Failed to complete on-chain sell: ' + err.message, false);
        }
      }

      btn.disabled  = false;
      btn.innerHTML = origHTML;
    });

    fetchWallets();
    fetchTransactions();
  });
</script>

</body>
</html>
