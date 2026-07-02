<?php
$currentPage = basename($_SERVER['PHP_SELF']);

// Calculate base path dynamically to support inclusion from subdirectories
$base_path = '';
$script_name = $_SERVER['SCRIPT_NAME'];
$parts = explode('/', trim($script_name, '/'));
if (count($parts) > 1) {
    $base_path = str_repeat('../', count($parts) - 1);
}

// Check if logged-in user is an admin
$_isAdmin = false;
if (isset($_SESSION['id'])) {
    if (!isset($conn)) {
        require_once __DIR__ . '/config.php';
    }
    $stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['id']);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        if ($res && (int)$res['is_admin'] === 1) {
            $_isAdmin = true;
        }
        $stmt->close();
    }
}
?>
<a href="#main-content" class="skip-link">Skip to main content</a>
<nav class="g-navbar">
    <div class="g-navbar-inner">
        <a href="<?= $base_path ?>dashboard.php" class="g-navbar-brand">
            <div class="brand-icon">
                <svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2L2 7l10 5 10-5-10-5z" />
                    <path d="M2 17l10 5 10-5" />
                    <path d="M2 12l10 5 10-5" />
                </svg>
            </div>
            Aether
        </a>
        <button class="hamburger-btn" aria-label="Toggle Menu" aria-expanded="false">
            <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>
        <div class="g-nav-links">
            <a href="<?= $base_path ?>dashboard.php" class="g-nav-link <?= ($currentPage == 'dashboard.php') ? 'active' : '' ?>">Dashboard</a>
            <a href="<?= $base_path ?>fear_greed.php" class="g-nav-link <?= ($currentPage == 'fear_greed.php') ? 'active' : '' ?>">Sentiment</a>
            <a href="<?= $base_path ?>buy_sell_form.php" class="g-nav-link <?= ($currentPage == 'buy_sell_form.php') ? 'active' : '' ?>">Trade</a>
            <a href="<?= $base_path ?>orders/my_orders.php" class="g-nav-link <?= ($currentPage == 'my_orders.php') ? 'active' : '' ?>">Orders</a>
            <a href="<?= $base_path ?>orders/order_book.php" class="g-nav-link <?= ($currentPage == 'order_book.php') ? 'active' : '' ?>">Order Book</a>
            <a href="<?= $base_path ?>alerts/create_alert.php" class="g-nav-link <?= ($currentPage == 'create_alert.php') ? 'active' : '' ?>">Alerts</a>
            <a href="<?= $base_path ?>sandbox/index.php" class="g-nav-link <?= (strpos($_SERVER['SCRIPT_NAME'], '/sandbox/') !== false) ? 'active' : '' ?>">Sandbox</a>
            <?php if ($_isAdmin): ?>
                <a href="<?= $base_path ?>admin/admin.php" class="g-nav-link admin-btn-highlight" style="color:#d4af37;font-weight:600;">Admin Panel</a>
            <?php endif; ?>
            <button id="theme-toggle" class="g-nav-link theme-toggle-btn" aria-label="Toggle color theme" aria-pressed="false" title="Toggle theme">
                <span class="theme-icon" aria-hidden="true"></span>
                <span class="sr-only">Toggle color theme</span>
            </button>
            <a href="<?= $base_path ?>auth/logout.php" class="g-btn g-btn-danger nav-logout-btn">Logout</a>
        </div>
    </div>
</nav>
