<?php
// Prevent PHP warnings/errors from corrupting JSON output
ini_set('display_errors', 0);
ini_set('log_errors', 1);
/**
 * confirm_trade.php — Blockchain Trade Confirmation Endpoint
 * ──────────────────────────────────────────────────────────
 * Called by the browser (web3_trade.js) AFTER MetaMask confirms a BUY transaction.
 *
 * Flow:
 *  1. JS sends: { txHash, asset, qty, eth_address }
 *  2. This file verifies the tx on Ganache via Web3.php
 *  3. If valid → credits commodity balance in MySQL
 *  4. Logs the on-chain transaction hash in the transactions table
 *
 * SELL settlements are handled in sell_settle.php (called by the exchange server-side).
 */

session_start();
header('Content-Type: application/json');

// Must be logged in
if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Web3\Web3;
use Web3\Utils;

// ── Input validation ─────────────────────────────────────────────────────────
$txHash     = trim($_POST['txHash']     ?? '');
$asset      = strtoupper(trim($_POST['asset']  ?? ''));
$qty        = (float)($_POST['qty']            ?? 0);
$ethAddress = trim($_POST['eth_address']       ?? '');
$userId     = (int)$_SESSION['id'];

if (empty($txHash) || !preg_match('/^0x[a-fA-F0-9]{64}$/', $txHash)) {
    echo json_encode(['success' => false, 'error' => 'Invalid transaction hash']);
    exit;
}
// Fetch active assets from database for validation
$pairsStmt = $conn->query("SELECT base_asset FROM trading_pairs WHERE active = 1");
$validAssets = [];
if ($pairsStmt) {
    while ($pRow = $pairsStmt->fetch_assoc()) {
        $validAssets[] = strtoupper($pRow['base_asset']);
    }
}

