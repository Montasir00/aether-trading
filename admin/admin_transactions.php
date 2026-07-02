<?php
/**
 * admin_transactions.php — Full Transaction Audit Ledger
 * Filterable by email, type, coin, and status. Paginated 50/page.
 */
session_start();
require_once __DIR__ . '/admin_auth.php';

// ── Filters ──────────────────────────────────────────────────────────────────
$filter_email  = trim($_GET['email']  ?? '');
$filter_type   = trim($_GET['type']   ?? '');
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
if ($filter_type !== '') {
    $conditions[] = 't.type = ?';
    $params[]     = strtoupper($filter_type);
    $types       .= 's';
}
if ($filter_coin !== '') {
    $conditions[] = 't.coin = ?';
    $params[]     = strtoupper($filter_coin);
    $types       .= 's';
}
if ($filter_status !== '') {
    $conditions[] = 't.status = ?';
    $params[]     = $filter_status;
    $types       .= 's';
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Count query
$countSql = "SELECT COUNT(*) FROM transactions t JOIN users u ON t.user_id = u.id $where";
$countStmt = $conn->prepare($countSql);
if ($types) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$total_rows = (int)$countStmt->get_result()->fetch_row()[0];
$countStmt->close();

$total_pages = max(1, (int)ceil($total_rows / $per_page));
$page = min($page, $total_pages);

// Data query
$dataSql = "SELECT t.*, u.username, u.email
            FROM transactions t
            JOIN users u ON t.user_id = u.id
            $where
            ORDER BY t.created_at DESC
            LIMIT ? OFFSET ?";
$dataParams = array_merge($params, [$per_page, $offset]);
$dataTypes  = $types . 'ii';
$dataStmt = $conn->prepare($dataSql);
$dataStmt->bind_param($dataTypes, ...$dataParams);
$dataStmt->execute();
$rows = $dataStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$dataStmt->close();

// Build query string helper for pagination links
function txn_page_url(int $p): string {
    $params = $_GET;
    $params['page'] = $p;
    return 'admin_transactions.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Transactions — Aether Admin</title>
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
                <h1 class="admin-page-title">Transaction Audit</h1>
                <p class="admin-page-subtitle">Every trade executed on the platform</p>
            </div>
            <div class="admin-page-actions">
                <a href="admin_export.php" class="g-btn g-btn-outline">Export CSV</a>
            </div>
        </div>

        <!-- Filter Bar -->
        <form method="get" action="admin_transactions.php" class="admin-filter-bar">
            <div class="admin-filter-group">
                <label for="f-email">User Email</label>
                <input type="text" id="f-email" name="email" class="g-input"
                       placeholder="Filter by email…" value="<?= htmlspecialchars($filter_email) ?>">
            </div>
            <div class="admin-filter-group">
                <label for="f-type">Type</label>
                <select id="f-type" name="type" class="g-select">
                    <option value="">All Types</option>
                    <option value="BUY"  <?= $filter_type === 'BUY'  ? 'selected' : '' ?>>BUY</option>
                    <option value="SELL" <?= $filter_type === 'SELL' ? 'selected' : '' ?>>SELL</option>
                </select>
            </div>
            <div class="admin-filter-group">
                <label for="f-coin">Coin</label>
                <select id="f-coin" name="coin" class="g-select">
                    <option value="">All Coins</option>
                    <option value="XAU" <?= $filter_coin === 'XAU' ? 'selected' : '' ?>>XAU (Gold)</option>
                    <option value="XAG" <?= $filter_coin === 'XAG' ? 'selected' : '' ?>>XAG (Silver)</option>
                </select>
            </div>
            <div class="admin-filter-group">
                <label for="f-status">Status</label>
                <select id="f-status" name="status" class="g-select">
                    <option value="">All Statuses</option>
                    <option value="completed"  <?= $filter_status === 'completed'  ? 'selected' : '' ?>>Completed</option>
                    <option value="pending"    <?= $filter_status === 'pending'    ? 'selected' : '' ?>>Pending</option>
                    <option value="cancelled"  <?= $filter_status === 'cancelled'  ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            <div class="admin-filter-actions">
                <button type="submit" class="g-btn g-btn-primary" id="btn-filter-txn">Filter</button>
                <a href="admin_transactions.php" class="g-btn g-btn-ghost">Reset</a>
            </div>
        </form>

        <!-- Table -->
        <div class="admin-table-wrapper">
            <div class="admin-table-header">
                <div class="admin-table-title">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    Transactions
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
                            <th>Type</th>
                            <th>Coin</th>
                            <th>Amount</th>
                            <th>Price (USDT)</th>
                            <th>Total (USDT)</th>
                            <th>Order Type</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="10">
                            <div class="admin-empty">
                                <div class="admin-empty-icon">📊</div>
                                <p>No transactions match the current filters.</p>
                            </div>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $tx): ?>
                        <tr>
                            <td class="td-mono">#<?= $tx['id'] ?></td>
                            <td>
                                <a href="admin_view_user.php?id=<?= $tx['user_id'] ?>" style="color:var(--accent);font-size:0.82rem;">
                                    <?= htmlspecialchars($tx['username']) ?>
                                </a>
                                <div class="td-email"><?= htmlspecialchars($tx['email']) ?></div>
                            </td>
                            <td><span class="badge-<?= strtolower($tx['type']) ?> g-badge"><?= htmlspecialchars($tx['type']) ?></span></td>
                            <td><strong><?= htmlspecialchars($tx['coin']) ?></strong></td>
                            <td class="td-mono"><?= number_format((float)$tx['amount'], 6) ?></td>
                            <td class="td-mono"><?= number_format((float)$tx['price'], 2) ?></td>
                            <td class="td-mono"><?= number_format((float)$tx['total'], 2) ?></td>
                            <td style="font-size:0.78rem;text-transform:capitalize;"><?= htmlspecialchars($tx['order_type'] ?? 'market') ?></td>
                            <td>
                                <?php
                                $st  = strtolower($tx['status'] ?? 'completed');
                                $cls = $st === 'completed' ? 'badge-completed' : ($st === 'pending' ? 'badge-pending' : 'badge-cancelled');
                                ?>
                                <span class="<?= $cls ?> g-badge"><?= ucfirst($st) ?></span>
                            </td>
                            <td style="font-size:0.78rem;color:var(--text-muted);"><?= date('Y-m-d H:i', strtotime($tx['created_at'])) ?></td>
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
                        <a href="<?= txn_page_url(1) ?>">&laquo;</a>
                        <a href="<?= txn_page_url($page - 1) ?>">&lsaquo;</a>
                    <?php endif; ?>
                    <?php
                    $range = 2;
                    for ($i = max(1, $page - $range); $i <= min($total_pages, $page + $range); $i++):
                    ?>
                        <?php if ($i === $page): ?>
                            <span class="current"><?= $i ?></span>
                        <?php else: ?>
                            <a href="<?= txn_page_url($i) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="<?= txn_page_url($page + 1) ?>">&rsaquo;</a>
                        <a href="<?= txn_page_url($total_pages) ?>">&raquo;</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </main>
</div>
</body>
</html>
