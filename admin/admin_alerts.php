<?php
/**
 * admin_alerts.php — Price Alerts Inspection Panel
 * Filter by email, coin, status. Paginated 50/page.
 */
session_start();
require_once __DIR__ . '/admin_auth.php';

// ── Filters ───────────────────────────────────────────────────────────────────
$filter_email  = trim($_GET['email']  ?? '');
$filter_coin   = trim($_GET['coin']   ?? '');
$filter_status = trim($_GET['status'] ?? '');
$page          = max(1, (int)($_GET['page'] ?? 1));
$per_page      = 50;
$offset        = ($page - 1) * $per_page;

// ── Build query ───────────────────────────────────────────────────────────────
$conditions = [];
$params     = [];
$types      = '';

if ($filter_email !== '') {
    $conditions[] = 'u.email LIKE ?';
    $params[]     = '%' . $filter_email . '%';
    $types       .= 's';
}
if ($filter_coin !== '') {
    $conditions[] = 'a.coin = ?';
    $params[]     = strtoupper($filter_coin);
    $types       .= 's';
}
if ($filter_status !== '') {
    if ($filter_status === 'triggered') {
        $conditions[] = 'a.notified = 1';
    } elseif ($filter_status === 'pending') {
        $conditions[] = 'a.notified = 0';
    }
    // Unknown values are silently ignored
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Count
$countSql = "SELECT COUNT(*) FROM price_alerts a JOIN users u ON a.user_id = u.id $where";
$countStmt = $conn->prepare($countSql);
if ($types) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$total_rows = (int)$countStmt->get_result()->fetch_row()[0];
$countStmt->close();

$total_pages = max(1, (int)ceil($total_rows / $per_page));
$page = min($page, $total_pages);

// Data
$dataSql = "SELECT a.*, u.username, u.email
            FROM price_alerts a
            JOIN users u ON a.user_id = u.id
            $where
            ORDER BY a.created_at DESC
            LIMIT ? OFFSET ?";
$dataParams = array_merge($params, [$per_page, $offset]);
$dataTypes  = $types . 'ii';
$dataStmt = $conn->prepare($dataSql);
$dataStmt->bind_param($dataTypes, ...$dataParams);
$dataStmt->execute();
$rows = $dataStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$dataStmt->close();

function alert_page_url(int $p): string {
    $params = $_GET;
    $params['page'] = $p;
    return 'admin_alerts.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Price Alerts — Aether Admin</title>
<meta name="robots" content="noindex,nofollow">
<link rel="stylesheet" href="../global.css">
<link rel="stylesheet" href="admin.css">
</head>
<body>
<div class="admin-layout">

    <?php require_once __DIR__ . '/admin_navbar.php'; ?>

    <main class="admin-main" id="main-content">

        <!-- Header -->
        <div class="admin-page-header">
            <div>
                <h1 class="admin-page-title">Price Alerts</h1>
                <p class="admin-page-subtitle">All user-configured price alert rules</p>
            </div>
        </div>

        <!-- Filter Bar -->
        <form method="get" action="admin_alerts.php" class="admin-filter-bar">
            <div class="admin-filter-group">
                <label for="fa-email">User Email</label>
                <input type="text" id="fa-email" name="email" class="g-input"
                       placeholder="Filter by email…" value="<?= htmlspecialchars($filter_email) ?>">
            </div>
            <div class="admin-filter-group">
                <label for="fa-coin">Coin</label>
                <select id="fa-coin" name="coin" class="g-select">
                    <option value="">All Coins</option>
                    <option value="XAU" <?= $filter_coin === 'XAU' ? 'selected' : '' ?>>XAU (Gold)</option>
                    <option value="XAG" <?= $filter_coin === 'XAG' ? 'selected' : '' ?>>XAG (Silver)</option>
                </select>
            </div>
            <div class="admin-filter-group">
                <label for="fa-status">Status</label>
                <select id="fa-status" name="status" class="g-select">
                    <option value="">All Statuses</option>
                    <option value="pending"   <?= $filter_status === 'pending'   ? 'selected' : '' ?>>Pending</option>
                    <option value="triggered" <?= $filter_status === 'triggered' ? 'selected' : '' ?>>Triggered</option>
                </select>
            </div>
            <div class="admin-filter-actions">
                <button type="submit" class="g-btn g-btn-primary" id="btn-filter-alerts">Filter</button>
                <a href="admin_alerts.php" class="g-btn g-btn-ghost">Reset</a>
            </div>
        </form>

        <!-- Table -->
        <div class="admin-table-wrapper">
            <div class="admin-table-header">
                <div class="admin-table-title">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    Price Alerts
                    <span class="admin-table-count"><?= number_format($total_rows) ?> total</span>
                </div>
                <span style="font-size:0.78rem;color:var(--text-muted);">Page <?= $page ?> of <?= $total_pages ?></span>
            </div>

            <div class="admin-table-scroll">
                <table class="g-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Coin</th>
                            <th>Target Price (USDT)</th>
                            <th>Direction</th>
                            <th>Status</th>
                            <th>Send Attempts</th>
                            <th>Last Attempt</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="9">
                            <div class="admin-empty">
                                <div class="admin-empty-icon">🔔</div>
                                <p>No alerts match the current filters.</p>
                            </div>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $al): ?>
                        <tr>
                            <td class="td-mono">#<?= $al['id'] ?></td>
                            <td>
                                <a href="admin_view_user.php?id=<?= $al['user_id'] ?>" style="color:var(--accent);font-size:0.82rem;">
                                    <?= htmlspecialchars($al['username']) ?>
                                </a>
                                <div class="td-email"><?= htmlspecialchars($al['email']) ?></div>
                            </td>
                            <td><strong><?= htmlspecialchars($al['coin']) ?></strong></td>
                            <td class="td-mono"><?= number_format((float)$al['target_price'], 2) ?></td>
                            <td style="font-size:0.82rem;">
                                <?= htmlspecialchars($al['operator']) === '>=' ? '↑ Above or Equal' : '↓ Below or Equal' ?>
                            </td>
                            <td>
                                <?= $al['notified']
                                    ? '<span class="badge-triggered g-badge">Triggered</span>'
                                    : '<span class="badge-pending g-badge">Pending</span>' ?>
                            </td>
                            <td class="td-mono"><?= (int)$al['send_attempts'] ?></td>
                            <td style="font-size:0.78rem;color:var(--text-muted);">
                                <?= $al['last_attempt_at'] ? date('Y-m-d H:i', strtotime($al['last_attempt_at'])) : '—' ?>
                            </td>
                            <td style="font-size:0.78rem;color:var(--text-muted);"><?= date('Y-m-d H:i', strtotime($al['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="admin-pagination">
                <span>Showing <?= number_format(count($rows)) ?> of <?= number_format($total_rows) ?> results</span>
                <div class="admin-pagination-links">
                    <?php if ($page > 1): ?>
                        <a href="<?= alert_page_url(1) ?>">&laquo;</a>
                        <a href="<?= alert_page_url($page - 1) ?>">&lsaquo;</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <?php if ($i === $page): ?>
                            <span class="current"><?= $i ?></span>
                        <?php else: ?>
                            <a href="<?= alert_page_url($i) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="<?= alert_page_url($page + 1) ?>">&rsaquo;</a>
                        <a href="<?= alert_page_url($total_pages) ?>">&raquo;</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </main>
</div>
</body>
</html>
