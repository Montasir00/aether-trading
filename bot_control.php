<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['id'])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $newStatus = ($_POST['bot_status'] ?? '') === "on" ? 1 : 0;
    $stmt = $conn->prepare("UPDATE bot_control SET is_active = ? WHERE id = 1");
    $stmt->bind_param("i", $newStatus);
    $stmt->execute();
    $stmt->close();
    $_SESSION['flash'] = "Bot status updated to " . ($newStatus ? "ON" : "OFF") . ".";
    header("Location: bot_control.php");
    exit;
}

$res = $conn->query("SELECT is_active FROM bot_control WHERE id = 1");
$row = $res ? $res->fetch_assoc() : null;
$currentStatus = ($row && $row['is_active']) ? "on" : "off";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bot Control — Aether</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="global.css">
</head>
<body>
<div class="g-page">

<?php include 'navbar.php'; ?>

<main>
  <div style="max-width:520px; margin:0 auto; padding: var(--space-xl) var(--space-lg);">

    <span class="g-eyebrow" style="text-align:left; margin-bottom: 2px;">Algorithm Node</span>
    <h2 style="font-weight:800; margin-bottom:var(--space-lg); display:flex; align-items:center; gap:var(--space-sm);">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
      SMA Bot Control Panel
    </h2>

    <?php if (isset($_SESSION['flash'])): ?>
        <div class="g-alert g-alert-success">
            <?= htmlspecialchars($_SESSION['flash']) ?>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <div class="g-double-bezel">
      <div class="g-double-bezel-inner">
      <!-- Status indicator -->
      <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:var(--space-lg);">
        <span style="font-size:0.85rem; font-weight:600; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px;">Current Status</span>
        <span class="g-badge <?= $currentStatus === 'on' ? 'g-badge-green' : 'g-badge-red' ?>">
          <?= $currentStatus === 'on' ? 'Active' : 'Inactive' ?>
        </span>
      </div>

      <p style="font-size:0.875rem; color:var(--text-secondary); margin-bottom:var(--space-lg); line-height:1.6;">
        The SMA Strategy Bot automatically executes <strong>BUY</strong> and <strong>SELL</strong> orders
        based on 50-period vs 200-period moving average crossovers. Toggle it on or off below.
      </p>

      <form method="POST" style="display:flex; flex-direction:column; gap:var(--space-md);">
        <div>
          <label for="bot_status" class="g-label">Set Bot Status</label>
          <select name="bot_status" id="bot_status" class="g-select">
            <option value="on"  <?= $currentStatus === "on"  ? "selected" : "" ?>>ON — Bot is active and trading</option>
            <option value="off" <?= $currentStatus === "off" ? "selected" : "" ?>>OFF — Bot is paused</option>
          </select>
        </div>
        <button type="submit" class="g-btn-pill g-btn-pill-primary" style="width:100%;">
            Update Bot Status
            <span class="g-btn-icon-circle">
                <svg aria-hidden="true" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
            </span>
        </button>
      </form>
      </div>
    </div>

    <div style="margin-top:var(--space-lg);">
      <a href="dashboard.php" class="g-btn g-btn-ghost" style="gap:var(--space-xs);">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Dashboard
      </a>
    </div>

  </div>
</main>

<footer class="g-footer">
  <p>&copy; 2026 Aether — Engineered for High Performance</p>
</footer>

</div>
<script src="global.js?v=<?php echo filemtime('global.js'); ?>" defer></script>
</body>
</html>
