<?php

/**
 * Generic commodity trade function for the bot / wallet operations.
 *
 * @param int    $userId  The user performing the trade
 * @param string $action  'BUY' or 'SELL'
 * @param string $coin    'XAU' or 'XAG'
 * @param float  $price   Current price per troy oz in USDT
 */
function tradeAsset(int $userId, string $action, string $coin, float $price): bool
{
    global $conn;
    require_once __DIR__ . '/config.php';

    $action = strtoupper($action);
    $coin   = strtoupper($coin);

    // Fixed bot trade size: 0.01 oz of gold/silver
    $amount = 0.01;
    $total  = $amount * $price;

    // Map coin to its balance column
    $balanceColumn = ($coin === 'XAU') ? 'xau_balance' : 'xag_balance';

    $conn->begin_transaction();
    try {
        // Enforce all risk rules before bot executes — same checks as manual orders.
        // Without this, the bot bypasses max trade %, daily volume, and drawdown lockout.
        require_once __DIR__ . '/trading_engine/RiskManager.php';
        $risk  = new RiskManager($conn, $userId);
        $check = $risk->validateTrade($action, $coin, $amount, $price);
        if (!$check['allowed']) {
            throw new Exception("Risk check blocked bot trade for USER $userId: " . implode('; ', $check['errors']));
        }
        if ($action === 'BUY') {
            // Check USDT balance — but account for amounts already reserved for
            // pending limit orders in the balances table. Without this, the bot
            // could spend USDT that is locked in a user's open limit order.
            $stmt = $conn->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            // Fetch reserved USDT from wallets/balances table
            $reservedUsdt = 0.0;
            $rStmt = $conn->prepare(
                "SELECT COALESCE(b.reserved, 0) AS reserved
                 FROM wallets w
                 JOIN balances b ON b.wallet_id = w.id AND b.asset = 'USDT'
                 WHERE w.user_id = ? LIMIT 1"
            );
            if ($rStmt) {
                $rStmt->bind_param("i", $userId);
                $rStmt->execute();
                $rRow = $rStmt->get_result()->fetch_assoc();
                $rStmt->close();
                if ($rRow) $reservedUsdt = (float)$rRow['reserved'];
            }
            $availableUsdt = (float)($user['balance'] ?? 0) - $reservedUsdt;

            if (!$user || $availableUsdt < $total) {
                throw new Exception("USER $userId BUY $coin failed: insufficient USDT (need $total, have $availableUsdt available after reservations)");
            }

            // Deduct USDT, add commodity
            $stmt = $conn->prepare("UPDATE users SET balance = balance - ?, $balanceColumn = $balanceColumn + ? WHERE id = ?");
            $stmt->bind_param("ddi", $total, $amount, $userId);
            $stmt->execute();
            $stmt->close();

            // Check if wallet exists to also update balances table
            $walletStmt = $conn->prepare("SELECT id FROM wallets WHERE user_id = ? LIMIT 1");
            $walletStmt->bind_param("i", $userId);
            $walletStmt->execute();
            $wRow = $walletStmt->get_result()->fetch_assoc();
            $walletStmt->close();

            if ($wRow) {
                $walletId = (int)$wRow['id'];
                
                // Deduct USDT
                $stmt = $conn->prepare("UPDATE balances SET balance = balance - ? WHERE wallet_id = ? AND asset = 'USDT'");
                $stmt->bind_param("di", $total, $walletId);
                $stmt->execute();
                $stmt->close();

                // Add commodity (XAU/XAG)
                $stmt = $conn->prepare("UPDATE balances SET balance = balance + ? WHERE wallet_id = ? AND asset = ?");
                $stmt->bind_param("dis", $amount, $walletId, $coin);
                $stmt->execute();
                $stmt->close();
            }

            error_log("USER $userId BUY $coin at $price — SUCCESS");

        } elseif ($action === 'SELL') {
            // Check commodity balance
            $stmt = $conn->prepare("SELECT $balanceColumn FROM users WHERE id = ? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$user) {
                throw new Exception("USER $userId SELL $coin failed: user not found");
            }

            // Fetch reserved commodity from wallets/balances table
            $reservedCoin = 0.0;
            $rStmt = $conn->prepare(
                "SELECT COALESCE(b.reserved, 0) AS reserved
                 FROM wallets w
                 JOIN balances b ON b.wallet_id = w.id AND b.asset = ?
                 WHERE w.user_id = ? LIMIT 1"
            );
            if ($rStmt) {
                $rStmt->bind_param("si", $coin, $userId);
                $rStmt->execute();
                $rRow = $rStmt->get_result()->fetch_assoc();
                $rStmt->close();
                if ($rRow) $reservedCoin = (float)$rRow['reserved'];
            }
            $availableCoin = (float)($user[$balanceColumn] ?? 0) - $reservedCoin;

            if ($availableCoin < $amount) {
                throw new Exception("USER $userId SELL $coin failed: insufficient $coin (need $amount, have $availableCoin available after reservations)");
            }

            // Deduct commodity, add USDT
            $stmt = $conn->prepare("UPDATE users SET $balanceColumn = $balanceColumn - ?, balance = balance + ? WHERE id = ?");
            $stmt->bind_param("ddi", $amount, $total, $userId);
            $stmt->execute();
            $stmt->close();

            // Check if wallet exists to also update balances table
            $walletStmt = $conn->prepare("SELECT id FROM wallets WHERE user_id = ? LIMIT 1");
            $walletStmt->bind_param("i", $userId);
            $walletStmt->execute();
            $wRow = $walletStmt->get_result()->fetch_assoc();
            $walletStmt->close();

            if ($wRow) {
                $walletId = (int)$wRow['id'];
                
                // Deduct commodity (XAU/XAG)
                $stmt = $conn->prepare("UPDATE balances SET balance = balance - ? WHERE wallet_id = ? AND asset = ?");
                $stmt->bind_param("dis", $amount, $walletId, $coin);
                $stmt->execute();
                $stmt->close();

                // Add USDT
                $stmt = $conn->prepare("UPDATE balances SET balance = balance + ? WHERE wallet_id = ? AND asset = 'USDT'");
                $stmt->bind_param("di", $total, $walletId);
                $stmt->execute();
                $stmt->close();
            }

            error_log("USER $userId SELL $coin at $price — SUCCESS");

        } else {
            throw new Exception("tradeAsset: unknown action '$action' for user $userId");
        }

        // Log transaction
        $type      = $action;
        $orderType = 'market';
        $status    = 'completed';
        $stmt = $conn->prepare(
            "INSERT INTO transactions (user_id, type, coin, amount, price, total, order_type, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("issdddss", $userId, $type, $coin, $amount, $price, $total, $orderType, $status);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        error_log($e->getMessage());
        return false;
    }
}

// ---------------------------------------------------------------------------
// Convenience wrappers used by TradeExecutor (bot)
// ---------------------------------------------------------------------------
function buyXAU(int $userId, float $price): bool  { return tradeAsset($userId, 'BUY',  'XAU', $price); }
function sellXAU(int $userId, float $price): bool { return tradeAsset($userId, 'SELL', 'XAU', $price); }
function buyXAG(int $userId, float $price): bool  { return tradeAsset($userId, 'BUY',  'XAG', $price); }
function sellXAG(int $userId, float $price): bool { return tradeAsset($userId, 'SELL', 'XAG', $price); }
