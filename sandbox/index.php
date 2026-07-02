<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aether Sandbox — Strategy Laboratory</title>
    <meta name="description" content="Blind backtesting simulator and algorithmic strategy optimizer. Use real Binance historical data to test your trading skills and optimize strategies.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="sandbox.css">
    <script src="vendor/chart.3.9.1.min.js?v=<?php echo filemtime(__DIR__ . '/vendor/chart.3.9.1.min.js'); ?>"></script>
    <script src="vendor/chartjs-plugin-annotation.2.2.1.min.js?v=<?php echo filemtime(__DIR__ . '/vendor/chartjs-plugin-annotation.2.2.1.min.js'); ?>"></script>
</head>
<body>

    <header class="sb-header" role="banner">
        <div style="display: flex; gap: 8px; align-items: center;">
            <a href="../dashboard.php" class="sb-back-btn" aria-label="Back to Aether Trading Platform">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M19 12H5M12 5l-7 7 7 7"/>
                </svg>
                Back to Aether
            </a>
            <button id="theme-toggle" class="sb-back-btn" aria-label="Toggle color theme" aria-pressed="false" title="Toggle theme" style="cursor: pointer; padding: 6px 10px;">
                <span class="theme-icon" aria-hidden="true"></span>
                <span class="sr-only">Toggle color theme</span>
            </button>
        </div>

        <div class="sb-brand">
            <div class="sb-brand-icon" aria-hidden="true">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                </svg>
            </div>
            <div>
                <div class="sb-eyebrow">STRATEGY LABORATORY</div>
                <h1 class="sb-brand-name" style="font-size:inherit;margin:0;line-height:inherit;">Aether Sandbox</h1>
            </div>
        </div>

        <div class="sb-session-stats" aria-label="Session performance">
            <div class="sb-stat-item">
                <span class="sb-stat-label">Trades</span>
                <span class="sb-stat-value" id="stat-trades">0</span>
            </div>
            <div class="sb-stat-divider" aria-hidden="true"></div>
            <div class="sb-stat-item">
                <span class="sb-stat-label">Win Rate</span>
                <span class="sb-stat-value" id="stat-winrate">—</span>
            </div>
            <div class="sb-stat-divider" aria-hidden="true"></div>
            <div class="sb-stat-item">
                <span class="sb-stat-label">Net P&amp;L</span>
                <span class="sb-stat-value" id="stat-pnl">$0.00</span>
            </div>
            <button class="sb-reset-btn" id="btn-reset-stats" title="Reset session statistics">Reset</button>
        </div>
    </header>

    <!-- ═══ MODE TOGGLE ══════════════════════════════════════════════ -->
    <div class="sb-mode-bar" role="tablist" aria-label="Sandbox mode">
        <div class="sb-mode-toggle">
            <button class="sb-mode-btn active" data-mode="manual" id="btn-mode-manual"
                    role="tab" aria-selected="true" aria-controls="manual-controls">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
                </svg>
                Manual Trainer
            </button>
            <button class="sb-mode-btn" data-mode="optimizer" id="btn-mode-optimizer"
                    role="tab" aria-selected="false" aria-controls="optimizer-controls">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/>
                </svg>
                Algorithm Optimizer
            </button>
            <button class="sb-mode-btn" data-mode="rules" id="btn-mode-rules"
                    role="tab" aria-selected="false" aria-controls="rules-controls">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>
                </svg>
                Custom Rules
            </button>
        </div>
        <p class="sb-mode-desc" id="mode-description">
            Load real historical data, study the chart, place a simulated trade, and let the market reveal if your call was correct.
        </p>
    </div>

    <!-- ═══ MAIN LAYOUT ══════════════════════════════════════════════ -->
    <main class="sb-main" id="main-content">

        <!-- ─── LEFT: Control Panel ─────────────────────────────────── -->
        <aside class="sb-sidebar" aria-label="Configuration panel">

            <!-- Market Data Card -->
            <div class="sb-card">
                <div class="sb-card-eyebrow">MARKET DATA</div>
                <h2 class="sb-card-title">Asset &amp; Timeframe</h2>

                <div class="sb-field">
                    <label class="sb-label" for="ctrl-symbol">Asset</label>
                    <select class="sb-select" id="ctrl-symbol">
                        <option value="BTCUSDT">Bitcoin (BTC/USDT)</option>
                        <option value="ETHUSDT">Ethereum (ETH/USDT)</option>
                        <option value="PAXGUSDT">Gold — PAXG/USDT</option>
                        <option value="SOLUSDT">Solana (SOL/USDT)</option>
                        <option value="BNBUSDT">BNB/USDT</option>
                        <option value="XRPUSDT">XRP/USDT</option>
                        <option value="ADAUSDT">Cardano (ADA/USDT)</option>
                        <option value="AVAXUSDT">Avalanche (AVAX/USDT)</option>
                        <option value="DOTUSDT">Polkadot (DOT/USDT)</option>
                        <option value="LINKUSDT">Chainlink (LINK/USDT)</option>
                    </select>
                </div>

                <div class="sb-field">
                    <span class="sb-label" id="timeframe-label">Timeframe</span>
                    <div class="sb-pill-row" id="ctrl-interval" role="group" aria-labelledby="timeframe-label">
                        <button class="sb-pill" data-val="5m" aria-pressed="false">5m</button>
                        <button class="sb-pill" data-val="15m" aria-pressed="false">15m</button>
                        <button class="sb-pill active" data-val="1h" aria-pressed="true">1h</button>
                        <button class="sb-pill" data-val="4h" aria-pressed="false">4h</button>
                        <button class="sb-pill" data-val="1d" aria-pressed="false">1d</button>
                    </div>
                </div>

                <div class="sb-field">
                    <label class="sb-label" for="ctrl-date">Historical Start Date</label>
                    <input type="date" class="sb-input" id="ctrl-date">
                    <span class="sb-hint">Manual mode: 200 visible candles · Optimizer / Rules: all 260 candles</span>
                </div>

                <button class="sb-btn-primary" id="btn-load">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                    Load Chart
                </button>
                <span id="load-spinner" style="display:none; margin-left:8px; vertical-align:middle;">
                    <svg width="18" height="18" viewBox="0 0 50 50" xmlns="http://www.w3.org/2000/svg" fill="none">
                        <circle cx="25" cy="25" r="20" stroke="rgba(212,175,55,0.2)" stroke-width="5"></circle>
                        <path d="M45 25A20 20 0 1 1 25 5" stroke="#d4af37" stroke-width="5" stroke-linecap="round"></path>
                    </svg>
                </span>
                <button id="btn-retry-load" class="sb-btn-secondary" style="display:none; margin-left:8px;">Retry</button>
            </div>

            <!-- Mode 1: Manual Trade Controls -->
            <div class="sb-card" id="manual-controls">
                <div class="sb-card-eyebrow">TRADE SETUP</div>
                <h2 class="sb-card-title">Execute Position</h2>
                <p class="sb-card-hint">Chart hides the next 60 candles. Set your levels, execute, then watch the market reveal.</p>

                <div class="sb-direction-toggle" role="group" aria-label="Trade direction">
                    <button class="sb-dir-btn active" id="btn-long" aria-pressed="true">Long / Buy</button>
                    <button class="sb-dir-btn" id="btn-short" aria-pressed="false">Short / Sell</button>
                </div>

                <div class="sb-field">
                    <label class="sb-label" for="ctrl-capital">Capital (USD)</label>
                    <input type="number" class="sb-input" id="ctrl-capital" value="1000" min="100" max="10000000" step="100">
                </div>

                <div class="sb-field">
                    <label class="sb-label" for="ctrl-leverage">
                        Leverage: <span id="leverage-display" class="sb-accent-text">5x</span>
                    </label>
                    <input type="range" class="sb-range" id="ctrl-leverage" min="1" max="20" value="5">
                    <div class="sb-range-labels">
                        <span>1x</span><span>10x</span><span>20x</span>
                    </div>
                </div>

                <div class="sb-tp-sl-group">
                    <div class="sb-field">
                        <label class="sb-label" for="ctrl-tp">
                            <span class="sb-tp-dot" aria-hidden="true"></span> Take Profit ($)
                        </label>
                        <input type="number" class="sb-input" id="ctrl-tp" step="0.01" placeholder="Drag line or enter price… e.g. 50000.00">
                    </div>
                    <div class="sb-field">
                        <label class="sb-label" for="ctrl-sl">
                            <span class="sb-sl-dot" aria-hidden="true"></span> Stop Loss ($)
                        </label>
                        <input type="number" class="sb-input" id="ctrl-sl" step="0.01" placeholder="Drag line or enter price… e.g. 48000.00">
                    </div>
                </div>
                <p class="sb-hint sb-drag-hint">Drag the green/red lines on the chart to set levels</p>

                <div class="sb-risk-preview" id="risk-preview" aria-live="polite"></div>

                <button class="sb-btn-execute" id="btn-execute" disabled>Execute Trade</button>
            </div>

            <!-- Mode 2: Optimizer Controls -->
            <div class="sb-card sb-hidden" id="optimizer-controls">
                <div class="sb-card-eyebrow">ALGORITHM ENGINE</div>
                <h2 class="sb-card-title">Strategy Optimizer</h2>
                <p class="sb-card-hint">The grid-search engine runs dozens of backtests across parameter permutations and ranks them by risk-adjusted performance.</p>

                <div class="sb-field">
                    <label class="sb-label" for="ctrl-strategy">Trading Strategy</label>
                    <select class="sb-select" id="ctrl-strategy">
                        <option value="ema_cross">EMA Crossover</option>
                        <option value="rsi_reversion">RSI Mean Reversion</option>
                        <option value="bb_breakout">Bollinger Band Breakout</option>
                        <option value="macd_signal">MACD Signal Cross</option>
                    </select>
                </div>

                <div class="sb-strategy-info" id="strategy-info">
                    <div class="sb-strategy-desc" id="strategy-desc"></div>
                    <div class="sb-strategy-grid-size" id="strategy-grid-size"></div>
                </div>

                <div class="sb-field">
                    <label class="sb-label" for="ctrl-score-metric">Ranking Metric</label>
                    <select class="sb-select" id="ctrl-score-metric">
                        <option value="sharpe">Sharpe Ratio (risk-adjusted)</option>
                        <option value="return">Net Return %</option>
                        <option value="calmar">Calmar Ratio (return/drawdown)</option>
                    </select>
                </div>

                <button class="sb-btn-primary" id="btn-optimize" disabled>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/>
                    </svg>
                    Run Optimization
                </button>
                <div id="opt-controls" style="margin-top:10px; display:none; gap:8px; align-items:center;">
                    <div id="opt-progress"
                         role="progressbar"
                         aria-label="Optimization progress"
                         aria-valuenow="0"
                         aria-valuemin="0"
                         aria-valuemax="100"
                         style="flex:1; height:8px; background:rgba(255,255,255,0.06); border-radius:4px; overflow:hidden;">
                        <div id="opt-progress-bar" style="height:100%; width:0%; background:linear-gradient(90deg,#d4af37,#e5c158);"></div>
                    </div>
                    <button class="sb-btn-secondary" id="btn-opt-cancel">Cancel</button>
                </div>
            </div>

            <!-- Mode 3: Custom Rule Builder Controls -->
            <div class="sb-card sb-hidden" id="rules-controls">
                <div class="sb-card-eyebrow">STRATEGY DESIGNER</div>
                <h2 class="sb-card-title">Custom Strategy</h2>
                <p class="sb-card-hint">Design custom entry and exit rules using indicator conditions.</p>

                <!-- Buy Rules Section -->
                <div class="rules-section-header">
                    <span class="sb-label">BUY CONDITIONS (AND)</span>
                    <button type="button" class="sb-add-rule-btn" id="btn-add-buy-rule">+ Add</button>
                </div>
                <div class="rules-container" id="buy-rules-list">
                    <!-- Rule rows will be injected here -->
                </div>

                <!-- Sell Rules Section -->
                <div class="rules-section-header" style="margin-top: 10px;">
                    <span class="sb-label">SELL CONDITIONS (AND)</span>
                    <button type="button" class="sb-add-rule-btn" id="btn-add-sell-rule" style="display: none;">+ Add</button>
                </div>
                <div class="sb-toggle-wrapper" style="margin-top: 4px; margin-bottom: 8px; display: flex; align-items: center;">
                    <label class="sb-toggle-label" for="rules-opposite-exit" style="display: flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 600; cursor: pointer; color: var(--text-secondary);">
                        <input type="checkbox" id="rules-opposite-exit" checked style="accent-color: var(--accent); cursor: pointer;">
                        <span class="sb-toggle-text">Exit when Buy rules are false</span>
                    </label>
                </div>
                <div class="rules-container" id="sell-rules-list" style="display: none;">
                    <!-- Sell rule rows will be injected here -->
                </div>

                <!-- Trade Settings (Capital, Leverage, TP, SL) -->
                <div class="sb-field" style="margin-top: 10px;">
                    <label class="sb-label" for="rules-capital">Capital (USD)</label>
                    <input type="number" class="sb-input" id="rules-capital" value="1000" min="100" step="100">
                </div>
                <div class="sb-field">
                    <label class="sb-label" for="rules-leverage">
                        Leverage: <span id="rules-leverage-display" class="sb-accent-text">5x</span>
                    </label>
                    <input type="range" class="sb-range" id="rules-leverage" min="1" max="20" value="5">
                    <div class="sb-range-labels">
                        <span>1x</span><span>10x</span><span>20x</span>
                    </div>
                </div>
                
                <div class="sb-tp-sl-group">
                    <div class="sb-field">
                        <label class="sb-label" for="rules-tp">
                            <span class="sb-tp-dot"></span> Take Profit (%)
                        </label>
                        <input type="number" class="sb-input" id="rules-tp" placeholder="Optional… e.g. 5.0">
                    </div>
                    <div class="sb-field">
                        <label class="sb-label" for="rules-sl">
                            <span class="sb-sl-dot"></span> Stop Loss (%)
                        </label>
                        <input type="number" class="sb-input" id="rules-sl" placeholder="Optional… e.g. 2.5">
                    </div>
                </div>

                <button class="sb-btn-primary" id="btn-run-rules" disabled style="margin-top: 10px;">
                    Backtest Strategy
                </button>
            </div>

        </aside>

        <!-- ─── RIGHT: Chart Workspace ────────────────────────────────── -->
        <section class="sb-chart-area" aria-label="Chart workspace">
            <div class="sb-chart-card">

                <div class="sb-chart-header">
                    <div class="sb-chart-meta" id="chart-symbol-display">Select asset and load chart to begin</div>
                    <div class="sb-chart-overlay-toggles">
                        <label class="sb-toggle-label" for="tog-ema">
                            <input type="checkbox" id="tog-ema" checked>
                            <span class="sb-toggle-text sb-ema-color">EMA 20/50</span>
                        </label>
                        <label class="sb-toggle-label" for="tog-bb">
                            <input type="checkbox" id="tog-bb">
                            <span class="sb-toggle-text sb-bb-color">Bollinger Bands</span>
                        </label>
                    </div>
                </div>

                <!-- Main Candlestick Canvas -->
                <div class="sb-canvas-wrapper" id="candle-wrapper">
                    <canvas id="candle-chart" aria-label="Candlestick price chart"></canvas>
                    <div class="sb-placeholder" id="chart-placeholder" aria-live="polite">
                        <div class="sb-placeholder-icon" aria-hidden="true">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                            </svg>
                        </div>
                        <p>Select an asset, set a date, and click <strong>Load Chart</strong></p>
                    </div>
                    <div class="sb-live-pnl" id="live-pnl" aria-live="polite">
                        Live P&amp;L: <span id="pnl-value">$0.00</span>
                    </div>
                </div>

                <!-- RSI Sub-Chart -->
                <div class="sb-sub-header">
                    <span class="sb-sub-label">RSI (14)</span>
                    <span class="sb-sub-hint">Overbought &gt;70 · Oversold &lt;30</span>
                </div>
                <div class="sb-sub-canvas-wrapper" id="rsi-wrapper">
                    <canvas id="rsi-chart" aria-label="RSI indicator panel"></canvas>
                </div>

                <!-- MACD Sub-Chart -->
                <div class="sb-sub-header">
                    <span class="sb-sub-label">MACD (12 / 26 / 9)</span>
                    <span class="sb-sub-hint">
                        <span class="sb-macd-legend-gold">MACD</span> /
                        <span class="sb-macd-legend-blue">Signal</span>
                    </span>
                </div>
                <div class="sb-sub-canvas-wrapper" id="macd-wrapper">
                    <canvas id="macd-chart" aria-label="MACD indicator panel"></canvas>
                </div>

            </div>
        </section>

    </main>

    <!-- ═══ RESULTS PANEL ══════════════════════════════════════════════ -->
    <section class="sb-results-section" id="results-panel" aria-live="polite" aria-label="Trade results"></section>

    <!-- ═══ SCRIPTS ════════════════════════════════════════════════════ -->
    <script src="../global.js?v=<?php echo filemtime(__DIR__ . '/../global.js'); ?>" defer></script>
    <script src="sandbox.js"></script>

</body>
</html>
