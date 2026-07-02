<?php
/**
 * Simple Matching Engine (MVP)
 * - Market orders execute immediately against market price (cached)
 * - Limit orders are inserted as open and reserve balances
 * This is intentionally minimal and synchronous for the prototype.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../api/api_helper.php';

class MatchingEngine
{
    private mysqli $db;

    public function __construct(mysqli $conn)
    {
        $this->db = $conn;
    }

    /**
     * Match a limit order against opposite open orders (price-time priority).
     */
    private function matchLimitOrder(int $orderId): array
    {
        // Lock the order row
        $stmt = $this->db->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$order || $order['status'] !== 'open') {
            return ['success' => false, 'message' => 'Order not open or not found'];
        }

        $pair = $order['pair'];
        $side = $order['side'];
        $price = (float)$order['price'];
        $remaining = (float)$order['qty'] - (float)$order['filled_qty'];

        // Determine opposite side and price comparator
        $opSide = $side === 'BUY' ? 'SELL' : 'BUY';
        if ($side === 'BUY') {
            // match with lowest priced sell orders where sell.price <= buy.price
            $priceCmp = "AND price <= ? ORDER BY price ASC, created_at ASC";
        } else {
            // SELL: match with highest priced buy orders where buy.price >= sell.price
            $priceCmp = "AND price >= ? ORDER BY price DESC, created_at ASC";
        }

        $selSql = "SELECT * FROM orders WHERE pair = ? AND side = ? AND status IN ('open','partially_filled') " . $priceCmp . " LIMIT 100 FOR UPDATE";
        $stmt = $this->db->prepare($selSql);
        $stmt->bind_param('ssd', $pair, $opSide, $price);
        $stmt->execute();
        $oppRes = $stmt->get_result();

            while ($opp = $oppRes->fetch_assoc()) {
            if ($remaining <= 0) break;
            $oppRemaining = (float)$opp['qty'] - (float)$opp['filled_qty'];
            if ($oppRemaining <= 0) continue;

            $matchQty = min($remaining, $oppRemaining);
            $execPrice = $opp['price']; // use resting order price

            // Update orders' filled_qty and status
            $stmtUp = $this->db->prepare('UPDATE orders SET filled_qty = filled_qty + ?, status = CASE WHEN filled_qty + ? >= qty THEN "completed" ELSE "partially_filled" END, updated_at = NOW() WHERE id = ?');
            $stmtUp->bind_param('ddi', $matchQty, $matchQty, $orderId);
            $stmtUp->execute();
            $stmtUp->close();

            $stmtUp2 = $this->db->prepare('UPDATE orders SET filled_qty = filled_qty + ?, status = CASE WHEN filled_qty + ? >= qty THEN "completed" ELSE "partially_filled" END, updated_at = NOW() WHERE id = ?');
            $stmtUp2->bind_param('ddi', $matchQty, $matchQty, $opp['id']);
            $stmtUp2->execute();
            $stmtUp2->close();

            // Transfer balances between wallets
            // buyer gets base, seller gets quote
            $buyOrder = $side === 'BUY' ? $order : $opp;
            $sellOrder = $side === 'SELL' ? $order : $opp;

            $buyUserWallet = $this->getWalletId((int)$buyOrder['user_id'], true);
            $sellUserWallet = $this->getWalletId((int)$sellOrder['user_id'], true);

            $base = 'XAU';
            $quote = 'USDT';
            $stmtPair = $this->db->prepare("SELECT base_asset, quote_asset FROM trading_pairs WHERE symbol = ? LIMIT 1");
            $stmtPair->bind_param("s", $pair);
            $stmtPair->execute();
            $pairRow = $stmtPair->get_result()->fetch_assoc();
            $stmtPair->close();
            if ($pairRow) {
                $base = $pairRow['base_asset'];
                $quote = $pairRow['quote_asset'];
            } else {
                if (str_ends_with($pair, 'USDT')) {
                    $base  = substr($pair, 0, -4);
                    $quote = 'USDT';
                } else {
                    $base  = substr($pair, 0, 3);
                    $quote = substr($pair, 3);
                }
            }

            $tradeTotal = $matchQty * $execPrice;

            // Release reserved funds for the matched portion.
            // The correct signal is ORDER TYPE, not maker/taker position:
            // - LIMIT orders always reserve funds at placement (both maker AND taker).
            // - MARKET orders execute immediately without pre-reserving anything.
            // Using maker/taker was wrong: taker limit orders had their funds reserved
            // at placement but were never released here, locking capital permanently.
            $buyQuoteReservedDelta  = ($buyOrder['type'] === 'limit') ? -($matchQty * (float)$buyOrder['price']) : 0.0;
            $sellBaseReservedDelta  = ($sellOrder['type'] === 'limit') ? -$matchQty   : 0.0;

            // Buyer: deduct spent quote, credit bought base
            $this->upsertBalance($buyUserWallet, $quote, -$tradeTotal, $buyQuoteReservedDelta);
            $this->upsertBalance($buyUserWallet, $base, $matchQty, 0.0);

            // Seller: deduct sold base, credit received quote
            $this->upsertBalance($sellUserWallet, $base, -$matchQty, $sellBaseReservedDelta);
            $this->upsertBalance($sellUserWallet, $quote, $tradeTotal, 0.0);

            // Synchronize with users table only if base is XAU or XAG
            if ($base === 'XAU' || $base === 'XAG') {
                $baseColumn = ($base === 'XAU') ? 'xau_balance' : 'xag_balance';

                // Update buyer in users table
                $buyerId = (int)$buyOrder['user_id'];
                $stmtU1 = $this->db->prepare("UPDATE users SET balance = balance - ?, $baseColumn = $baseColumn + ? WHERE id = ?");
                $stmtU1->bind_param("ddi", $tradeTotal, $matchQty, $buyerId);
                $stmtU1->execute();
                $stmtU1->close();

                // Update seller in users table
                $sellerId = (int)$sellOrder['user_id'];
                $stmtU2 = $this->db->prepare("UPDATE users SET $baseColumn = $baseColumn - ?, balance = balance + ? WHERE id = ?");
                $stmtU2->bind_param("ddi", $matchQty, $tradeTotal, $sellerId);
                $stmtU2->execute();
                $stmtU2->close();
            } else {
                // Update buyer quote balance in users table
                $buyerId = (int)$buyOrder['user_id'];
                $stmtU1 = $this->db->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                $stmtU1->bind_param("di", $tradeTotal, $buyerId);
                $stmtU1->execute();
                $stmtU1->close();

                // Update seller quote balance in users table
                $sellerId = (int)$sellOrder['user_id'];
                $stmtU2 = $this->db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                $stmtU2->bind_param("di", $tradeTotal, $sellerId);
                $stmtU2->execute();
                $stmtU2->close();
            }

            // Record trade — single prepared statement (the previous code had a dead
            // prepared stmt that was built+closed without ->execute(), then a raw query
            // that actually ran. Removed the dead block; using only the prepared stmt now.
            $buyId  = (int)$buyOrder['id'];
            $sellId = (int)$sellOrder['id'];
            $stmtT  = $this->db->prepare('INSERT INTO trades (buy_order_id, sell_order_id, pair, price, qty) VALUES (?, ?, ?, ?, ?)');
            if ($stmtT) {
                $stmtT->bind_param('iisdd', $buyId, $sellId, $pair, $execPrice, $matchQty);
                $stmtT->execute();
                $stmtT->close();
            }

            $remaining -= $matchQty;
        }

        $stmt->close();

        // Update original order status if any remains
        if ($remaining <= 0) {
            $stmtDone = $this->db->prepare("UPDATE orders SET status = 'completed' WHERE id = ?");
            $stmtDone->bind_param('i', $orderId);
            $stmtDone->execute();
            $stmtDone->close();
            return ['success' => true, 'message' => 'Order fully matched'];
        }

        if ($remaining < (float)$order['qty']) {
            return ['success' => true, 'message' => 'Order partially matched'];
        }

        return ['success' => true, 'message' => 'No matches available'];
    }

    private function getWalletId(int $userId, bool $inTransaction = false): ?int
    {
        $stmt = $this->db->prepare('SELECT id FROM wallets WHERE user_id = ? LIMIT 1');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($row) {
            return (int)$row['id'];
        }
        
        // Dynamically create wallet and migrate balances from users table!
        if (!$inTransaction) {
            $this->db->begin_transaction();
        }
        try {
            $stmt = $this->db->prepare('INSERT IGNORE INTO wallets (user_id) VALUES (?)');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();
            
            $stmt = $this->db->prepare('SELECT id FROM wallets WHERE user_id = ? LIMIT 1');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$row) {
                throw new Exception("Failed to create wallet");
            }
            
            $walletId = (int)$row['id'];
            
            // Fetch balances from users table
            $stmt = $this->db->prepare('SELECT balance, xau_balance, xag_balance FROM users WHERE id = ?');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $userRow = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($userRow) {
                $usdt = (float)($userRow['balance'] ?? 0);
                $xau = (float)($userRow['xau_balance'] ?? 0);
                $xag = (float)($userRow['xag_balance'] ?? 0);
                
                // Insert into balances
                $stmt = $this->db->prepare('INSERT INTO balances (wallet_id, asset, balance, reserved) VALUES (?, ?, ?, 0)');
                
                $asset = 'USDT';
                $stmt->bind_param('isd', $walletId, $asset, $usdt);
                $stmt->execute();
                
                $asset = 'XAU';
                $stmt->bind_param('isd', $walletId, $asset, $xau);
                $stmt->execute();
                
                $asset = 'XAG';
                $stmt->bind_param('isd', $walletId, $asset, $xag);
                $stmt->execute();
                
                $stmt->close();
            }
            
            if (!$inTransaction) {
                $this->db->commit();
            }
            return $walletId;
        } catch (Exception $e) {
            if (!$inTransaction) {
                $this->db->rollback();
            } else {
                throw $e; // Re-throw to allow the outer transaction to roll back
            }
            return null;
        }
    }

    private function getBalanceRow(int $walletId, string $asset): ?array
    {
        $stmt = $this->db->prepare('SELECT balance, reserved FROM balances WHERE wallet_id = ? AND asset = ? LIMIT 1 FOR UPDATE');
        $stmt->bind_param('is', $walletId, $asset);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? $row : null;
    }

    private function upsertBalance(int $walletId, string $asset, float $balanceDelta, float $reservedDelta = 0.0)
    {
        // Try update first
        $this->db->query("INSERT INTO balances (wallet_id, asset, balance, reserved) VALUES ({$walletId}, '{$this->db->real_escape_string($asset)}', 0, 0)
            ON DUPLICATE KEY UPDATE balance = balance, reserved = reserved");

        $stmt = $this->db->prepare('UPDATE balances SET balance = balance + ?, reserved = reserved + ? WHERE wallet_id = ? AND asset = ?');
        $stmt->bind_param('ddis', $balanceDelta, $reservedDelta, $walletId, $asset);
        $stmt->execute();
        $stmt->close();
    }

    private function fetchMarketPrice(string $pair): ?float
    {
        // Try cached file
        $cacheFile = __DIR__ . '/tmp/market_prices.json';
        if (file_exists($cacheFile)) {
            $raw = @file_get_contents($cacheFile);
            $json = $raw ? json_decode($raw, true) : null;
            if (isset($json['prices'][$pair]['price'])) {
                return (float)$json['prices'][$pair]['price'];
            }
        }

        // Keep CLI/test runs deterministic: only hit live prices when explicitly enabled.
        $allowLiveFetch = getenv('ALLOW_LIVE_MARKET_FETCH');
        if (PHP_SAPI === 'cli' && !$allowLiveFetch) {
            return null;
        }

        // Fallback to direct fetch for web/runtime usage when cache misses.
        return fetchBinancePrice($pair);
    }

    /**
     * Public wrapper for async limit order matching in the daemon.
     * After the off-chain match is recorded in MySQL, if both orders
     * have on-chain signatures, this also triggers the smart contract
     * settleLimitOrder() call to transfer the escrowed ETH on-chain.
     */
    public function matchLimitOrderAsynchronously(int $orderId): array
    {
        $result = $this->matchLimitOrder($orderId);

        // After a successful match, attempt on-chain settlement for orders
        // that were placed as on-chain limit orders (have ETH signatures + escrow)
        if ($result['success'] && $result['message'] !== 'No matches available') {
            $this->settlePendingOnChainPairs($orderId);
        }

        return $result;
    }

    /**
     * Scans recently completed trades involving $orderId and attempts to
     * settle any on-chain pairs (where both orders have eth_signature + escrow_tx_hash)
     * by calling AetherTrade.sol's settleLimitOrder() via the Node.js bridge.
     */
    private function settlePendingOnChainPairs(int $triggeredOrderId): void
    {
        // Find trades that involve this order and where the buy order has an escrow
        $stmtTrades = $this->db->prepare(
            "SELECT t.id AS trade_id, t.buy_order_id, t.sell_order_id, t.qty, t.price,
                    bo.eth_wallet_address AS buyer_wallet,  bo.eth_signature AS buy_sig,
                    bo.escrow_tx_hash     AS buy_escrow_hash,
                    so.eth_wallet_address AS seller_wallet, so.eth_signature AS sell_sig,
                    bo.on_chain_settled   AS already_settled,
                    tp.base_asset
             FROM   trades t
             JOIN   orders bo ON bo.id = t.buy_order_id
             JOIN   orders so ON so.id = t.sell_order_id
             LEFT JOIN trading_pairs tp ON tp.symbol = bo.pair
             WHERE  (t.buy_order_id = ? OR t.sell_order_id = ?)
               AND  bo.eth_signature  IS NOT NULL
               AND  so.eth_signature  IS NOT NULL
               AND  bo.escrow_tx_hash IS NOT NULL
               AND  bo.on_chain_settled = 0
             LIMIT  10"
        );
        $stmtTrades->bind_param('ii', $triggeredOrderId, $triggeredOrderId);
        $stmtTrades->execute();
        $trades = $stmtTrades->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtTrades->close();

        foreach ($trades as $trade) {
            $this->settleLimitOrderOnChain(
                (int)$trade['buy_order_id'],
                (int)$trade['sell_order_id'],
                $trade['seller_wallet'],
                $trade['base_asset'] ?? 'XAU'
            );
        }
    }

    /**
     * Calls the AetherTrade.sol settleLimitOrder() function via a Node.js
     * bridge script to transfer escrowed ETH from the contract to the seller.
     *
     * Uses the checks-effects-interactions pattern:
     *   1. Mark as settled in DB first (prevents double-settlement if script crashes)
     *   2. Call the smart contract
     *   3. Update with tx hash
     *
     * @param int    $buyOrderId   MySQL buy order ID (also on-chain escrow ID)
     * @param int    $sellOrderId  MySQL sell order ID
     * @param string $sellerWallet MetaMask address of the seller
     * @param string $asset        Commodity ticker e.g. "XAU"
     */
    private function settleLimitOrderOnChain(int $buyOrderId, int $sellOrderId, string $sellerWallet, string $asset): void
    {
        if (!$sellerWallet) {
            error_log("[MatchingEngine] settleLimitOrderOnChain: seller wallet address missing for buy #{$buyOrderId}");
            return;
        }

        // Step 1: Mark both orders as settled in DB BEFORE the on-chain call
        // This prevents double-settlement even if the script is interrupted
        $stmtMark = $this->db->prepare(
            "UPDATE orders SET on_chain_settled = 1 WHERE id IN (?, ?)"
        );
        $stmtMark->bind_param('ii', $buyOrderId, $sellOrderId);
        $stmtMark->execute();
        $stmtMark->close();

        // Step 2: Call the smart contract via Node.js bridge script
        // The bridge reads AETHER_BLOCKCHAIN config and calls web3.settleLimitOrder()
        $bridgeScript = __DIR__ . '/blockchain/settle_limit_order.js';
        if (!file_exists($bridgeScript)) {
            error_log("[MatchingEngine] settleLimitOrderOnChain: bridge script not found at {$bridgeScript}");
            return;
        }

        $cmd = sprintf(
            'GANACHE_RPC_URL=%s node %s %d %d %s %s 2>&1',
            escapeshellarg(GANACHE_RPC_URL),
            escapeshellarg($bridgeScript),
            $buyOrderId,
            $sellOrderId,
            escapeshellarg($sellerWallet),
            escapeshellarg($asset)
        );


        $output   = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);
        $outputStr = implode("\n", $output);

        if ($exitCode !== 0) {
            error_log("[MatchingEngine] settleLimitOrderOnChain failed (exit {$exitCode}): {$outputStr}");
            return;
        }

        // Step 3: Parse the tx hash from Node.js output and save it
        // Expected output from settle_limit_order.js: JSON {"txHash":"0x..."}
        $decoded = json_decode($outputStr, true);
        $txHash  = $decoded['txHash'] ?? null;

        if ($txHash) {
            $stmtHash = $this->db->prepare(
                "UPDATE orders SET settle_tx_hash = ? WHERE id IN (?, ?)"
            );
            $stmtHash->bind_param('sii', $txHash, $buyOrderId, $sellOrderId);
            $stmtHash->execute();
            $stmtHash->close();

            // Update escrow record
            $stmtEsc = $this->db->prepare(
                "UPDATE limit_order_escrows SET status = 'settled', settle_tx_hash = ? WHERE order_id = ?"
            );
            $stmtEsc->bind_param('si', $txHash, $buyOrderId);
            $stmtEsc->execute();
            $stmtEsc->close();

            error_log("[MatchingEngine] settleLimitOrderOnChain: buy #{$buyOrderId} settled on-chain. TxHash: {$txHash}");
        }
    }

    /**
     * Executes a market order asynchronously in the daemon.
     */
    public function executeMarketOrderAsynchronously(int $orderId): array
    {
        // Lock the order row
        $stmt = $this->db->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$order || $order['status'] !== 'open') {
            return ['success' => false, 'message' => 'Order not open or not found'];
        }

        $userId = (int)$order['user_id'];
        $pair = $order['pair'];
        $side = $order['side'];
        $qty = (float)$order['qty'];

        $walletId = $this->getWalletId($userId, true);
        if (!$walletId) return ['success' => false, 'message' => 'Wallet not found'];

        // Determine base/quote assets
        $base = 'XAU';
        $quote = 'USDT';
        $stmtPair = $this->db->prepare("SELECT base_asset, quote_asset FROM trading_pairs WHERE symbol = ? LIMIT 1");
        $stmtPair->bind_param("s", $pair);
        $stmtPair->execute();
        $pairRow = $stmtPair->get_result()->fetch_assoc();
        $stmtPair->close();
        if ($pairRow) {
            $base = $pairRow['base_asset'];
            $quote = $pairRow['quote_asset'];
        }

        $marketPrice = $this->fetchMarketPrice($pair);
        if ($marketPrice === null || $marketPrice <= 0) {
            return ['success' => false, 'message' => 'Market price unavailable'];
        }
        $execPrice = $marketPrice;
        $total = $qty * $execPrice;

        if ($side === 'BUY') {
            // Check current balances
            $bal = $this->getBalanceRow($walletId, $quote);
            $origTotal = (float)$order['total'];
            
            // Debit quote (including from reserved) and credit base
            $this->upsertBalance($walletId, $quote, -$total, -$origTotal);
            $this->upsertBalance($walletId, $base, $qty, 0.0);

            // Sync users table
            if ($base === 'XAU' || $base === 'XAG') {
                $baseColumn = ($base === 'XAU') ? 'xau_balance' : 'xag_balance';
                $stmtU = $this->db->prepare("UPDATE users SET balance = balance - ?, $baseColumn = $baseColumn + ? WHERE id = ?");
                $stmtU->bind_param("ddi", $total, $qty, $userId);
            } else {
                $stmtU = $this->db->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                $stmtU->bind_param("di", $total, $userId);
            }
            $stmtU->execute();
            $stmtU->close();
        } else {
            // SELL: check base balance
            $bal = $this->getBalanceRow($walletId, $base);
            $origQty = (float)$order['qty'];

            $this->upsertBalance($walletId, $base, -$qty, -$origQty);
            $this->upsertBalance($walletId, $quote, $total, 0.0);

            // Sync users table
            if ($base === 'XAU' || $base === 'XAG') {
                $baseColumn = ($base === 'XAU') ? 'xau_balance' : 'xag_balance';
                $stmtU = $this->db->prepare("UPDATE users SET $baseColumn = $baseColumn - ?, balance = balance + ? WHERE id = ?");
                $stmtU->bind_param("ddi", $qty, $total, $userId);
            } else {
                $stmtU = $this->db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                $stmtU->bind_param("di", $total, $userId);
            }
            $stmtU->execute();
            $stmtU->close();
        }

        // Update order status to completed
        $stmt = $this->db->prepare("UPDATE orders SET price = ?, total = ?, filled_qty = ?, status = 'completed', updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('dddi', $execPrice, $total, $qty, $orderId);
        $stmt->execute();
        $stmt->close();

        // Record trade
        $buyOrderId  = ($side === 'BUY')  ? $orderId : null;
        $sellOrderId = ($side === 'SELL') ? $orderId : null;
        $stmtT = $this->db->prepare("INSERT INTO trades (buy_order_id, sell_order_id, pair, price, qty) VALUES (?, ?, ?, ?, ?)");
        $stmtT->bind_param('iisdd', $buyOrderId, $sellOrderId, $pair, $execPrice, $qty);
        $stmtT->execute();
        $stmtT->close();

        return ['success' => true, 'message' => 'Market order completed asynchronously'];
    }

    /**
     * Place an order. Returns array: ['success'=>bool, 'message'=>string, 'order_id'=>int|null]
     */
    public function placeOrder(int $userId, string $pair, string $side, string $type, float $price, float $qty): array
    {
        $pair = strtoupper($pair);
        $side = strtoupper($side);
        $type = strtolower($type);

        $walletId = $this->getWalletId($userId);
        if (!$walletId) return ['success' => false, 'message' => 'Wallet not found'];

        $base = 'XAU';
        $quote = 'USDT';
        $stmtPair = $this->db->prepare("SELECT base_asset, quote_asset FROM trading_pairs WHERE symbol = ? LIMIT 1");
        $stmtPair->bind_param("s", $pair);
        $stmtPair->execute();
        $pairRow = $stmtPair->get_result()->fetch_assoc();
        $stmtPair->close();
        if ($pairRow) {
            $base = $pairRow['base_asset'];
            $quote = $pairRow['quote_asset'];
        } else {
            if (str_ends_with($pair, 'USDT')) {
                $base  = substr($pair, 0, -4);
                $quote = 'USDT';
            } else {
                $base  = substr($pair, 0, 3);
                $quote = substr($pair, 3);
            }
        }

        $this->db->begin_transaction();
        try {
            if ($type === 'market') {
                $marketPrice = $this->fetchMarketPrice($pair);
                if ($marketPrice === null || $marketPrice <= 0) {
                    throw new Exception('Market price unavailable');
                }
                $price = $marketPrice;
                $total = $qty * $price;

                if ($side === 'BUY') {
                    // check quote balance
                    $bal = $this->getBalanceRow($walletId, $quote);
                    $available = ($bal['balance'] ?? 0) - ($bal['reserved'] ?? 0);
                    if ($available < $total) throw new Exception('Insufficient ' . $quote . ' balance');

                    // reserve quote balance
                    $this->upsertBalance($walletId, $quote, 0, $total);
                } else {
                    // SELL: check base balance
                    $bal = $this->getBalanceRow($walletId, $base);
                    $available = ($bal['balance'] ?? 0) - ($bal['reserved'] ?? 0);
                    if ($available < $qty) throw new Exception('Insufficient ' . $base . ' balance');

                    // reserve base balance
                    $this->upsertBalance($walletId, $base, 0, $qty);
                }

                // record order as open / queued
                $stmt = $this->db->prepare(
                    "INSERT INTO orders (user_id, pair, side, type, price, qty, filled_qty, total, status) VALUES (?, ?, ?, ?, ?, ?, 0, ?, 'open')"
                );
                $stmt->bind_param('isssddd', $userId, $pair, $side, $type, $price, $qty, $total);
                $stmt->execute();
                $orderId = $stmt->insert_id;
                $stmt->close();

                $this->db->commit();
                return ['success' => true, 'message' => 'Market order placed and queued', 'order_id' => $orderId];
            }

            // Limit order: reserve balances and insert as open order
            if ($type === 'limit') {
                if ($price <= 0) throw new Exception('Limit order requires price');
                $total = $qty * $price;
                if ($side === 'BUY') {
                    $bal = $this->getBalanceRow($walletId, $quote);
                    $available = ($bal['balance'] ?? 0) - ($bal['reserved'] ?? 0);
                    if ($available < $total) throw new Exception('Insufficient ' . $quote . ' balance to reserve');
                    // reserve quote
                    $this->upsertBalance($walletId, $quote, 0, $total);
                } else {
                    $bal = $this->getBalanceRow($walletId, $base);
                    $available = ($bal['balance'] ?? 0) - ($bal['reserved'] ?? 0);
                    if ($available < $qty) throw new Exception('Insufficient ' . $base . ' balance to reserve');
                    $this->upsertBalance($walletId, $base, 0, $qty);
                }

                $stmt = $this->db->prepare(
                    "INSERT INTO orders (user_id, pair, side, type, price, qty, filled_qty, total, status) VALUES (?, ?, ?, ?, ?, ?, 0, ?, 'open')"
                );
                $stmt->bind_param('isssddd', $userId, $pair, $side, $type, $price, $qty, $total);
                $stmt->execute();
                $orderId = $stmt->insert_id;
                $stmt->close();

                $this->db->commit();
                return ['success' => true, 'message' => 'Limit order placed and queued', 'order_id' => $orderId];
            }

            throw new Exception('Unsupported order type');

        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => $e->getMessage(), 'order_id' => null];
        }
    }
}

?>