if (empty($asset) || !in_array($asset, $validAssets, true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid asset: ' . $asset]);
    exit;
}
if ($qty <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid quantity']);
    exit;
}

// ── Check for duplicate settlement (replay attack prevention) ─────────────────
$dup = $conn->prepare("SELECT id FROM transactions WHERE tx_hash = ? LIMIT 1");
$dup->bind_param("s", $txHash);
$dup->execute();
$dupResult = $dup->get_result();
$dup->close();

if ($dupResult->num_rows > 0) {
    echo json_encode(['success' => false, 'error' => 'Transaction already settled']);
    exit;
}

// ── Verify on-chain via Web3.php → Ganache ───────────────────────────────────
$ganacheUrl = defined('GANACHE_RPC_URL') ? GANACHE_RPC_URL : 'http://host.docker.internal:7545';
$web3 = new Web3($ganacheUrl);

$receipt  = null;
$rpcError = null;

$web3->eth->getTransactionReceipt($txHash, function ($err, $rec) use (&$receipt, &$rpcError) {
    if ($err) {
        $rpcError = $err->getMessage();
    } else {
        $receipt = $rec;
    }
});

if ($rpcError) {
    error_log("confirm_trade.php: Ganache RPC error for $txHash: $rpcError");
    echo json_encode(['success' => false, 'error' => 'Blockchain RPC error: ' . $rpcError]);
    exit;
}

if (!$receipt) {
    echo json_encode(['success' => false, 'error' => 'Transaction not yet mined — please wait and retry']);
    exit;
}

// status = '0x1' means SUCCESS on Ethereum
if (!isset($receipt->status) || $receipt->status !== '0x1') {
    echo json_encode(['success' => false, 'error' => 'Transaction failed or was reverted on-chain']);
    exit;
}

// ── Read how much ETH was paid (from the transaction itself) ──────────────────
$tx        = null;
$txRpcErr  = null;
$web3->eth->getTransactionByHash($txHash, function ($err, $t) use (&$tx, &$txRpcErr) {
    if ($err) $txRpcErr = $err->getMessage();
    else       $tx = $t;
});

// ── Fetch real on-chain ETH balance for this wallet (for accurate DB sync) ───
$onChainEthWei = null;
if (!empty($ethAddress)) {
    $web3->eth->getBalance($ethAddress, 'latest', function($err, $bal) use (&$onChainEthWei) {
        if (!$err) $onChainEthWei = $bal;
    });
}

// Convert hex Wei string to ETH — use GMP for precision on large 256-bit values
// $tx->value is a hex string like "0x1dd6559bdb170000"
if ($tx && !empty($tx->value)) {
    $hexVal = ltrim($tx->value, '0x');
    if ($hexVal === '' || $hexVal === '0') {
        $ethPaid = 0.0;
    } else {
        // GMP handles arbitrarily large integers without float overflow
        $weiGmp  = gmp_init($hexVal, 16);
        $ethPaid = (float)gmp_strval($weiGmp) / 1e18;
    }
} else {
    $ethPaid = 0.0;
}

// Compute real on-chain ETH balance for DB sync (accurate first-time insert)
$onChainEth = 100.0; // fallback default
if ($onChainEthWei !== null) {
    try {
        $weiStr = method_exists($onChainEthWei, 'toString') ? $onChainEthWei->toString() : (string)$onChainEthWei;
        $onChainEth = (float)$weiStr / 1e18;
    } catch (Throwable $e) {
        // keep fallback
    }
}

// ── Credit the commodity in MySQL (DB transaction) ────────────────────────────
$conn->begin_transaction();
try {
    // 1. Find or create the user's wallet
    $wStmt = $conn->prepare("SELECT id FROM wallets WHERE user_id = ? LIMIT 1");
    $wStmt->bind_param("i", $userId);
    $wStmt->execute();
    $wRow = $wStmt->get_result()->fetch_assoc();
    $wStmt->close();

    if (!$wRow) {
        $insertW = $conn->prepare("INSERT INTO wallets (user_id) VALUES (?)");
        $insertW->bind_param("i", $userId);
        $insertW->execute();
        $walletId = $insertW->insert_id;
        $insertW->close();

        // Initialize USDT balance from users table
        $uStmt = $conn->prepare("SELECT balance FROM users WHERE id = ? LIMIT 1");
        $uStmt->bind_param("i", $userId);
        $uStmt->execute();
        $uRow = $uStmt->get_result()->fetch_assoc();
        $uStmt->close();
        $userUsdt = $uRow ? (float)$uRow['balance'] : 10000.00;

        $initBal = $conn->prepare("INSERT INTO balances (wallet_id, asset, balance, reserved) VALUES (?, 'USDT', ?, 0)");
        $initBal->bind_param("id", $walletId, $userUsdt);
        $initBal->execute();
        $initBal->close();
    } else {
        $walletId = (int)$wRow['id'];
    }

    // 2. Add commodity balance (insert row if user has never traded this asset before)
    $bStmt = $conn->prepare(
        "INSERT INTO balances (wallet_id, asset, balance, reserved) VALUES (?, ?, ?, 0)
         ON DUPLICATE KEY UPDATE balance = balance + ?"
    );
    $qty2 = $qty;
    $bStmt->bind_param("isdd", $walletId, $asset, $qty, $qty2);
    $bStmt->execute();
    $bStmt->close();

    // 3. Deduct ETH balance from the platform's internal ETH tracker
    //    (The actual Ganache ETH already moved on-chain — this just keeps the
    //     platform's displayed ETH balance in sync with Ganache)
    $eStmt = $conn->prepare(
        "INSERT INTO balances (wallet_id, asset, balance, reserved) VALUES (?, 'ETH', ?, 0)
         ON DUPLICATE KEY UPDATE balance = balance - ?"
    );
    // Use real on-chain ETH balance for first-time insert (not hardcoded 100)
    $eStmt->bind_param("idd", $walletId, $onChainEth, $ethPaid);
    $eStmt->execute();
    $eStmt->close();

    // 4. Update eth_address on users table if provided
    if (!empty($ethAddress) && preg_match('/^0x[a-fA-F0-9]{40}$/', $ethAddress)) {
        $addrStmt = $conn->prepare("UPDATE users SET eth_address = ? WHERE id = ?");
        $addrStmt->bind_param("si", $ethAddress, $userId);
        $addrStmt->execute();
        $addrStmt->close();
        // Also store in session so sell_settle.php can use it immediately
        $_SESSION['eth_address'] = $ethAddress;
    }

    // 5. Log the transaction — store USD price per unit for proper display
    //    Fetch current USD price from Binance for this asset
    require_once __DIR__ . '/../api/api_helper.php';
    $usdPrice = fetchBinancePrice($asset . 'USDT');
    // Fallback: derive from ethPaid if price fetch fails
    $ethPriceUsdt = fetchBinancePrice('ETHUSDT');
    if ($usdPrice !== null) {
        $pricePerUnit = $usdPrice;
        $totalUsdt    = $qty * $usdPrice;
    } elseif ($ethPriceUsdt !== null && $ethPriceUsdt > 0) {
        $pricePerUnit = ($ethPaid * $ethPriceUsdt) / max($qty, 0.000001);
        $totalUsdt    = $ethPaid * $ethPriceUsdt;
    } else {
        $pricePerUnit = $qty > 0 ? ($ethPaid / $qty) : 0.0;
        $totalUsdt    = $ethPaid;
    }
    $type     = 'BUY';
    $oType    = 'market';
    $status   = 'completed';
    $logStmt  = $conn->prepare(
        "INSERT INTO transactions (user_id, type, coin, amount, price, total, order_type, status, tx_hash)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $logStmt->bind_param("issdddsss", $userId, $type, $asset, $qty, $pricePerUnit, $totalUsdt, $oType, $status, $txHash);
    $logStmt->execute();
    $logStmt->close();

    $conn->commit();

    echo json_encode([
        'success'  => true,
        'txHash'   => $txHash,
        'asset'    => $asset,
        'qty'      => $qty,
        'eth_paid' => $ethPaid,
        'message'  => "Trade confirmed! $qty $asset credited to your account."
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("confirm_trade.php settlement error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Settlement DB error: ' . $e->getMessage()]);
}
