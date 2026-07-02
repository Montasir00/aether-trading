<?php
/**
 * admin_trigger_reconciliation.php — Manual Reconciliation Worker Controls
 * Shows pending/completed counts and allows admin to bulk-reconcile stuck transactions.
 */
session_start();
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/csrf_helper.php';

$flash = '';
$reconciled_count = 0;

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reconcile') {
    csrf_validate_token();

    // Mark pending transactions older than 1 minute as completed
    $rec = $conn->prepare(
        "UPDATE transactions
         SET status = 'completed'
         WHERE status = 'pending'
           AND created_at <= NOW() - INTERVAL 1 MINUTE"
    );
    $rec->execute();
    $reconciled_count = $rec->affected_rows;
    $rec->close();

    if ($reconciled_count > 0) {
        $flash = "success:Reconciliation complete — {$reconciled_count} transaction(s) marked as completed.";
    } else {
        $flash = "info:No eligible pending transactions found (all pending are less than 1 minute old).";
    }
}

// ── Stats ──────────────────────────────────────────────────────────────────────
$pending_count   = (int)$conn->query("SELECT COUNT(*) FROM transactions WHERE status = 'pending'")->fetch_row()[0];
$completed_count = (int)$conn->query("SELECT COUNT(*) FROM transactions WHERE status = 'completed'")->fetch_row()[0];
$cancelled_count = (int)$conn->query("SELECT COUNT(*) FROM transactions WHERE status = 'cancelled'")->fetch_row()[0];

// Pending older than 1 minute (eligible for reconciliation)
$eligible_count  = (int)$conn->query(
    "SELECT COUNT(*) FROM transactions WHERE status = 'pending' AND created_at <= NOW() - INTERVAL 1 MINUTE"
)->fetch_row()[0];

$csrf_token = csrf_get_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reconciliation — Aether Admin</title>
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
                <h1 class="admin-page-title">Reconciliation</h1>
                <p class="admin-page-subtitle">Manual override for pending and stuck transactions</p>
            </div>
        </div>

        <?php if ($flash): ?>
            <?php [$type, $msg] = explode(':', $flash, 2); ?>
            <div class="admin-flash admin-flash-<?= $type === 'success' ? 'success' : ($type === 'error' ? 'error' : 'info') ?>">
                <?= $type === 'success' ? '✓' : ($type === 'error' ? '✕' : 'ℹ') ?> <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <!-- Transaction Status Counts -->
        <div class="reconcile-stats">
            <div class="reconcile-stat">
                <div class="reconcile-stat-num" style="color:var(--accent);"><?= number_format($pending_count) ?></div>
                <div class="reconcile-stat-label">Pending Transactions</div>
            </div>
            <div class="reconcile-stat">
                <div class="reconcile-stat-num" style="color:var(--green);"><?= number_format($completed_count) ?></div>
                <div class="reconcile-stat-label">Completed Transactions</div>
            </div>
            <div class="reconcile-stat">
                <div class="reconcile-stat-num" style="color:var(--red);"><?= number_format($cancelled_count) ?></div>
                <div class="reconcile-stat-label">Cancelled Transactions</div>
            </div>
            <div class="reconcile-stat" style="border-color:var(--border-strong);">
                <div class="reconcile-stat-num" style="color:<?= $eligible_count > 0 ? 'var(--red)' : 'var(--text-muted)' ?>;">
                    <?= number_format($eligible_count) ?>
                </div>
                <div class="reconcile-stat-label">Eligible for Reconciliation</div>
            </div>
        </div>

        <!-- Reconcile Action Card -->
        <div class="reconcile-action-card">
            <h3>Manual Reconciliation Worker</h3>
            <p>
                This action will query all <strong>pending</strong> transactions older than <strong>1 minute</strong>
                and mark them as <strong>completed</strong>. Use this to resolve stuck or abandoned blockchain transactions.
                <?php if ($eligible_count > 0): ?>
                    <br><br>
                    <strong style="color:var(--accent);"><?= $eligible_count ?> transaction(s)</strong> are currently eligible.
                <?php else: ?>
                    <br><br>
                    <span style="color:var(--text-muted);">No transactions are currently eligible for reconciliation.</span>
                <?php endif; ?>
            </p>

            <form method="post" action="admin_trigger_reconciliation.php"
                  onsubmit="return confirm('Reconcile all pending transactions older than 1 minute? This will mark <?= $eligible_count ?> transaction(s) as completed.')">
                <input type="hidden" name="action" value="reconcile">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <button
                    type="submit"
                    id="btn-reconcile"
                    class="g-btn g-btn-primary"
                    style="font-size:1rem;padding:14px 36px;"
                    <?= $eligible_count === 0 ? 'disabled title="No eligible transactions"' : '' ?>>
                    ⟳ Trigger Reconciliation
                </button>
            </form>
        </div>

        <!-- Recent pending transactions preview -->
        <?php
        $previewStmt = $conn->prepare(
            "SELECT t.id, t.type, t.coin, t.amount, t.total, t.created_at, u.username
             FROM transactions t
             JOIN users u ON t.user_id = u.id
             WHERE t.status = 'pending'
             ORDER BY t.created_at ASC
             LIMIT 15"
        );
        $previewStmt->execute();
        $pending_rows = $previewStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $previewStmt->close();
        ?>

        <?php if (!empty($pending_rows)): ?>
        <div class="admin-table-wrapper" style="margin-top:var(--space-lg);">
            <div class="admin-table-header">
                <div class="admin-table-title">Oldest Pending Transactions (preview)</div>
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
                            <th>Total (USDT)</th>
                            <th>Age</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pending_rows as $pr): ?>
                        <?php $age = time() - strtotime($pr['created_at']); ?>
                        <tr>
                            <td class="td-mono">#<?= $pr['id'] ?></td>
                            <td><?= htmlspecialchars($pr['username']) ?></td>
                            <td><span class="badge-<?= strtolower($pr['type']) ?> g-badge"><?= $pr['type'] ?></span></td>
                            <td><strong><?= $pr['coin'] ?></strong></td>
                            <td class="td-mono"><?= number_format((float)$pr['amount'], 6) ?></td>
                            <td class="td-mono"><?= number_format((float)$pr['total'], 2) ?></td>
                            <td style="font-size:0.78rem;color:<?= $age >= 60 ? 'var(--red)' : 'var(--text-muted)' ?>;">
                                <?php
                                if ($age < 60)       echo $age . 's ago';
                                elseif ($age < 3600) echo floor($age / 60) . 'm ago';
                                else                 echo floor($age / 3600) . 'h ago';
                                ?>
                                <?= $age >= 60 ? ' ⚠' : '' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </main>
</div>
</body>
</html>
