<?php
session_start();

require '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['otp_email']) || !isset($_SESSION['pending_user_id'])) {
    $_SESSION['error'] = "Session expired. Please log in again.";
    header("Location: ../index.php");
    exit;
}

// Rate limiting
if (isset($_SESSION['last_otp_resend']) && time() - $_SESSION['last_otp_resend'] < 60) {
    echo "Please wait 60 seconds before requesting a new OTP.";
    exit;
}
$_SESSION['last_otp_resend'] = time();

// Regenerate OTP
$otp = random_int(100000, 999999);
$_SESSION['otp'] = $otp;
$_SESSION['otp_expiry'] = time() + 600;
$_SESSION['otp_attempts'] = 0;

$isDevMode = (getenv('APP_ENV') === 'development');
if ($isDevMode) {
    $_SESSION['otp_debug'] = (string)$otp;
}

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = getenv('SMTP_USER') ?: '';
    $mail->Password = getenv('SMTP_PASS') ?: '';
    $mail->SMTPSecure = getenv('SMTP_SECURE') ?: 'tls';
    $mail->Port = getenv('SMTP_PORT') ?: 587;

    $mail->setFrom(getenv('SMTP_FROM') ?: '', 'Aether Security');
    $mail->addAddress($_SESSION['otp_email']);
    $mail->isHTML(true);
    $mail->Subject = 'Your New Aether Authentication Code';
    $mail->Body    = "Secure Login Request for Aether.<br><br>Your new authentication code is: <b>$otp</b><br><br>This code will expire in 10 minutes. If you did not request this, please secure your account immediately.";

    $mail->send();
    error_log('[resend_otp] OTP sent to: ' . $_SESSION['otp_email']);
    $_SESSION['flash'] = "A new OTP was sent to your email.";

    header("Location: enter_otp.php");
    exit;

} catch (Exception $e) {
    error_log('[resend_otp] Mail error: ' . $mail->ErrorInfo . ' | ' . $e->getMessage());
    $_SESSION['error'] = "Could not resend OTP email. Open Inspect (View Source) on this OTP page in local mode.";
    header("Location: enter_otp.php");
    exit;
}
