<?php
// StrategyEngine class - included by StrategyController.php
// Session is started by the caller.
// Price window is read directly from the price_history DB table — never from session.

class StrategyEngine
{
    private int    $shortPeriod = 50;
    private int    $longPeriod  = 200;
    private mysqli $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    /**
     * Load the last N prices for XAU from price_history (oldest first).
     */
    private function getPriceWindow(int $limit): array
    {
        $stmt = $this->conn->prepare(
            "SELECT price FROM price_history
             WHERE asset = 'XAU'
             ORDER BY recorded_at DESC
             LIMIT ?"
        );
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $prices = [];
        while ($row = $result->fetch_assoc()) {
            $prices[] = (float) $row['price'];
        }
        $stmt->close();

        // Reverse so oldest price is first (chronological order for SMA)
        return array_reverse($prices);
    }

    /**
     * SMA calculation on a given prices array.
     */
    private function calculateSMA(array $prices, int $period): ?float
    {
        if (count($prices) < $period) {
            return null;
        }
        return array_sum(array_slice($prices, -$period)) / $period;
    }

    /**
     * SIGNAL GENERATION
     * Fetches 201 price points so we can compare current vs previous tick's SMA
     * to detect Golden Cross / Death Cross — no session state needed.
     */
    public function generateSignal(): array
    {
        // Need longPeriod + 1 prices to detect a crossover (current vs previous tick)
        $prices = $this->getPriceWindow($this->longPeriod + 1);
        $count  = count($prices);

        // If we don't have enough data for crossover detection yet
        if ($count <= $this->longPeriod) {
            return [
                'status'       => 'OK',
                'signal'       => 'HOLD',
                'sma50'        => $this->calculateSMA($prices, $this->shortPeriod),
                'sma200'       => $this->calculateSMA($prices, $this->longPeriod),
                'prices_count' => $count,
            ];
        }

        // Current window: prices[1..200] (the last 200 prices)
        $currentPrices = array_slice($prices, 1);
        // Previous window: prices[0..199] (one tick back)
        $prevPrices    = array_slice($prices, 0, $this->longPeriod);

        $smaShort     = $this->calculateSMA($currentPrices, $this->shortPeriod);
        $smaLong      = $this->calculateSMA($currentPrices, $this->longPeriod);
        $prevSmaShort = $this->calculateSMA($prevPrices, $this->shortPeriod);
        $prevSmaLong  = $this->calculateSMA($prevPrices, $this->longPeriod);

        $signal = 'HOLD';

        if ($prevSmaShort !== null && $prevSmaLong !== null
            && $smaShort !== null && $smaLong !== null) {

            // Golden Cross: short SMA crosses above long SMA
            if ($prevSmaShort <= $prevSmaLong && $smaShort > $smaLong) {
                $signal = 'BUY';
            }
            // Death Cross: short SMA crosses below long SMA
            elseif ($prevSmaShort >= $prevSmaLong && $smaShort < $smaLong) {
                $signal = 'SELL';
            }
        }

        return [
            'status'       => 'OK',
            'signal'       => $signal,
            'sma50'        => $smaShort,
            'sma200'       => $smaLong,
            'prices_count' => $count,
        ];
    }
}
// Class is instantiated by StrategyController.php
// Do NOT run standalone code here
