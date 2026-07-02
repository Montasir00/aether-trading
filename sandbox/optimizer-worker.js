// Optimizer worker for sandbox
self.addEventListener('message', (ev) => {
    const msg = ev.data;
    if (msg.type === 'start') {
        const { strategy, metric, closes, activeInterval } = msg.payload;
        runOptimization(strategy, metric, closes, activeInterval);
    }
});

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
        avgGain += gains[i] || 0;
        avgLoss += losses[i] || 0;
    }
    avgGain /= period;
    avgLoss /= period;

    for (let i = 0; i < period; i++) rsi.push(null);

    rsi.push(avgLoss === 0 ? 100 : 100 - (100 / (1 + avgGain / avgLoss)));

    for (let i = period; i < gains.length; i++) {
        avgGain = (avgGain * (period - 1) + (gains[i] || 0)) / period;
        avgLoss = (avgLoss * (period - 1) + (losses[i] || 0)) / period;
        rsi.push(avgLoss === 0 ? 100 : 100 - (100 / (1 + avgGain / avgLoss)));
    }

    return rsi;
}

function calculateMACD(closes) {
    const ema12 = calculateEMA(closes, 12);
    const ema26 = calculateEMA(closes, 26);
    const macdLine = [];
    for (let i = 0; i < closes.length; i++) {
        if (ema12[i] === null || ema26[i] === null) macdLine.push(null);
        else macdLine.push(ema12[i] - ema26[i]);
    }
    const validMacd = macdLine.filter(x => x !== null);
    const validSignal = calculateEMA(validMacd, 9);
    const signalLine = [];
    let sigIdx = 0;
    for (let i = 0; i < closes.length; i++) {
        if (macdLine[i] === null) signalLine.push(null);
        else signalLine.push(validSignal[sigIdx++]);
    }
    const histogram = macdLine.map((v, i) => (v === null || signalLine[i] === null) ? null : v - signalLine[i]);
    return { macdLine, signalLine, histogram };
}

function calculateBB(closes, period = 20, multiplier = 2) {
    const basis = [], upper = [], lower = [];
    for (let i = 0; i < closes.length; i++) {
        if (i < period - 1) { basis.push(null); upper.push(null); lower.push(null); }
        else {
            const slice = closes.slice(i - period + 1, i + 1);
            const mean = slice.reduce((a, b) => a + b, 0) / period;
            basis.push(mean);
            const variance = slice.reduce((a, b) => a + Math.pow(b - mean, 2), 0) / period;
            const stdDev = Math.sqrt(variance);
            upper.push(mean + multiplier * stdDev);
            lower.push(mean - multiplier * stdDev);
        }
    }
    return { basis, upper, lower };
}

