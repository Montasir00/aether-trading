<?php
/**
 * RiskManager - Complex custom logic for risk management
 *
 * Enforces:
 * 1. Maximum trade size as % of portfolio
 * 2. Maximum position limits (total XAU/XAG holding cap)
 * 3. Daily trade volume limits
 * 4. Portfolio risk metrics (exposure %, drawdown tracking)
 * 5. Prevents excessive risk-taking
 */

require_once __DIR__ . '/../config.php';

class RiskManager
{
    // --- Configuration ---
    private float $maxTradePercent       = 0.25;     // Max 25% of portfolio per trade
    private float $maxPositionXAU        = 100.0;    // Max 100 oz of Gold total position
    private float $maxPositionXAG        = 5000.0;   // Max 5000 oz of Silver total position
    private float $dailyVolumeLimitUSDT  = 50000.0;  // Max $50k daily volume
    private float $maxDrawdownPercent    = 0.20;     // 20% max drawdown before lockout
    private int   $maxDailyTrades        = 50;       // Max 50 trades per day

    private mysqli $db;
    private int    $userId;

    public function __construct(mysqli $dbConn, int $userId)
    {
        $this->db     = $dbConn;
        $this->userId = $userId;
    }

    // =========================================================
    //  1. VALIDATE TRADE (called before any buy/sell)
    // =========================================================
    public function validateTrade(string $type, string $coin, float $amount, float $price): array
    {
        $errors = [];
        $total  = $amount * $price;
        $type   = strtoupper($type);
        $coin   = strtoupper($coin);

        // --- Get current user balances ---
        $user = $this->getUserBalances();
        if (!$user) {
            return ['allowed' => false, 'errors' => ['User not found']];
        }

        $portfolioValue = $this->calculatePortfolioValue($user);

        // Fetch dynamic balance and available balance of the specific coin being validated
        $coinBalance   = (float)($user['assets'][$coin]['balance']   ?? 0.0);
        $coinAvailable = (float)($user['assets'][$coin]['available'] ?? 0.0);

        // CHECK 1: Max trade size (% of portfolio) - only applies to BUY orders
        if ($type === 'BUY' && $portfolioValue > 0 && ($total / $portfolioValue) > $this->maxTradePercent) {
            $maxAllowed = $portfolioValue * $this->maxTradePercent;
            $errors[] = "Trade exceeds " . ($this->maxTradePercent * 100) . "% of portfolio. Max allowed: $" . number_format($maxAllowed, 2);
        }

        // CHECK 2: Position limit
        if ($type === 'BUY' && $coin === 'XAU') {
            $newPosition = $coinBalance + $amount;
            if ($newPosition > $this->maxPositionXAU) {
                $errors[] = "Position limit exceeded. Max Gold holding: {$this->maxPositionXAU} oz. Current: {$coinBalance} oz";
            }
        }
        if ($type === 'BUY' && $coin === 'XAG') {
            $newPosition = $coinBalance + $amount;
            if ($newPosition > $this->maxPositionXAG) {
                $errors[] = "Position limit exceeded. Max Silver holding: {$this->maxPositionXAG} oz. Current: {$coinBalance} oz";
            }
        }

        // CHECK 3: Daily volume limit
        $dailyVolume = $this->getDailyVolume();
        if (($dailyVolume + $total) > $this->dailyVolumeLimitUSDT) {
            $remaining = $this->dailyVolumeLimitUSDT - $dailyVolume;
            $errors[] = "Daily volume limit reached. Limit: $" . number_format($this->dailyVolumeLimitUSDT, 2) . ". Remaining: $" . number_format(max(0, $remaining), 2);
        }

        // CHECK 4: Daily trade count
        $dailyCount = $this->getDailyTradeCount();
        if ($dailyCount >= $this->maxDailyTrades) {
            $errors[] = "Maximum daily trade count ({$this->maxDailyTrades}) reached.";
        }

        // CHECK 5: Drawdown protection
        $drawdown = $this->calculateDrawdown();
        if ($drawdown !== null && $drawdown >= $this->maxDrawdownPercent) {
            $errors[] = "Portfolio drawdown exceeds " . ($this->maxDrawdownPercent * 100) . "%. Trading paused for risk protection. Current drawdown: " . number_format($drawdown * 100, 1) . "%";
        }

        // CHECK 6: Sufficient AVAILABLE balance (balance minus already-reserved funds).
        $usdtAvailable = (float)($user['usdt_available'] ?? (float)$user['balance']);
        if ($type === 'BUY' && $usdtAvailable < $total) {
            $errors[] = "Insufficient USDT balance. Available (after reservations): $" . number_format($usdtAvailable, 2) . ", Need: $" . number_format($total, 2);
        }
        if ($type === 'SELL' && $coinAvailable < $amount) {
            $errors[] = "Insufficient $coin balance. Available (after reservations): {$coinAvailable}, Need: {$amount}";
        }

        return [
            'allowed' => empty($errors),
            'errors'  => $errors
        ];
    }

