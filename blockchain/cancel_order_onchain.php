<?php
/**
 * cancel_order_onchain.php
 *
 * Called by the frontend AFTER the user has confirmed cancelLimitOrder()
 * in MetaMask and the on-chain tx has been mined (ETH already refunded by contract).
 *
 * This endpoint:
 *   - Verifies the order belongs to the logged-in user
 *   - Marks the order as 'cancelled' in the orders table
 *   - Records the cancel tx hash for audit
 *   - Updates the limit_order_escrows status to 'refunded'
 *   - Releases the reserved balance back to available in the wallets/balances tables
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

require_once __DIR__ . '/config.php';

$user_id        = (int)$_SESSION['id'];
$order_id       = (int)($_POST['order_id']      ?? 0);
$cancel_tx_hash = trim($_POST['cancel_tx_hash'] ?? '');

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid order ID']);
    exit;
}

// ── Verify the order belongs to this user and is still open ──────────────────
$stmtOwn = $conn->prepare(
    "SELECT o.id, o.user_id, o.side, o.pair, o.type, o.price, o.qty, o.filled_qty, 
            o.status, o.escrow_tx_hash, o.on_chain_settled,
            w.id AS wallet_id
     FROM orders o
     LEFT JOIN wallets w ON w.user_id = o.user_id
     WHERE o.id = ? AND o.user_id = ?
     LIMIT 1"
);
$stmtOwn->bind_param("ii", $order_id, $user_id);
$stmtOwn->execute();
$order = $stmtOwn->get_result()->fetch_assoc();
$stmtOwn->close();

if (!$order) {
    echo json_encode(['success' => false, 'error' => 'Order not found or access denied']);
    exit;
}

if ($order['status'] === 'cancelled') {
    echo json_encode(['success' => true, 'message' => 'Order already cancelled']); // Idempotent
    exit;
}

if ($order['on_chain_settled']) {
    echo json_encode(['success' => false, 'error' => 'Order has already been settled on-chain and cannot be cancelled']);
    exit;
}

// ── Begin DB transaction ──────────────────────────────────────────────────────
$conn->begin_transaction();
try {
    // Mark order as cancelled and record the tx hash
    $stmtUpd = $conn->prepare(
        "UPDATE orders 
         SET status = 'cancelled', cancel_tx_hash = ?, updated_at = NOW() 
         WHERE id = ? AND user_id = ?"
    );
    $stmtUpd->bind_param("sii", $cancel_tx_hash, $order_id, $user_id);
    $stmtUpd->execute();
    $stmtUpd->close();

    // Release reserved balance back to available (unreserve the funds)
    if ($order['wallet_id']) {
        $walletId = (int)$order['wallet_id'];
        $remaining = (float)$order['qty'] - (float)$order['filled_qty'];

        // Determine which asset to unreserve based on order side
        $pair = $order['pair'];
        // Parse quote asset from pair name (e.g. XAUUSDT → USDT)
        $quote = str_ends_with($pair, 'USDT') ? 'USDT' : substr($pair, 3);
        $base  = str_ends_with($pair, 'USDT') ? substr($pair, 0, -4) : substr($pair, 0, 3);

        if ($order['side'] === 'BUY') {
            // Buyer had USDT reserved — release it back
            $reservedRelease = $remaining * (float)$order['price'];
            $stmtBal = $conn->prepare(
                "UPDATE balances SET reserved = GREATEST(0, reserved - ?) 
                 WHERE wallet_id = ? AND asset = ?"
            );
            $stmtBal->bind_param("dis", $reservedRelease, $walletId, $quote);
            $stmtBal->execute();
            $stmtBal->close();
        } else {
            // Seller had base asset reserved — release it back
            $stmtBal = $conn->prepare(
                "UPDATE balances SET reserved = GREATEST(0, reserved - ?) 
                 WHERE wallet_id = ? AND asset = ?"
            );
            $stmtBal->bind_param("dis", $remaining, $walletId, $base);
            $stmtBal->execute();
            $stmtBal->close();
        }
    }

    // Update limit_order_escrows record if it exists
    if ($cancel_tx_hash) {
        $stmtEsc = $conn->prepare(
            "UPDATE limit_order_escrows 
             SET status = 'refunded', cancel_tx_hash = ?, updated_at = NOW() 
             WHERE order_id = ?"
        );
        $stmtEsc->bind_param("si", $cancel_tx_hash, $order_id);
        $stmtEsc->execute();
        $stmtEsc->close();
    }

    $conn->commit();

    echo json_encode([
        'success'        => true,
        'order_id'       => $order_id,
        'cancel_tx_hash' => $cancel_tx_hash,
        'message'        => 'Order cancelled and ETH refunded on-chain'
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("[cancel_order_onchain] Error for order #{$order_id}: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
exit;
