<?php
/**
 * admin_navbar.php — Admin Sidebar Navigation
 * Include inside .admin-layout as the first child.
 */
$adminPage = basename($_SERVER['PHP_SELF']);

function admin_nav_link(string $file, string $label, string $icon, string $current): string {
    $active = ($current === $file) ? ' active' : '';
    return "<a href=\"{$file}\" class=\"admin-nav-link{$active}\">{$icon}<span>{$label}</span></a>";
}

$ic_dashboard   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>';
$ic_users       = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>';
$ic_tx          = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>';
$ic_alerts      = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>';
$ic_reconcile   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>';
$ic_export      = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>';
$ic_back        = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>';
?>
<aside class="admin-sidebar" id="adminSidebar">
    <!-- Brand -->
    <a href="admin.php" class="admin-brand">
        <div class="admin-brand-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                <path d="M2 17l10 5 10-5"/>
                <path d="M2 12l10 5 10-5"/>
            </svg>
        </div>
        <div class="admin-brand-text">
            <span class="admin-brand-name">Aether</span>
            <span class="admin-brand-sub">Admin Suite</span>
        </div>
    </a>

    <!-- Navigation -->
    <nav class="admin-nav">
        <span class="admin-nav-section">Overview</span>
        <?= admin_nav_link('admin.php', 'Dashboard & Users', $ic_dashboard, $adminPage) ?>

        <span class="admin-nav-section">Management</span>
        <?= admin_nav_link('admin_transactions.php', 'Transactions', $ic_tx, $adminPage) ?>
        <?= admin_nav_link('admin_alerts.php', 'Price Alerts', $ic_alerts, $adminPage) ?>

        <span class="admin-nav-section">Tools</span>
        <?= admin_nav_link('admin_trigger_reconciliation.php', 'Reconciliation', $ic_reconcile, $adminPage) ?>
        <?= admin_nav_link('admin_export.php', 'Export CSV', $ic_export, $adminPage) ?>

        <span class="admin-nav-section" style="margin-top:auto;"></span>
        <a href="../dashboard.php" class="admin-nav-link" style="margin-top:auto;">
            <?= $ic_back ?>
            <span>Back to Platform</span>
        </a>
    </nav>

    <!-- Logged-in admin user chip -->
    <div class="admin-sidebar-footer">
        <div class="admin-user-chip">
            <div class="admin-user-avatar">
                <?= strtoupper(substr($ADMIN_USER['username'] ?? 'A', 0, 1)) ?>
            </div>
            <div class="admin-user-info">
                <div class="admin-user-name"><?= htmlspecialchars($ADMIN_USER['username'] ?? '') ?></div>
                <div class="admin-user-role">Administrator</div>
            </div>
        </div>
    </div>
</aside>

<!-- Mobile sidebar toggle -->
<button class="admin-sidebar-toggle" id="adminSidebarToggle" aria-label="Toggle sidebar" aria-expanded="false">☰</button>

<script>
(function() {
    const sidebar = document.getElementById('adminSidebar');
    const btn     = document.getElementById('adminSidebarToggle');
    if (!sidebar || !btn) return;

    btn.addEventListener('click', function() {
        const open = sidebar.classList.toggle('open');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        btn.textContent = open ? '✕' : '☰';
    });

    document.addEventListener('click', function(e) {
        if (!sidebar.contains(e.target) && !btn.contains(e.target)) {
            sidebar.classList.remove('open');
            btn.textContent = '☰';
            btn.setAttribute('aria-expanded', 'false');
        }
    });
})();
</script>
