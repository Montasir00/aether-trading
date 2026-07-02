<?php
/**
 * save_limit_order_onchain.php
 *
 * Called by the frontend (web3_trade.js) AFTER:
 *   1. The user signed the limit order via EIP-712 (signature stored here)
 *   2. For BUY orders: ETH was escrowed in AetherTrade.sol (escrow_tx_hash stored here)
 *
 * This endpoint:
 *   - Saves the signature + escrow tx hash on the orders row
 *   - Creates a record in limit_order_escrows (for BUY orders)
 *   - Enqueues the order to Redis for the matching daemon to pick up
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

require_once __DIR__ . '/../config.php';

$user_id        = (int)$_SESSION['id'];
$order_id       = (int)($_POST['order_id']       ?? 0);
$eth_signature  = trim($_POST['eth_signature']   ?? '');
$escrow_tx_hash = trim($_POST['escrow_tx_hash']  ?? '');
$eth_address    = trim($_POST['eth_address']      ?? '');

if ($order_id <= 0 || !$eth_signature) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// ── Verify the order belongs to this user ────────────────────────────────────
$stmtOwn = $conn->prepare("SELECT id, side, qty, price, total, status FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
$stmtOwn->bind_param("ii", $order_id, $user_id);
$stmtOwn->execute();
$order = $stmtOwn->get_result()->fetch_assoc();
$stmtOwn->close();

if (!$order) {
    echo json_encode(['success' => false, 'error' => 'Order not found or access denied']);
    exit;
}
if ($order['status'] !== 'open') {
    echo json_encode(['success' => false, 'error' => 'Order is no longer open']);
    exit;
}

// ── Save signature + escrow details on the order row ─────────────────────────
$stmtUpd = $conn->prepare(
    "UPDATE orders 
     SET eth_signature = ?, 
         escrow_tx_hash = ?,
         eth_wallet_address = ?
     WHERE id = ? AND user_id = ?"
);
$stmtUpd->bind_param("sssii", $eth_signature, $escrow_tx_hash, $eth_address, $order_id, $user_id);
$stmtUpd->execute();
$stmtUpd->close();

// ── If this is a BUY order with ETH escrowed, record it in limit_order_escrows ─
if ($order['side'] === 'BUY' && $escrow_tx_hash) {
    // Calculate approximate ETH amount from the order total
    // (exact Wei is tracked on-chain; we store a human-readable approximation)
    $ethPrice   = 3000.0; // fallback; ideally fetched
    $ethEther   = $order['total'] / $ethPrice;
    $ethWei     = bcmul((string)$ethEther, '1000000000000000000', 0); // approximate Wei

    $stmtEsc = $conn->prepare(
        "INSERT INTO limit_order_escrows
            (order_id, user_id, eth_wallet, eth_wei, eth_ether, escrow_tx_hash, status)
         VALUES (?, ?, ?, ?, ?, ?, 'locked')
         ON DUPLICATE KEY UPDATE
            escrow_tx_hash = VALUES(escrow_tx_hash),
            status = 'locked'"
    );
    $stmtEsc->bind_param("iissds", $order_id, $user_id, $eth_address, $ethWei, $ethEther, $escrow_tx_hash);
    $stmtEsc->execute();
    $stmtEsc->close();
}

// ── Enqueue to Redis so the matching daemon can try to match it ───────────────
try {
    require_once __DIR__ . '/../vendor/autoload.php';
    $redisHost = getenv('REDIS_HOST') ?: '127.0.0.1';
    $redisPort = getenv('REDIS_PORT') ?: 6379;
    $redis = new Predis\Client([
        'scheme' => 'tcp',
        'host'   => $redisHost,
        'port'   => $redisPort
    ]);
    $redis->lpush('order_ingest_queue', $order_id);
} catch (Exception $e) {
    // Redis offline is non-fatal here — the reconciliation daemon will pick it up
    error_log("[save_limit_order_onchain] Redis unavailable: " . $e->getMessage());
}

echo json_encode([
    'success'  => true,
    'order_id' => $order_id,
    'message'  => 'On-chain limit order saved and queued for matching'
]);
exit;
