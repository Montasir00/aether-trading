/**
 * AETHER HEATMAP ENGINE — Phase 1: Treemap Visualization
 * Generates a dynamic market heatmap with contextual insights.
 */

const AetherHeatmap = {
    /**
     * Renders the heatmap into a target container
     */
    render(containerId, data) {
        const container = document.getElementById(containerId);
        if (!container || !data || data.length === 0) return;

        const animateTiles = container.children.length === 0;
        container.innerHTML = '';
        container.style.display = 'grid';
        container.style.gridTemplateColumns = 'repeat(auto-fit, minmax(100px, 1fr))';
        container.style.gridAutoRows = 'minmax(80px, auto)';
        container.style.gridAutoFlow = 'dense';
        
        const sortedData = [...data].sort((a, b) => Math.abs(b.price_change_24h || 0) - Math.abs(a.price_change_24h || 0));
        const marketAvgChange = sortedData.reduce((sum, coin) => sum + (coin.price_change_24h || 0), 0) / sortedData.length;

        sortedData.forEach((coin, index) => {
            const tile = document.createElement('div');
            tile.className = animateTiles ? 'heatmap-tile g-animate-in' : 'heatmap-tile';
            
            // Size Encoding: Dynamically highlight the top 6 market movers (highest 24h change)
            const span = index < 6 ? 2 : 1;
            tile.style.gridColumn = `span ${span}`;
            tile.style.gridRow = `span ${span}`;
            
            // Color Encoding: OKLCH intensity based on change
            const change = coin.price_change_24h || 0;
            const color = this.getHeatColor(change);
            tile.style.backgroundColor = color.bg;
            tile.style.borderColor = color.border;
            
            // Contextual Insight Layer
            const relativePerformance = change - marketAvgChange;
            const insightText = relativePerformance > 2 
                ? `Outperforming market by ${relativePerformance.toFixed(1)}%`
                : (relativePerformance < -2 ? `Underperforming market by ${Math.abs(relativePerformance).toFixed(1)}%` : 'Moving with market');

            tile.innerHTML = `
                <div class="tile-content">
                    <span class="tile-symbol">${(coin.displaySymbol || coin.symbol).toUpperCase()}</span>
                    <span class="tile-change">${change > 0 ? '+' : ''}${change.toFixed(1)}%</span>
                </div>
                <div class="tile-tooltip">
                    <strong>${coin.name}</strong><br>
                    Price: $${this.formatPrice(coin.current_price)}<br>
                    ${insightText}
                </div>
            `;

            container.appendChild(tile);
        });
    },

    /**
     * Generates OKLCH color strings based on price performance
     */
    getHeatColor(change) {
        // Clamp change between -10% and 10% for color mapping
        const val = Math.max(-10, Math.min(10, change));
        
        if (val > 0) {
            // Green scale: oklch(L C H)
            // L (Lightness) decreases as val increases (more saturated)
            // C (Chroma) increases as val increases
            const l = 0.4 - (val / 100);
            const c = 0.1 + (val / 100);
            return {
                bg: `oklch(${l + 0.1} ${c} 145 / 0.8)`,
                border: `oklch(${l} ${c} 145)`
            };
        } else {
            // Red scale
            const l = 0.4 - (Math.abs(val) / 100);
            const c = 0.1 + (Math.abs(val) / 100);
            return {
                bg: `oklch(${l + 0.1} ${c} 25 / 0.8)`,
                border: `oklch(${l} ${c} 25)`
            };
        }
    },

    formatPrice(price) {
        return price >= 1 ? price.toLocaleString() : price.toFixed(4);
    }
};

if (typeof module !== 'undefined') module.exports = AetherHeatmap;
