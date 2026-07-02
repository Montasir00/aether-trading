<?php
// Prevent PHP warnings/errors from corrupting JSON output
ini_set('display_errors', 0);
ini_set('log_errors', 1);
/**
 * sell_settle.php — Blockchain Sell Settlement Endpoint
 * ───────────────────────────────────────────────────
 * Called by the browser (web3_trade.js) when a user wants to SELL a commodity for ETH.
 *
 * Flow:
 *  1. Frontend submits POST: { asset, qty }
 *  2. Backend fetches live price of asset and ETH via fetchBinancePrice
 *  3. Backend calculates: ETH to release = (asset_qty * asset_price) / eth_price
 *  4. Backend verifies that the user has enough commodity balance in MySQL
 *  5. Backend calls smart contract releaseFunds() by running Hardhat script
 *  6. If transaction succeeds, backend updates MySQL balances (deducts commodity) and logs transaction
 *  7. Returns transaction hash
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../api/api_helper.php';

$userId = (int)$_SESSION['id'];
$asset       = strtoupper(trim($_POST['asset'] ?? ''));
$qty         = (float)($_POST['qty'] ?? 0);
$postEthAddr = trim($_POST['eth_address'] ?? '');

if (empty($asset)) {
    echo json_encode(['success' => false, 'error' => 'Invalid asset']);
    exit;
}
if ($qty <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid quantity']);
    exit;
}

// 1. Fetch user's connected MetaMask address
$stmt = $conn->prepare("SELECT eth_address FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$ethAddress = $row['eth_address'] ?? null;
// Fallback 1: check session (set by confirm_trade.php when wallet is connected)
if (empty($ethAddress) && !empty($_SESSION['eth_address'])) {
    $ethAddress = $_SESSION['eth_address'];
}
// Fallback 2: use the eth_address sent with this POST request (from JS)
if (empty($ethAddress) && !empty($postEthAddr) && preg_match('/^0x[a-fA-F0-9]{40}$/', $postEthAddr)) {
    $ethAddress = $postEthAddr;
}
if (empty($ethAddress)) {
    echo json_encode(['success' => false, 'error' => 'Please connect MetaMask first to save your wallet address.']);
    exit;
}
// Ensure the eth_address is persisted to DB and session for future use
if (!empty($ethAddress) && empty($row['eth_address'])) {
    $saveAddr = $conn->prepare("UPDATE users SET eth_address = ? WHERE id = ?");
    $saveAddr->bind_param("si", $ethAddress, $userId);
    $saveAddr->execute();
    $saveAddr->close();
    $_SESSION['eth_address'] = $ethAddress;
}

// 2. Verify or create the user's wallet
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
} else {
    $walletId = (int)$wRow['id'];
}

$bStmt = $conn->prepare("SELECT balance FROM balances WHERE wallet_id = ? AND asset = ? LIMIT 1");
$bStmt->bind_param("is", $walletId, $asset);
$bStmt->execute();
$bRow = $bStmt->get_result()->fetch_assoc();
$bStmt->close();

$currentBalance = $bRow ? (float)$bRow['balance'] : 0.0;
if ($currentBalance < $qty) {
    echo json_encode(['success' => false, 'error' => "Insufficient $asset balance. You have $currentBalance oz."]);
    exit;
}

// 3. Fetch prices & calculate ETH amount
$assetPrice = fetchBinancePrice($asset . 'USDT');
$ethPrice   = fetchBinancePrice('ETHUSDT');

if ($assetPrice === null || $ethPrice === null || $ethPrice <= 0) {
    echo json_encode(['success' => false, 'error' => 'Unable to fetch exchange rates. Try again.']);
    exit;
}

$totalUsdt  = $qty * $assetPrice;
$ethToRelease = $totalUsdt / $ethPrice;
$weiToRelease = number_format(round($ethToRelease * 1e18), 0, '.', '');

// 4. Call the releaseFunds contract function using Web3.php
$tradeId = date('YmdHis') . rand(100, 999);

require_once __DIR__ . '/../vendor/autoload.php';

$ganacheUrl = defined('GANACHE_RPC_URL') ? GANACHE_RPC_URL : 'http://host.docker.internal:7545';
$contractAddress = defined('CONTRACT_ADDRESS') ? CONTRACT_ADDRESS : '';

$web3 = new \Web3\Web3($ganacheUrl);

$contractAbi = [
    [
        "inputs" => [],
        "name" => "owner",
        "outputs" => [
            [
                "internalType" => "address",
                "name" => "",
                "type" => "address"
            ]
        ],
        "stateMutability" => "view",
        "type" => "function"
    ],
    [
        "inputs" => [
            [
                "internalType" => "uint256",
                "name" => "_tradeId",
                "type" => "uint256"
            ],
            [
                "internalType" => "address payable",
                "name" => "_seller",
                "type" => "address"
            ],
            [
                "internalType" => "string",
                "name" => "_asset",
                "type" => "string"
            ]
        ],
        "name" => "releaseFunds",
        "outputs" => [],
        "stateMutability" => "payable",
        "type" => "function"
    ]
];

$contract = new \Web3\Contract($ganacheUrl, $contractAbi);

// Fetch contract owner to send transaction from
$ownerAddress = null;
$contract->at($contractAddress)->call('owner', function ($err, $res) use (&$ownerAddress) {
    if (!$err && !empty($res)) {
        $ownerAddress = $res[0];
    }
});

if (empty($ownerAddress)) {
    // Fallback: fetch accounts from Ganache
    $web3->eth->accounts(function ($err, $accounts) use (&$ownerAddress) {
        if (!$err && !empty($accounts)) {
            $ownerAddress = $accounts[0];
        }
    });
}

if (empty($ownerAddress)) {
    echo json_encode(['success' => false, 'error' => 'Unable to determine owner account on Ganache.']);
    exit;
}

// Convert weiToRelease (string) to hex representation for value
$hexWei = \Web3\Utils::toHex(\Web3\Utils::toBn($weiToRelease), true);

$txHash = null;
$releaseErr = null;

$contract->at($contractAddress)->send('releaseFunds', $tradeId, $ethAddress, $asset, [
    'from'  => $ownerAddress,
    'value' => $hexWei,
    'gas'   => '0x7A120' // 500,000 gas limit (safe buffer)
], function ($err, $tx) use (&$txHash, &$releaseErr) {
    if ($err) {
        $releaseErr = $err->getMessage();
    } else {
        $txHash = $tx;
    }
});

if ($releaseErr) {
    echo json_encode(['success' => false, 'error' => 'On-chain fund release failed: ' . $releaseErr]);
    exit;
}

if (empty($txHash) || !preg_match('/^0x[a-fA-F0-9]{64}$/', $txHash)) {
    echo json_encode(['success' => false, 'error' => 'Invalid transaction hash returned by Ganache: ' . $txHash]);
    exit;
}

// 5. Update balances in MySQL (DB Transaction)
$conn->begin_transaction();
try {
    // Deduct commodity balance
    $deductStmt = $conn->prepare("UPDATE balances SET balance = balance - ? WHERE wallet_id = ? AND asset = ?");
    $deductStmt->bind_param("dis", $qty, $walletId, $asset);
    $deductStmt->execute();
    $deductStmt->close();

    // Add ETH to the user's platform balance tracker
    $addEthStmt = $conn->prepare(
        "INSERT INTO balances (wallet_id, asset, balance, reserved) VALUES (?, 'ETH', ?, 0)
         ON DUPLICATE KEY UPDATE balance = balance + ?"
    );
    $addEthStmt->bind_param("idd", $walletId, $ethToRelease, $ethToRelease);
    $addEthStmt->execute();
    $addEthStmt->close();

    // Log the transaction with price in USD/unit and total in ETH
    $type    = 'SELL';
    $oType   = 'market';
    $status  = 'completed';
    $pricePerUnit = ($assetPrice !== null) ? $assetPrice : 0.0;
    $logStmt = $conn->prepare(
        "INSERT INTO transactions (user_id, type, coin, amount, price, total, order_type, status, tx_hash)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $logStmt->bind_param("issdddsss", $userId, $type, $asset, $qty, $pricePerUnit, $ethToRelease, $oType, $status, $txHash);
    $logStmt->execute();
    $logStmt->close();

    $conn->commit();

    echo json_encode([
        'success'      => true,
        'txHash'       => $txHash,
        'asset'        => $asset,
        'qty'          => $qty,
        'eth_released' => $ethToRelease,
        'message'      => "Trade settled! $qty $asset sold. $ethToRelease ETH sent to your wallet."
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Database update failed: ' . $e->getMessage()]);
}
