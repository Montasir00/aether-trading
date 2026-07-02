<?php
/**
 * save_eth_address.php — Saves the user's MetaMask address to the DB
 * Called by web3_trade.js after MetaMask connects.
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false]);
    exit;
}

require_once __DIR__ . '/../config.php';

$address = trim($_POST['eth_address'] ?? '');
$userId  = (int)$_SESSION['id'];

if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
    echo json_encode(['success' => false, 'error' => 'Invalid ETH address format']);
    exit;
}

$stmt = $conn->prepare("UPDATE users SET eth_address = ? WHERE id = ?");
$stmt->bind_param("si", $address, $userId);
$stmt->execute();
$stmt->close();

// Also persist to session so sell_settle.php can use it immediately
$_SESSION['eth_address'] = $address;

// Ensure user has a wallet record and initialized USDT balance
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
}

echo json_encode(['success' => true]);