function runSingleBacktest(strategyType, params, closes, activeInterval) {
    let position = null;
    let capital = 10000;
    const trades = [];
    const equity = new Array(closes.length).fill(10000);

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
        for (let i = 0; i < closes.length; i++) {
            if (emaF[i] === null || emaS[i] === null) macdLine.push(null);
            else macdLine.push(emaF[i] - emaS[i]);
        }
        const validMacd = macdLine.filter(x => x !== null);
        const validSignal = calculateEMA(validMacd, params.signal);
        signalLine = [];
        let sigIdx = 0;
        for (let i = 0; i < closes.length; i++) {
            if (macdLine[i] === null) signalLine.push(null);
            else signalLine.push(validSignal[sigIdx++]);
        }
    }

    const startIdx = 80;
    for (let i = 0; i < startIdx; i++) equity[i] = capital;

    for (let i = startIdx; i < closes.length; i++) {
        const price = closes[i];
        let signal = 'hold';
        if (strategyType === 'ema_cross') {
            const prevFast = fastEMA[i-1];
            const prevSlow = slowEMA[i-1];
            const currFast = fastEMA[i];
            const currSlow = slowEMA[i];
            if (prevFast !== null && prevSlow !== null) {
                if (prevFast <= prevSlow && currFast > currSlow) signal = 'buy';
                else if (prevFast >= prevSlow && currFast < currSlow) signal = 'sell';
            }
        } else if (strategyType === 'rsi_reversion') {
            const prevRsi = rsi[i-1];
            const currRsi = rsi[i];
            if (prevRsi !== null) {
                if (prevRsi < params.oversold && currRsi >= params.oversold) signal = 'buy';
                else if (prevRsi > params.overbought && currRsi <= params.overbought) signal = 'sell';
            }
        } else if (strategyType === 'bb_breakout') {
            const prevClose = closes[i-1];
            const prevUpper = bb.upper[i-1];
            const prevLower = bb.lower[i-1];
            const currClose = closes[i];
            if (prevUpper !== null && prevLower !== null) {
                if (prevClose <= prevUpper && currClose > bb.upper[i]) signal = 'buy';
                else if (prevClose >= prevLower && currClose < bb.lower[i]) signal = 'sell';
            }
        } else if (strategyType === 'macd_signal') {
            const prevMacd = macdLine[i-1];
            const prevSig = signalLine[i-1];
            const currMacd = macdLine[i];
            const currSig = signalLine[i];
            if (prevMacd !== null && prevSig !== null) {
                if (prevMacd <= prevSig && currMacd > currSig) signal = 'buy';
                else if (prevMacd >= prevSig && currMacd < currSig) signal = 'sell';
            }
        }

        // BB Breakout — middle-band exit: if holding a position and price reverts past the
        // basis (middle band) without a new breakout signal firing, close the position.
        // Using signal === 'hold' ensures real breakout signals always take full priority.
        let bbMidExit = false;
        if (strategyType === 'bb_breakout' && position && signal === 'hold' && bb.basis[i] !== null) {
            if (position.type === 'long'  && price < bb.basis[i]) bbMidExit = true;
            else if (position.type === 'short' && price > bb.basis[i]) bbMidExit = true;
        }

        if (position) {
            let shouldClose = false;
            // RSI Reversion — exit on RSI midline CROSSOVER, not a bare threshold check.
            // This prevents premature exits in the same bar RSI enters from the signal zone.
            const prevRsiVal = strategyType === 'rsi_reversion' ? rsi[i-1] : null;
            const currRsiVal = strategyType === 'rsi_reversion' ? rsi[i]   : null;
            const rsiExitLong  = strategyType === 'rsi_reversion' && prevRsiVal !== null && prevRsiVal <  50 && currRsiVal >= 50;
            const rsiExitShort = strategyType === 'rsi_reversion' && prevRsiVal !== null && prevRsiVal >  50 && currRsiVal <= 50;

            if (position.type === 'long'  && (signal === 'sell' || rsiExitLong  || bbMidExit)) shouldClose = true;
            else if (position.type === 'short' && (signal === 'buy'  || rsiExitShort || bbMidExit)) shouldClose = true;
            if (shouldClose) {
                const entryPrice = position.entryPrice;
                const exitPrice = price;
                let pnlPercent = 0;
                if (position.type === 'long') pnlPercent = (exitPrice - entryPrice) / entryPrice;
                else pnlPercent = (entryPrice - exitPrice) / entryPrice;
                
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
            // Do NOT open a new position when the close was triggered by the BB middle-band
            // exit — that is an orderly exit, not a new directional signal.
            if (signal === 'buy' && !bbMidExit) {
                const fee = capital * 0.001;
                const positionSize = capital - fee;
                capital -= fee;
                position = { type: 'long', entryPrice: price, entryIndex: i, entryFee: fee, positionSize: positionSize };
            } else if (signal === 'sell' && !bbMidExit) {
                const fee = capital * 0.001;
                const positionSize = capital - fee;
                capital -= fee;
                position = { type: 'short', entryPrice: price, entryIndex: i, entryFee: fee, positionSize: positionSize };
            }
        }

        if (position) {
            let unrealizedPnlPercent = 0;
            if (position.type === 'long') unrealizedPnlPercent = (price - position.entryPrice) / position.entryPrice;
            else unrealizedPnlPercent = (position.entryPrice - price) / position.entryPrice;
            equity[i] = capital + (capital * unrealizedPnlPercent);
        } else equity[i] = capital;
    }

    if (position) {
        const entryPrice = position.entryPrice;
        const exitPrice = closes[closes.length - 1];
        let pnlPercent = 0;
        if (position.type === 'long') pnlPercent = (exitPrice - entryPrice) / entryPrice;
        else pnlPercent = (entryPrice - exitPrice) / entryPrice;
        
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
            exitIndex: closes.length - 1,
            pnl: netTradePnl,
            pnlPercent: (netTradePnl / positionSize) * 100
        });
        equity[closes.length - 1] = capital;
    }

    const returns = [];
    for (let i = startIdx + 1; i < closes.length; i++) returns.push((equity[i] - equity[i-1]) / equity[i-1]);
    let meanReturn = 0, stdDev = 0, sharpe = 0;
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

    let maxDd = 0, peak = -Infinity;
    for (let i = 0; i < closes.length; i++) {
        if (equity[i] > peak) peak = equity[i];
        const dd = (peak - equity[i]) / peak;
        if (dd > maxDd) maxDd = dd;
    }

    const netReturn = ((capital - 10000) / 10000) * 100;
    const calmar = maxDd > 0 ? (netReturn / (maxDd * 100)) : (netReturn / 0.01);
    const wins = trades.filter(t => t.pnl > 0).length;
    const winRate = trades.length > 0 ? (wins / trades.length) * 100 : 0;

    return { params, finalCapital: capital, netReturn, sharpe, maxDrawdown: maxDd * 100, calmar, trades: trades.length, winRate, tradesList: trades };
}

