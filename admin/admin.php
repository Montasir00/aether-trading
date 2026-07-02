<?php
/**
 * admin.php — Main Admin Dashboard
 * Shows platform metrics and full user management table.
 */
session_start();
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/csrf_helper.php';

$search = trim($_GET['q'] ?? '');

// ── Delete user action ──────────────────────────────────────────────────────
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    csrf_validate_token();
    $del_id = (int)($_POST['user_id'] ?? 0);
    if ($del_id > 0 && $del_id !== (int)$_SESSION['id']) {
        $del = $conn->prepare('DELETE FROM users WHERE id = ?');
        $del->bind_param('i', $del_id);
        $del->execute();
        $del->close();
        $flash = 'success:User #' . $del_id . ' deleted successfully.';
    } else {
        $flash = 'error:Cannot delete your own account.';
    }
}

// ── Platform metrics ────────────────────────────────────────────────────────
$total_users = (int)$conn->query('SELECT COUNT(*) FROM users')->fetch_row()[0];
$total_txns  = (int)$conn->query('SELECT COUNT(*) FROM transactions')->fetch_row()[0];
$total_alerts_triggered = (int)$conn->query('SELECT COUNT(*) FROM price_alerts WHERE notified = 1')->fetch_row()[0];

// ── User query ──────────────────────────────────────────────────────────────
$params  = [];
$types   = '';
$where   = '';
if ($search !== '') {
    $like = '%' . $search . '%';
    $where = ' WHERE (username LIKE ? OR email LIKE ?)';
    $params = [$like, $like];
    $types  = 'ss';
}

$userStmt = $conn->prepare("SELECT id, username, email, balance, xau_balance, xag_balance, is_verified, is_admin, created_at FROM users{$where} ORDER BY id ASC");
if ($types) $userStmt->bind_param($types, ...$params);
$userStmt->execute();
$users = $userStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$userStmt->close();

$csrf_token = csrf_get_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard — Aether</title>
<meta name="description" content="Aether Trading Platform Administration Dashboard">
<meta name="robots" content="noindex,nofollow">
<link rel="stylesheet" href="../global.css">
<link rel="stylesheet" href="admin.css">
</head>
<body>
<div class="admin-layout">

    <?php require_once __DIR__ . '/admin_navbar.php'; ?>

    <main class="admin-main" id="main-content">

        <!-- Page Header -->
        <div class="admin-page-header">
            <div>
                <h1 class="admin-page-title">Dashboard</h1>
                <p class="admin-page-subtitle">Platform overview and user management</p>
            </div>
        </div>

        <?php if ($flash): ?>
            <?php [$type, $msg] = explode(':', $flash, 2); ?>
            <div class="admin-flash admin-flash-<?= $type === 'success' ? 'success' : 'error' ?>">
                <?= $type === 'success' ? '✓' : '✕' ?> <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <!-- Stat Cards -->
        <div class="admin-stats-grid">
            <div class="admin-stat-card">
                <div class="admin-stat-label">Total Users</div>
                <div class="admin-stat-value"><?= number_format($total_users) ?></div>
                <div class="admin-stat-sub">Registered accounts</div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-label">Total Transactions</div>
                <div class="admin-stat-value"><?= number_format($total_txns) ?></div>
                <div class="admin-stat-sub">All-time trades</div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-label">Triggered Alerts</div>
                <div class="admin-stat-value"><?= number_format($total_alerts_triggered) ?></div>
                <div class="admin-stat-sub">Price alerts fired</div>
            </div>
        </div>

        <!-- User Table -->
        <div class="admin-table-wrapper">
            <div class="admin-table-header">
                <div class="admin-table-title">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    Users
                    <span class="admin-table-count"><?= count($users) ?></span>
                </div>
                <form method="get" action="admin.php" style="display:flex;gap:8px;align-items:center;">
                    <input type="text" name="q" id="search-users" class="g-input" placeholder="Search username or email…"
                           value="<?= htmlspecialchars($search) ?>" style="width:240px;padding:8px 14px;font-size:0.82rem;">
                    <button type="submit" class="btn-action btn-view">Search</button>
                    <?php if ($search): ?>
                        <a href="admin.php" class="btn-action btn-view">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="admin-table-scroll">
                <table class="g-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>USDT Balance</th>
                            <th>XAU (oz)</th>
                            <th>XAG (oz)</th>
                            <th>Verified</th>
                            <th>Admin</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="10">
                            <div class="admin-empty">
                                <div class="admin-empty-icon">👤</div>
                                <p>No users found<?= $search ? ' matching "' . htmlspecialchars($search) . '"' : '' ?>.</p>
                            </div>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td class="td-mono">#<?= $u['id'] ?></td>
                            <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                            <td class="td-email"><?= htmlspecialchars($u['email']) ?></td>
                            <td class="td-mono"><?= number_format((float)$u['balance'], 2) ?></td>
                            <td class="td-mono"><?= number_format((float)$u['xau_balance'], 6) ?></td>
                            <td class="td-mono"><?= number_format((float)$u['xag_balance'], 4) ?></td>
                            <td>
                                <?php if ($u['is_verified']): ?>
                                    <span class="badge-completed g-badge">✓ Yes</span>
                                <?php else: ?>
                                    <span class="badge-pending g-badge">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['is_admin']): ?>
                                    <span class="badge-completed g-badge">Admin</span>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);font-size:0.75rem;">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:0.78rem;color:var(--text-muted);"><?= date('Y-m-d', strtotime($u['created_at'])) ?></td>
                            <td>
                                <div class="td-actions">
                                    <a href="admin_view_user.php?id=<?= $u['id'] ?>" class="btn-action btn-view" title="View user details">View</a>
                                    <a href="admin_edit_user.php?id=<?= $u['id'] ?>" class="btn-action btn-edit" title="Edit user">Edit</a>
                                    <?php if ($u['id'] !== (int)$_SESSION['id']): ?>
                                    <form method="post" action="admin.php" style="display:inline;" onsubmit="return confirm('Delete user #<?= $u['id'] ?> (<?= htmlspecialchars(addslashes($u['username'])) ?>)? This cannot be undone.')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                        <?php if ($search): ?><input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
                                        <button type="submit" class="btn-action btn-delete">Delete</button>
                                    </form>
                                    <?php else: ?>
                                        <span style="font-size:0.7rem;color:var(--text-muted);padding:5px 8px;">You</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>
</body>
</html>
