<?php
session_start();
if (!isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}
require_once '../config.php';
$pairsStmt = $conn->query("SELECT base_asset FROM trading_pairs WHERE active = 1 ORDER BY base_asset ASC");
$activeCoins = [];
if ($pairsStmt) {
    while ($pRow = $pairsStmt->fetch_assoc()) {
        $base = $pRow['base_asset'];
        $name = ($base === 'XAU') ? 'Gold' : (($base === 'XAG') ? 'Silver' : $base);
        $activeCoins[] = [
            'base_asset' => $base,
            'name' => $name
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>New Alert — Aether</title>
  <link rel="icon" type="image/svg+xml" href="../favicon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../global.css?v=<?php echo filemtime('../global.css'); ?>" />
  <link rel="stylesheet" href="create_alert.css?v=<?php echo filemtime('create_alert.css'); ?>" />
</head>
<body>
<div class="g-page">

<?php include '../navbar.php'; ?>


<main>
  <div class="alert-wrap">
    <div class="g-double-bezel">
      <div class="g-double-bezel-inner">

      <div class="alert-icon-container">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
      </div>
       <span class="g-eyebrow" style="margin-bottom: 2px;">Trigger Notification</span>
       <h2 class="alert-page-title">Create Price Alert</h2>
      <p class="alert-hint">Get notified when a coin hits your target price</p>

      <?php if (isset($_SESSION['error'])): ?>
          <div class="g-alert g-alert-error">
              <?= htmlspecialchars($_SESSION['error']) ?>
          </div>
          <?php unset($_SESSION['error']); ?>
      <?php endif; ?>

      <form method="POST" action="save_alert.php" class="alert-form" id="alert-form">

        <div>
          <label for="coin" class="g-label">Asset/Commodity</label>
          <div class="select-with-icon-wrapper" style="display: flex; align-items: center; gap: var(--space-sm);">
            <div id="selected-coin-icon" class="selected-asset-icon-preview"></div>
            <select name="coin" id="coin" class="g-select" style="flex: 1;">
              <?php foreach ($activeCoins as $coinInfo): ?>
                <option value="<?= htmlspecialchars($coinInfo['base_asset']) ?>"><?= htmlspecialchars($coinInfo['base_asset']) ?> — <?= htmlspecialchars($coinInfo['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div>
          <label for="target_price" class="g-label">Target Price (USDT / oz)</label>
          <input type="number" step="0.01" min="0.01" name="target_price" id="target_price" required class="g-input" placeholder="e.g. 3300.00">
        </div>

        <button type="submit" class="g-btn-pill g-btn-pill-primary alert-submit-btn" style="width:100%;">
            Set Alert
            <span class="g-btn-icon-circle">
                <svg aria-hidden="true" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
            </span>
        </button>
      </form>

      </div>
    </div>
  </div>
</main>

<footer class="g-footer">
  <p>&copy; 2026 Aether — Engineered for High Performance</p>
</footer>

</div>
<script src="global.js?v=<?php echo filemtime('global.js'); ?>" defer></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const coinSelect = document.getElementById('coin');
        const iconContainer = document.getElementById('selected-coin-icon');
        
        function updateSelectedCoinIcon() {
            if (iconContainer && coinSelect && window.getAssetIcon) {
                iconContainer.innerHTML = window.getAssetIcon(coinSelect.value);
            }
        }
        
        if (coinSelect) {
            coinSelect.addEventListener('change', updateSelectedCoinIcon);
            updateSelectedCoinIcon();
        }
    });

    document.getElementById('alert-form').addEventListener('submit', function() {
        const btn = this.querySelector('.alert-submit-btn');
        btn.disabled = true;
        btn.textContent = 'Saving…';
    });
</script>
</body>
</html>
