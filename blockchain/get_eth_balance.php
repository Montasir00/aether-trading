<?php
// Prevent PHP warnings/errors from corrupting JSON output
ini_set('display_errors', 0);
ini_set('log_errors', 1);
/**
 * get_eth_balance.php — Returns the user's live ETH balance from Ganache
 * ────────────────────────────────────────────────────────────────────────
 * Called by the frontend dashboard to show the Ganache ETH balance
 * alongside existing USDT and commodity balances.
 *
 * Returns JSON: { success: true, balance: "97.42", address: "0xABC..." }
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Web3\Web3;

$userId = (int)$_SESSION['id'];

// Fetch the user's stored MetaMask/Ganache address
$stmt = $conn->prepare("SELECT eth_address FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$ethAddress = $row['eth_address'] ?? null;

if (empty($ethAddress)) {
    echo json_encode([
        'success' => false,
        'error'   => 'No ETH wallet linked. Connect MetaMask first.',
        'balance' => '0',
        'address' => null
    ]);
    exit;
}

// Query Ganache for the real-time ETH balance
$ganacheUrl = defined('GANACHE_RPC_URL') ? GANACHE_RPC_URL : 'http://host.docker.internal:7545';
$web3 = new Web3($ganacheUrl);

$balanceWei = null;
$rpcError   = null;

$web3->eth->getBalance($ethAddress, 'latest', function ($err, $balance) use (&$balanceWei, &$rpcError) {
    if ($err) {
        $rpcError = $err->getMessage();
    } else {
        $balanceWei = $balance;
    }
});

if ($rpcError) {
    echo json_encode(['success' => false, 'error' => 'Ganache RPC error: ' . $rpcError]);
    exit;
}

// Convert from Wei BigNumber to ETH float string
$ethBalance = '0';
if ($balanceWei !== null) {
    // Web3.php returns a phpseclib BigInteger — toString() gives decimal string
    $weiDecimal = method_exists($balanceWei, 'toString') ? $balanceWei->toString() : (string)$balanceWei;
    // Use GMP for precision (GMP is installed; bcdiv/bcmath is not)
    if (function_exists('gmp_init') && $weiDecimal !== '' && $weiDecimal !== '0') {
        $weiGmp   = gmp_init($weiDecimal);
        $eth      = (float)gmp_strval($weiGmp) / 1e18;
        $ethBalance = number_format($eth, 6, '.', '');
    } else {
        $ethBalance = number_format((float)$weiDecimal / 1e18, 6, '.', '');
    }
}

echo json_encode([
    'success' => true,
    'balance' => $ethBalance,
    'address' => $ethAddress
]);
