<?php
session_start();
if (!isset($_SESSION['id'])) {
    header("Location: ../index.php");
    exit;
}

require '../config.php';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['alert_id'])) {
    $alert_id = (int)$_POST['alert_id'];
    $user_id = $_SESSION['id'];

    $stmt = $conn->prepare("DELETE FROM price_alerts WHERE id = ? AND user_id = ? AND notified = 0");
    $stmt->bind_param("ii", $alert_id, $user_id);
    $stmt->execute();
    $stmt->close();
}

header("Location: ../dashboard.php");
exit;