    // =========================================================
    //  2. PORTFOLIO RISK METRICS
    // =========================================================
    public function getPortfolioMetrics(): array
    {
        $user = $this->getUserBalances();
        if (!$user) {
            return ['error' => 'User not found'];
        }

        $portfolioValue = $this->calculatePortfolioValue($user);
        $dailyVolume    = $this->getDailyVolume();
        $dailyCount     = $this->getDailyTradeCount();
        $drawdown       = $this->calculateDrawdown();
        $xauExposure    = $this->calculateXAUExposure($user);

        return [
            'portfolio_value'       => round($portfolioValue, 2),
            'usdt_balance'          => round((float)$user['balance'], 2),
            'xau_balance'           => round((float)$user['xau_balance'], 6),
            'xag_balance'           => round((float)$user['xag_balance'], 4),
            'xau_exposure_percent'  => round($xauExposure * 100, 1),
            'daily_volume_used'     => round($dailyVolume, 2),
            'daily_volume_limit'    => $this->dailyVolumeLimitUSDT,
            'daily_volume_percent'  => round(($dailyVolume / $this->dailyVolumeLimitUSDT) * 100, 1),
            'daily_trades'          => $dailyCount,
            'daily_trades_limit'    => $this->maxDailyTrades,
            'max_trade_percent'     => $this->maxTradePercent * 100,
            'max_position_xau'      => $this->maxPositionXAU,
            'max_position_xag'      => $this->maxPositionXAG,
            'drawdown_percent'      => $drawdown !== null ? round($drawdown * 100, 1) : 0,
            'max_drawdown_percent'  => $this->maxDrawdownPercent * 100,
            'risk_level'            => $this->calculateRiskLevel($xauExposure, $drawdown, $dailyVolume)
        ];
    }

    // =========================================================
    //  INTERNAL HELPERS
    // =========================================================

