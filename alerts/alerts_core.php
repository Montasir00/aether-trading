<?php
/**
 * alerts_core.php
 * Provides run_alert_checks($conn) to execute the alert checking logic.
 */
require_once __DIR__ . '/../api/api_helper.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

function run_alert_checks(mysqli $conn)
{
    // Step 1: Get distinct coins with pending alerts dynamically
    $prices = [];
    $coinsRes = $conn->query("SELECT DISTINCT coin FROM price_alerts WHERE notified = 0 AND processing = 0");
    if ($coinsRes) {
        while ($cRow = $coinsRes->fetch_assoc()) {
            $coin = strtoupper($cRow['coin']);
            $fetchedPrice = fetchBinancePrice("{$coin}USDT");
            if ($fetchedPrice === null) {
                error_log("[check_alerts] Failed to fetch {$coin} price after retries. Skipping.");
                continue;
            }
            $prices[$coin] = $fetchedPrice;
        }
    }

    // Step 2: Fetch candidate alerts (pending and not being processed)
    $maxAttempts = getenv('MAX_SEND_ATTEMPTS') ? intval(getenv('MAX_SEND_ATTEMPTS')) : 3;
    $alertsStmt = $conn->prepare("SELECT a.id, a.user_id, a.coin, a.target_price, a.operator, u.email FROM price_alerts a JOIN users u ON a.user_id = u.id WHERE a.notified = 0 AND a.processing = 0 AND (a.send_attempts IS NULL OR a.send_attempts < ?)");
    if (!$alertsStmt) {
        error_log('[check_alerts] Failed to prepare price_alerts query: ' . $conn->error);
        return;
    }
    $alertsStmt->bind_param('i', $maxAttempts);
    if (!$alertsStmt->execute()) {
        error_log('[check_alerts] Failed to execute price_alerts query: ' . $conn->error);
        return;
    }

    $result = $alertsStmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $id = (int)$row['id'];
        $user_id = (int)$row['user_id'];
        $user_email = $row['email'];
        $coin = $row['coin'];
        $target = (float)$row['target_price'];
        $operator = $row['operator'] ?? '>=';

        if (!isset($prices[$coin])) continue; // skip if price unavailable
        $current = $prices[$coin];
        
        if ($operator === '>=' && $current < $target) continue; // not yet triggered
        if ($operator === '<=' && $current > $target) continue; // not yet triggered

        // Attempt to claim the alert atomically
        $claimStmt = $conn->prepare("UPDATE price_alerts SET processing = 1, processing_started_at = NOW() WHERE id = ? AND notified = 0 AND processing = 0");
        $claimStmt->bind_param('i', $id);
        if (!$claimStmt->execute()) {
            error_log('[check_alerts] Failed to claim alert ' . $id . ': ' . $conn->error);
            $claimStmt->close();
            continue;
        }
        $claimed = $conn->affected_rows === 1;
        $claimStmt->close();
        if (!$claimed) {
            // Someone else claimed it
            continue;
        }

        // Read current send attempts
        $attempts = 0;
        $aStmt = $conn->prepare("SELECT send_attempts FROM price_alerts WHERE id = ?");
        $aStmt->bind_param('i', $id);
        $aStmt->execute();
        $ar = $aStmt->get_result()->fetch_assoc();
        if ($ar) $attempts = (int)$ar['send_attempts'];
        $aStmt->close();

        // Build mail
        $smtpHost   = getenv('SMTP_HOST');
        $smtpUser   = getenv('SMTP_USER');
        $smtpPass   = getenv('SMTP_PASS');
        $smtpPort   = getenv('SMTP_PORT') ? intval(getenv('SMTP_PORT')) : 587;
        $smtpSecure = getenv('SMTP_SECURE') ?: 'tls';
        $smtpFrom   = getenv('SMTP_FROM') ?: $smtpUser;

        $coinName = ($coin === 'XAU') ? 'Gold (XAU)' : (($coin === 'XAG') ? 'Silver (XAG)' : $coin);

        $notificationStatus = 'failed';
        $errorText = null;

        if (empty($smtpHost) || empty($smtpUser) || empty($smtpPass)) {
            $errorText = 'SMTP not configured';
            error_log("[check_alerts] SMTP missing; will not send alert $id to $user_email");
        } else {
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = $smtpHost;
                $mail->SMTPAuth   = true;
                $mail->Username   = $smtpUser;
                $mail->Password   = $smtpPass;
                $mail->SMTPSecure = $smtpSecure;
                $mail->Port       = $smtpPort;

                $mail->setFrom($smtpFrom, 'Aether Alerts');
                $mail->addAddress($user_email);
                $mail->isHTML(true);
                $mail->Subject = "{$coinName} Market Alert Triggered";
                $mail->Body    = "<b>Aether Market Alert</b><br><br>{$coinName} has crossed your target threshold.<br><br><b>Current Price:</b> {$current} USDT/oz<br><b>Target Price:</b> {$target} USDT/oz<br><br>Log in to Aether to review your portfolio.";

                if ($mail->send()) {
                    $notificationStatus = 'sent';
                } else {
                    $errorText = 'mail->send returned false';
                }
            } catch (Exception $e) {
                $errorText = $e->getMessage();
            }
        }

        // Record notification attempt
        $nextAttempt = $attempts + 1;

        $willExceed = $nextAttempt >= $maxAttempts;

        // Append max-attempts note when the retry budget is exhausted.
        // (The previous condition `empty($errorText)` was unreachable — every failure
        //  path above always sets $errorText, so this block never executed.)
        if ($willExceed) {
            $errorText = ($errorText ? $errorText . '; ' : '') . 'max attempts reached';
        }

        $ins = $conn->prepare('INSERT INTO notifications (alert_id, user_id, status, attempt, error_text) VALUES (?, ?, ?, ?, ?)');
        // types: int, int, string, int, string
        $ins->bind_param('iisis', $id, $user_id, $notificationStatus, $nextAttempt, $errorText);
        if (!$ins->execute()) {
            error_log("[check_alerts] Failed to insert notification for alert {$id}: " . $ins->error);
        }
        $ins->close();

        // Finalize alert row
        if ($notificationStatus === 'sent') {
            $fstmt = $conn->prepare('UPDATE price_alerts SET notified = 1, processing = 0, send_attempts = COALESCE(send_attempts,0) + 1, last_attempt_at = NOW() WHERE id = ?');
            $fstmt->bind_param('i', $id);
            $fstmt->execute();
            $fstmt->close();
            error_log("[check_alerts] Alert $id triggered and email sent to {$user_email}");
        } else {
            if ($willExceed) {
                error_log("[check_alerts] Alert $id reached max attempts ({$maxAttempts}), marking permanent failure");
            }

            $fstmt = $conn->prepare('UPDATE price_alerts SET processing = 0, send_attempts = COALESCE(send_attempts,0) + 1, last_attempt_at = NOW() WHERE id = ?');
            $fstmt->bind_param('i', $id);
            $fstmt->execute();
            $fstmt->close();
            error_log("[check_alerts] Alert $id failed to send: {$errorText}");
        }
    }
    $alertsStmt->close();
}

?>