<?php
session_start();
if (!isset($_SESSION['id'])) {
    header("Location: ../index.php");
    exit;
}

require_once "../config.php";

$coin = isset($_GET['coin']) ? strtoupper($_GET['coin']) : 'XAU';
if (!in_array($coin, ['XAU', 'XAG'])) {
    $coin = 'XAU';
}

$buy_orders = $conn->query("
    SELECT price, SUM(amount) as amount, coin FROM (
        SELECT price, amount, coin COLLATE utf8mb4_unicode_ci AS coin FROM transactions WHERE UPPER(type) = 'BUY' AND status = 'pending'
        UNION ALL
        SELECT o.price, (o.qty - o.filled_qty) AS amount, t.base_asset COLLATE utf8mb4_unicode_ci AS coin FROM orders o JOIN trading_pairs t ON o.pair = t.symbol WHERE o.side = 'BUY' AND o.status IN ('open','partially_filled')
    ) combined 
    WHERE coin = '$coin'
    GROUP BY price, coin 
    ORDER BY price DESC LIMIT 15
");
$sell_orders = $conn->query("
    SELECT price, SUM(amount) as amount, coin FROM (
        SELECT price, amount, coin COLLATE utf8mb4_unicode_ci AS coin FROM transactions WHERE UPPER(type) = 'SELL' AND status = 'pending'
        UNION ALL
        SELECT o.price, (o.qty - o.filled_qty) AS amount, t.base_asset COLLATE utf8mb4_unicode_ci AS coin FROM orders o JOIN trading_pairs t ON o.pair = t.symbol WHERE o.side = 'SELL' AND o.status IN ('open','partially_filled')
    ) combined 
    WHERE coin = '$coin'
    GROUP BY price, coin 
    ORDER BY price ASC LIMIT 15
");

$sells = [];
$max_sell_amt = 0.0001;
if ($sell_orders) {
    while ($row = $sell_orders->fetch_assoc()) {
        $sells[] = $row;
        if ($row['amount'] > $max_sell_amt) {
            $max_sell_amt = (float)$row['amount'];
        }
    }
}

$buys = [];
$max_buy_amt = 0.0001;
if ($buy_orders) {
    while ($row = $buy_orders->fetch_assoc()) {
        $buys[] = $row;
        if ($row['amount'] > $max_buy_amt) {
            $max_buy_amt = (float)$row['amount'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order Book — Aether</title>
  <link rel="icon" type="image/svg+xml" href="../favicon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../global.css" />
  <link rel="stylesheet" href="order_book.css" />
</head>
<body>
<div class="g-page">

<?php include '../navbar.php'; ?>


<main>
  <div class="ob-wrap">
    <div class="g-double-bezel">
      <div class="g-double-bezel-inner">

      <h2 class="ob-page-title" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-sm);">
        <div style="display: flex; flex-direction: column; align-items: flex-start;">
          <span class="g-eyebrow" style="margin-bottom: 2px;">Commodity Depth</span>
          <span style="display: inline-flex; align-items: center; gap: var(--space-sm); font-size: 1.8rem; font-weight: 800; letter-spacing: -0.02em;">
            <svg class="ob-page-icon" aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
              <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
              <line x1="9" y1="6" x2="15" y2="6"></line>
              <line x1="9" y1="10" x2="15" y2="10"></line>
              <line x1="9" y1="14" x2="15" y2="14"></line>
            </svg>
            Order Book
          </span>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="?coin=XAU" class="g-btn-pill <?= $coin === 'XAU' ? 'g-btn-pill-primary' : 'g-btn-outline' ?>" style="padding: 6px 16px; font-size: 0.8rem; border-radius: 9999px;">XAU</a>
            <a href="?coin=XAG" class="g-btn-pill <?= $coin === 'XAG' ? 'g-btn-pill-primary' : 'g-btn-outline' ?>" style="padding: 6px 16px; font-size: 0.8rem; border-radius: 9999px;">XAG</a>
        </div>
      </h2>

      <div class="ob-columns">

        <!-- Sell Orders -->
        <div>
          <div class="ob-section-title sell"><span class="indicator-dot sell"></span> Sell Orders (Asks)</div>
          <?php if (empty($sells)): ?>
            <div class="ob-empty">No sell orders</div>
          <?php else: ?>
          <div class="table-scroll-wrapper">
          <table class="ob-table">
            <thead><tr><th>Price</th><th>Amount</th><th>Total</th></tr></thead>
            <tbody>
              <?php foreach ($sells as $order): 
                $pct = min(100, max(2, round(($order['amount'] / $max_sell_amt) * 100)));
              ?>
                <tr style="background: linear-gradient(270deg, var(--red-bg) <?= $pct ?>%, transparent <?= $pct ?>%);">
                  <td class="ob-price-sell">$<?= number_format((float)$order['price'], 2) ?></td>
                  <td class="g-numeric"><?= number_format((float)$order['amount'], 4) ?> <span class="ob-coin-label"><?= htmlspecialchars($order['coin']) ?></span></td>
                  <td class="g-numeric">$<?= number_format($order['price'] * $order['amount'], 2) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          </div>
          <?php endif; ?>
        </div>

        <!-- Buy Orders -->
        <div>
          <div class="ob-section-title buy"><span class="indicator-dot buy"></span> Buy Orders (Bids)</div>
          <?php if (empty($buys)): ?>
            <div class="ob-empty">No buy orders</div>
          <?php else: ?>
          <div class="table-scroll-wrapper">
          <table class="ob-table">
            <thead><tr><th>Price</th><th>Amount</th><th>Total</th></tr></thead>
            <tbody>
              <?php foreach ($buys as $order): 
                $pct = min(100, max(2, round(($order['amount'] / $max_buy_amt) * 100)));
              ?>
                <tr style="background: linear-gradient(270deg, var(--green-bg) <?= $pct ?>%, transparent <?= $pct ?>%);">
                  <td class="ob-price-buy">$<?= number_format((float)$order['price'], 2) ?></td>
                  <td class="g-numeric"><?= number_format((float)$order['amount'], 4) ?> <span class="ob-coin-label"><?= htmlspecialchars($order['coin']) ?></span></td>
                  <td class="g-numeric">$<?= number_format($order['price'] * $order['amount'], 2) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          </div>
          <?php endif; ?>
        </div>

      </div><!-- /ob-columns -->
      </div>
    </div>
  </div>
</main>

<footer class="g-footer">
  <p>&copy; 2026 Aether — Engineered for High Performance</p>
</footer>

</div>
<script src="../global.js?v=<?php echo filemtime('../global.js'); ?>" defer></script>
</body>
</html>