function generateConfigs(strategy) {
    const configs = [];
    if (strategy === 'ema_cross') {
        // Expanded: tighter fast range, slow capped at 60 so every config fires trades on 260 bars
        for (let fast = 5; fast <= 25; fast += 5)
            for (let slow = 15; slow <= 60; slow += 5)
                if (fast < slow) configs.push({ fast, slow });
    } else if (strategy === 'rsi_reversion') {
        // Wider oversold/overbought bands to capture more market conditions
        for (let oversold = 20; oversold <= 40; oversold += 5)
            for (let overbought = 60; overbought <= 80; overbought += 5)
                configs.push({ oversold, overbought });
    } else if (strategy === 'bb_breakout') {
        // Finer period steps and tighter multiplier range
        for (let period = 10; period <= 25; period += 5)
            for (let multiplier = 1.5; multiplier <= 2.5; multiplier += 0.5)
                configs.push({ period, multiplier });
    } else if (strategy === 'macd_signal') {
        // More granular fast/slow/signal combinations; enforce fast < slow
        for (let fast = 6; fast <= 16; fast += 4)
            for (let slow = 18; slow <= 30; slow += 4)
                for (let signal = 6; signal <= 12; signal += 2)
                    if (fast < slow) configs.push({ fast, slow, signal });
    }
    return configs;
}

async function runOptimization(strategy, metric, closes, activeInterval) {
    const configs = generateConfigs(strategy);
    const results = [];
    const total = configs.length;
    for (let i = 0; i < total; i++) {
        const res = runSingleBacktest(strategy, configs[i], closes, activeInterval);
        results.push(res);
        if (i % Math.max(1, Math.floor(total / 100)) === 0) {
            const pct = Math.round(((i + 1) / total) * 100);
            self.postMessage({ type: 'progress', percent: pct });
            await new Promise(resolve => setTimeout(resolve, 0));
        }
    }
    self.postMessage({ type: 'done', results });
}
