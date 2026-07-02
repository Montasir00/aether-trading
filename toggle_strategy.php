<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['id'])) {
    header("Location: dashboard.php");
    exit;
}

$user_id = (int)$_SESSION['id'];

// Get current state
$stmt = $conn->prepare("SELECT bot_enabled FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$newState = $row && $row['bot_enabled'] ? 0 : 1;

// Toggle state in database
$stmt = $conn->prepare("UPDATE users SET bot_enabled = ? WHERE id = ?");
$stmt->bind_param("ii", $newState, $user_id);
$stmt->execute();
$stmt->close();

// Update session for compatibility
$_SESSION['strategy_enabled'] = (bool)$newState;

$_SESSION['flash'] = "Bot status successfully updated to " . ($newState ? "ENABLED" : "DISABLED") . ".";

header("Location: dashboard.php");
exit;