    private function getUserBalances(): ?array
    {
        // Check if the user has a wallet in wallets table
        $stmt = $this->db->prepare("SELECT id FROM wallets WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $this->userId);
        $stmt->execute();
        $walletRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($walletRow) {
            $walletId = (int)$walletRow['id'];
            $stmt = $this->db->prepare("SELECT asset, balance, reserved FROM balances WHERE wallet_id = ?");
            $stmt->bind_param("i", $walletId);
            $stmt->execute();
            $balances = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $assets = [];
            foreach ($balances as $bRow) {
                $asset    = strtoupper((string)($bRow['asset'] ?? ''));
                $amount   = (float)($bRow['balance']  ?? 0);
                $reserved = (float)($bRow['reserved'] ?? 0);
                $assets[$asset] = [
                    'balance'   => $amount,
                    'reserved'  => $reserved,
                    'available' => $amount - $reserved
                ];
            }

            return [
                'balance'        => $assets['USDT']['balance'] ?? 0.0,
                'usdt_reserved'  => $assets['USDT']['reserved'] ?? 0.0,
                'usdt_available' => $assets['USDT']['available'] ?? 0.0,
                'xau_balance'    => $assets['XAU']['balance'] ?? 0.0,
                'xag_balance'    => $assets['XAG']['balance'] ?? 0.0,
                'assets'         => $assets
            ];
        }

        // Fallback to original users table (no wallet/reserved info available)
        $stmt = $this->db->prepare("SELECT balance, xau_balance, xag_balance FROM users WHERE id = ?");
        $stmt->bind_param("i", $this->userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) return null;
        
        $assets = [
            'USDT' => ['balance' => (float)$row['balance'], 'reserved' => 0.0, 'available' => (float)$row['balance']],
            'XAU'  => ['balance' => (float)$row['xau_balance'], 'reserved' => 0.0, 'available' => (float)$row['xau_balance']],
            'XAG'  => ['balance' => (float)$row['xag_balance'], 'reserved' => 0.0, 'available' => (float)$row['xag_balance']]
        ];

        return [
            'balance'        => (float)$row['balance'],
            'usdt_reserved'  => 0.0,
            'usdt_available' => (float)$row['balance'],
            'xau_balance'    => (float)$row['xau_balance'],
            'xag_balance'    => (float)$row['xag_balance'],
            'assets'         => $assets
        ];
    }

    private function calculatePortfolioValue(array $user): float
    {
        // Check if the user has a wallet in wallets table
        $stmt = $this->db->prepare("SELECT id FROM wallets WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $this->userId);
        $stmt->execute();
        $walletRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($walletRow) {
            $walletId = (int)$walletRow['id'];
            $stmt = $this->db->prepare("SELECT asset, balance FROM balances WHERE wallet_id = ?");
            $stmt->bind_param("i", $walletId);
            $stmt->execute();
            $balances = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $totalValue = 0.0;
            foreach ($balances as $bRow) {
                $asset = strtoupper((string)($bRow['asset'] ?? ''));
                $amount = (float)($bRow['balance'] ?? 0);
                if ($asset === 'USDT') {
                    $totalValue += $amount;
                } else {
                    $price = $this->fetchCommodityPrice($asset . 'USDT');
                    $totalValue += $amount * $price;
                }
            }
            return $totalValue;
        }

        // Fallback to legacy
        $xauPrice = $this->fetchCommodityPrice('XAUUSDT');
        $xagPrice = $this->fetchCommodityPrice('XAGUSDT');
        $usdtBal  = (float)$user['balance'];
        $xauBal   = (float)$user['xau_balance'];
        $xagBal   = (float)$user['xag_balance'];
        return $usdtBal + ($xauBal * $xauPrice) + ($xagBal * $xagPrice);
    }

    private function calculateXAUExposure(array $user): float
    {
        // Check if the user has a wallet in wallets table
        $stmt = $this->db->prepare("SELECT id FROM wallets WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $this->userId);
        $stmt->execute();
        $walletRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $xauBal = 0.0;
        if ($walletRow) {
            $walletId = (int)$walletRow['id'];
            $stmt = $this->db->prepare("SELECT balance FROM balances WHERE wallet_id = ? AND asset = 'XAU' LIMIT 1");
            $stmt->bind_param("i", $walletId);
            $stmt->execute();
            $xauRow = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($xauRow) {
                $xauBal = (float)$xauRow['balance'];
            }
        } else {
            $xauBal = (float)$user['xau_balance'];
        }

        $xauPrice       = $this->fetchCommodityPrice('XAUUSDT');
        $xauValueUSDT   = $xauBal * $xauPrice;
        $portfolioValue = $this->calculatePortfolioValue($user);
        return $portfolioValue > 0 ? $xauValueUSDT / $portfolioValue : 0;
    }

    private function getDailyVolume(): float
    {
        // 1. Legacy Transactions daily volume
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(total), 0) as daily_vol
             FROM transactions
             WHERE user_id = ? AND status = 'completed' AND DATE(created_at) = CURDATE()"
        );
        $stmt->bind_param("i", $this->userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $legacyVol = (float)$row['daily_vol'];

        // 2. Spot Engine orders filled volume
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(filled_qty * price), 0) as daily_vol
             FROM orders
             WHERE user_id = ? AND filled_qty > 0 AND DATE(created_at) = CURDATE()"
        );
        $stmt->bind_param("i", $this->userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $spotVol = (float)$row['daily_vol'];

        return $legacyVol + $spotVol;
    }

    private function getDailyTradeCount(): int
    {
        // 1. Legacy Transactions count
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as cnt
             FROM transactions
             WHERE user_id = ? AND status = 'completed' AND DATE(created_at) = CURDATE()"
        );
        $stmt->bind_param("i", $this->userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $legacyCount = (int)$row['cnt'];

        // 2. Spot Engine orders count
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as cnt
             FROM orders
             WHERE user_id = ? AND filled_qty > 0 AND DATE(created_at) = CURDATE()"
        );
        $stmt->bind_param("i", $this->userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $spotCount = (int)$row['cnt'];

        return $legacyCount + $spotCount;
    }

    /**
     * Calculate portfolio drawdown from peak balance.
     * Tracks peak portfolio value over the user's history.
     */
    private function calculateDrawdown(): ?float
    {
        $user = $this->getUserBalances();
        if (!$user) return null;

        $currentValue = $this->calculatePortfolioValue($user);
        if ($currentValue <= 0) return null;

        // Peak baseline: use a configurable starting capital rather than a hardcoded
        // $10,000 that was wrong for every user except those who started with exactly $10k.
        // Reads from env (set in docker-compose) or falls back to 10000.
        // IMPORTANT: max() with currentValue prevents false positives — if the user's
        // portfolio has grown above the baseline, peak = currentValue and drawdown = 0.
        $configuredBaseline = (float)(getenv('INITIAL_PORTFOLIO_BASELINE') ?: 10000.0);
        $peak = max($configuredBaseline, $currentValue);

        if ($peak <= 0) return 0;

        $drawdown = ($peak - $currentValue) / $peak;
        return max(0.0, $drawdown);
    }

    private function calculateRiskLevel(float $exposure, ?float $drawdown, float $dailyVolume): string
    {
        $dd       = $drawdown ?? 0;
        $volRatio = $dailyVolume / $this->dailyVolumeLimitUSDT;

        if ($dd >= 0.15 || $exposure >= 0.8 || $volRatio >= 0.9) {
            return 'HIGH';
        } elseif ($dd >= 0.08 || $exposure >= 0.5 || $volRatio >= 0.5) {
            return 'MEDIUM';
        }
        return 'LOW';
    }

    private function fetchCommodityPrice(string $symbol): float
    {
        static $cache = [];
        if (isset($cache[$symbol])) return $cache[$symbol];

        // 1. Try local cache file first to stay independent of internet connection failures
        $cacheFile = __DIR__ . '/tmp/market_prices.json';
        if (file_exists($cacheFile)) {
            $raw = @file_get_contents($cacheFile);
            $json = $raw ? json_decode($raw, true) : null;
            if (isset($json['prices'][$symbol]['price'])) {
                $cache[$symbol] = (float)$json['prices'][$symbol]['price'];
                return $cache[$symbol];
            }
        }

        require_once __DIR__ . '/../api/api_helper.php';
        $price = fetchBinancePrice($symbol);
        $cache[$symbol] = $price ?? 0.0;
        return $cache[$symbol];
    }
}
