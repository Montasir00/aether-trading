<?php
/**
 * admin_view_user.php — View a single user's full profile, transactions & alerts.
 */
session_start();
require_once __DIR__ . '/admin_auth.php';

$uid = (int)($_GET['id'] ?? 0);
if ($uid <= 0) { header('Location: admin.php'); exit; }

// ── Load user ──────────────────────────────────────────────────────────────
$stmt = $conn->prepare('SELECT id, username, email, balance, xau_balance, xag_balance, is_verified, is_admin, bot_position, created_at FROM users WHERE id = ?');
$stmt->bind_param('i', $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) { header('Location: admin.php'); exit; }

// ── Recent transactions (last 20) ──────────────────────────────────────────
$txStmt = $conn->prepare('SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 20');
$txStmt->bind_param('i', $uid);
$txStmt->execute();
$transactions = $txStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$txStmt->close();

// ── Price alerts ───────────────────────────────────────────────────────────
$alStmt = $conn->prepare('SELECT * FROM price_alerts WHERE user_id = ? ORDER BY created_at DESC');
$alStmt->bind_param('i', $uid);
$alStmt->execute();
$alerts = $alStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$alStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User #<?= $uid ?> — Aether Admin</title>
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
                <h1 class="admin-page-title"><?= htmlspecialchars($user['username']) ?></h1>
                <p class="admin-page-subtitle">User #<?= $uid ?> — Full profile &amp; history</p>
            </div>
            <div class="admin-page-actions">
                <a href="admin_edit_user.php?id=<?= $uid ?>" class="g-btn g-btn-outline">Edit User</a>
                <a href="admin.php" class="btn-action btn-view">← Back</a>
            </div>
        </div>

        <!-- Profile Detail Cards -->
        <div class="admin-detail-grid">
            <div class="admin-detail-item">
                <div class="admin-detail-label">Email</div>
                <div class="admin-detail-value"><?= htmlspecialchars($user['email']) ?></div>
            </div>
            <div class="admin-detail-item">
                <div class="admin-detail-label">USDT Balance</div>
                <div class="admin-detail-value mono"><?= number_format((float)$user['balance'], 2) ?> USDT</div>
            </div>
            <div class="admin-detail-item">
                <div class="admin-detail-label">XAU Balance (Gold)</div>
                <div class="admin-detail-value mono"><?= number_format((float)$user['xau_balance'], 6) ?> oz</div>
            </div>
            <div class="admin-detail-item">
                <div class="admin-detail-label">XAG Balance (Silver)</div>
                <div class="admin-detail-value mono"><?= number_format((float)$user['xag_balance'], 4) ?> oz</div>
            </div>
            <div class="admin-detail-item">
                <div class="admin-detail-label">Email Verified</div>
                <div class="admin-detail-value">
                    <?= $user['is_verified'] ? '<span class="badge-completed g-badge">Verified</span>' : '<span class="badge-pending g-badge">Pending</span>' ?>
                </div>
            </div>
            <div class="admin-detail-item">
                <div class="admin-detail-label">Admin Role</div>
                <div class="admin-detail-value">
                    <?= $user['is_admin'] ? '<span class="badge-completed g-badge">Administrator</span>' : '<span style="color:var(--text-muted)">Regular User</span>' ?>
                </div>
            </div>
            <div class="admin-detail-item">
                <div class="admin-detail-label">Bot Position</div>
                <div class="admin-detail-value"><?= htmlspecialchars($user['bot_position']) ?></div>
            </div>
            <div class="admin-detail-item">
                <div class="admin-detail-label">Member Since</div>
                <div class="admin-detail-value"><?= date('d M Y, H:i', strtotime($user['created_at'])) ?></div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="admin-table-wrapper" style="margin-bottom:var(--space-lg);">
            <div class="admin-table-header">
                <div class="admin-table-title">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    Recent Transactions
                    <span class="admin-table-count"><?= count($transactions) ?></span>
                </div>
                <a href="admin_transactions.php?email=<?= urlencode($user['email']) ?>" class="btn-action btn-view">View all</a>
            </div>
            <div class="admin-table-scroll">
                <table class="g-table">
                    <thead>
                        <tr>
                            <th>ID</th>
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
                    <?php if (empty($transactions)): ?>
                        <tr><td colspan="9"><div class="admin-empty"><div class="admin-empty-icon">📊</div><p>No transactions yet.</p></div></td></tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $tx): ?>
                        <tr>
                            <td class="td-mono">#<?= $tx['id'] ?></td>
                            <td><span class="badge-<?= strtolower($tx['type']) ?> g-badge"><?= htmlspecialchars($tx['type']) ?></span></td>
                            <td><strong><?= htmlspecialchars($tx['coin']) ?></strong></td>
                            <td class="td-mono"><?= number_format((float)$tx['amount'], 6) ?></td>
                            <td class="td-mono"><?= number_format((float)$tx['price'], 2) ?></td>
                            <td class="td-mono"><?= number_format((float)$tx['total'], 2) ?></td>
                            <td style="font-size:0.78rem;text-transform:capitalize;"><?= htmlspecialchars($tx['order_type'] ?? 'market') ?></td>
                            <td>
                                <?php
                                $st = strtolower($tx['status'] ?? 'completed');
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
        </div>

        <!-- Price Alerts -->
        <div class="admin-table-wrapper">
            <div class="admin-table-header">
                <div class="admin-table-title">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    Price Alerts
                    <span class="admin-table-count"><?= count($alerts) ?></span>
                </div>
                <a href="admin_alerts.php?email=<?= urlencode($user['email']) ?>" class="btn-action btn-view">View all</a>
            </div>
            <div class="admin-table-scroll">
                <table class="g-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Coin</th>
                            <th>Target Price</th>
                            <th>Direction</th>
                            <th>Status</th>
                            <th>Send Attempts</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($alerts)): ?>
                        <tr><td colspan="7"><div class="admin-empty"><div class="admin-empty-icon">🔔</div><p>No alerts configured.</p></div></td></tr>
                    <?php else: ?>
                        <?php foreach ($alerts as $al): ?>
                        <tr>
                            <td class="td-mono">#<?= $al['id'] ?></td>
                            <td><strong><?= htmlspecialchars($al['coin']) ?></strong></td>
                            <td class="td-mono"><?= number_format((float)$al['target_price'], 2) ?> USDT</td>
                            <td style="font-size:0.82rem;"><?= htmlspecialchars($al['operator']) === '>=' ? '↑ Above or Equal' : '↓ Below or Equal' ?></td>
                            <td><?= $al['notified'] ? '<span class="badge-triggered g-badge">Triggered</span>' : '<span class="badge-pending g-badge">Pending</span>' ?></td>
                            <td class="td-mono"><?= (int)$al['send_attempts'] ?></td>
                            <td style="font-size:0.78rem;color:var(--text-muted);"><?= date('Y-m-d H:i', strtotime($al['created_at'])) ?></td>
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
