<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'not authenticated']);
    exit;
}

$user_id = $_SESSION['id'];

// Check if user has a wallet
$stmt = $conn->prepare('SELECT id FROM wallets WHERE user_id = ? LIMIT 1');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$hasWallet = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$hasWallet) {
    // Dynamically provision wallet and migrate balances
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('INSERT IGNORE INTO wallets (user_id) VALUES (?)');
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare('SELECT id FROM wallets WHERE user_id = ? LIMIT 1');
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $walletRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($walletRow) {
            $walletId = (int)$walletRow['id'];
            // Fetch default balances from users table
            $stmt = $conn->prepare('SELECT balance, xau_balance, xag_balance FROM users WHERE id = ?');
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $userRow = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($userRow) {
                $usdt = (float)($userRow['balance'] ?? 0);
                $xau = (float)($userRow['xau_balance'] ?? 0);
                $xag = (float)($userRow['xag_balance'] ?? 0);

                // Insert USDT, XAU, and XAG balances
                $stmt = $conn->prepare('INSERT INTO balances (wallet_id, asset, balance, reserved) VALUES (?, ?, ?, 0)');
                
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
        }
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
    }
}

require_once __DIR__ . '/api_helper.php';

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

$stmt = $conn->prepare('SELECT w.id as wallet_id, b.asset, b.balance, b.reserved FROM wallets w LEFT JOIN balances b ON b.wallet_id = w.id WHERE w.user_id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$out = [];
$balances = [];
$totalValuation = 0.0;
foreach ($rows as $r) {
    $asset = $r['asset'] ?? 'USDT';
    $balance = (float)($r['balance'] ?? 0);
    $reserved = (float)($r['reserved'] ?? 0);
    
    if (strtoupper($asset) === 'USDT') {
        $price = 1.0;
        $avgBuy = 1.0;
    } else {
        $price = fetchBinancePrice(strtoupper($asset) . 'USDT');
        $price = $price !== null ? (float)$price : 0.0;
        $avgBuy = getAverageBuyPrice($conn, $user_id, $asset);
    }
    
    $availVal = ($balance - $reserved) * $price;
    $resVal = $reserved * $price;
    $totalValuation += ($balance * $price);
    
    $pnl = 0.0;
    $pnlPct = 0.0;
    if (strtoupper($asset) !== 'USDT' && $avgBuy > 0) {
        $costBasis = $balance * $avgBuy;
        $pnl = ($balance * $price) - $costBasis;
        $pnlPct = ($pnl / $costBasis) * 100;
    }
    
    $out[$asset] = [
        'balance' => $balance,
        'reserved' => $reserved,
        'price' => $price,
        'available_value' => $availVal,
        'reserved_value' => $resVal,
        'avg_buy_price' => $avgBuy,
        'pnl' => $pnl,
        'pnl_percent' => $pnlPct
    ];
    $balances[] = [
        'asset' => $asset,
        'balance' => $balance,
        'reserved' => $reserved,
        'price' => $price,
        'available_value' => $availVal,
        'reserved_value' => $resVal,
        'avg_buy_price' => $avgBuy,
        'pnl' => $pnl,
        'pnl_percent' => $pnlPct
    ];
}

echo json_encode([
    'wallet' => $out,
    'balances' => $balances,
    'total_valuation' => $totalValuation
]);

?>
