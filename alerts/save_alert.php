<?php
session_start();
if (!isset($_SESSION['id'])) {
    $_SESSION['error'] = "Login required";
    header("Location: ../index.php");
    exit;
}

require_once '../config.php';

$coin = strtoupper(trim($_POST['coin'] ?? ''));
$stmtCheck = $conn->prepare("SELECT 1 FROM trading_pairs WHERE base_asset = ? AND active = 1 LIMIT 1");
$stmtCheck->bind_param("s", $coin);
$stmtCheck->execute();
$isValidCoin = $stmtCheck->get_result()->fetch_row();
$stmtCheck->close();

if (!$isValidCoin) {
    $_SESSION['error'] = "Invalid asset/commodity selected.";
    header("Location: create_alert.php");
    exit;
}

$price = floatval($_POST['target_price'] ?? 0);
if ($price <= 0) {
    $_SESSION['error'] = "Invalid target price";
    header("Location: create_alert.php");
    exit;
}

$user_id = $_SESSION['id'];

require_once '../api/api_helper.php';
$current_price = fetchBinancePrice($coin . 'USDT') ?? 0;
$operator = ($price >= $current_price) ? '>=' : '<=';

$stmt = $conn->prepare("INSERT INTO price_alerts (user_id, coin, target_price, operator) VALUES (?, ?, ?, ?)");
$stmt->bind_param("isds", $user_id, $coin, $price, $operator);

if (!$stmt->execute()) {
    $stmt->close();
    $_SESSION['error'] = "Failed to save alert. Please try again.";
    header('Location: create_alert.php');
    exit;
}
$stmt->close();

$_SESSION['flash'] = "Alert created successfully!";
header('Location: ../dashboard.php');
exit;
