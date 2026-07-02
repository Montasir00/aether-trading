<?php
/**
 * admin_auth.php — Shared Admin Authentication Guard
 * Include at the very top of every admin page (after session_start).
 * Checks session + DB is_admin flag. Sends 403 if not admin.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Must be logged in
if (empty($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}

// Load DB connection (path relative to admin/ folder)
require_once __DIR__ . '/../config.php';

// Verify is_admin in DB (never trust session alone)
$_admin_id   = (int)$_SESSION['id'];
$_admin_stmt = $conn->prepare('SELECT id, username, email, is_admin FROM users WHERE id = ? LIMIT 1');
$_admin_stmt->bind_param('i', $_admin_id);
$_admin_stmt->execute();
$_admin_row  = $_admin_stmt->get_result()->fetch_assoc();
$_admin_stmt->close();

if (!$_admin_row || (int)$_admin_row['is_admin'] !== 1) {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>403 Forbidden — Aether Admin</title>
    <link rel="stylesheet" href="../global.css">
    <style>body{display:flex;align-items:center;justify-content:center;min-height:100vh;background:var(--bg-primary);color:var(--text-primary);font-family:var(--font);}
    .err{text-align:center;}.err h1{font-size:4rem;color:var(--red);margin-bottom:1rem;font-family:var(--font-display);}
    .err p{color:var(--text-muted);margin-bottom:2rem;}.err a{color:var(--accent);}
    </style></head><body>
    <div class="err"><h1>403</h1><p>You do not have administrator access.</p><a href="../dashboard.php">← Return to Dashboard</a></div>
    </body></html>';
    exit;
}

// Make admin user info available to all pages
$ADMIN_USER = $_admin_row;
