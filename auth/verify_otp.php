<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit;
}

$entered_otp = $_POST['otp'] ?? '';

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');

if (!isset($_SESSION['otp']) || !isset($_SESSION['otp_expiry']) || !isset($_SESSION['pending_user_id'])) {
    $_SESSION['error'] = "Session expired or unauthorized access. Please login again.";
    if ($isAjax) {
        echo json_encode(['error' => $_SESSION['error']]);
        exit;
    }
    header("Location: ../index.php");
    exit;
}

// Check expiration
if (time() > $_SESSION['otp_expiry']) {
    $_SESSION['error'] = "OTP expired. Please login again.";
    if ($isAjax) {
        echo json_encode(['error' => $_SESSION['error']]);
        exit;
    }
    header("Location: ../index.php");
    exit;
}

// Count attempts
if (!isset($_SESSION['otp_attempts'])) {
    $_SESSION['otp_attempts'] = 0;
}

// Reject if attempts exceeded
if ($_SESSION['otp_attempts'] >= 3) {
    $_SESSION['error'] = "Too many incorrect attempts. Please login again.";
    if ($isAjax) {
        echo json_encode(['error' => $_SESSION['error']]);
        exit;
    }
    header("Location: ../index.php");
    exit;
}

$_SESSION['otp_attempts']++;

if ((string)$entered_otp === (string)$_SESSION['otp']) {
    require '../config.php';
    $user_id = $_SESSION['pending_user_id'];
    
    // Mark user as verified
    $stmt = $conn->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    
    $_SESSION['id'] = $user_id;
    session_regenerate_id(true);

    // Clean up
    unset($_SESSION['otp'], $_SESSION['otp_expiry'], $_SESSION['otp_email'], $_SESSION['otp_attempts'], $_SESSION['pending_user_id'], $_SESSION['otp_debug'], $_SESSION['flash'], $_SESSION['error']);

    if ($isAjax) {
        echo json_encode(['success' => true, 'redirect' => 'dashboard.php']);
        exit;
    }

    header("Location: ../dashboard.php");
    exit;
} else {
    $remaining = 3 - $_SESSION['otp_attempts'];
    $_SESSION['error'] = "Incorrect OTP. You have $remaining attempt(s) left.";
    
    if ($isAjax) {
        echo json_encode(['error' => $_SESSION['error']]);
        exit;
    }

    header("Location: enter_otp.php");
    exit;
}
?>
