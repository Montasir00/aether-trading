<?php
/**
 * admin_edit_user.php — Edit a user's profile (username, email, balance, admin flag).
 */
session_start();
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/csrf_helper.php';

$uid = (int)($_GET['id'] ?? $_POST['user_id'] ?? 0);
if ($uid <= 0) { header('Location: admin.php'); exit; }

$flash = '';

// ── Handle POST ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_token();

    $new_username = trim($_POST['username'] ?? '');
    $new_email    = trim($_POST['email']    ?? '');
    $new_balance  = (float)($_POST['balance'] ?? 0);
    $new_is_admin = isset($_POST['is_admin']) ? 1 : 0;

    // Basic validation
    if (empty($new_username) || empty($new_email)) {
        $flash = 'error:Username and email are required.';
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $flash = 'error:Invalid email address.';
    } elseif ($new_balance < 0) {
        $flash = 'error:Balance cannot be negative.';
    } else {
        // Prevent removing your own admin flag
        if ($uid === (int)$_SESSION['id'] && !$new_is_admin) {
            $flash = 'error:You cannot remove your own admin access.';
        } else {
            $upd = $conn->prepare('UPDATE users SET username=?, email=?, balance=?, is_admin=? WHERE id=?');
            $upd->bind_param('ssdii', $new_username, $new_email, $new_balance, $new_is_admin, $uid);
            if ($upd->execute()) {
                $flash = 'success:User updated successfully.';
            } else {
                $flash = 'error:Update failed: ' . $conn->error;
            }
            $upd->close();
        }
    }
}

// ── Load current user data ─────────────────────────────────────────────────
$stmt = $conn->prepare('SELECT id, username, email, balance, xau_balance, xag_balance, is_admin, is_verified, created_at FROM users WHERE id = ?');
$stmt->bind_param('i', $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) { header('Location: admin.php'); exit; }

$csrf_token = csrf_get_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit User #<?= $uid ?> — Aether Admin</title>
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
                <h1 class="admin-page-title">Edit User</h1>
                <p class="admin-page-subtitle">#<?= $uid ?> — <?= htmlspecialchars($user['username']) ?></p>
            </div>
            <div class="admin-page-actions">
                <a href="admin_view_user.php?id=<?= $uid ?>" class="btn-action btn-view">← Back to Profile</a>
            </div>
        </div>

        <?php if ($flash): ?>
            <?php [$type, $msg] = explode(':', $flash, 2); ?>
            <div class="admin-flash admin-flash-<?= $type === 'success' ? 'success' : 'error' ?>">
                <?= $type === 'success' ? '✓' : '✕' ?> <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="admin_edit_user.php" class="admin-form-card">
            <input type="hidden" name="user_id" value="<?= $uid ?>">
            <?= csrf_field() ?>

            <!-- Read-only: ID -->
            <div class="admin-form-row">
                <label for="f-id">User ID</label>
                <div class="admin-form-readonly" id="f-id">#<?= $uid ?></div>
            </div>

            <!-- Read-only: Created At -->
            <div class="admin-form-row">
                <label>Created At</label>
                <div class="admin-form-readonly"><?= date('d M Y, H:i', strtotime($user['created_at'])) ?></div>
            </div>

            <!-- Read-only: XAU Balance -->
            <div class="admin-form-row">
                <label>XAU Balance (Gold, oz) — Read-only</label>
                <div class="admin-form-readonly"><?= number_format((float)$user['xau_balance'], 6) ?></div>
            </div>

            <!-- Read-only: XAG Balance -->
            <div class="admin-form-row">
                <label>XAG Balance (Silver, oz) — Read-only</label>
                <div class="admin-form-readonly"><?= number_format((float)$user['xag_balance'], 4) ?></div>
            </div>

            <!-- Editable: Username -->
            <div class="admin-form-row">
                <label for="f-username">Username</label>
                <input type="text" id="f-username" name="username" class="g-input"
                       value="<?= htmlspecialchars($user['username']) ?>" required maxlength="100">
            </div>

            <!-- Editable: Email -->
            <div class="admin-form-row">
                <label for="f-email">Email</label>
                <input type="email" id="f-email" name="email" class="g-input"
                       value="<?= htmlspecialchars($user['email']) ?>" required maxlength="255">
            </div>

            <!-- Editable: USDT Balance -->
            <div class="admin-form-row">
                <label for="f-balance">Virtual USDT Balance</label>
                <input type="number" id="f-balance" name="balance" class="g-input"
                       value="<?= htmlspecialchars($user['balance']) ?>"
                       min="0" step="0.01" required>
            </div>

            <!-- Editable: is_admin toggle -->
            <div class="admin-form-row">
                <label>Administrator Access</label>
                <div class="admin-toggle-wrap">
                    <?php if ($uid === (int)$_SESSION['id']): ?>
                        <!-- Hidden fallback keeps is_admin=1 in POST when checkbox is disabled -->
                        <input type="hidden" name="is_admin" value="1">
                        <input type="checkbox" id="f-is-admin" class="admin-toggle-input" checked disabled
                               title="Cannot remove your own admin access">
                    <?php else: ?>
                        <input type="checkbox" id="f-is-admin" name="is_admin" class="admin-toggle-input"
                               <?= $user['is_admin'] ? 'checked' : '' ?>>
                    <?php endif; ?>
                    <label for="f-is-admin" style="font-size:0.875rem;color:var(--text-primary);text-transform:none;letter-spacing:0;">Grant administrator access</label>
                </div>
            </div>

            <div class="admin-form-actions">
                <button type="submit" class="g-btn g-btn-primary" id="btn-save-user">Save Changes</button>
                <a href="admin_view_user.php?id=<?= $uid ?>" class="g-btn g-btn-ghost">Cancel</a>
            </div>
        </form>

    </main>
</div>
</body>
</html>
