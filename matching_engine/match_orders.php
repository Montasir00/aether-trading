<?php
session_start();
require '../config.php';

// Fetch pending buy and sell limit orders from transactions table
$buy_orders_result  = $conn->query("SELECT * FROM transactions WHERE UPPER(type)='BUY'  AND status='pending' AND order_type='limit' ORDER BY price DESC, created_at ASC");
$sell_orders_result = $conn->query("SELECT * FROM transactions WHERE UPPER(type)='SELL' AND status='pending' AND order_type='limit' ORDER BY price ASC,  created_at ASC");

if (!$buy_orders_result || !$sell_orders_result) {
    die("Failed to fetch orders");
}

$buy_orders = [];
while ($row = $buy_orders_result->fetch_assoc()) $buy_orders[] = $row;
$sell_orders = [];
while ($row = $sell_orders_result->fetch_assoc()) $sell_orders[] = $row;

// Prepared statements for balance updates — XAU (Gold)
$updateBuyerXAU  = $conn->prepare("UPDATE users SET balance = balance + ?, xau_balance = xau_balance + ? WHERE id = ?");
$updateSellerXAU = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");

// Prepared statements for balance updates — XAG (Silver)
$updateBuyerXAG  = $conn->prepare("UPDATE users SET balance = balance + ?, xag_balance = xag_balance + ? WHERE id = ?");
$updateSellerXAG = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");

$updateOrderAmount = $conn->prepare("UPDATE transactions SET amount = amount - ?, total = total - ? WHERE id = ?");
$fulfillOrder      = $conn->prepare("UPDATE transactions SET status='completed' WHERE id = ?");

foreach ($buy_orders as &$buy) {
    if ((float)$buy['amount'] <= 0) continue;

    foreach ($sell_orders as &$sell) {
        if ((float)$sell['amount'] <= 0) continue;
        if (strtoupper($buy['coin']) !== strtoupper($sell['coin'])) continue;

        if ((float)$buy['price'] >= (float)$sell['price']) {
            $conn->begin_transaction();
            try {
                $trade_amount    = min((float)$buy['amount'], (float)$sell['amount']);
                $execution_price = (float)$sell['price'];
                $coin            = strtoupper($buy['coin']);
                $buyer_id        = (int)$buy['user_id'];
                $seller_id       = (int)$sell['user_id'];

                $buyer_refund_usdt = $trade_amount * ((float)$buy['price'] - $execution_price);
                $seller_get_usdt   = $trade_amount * $execution_price;

                if ($coin === 'XAG') {
                    $updateBuyer  = $updateBuyerXAG;
                    $updateSeller = $updateSellerXAG;
                } else {
                    $updateBuyer  = $updateBuyerXAU;
                    $updateSeller = $updateSellerXAU;
                }

                // Buyer gets commodity and any difference refunded
                $updateBuyer->bind_param("ddi", $buyer_refund_usdt, $trade_amount, $buyer_id);
                $updateBuyer->execute();

                // Seller gets USDT
                $updateSeller->bind_param("di", $seller_get_usdt, $seller_id);
                $updateSeller->execute();

                // Update remaining amounts and totals
                $buy_id  = (int)$buy['id'];
                $sell_id = (int)$sell['id'];
                
                $buy_reduce_total = $trade_amount * (float)$buy['price'];
                $updateOrderAmount->bind_param("ddi", $trade_amount, $buy_reduce_total, $buy_id);
                $updateOrderAmount->execute();

                $sell_reduce_total = $trade_amount * (float)$sell['price'];
                $updateOrderAmount->bind_param("ddi", $trade_amount, $sell_reduce_total, $sell_id);
                $updateOrderAmount->execute();

                if ($trade_amount >= (float)$buy['amount']) {
                    $fulfillOrder->bind_param("i", $buy_id);
                    $fulfillOrder->execute();
                }
                if ($trade_amount >= (float)$sell['amount']) {
                    $fulfillOrder->bind_param("i", $sell_id);
                    $fulfillOrder->execute();
                }

                // Update in-memory amounts
                $buy['amount']  -= $trade_amount;
                $sell['amount'] -= $trade_amount;

                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
                error_log("Matching failed: " . $e->getMessage());
            }

            if ((float)$buy['amount'] <= 0) break;
        }
    }
}

$updateBuyerXAU->close();
$updateSellerXAU->close();
$updateBuyerXAG->close();
$updateSellerXAG->close();
$updateOrderAmount->close();
$fulfillOrder->close();
