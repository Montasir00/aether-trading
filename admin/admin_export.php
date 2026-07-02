<?php
/**
 * admin_export.php — CSV Data Export
 * Exports users or transactions as downloadable CSV files.
 */
session_start();
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/csrf_helper.php';

// ── Handle CSV download requests ─────────────────────────────────────────────
$export = $_GET['export'] ?? '';

if ($export === 'users') {
    // ── Export Users ──────────────────────────────────────────────────────────
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="aether_users_' . date('Ymd_His') . '.csv"');
    header('Cache-Control: no-store, no-cache');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    // BOM for Excel UTF-8 compatibility
    fputs($out, "\xEF\xBB\xBF");

    // Header row
    fputcsv($out, [
        'ID', 'Username', 'Email',
        'USDT Balance', 'XAU Balance (oz)', 'XAG Balance (oz)',
        'Email Verified', 'Is Admin', 'Bot Position', 'Created At'
    ]);

    $res = $conn->query(
        'SELECT id, username, email, balance, xau_balance, xag_balance,
                is_verified, is_admin, bot_position, created_at
         FROM users
         ORDER BY id ASC'
    );
    while ($row = $res->fetch_assoc()) {
        fputcsv($out, [
            $row['id'],
            $row['username'],
            $row['email'],
            $row['balance'],
            $row['xau_balance'],
            $row['xag_balance'],
            $row['is_verified'] ? 'Yes' : 'No',
            $row['is_admin']    ? 'Yes' : 'No',
            $row['bot_position'],
            $row['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

if ($export === 'transactions') {
    // ── Export Transactions ───────────────────────────────────────────────────
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="aether_transactions_' . date('Ymd_His') . '.csv"');
    header('Cache-Control: no-store, no-cache');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");

    fputcsv($out, [
        'ID', 'User ID', 'Username', 'Email',
        'Type', 'Coin', 'Amount', 'Price (USDT)', 'Total (USDT)',
        'Order Type', 'Status', 'Created At'
    ]);

    $res = $conn->query(
        'SELECT t.id, t.user_id, u.username, u.email,
                t.type, t.coin, t.amount, t.price, t.total,
                t.order_type, t.status, t.created_at
         FROM transactions t
         JOIN users u ON t.user_id = u.id
         ORDER BY t.created_at DESC'
    );
    while ($row = $res->fetch_assoc()) {
        fputcsv($out, [
            $row['id'],
            $row['user_id'],
            $row['username'],
            $row['email'],
            $row['type'],
            $row['coin'],
            $row['amount'],
            $row['price'],
            $row['total'],
            $row['order_type'],
            $row['status'],
            $row['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

// ── Summary counts for display ────────────────────────────────────────────────
$user_count = (int)$conn->query('SELECT COUNT(*) FROM users')->fetch_row()[0];
$txn_count  = (int)$conn->query('SELECT COUNT(*) FROM transactions')->fetch_row()[0];

$csrf_token = csrf_get_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Export Data — Aether Admin</title>
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
                <h1 class="admin-page-title">Export Data</h1>
                <p class="admin-page-subtitle">Download platform data as spreadsheet-compatible CSV files</p>
            </div>
        </div>

        <div class="admin-export-grid">

            <!-- Export Users Card -->
            <div class="admin-export-card">
                <div class="admin-export-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
                <div>
                    <div class="admin-export-title">Export Users</div>
                    <div class="admin-export-desc">
                        Downloads <code>aether_users_[date].csv</code> containing all
                        <strong><?= number_format($user_count) ?> user account(s)</strong> with their
                        balances, verification status, and metadata.
                    </div>
                </div>
                <div>
                    <p style="font-size:0.75rem;color:var(--text-muted);margin-bottom:var(--space-sm);">
                        Columns: ID, Username, Email, USDT Balance, XAU Balance, XAG Balance,
                        Verified, Admin, Bot Position, Created At
                    </p>
                    <a href="admin_export.php?export=users"
                       id="btn-export-users"
                       class="g-btn g-btn-primary"
                       style="width:100%;justify-content:center;">
                        ↓ Download users.csv
                    </a>
                </div>
            </div>

            <!-- Export Transactions Card -->
            <div class="admin-export-card">
                <div class="admin-export-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="1" x2="12" y2="23"/>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                </div>
                <div>
                    <div class="admin-export-title">Export Transactions</div>
                    <div class="admin-export-desc">
                        Downloads <code>aether_transactions_[date].csv</code> containing the complete
                        trade history ledger — all
                        <strong><?= number_format($txn_count) ?> transaction(s)</strong>.
                    </div>
                </div>
                <div>
                    <p style="font-size:0.75rem;color:var(--text-muted);margin-bottom:var(--space-sm);">
                        Columns: ID, User ID, Username, Email, Type, Coin, Amount, Price,
                        Total, Order Type, Status, Created At
                    </p>
                    <a href="admin_export.php?export=transactions"
                       id="btn-export-transactions"
                       class="g-btn g-btn-primary"
                       style="width:100%;justify-content:center;">
                        ↓ Download transactions.csv
                    </a>
                </div>
            </div>

        </div>

        <!-- Info note -->
        <div style="margin-top:var(--space-lg);padding:var(--space-md);background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-md);font-size:0.82rem;color:var(--text-muted);">
            <strong style="color:var(--accent);">ℹ Note:</strong>
            CSV files include a UTF-8 BOM for compatibility with Microsoft Excel.
            All timestamps are in server local time (UTC). Sensitive fields such as password hashes and OTP values are excluded.
        </div>

    </main>
</div>
</body>
</html>
