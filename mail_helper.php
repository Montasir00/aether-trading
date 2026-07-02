<?php
/**
 * mail_helper.php
 * Reusable helper to send emails via PHPMailer using .env settings.
 */

require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function send_email(string $to, string $subject, string $bodyHtml): bool
{
    $smtpHost   = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
    $smtpUser   = getenv('SMTP_USER');
    $smtpPass   = getenv('SMTP_PASS');
    $smtpPort   = getenv('SMTP_PORT') ? intval(getenv('SMTP_PORT')) : 587;
    $smtpSecure = getenv('SMTP_SECURE') ?: 'tls';
    $smtpFrom   = getenv('SMTP_FROM') ?: $smtpUser;

    if (empty($smtpUser) || empty($smtpPass)) {
        error_log("[mail_helper] SMTP not configured. Missing SMTP_USER or SMTP_PASS.");
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $smtpHost;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUser;
        $mail->Password   = $smtpPass;
        $mail->SMTPSecure = $smtpSecure;
        $mail->Port       = $smtpPort;

        $mail->setFrom($smtpFrom, 'Aether Platform');
        $mail->CharSet = 'UTF-8';

        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $bodyHtml;

        $mail->send();
        error_log("[mail_helper] Email sent to $to with subject '$subject'");
        return true;
    } catch (Exception $e) {
        error_log("[mail_helper] PHPMailer Error: " . $mail->ErrorInfo . " | Exception: " . $e->getMessage());
        return false;
    }
}
