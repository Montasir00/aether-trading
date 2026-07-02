/**
 * AETHER INTELLIGENCE — Geometric Pattern Engine
 * Handles client-side technical analysis without API/DB calls.
 */

const AetherEngine = {
    /**
     * Finds local minima and maxima in a price array.
     * @param {Array} data - Array of price points or candle objects
     * @param {number} range - How many points to check on each side (strength)
     */
    findPivots(data, range = 5) {
        const highs = [];
        const lows = [];

        for (let i = range; i < data.length - range; i++) {
            const current = data[i];
            let isHigh = true;
            let isLow = true;

            for (let j = 1; j <= range; j++) {
                if (data[i - j] >= current || data[i + j] > current) isHigh = false;
                if (data[i - j] <= current || data[i + j] < current) isLow = false;
            }

            if (isHigh) highs.push({ index: i, price: current });
            if (isLow) lows.push({ index: i, price: current });
        }

        return { highs, lows };
    },

    /**
     * Identifies horizontal price levels where multiple pivots align.
     */
    detectLevels(pivots, tolerance = 0.002) {
        const levels = [];
        const allPivots = [...pivots.highs, ...pivots.lows];

        allPivots.forEach(p => {
            // Check if this price is close to an existing level
            let existing = levels.find(l => Math.abs(l.price - p.price) / p.price < tolerance);
            
            if (existing) {
                existing.touches++;
                existing.strength = Math.min(100, existing.touches * 25);
            } else {
                levels.push({
                    price: p.price,
                    touches: 1,
                    strength: 25,
                    type: pivots.highs.includes(p) ? 'resistance' : 'support'
                });
            }
        });

        // Filter for "Significant" levels (2+ touches)
        return levels.filter(l => l.touches >= 2).sort((a, b) => b.touches - a.touches);
    },

    /**
     * Analyzes price action for basic shapes like Double Tops.
     */
    scanShapes(pivots, currentPrice) {
        const alerts = [];
        
        // Double Top Detection
        if (pivots.highs.length >= 2) {
            const lastHigh = pivots.highs[pivots.highs.length - 1];
            const prevHigh = pivots.highs[pivots.highs.length - 2];
            const diff = Math.abs(lastHigh.price - prevHigh.price) / lastHigh.price;
            
            if (diff < 0.001) {
                alerts.push({
                    name: 'Double Top',
                    confidence: 85,
                    sentiment: 'bearish'
                });
            }
        }

        // Potential Breakout Detection
        const nearResistance = pivots.highs.some(h => Math.abs(currentPrice - h.price) / h.price < 0.0005);
        if (nearResistance) {
            alerts.push({
                name: 'Breakout Watch',
                confidence: 60,
                sentiment: 'neutral'
            });
        }

        return alerts;
    }
};

// Export for use in dashboard.js
if (typeof module !== 'undefined') module.exports = AetherEngine;
