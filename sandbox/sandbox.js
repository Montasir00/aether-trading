/* =============================================================
   AETHER SANDBOX — Pure Client-Side Algorithmic Engine
   ============================================================= */

document.addEventListener('DOMContentLoaded', () => {

    const readCssVar = (name, fallback = '') => {
        const value = getComputedStyle(document.body).getPropertyValue(name).trim();
        return value || fallback;
    };
    const getChartColors = () => ({
        grid: readCssVar('--chart-grid', 'rgba(212, 175, 55, 0.03)'),
        text: readCssVar('--text-secondary', '#8da094')
    });

    window.addEventListener('theme-changed', () => {
        const colors = getChartColors();
        [priceChart, rsiChart, macdChart].forEach(chart => {
            if (chart) {
                if (chart.options.scales.x) {
                    chart.options.scales.x.grid.color = colors.grid;
                    if (chart.options.scales.x.ticks && chart.options.scales.x.ticks.display !== false) {
                        chart.options.scales.x.ticks.color = colors.text;
                    }
                }
                if (chart.options.scales.y) {
                    chart.options.scales.y.grid.color = colors.grid;
                    if (chart.options.scales.y.ticks && chart.options.scales.y.ticks.display !== false) {
                        chart.options.scales.y.ticks.color = colors.text;
                    }
                }
                chart.update();
            }
        });
    });

    if (typeof window.Chart === 'undefined') {
        console.error('Chart.js failed to load. Check sandbox/vendor/chart.3.9.1.min.js');
        document.getElementById('results-panel').textContent = 'Chart library failed to load. Refresh the page.';
        return;
    }

    // Mode toggles
    const btnModeManual = document.getElementById('btn-mode-manual');
    const btnModeOptimizer = document.getElementById('btn-mode-optimizer');
    const manualControls = document.getElementById('manual-controls');
    const optimizerControls = document.getElementById('optimizer-controls');
    const modeDescription = document.getElementById('mode-description');
    
    // Inputs
    const ctrlSymbol = document.getElementById('ctrl-symbol');
    const ctrlInterval = document.getElementById('ctrl-interval');
    const ctrlDate = document.getElementById('ctrl-date');
    const btnLoad = document.getElementById('btn-load');
    const loadSpinner = document.getElementById('load-spinner');
    const btnRetryLoad = document.getElementById('btn-retry-load');
    
    // Manual inputs
    const btnLong = document.getElementById('btn-long');
    const btnShort = document.getElementById('btn-short');
    const ctrlCapital = document.getElementById('ctrl-capital');
    const ctrlLeverage = document.getElementById('ctrl-leverage');
    const leverageDisplay = document.getElementById('leverage-display');
    const ctrlTp = document.getElementById('ctrl-tp');
    const ctrlSl = document.getElementById('ctrl-sl');
    const btnExecute = document.getElementById('btn-execute');
    const riskPreview = document.getElementById('risk-preview');
    const livePnl = document.getElementById('live-pnl');
    const pnlValue = document.getElementById('pnl-value');
    
    // Optimizer inputs
    const ctrlStrategy = document.getElementById('ctrl-strategy');
    const ctrlScoreMetric = document.getElementById('ctrl-score-metric');
    const btnOptimize = document.getElementById('btn-optimize');
    const optControls = document.getElementById('opt-controls');
    const optProgressBar = document.getElementById('opt-progress-bar');
    const btnOptCancel = document.getElementById('btn-opt-cancel');
    const strategyDesc = document.getElementById('strategy-desc');
    const strategyGridSize = document.getElementById('strategy-grid-size');
    
    // Headers & Stats
    const statTrades = document.getElementById('stat-trades');
    const statWinrate = document.getElementById('stat-winrate');
    const statPnl = document.getElementById('stat-pnl');
    const btnResetStats = document.getElementById('btn-reset-stats');
    
    // Chart toggles & details
    const togEma = document.getElementById('tog-ema');
    const togBb = document.getElementById('tog-bb');
    const chartSymbolDisplay = document.getElementById('chart-symbol-display');
    const chartPlaceholder = document.getElementById('chart-placeholder');
    const resultsPanel = document.getElementById('results-panel');

    // Custom Rules inputs
    const btnModeRules = document.getElementById('btn-mode-rules');
    const rulesControls = document.getElementById('rules-controls');
    const btnAddBuyRule = document.getElementById('btn-add-buy-rule');
    const btnAddSellRule = document.getElementById('btn-add-sell-rule');
    const buyRulesList = document.getElementById('buy-rules-list');
    const sellRulesList = document.getElementById('sell-rules-list');
    const rulesOppositeExit = document.getElementById('rules-opposite-exit');
    const rulesCapital = document.getElementById('rules-capital');
    const rulesLeverage = document.getElementById('rules-leverage');
    const rulesLeverageDisplay = document.getElementById('rules-leverage-display');
    const rulesTp = document.getElementById('rules-tp');
    const rulesSl = document.getElementById('rules-sl');
    const btnRunRules = document.getElementById('btn-run-rules');
    
    // Canvas contexts
    const ctxCandle = document.getElementById('candle-chart').getContext('2d');
    const ctxRsi = document.getElementById('rsi-chart').getContext('2d');
    const ctxMacd = document.getElementById('macd-chart').getContext('2d');
    
    // State Variables
    let currentMode = 'manual'; // 'manual' or 'optimizer'
    let activeInterval = '1h';
    let fetchedCandles = null; // { timestamps, opens, highs, lows, closes, volumes }
    let calculatedIndicators = null; // { ema20, ema50, bb, rsi, macd }
    let priceChart = null;
    let rsiChart = null;
    let macdChart = null;
    let isChartLoaded = false;
    
    // Trade settings (Mode 1)
    let tradeDirection = 'long'; // 'long' or 'short'
    let entryPrice = 0;
    let tpPrice = 0;
    let slPrice = 0;
    let isDragging = null; // 'tp' or 'sl'
    let isPlaybackRunning = false;
    let playbackInterval = null;
    let currentCandleCount = 200;
    let loadStartTimestampMs = null;
    
    // Optimizer state (Mode 2)
    let activeSignals = [];
    let optimizerCancelRequested = false;
    
    // Session Statistics
    let sessionStats = {
        trades: 0,
        wins: 0,
        netPnl: 0.0
    };
    
    // Load persisted stats on init
    if (localStorage.getItem('sb_session_stats')) {
        try {
            sessionStats = JSON.parse(localStorage.getItem('sb_session_stats'));
            displaySessionStats();
        } catch (e) {
            console.error('Error loading session stats', e);
        }
    }
    
    // Initialize interval pills
    const intervalPills = document.querySelectorAll('#ctrl-interval .sb-pill');
    intervalPills.forEach(pill => {
        pill.addEventListener('click', () => {
            if (isPlaybackRunning) return;
            intervalPills.forEach(p => {
                p.classList.remove('active');
                p.setAttribute('aria-pressed', 'false');
            });
            pill.classList.add('active');
            pill.setAttribute('aria-pressed', 'true');
            activeInterval = pill.dataset.val;
        });
    });
    
    // Initialize date selector to 3 months ago
    const defaultDate = new Date();
    defaultDate.setMonth(defaultDate.getMonth() - 3);
    ctrlDate.value = defaultDate.toISOString().split('T')[0];
    loadStartTimestampMs = parseDateInputToUtcMs(ctrlDate.value);

    ctrlDate.addEventListener('change', () => {
        if (!ctrlDate.value) return;
        loadStartTimestampMs = parseDateInputToUtcMs(ctrlDate.value);
    });
    
    // Mode toggling
    btnModeManual.addEventListener('click', () => {
        if (isPlaybackRunning) return;
        cancelOptimization(true);
        currentMode = 'manual';
        btnModeManual.classList.add('active');
        btnModeOptimizer.classList.remove('active');
        btnModeRules.classList.remove('active');
        btnModeManual.setAttribute('aria-selected', 'true');
        btnModeOptimizer.setAttribute('aria-selected', 'false');
        btnModeRules.setAttribute('aria-selected', 'false');
        manualControls.classList.remove('sb-hidden');
        optimizerControls.classList.add('sb-hidden');
        rulesControls.classList.add('sb-hidden');
        modeDescription.textContent = "Load real historical data, study the chart, place a simulated trade, and let the market reveal if your call was correct.";
        clearChartSignals();
        updateResultsVisibility();
    });
    
    btnModeOptimizer.addEventListener('click', () => {
        if (isPlaybackRunning) return;
        cancelOptimization(true);
        currentMode = 'optimizer';
        btnModeOptimizer.classList.add('active');
        btnModeManual.classList.remove('active');
        btnModeRules.classList.remove('active');
        btnModeManual.setAttribute('aria-selected', 'false');
        btnModeOptimizer.setAttribute('aria-selected', 'true');
        btnModeRules.setAttribute('aria-selected', 'false');
        optimizerControls.classList.remove('sb-hidden');
        manualControls.classList.add('sb-hidden');
        rulesControls.classList.add('sb-hidden');
        modeDescription.textContent = "The grid-search engine runs dozens of backtests across parameter permutations and ranks them by risk-adjusted performance.";
        clearChartSignals();
        updateResultsVisibility();
    });

    btnModeRules.addEventListener('click', () => {
        if (isPlaybackRunning) return;
        cancelOptimization(true);
        currentMode = 'rules';
        btnModeRules.classList.add('active');
        btnModeManual.classList.remove('active');
        btnModeOptimizer.classList.remove('active');
        btnModeManual.setAttribute('aria-selected', 'false');
        btnModeOptimizer.setAttribute('aria-selected', 'false');
        btnModeRules.setAttribute('aria-selected', 'true');
        rulesControls.classList.remove('sb-hidden');
        manualControls.classList.add('sb-hidden');
        optimizerControls.classList.add('sb-hidden');
        modeDescription.textContent = "Design custom entry and exit conditions using visual logical builders and backtest your strategy.";
        clearChartSignals();
        updateResultsVisibility();
    });
    
    // Strategy descriptions metadata
    const strategyMetadata = {
        ema_cross: {
            desc: "Long when Fast EMA crosses above Slow EMA; Short when Fast EMA crosses below Slow EMA. Grid searches combinations of Fast (5–25) and Slow (15–60) periods.",
        },
        rsi_reversion: {
            desc: "Buy when RSI dips below Oversold threshold, close when RSI rebounds to 50. Sell when RSI rises above Overbought threshold, close when RSI dips to 50. Grid searches Oversold (20–40) and Overbought (60–80) boundaries.",
        },
        bb_breakout: {
            desc: "Long when price crosses above upper Bollinger Band; exit when price reverts below the middle band (basis). Short when price crosses below lower Bollinger Band; exit when price reverts above the middle band. Grid searches Period (10–25) and StdDev multiplier (1.5–2.5).",
        },
        macd_signal: {
            desc: "Long when MACD Line crosses above Signal Line; Short when MACD Line crosses below Signal Line. Grid searches Fast EMA (6–16), Slow EMA (18–30), and Signal Period (6–12).",
        }
    };
    
    function updateStrategyInfo() {
        const strategy = ctrlStrategy.value;
        const meta = strategyMetadata[strategy];
        
        let combos = 0;
        if (strategy === 'ema_cross') {
            for (let fast = 5; fast <= 25; fast += 5) {
                for (let slow = 15; slow <= 60; slow += 5) {
                    if (fast < slow) combos++;
                }
            }
        } else if (strategy === 'rsi_reversion') {
            combos = 5 * 5; // 25
        } else if (strategy === 'bb_breakout') {
            combos = 4 * 3; // 12
        } else if (strategy === 'macd_signal') {
            for (let fast = 6; fast <= 16; fast += 4)
                for (let slow = 18; slow <= 30; slow += 4)
                    for (let signal = 6; signal <= 12; signal += 2)
                        if (fast < slow) combos++;
        }
        
        strategyDesc.textContent = meta.desc;
        strategyGridSize.textContent = `Grid size: ${combos} configurations`;
    }
    
    ctrlStrategy.addEventListener('change', updateStrategyInfo);
    updateStrategyInfo(); // run once on init
    
    // Chart options changes
    togEma.addEventListener('change', () => { if (isChartLoaded) scheduleRender(); });
    togBb.addEventListener('change', () => { if (isChartLoaded) scheduleRender(); });
    
    // Manual Trade Direction buttons
    btnLong.addEventListener('click', () => {
        if (!isChartLoaded || isPlaybackRunning) return;
        tradeDirection = 'long';
        btnLong.classList.add('active');
        btnShort.classList.remove('active');
        btnLong.setAttribute('aria-pressed', 'true');
        btnShort.setAttribute('aria-pressed', 'false');
        setDefaultTpSl();
        updateAnnotations();
        updateRiskPreview();
    });
    
    btnShort.addEventListener('click', () => {
        if (!isChartLoaded || isPlaybackRunning) return;
        tradeDirection = 'short';
        btnLong.classList.remove('active');
        btnShort.classList.add('active');
        btnLong.setAttribute('aria-pressed', 'false');
        btnShort.setAttribute('aria-pressed', 'true');
        setDefaultTpSl();
        updateAnnotations();
        updateRiskPreview();
    });
    
    // Leverage range listener
    ctrlLeverage.addEventListener('input', (e) => {
        leverageDisplay.textContent = `${e.target.value}x`;
        updateRiskPreview();
    });
    
    // Inputs change listeners
    ctrlTp.addEventListener('input', () => {
        tpPrice = parseFloat(ctrlTp.value) || 0;
        updateAnnotations();
        updateRiskPreview();
    });
    ctrlSl.addEventListener('input', () => {
        slPrice = parseFloat(ctrlSl.value) || 0;
        updateAnnotations();
        updateRiskPreview();
    });
    
    btnLoad.addEventListener('click', loadMarketData);
    
    // Fetch and Load Data
    async function loadMarketData(explicitStartMs = null) {
        const symbol = ctrlSymbol.value;
        const interval = activeInterval;
        const startDate = ctrlDate.value;
        
        if (!startDate) {
            showToast('Please select a historical start date', 'error');
            return;
        }
        
        btnLoad.disabled = true;
        btnLoad.textContent = 'Loading…';
        loadSpinner.style.display = 'inline-block';
        btnRetryLoad.style.display = 'none';
        // Disable action buttons during load to prevent race conditions
        btnExecute.disabled = true;
        btnOptimize.disabled = true;
        btnRunRules.disabled = true;
        toggleLoadingInputs(true);
        resultsPanel.innerHTML = '';
        const requestTimeoutMs = 10000;
        const abortController = new AbortController();
        const timeoutId = setTimeout(() => abortController.abort(), requestTimeoutMs);
        
        try {
            const startMs = Number.isFinite(explicitStartMs)
                ? explicitStartMs
                : parseDateInputToUtcMs(startDate);
            loadStartTimestampMs = startMs;
            setDateInputFromUtcMs(startMs);

            const limit = 260;
            const url = `https://api.binance.com/api/v3/klines?symbol=${symbol}&interval=${interval}&startTime=${startMs}&limit=${limit}`;
            
            const response = await fetch(url, { signal: abortController.signal });
            if (!response.ok) {
                throw new Error('Failed to fetch data from Binance API');
            }
            
            const raw = await response.json();
            if (raw.length < 200) {
                throw new Error('Insufficient historical data for selected date on Binance.');
            }
            
            const timestamps = [];
            const opens = [];
            const highs = [];
            const lows = [];
            const closes = [];
            const volumes = [];
            
            raw.forEach(c => {
                timestamps.push(new Date(c[0]).toLocaleDateString(undefined, {
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                }));
                opens.push(parseFloat(c[1]));
                highs.push(parseFloat(c[2]));
                lows.push(parseFloat(c[3]));
                closes.push(parseFloat(c[4]));
                volumes.push(parseFloat(c[5]));
            });

            loadStartTimestampMs = raw[0][0];
            setDateInputFromUtcMs(loadStartTimestampMs);
            
            fetchedCandles = { timestamps, opens, highs, lows, closes, volumes };
            
            calculateAllIndicators();
            
            isChartLoaded = true;
            btnExecute.disabled = false;
            btnOptimize.disabled = false;
            chartPlaceholder.classList.add('sb-hidden');
            
            const windowEndIndex = Math.min(199, timestamps.length - 1);
            const startStampLabel = formatStartTimestamp(loadStartTimestampMs);
            chartSymbolDisplay.textContent = `${symbol} — ${interval} (${timestamps[0]} to ${timestamps[windowEndIndex]}) · Start: ${startStampLabel}`;
            
            resetTradeSetup();
            updateResultsVisibility();
            
            showToast('Market data loaded successfully', 'success');
        } catch (err) {
            console.error(err);
            if (err.name === 'AbortError') {
                showToast('Request timed out. Click Retry to try again.', 'error');
                btnRetryLoad.style.display = 'inline-block';
                return;
            }
            showToast(err.message || 'Error loading market data', 'error');
        } finally {
            clearTimeout(timeoutId);
            btnLoad.disabled = false;
            btnLoad.textContent = 'Load Chart';
            loadSpinner.style.display = 'none';
            toggleLoadingInputs(false);
        }
    }

    btnRetryLoad.addEventListener('click', () => {
        btnRetryLoad.style.display = 'none';
        loadMarketData(loadStartTimestampMs);
    });
    
    function resetTradeSetup() {
        if (!fetchedCandles) return;
        entryPrice = fetchedCandles.closes[199];
        setDefaultTpSl();
        updateRiskPreview();
    }
    
    function setDefaultTpSl() {
        if (tradeDirection === 'long') {
            tpPrice = entryPrice * 1.02; // +2%
            slPrice = entryPrice * 0.99; // -1%
        } else {
            tpPrice = entryPrice * 0.98; // -2%
            slPrice = entryPrice * 1.01; // +1%
        }
        ctrlTp.value = tpPrice.toFixed(2);
        ctrlSl.value = slPrice.toFixed(2);
    }
    
    function updateRiskPreview() {
        if (!isChartLoaded) return;
        
        const capital = parseFloat(ctrlCapital.value) || 1000;
        const leverage = parseInt(ctrlLeverage.value) || 5;
        const positionSize = capital * leverage;
        
        let tpPercent = 0;
        let slPercent = 0;
        
        if (tradeDirection === 'long') {
            tpPercent = (tpPrice - entryPrice) / entryPrice;
            slPercent = (entryPrice - slPrice) / entryPrice;
        } else {
            tpPercent = (entryPrice - tpPrice) / entryPrice;
            slPercent = (slPrice - entryPrice) / entryPrice;
        }
        
        const expectedWin = positionSize * tpPercent;
        const expectedLoss = positionSize * slPercent;
        const riskRewardRatio = slPercent !== 0 ? (tpPercent / slPercent) : 0;
        
        riskPreview.innerHTML = `
            <div class="sb-risk-row pos">
                <span>Est. Profit (TP):</span>
                <span>+$${expectedWin.toFixed(2)} (${(tpPercent * 100).toFixed(1)}%)</span>
            </div>
            <div class="sb-risk-row neg">
                <span>Est. Loss (SL):</span>
                <span>-$${expectedLoss.toFixed(2)} (${(slPercent * 100).toFixed(1)}%)</span>
            </div>
            <div class="sb-risk-row">
                <span>Risk/Reward Ratio:</span>
                <span>${riskRewardRatio.toFixed(2)}</span>
            </div>
        `;
    }
    
    // Technical Indicator Calculations
    function calculateEMA(data, period) {
        const k = 2 / (period + 1);
        const ema = [];
        let sum = 0;
        for (let i = 0; i < data.length; i++) {
            if (i < period - 1) {
                sum += data[i];
                ema.push(null);
            } else if (i === period - 1) {
                sum += data[i];
                ema.push(sum / period);
            } else {
                ema.push(data[i] * k + ema[i - 1] * (1 - k));
            }
        }
        return ema;
    }
    
    function calculateRSI(closes, period = 14) {
        const rsi = [];
        const gains = [];
        const losses = [];
        
        for (let i = 1; i < closes.length; i++) {
            const diff = closes[i] - closes[i - 1];
            gains.push(diff > 0 ? diff : 0);
            losses.push(diff < 0 ? -diff : 0);
        }
        
        let avgGain = 0;
        let avgLoss = 0;
        
        for (let i = 0; i < period; i++) {
            avgGain += gains[i];
            avgLoss += losses[i];
        }
        avgGain /= period;
        avgLoss /= period;
        
        for (let i = 0; i < period; i++) {
            rsi.push(null);
        }
        
        rsi.push(avgLoss === 0 ? 100 : 100 - (100 / (1 + avgGain / avgLoss)));
        
        for (let i = period; i < gains.length; i++) {
            avgGain = (avgGain * (period - 1) + gains[i]) / period;
            avgLoss = (avgLoss * (period - 1) + losses[i]) / period;
            rsi.push(avgLoss === 0 ? 100 : 100 - (100 / (1 + avgGain / avgLoss)));
        }
        
        return rsi;
    }
    
    function calculateMACD(closes) {
        const ema12 = calculateEMA(closes, 12);
        const ema26 = calculateEMA(closes, 26);
        const macdLine = [];
        for (let i = 0; i < closes.length; i++) {
            if (ema12[i] === null || ema26[i] === null) {
                macdLine.push(null);
            } else {
                macdLine.push(ema12[i] - ema26[i]);
            }
        }
        
        const validMacd = macdLine.filter(x => x !== null);
        const validSignal = calculateEMA(validMacd, 9);
        const signalLine = [];
        let signalIdx = 0;
        for (let i = 0; i < closes.length; i++) {
            if (macdLine[i] === null) {
                signalLine.push(null);
            } else {
                signalLine.push(validSignal[signalIdx++]);
            }
        }
        
        const histogram = [];
        for (let i = 0; i < closes.length; i++) {
            if (macdLine[i] === null || signalLine[i] === null) {
                histogram.push(null);
            } else {
                histogram.push(macdLine[i] - signalLine[i]);
            }
        }
        return { macdLine, signalLine, histogram };
    }
    
    function calculateBB(closes, period = 20, multiplier = 2) {
        const basis = [];
        const upper = [];
        const lower = [];
        for (let i = 0; i < closes.length; i++) {
            if (i < period - 1) {
                basis.push(null);
                upper.push(null);
                lower.push(null);
            } else {
                const slice = closes.slice(i - period + 1, i + 1);
                const sum = slice.reduce((a, b) => a + b, 0);
                const mean = sum / period;
                basis.push(mean);
                
                const variance = slice.reduce((a, b) => a + Math.pow(b - mean, 2), 0) / period;
                const stdDev = Math.sqrt(variance);
                upper.push(mean + multiplier * stdDev);
                lower.push(mean - multiplier * stdDev);
            }
        }
        return { basis, upper, lower };
    }
    
    function calculateAllIndicators() {
        const closes = fetchedCandles.closes;
        calculatedIndicators = {
            ema20: calculateEMA(closes, 20),
            ema50: calculateEMA(closes, 50),
            bb: calculateBB(closes, 20, 2),
            rsi: calculateRSI(closes, 14),
            macd: calculateMACD(closes)
        };
    }
    
    // Chart Rendering
    // Throttled rendering scheduler to keep UI responsive
    let renderScheduled = false;
    let lastRenderTime = 0;
    const MIN_RENDER_INTERVAL = 50; // ms -> ~20 FPS

    function scheduleRender(force = false) {
        if (force) {
            renderScheduled = false;
            lastRenderTime = performance.now();
            renderCharts();
            return;
        }
        if (renderScheduled) return;
        const now = performance.now();
        const since = now - lastRenderTime;
        if (since >= MIN_RENDER_INTERVAL) {
            lastRenderTime = now;
            renderCharts();
            return;
        }
        renderScheduled = true;
        requestAnimationFrame(() => {
            renderScheduled = false;
            if (performance.now() - lastRenderTime >= MIN_RENDER_INTERVAL) {
                lastRenderTime = performance.now();
                renderCharts();
            }
        });
    }

    function renderCharts() {
        if (!fetchedCandles) return;
        
        const labels = fetchedCandles.timestamps.slice(0, currentCandleCount);
        const prices = fetchedCandles.closes.slice(0, currentCandleCount);
        
        const startStampLabel = formatStartTimestamp(loadStartTimestampMs);
        chartSymbolDisplay.textContent = `${ctrlSymbol.value} — ${activeInterval} (${labels[0]} to ${labels[labels.length - 1]}) · Start: ${startStampLabel}`;
        
        const priceDatasets = [
            {
                label: 'Price',
                data: prices,
                borderColor: '#d4af37',
                borderWidth: 2,
                pointRadius: 0,
                fill: true,
                backgroundColor: 'rgba(212, 175, 55, 0.04)',
                tension: 0.15
            }
        ];
        
        if (togEma.checked && calculatedIndicators.ema20 && calculatedIndicators.ema50) {
            priceDatasets.push({
                label: 'EMA 20',
                data: calculatedIndicators.ema20.slice(0, currentCandleCount),
                borderColor: '#ff9800',
                borderWidth: 1.2,
                pointRadius: 0,
                fill: false,
                tension: 0.2
            });
            priceDatasets.push({
                label: 'EMA 50',
                data: calculatedIndicators.ema50.slice(0, currentCandleCount),
                borderColor: '#00bcd4',
                borderWidth: 1.2,
                pointRadius: 0,
                fill: false,
                tension: 0.2
            });
        }
        
        if (togBb.checked && calculatedIndicators.bb) {
            priceDatasets.push({
                label: 'BB Upper',
                data: calculatedIndicators.bb.upper.slice(0, currentCandleCount),
                borderColor: 'rgba(126, 87, 194, 0.3)',
                borderWidth: 1,
                pointRadius: 0,
                fill: false
            });
            priceDatasets.push({
                label: 'BB Lower',
                data: calculatedIndicators.bb.lower.slice(0, currentCandleCount),
                borderColor: 'rgba(126, 87, 194, 0.3)',
                borderWidth: 1,
                pointRadius: 0,
                fill: false
            });
        }
        
        const annotations = {};
        
        if (currentMode === 'manual') {
            annotations.entryLine = {
                type: 'line',
                yMin: entryPrice,
                yMax: entryPrice,
                borderColor: 'rgba(212, 175, 55, 0.7)',
                borderWidth: 1.5,
                label: {
                    display: true,
                    content: 'Entry',
                    position: 'start',
                    backgroundColor: 'rgba(212, 175, 55, 0.9)',
                    color: '#050c09',
                    font: { size: 10, weight: 'bold' }
                }
            };
            
            annotations.tpLine = {
                type: 'line',
                yMin: tpPrice,
                yMax: tpPrice,
                borderColor: 'rgba(0, 200, 83, 0.8)',
                borderWidth: 2,
                borderDash: [6, 4],
                label: {
                    display: true,
                    content: `TP: $${tpPrice.toFixed(2)}`,
                    position: 'end',
                    backgroundColor: 'rgba(0, 200, 83, 0.9)',
                    color: '#fff',
                    font: { size: 10, weight: 'bold' }
                }
            };
            
            annotations.slLine = {
                type: 'line',
                yMin: slPrice,
                yMax: slPrice,
                borderColor: 'rgba(213, 0, 0, 0.8)',
                borderWidth: 2,
                borderDash: [6, 4],
                label: {
                    display: true,
                    content: `SL: $${slPrice.toFixed(2)}`,
                    position: 'end',
                    backgroundColor: 'rgba(213, 0, 0, 0.9)',
                    color: '#fff',
                    font: { size: 10, weight: 'bold' }
                }
            };
        }
        
        if (currentMode === 'optimizer' && activeSignals && activeSignals.length > 0) {
            activeSignals.forEach((sig, idx) => {
                if (sig.index < currentCandleCount) {
                    annotations[`sig-${idx}`] = {
                        type: 'point',
                        xValue: labels[sig.index],
                        yValue: prices[sig.index],
                        backgroundColor: sig.type === 'buy' ? '#00c853' : '#d50000',
                        radius: 6,
                        borderColor: '#050c09',
                        borderWidth: 1.5,
                        label: {
                            display: true,
                            content: sig.type === 'buy' ? 'BUY' : 'SELL',
                            position: sig.type === 'buy' ? 'bottom' : 'top',
                            backgroundColor: sig.type === 'buy' ? 'rgba(0, 200, 83, 0.8)' : 'rgba(213, 0, 0, 0.8)',
                            color: '#fff',
                            font: { size: 8, weight: 'bold' }
                        }
                    };
                }
            });
        }
        
        // 1. Candle Price Chart
        if (priceChart) {
            priceChart.data.labels = labels;
            priceChart.data.datasets = priceDatasets;
            priceChart.options.plugins.annotation.annotations = annotations;
            priceChart.update('none');
        } else {
            priceChart = new Chart(ctxCandle, {
                type: 'line',
                data: { labels, datasets: priceDatasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        annotation: { annotations }
                    },
                    scales: {
                        x: {
                            grid: { color: getChartColors().grid },
                            ticks: { color: getChartColors().text, font: { size: 10 }, maxTicksLimit: 12 }
                        },
                        y: {
                            grid: { color: getChartColors().grid },
                            ticks: { color: getChartColors().text, font: { size: 10 } }
                        }
                    }
                }
            });
            setupDraggableEvents();
        }
        
        // 2. RSI Chart
        const rsiData = calculatedIndicators.rsi.slice(0, currentCandleCount);
        if (rsiChart) {
            rsiChart.data.labels = labels;
            rsiChart.data.datasets[0].data = rsiData;
            rsiChart.update('none');
        } else {
            rsiChart = new Chart(ctxRsi, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label: 'RSI',
                        data: rsiData,
                        borderColor: '#7e57c2',
                        borderWidth: 1.5,
                        pointRadius: 0,
                        fill: false,
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        annotation: {
                            annotations: {
                                rsiOversold: {
                                    type: 'line',
                                    yMin: 30,
                                    yMax: 30,
                                    borderColor: 'rgba(0, 200, 83, 0.45)',
                                    borderWidth: 1.5,
                                    borderDash: [5, 4]
                                },
                                rsiOverbought: {
                                    type: 'line',
                                    yMin: 70,
                                    yMax: 70,
                                    borderColor: 'rgba(213, 0, 0, 0.45)',
                                    borderWidth: 1.5,
                                    borderDash: [5, 4]
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { color: getChartColors().grid },
                            ticks: { display: false }
                        },
                        y: {
                            min: 0,
                            max: 100,
                            ticks: {
                                color: getChartColors().text,
                                font: { size: 9 },
                                stepSize: 30,
                                callback: function(value) { if (value === 30 || value === 70) return value; return ''; }
                            },
                            grid: {
                                color: function(context) {
                                    if (context.tick.value === 30 || context.tick.value === 70) {
                                        return 'rgba(126, 87, 194, 0.3)';
                                    }
                                    return getChartColors().grid;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // 3. MACD Chart
        const macdLine = calculatedIndicators.macd.macdLine.slice(0, currentCandleCount);
        const signalLine = calculatedIndicators.macd.signalLine.slice(0, currentCandleCount);
        const histogram = calculatedIndicators.macd.histogram.slice(0, currentCandleCount);
        
        if (macdChart) {
            macdChart.data.labels = labels;
            macdChart.data.datasets[0].data = macdLine;
            macdChart.data.datasets[1].data = signalLine;
            macdChart.data.datasets[2].data = histogram;
            macdChart.update('none');
        } else {
            macdChart = new Chart(ctxMacd, {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        {
                            label: 'MACD',
                            data: macdLine,
                            borderColor: '#d4af37',
                            borderWidth: 1.2,
                            pointRadius: 0,
                            fill: false
                        },
                        {
                            label: 'Signal',
                            data: signalLine,
                            borderColor: '#607d8b',
                            borderWidth: 1.2,
                            pointRadius: 0,
                            fill: false
                        },
                        {
                            label: 'Histogram',
                            data: histogram,
                            type: 'bar',
                            backgroundColor: function(context) {
                                const val = context.raw;
                                return val >= 0 ? 'rgba(0, 200, 83, 0.3)' : 'rgba(213, 0, 0, 0.3)';
                            },
                            borderWidth: 0,
                            barPercentage: 0.8,
                            categoryPercentage: 0.8
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: {
                            grid: { color: getChartColors().grid },
                            ticks: { display: false }
                        },
                        y: {
                            grid: { color: getChartColors().grid },
                            ticks: { color: getChartColors().text, font: { size: 9 } }
                        }
                    }
                }
            });
        }
    }
    
    function setupDraggableEvents() {
        const canvas = ctxCandle.canvas;
        
        canvas.addEventListener('mousedown', (e) => {
            if (!isChartLoaded || currentMode !== 'manual' || isPlaybackRunning) return;
            
            const rect = canvas.getBoundingClientRect();
            const mouseY = e.clientY - rect.top;
            
            const tpPixel = priceChart.scales.y.getPixelForValue(tpPrice);
            const slPixel = priceChart.scales.y.getPixelForValue(slPrice);
            
            if (Math.abs(mouseY - tpPixel) < 12) {
                isDragging = 'tp';
            } else if (Math.abs(mouseY - slPixel) < 12) {
                isDragging = 'sl';
            }
        });
        
        canvas.addEventListener('mousemove', (e) => {
            if (!isChartLoaded || currentMode !== 'manual' || isPlaybackRunning) return;
            
            const rect = canvas.getBoundingClientRect();
            const mouseY = e.clientY - rect.top;
            
            if (isDragging) {
                const priceValue = priceChart.scales.y.getValueForPixel(mouseY);
                if (isDragging === 'tp') {
                    tpPrice = priceValue;
                    ctrlTp.value = tpPrice.toFixed(2);
                } else if (isDragging === 'sl') {
                    slPrice = priceValue;
                    ctrlSl.value = slPrice.toFixed(2);
                }
                // Defer heavy chart update to scheduler
                updateRiskPreview();
                updateAnnotations();
                scheduleRender();
            } else {
                const tpPixel = priceChart.scales.y.getPixelForValue(tpPrice);
                const slPixel = priceChart.scales.y.getPixelForValue(slPrice);
                if (Math.abs(mouseY - tpPixel) < 12 || Math.abs(mouseY - slPixel) < 12) {
                    canvas.style.cursor = 'row-resize';
                } else {
                    canvas.style.cursor = 'crosshair';
                }
            }
        });
        
        window.addEventListener('mouseup', () => {
            isDragging = null;
        });
    }
    
    function updateAnnotations() {
        if (!priceChart) return;
        // Guard: annotations only exist when chart was first rendered in manual mode
        const annots = priceChart.options.plugins.annotation.annotations;
        if (!annots || !annots.tpLine || !annots.slLine) return;
        
        annots.tpLine.yMin = tpPrice;
        annots.tpLine.yMax = tpPrice;
        annots.tpLine.label.content = `TP: $${tpPrice.toFixed(2)}`;
        
        annots.slLine.yMin = slPrice;
        annots.slLine.yMax = slPrice;
        annots.slLine.label.content = `SL: $${slPrice.toFixed(2)}`;
        
        priceChart.update('none');
    }

    function parseDateInputToUtcMs(dateStr) {
        const parts = dateStr.split('-').map(Number);
        if (parts.length !== 3 || parts.some(Number.isNaN)) {
            return new Date(dateStr).getTime();
        }
        return Date.UTC(parts[0], parts[1] - 1, parts[2], 0, 0, 0, 0);
    }

    function setDateInputFromUtcMs(ms) {
        if (!Number.isFinite(ms)) return;
        ctrlDate.value = new Date(ms).toISOString().split('T')[0];
    }
    function formatStartTimestamp(ms) {
        if (!Number.isFinite(ms)) return 'N/A';
        return new Date(ms).toLocaleString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function intervalToMs(interval) {
        switch (interval) {
            case '5m': return 5 * 60 * 1000;
            case '15m': return 15 * 60 * 1000;
            case '1h': return 60 * 60 * 1000;
            case '4h': return 4 * 60 * 60 * 1000;
            case '1d': return 24 * 60 * 60 * 1000;
            default: return 60 * 60 * 1000;
        }
    }

    function resolveDualBreach(candleOpen) {
        const tpDistance = Math.abs(candleOpen - tpPrice);
        const slDistance = Math.abs(candleOpen - slPrice);
        if (tpDistance === slDistance) {
            return {
                outcome: 'Stop Loss Hit (Tie-break: Equal Distance)',
                exitPrice: slPrice
            };
        }

        if (tpDistance < slDistance) {
            return { outcome: 'Take Profit Hit', exitPrice: tpPrice };
        }

        return { outcome: 'Stop Loss Hit', exitPrice: slPrice };
    }
    
    // Playback and State-machine execution (Mode 1)
    btnExecute.addEventListener('click', () => {
        if (!isChartLoaded || isPlaybackRunning) return;
        
        const capital = parseFloat(ctrlCapital.value) || 1000;
        const leverage = parseInt(ctrlLeverage.value) || 5;
        
        if (capital < 100) {
            showToast('Capital must be at least $100', 'error');
            return;
        }
        if (capital > 10000000) {
            showToast('Capital cannot exceed $10,000,000', 'error');
            return;
        }
        
        if (tradeDirection === 'long') {
            if (tpPrice <= entryPrice) {
                showToast('Take Profit must be above Entry Price for a Long trade', 'error');
                return;
            }
            if (slPrice >= entryPrice) {
                showToast('Stop Loss must be below Entry Price for a Long trade', 'error');
                return;
            }
        } else {
            if (tpPrice >= entryPrice) {
                showToast('Take Profit must be below Entry Price for a Short trade', 'error');
                return;
            }
            if (slPrice <= entryPrice) {
                showToast('Stop Loss must be above Entry Price for a Short trade', 'error');
                return;
            }
        }
        
        isPlaybackRunning = true;
        btnExecute.disabled = true;
        btnLoad.disabled = true;
        btnModeManual.disabled = true;
        btnModeOptimizer.disabled = true;
        btnModeRules.disabled = true;
        
        toggleInputs(true);
        
        currentCandleCount = 200;
        livePnl.style.display = 'block';
        resultsPanel.innerHTML = '';
        
        const positionSize = capital * leverage;
        const openFee = positionSize * 0.001;
        
        playbackInterval = setInterval(() => {
            currentCandleCount++;
            
            if (currentCandleCount >= 260) {  // >= so exactly 60 candles play back, not 61
                const exitPrice = fetchedCandles.closes[259];
                finishTrade('Time Limit Exceeded', exitPrice, 60);
                return;
            }
            
            const currentIdx = currentCandleCount - 1;
            const open = fetchedCandles.opens[currentIdx];
            const high = fetchedCandles.highs[currentIdx];
            const low = fetchedCandles.lows[currentIdx];
            const close = fetchedCandles.closes[currentIdx];
            
            let currentPnlPercent = 0;
            if (tradeDirection === 'long') {
                currentPnlPercent = (close - entryPrice) / entryPrice;
            } else {
                currentPnlPercent = (entryPrice - close) / entryPrice;
            }
            
            const currentPnlUsd = positionSize * currentPnlPercent;
            const currentNetPnlUsd = currentPnlUsd - openFee - (positionSize * 0.001);
            
            pnlValue.textContent = `${currentNetPnlUsd >= 0 ? '+' : ''}$${currentNetPnlUsd.toFixed(2)} (${(currentPnlPercent * 100 * leverage).toFixed(1)}%)`;
            if (currentNetPnlUsd >= 0) {
                pnlValue.style.color = '#00c853';
            } else {
                pnlValue.style.color = '#d50000';
            }
            
            let isTpBreached = false;
            let isSlBreached = false;
            
            if (tradeDirection === 'long') {
                if (high >= tpPrice) isTpBreached = true;
                if (low <= slPrice) isSlBreached = true;
            } else {
                if (low <= tpPrice) isTpBreached = true;
                if (high >= slPrice) isSlBreached = true;
            }
            
            if (isTpBreached && isSlBreached) {
                const dualBreachResult = resolveDualBreach(open);
                finishTrade(dualBreachResult.outcome, dualBreachResult.exitPrice, currentCandleCount - 200);
            } else if (isSlBreached) {
                finishTrade('Stop Loss Hit', slPrice, currentCandleCount - 200);
            } else if (isTpBreached) {
                finishTrade('Take Profit Hit', tpPrice, currentCandleCount - 200);
            } else {
                scheduleRender();
            }
        }, 100);
    });
    
    function finishTrade(outcome, exitPrice, candlesHeld) {
        clearInterval(playbackInterval);
        isPlaybackRunning = false;
        
        scheduleRender(true);
        
        const capital = parseFloat(ctrlCapital.value) || 1000;
        const leverage = parseInt(ctrlLeverage.value) || 5;
        const positionSize = capital * leverage;
        
        let priceDelta = 0;
        if (tradeDirection === 'long') {
            priceDelta = (exitPrice - entryPrice) / entryPrice;
        } else {
            priceDelta = (entryPrice - exitPrice) / entryPrice;
        }
        
        const grossPnl = positionSize * priceDelta;
        const entryFee = positionSize * 0.001;
        const exitFee = positionSize * 0.001;
        const totalFees = entryFee + exitFee;
        const netPnl = grossPnl - totalFees;
        const netPnlPercent = (netPnl / capital) * 100;
        
        const isWin = netPnl > 0;
        
        pnlValue.textContent = `${netPnl >= 0 ? '+' : ''}$${netPnl.toFixed(2)} (${netPnlPercent.toFixed(1)}%)`;
        
        const badgeClass = isWin ? 'win' : 'loss';
        const cardClass = isWin ? 'win' : 'loss';
        const pnlClass = netPnl >= 0 ? 'pos' : 'neg';
        
        resultsPanel.innerHTML = `
            <div class="sb-result-card ${cardClass}">
                <div class="sb-result-header">
                    <span class="sb-result-badge ${badgeClass}">${isWin ? 'Win' : 'Loss'}</span>
                    <h3 class="sb-result-title">${outcome}</h3>
                    <p class="sb-result-subtitle">Simulated position closed after ${candlesHeld} candles</p>
                </div>
                <div class="sb-result-grid">
                    <div class="sb-result-stat">
                        <span class="sb-result-stat-label">Entry Price</span>
                        <span class="sb-result-stat-value">$${entryPrice.toFixed(2)}</span>
                    </div>
                    <div class="sb-result-stat">
                        <span class="sb-result-stat-label">Exit Price</span>
                        <span class="sb-result-stat-value">$${exitPrice.toFixed(2)}</span>
                    </div>
                    <div class="sb-result-stat">
                        <span class="sb-result-stat-label">Net Return</span>
                        <span class="sb-result-stat-value ${pnlClass}">${netPnl >= 0 ? '+' : ''}$${netPnl.toFixed(2)} (${netPnlPercent.toFixed(2)}%)</span>
                    </div>
                    <div class="sb-result-stat">
                        <span class="sb-result-stat-label">Position Size</span>
                        <span class="sb-result-stat-value">$${positionSize.toFixed(2)} (${leverage}x)</span>
                    </div>
                    <div class="sb-result-stat">
                        <span class="sb-result-stat-label">Total Commission</span>
                        <span class="sb-result-stat-value">$${totalFees.toFixed(2)} (0.1% each side)</span>
                    </div>
                    <div class="sb-result-stat">
                        <span class="sb-result-stat-label">Duration</span>
                        <span class="sb-result-stat-value">${candlesHeld} candles</span>
                    </div>
                </div>
                <div class="sb-result-footer">
                    <button class="sb-btn-secondary" id="btn-next-trade">Load Next Trade</button>
                </div>
            </div>
        `;
        
        btnLoad.disabled = false;
        btnModeManual.disabled = false;
        btnModeOptimizer.disabled = false;
        btnModeRules.disabled = false;
        
        updateSessionStats(netPnl, isWin);
        
        document.getElementById('btn-next-trade').addEventListener('click', () => {
            resultsPanel.innerHTML = '';
            livePnl.style.display = 'none';
            advanceDateByCandles(60);
        });
    }
    
    function advanceDateByCandles(count) {
        const currentDateVal = ctrlDate.value;
        if (!currentDateVal && !Number.isFinite(loadStartTimestampMs)) return;

        if (!Number.isFinite(loadStartTimestampMs)) {
            loadStartTimestampMs = parseDateInputToUtcMs(currentDateVal);
        }

        const msToAdd = count * intervalToMs(activeInterval);
        const newStartMs = loadStartTimestampMs + msToAdd;
        loadStartTimestampMs = newStartMs;
        setDateInputFromUtcMs(newStartMs);
        loadMarketData(newStartMs);
    }
    
    function toggleInputs(disabled) {
        ctrlSymbol.disabled = disabled;
        ctrlDate.disabled = disabled;
        ctrlCapital.disabled = disabled;
        ctrlLeverage.disabled = disabled;
        ctrlTp.disabled = disabled;
        ctrlSl.disabled = disabled;
        btnLong.disabled = disabled;
        btnShort.disabled = disabled;
        
        const intervalButtons = document.querySelectorAll('#ctrl-interval .sb-pill');
        intervalButtons.forEach(btn => btn.disabled = disabled);
    }
    
    function updateSessionStats(netPnl, isWin) {
        sessionStats.trades += 1;
        if (isWin) sessionStats.wins += 1;
        sessionStats.netPnl += netPnl;
        
        localStorage.setItem('sb_session_stats', JSON.stringify(sessionStats));
        displaySessionStats();
    }
    
    function displaySessionStats() {
        statTrades.textContent = sessionStats.trades;
        
        if (sessionStats.trades > 0) {
            const wr = (sessionStats.wins / sessionStats.trades) * 100;
            statWinrate.textContent = `${wr.toFixed(1)}%`;
        } else {
            statWinrate.textContent = '—';
        }
        
        statPnl.textContent = `${sessionStats.netPnl >= 0 ? '+' : ''}$${sessionStats.netPnl.toFixed(2)}`;
        if (sessionStats.netPnl >= 0) {
            statPnl.style.color = '#00c853';
        } else {
            statPnl.style.color = '#d50000';
        }
    }
    
    btnResetStats.addEventListener('click', () => {
        sessionStats = { trades: 0, wins: 0, netPnl: 0.0 };
        localStorage.setItem('sb_session_stats', JSON.stringify(sessionStats));
        displaySessionStats();
        showToast('Session statistics reset', 'success');
    });
    
    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `sb-toast ${type}`;
        toast.textContent = message;
        document.body.appendChild(toast);
        
        toast.offsetHeight; // trigger reflow
        
        toast.classList.add('visible');
        
        setTimeout(() => {
            toast.classList.remove('visible');
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, 3000);
    }
    
    // Optimizer Engine (Mode 2)
    function runSingleBacktest(strategyType, params) {
        const closes = fetchedCandles.closes;
        const length = closes.length;
        
        let position = null;
        let capital = 10000;
        const trades = [];
        const equity = new Array(length).fill(10000);
        
        let fastEMA, slowEMA, rsi, bb, macdLine, signalLine;
        
        if (strategyType === 'ema_cross') {
            fastEMA = calculateEMA(closes, params.fast);
            slowEMA = calculateEMA(closes, params.slow);
        } else if (strategyType === 'rsi_reversion') {
            rsi = calculateRSI(closes, 14);
        } else if (strategyType === 'bb_breakout') {
            bb = calculateBB(closes, params.period, params.multiplier);
        } else if (strategyType === 'macd_signal') {
            const emaF = calculateEMA(closes, params.fast);
            const emaS = calculateEMA(closes, params.slow);
            macdLine = [];
            for (let i = 0; i < length; i++) {
                if (emaF[i] === null || emaS[i] === null) macdLine.push(null);
                else macdLine.push(emaF[i] - emaS[i]);
            }
            const validMacd = macdLine.filter(x => x !== null);
            const validSignal = calculateEMA(validMacd, params.signal);
            signalLine = [];
            let sigIdx = 0;
            for (let i = 0; i < length; i++) {
                if (macdLine[i] === null) signalLine.push(null);
                else signalLine.push(validSignal[sigIdx++]);
            }
        }
        
        const startIdx = 80;
        
        for (let i = 0; i < startIdx; i++) {
            equity[i] = capital;
        }
        
        for (let i = startIdx; i < length; i++) {
            const price = closes[i];
            let signal = 'hold';
            
            if (strategyType === 'ema_cross') {
                const prevFast = fastEMA[i-1];
                const prevSlow = slowEMA[i-1];
                const currFast = fastEMA[i];
                const currSlow = slowEMA[i];
                
                if (prevFast !== null && prevSlow !== null) {
                    if (prevFast <= prevSlow && currFast > currSlow) {
                        signal = 'buy';
                    } else if (prevFast >= prevSlow && currFast < currSlow) {
                        signal = 'sell';
                    }
                }
            } else if (strategyType === 'rsi_reversion') {
                const prevRsi = rsi[i-1];
                const currRsi = rsi[i];
                
                if (prevRsi !== null) {
                    if (prevRsi < params.oversold && currRsi >= params.oversold) {
                        signal = 'buy';
                    } else if (prevRsi > params.overbought && currRsi <= params.overbought) {
                        signal = 'sell';
                    }
                }
            } else if (strategyType === 'bb_breakout') {
                const prevClose = closes[i-1];
                const prevUpper = bb.upper[i-1];
                const prevLower = bb.lower[i-1];
                const currClose = closes[i];
                
                if (prevUpper !== null && prevLower !== null) {
                    if (prevClose <= prevUpper && currClose > bb.upper[i]) {
                        signal = 'buy';
                    } else if (prevClose >= prevLower && currClose < bb.lower[i]) {
                        signal = 'sell';
                    }
                }
            } else if (strategyType === 'macd_signal') {
                const prevMacd = macdLine[i-1];
                const prevSig = signalLine[i-1];
                const currMacd = macdLine[i];
                const currSig = signalLine[i];
                
                if (prevMacd !== null && prevSig !== null) {
                    if (prevMacd <= prevSig && currMacd > currSig) {
                        signal = 'buy';
                    } else if (prevMacd >= prevSig && currMacd < currSig) {
                        signal = 'sell';
                    }
                }
            }

            // BB Breakout — middle-band exit: close the position when price reverts past the
            // basis without a new breakout signal. Guard with signal === 'hold' so real
            // band-crossover signals always take full priority over the mid-band exit.
            let bbMidExit = false;
            if (strategyType === 'bb_breakout' && position && signal === 'hold' && bb.basis[i] !== null) {
                if (position.type === 'long'  && price < bb.basis[i]) bbMidExit = true;
                else if (position.type === 'short' && price > bb.basis[i]) bbMidExit = true;
            }
            
            if (position) {
                let shouldClose = false;
                // RSI Reversion — exit on RSI midline CROSSOVER, not a bare threshold check.
                // Prevents premature 1-bar exits when RSI enters and immediately passes 50.
                const prevRsiVal = strategyType === 'rsi_reversion' ? rsi[i-1] : null;
                const currRsiVal = strategyType === 'rsi_reversion' ? rsi[i]   : null;
                const rsiExitLong  = strategyType === 'rsi_reversion' && prevRsiVal !== null && prevRsiVal <  50 && currRsiVal >= 50;
                const rsiExitShort = strategyType === 'rsi_reversion' && prevRsiVal !== null && prevRsiVal >  50 && currRsiVal <= 50;

                if (position.type === 'long'  && (signal === 'sell' || rsiExitLong  || bbMidExit)) {
                    shouldClose = true;
                } else if (position.type === 'short' && (signal === 'buy'  || rsiExitShort || bbMidExit)) {
                    shouldClose = true;
                }
                
                if (shouldClose) {
                    const entryPrice = position.entryPrice;
                    const exitPrice = price;
                    let pnlPercent = 0;
                    
                    if (position.type === 'long') {
                        pnlPercent = (exitPrice - entryPrice) / entryPrice;
                    } else {
                        pnlPercent = (entryPrice - exitPrice) / entryPrice;
                    }
                    
                    const positionSize = position.positionSize;
                    const entryFee = position.entryFee;
                    const exitFee = positionSize * 0.001;
                    const grossPnl = positionSize * pnlPercent;
                    const netTradePnl = grossPnl - entryFee - exitFee;
                    
                    capital += grossPnl - exitFee;
                    
                    trades.push({
                        type: position.type,
                        entryPrice,
                        exitPrice,
                        entryIndex: position.entryIndex,
                        exitIndex: i,
                        pnl: netTradePnl,
                        pnlPercent: (netTradePnl / positionSize) * 100
                    });
                    
                    position = null;
                }
            } else {
                // Do NOT open a new position when the close was triggered by the BB
                // middle-band exit — that is an orderly exit, not a new directional signal.
                if (signal === 'buy' && !bbMidExit) {
                    const fee = capital * 0.001;
                    const positionSize = capital - fee;
                    capital -= fee;
                    position = {
                        type: 'long',
                        entryPrice: price,
                        entryIndex: i,
                        entryFee: fee,
                        positionSize: positionSize
                    };
                } else if (signal === 'sell' && !bbMidExit) {
                    const fee = capital * 0.001;
                    const positionSize = capital - fee;
                    capital -= fee;
                    position = {
                        type: 'short',
                        entryPrice: price,
                        entryIndex: i,
                        entryFee: fee,
                        positionSize: positionSize
                    };
                }
            }
            
            if (position) {
                let unrealizedPnlPercent = 0;
                if (position.type === 'long') {
                    unrealizedPnlPercent = (price - position.entryPrice) / position.entryPrice;
                } else {
                    unrealizedPnlPercent = (position.entryPrice - price) / position.entryPrice;
                }
                equity[i] = capital + (capital * unrealizedPnlPercent);
            } else {
                equity[i] = capital;
            }
        }
        
        if (position) {
            const entryPrice = position.entryPrice;
            const exitPrice = closes[length - 1];
            let pnlPercent = 0;
            if (position.type === 'long') {
                pnlPercent = (exitPrice - entryPrice) / entryPrice;
            } else {
                pnlPercent = (entryPrice - exitPrice) / entryPrice;
            }
            const positionSize = position.positionSize;
            const entryFee = position.entryFee;
            const exitFee = positionSize * 0.001;
            const grossPnl = positionSize * pnlPercent;
            const netTradePnl = grossPnl - entryFee - exitFee;
            
            capital += grossPnl - exitFee;
            trades.push({
                type: position.type,
                entryPrice,
                exitPrice,
                entryIndex: position.entryIndex,
                exitIndex: length - 1,
                pnl: netTradePnl,
                pnlPercent: (netTradePnl / positionSize) * 100
            });
            equity[length - 1] = capital;
        }
        
        const returns = [];
        for (let i = startIdx + 1; i < length; i++) {
            returns.push((equity[i] - equity[i-1]) / equity[i-1]);
        }
        
        let meanReturn = 0;
        let stdDev = 0;
        let sharpe = 0;
        
        if (returns.length > 0) {
            meanReturn = returns.reduce((a, b) => a + b, 0) / returns.length;
            const variance = returns.reduce((sum, val) => sum + Math.pow(val - meanReturn, 2), 0) / returns.length;
            stdDev = Math.sqrt(variance);
            if (stdDev > 0) {
                let annualFactor = 252;
                if (activeInterval === '5m') annualFactor = 252 * 288;
                else if (activeInterval === '15m') annualFactor = 252 * 96;
                else if (activeInterval === '1h') annualFactor = 252 * 24;
                else if (activeInterval === '4h') annualFactor = 252 * 6;
                else if (activeInterval === '1d') annualFactor = 252;
                
                sharpe = (meanReturn / stdDev) * Math.sqrt(annualFactor);
            }
        }
        
        let maxDd = 0;
        let peak = -Infinity;
        for (let i = 0; i < length; i++) {
            if (equity[i] > peak) peak = equity[i];
            const dd = (peak - equity[i]) / peak;
            if (dd > maxDd) maxDd = dd;
        }
        
        const netReturn = ((capital - 10000) / 10000) * 100;
        const calmar = maxDd > 0 ? (netReturn / (maxDd * 100)) : (netReturn / 0.01);
        
        const wins = trades.filter(t => t.pnl > 0).length;
        const winRate = trades.length > 0 ? (wins / trades.length) * 100 : 0;
        
        return {
            params,
            finalCapital: capital,
            netReturn,
            sharpe,
            maxDrawdown: maxDd * 100,
            calmar,
            trades: trades.length,
            winRate,
            tradesList: trades
        };
    }
    
    btnOptimize.addEventListener('click', () => {
        if (!isChartLoaded || isPlaybackRunning) return;
        optimizerCancelRequested = false;
        btnOptimize.disabled = true;
        btnOptimize.textContent = 'Optimizing…';
        optControls.style.display = 'flex';
        optProgressBar.style.width = '0%';
        toggleOptimizerInputs(true);

        // Use WebWorker for heavy optimization if available
        if (window.Worker) {
            if (window.optWorker) {
                try { window.optWorker.terminate(); } catch (e) {}
                window.optWorker = null;
            }
            const worker = new Worker('optimizer-worker.js');
            window.optWorker = worker;
            worker.postMessage({ type: 'start', payload: { strategy: ctrlStrategy.value, metric: ctrlScoreMetric.value, closes: fetchedCandles.closes, activeInterval } });

            worker.onmessage = (ev) => {
                const msg = ev.data;
                if (msg.type === 'progress') {
                    optProgressBar.style.width = `${msg.percent}%`;
                    // Update ARIA attribute for screen readers
                    document.getElementById('opt-progress').setAttribute('aria-valuenow', msg.percent);
                } else if (msg.type === 'done') {
                    try {
                        const results = msg.results || [];
                        const metric = ctrlScoreMetric.value;
                        results.sort((a, b) => {
                            // Always push zero-trade configs to the bottom — they have no
                            // meaningful metrics and pollute the leaderboard.
                            if (a.trades === 0 && b.trades === 0) return 0;
                            if (a.trades === 0) return 1;
                            if (b.trades === 0) return -1;
                            let valA, valB;
                            if (metric === 'sharpe') { valA = a.sharpe; valB = b.sharpe; }
                            else if (metric === 'return') { valA = a.netReturn; valB = b.netReturn; }
                            else if (metric === 'calmar') { valA = a.calmar; valB = b.calmar; }
                            return valB - valA;
                        });
                        renderOptimizerResults(results, ctrlStrategy.value);
                        currentCandleCount = fetchedCandles.closes.length;
                        scheduleRender(true);
                        showToast('Strategy optimization complete!', 'success');
                    } catch (err) {
                        console.error('Worker done handling error', err);
                        showToast('Error processing optimization results', 'error');
                    } finally {
                        worker.terminate();
                        window.optWorker = null;
                        btnOptimize.disabled = false;
                        btnOptimize.textContent = 'Run Optimization';
                        optControls.style.display = 'none';
                        optProgressBar.style.width = '0%';
                        toggleOptimizerInputs(false);
                    }
                }
            };

            worker.onerror = (err) => {
                console.error('Optimizer worker error', err);
                showToast('Optimizer encountered an error', 'error');
                try { worker.terminate(); } catch (e) {}
                window.optWorker = null;
                btnOptimize.disabled = false;
                btnOptimize.textContent = 'Run Optimization';
                optControls.style.display = 'none';
                optProgressBar.style.width = '0%';
                toggleOptimizerInputs(false);
            };
        } else {
            // Fallback to in-thread optimization (kept for old browsers)
            (async () => {
                try {
                    const strategy = ctrlStrategy.value;
                    const metric = ctrlScoreMetric.value;
                    const configs = [];

                    if (strategy === 'ema_cross') {
                        for (let fast = 5; fast <= 25; fast += 5) {
                            for (let slow = 15; slow <= 60; slow += 5) {
                                if (fast < slow) configs.push({ fast, slow });
                            }
                        }
                    } else if (strategy === 'rsi_reversion') {
                        for (let oversold = 20; oversold <= 40; oversold += 5) {
                            for (let overbought = 60; overbought <= 80; overbought += 5) {
                                configs.push({ oversold, overbought });
                            }
                        }
                    } else if (strategy === 'bb_breakout') {
                        for (let period = 10; period <= 25; period += 5) {
                            for (let multiplier = 1.5; multiplier <= 2.5; multiplier += 0.5) {
                                configs.push({ period, multiplier });
                            }
                        }
                    } else if (strategy === 'macd_signal') {
                        for (let fast = 6; fast <= 16; fast += 4) {
                            for (let slow = 18; slow <= 30; slow += 4) {
                                for (let signal = 6; signal <= 12; signal += 2) {
                                    if (fast < slow) configs.push({ fast, slow, signal });
                                }
                            }
                        }
                    }

                    const results = [];
                    for (let i = 0; i < configs.length; i++) {
                        if (optimizerCancelRequested) break;
                        await new Promise(resolve => setTimeout(resolve, 0));
                        try {
                            const res = runSingleBacktest(strategy, configs[i]);
                            results.push(res);
                        } catch (e) {
                            console.error('Backtest error', e);
                        }
                        const pct = Math.round(((i + 1) / configs.length) * 100);
                        optProgressBar.style.width = `${pct}%`;
                    }

                    if (optimizerCancelRequested) {
                        // Already handled by cancelOptimization
                    } else {
                        results.sort((a, b) => {
                            // Always push zero-trade configs to the bottom.
                            if (a.trades === 0 && b.trades === 0) return 0;
                            if (a.trades === 0) return 1;
                            if (b.trades === 0) return -1;
                            let valA, valB;
                            if (metric === 'sharpe') { valA = a.sharpe; valB = b.sharpe; }
                            else if (metric === 'return') { valA = a.netReturn; valB = b.netReturn; }
                            else if (metric === 'calmar') { valA = a.calmar; valB = b.calmar; }
                            return valB - valA;
                        });
                        renderOptimizerResults(results, strategy);
                        currentCandleCount = fetchedCandles.closes.length;
                        scheduleRender(true);
                        showToast('Strategy optimization complete!', 'success');
                    }
                } catch (err) {
                    console.error(err);
                    showToast('Error during optimization: ' + err.message, 'error');
                } finally {
                    btnOptimize.disabled = false;
                    btnOptimize.textContent = 'Run Optimization';
                    optControls.style.display = 'none';
                    optProgressBar.style.width = '0%';
                    toggleOptimizerInputs(false);
                }
            })();
        }
    });

    btnOptCancel.addEventListener('click', () => {
        btnOptCancel.disabled = true;
        cancelOptimization(false);
        btnOptCancel.disabled = false;
    });

    function cancelOptimization(silent = false) {
        optimizerCancelRequested = true;
        if (window.optWorker) {
            try { window.optWorker.terminate(); } catch (e) {}
            window.optWorker = null;
        }
        if (!silent) {
            showToast('Optimization canceled', 'error');
        }
        btnOptimize.disabled = false;
        btnOptimize.textContent = 'Run Optimization';
        optControls.style.display = 'none';
        optProgressBar.style.width = '0%';
        toggleOptimizerInputs(false);
    }

    function toggleOptimizerInputs(disabled) {
        ctrlSymbol.disabled = disabled;
        ctrlDate.disabled = disabled;
        btnLoad.disabled = disabled;
        ctrlStrategy.disabled = disabled;
        ctrlScoreMetric.disabled = disabled;
        btnModeManual.disabled = disabled;
        btnModeRules.disabled = disabled;
        
        const intervalButtons = document.querySelectorAll('#ctrl-interval .sb-pill');
        intervalButtons.forEach(btn => btn.disabled = disabled);
    }

    function toggleLoadingInputs(disabled) {
        ctrlSymbol.disabled = disabled;
        ctrlDate.disabled = disabled;
        btnModeManual.disabled = disabled;
        btnModeOptimizer.disabled = disabled;
        btnModeRules.disabled = disabled;
        
        const intervalButtons = document.querySelectorAll('#ctrl-interval .sb-pill');
        intervalButtons.forEach(btn => btn.disabled = disabled);
    }
    
    function renderOptimizerResults(results, strategyType) {
        const topResults = results.slice(0, 15);
        
        let tableHeaderHtml = `
            <thead>
                <tr>
                    <th scope="col">Rank</th>
                    <th scope="col">Parameters</th>
                    <th scope="col">Net Return</th>
                    <th scope="col">Sharpe Ratio</th>
                    <th scope="col">Max DD</th>
                    <th scope="col">Win Rate</th>
                    <th scope="col">Trades</th>
                </tr>
            </thead>
        `;
        
        let rowsHtml = '';
        topResults.forEach((res, index) => {
            const isTop = index === 0;
            const rowClass = isTop ? 'top-result' : '';
            const returnClass = res.netReturn >= 0 ? 'pos' : 'neg';
            
            let paramsText = '';
            if (strategyType === 'ema_cross') {
                paramsText = `Fast: ${res.params.fast}, Slow: ${res.params.slow}`;
            } else if (strategyType === 'rsi_reversion') {
                paramsText = `Oversold: ${res.params.oversold}, Overbought: ${res.params.overbought}`;
            } else if (strategyType === 'bb_breakout') {
                paramsText = `Period: ${res.params.period}, StdDev: ${res.params.multiplier}`;
            } else if (strategyType === 'macd_signal') {
                paramsText = `Fast: ${res.params.fast}, Slow: ${res.params.slow}, Sig: ${res.params.signal}`;
            }
            
            rowsHtml += `
                <tr class="${rowClass}" style="cursor: pointer;" data-idx="${index}">
                    <td><span class="rank-badge">${index + 1}</span></td>
                    <td><code>${paramsText}</code></td>
                    <td class="${returnClass}">${res.netReturn >= 0 ? '+' : ''}${res.netReturn.toFixed(2)}%</td>
                    <td>${res.sharpe.toFixed(2)}</td>
                    <td class="${res.maxDrawdown >= 0.005 ? 'neg' : ''}">${res.maxDrawdown >= 0.005 ? '-' : ''}${res.maxDrawdown.toFixed(2)}%</td>
                    <td>${res.winRate.toFixed(1)}%</td>
                    <td>${res.trades}</td>
                </tr>
            `;
        });
        
        resultsPanel.innerHTML = `
            <div class="sb-opt-results">
                <h3 class="sb-section-title">Optimization Search Results</h3>
                <p class="sb-results-meta">Grid search evaluated ${results.length} permutations. Showing the top 15 settings ranked by chosen metric. Click any row to overlay its buy/sell trades on the main chart.</p>
                <div class="sb-table-wrapper">
                    <table class="sb-opt-table">
                        ${tableHeaderHtml}
                        <tbody>
                            ${rowsHtml}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
        
        const rows = resultsPanel.querySelectorAll('tbody tr');
        rows.forEach(row => {
            row.addEventListener('click', () => {
                rows.forEach(r => r.classList.remove('top-result'));
                row.classList.add('top-result');
                
                const idx = parseInt(row.dataset.idx);
                const chosenResult = topResults[idx];
                
                mapStrategyTradesToSignals(chosenResult.tradesList);
                scheduleRender(true);
                
                showToast(`Loaded strategy settings: ${row.querySelector('code').textContent}`, 'success');
            });
        });
        
        if (rows.length > 0) {
            rows[0].dispatchEvent(new Event('click'));
        }
    }
    
    function mapStrategyTradesToSignals(tradesList) {
        activeSignals = [];
        tradesList.forEach(t => {
            if (t.type === 'long') {
                activeSignals.push({ type: 'buy', index: t.entryIndex });
                activeSignals.push({ type: 'sell', index: t.exitIndex });
            } else {
                activeSignals.push({ type: 'sell', index: t.entryIndex });
                activeSignals.push({ type: 'buy', index: t.exitIndex });
            }
        });
    }
    
    function clearChartSignals() {
        activeSignals = [];
        if (priceChart) {
            const annotations = priceChart.options.plugins.annotation.annotations;
            for (let key in annotations) {
                if (key.startsWith('sig-')) {
                    delete annotations[key];
                }
            }
            priceChart.update('none');
        }
    }
    
    function updateResultsVisibility() {
        resultsPanel.innerHTML = '';
        livePnl.style.display = 'none';
        toggleInputs(false);
        if (currentMode === 'manual') {
            if (isChartLoaded) {
                btnExecute.disabled = false;
                currentCandleCount = 200;
                scheduleRender();
            } else {
                btnExecute.disabled = true;
            }
        } else if (currentMode === 'optimizer') {
            if (isChartLoaded) {
                btnOptimize.disabled = false;
                currentCandleCount = fetchedCandles.closes.length;
                scheduleRender();
            } else {
                btnOptimize.disabled = true;
            }
        } else if (currentMode === 'rules') {
            if (isChartLoaded) {
                btnRunRules.disabled = false;
                currentCandleCount = fetchedCandles.closes.length;
                scheduleRender();
            } else {
                btnRunRules.disabled = true;
            }
        }
    }

    // Custom Rule Row Creator
    function createRuleRow(container) {
        const row = document.createElement('div');
        row.className = 'rule-row';
        row.innerHTML = `
            <select class="sb-select rule-left" aria-label="Rule left operand">
                <option value="close">Close Price</option>
                <option value="rsi">RSI (14)</option>
                <option value="ema20">EMA 20</option>
                <option value="ema50">EMA 50</option>
                <option value="macd">MACD Line</option>
                <option value="signal">MACD Signal</option>
                <option value="upper">BB Upper</option>
                <option value="lower">BB Lower</option>
            </select>
            <select class="sb-select rule-operator" aria-label="Rule operator">
                <option value="gt">&gt;</option>
                <option value="lt">&lt;</option>
                <option value="crosses_above">Crosses Above</option>
                <option value="crosses_below">Crosses Below</option>
            </select>
            <div class="rule-right-container">
                <input type="number" class="sb-input rule-right-val" placeholder="Value… e.g. 50" aria-label="Rule target value">
                <select class="sb-select rule-right-indicator" style="display: none;" aria-label="Rule target indicator">
                    <option value="close">Close Price</option>
                    <option value="rsi">RSI (14)</option>
                    <option value="ema20">EMA 20</option>
                    <option value="ema50">EMA 50</option>
                    <option value="macd">MACD Line</option>
                    <option value="signal">MACD Signal</option>
                    <option value="upper">BB Upper</option>
                    <option value="lower">BB Lower</option>
                </select>
            </div>
            <button type="button" class="rule-type-toggle" aria-label="Toggle input type between value and indicator" title="Toggle value/indicator">🔄</button>
            <button type="button" class="rule-delete-btn" aria-label="Delete condition" title="Delete condition">✕</button>
        `;
        
        const toggle = row.querySelector('.rule-type-toggle');
        const rightVal = row.querySelector('.rule-right-val');
        const rightInd = row.querySelector('.rule-right-indicator');
        const deleteBtn = row.querySelector('.rule-delete-btn');
        
        let type = 'val';
        toggle.addEventListener('click', () => {
            if (type === 'val') {
                type = 'ind';
                rightVal.style.display = 'none';
                rightInd.style.display = 'block';
            } else {
                type = 'val';
                rightVal.style.display = 'block';
                rightInd.style.display = 'none';
            }
        });
        
        deleteBtn.addEventListener('click', () => {
            row.remove();
        });
        
        container.appendChild(row);
    }

    // Initialize custom rule triggers
    btnAddBuyRule.addEventListener('click', () => createRuleRow(buyRulesList));
    btnAddSellRule.addEventListener('click', () => createRuleRow(sellRulesList));
    
    rulesOppositeExit.addEventListener('change', () => {
        if (rulesOppositeExit.checked) {
            sellRulesList.style.display = 'none';
            document.getElementById('btn-add-sell-rule').style.display = 'none';
        } else {
            sellRulesList.style.display = 'flex';
            document.getElementById('btn-add-sell-rule').style.display = 'inline-block';
        }
    });

    rulesLeverage.addEventListener('input', () => {
        rulesLeverageDisplay.textContent = rulesLeverage.value + 'x';
    });

    // Custom Rules Backtester Engine
    function evaluateCustomRuleCondition(cond, i) {
        if (i < 1) return false;
        
        function getOperandValue(name, index) {
            if (name === 'close') return fetchedCandles.closes[index];
            if (name === 'rsi') return calculatedIndicators.rsi[index];
            if (name === 'ema20') return calculatedIndicators.ema20[index];
            if (name === 'ema50') return calculatedIndicators.ema50[index];
            if (name === 'macd') return calculatedIndicators.macd.macdLine[index];
            if (name === 'signal') return calculatedIndicators.macd.signalLine[index];
            if (name === 'upper') return calculatedIndicators.bb.upper[index];
            if (name === 'lower') return calculatedIndicators.bb.lower[index];
            return 0;
        }
        
        const leftVal = getOperandValue(cond.left, i);
        const rightVal = cond.rightType === 'val' 
            ? cond.rightVal 
            : getOperandValue(cond.rightInd, i);
            
        if (leftVal === null || rightVal === null || Number.isNaN(leftVal) || Number.isNaN(rightVal)) {
            return false;
        }
        
        if (cond.operator === 'gt') {
            return leftVal > rightVal;
        }
        if (cond.operator === 'lt') {
            return leftVal < rightVal;
        }
        
        // Crossover operations
        const prevLeftVal = getOperandValue(cond.left, i - 1);
        const prevRightVal = cond.rightType === 'val'
            ? cond.rightVal
            : getOperandValue(cond.rightInd, i - 1);
            
        if (prevLeftVal === null || prevRightVal === null || Number.isNaN(prevLeftVal) || Number.isNaN(prevRightVal)) {
            return false;
        }
        
        if (cond.operator === 'crosses_above') {
            return prevLeftVal <= prevRightVal && leftVal > rightVal;
        }
        if (cond.operator === 'crosses_below') {
            return prevLeftVal >= prevRightVal && leftVal < rightVal;
        }
        
        return false;
    }
    
    function parseRulesFromContainer(container) {
        const rows = container.querySelectorAll('.rule-row');
        const rules = [];
        rows.forEach(row => {
            const left = row.querySelector('.rule-left').value;
            const operator = row.querySelector('.rule-operator').value;
            const isValVisible = row.querySelector('.rule-right-val').style.display !== 'none';
            const rightType = isValVisible ? 'val' : 'ind';
            // Preserve NaN for empty fields so we can detect and warn the user
            const rightValStr = row.querySelector('.rule-right-val').value.trim();
            const rightVal = rightValStr === '' ? NaN : parseFloat(rightValStr);
            const rightInd = row.querySelector('.rule-right-indicator').value;
            
            rules.push({ left, operator, rightType, rightVal, rightInd });
        });
        return rules;
    }

    function runCustomStrategyBacktest() {
        const buyRules = parseRulesFromContainer(buyRulesList);
        const sellRules = rulesOppositeExit.checked ? [] : parseRulesFromContainer(sellRulesList);
        
        if (buyRules.length === 0) {
            showToast('Please add at least one Buy condition.', 'error');
            return;
        }
        if (!rulesOppositeExit.checked && sellRules.length === 0) {
            showToast('Please add at least one Sell condition or check "Exit when Buy rules are false".', 'error');
            return;
        }
        
        // Validate: catch empty value fields (they parse as NaN, which would silently never trigger)
        const allRules = [...buyRules, ...(rulesOppositeExit.checked ? [] : sellRules)];
        const hasEmptyValue = allRules.some(r => r.rightType === 'val' && isNaN(r.rightVal));
        if (hasEmptyValue) {
            showToast('One or more conditions have an empty value field. Please enter a number.', 'error');
            return;
        }
        
        const capitalSetting = parseFloat(rulesCapital.value) || 1000;
        const leverage = parseInt(rulesLeverage.value) || 5;
        const tpPercent = parseFloat(rulesTp.value) || null;
        const slPercent = parseFloat(rulesSl.value) || null;
        
        const closes = fetchedCandles.closes;
        const length = closes.length;
        
        let position = null;
        let capital = capitalSetting;
        const trades = [];
        const equity = new Array(length).fill(capitalSetting);
        
        const startIdx = 80;
        for (let i = 0; i < startIdx; i++) {
            equity[i] = capital;
        }
        
        for (let i = startIdx; i < length; i++) {
            const price = closes[i];
            
            // Evaluate conditions
            let buyTrigger = buyRules.length > 0;
            buyRules.forEach(rule => {
                if (!evaluateCustomRuleCondition(rule, i)) buyTrigger = false;
            });
            
            let sellTrigger = false;
            if (rulesOppositeExit.checked) {
                sellTrigger = !buyTrigger;
            } else {
                sellTrigger = sellRules.length > 0;
                sellRules.forEach(rule => {
                    if (!evaluateCustomRuleCondition(rule, i)) sellTrigger = false;
                });
            }
            
            // Trading engine
            if (position) {
                let exitPrice = price;
                let exitReason = null;
                
                if (position.type === 'long') {
                    if (tpPercent && (price - position.entryPrice) / position.entryPrice * 100 >= tpPercent) {
                        exitPrice = position.entryPrice * (1 + tpPercent / 100);
                        exitReason = 'Take Profit Hit';
                    } else if (slPercent && (position.entryPrice - price) / position.entryPrice * 100 >= slPercent) {
                        exitPrice = position.entryPrice * (1 - slPercent / 100);
                        exitReason = 'Stop Loss Hit';
                    } else if (sellTrigger) {
                        exitReason = 'Exit Signal';
                    }
                } else if (position.type === 'short') {
                    if (tpPercent && (position.entryPrice - price) / position.entryPrice * 100 >= tpPercent) {
                        exitPrice = position.entryPrice * (1 - tpPercent / 100);
                        exitReason = 'Take Profit Hit';
                    } else if (slPercent && (price - position.entryPrice) / position.entryPrice * 100 >= slPercent) {
                        exitPrice = position.entryPrice * (1 + slPercent / 100);
                        exitReason = 'Stop Loss Hit';
                    } else if (buyTrigger) {
                        exitReason = 'Exit Signal';
                    }
                }
                
                if (exitReason) {
                    let pnlPercent = 0;
                    if (position.type === 'long') {
                        pnlPercent = (exitPrice - position.entryPrice) / position.entryPrice;
                    } else {
                        pnlPercent = (position.entryPrice - exitPrice) / position.entryPrice;
                    }
                    
                    const positionSize = position.positionSize;
                    const entryFee = position.entryFee;
                    const exitFee = positionSize * 0.001;
                    const grossPnl = positionSize * pnlPercent;
                    const netTradePnl = grossPnl - entryFee - exitFee;
                    
                    capital += grossPnl - exitFee;
                    
                    trades.push({
                        type: position.type,
                        entryPrice: position.entryPrice,
                        exitPrice: exitPrice,
                        entryIndex: position.entryIndex,
                        exitIndex: i,
                        pnl: netTradePnl,
                        pnlPercent: (netTradePnl / capitalSetting) * 100,
                        reason: exitReason
                    });
                    
                    position = null;
                }
            } else {
                if (buyTrigger) {
                    const positionSize = capital * leverage;
                    const entryFee = positionSize * 0.001;
                    capital -= entryFee;
                    position = {
                        type: 'long',
                        entryPrice: price,
                        entryIndex: i,
                        entryFee: entryFee,
                        positionSize: positionSize
                    };
                } else if (sellTrigger && !rulesOppositeExit.checked) {
                    const positionSize = capital * leverage;
                    const entryFee = positionSize * 0.001;
                    capital -= entryFee;
                    position = {
                        type: 'short',
                        entryPrice: price,
                        entryIndex: i,
                        entryFee: entryFee,
                        positionSize: positionSize
                    };
                }
            }
            
            if (position) {
                let unrealizedPnlPercent = 0;
                if (position.type === 'long') {
                    unrealizedPnlPercent = (price - position.entryPrice) / position.entryPrice;
                } else {
                    unrealizedPnlPercent = (position.entryPrice - price) / position.entryPrice;
                }
                const positionSize = position.positionSize;
                equity[i] = capital + (positionSize * unrealizedPnlPercent);
            } else {
                equity[i] = capital;
            }
        }
        
        if (position) {
            const exitPrice = closes[length - 1];
            let pnlPercent = 0;
            if (position.type === 'long') {
                pnlPercent = (exitPrice - position.entryPrice) / position.entryPrice;
            } else {
                pnlPercent = (position.entryPrice - exitPrice) / position.entryPrice;
            }
            const positionSize = position.positionSize;
            const entryFee = position.entryFee;
            const exitFee = positionSize * 0.001;
            const grossPnl = positionSize * pnlPercent;
            const netTradePnl = grossPnl - entryFee - exitFee;
            
            capital += grossPnl - exitFee;
            trades.push({
                type: position.type,
                entryPrice: position.entryPrice,
                exitPrice: exitPrice,
                entryIndex: position.entryIndex,
                exitIndex: length - 1,
                pnl: netTradePnl,
                pnlPercent: (netTradePnl / capitalSetting) * 100,
                reason: 'Final Candle Close'
            });
            equity[length - 1] = capital;
        }
        
        const returns = [];
        for (let i = startIdx + 1; i < length; i++) {
            returns.push((equity[i] - equity[i-1]) / equity[i-1]);
        }
        
        let meanReturn = 0;
        let stdDev = 0;
        let sharpe = 0;
        
        if (returns.length > 0) {
            meanReturn = returns.reduce((a, b) => a + b, 0) / returns.length;
            const variance = returns.reduce((sum, val) => sum + Math.pow(val - meanReturn, 2), 0) / returns.length;
            stdDev = Math.sqrt(variance);
            if (stdDev > 0) {
                let annualFactor = 252;
                if (activeInterval === '5m') annualFactor = 252 * 288;
                else if (activeInterval === '15m') annualFactor = 252 * 96;
                else if (activeInterval === '1h') annualFactor = 252 * 24;
                else if (activeInterval === '4h') annualFactor = 252 * 6;
                else if (activeInterval === '1d') annualFactor = 252;
                
                sharpe = (meanReturn / stdDev) * Math.sqrt(annualFactor);
            }
        }
        
        let maxDd = 0;
        let peak = -Infinity;
        for (let i = 0; i < length; i++) {
            if (equity[i] > peak) peak = equity[i];
            const dd = (peak - equity[i]) / peak;
            if (dd > maxDd) maxDd = dd;
        }
        
        const netReturn = ((capital - capitalSetting) / capitalSetting) * 100;
        const calmar = maxDd > 0 ? (netReturn / (maxDd * 100)) : (netReturn / 0.01);
        
        const wins = trades.filter(t => t.pnl > 0).length;
        const winRate = trades.length > 0 ? (wins / trades.length) * 100 : 0;
        
        const result = {
            params: { customRules: true },
            finalCapital: capital,
            netReturn,
            sharpe,
            maxDrawdown: maxDd * 100,
            calmar,
            trades: trades.length,
            winRate,
            tradesList: trades
        };
        
        renderCustomRulesResults(result);
        mapStrategyTradesToSignals(trades);
        
        currentCandleCount = length;
        renderCharts();
        
        showToast('Custom strategy backtest completed!', 'success');
    }
    
    function renderCustomRulesResults(res) {
        const isWin = res.netReturn > 0;
        const badgeClass = isWin ? 'win' : 'loss';
        const cardClass = isWin ? 'win' : 'loss';
        const pnlClass = res.netReturn >= 0 ? 'pos' : 'neg';
        
        resultsPanel.innerHTML = `
            <div class="sb-result-card ${cardClass}">
                <div class="sb-result-header">
                    <span class="sb-result-badge ${badgeClass}">${isWin ? 'Win' : 'Loss'}</span>
                    <h3 class="sb-result-title">Custom Strategy Results</h3>
                    <p class="sb-result-subtitle">Backtest simulated successfully over ${fetchedCandles.closes.length - 80} candles</p>
                </div>
                <div class="sb-result-grid">
                    <div class="sb-result-stat">
                        <span class="sb-result-stat-label">Initial Capital</span>
                        <span class="sb-result-stat-value">$${parseFloat(rulesCapital.value).toFixed(2)}</span>
                    </div>
                    <div class="sb-result-stat">
                        <span class="sb-result-stat-label">Final Capital</span>
                        <span class="sb-result-stat-value">$${res.finalCapital.toFixed(2)}</span>
                    </div>
                    <div class="sb-result-stat">
                        <span class="sb-result-stat-label">Net Return</span>
                        <span class="sb-result-stat-value ${pnlClass}">${res.netReturn >= 0 ? '+' : ''}${res.netReturn.toFixed(2)}%</span>
                    </div>
                    <div class="sb-result-stat">
                        <span class="sb-result-stat-label">Sharpe Ratio</span>
                        <span class="sb-result-stat-value">${res.sharpe.toFixed(2)}</span>
                    </div>
                    <div class="sb-result-stat">
                        <span class="sb-result-stat-label">Max Drawdown</span>
                        <span class="sb-result-stat-value neg">-${res.maxDrawdown.toFixed(2)}%</span>
                    </div>
                    <div class="sb-result-stat">
                        <span class="sb-result-stat-label">Win Rate</span>
                        <span class="sb-result-stat-value">${res.winRate.toFixed(1)}%</span>
                    </div>
                    <div class="sb-result-stat">
                        <span class="sb-result-stat-label">Total Trades</span>
                        <span class="sb-result-stat-value">${res.trades}</span>
                    </div>
                    <div class="sb-result-stat">
                        <span class="sb-result-stat-label">Calmar Ratio</span>
                        <span class="sb-result-stat-value">${res.calmar.toFixed(2)}</span>
                    </div>
                    <div class="sb-result-stat">
                        <span class="sb-result-stat-label">Leverage</span>
                        <span class="sb-result-stat-value">${rulesLeverage.value}x</span>
                    </div>
                </div>
            </div>
        `;
    }

    btnRunRules.addEventListener('click', runCustomStrategyBacktest);
});
