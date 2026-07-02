<?php
session_start();
require_once '../config.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $otp        = random_int(100000, 999999);
    $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    // Insert user — no ETH/Ganache columns needed
    $stmt = $conn->prepare(
        "INSERT INTO users (username, email, password_hash, otp, otp_expiry) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("sssss", $username, $email, $hashed_password, $otp, $otp_expiry);

    try {
        if ($stmt->execute()) {
            $user_id = $conn->insert_id;

            $isDevMode = (getenv('APP_ENV') === 'development');
            if ($isDevMode) {
                $_SESSION['otp_debug'] = (string)$otp;
            }
            
            // Send OTP verification email
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = getenv('SMTP_USER') ?: '';
                $mail->Password   = getenv('SMTP_PASS') ?: '';
                $mail->SMTPSecure = getenv('SMTP_SECURE') ?: 'tls';
                $mail->Port       = getenv('SMTP_PORT') ?: 587;

                $mail->setFrom(getenv('SMTP_FROM') ?: '', 'Aether Trading');
                $mail->addAddress($email);
                $mail->Subject = 'Aether OTP Verification Code';
                $mail->Body    = "Welcome to Aether!\n\nYour verification code is: $otp\n\nThis code expires in 10 minutes.";

                $mail->send();
                error_log('[register] OTP sent to: ' . $email);
                $_SESSION['flash'] = "OTP sent to your email.";
            } catch (Exception $e) {
                error_log('[register] Mail error: ' . $mail->ErrorInfo . ' | ' . $e->getMessage());
                $_SESSION['error'] = "OTP email could not be sent. Open Inspect (View Source) on this OTP page in local mode.";
            }

            $_SESSION['pending_user_id'] = $user_id;
            $_SESSION['otp'] = $otp;
            $_SESSION['otp_email'] = $email;
            $_SESSION['otp_expiry'] = time() + 600; // 10 mins
            $_SESSION['otp_attempts'] = 0;

            header("Location: enter_otp.php");
            exit();
        } else {
            $_SESSION['error'] = "Registration error: " . $stmt->error;
            header("Location: ../index.php");
            exit();
        }
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() == 1062) {
            $_SESSION['error'] = "An account with that username or email already exists.";
        } else {
            $_SESSION['error'] = "Database error during registration. Please try again.";
            error_log('[register] DB Error: ' . $e->getMessage());
        }
        header("Location: ../index.php");
        exit();
    }

    $stmt->close();
}
$conn->close();
?>
