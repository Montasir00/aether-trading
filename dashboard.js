document.addEventListener('DOMContentLoaded', () => {

  const readCssVar = (name, fallback = '') => {
    const value = getComputedStyle(document.body).getPropertyValue(name).trim();
    return value || fallback;
  };

  const getChartTheme = () => ({
    text: readCssVar('--chart-text', '#eaeaea'),
    grid: readCssVar('--chart-grid', 'rgba(255,255,255,0.2)'),
    priceLine: readCssVar('--chart-price-line', 'rgba(240,185,11,1)'),
    priceFill: readCssVar('--chart-price-fill', 'rgba(240,185,11,0.1)'),
    smaFast: readCssVar('--chart-sma-fast', '#3498db'),
    smaSlow: readCssVar('--chart-sma-slow', '#e74c3c'),
    rsi: readCssVar('--chart-rsi', '#9b59b6'),
    overbought: readCssVar('--chart-overbought', '#e74c3c'),
    oversold: readCssVar('--chart-oversold', '#2ecc71'),
    macdLine: readCssVar('--chart-macd-line', '#3498db'),
    signalLine: readCssVar('--chart-signal-line', '#e67e22'),
    histPos: readCssVar('--chart-hist-pos', 'rgba(46,204,113,0.6)'),
    histNeg: readCssVar('--chart-hist-neg', 'rgba(231,76,60,0.6)')
  });

  const panelStatus = {
    market: document.getElementById('market-panel-status'),
    risk: document.getElementById('risk-panel-status'),
    strategy: document.getElementById('strategy-panel-status')
  };

  const panelUpdated = {
    market: document.getElementById('market-last-updated'),
    risk: document.getElementById('risk-last-updated'),
    strategy: document.getElementById('strategy-last-updated')
  };

  function formatRelativeTime(date) {
    const diffMs = Date.now() - date.getTime();
    const diffMinutes = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMinutes / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffMinutes < 1) return 'just now';
    if (diffMinutes < 60) return `${diffMinutes}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    return `${diffDays}d ago`;
  }

  function setPanelState(panel, state, message) {
    const el = panelStatus[panel];
    if (!el) return;

    el.classList.remove('is-loading', 'is-ok', 'is-stale', 'is-error');
    el.classList.add(`is-${state}`);
    el.textContent = message;
  }

  function setLastUpdated(panel) {
    const el = panelUpdated[panel];
    if (!el) return;

    const now = new Date();
    const timeLabel = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    el.textContent = `Last updated: ${formatRelativeTime(now)}`;
    el.title = `Last updated at ${timeLabel}`;
  }

  // ============================================================
  //  THEME TOGGLE (EVENT DRIVEN)
  // ============================================================
  window.addEventListener('theme-changed', () => {
    if (activeSymbol) showIndicatorChart(activeSymbol);
    fetchRiskMetrics();
  });

  // ============================================================
  //  API FETCH WITH RETRY & RATE-LIMIT HANDLING
  // ============================================================
  async function fetchWithRetry(url, options = {}, retries = 3, delay = 1000) {
    for (let attempt = 1; attempt <= retries; attempt++) {
      try {
        const response = await fetch(url, options);

        // Handle Binance rate limit (HTTP 429)
        if (response.status === 429) {
          const retryAfter = parseInt(response.headers.get('Retry-After') || '5', 10);
          console.warn(`Rate limited. Retrying after ${retryAfter}s (attempt ${attempt}/${retries})`);
          await new Promise(r => setTimeout(r, retryAfter * 1000));
          continue;
        }

        // Handle server errors (5xx)
        if (response.status >= 500 && attempt < retries) {
          console.warn(`Server error ${response.status}. Retrying... (attempt ${attempt}/${retries})`);
          await new Promise(r => setTimeout(r, delay * attempt));
          continue;
        }

        if (!response.ok) {
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        return await response.json();
      } catch (err) {
        if (attempt === retries) {
          console.error(`Failed after ${retries} attempts:`, err.message);
          return null;
        }
        console.warn(`Fetch error, retrying... (attempt ${attempt}/${retries}):`, err.message);
        await new Promise(r => setTimeout(r, delay * attempt));
      }
    }
    return null;
  }

  // ============================================================
  //  BALANCE FETCHING
  // ============================================================
  async function fetchBalance() {
    const data = await fetchWithRetry('api/get_balance.php');
    if (data && 'balances' in data) {
      const grid = document.querySelector('.wallet-grid');
      if (grid) {
        grid.innerHTML = Object.entries(data.balances).map(([asset, balance]) => {
          let label = asset;
          let unit = '';
          let precision = 4;
          if (asset === 'USDT') {
            label = 'USDT';
            unit = ' USDT';
            precision = 2;
          } else if (asset === 'ETH') {
            label = 'ETH — Ethereum';
            unit = ' ETH';
            precision = 4;
          } else if (asset === 'XAU') {
            label = 'XAU — Gold';
            unit = ' oz';
            precision = 6;
          } else if (asset === 'XAG') {
            label = 'XAG — Silver <span class="proxy-tag" title="Priced via LTC proxy">proxy</span>';
            unit = ' oz';
            precision = 4;
          } else {
            // Stocks/other commodities
            label = asset + ' — Stock';
            unit = ' shares';
            precision = 4;
          }
          const formatted = parseFloat(balance).toFixed(precision);
          const price = data.prices && data.prices[asset] !== undefined ? parseFloat(data.prices[asset]) : 0;
          const val = data.valuations && data.valuations[asset] !== undefined ? parseFloat(data.valuations[asset]) : (parseFloat(balance) * price);
          
          let valuationHtml = '';
          if (asset !== 'USDT' && asset !== 'ETH') {
            const avgBuy = data.avg_buy_prices && data.avg_buy_prices[asset] ? parseFloat(data.avg_buy_prices[asset]) : 0;
            const pnl = data.pnls && data.pnls[asset] ? parseFloat(data.pnls[asset]) : 0;
            const pnlPct = data.pnl_percents && data.pnl_percents[asset] ? parseFloat(data.pnl_percents[asset]) : 0;
            
            let pnlColor = 'var(--text-secondary)';
            let pnlSign = '';
            if (pnl > 0) {
              pnlColor = 'var(--green)';
              pnlSign = '+';
            } else if (pnl < 0) {
              pnlColor = 'var(--red)';
            }

            const pnlHtml = avgBuy > 0 
              ? `<div style="font-size:0.78rem; font-weight:700; color:${pnlColor}; margin-top:2px;">
                  PnL: ${pnlSign}$${pnl.toFixed(2)} (${pnlSign}${pnlPct.toFixed(2)}%)
                 </div>`
              : `<div style="font-size:0.75rem; color:var(--text-muted); margin-top:2px;">No purchases</div>`;

            valuationHtml = `
              <div class="coin-valuation" style="font-size: 0.85rem; font-weight: 600; color: var(--accent); margin-top: var(--space-xs);">
                ≈ ${val.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} USDT
              </div>
              <div style="font-size:0.75rem; color:var(--text-secondary); margin-top:4px;">
                Avg Buy: ${avgBuy > 0 ? '$' + avgBuy.toFixed(2) : '—'}
              </div>
              ${pnlHtml}
            `;
          } else if (asset === 'ETH') {
            valuationHtml = `
              <div class="coin-valuation" style="font-size: 0.85rem; font-weight: 600; color: var(--accent); margin-top: var(--space-xs);">
                ≈ ${val.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} USDT
              </div>
              <div style="font-size:0.75rem; color:var(--text-muted); margin-top:4px;">
                Settlement Currency
              </div>
            `;
          } else {
            valuationHtml = `
              <div class="coin-valuation" style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-top: var(--space-xs);">
                Base Currency
              </div>
            `;
          }
          function getAssetIcon(assetCode) {
            return window.getAssetIcon ? window.getAssetIcon(assetCode) : '';
          }

          return `
            <div class="wallet-item">
                ${getAssetIcon(asset)}
                <div class="coin-details">
                    <div class="coin-label">${label}</div>
                    <div class="coin-value">${formatted}${unit}</div>
                    ${valuationHtml}
                </div>
            </div>
          `;
        }).join('');
      }
      
      const totalValEl = document.getElementById('portfolio-total-val');
      if (totalValEl && data.total_valuation !== undefined) {
        const totalUsdt = parseFloat(data.total_valuation);
        const ethPrice = parseFloat(data.eth_price) || 3000;
        const totalEth = totalUsdt / ethPrice;
        
        totalValEl.innerHTML = `Total: <span style="color: var(--accent); font-weight: 800;">${totalEth.toFixed(4)} ETH</span> <span style="font-size: 0.75rem; color: var(--text-secondary); margin-left: var(--space-xs);">≈ $${totalUsdt.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} USDT</span>`;
      }
      
      // Fallback element text assignments in case other parts of the script reference them
      const usdtEl = document.getElementById('usdt-amount');
      if (usdtEl) usdtEl.textContent = parseFloat(data.balance).toFixed(2) + ' USDT';
      const xauEl = document.getElementById('xau-amount');
      if (xauEl) xauEl.textContent = parseFloat(data.xau_balance).toFixed(6) + ' oz';
      const xagEl = document.getElementById('xag-amount');
      if (xagEl) xagEl.textContent = parseFloat(data.xag_balance).toFixed(4) + ' oz';
    }
  }

  // ============================================================
  //  RISK METRICS PANEL
  // ============================================================
  async function fetchRiskMetrics(isBackground = false) {
    if (!isBackground) setPanelState('risk', 'loading', 'Refreshing risk metrics...');

    const data = await fetchWithRetry('api/get_risk_metrics.php');
    if (!data) {
      setPanelState('risk', 'stale', 'Risk metrics unavailable. Showing last known values.');
      return;
    }

    if (data.error) {
      setPanelState('risk', 'error', 'Risk metrics service returned an error.');
      return;
    }

    const set = (id, val) => {
      const el = document.getElementById(id);
      if (el) el.textContent = val;
    };

    set('risk-portfolio-value', '$' + data.portfolio_value.toLocaleString());
    set('risk-btc-exposure', data.xau_exposure_percent + '%');
    set('risk-daily-volume', '$' + data.daily_volume_used.toLocaleString() + ' / $' + data.daily_volume_limit.toLocaleString());
    set('risk-daily-trades', data.daily_trades + ' / ' + data.daily_trades_limit);
    set('risk-drawdown', data.drawdown_percent + '%');

    const levelEl = document.getElementById('risk-level');
    if (levelEl) {
      levelEl.textContent = data.risk_level;
      levelEl.style.color =
        data.risk_level === 'HIGH' ? readCssVar('--risk-high', '#e74c3c') :
        data.risk_level === 'MEDIUM' ? readCssVar('--risk-medium', '#f39c12') :
        readCssVar('--risk-low', '#2ecc71');
    }

    setPanelState('risk', 'ok', 'Risk metrics synced');
    setLastUpdated('risk');
  }

  // ============================================================
  //  PRICE UPDATES (24h ticker) — via server-side proxy
  // ============================================================
  async function updatePrices(isBackground = false) {
    if (!isBackground) setPanelState('market', 'loading', 'Refreshing market data...');

    const data = await fetchWithRetry('api/api.php');
    if (!data) {
      setPanelState('market', 'stale', 'Market feed unavailable. Showing previous values.');
      return;
    }

    const xauData = data['XAUUSDT'];
    const xagData = data['XAGUSDT'];
    if (xauData?.error || xagData?.error) {
      setPanelState('market', 'error', 'Market feed returned incomplete data.');
      return;
    }

    if (xauData && xagData) {
      const xauPriceEl = document.getElementById('xau-price');
      if (xauPriceEl) xauPriceEl.textContent = `$${parseFloat(xauData.lastPrice).toFixed(2)}`;
      const xagPriceEl = document.getElementById('xag-price');
      if (xagPriceEl) xagPriceEl.textContent = `$${parseFloat(xagData.lastPrice).toFixed(2)}`;
      
      const rows = Object.entries(data).map(([symbol, d]) => {
        if (d.error) return '';
        let name = symbol.replace('USDT', '');
        let volumeSuffix = ' oz';
        if (name === 'XAU') {
          name = 'Gold (XAU)';
          volumeSuffix = ' oz';
        } else if (name === 'XAG') {
          name = 'Silver (XAG)';
          volumeSuffix = ' oz';
        } else if (['AAPL', 'TSLA', 'MSFT', 'AMZN', 'GOOG', 'NVDA', 'META', 'NFLX', 'AMD', 'BABA'].includes(name)) {
          volumeSuffix = ' shares';
        } else {
          volumeSuffix = ' units';
        }
        
        return `
          <tr>
            <td>${name}</td>
            <td>$${parseFloat(d.lastPrice).toFixed(2)}</td>
            <td>$${parseFloat(d.highPrice).toFixed(2)}</td>
            <td>$${parseFloat(d.lowPrice).toFixed(2)}</td>
            <td>${parseFloat(d.volume).toFixed(2)}${volumeSuffix}</td>
          </tr>
        `;
      }).join('');

      const marketDataEl = document.getElementById('market-data');
      if (marketDataEl) marketDataEl.innerHTML = rows;

      setPanelState('market', 'ok', 'Live commodity data synced');
      setLastUpdated('market');
    }
  }


  // ============================================================
  //  TECHNICAL INDICATOR CHARTS (RSI, MACD, SMA overlays)
  // ============================================================
  let priceChart = null;
  let rsiChart   = null;
  let macdChart  = null;
  let activeSymbol = null;
  let indicatorRequestSeq = 0;
  let technicalSymbols = [];
  let activeForecast = null; // Phase 11 state holder

  function renderTechnicalSymbols(items) {
    const select = document.getElementById('technical-symbol-select');
    const summary = document.getElementById('technical-symbol-summary');
    if (!select) return;

    if (!Array.isArray(items) || items.length === 0) {
      technicalSymbols = [];
      select.innerHTML = '<option value="">No selectable assets available</option>';
      select.disabled = true;
      if (summary) summary.textContent = 'No selectable assets available.';
      return;
    }

    technicalSymbols = items.slice(0, 40).map(item => {
      const symbol = String(item.symbol || '').toUpperCase(); // Now correctly OILUSDT
      const name = String(item.name || symbol.replace('USDT', ''));
      const change = Number(item.price_change_24h ?? 0);
      const changeText = `${change >= 0 ? '+' : ''}${change.toFixed(2)}%`;
      return { symbol, name, changeText, displaySymbol: symbol.replace('USDT', '') };
    });

    select.disabled = false;
    select.innerHTML = technicalSymbols.map(item => {
      const selected = activeSymbol === item.symbol;
      return `<option value="${item.symbol}"${selected ? ' selected' : ''}>${item.displaySymbol} — ${item.name} (${item.changeText})</option>`;
    }).join('');

    if (!select.dataset.bound) {
      select.addEventListener('change', () => {
        if (select.value) {
          activeForecast = null; // Clear forecast on asset change (Phase 14)
          document.getElementById('forecast-summary-widget').style.display = 'none';
          showIndicatorChart(select.value);
        }
      });
      select.dataset.bound = '1';
    }

    const selected = technicalSymbols.find(item => item.symbol === activeSymbol) || technicalSymbols[0];
    if (selected) {
      select.value = selected.symbol;
      if (summary) summary.textContent = `${selected.name} (${selected.symbol}) is selected.`;
      if (!activeSymbol || activeSymbol !== selected.symbol) {
        showIndicatorChart(selected.symbol);
      }
    }
  }

  // Re-render when interval or indicator checkboxes change
  const intervalSelect = document.getElementById('chart-interval');
  if (intervalSelect) {
    intervalSelect.addEventListener('change', () => {
      activeForecast = null; // Clear forecast on interval change
      document.getElementById('forecast-summary-widget').style.display = 'none';
      if (activeSymbol) showIndicatorChart(activeSymbol);
    });
  }
  document.getElementById('show-sma')?.addEventListener('change', () => { if (activeSymbol) showIndicatorChart(activeSymbol); });
  document.getElementById('show-rsi')?.addEventListener('change', () => { if (activeSymbol) showIndicatorChart(activeSymbol); });
  document.getElementById('show-macd')?.addEventListener('change', () => { if (activeSymbol) showIndicatorChart(activeSymbol); });

  // AI Forecast Controls & Observers (Phase 1-3)
  const enableForecastCheckbox = document.getElementById('enable-forecast');
  if (enableForecastCheckbox) {
    enableForecastCheckbox.addEventListener('change', () => {
      const forecastPanel = document.getElementById('ai-forecast-panel');
      const accuracyPanel = document.getElementById('ai-accuracy-panel');
      
      if (enableForecastCheckbox.checked) {
        if (forecastPanel) forecastPanel.classList.add('visible');
        if (accuracyPanel) accuracyPanel.classList.add('visible');
        fetchAccuracyLogs(activeSymbol);
      } else {
        if (forecastPanel) forecastPanel.classList.remove('visible');
        if (accuracyPanel) accuracyPanel.classList.remove('visible');
        activeForecast = null;
        if (activeSymbol) showIndicatorChart(activeSymbol);
      }
    });
  }

  // Generate Forecast button click orchestrator (Phase 3)
  const btnGenerateForecast = document.getElementById('btn-generate-forecast');
  if (btnGenerateForecast) {
    btnGenerateForecast.addEventListener('click', async () => {
      if (!activeSymbol) return;

      const windowVal = document.getElementById('forecast-window').value;
      const horizonVal = document.getElementById('forecast-horizon').value;
      const intervalVal = document.getElementById('chart-interval').value;
      
      const loader = document.getElementById('forecast-loader');
      const loaderText = document.getElementById('forecast-loader-text');
      const summaryWidget = document.getElementById('forecast-summary-widget');
      
      if (loader) loader.style.display = 'flex';
      if (summaryWidget) summaryWidget.style.display = 'none';
      
      // Step-by-step progressive loading animation to wow the user (Phase 3)
      const steps = [
        "Loading historical prices…",
        "Analyzing price momentum…",
        "Running forecasting model…",
        "Analyzing news sentiment…",
        "Calculating price ranges…",
        "Saving forecast results…"
      ];
      
      for (let i = 0; i < steps.length; i++) {
        if (loaderText) loaderText.textContent = steps[i];
        await new Promise(r => setTimeout(r, 400)); // smooth progressive delay
      }
      
      try {
        const url = `api/get_ai_forecast.php?symbol=${activeSymbol}&interval=${intervalVal}&input_window=${windowVal}&forecast_horizon=${horizonVal}`;
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.error) {
          alert(`Forecast failed: ${data.error}`);
          if (loader) loader.style.display = 'none';
          return;
        }
        
        // Save forecast state (Phase 11)
        activeForecast = data;
        
        // Update summary widget (Phase 13)
        document.getElementById('forecast-cache-badge').textContent = data.cache_status;
        const dirEl = document.getElementById('widget-direction');
        dirEl.textContent = data.summary.direction;
        dirEl.className = 'metric-value ' + (data.summary.direction === 'BULLISH' ? 'direction-bullish' : (data.summary.direction === 'BEARISH' ? 'direction-bearish' : 'direction-neutral'));
        
        document.getElementById('widget-move').textContent = `${data.summary.move_percent >= 0 ? '+' : ''}${data.summary.move_percent}%`;
        document.getElementById('widget-confidence').textContent = data.summary.confidence;
        document.getElementById('widget-volatility').textContent = `${data.summary.volatility_24h}%`;
        document.getElementById('widget-timestamp').textContent = data.calculated_at;
        
        if (loader) loader.style.display = 'none';
        if (summaryWidget) summaryWidget.style.display = 'block';
        
        // Re-render priceChart to show predictions (Phase 12)
        if (activeSymbol) showIndicatorChart(activeSymbol);
        
        // Refresh accuracy logs (Phase 15)
        fetchAccuracyLogs(activeSymbol);
        
      } catch (err) {
        console.error('Forecast generation failed:', err);
        alert('Forecast generation failed. Please try again.');
        if (loader) loader.style.display = 'none';
      }
    });
  }

  // Phase 15: Accuracy Log Fetcher
  async function fetchAccuracyLogs(symbol) {
    const tableBody = document.getElementById('accuracy-table-body');
    const tableContainer = document.getElementById('accuracy-table-container');
    const loadingHint = document.getElementById('accuracy-loading');
    
    if (!symbol) return;
    if (loadingHint) {
      loadingHint.style.display = 'block';
      loadingHint.textContent = 'Syncing validation logs...';
    }
    if (tableContainer) tableContainer.style.display = 'none';
    
    try {
      const url = `api/get_accuracy_logs.php?symbol=${symbol}`;
      const response = await fetch(url);
      const data = await response.json();
      
      if (data.status === 'OK' && data.logs.length > 0) {
        tableBody.innerHTML = data.logs.map(log => {
          let accuracyStatus = '<span class="g-badge g-badge-yellow">PENDING</span>';
          let errorText = '—';
          let dirMatchText = '—';
          
          if (log.realized === 1) {
            const isCorrect = log.direction_correct === 1;
            accuracyStatus = isCorrect 
              ? '<span class="g-badge g-badge-green">SUCCESS</span>' 
              : '<span class="g-badge g-badge-red">FAILED</span>';
            errorText = `$${log.mae.toFixed(2)}`;
            dirMatchText = isCorrect ? 'MATCH' : 'MISMATCH';
          }
          
          return `
            <tr>
              <td>#${log.id}</td>
              <td><strong>${log.symbol.replace('USDT', '')}</strong></td>
              <td>${log.interval}</td>
              <td style="font-weight:700; color:${log.direction === 'BULLISH' ? 'var(--green)' : (log.direction === 'BEARISH' ? 'var(--red)' : 'var(--blue)')};">
                ${log.direction} (${dirMatchText})
              </td>
              <td>${log.predicted_change >= 0 ? '+' : ''}${log.predicted_change.toFixed(2)}%</td>
              <td>${errorText}</td>
              <td>${accuracyStatus}</td>
            </tr>
          `;
        }).join('');
        
        if (loadingHint) loadingHint.style.display = 'none';
        if (tableContainer) tableContainer.style.display = 'block';
      } else {
        if (loadingHint) {
          loadingHint.style.display = 'block';
          loadingHint.textContent = 'No historical accuracy logs found for this symbol yet.';
        }
      }
    } catch (err) {
      console.error('Accuracy logs sync failed:', err);
      if (loadingHint) {
        loadingHint.style.display = 'block';
        loadingHint.textContent = 'Failed to sync accuracy database.';
      }
    }
  }

  async function showIndicatorChart(symbol) {
    activeSymbol = symbol;
    const requestSeq = ++indicatorRequestSeq;
    const chartTheme = getChartTheme();
    const interval = document.getElementById('chart-interval')?.value || '1h';
    const showSMA  = document.getElementById('show-sma')?.checked ?? false;
    const showRSI  = document.getElementById('show-rsi')?.checked ?? false;
    const showMACD = document.getElementById('show-macd')?.checked ?? false;
    const enableAI = document.getElementById('enable-ai')?.checked ?? false;

    const chartContainer = document.getElementById('technical-chart-container');
    const chartTitle = document.getElementById('technical-chart-title');
    const symbolSelect = document.getElementById('technical-symbol-select');
    const symbolSummary = document.getElementById('technical-symbol-summary');
    if (chartContainer) chartContainer.style.display = 'block';
    if (chartTitle) chartTitle.textContent = `${symbol.replace('USDT', '')} / USDT`;
    const rsiCont = document.getElementById('rsi-chart-container');
    const macdCont = document.getElementById('macd-chart-container');
    if (rsiCont) {
      if (showRSI) rsiCont.classList.add('visible');
      else rsiCont.classList.remove('visible');
    }
    if (macdCont) {
      if (showMACD) macdCont.classList.add('visible');
      else macdCont.classList.remove('visible');
    }

    if (symbolSelect && symbolSelect.value !== symbol) {
      symbolSelect.value = symbol;
    }
    if (symbolSummary) {
      const selected = technicalSymbols.find(item => item.symbol === symbol);
      symbolSummary.textContent = selected
        ? `${selected.name} (${selected.displaySymbol}) • ${selected.changeText} today`
        : `${symbol.replace('USDT', '')} is selected.`;
    }

    const loadingHint = document.getElementById('chart-loading-hint');
    if (loadingHint) loadingHint.textContent = `Loading ${symbol.replace('USDT', '')} indicators...`;

    // Fetch indicator data from server and defensively render
    let data;
    try {
      data = await fetchWithRetry(`api/get_indicators.php?symbol=${symbol}&interval=${interval}`);
      if (requestSeq !== indicatorRequestSeq) return;
      if (!data || data.error) {
        console.error('Indicator fetch failed:', data?.error);
        if (loadingHint) loadingHint.textContent = 'Indicator data unavailable.';
        return;
      }

      // Basic structure validation
      if (!data.labels || !data.ohlcv || !data.indicators) {
        console.error('Indicator payload missing fields:', data);
        if (loadingHint) loadingHint.textContent = 'Indicator data malformed.';
        return;
      }

      // Ensure Chart.js is present
      if (typeof Chart === 'undefined') {
        console.error('Chart.js not loaded');
        if (loadingHint) loadingHint.textContent = 'Charting library unavailable.';
        return;
      }

      // Ensure canvas context available
      const canvas = document.getElementById('technicalChart');
      if (!canvas || typeof canvas.getContext !== 'function') {
        console.error('Chart canvas not found or unavailable');
        if (loadingHint) loadingHint.textContent = 'Chart canvas missing.';
        return;
      }
    } catch (err) {
      console.error('Indicator fetch error:', err);
      if (loadingHint) loadingHint.textContent = 'Failed to fetch indicators.';
      return;
    }

    // --- Price chart with SMA overlays ---
    let ctx;
    try {
      ctx = document.getElementById('technicalChart').getContext('2d');
    } catch (err) {
      console.error('Failed to get chart context:', err);
      if (loadingHint) loadingHint.textContent = 'Unable to render chart.';
      return;
    }

    if (priceChart) priceChart.destroy();

    // --- AI Pattern Recognition Logic ---
    let annotations = {};
    if (enableAI && typeof AetherEngine !== 'undefined') {
        const pivots = AetherEngine.findPivots(data.ohlcv.close, 5);
        const levels = AetherEngine.detectLevels(pivots);
        
        levels.forEach((level, idx) => {
            annotations[`ai_level_${idx}`] = {
                type: 'line',
                yMin: level.price,
                yMax: level.price,
                borderColor: level.type === 'resistance' ? 'oklch(0.68 0.12 55 / 0.8)' : 'oklch(0.68 0.12 55 / 0.5)',
                borderWidth: level.touches >= 3 ? 2 : 1,
                borderDash: level.type === 'resistance' ? [2, 2] : [6, 4],
                label: {
                    display: true,
                    content: `${level.type.toUpperCase()} (${level.touches}x)`,
                    position: 'end',
                    backgroundColor: 'oklch(0.14 0.01 55 / 0.8)',
                    color: 'oklch(0.68 0.12 55)',
                    font: { size: 9, weight: 'bold' }
                }
            };
        });

        // Add a "Confidence" status hint to the UI
        const hint = document.getElementById('chart-loading-hint');
        if (hint) {
            const shapes = AetherEngine.scanShapes(pivots, data.ohlcv.close[data.ohlcv.close.length - 1]);
            if (shapes.length > 0) {
                hint.innerHTML = `<span style="color:var(--accent);font-weight:700;">Pattern Insight:</span> ${shapes[0].name} detected with ${shapes[0].confidence}% confidence.`;
            } else {
                hint.innerHTML = `<span style="color:var(--accent);font-weight:700;">Pivots Active:</span> Scanning for geometric levels...`;
            }
        }
    } else {
        const hintEl = document.getElementById('chart-loading-hint');
        if (hintEl) hintEl.innerHTML = 'Market streams synchronized.';
    }

    // Helper to format timestamp to local timezone string
    const formatTimestamp = (ts) => {
      const d = new Date(ts);
      const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
      const month = months[d.getMonth()];
      const day = d.getDate().toString().padStart(2, '0');
      const hours = d.getHours().toString().padStart(2, '0');
      const minutes = d.getMinutes().toString().padStart(2, '0');

      if (interval === '1d') {
        return `${month} ${day}`;
      } else if (interval === '1h' || interval === '4h') {
        return `${month} ${day} ${hours}:${minutes}`;
      } else {
        return `${hours}:${minutes}`;
      }
    };

    const historicalLabels = data.timestamps ? data.timestamps.map(ts => formatTimestamp(ts)) : [...data.labels];
    const isForecastActive = activeForecast && activeForecast.symbol === symbol && activeForecast.interval === interval;
    const chartLabels = [...historicalLabels];
    const realPrices = [...data.ohlcv.close];

    if (isForecastActive) {
      if (activeForecast.future_timestamps && activeForecast.future_timestamps.length > 0) {
        chartLabels.push(...activeForecast.future_timestamps.map(ts => formatTimestamp(ts)));
      } else {
        chartLabels.push(...activeForecast.future_labels);
      }
      for (let i = 0; i < activeForecast.predictions.length; i++) {
        realPrices.push(null);
      }
      // Add trailing empty labels to provide visual breathing room and prevent forecast crashing into the right edge
      chartLabels.push("", "");
    }

    const datasets = [{
      label: `${symbol.replace('USDT', '')}/USDT Close`,
      data: realPrices,
      borderColor: chartTheme.priceLine,
      backgroundColor: chartTheme.priceFill,
      fill: true,
      tension: 0.1,
      pointRadius: 0,
      borderWidth: 2
    }];

    if (isForecastActive) {
      // Build prediction sequences connected to the last closed price
      const predSequence = [];
      const lowerSequence = [];
      const upperSequence = [];

      for (let i = 0; i < data.ohlcv.close.length - 1; i++) {
        predSequence.push(null);
        lowerSequence.push(null);
        upperSequence.push(null);
      }

      // Connection point
      const lastClose = data.ohlcv.close[data.ohlcv.close.length - 1];
      predSequence.push(lastClose);
      lowerSequence.push(lastClose);
      upperSequence.push(lastClose);

      // Predictions and bands
      predSequence.push(...activeForecast.predictions);
      lowerSequence.push(...activeForecast.lower_bounds);
      upperSequence.push(...activeForecast.upper_bounds);

      datasets.push({
        label: 'TimesFM Forecast',
        data: predSequence,
        borderColor: 'var(--blue)',
        borderDash: [5, 5],
        borderWidth: 2,
        pointRadius: 3,
        pointBackgroundColor: 'var(--blue)',
        fill: false,
        tension: 0.15
      });

      datasets.push({
        label: 'Confidence Lower Bound',
        data: lowerSequence,
        borderColor: 'rgba(41, 182, 246, 0.12)',
        borderWidth: 1,
        pointRadius: 0,
        fill: false
      });

      datasets.push({
        label: '95% Confidence Interval',
        data: upperSequence,
        borderColor: 'rgba(41, 182, 246, 0.12)',
        borderWidth: 1,
        pointRadius: 0,
        fill: '-1', // fills down to the previous dataset (Confidence Lower Bound)
        backgroundColor: 'rgba(41, 182, 246, 0.05)'
      });
    }

    if (showSMA) {
      datasets.push({
        label: 'SMA(20)',
        data: data.indicators.sma20,
        borderColor: chartTheme.smaFast,
        borderWidth: 1.5,
        pointRadius: 0,
        fill: false,
        tension: 0.2
      });
      datasets.push({
        label: 'SMA(50)',
        data: data.indicators.sma50,
        borderColor: chartTheme.smaSlow,
        borderWidth: 1.5,
        pointRadius: 0,
        fill: false,
        tension: 0.2
      });
    }

    priceChart = new Chart(ctx, {
      type: 'line',
      data: { labels: chartLabels, datasets },
      options: {
        responsive: true,
        layout: {
          padding: {
            right: 25
          }
        },
        interaction: { mode: 'index', intersect: false },
        plugins: { 
            legend: { labels: { color: chartTheme.text } },
            annotation: { annotations }
        },
        scales: {
          x: {
            ticks: { color: chartTheme.text, maxTicksLimit: 20 },
            grid: { color: chartTheme.grid }
          },
          y: {
            ticks: { color: chartTheme.text },
            grid: { color: chartTheme.grid }
          }
        }
      }
    });

    // --- RSI chart ---
    if (showRSI) {
      const rsiCtx = document.getElementById('rsiChart').getContext('2d');
      if (rsiChart) rsiChart.destroy();

      rsiChart = new Chart(rsiCtx, {
        type: 'line',
        data: {
          labels: historicalLabels,
          datasets: [{
            label: 'RSI (14)',
            data: data.indicators.rsi,
            borderColor: chartTheme.rsi,
            borderWidth: 1.5,
            pointRadius: 0,
            fill: false,
            tension: 0.2
          }]
        },
        options: {
          responsive: true,
          interaction: { mode: 'index', intersect: false },
          plugins: {
            legend: { labels: { color: chartTheme.text } },
            annotation: {
              annotations: {
                overbought: {
                  type: 'line', yMin: 70, yMax: 70,
                  borderColor: chartTheme.overbought, borderWidth: 1, borderDash: [5, 5],
                  label: { display: true, content: 'Overbought (70)', position: 'end', color: chartTheme.overbought, font: { size: 10 } }
                },
                oversold: {
                  type: 'line', yMin: 30, yMax: 30,
                  borderColor: chartTheme.oversold, borderWidth: 1, borderDash: [5, 5],
                  label: { display: true, content: 'Oversold (30)', position: 'end', color: chartTheme.oversold, font: { size: 10 } }
                }
              }
            }
          },
          scales: {
            x: {
              ticks: { color: chartTheme.text, maxTicksLimit: 20 },
              grid: { color: chartTheme.grid }
            },
            y: {
              min: 0,
              max: 100,
              ticks: { color: chartTheme.text },
              grid: { color: chartTheme.grid }
            }
          }
        }
      });
    }
    if (!showRSI && rsiChart) {
      rsiChart.destroy();
      rsiChart = null;
    }

    // --- MACD chart ---
    if (showMACD) {
      const macdCtx = document.getElementById('macdChart').getContext('2d');
      if (macdChart) macdChart.destroy();

      // Build histogram colors (green when positive, red when negative)
      const histColors = data.indicators.histogram.map(v =>
        v === null ? 'transparent' : (v >= 0 ? chartTheme.histPos : chartTheme.histNeg)
      );

      // Find min and max of MACD line, signal line, and histogram to set a logical scale
      const allVals = [
        ...(data.indicators.macd_line || []),
        ...(data.indicators.signal_line || []),
        ...(data.indicators.histogram || [])
      ].filter(v => v !== null && !isNaN(v));

      let yMin = undefined;
      let yMax = undefined;
      if (allVals.length > 0) {
        const minVal = Math.min(...allVals);
        const maxVal = Math.max(...allVals);
        const absMax = Math.max(Math.abs(minVal), Math.abs(maxVal));
        
        // If the values are extremely small/flat near 0, bound the scale to [-1, 1] or [-2, 2]
        // to prevent Chart.js from autoscaling to huge default ranges (like -200 to 200)
        if (absMax < 0.1) {
          yMin = -1;
          yMax = 1;
        } else if (absMax < 1.0) {
          yMin = -2;
          yMax = 2;
        } else {
          // Add 10% padding
          yMin = minVal - (absMax * 0.1);
          yMax = maxVal + (absMax * 0.1);
        }
      }

      macdChart = new Chart(macdCtx, {
        type: 'bar',
        data: {
          labels: historicalLabels,
          datasets: [
            {
              label: 'MACD Histogram',
              data: data.indicators.histogram,
              backgroundColor: histColors,
              borderWidth: 0,
              type: 'bar',
              order: 2
            },
            {
              label: 'MACD Line',
              data: data.indicators.macd_line,
              borderColor: chartTheme.macdLine,
              borderWidth: 1.5,
              pointRadius: 0,
              fill: false,
              type: 'line',
              order: 1
            },
            {
              label: 'Signal Line',
              data: data.indicators.signal_line,
              borderColor: chartTheme.signalLine,
              borderWidth: 1.5,
              pointRadius: 0,
              fill: false,
              type: 'line',
              order: 1
            }
          ]
        },
        options: {
          responsive: true,
          interaction: { mode: 'index', intersect: false },
          plugins: { legend: { labels: { color: chartTheme.text } } },
          scales: {
            x: {
              ticks: { color: chartTheme.text, maxTicksLimit: 20 },
              grid: { color: chartTheme.grid }
            },
            y: {
              min: yMin,
              max: yMax,
              ticks: { color: chartTheme.text },
              grid: { color: chartTheme.grid }
            }
          }
        }
      });
    }
    if (!showMACD && macdChart) {
      macdChart.destroy();
      macdChart = null;
    }
  }

  document.getElementById('enable-ai')?.addEventListener('change', () => { if (activeSymbol) showIndicatorChart(activeSymbol); });

  async function fetchStrategyStatus(isBackground = false) {
    if (!isBackground) setPanelState('strategy', 'loading', 'Refreshing strategy status...');

    const data = await fetchWithRetry('trading_engine/StrategyController.php?execute=0');
    if (!data) {
      setPanelState('strategy', 'stale', 'Strategy status unavailable. Showing previous values.');
      return;
    }

    const setInner = (id, val) => { const el = document.getElementById(id); if (el) el.innerText = val; };

    if (data.status === 'STRATEGY_DISABLED') {
      setInner('strategy-ui-status', 'INACTIVE');
      setInner('strategy-signal',    '-');
      setInner('strategy-sma50',     '-');
      setInner('strategy-sma200',    '-');
      setInner('strategy-action',    '-');
      setPanelState('strategy', 'stale', 'Strategy is currently disabled.');
      setLastUpdated('strategy');
      return;
    }

    setInner('strategy-ui-status', 'ACTIVE');
    setInner('strategy-signal',    data.signal ?? '-');
    setInner('strategy-sma200',    data.sma200 !== null ? data.sma200.toFixed(2) : '-');
    setInner('strategy-sma50',     data.sma50  !== null ? data.sma50.toFixed(2)  : '-');
    setInner('strategy-action',    data.execution ?? '-');

    setPanelState('strategy', 'ok', 'Strategy status synced');
    setLastUpdated('strategy');
  }

  // ============================================================
  //  MARKET PULSE (SENTIMENT + HEATMAP + NEWS)
  // ============================================================
  async function updateMarketPulse() {
    // 1. Fetch Top 20 Market Data for Heatmap
    // For commodities we use the same Binance 24h ticker for XAU and XAG heatmap
    const xauTicker = await fetchWithRetry('api/api.php');
    if (xauTicker) {
      const marketData = Object.entries(xauTicker).map(([sym, d]) => ({
            symbol: sym, // keep full symbol 'OILUSDT' for indicator api
            name: sym === 'XAUUSDT' ? 'Gold' : (sym === 'XAGUSDT' ? 'Silver' : sym.replace('USDT', '')),
            current_price: parseFloat(d.lastPrice || 0),
            price_change_24h: parseFloat(d.priceChangePercent || 0),
            market_cap: parseFloat(d.quoteVolume || 0)
        }));

      const heatmapData = [...marketData].sort((a, b) => Math.abs(b.price_change_24h) - Math.abs(a.price_change_24h));

        // Always populate the TA asset selector (works on Trade page too)
      renderTechnicalSymbols(marketData);

        // Only render heatmap when heatmap.js is loaded (dashboard only)
        if (typeof AetherHeatmap !== 'undefined') {
            AetherHeatmap.render('market-heatmap', heatmapData);
        }

    }

      // The dashboard now only shows the heatmap; sentiment and news live on the Sentiment page.
  }

  // ============================================================
  //  VISIBILITY-AWARE POLLING SCHEDULER
  // ============================================================
  const scheduler = {
    timers: []
  };

  function addPollingTask(fn, ms) {
    let inFlight = false;
    const timerId = setInterval(async () => {
      if (document.visibilityState !== 'visible') return;
      if (inFlight) return;
      inFlight = true;
      try {
        await fn();
      } catch (err) {
        console.error('Polling task failed:', err);
      } finally {
        inFlight = false;
      }
    }, ms);
    scheduler.timers.push(timerId);
  }

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      // Run a fast refresh when user returns to the tab.
      updatePrices();
      fetchRiskMetrics();
      fetchStrategyStatus();
    } else {
      setPanelState('market', 'stale', 'Paused while tab is inactive.');
      setPanelState('risk', 'stale', 'Paused while tab is inactive.');
      setPanelState('strategy', 'stale', 'Paused while tab is inactive.');
    }
  });

  // ============================================================
  //  INITIAL LOAD & PERIODIC REFRESH
  // ============================================================
  updateMarketPulse(); // Run this first for immediate visual feedback
  updatePrices();
  fetchBalance();
  fetchRiskMetrics();
  fetchStrategyStatus();

  addPollingTask(() => updatePrices(true), 5000);
  addPollingTask(() => fetchBalance(true), 15000);
  addPollingTask(() => fetchRiskMetrics(true), 10000);
  addPollingTask(() => fetchStrategyStatus(true), 5000);
  addPollingTask(() => updateMarketPulse(true), 60000);
});
