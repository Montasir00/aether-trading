<?php
session_start();
if (!isset($_SESSION['id'])) {
    header("Location: ../index.php");
    exit;
}

require_once "../config.php";

$user_id = $_SESSION['id'];

// Fetch orders — include escrow_tx_hash to detect on-chain limit orders
$stmt = $conn->prepare("
    SELECT id, type, coin, amount, price, created_at, source_table, escrow_tx_hash FROM (
        SELECT id, type COLLATE utf8mb4_unicode_ci AS type, coin COLLATE utf8mb4_unicode_ci AS coin, amount, price, created_at, 'transactions' COLLATE utf8mb4_unicode_ci AS source_table, NULL AS escrow_tx_hash
        FROM transactions
        WHERE user_id = ? AND order_type = 'limit' AND status = 'pending'
        UNION ALL
        SELECT o.id, o.side COLLATE utf8mb4_unicode_ci AS type, t.base_asset COLLATE utf8mb4_unicode_ci AS coin, (o.qty - o.filled_qty) AS amount, o.price, o.created_at, 'orders' COLLATE utf8mb4_unicode_ci AS source_table, o.escrow_tx_hash
        FROM orders o JOIN trading_pairs t ON o.pair = t.symbol
        WHERE o.user_id = ? AND o.type = 'limit' AND o.status IN ('open', 'partially_filled')
    ) combined ORDER BY created_at DESC
");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Orders — Aether</title>
  <link rel="icon" type="image/svg+xml" href="../favicon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../global.css">
  <link rel="stylesheet" href="my_orders.css">
</head>
<body>
<div class="g-page">

<?php include '../navbar.php'; ?>


<main>
  <div class="orders-wrap">
    <div class="g-double-bezel">
      <div class="g-double-bezel-inner">

       <span class="g-eyebrow" style="margin-bottom: 2px;">Limit Orders</span>
       <h2 class="orders-page-title" style="display:flex; align-items:center; justify-content:center; gap:var(--space-sm);">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
        My Pending Limit Orders
      </h2>

      <?php if (isset($_SESSION['error'])): ?>
        <div style="background: rgba(239,68,68,0.1); color: var(--red, #ef4444); border: 1px solid rgba(239,68,68,0.3); padding: var(--space-sm); border-radius: var(--radius-sm); font-size: 0.85rem; margin-bottom: var(--space-md); text-align: center;">
          <?= htmlspecialchars($_SESSION['error']) ?>
          <?php unset($_SESSION['error']); ?>
        </div>
      <?php endif; ?>

      <?php if (isset($_SESSION['flash'])): ?>
        <div style="background: rgba(16,185,129,0.1); color: var(--green, #10b981); border: 1px solid rgba(16,185,129,0.3); padding: var(--space-sm); border-radius: var(--radius-sm); font-size: 0.85rem; margin-bottom: var(--space-md); text-align: center;">
          <?= htmlspecialchars($_SESSION['flash']) ?>
          <?php unset($_SESSION['flash']); ?>
        </div>
      <?php endif; ?>


      <div class="table-scroll-wrapper">
      <table class="g-table">
        <thead>
          <tr>
            <th>Type</th>
            <th>Coin</th>
            <th>Amount</th>
            <th>Price</th>
            <th>Total (USDT)</th>
            <th>Created</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr>
                <td>
                  <span class="g-badge <?= strtoupper($row['type']) === 'BUY' ? 'g-badge-green' : 'g-badge-red' ?>">
                    <?= htmlspecialchars($row['type']) ?>
                  </span>
                </td>
                <td><strong><?= htmlspecialchars($row['coin']) ?></strong></td>
                <td class="g-numeric"><?= $row['amount'] ?></td>
                <td class="g-numeric">$<?= number_format((float)$row['price'], 2) ?></td>
                <td class="g-numeric">$<?= number_format($row['price'] * $row['amount'], 2) ?></td>
                <td class="orders-date-cell g-numeric"><?= $row['created_at'] ?></td>
                <td>
<?php if (!empty($row['escrow_tx_hash'])): ?>
                  <!-- ON-CHAIN limit order — cancel via MetaMask to get ETH refund automatically -->
                  <button
                    type="button"
                    class="cancel-btn"
                    style="cursor:pointer;"
                    onclick="handleOnChainCancel(<?= (int)$row['id'] ?>)"
                    title="Refunds your escrowed ETH via MetaMask">
                    🔗 Cancel & Refund ETH
                  </button>
                <?php else: ?>
                  <!-- Off-chain (sandbox) limit order — standard form cancel -->
                  <form method="POST" action="cancel_order.php" onsubmit="return confirm('Cancel this order?');">
                    <input type="hidden" name="order_id" value="<?= $row['id'] ?>">
                    <input type="hidden" name="source_table" value="<?= htmlspecialchars($row['source_table']) ?>">
                    <button type="submit" class="cancel-btn">Cancel</button>
                  </form>
                <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="7" class="orders-empty">No pending limit orders</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
      </div>

      </div>
    </div>
  </div>
</main>

<footer class="g-footer">
  <p>&copy; 2026 Aether — Engineered for High Performance</p>
</footer>

</div>

<!-- Load Web3 + AetherTrade contract config for on-chain cancel -->
<script src="https://cdn.jsdelivr.net/npm/web3@4.0.0/dist/web3.min.js"></script>
<?php
// Load contract ABI + address from Truffle artifact (same source as buy_sell_form.php)
$contractABI = [];
$abiPath = __DIR__ . '/../blockchain/build/contracts/AetherTrade.json';
if (file_exists($abiPath)) {
    $truffleArtifact = json_decode(file_get_contents($abiPath), true);
    $contractABI     = $truffleArtifact['abi'] ?? [];
}
?>
<script>
window.AETHER_BLOCKCHAIN = {
    contractAddress: "<?= defined('CONTRACT_ADDRESS') ? htmlspecialchars(CONTRACT_ADDRESS) : '' ?>",
    contractABI:     <?= json_encode($contractABI) ?>
};
</script>

<script src="../blockchain/web3_trade.js"></script>
<script>
/**
 * Called when the user clicks '🔗 Cancel & Refund ETH' on an on-chain limit order.
 * Flow:
 *   1. Connect MetaMask if not already connected
 *   2. Call AetherTrade.sol.cancelLimitOrder(orderId) — MetaMask confirmation popup
 *   3. Smart contract automatically refunds escrowed ETH to buyer's wallet
 *   4. POST cancel_order_onchain.php to update MySQL order status
 */
async function handleOnChainCancel(orderId) {
    if (!confirm('This will cancel your limit order and refund your escrowed ETH on-chain via MetaMask. Confirm?')) {
        return;
    }

    // Ensure MetaMask is connected
    if (!window.ethereum) {
        alert('MetaMask is not installed.');
        return;
    }

    try {
        if (!connectedAccount) {
            await connectMetaMask();
        }

        // Trigger on-chain cancel — MetaMask popup appears, contract refunds ETH
        const cancelTxHash = await cancelOnChainLimitOrder(orderId);

        // Notify backend to mark order as cancelled in MySQL
        const payload = new URLSearchParams({
            order_id:        orderId,
            cancel_tx_hash:  cancelTxHash
        });

        const resp = await fetch('../blockchain/cancel_order_onchain.php', {
            method:      'POST',
            credentials: 'same-origin',
            headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:        payload.toString()
        });

        const result = await resp.json().catch(() => ({ success: false, error: 'Invalid server response' }));

        if (result.success) {
            alert(`✅ Order cancelled and ETH refunded on-chain!\nTxHash: ${cancelTxHash}`);
            location.reload();
        } else {
            alert('Backend error: ' + (result.error || 'Unknown'));
        }
    } catch (err) {
        if (err.code === 4001) {
            alert('Cancellation rejected by user.');
        } else {
            alert('Cancel failed: ' + err.message);
        }
    }
}
</script>
<script src="../global.js?v=<?php echo filemtime('../global.js'); ?>" defer></script>
</body>
</html>
