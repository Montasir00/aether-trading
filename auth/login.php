<?php
session_start();

ini_set('display_errors', 0);
error_reporting(E_ALL);

require '../vendor/autoload.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../config.php';

    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');

    $email = trim($_POST['email'] ?? '');
    $pwd   = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, email, password_hash, is_verified FROM users WHERE email = ?");
    if (!$stmt) {
        die("Failed to prepare statement: " . $conn->error);
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($pwd, $user['password_hash'])) {

            // OTP generation
            $otp = random_int(100000, 999999);
            $_SESSION['pending_user_id'] = $user['id'];
            $_SESSION['otp'] = $otp;
            $_SESSION['otp_email'] = $user['email'];
            $_SESSION['otp_expiry'] = time() + 600;
            $_SESSION['otp_attempts'] = 0;          

            $isDevMode = (getenv('APP_ENV') === 'development');
            if ($isDevMode) {
                $_SESSION['otp_debug'] = (string)$otp;
            }
            
            // Send OTP via email
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
                $mail->addAddress($user['email']);
                $mail->isHTML(true);
                $mail->Subject = 'Aether Login Authentication Code';
                $mail->Body    = "Secure Login Request for Aether.<br><br>Your authentication code is: <b>$otp</b><br><br>This code will expire in 10 minutes. If you did not request this, please secure your account immediately.";

                $mail->send();
                error_log('[login] OTP sent to: ' . $user['email']);
                $_SESSION['flash'] = "OTP sent to your email.";
                
                if ($isAjax) {
                    echo json_encode([
                        'success' => true,
                        'email' => $user['email'],
                        'expiry' => $_SESSION['otp_expiry'],
                        'debug_otp' => $_SESSION['otp_debug'] ?? ''
                    ]);
                    exit;
                }
                
                header("Location: enter_otp.php");
                exit;
            } catch (Exception $e) {
                error_log('[login] Mail error: ' . $mail->ErrorInfo . ' | ' . $e->getMessage());
                $_SESSION['error'] = "OTP email could not be sent. Open Inspect (View Source) on this OTP page in local mode.";
                
                if ($isAjax) {
                    echo json_encode([
                        'success' => true,
                        'email' => $user['email'],
                        'expiry' => $_SESSION['otp_expiry'],
                        'debug_otp' => $_SESSION['otp_debug'] ?? '',
                        'warning' => 'OTP email failed to send.'
                    ]);
                    exit;
                }
                
                header("Location: enter_otp.php");
                exit;
            }
        } else {
            $_SESSION['error'] = "Invalid email or password.";
            if ($isAjax) {
                echo json_encode(['error' => $_SESSION['error']]);
                exit;
            }
            header("Location: ../index.php");
            exit;
        }
    } else {
        $_SESSION['error'] = "Invalid email or password.";
        if ($isAjax) {
            echo json_encode(['error' => $_SESSION['error']]);
            exit;
        }
        header("Location: ../index.php");
        exit;
    }

    $stmt->close();
    $conn->close();

} else {
    header("Location: ../index.php");
    exit;
}
