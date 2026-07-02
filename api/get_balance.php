<?php
session_start();
header('Content-Type: application/json');

try {
    if (!isset($_SESSION['id'])) {
        echo json_encode(['error' => 'User not logged in']);
        exit;
    }

    // Unlock session early since we only read from it
    session_write_close();

    require_once '../config.php';
    require_once 'api_helper.php';

    // Helper to calculate the average buy price of an asset for a user
    function getAverageBuyPrice($conn, $userId, $asset) {
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(total), 0) AS total_spent, COALESCE(SUM(amount), 0) AS total_qty 
            FROM transactions 
            WHERE user_id = ? AND coin = ? AND type = 'BUY' AND status = 'completed'
        ");
        $stmt->bind_param("is", $userId, $asset);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $pairSymbol = $asset . 'USDT';
        $stmt2 = $conn->prepare("
            SELECT COALESCE(SUM(total), 0) AS total_spent, COALESCE(SUM(qty), 0) AS total_qty
            FROM orders 
            WHERE user_id = ? AND pair = ? AND side = 'BUY' AND status = 'completed'
        ");
        $stmt2->bind_param("is", $userId, $pairSymbol);
        $stmt2->execute();
        $res2 = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();

        $spent = (float)$res['total_spent'] + (float)$res2['total_spent'];
        $qty = (float)$res['total_qty'] + (float)$res2['total_qty'];
        
        return $qty > 0 ? ($spent / $qty) : 0.0;
    }

    $user_id = $_SESSION['id'];
    $stmt = $conn->prepare("SELECT w.id AS wallet_id FROM wallets w WHERE w.user_id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $wallet = $result->fetch_assoc();
    $stmt->close();

    if ($wallet) {
        $walletId = (int)$wallet['wallet_id'];
        $stmt = $conn->prepare("SELECT asset, balance, reserved FROM balances WHERE wallet_id = ?");
        $stmt->bind_param("i", $walletId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $balance = 0.0;
        $xau = 0.0;
        $xag = 0.0;
        $balances = [];
        foreach ($rows as $row) {
            $asset = strtoupper((string)($row['asset'] ?? ''));
            $amount = (float)($row['balance'] ?? 0) - (float)($row['reserved'] ?? 0);
            if ($asset === 'USDT') $balance = $amount;
            if ($asset === 'XAU') $xau = $amount;
            if ($asset === 'XAG') $xag = $amount;
            $balances[$asset] = $amount;
        }

        // Calculate USD/USDT valuations, prices, average purchase cost, and PnL
        $prices = [];
        $valuations = [];
        $avg_buy_prices = [];
        $pnls = [];
        $pnl_percents = [];
        $totalValuation = 0.0;
        foreach ($balances as $asset => $amount) {
            if ($asset === 'USDT') {
                $price = 1.0;
                $avgBuy = 1.0;
            } else {
                $price = fetchBinancePrice($asset . 'USDT');
                $price = $price !== null ? (float)$price : 0.0;
                $avgBuy = getAverageBuyPrice($conn, $user_id, $asset);
            }
            
            $prices[$asset] = $price;
            $val = $amount * $price;
            $valuations[$asset] = $val;
            $totalValuation += $val;
            
            $avg_buy_prices[$asset] = $avgBuy;
            if ($asset !== 'USDT' && $avgBuy > 0) {
                $costBasis = $amount * $avgBuy;
                $pnl = $val - $costBasis;
                $pnlPct = ($pnl / $costBasis) * 100;
            } else {
                $pnl = 0.0;
                $pnlPct = 0.0;
            }
            $pnls[$asset] = $pnl;
            $pnl_percents[$asset] = $pnlPct;
        }

        echo json_encode([
            'balance'         => number_format($balance, 2, '.', ''),
            'xau_balance'     => number_format($xau, 6, '.', ''),
            'xag_balance'     => number_format($xag, 4, '.', ''),
            'balances'        => $balances,
            'prices'          => $prices,
            'valuations'      => $valuations,
            'avg_buy_prices'  => $avg_buy_prices,
            'pnls'            => $pnls,
            'pnl_percents'    => $pnl_percents,
            'total_valuation' => number_format($totalValuation, 2, '.', ''),
            'eth_price'       => (float)fetchBinancePrice('ETHUSDT')
        ]);
        $conn->close();
        exit;
    }

    $stmt = $conn->prepare("SELECT balance, xau_balance, xag_balance FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        echo json_encode(['error' => 'User not found']);
        exit;
    }

    // Fallback valuation
    $balances = [
        'USDT' => (float)$user['balance'],
        'XAU'  => (float)$user['xau_balance'],
        'XAG'  => (float)$user['xag_balance']
    ];
    $prices = [];
    $valuations = [];
    $avg_buy_prices = [];
    $pnls = [];
    $pnl_percents = [];
    $totalValuation = 0.0;
    foreach ($balances as $asset => $amount) {
        if ($asset === 'USDT') {
            $price = 1.0;
            $avgBuy = 1.0;
        } else {
            $price = fetchBinancePrice($asset . 'USDT');
            $price = $price !== null ? (float)$price : 0.0;
            $avgBuy = getAverageBuyPrice($conn, $user_id, $asset);
        }
        $prices[$asset] = $price;
        $val = $amount * $price;
        $valuations[$asset] = $val;
        $totalValuation += $val;
        
        $avg_buy_prices[$asset] = $avgBuy;
        if ($asset !== 'USDT' && $avgBuy > 0) {
            $costBasis = $amount * $avgBuy;
            $pnl = $val - $costBasis;
            $pnlPct = ($pnl / $costBasis) * 100;
        } else {
            $pnl = 0.0;
            $pnlPct = 0.0;
        }
        $pnls[$asset] = $pnl;
        $pnl_percents[$asset] = $pnlPct;
    }

    echo json_encode([
        'balance'         => number_format((float)$user['balance'],     2, '.', ''),
        'xau_balance'     => number_format((float)$user['xau_balance'], 6, '.', ''),
        'xag_balance'     => number_format((float)$user['xag_balance'], 4, '.', ''),
        'balances'        => $balances,
        'prices'          => $prices,
        'valuations'      => $valuations,
        'avg_buy_prices'  => $avg_buy_prices,
        'pnls'            => $pnls,
        'pnl_percents'    => $pnl_percents,
        'total_valuation' => number_format($totalValuation, 2, '.', ''),
        'eth_price'       => (float)fetchBinancePrice('ETHUSDT')
    ]);

    $conn->close();
} catch (Throwable $e) {
    error_log('get_balance.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to fetch balance']);
}
