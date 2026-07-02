<?php
session_start();
require_once "../config.php";

if (!isset($_SESSION['id']) || !isset($_POST['order_id'])) {
    header("Location: ../dashboard.php");
    exit;
}

$user_id = $_SESSION['id'];
$order_id = (int)$_POST['order_id'];
$source_table = $_POST['source_table'] ?? 'transactions';

if ($source_table === 'orders') {
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ? FOR UPDATE');
        $stmt->bind_param('ii', $order_id, $user_id);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($order && in_array($order['status'], ['open','partially_filled'])) {
            // Guard: If it is an on-chain escrowed order, reject off-chain cancellation
            if (!empty($order['escrow_tx_hash'])) {
                $conn->rollback();
                $_SESSION['error'] = "This order is escrowed on-chain and must be cancelled via MetaMask.";
                header("Location: my_orders.php");
                exit;
            }

            // release reserved amounts

            $walletStmt = $conn->prepare('SELECT id FROM wallets WHERE user_id = ? LIMIT 1');
            $walletStmt->bind_param('i', $user_id);
            $walletStmt->execute();
            $wid = $walletStmt->get_result()->fetch_assoc();
            $walletStmt->close();
            $walletId = $wid['id'] ?? null;

            if ($walletId) {
                $base = 'XAU';
                $quote = 'USDT';
                $stmtPair = $conn->prepare("SELECT base_asset, quote_asset FROM trading_pairs WHERE symbol = ? LIMIT 1");
                $stmtPair->bind_param("s", $order['pair']);
                $stmtPair->execute();
                $pairRow = $stmtPair->get_result()->fetch_assoc();
                $stmtPair->close();
                if ($pairRow) {
                    $base = $pairRow['base_asset'];
                    $quote = $pairRow['quote_asset'];
                } else {
                    // Fallback: strip USDT suffix to get base asset.
                    // Do NOT use substr($pair, 0, 3) — it breaks stock tickers like AAPL.
                    if (str_ends_with($order['pair'], 'USDT')) {
                        $base  = substr($order['pair'], 0, -4);
                        $quote = 'USDT';
                    } else {
                        $base  = substr($order['pair'], 0, 3);
                        $quote = substr($order['pair'], 3);
                    }
                }
                $remaining = (float)$order['qty'] - (float)$order['filled_qty'];

                if ($order['side'] === 'BUY') {
                    $refund = $remaining * (float)$order['price'];
                    $stmt = $conn->prepare('UPDATE balances SET reserved = reserved - ? WHERE wallet_id = ? AND asset = ?');
                    $stmt->bind_param('dis', $refund, $walletId, $quote);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    $stmt = $conn->prepare('UPDATE balances SET reserved = reserved - ? WHERE wallet_id = ? AND asset = ?');
                    $stmt->bind_param('dis', $remaining, $walletId, $base);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            $stmt = $conn->prepare("UPDATE orders SET status = 'canceled', updated_at = NOW() WHERE id = ?");
            $stmt->bind_param('i', $order_id);
            $stmt->execute();
            $stmt->close();
        }
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
    }
} else {
    $conn->begin_transaction();
    try {
        // Fetch the order with FOR UPDATE to prevent race conditions
        $stmt = $conn->prepare("SELECT type, coin, amount, price, total, status FROM transactions WHERE id = ? AND user_id = ? FOR UPDATE");
        $stmt->bind_param("ii", $order_id, $user_id);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$order || $order['status'] !== 'pending') {
            $conn->rollback();
            header("Location: my_orders.php");
            exit;
        }

        // Mark as cancelled
        $stmt = $conn->prepare("UPDATE transactions SET status = 'cancelled' WHERE id = ? AND user_id = ? AND status = 'pending'");
        $stmt->bind_param("ii", $order_id, $user_id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected === 0) {
            throw new Exception("Order was already processed or cancelled.");
        }

        // Refund: BUY orders had USDT locked, SELL orders had crypto locked
        $type = strtoupper($order['type']);
        $coin = strtoupper($order['coin']);
        $amount = (float)$order['amount'];
        $total = (float)$order['total'];

        if ($type === 'BUY') {
            // Refund USDT that was reserved
            $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $stmt->bind_param("di", $total, $user_id);
            $stmt->execute();
            $stmt->close();
        } elseif ($type === 'SELL') {
            // Refund the commodity that was reserved
            if ($coin === 'XAU') {
                $stmt = $conn->prepare("UPDATE users SET xau_balance = xau_balance + ? WHERE id = ?");
                $stmt->bind_param("di", $amount, $user_id);
                $stmt->execute();
                $stmt->close();
            } elseif ($coin === 'XAG') {
                $stmt = $conn->prepare("UPDATE users SET xag_balance = xag_balance + ? WHERE id = ?");
                $stmt->bind_param("di", $amount, $user_id);
                $stmt->execute();
                $stmt->close();
            }
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Cancel order failed: " . $e->getMessage());
    }
}

header("Location: my_orders.php");
exit;
