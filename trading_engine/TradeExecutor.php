<?php

require_once 'PositionManager.php';
require_once '../wallet_functions.php';
require_once '../mail_helper.php';

class TradeExecutor {

    private PositionManager $positionManager;

    public function __construct(int $userId) {
        $this->positionManager = new PositionManager($userId);
    }

    public function execute(
        string $signal,
        int    $userId,
        float  $price
    ): string {

        if ($signal === "HOLD") {
            return "HOLD";
        }

        // Fetch user email from database for notifications
        global $conn;
        $email = null;
        if (isset($conn) && $conn instanceof mysqli) {
            $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $email = $row['email'] ?? null;
            }
        }

        // Bot trades Gold (XAU) by default
        if ($signal === "BUY" && $this->positionManager->canBuy()) {
            if (tradeAsset($userId, 'BUY', 'XAU', $price)) {
                $this->positionManager->enterLong();
                
                // Send notification email
                if ($email) {
                    $subject = "Bot Action: BUY Gold (XAU)";
                    $body = "<h3>&#129302; Aether SMA Strategy Bot Execution</h3>"
                          . "<p>Your SMA strategy bot has automatically executed a trade.</p>"
                          . "<ul>"
                          . "<li><b>Action:</b> BUY</li>"
                          . "<li><b>Asset:</b> Gold (XAU)</li>"
                          . "<li><b>Amount:</b> 0.01 oz</li>"
                          . "<li><b>Execution Price:</b> $" . number_format($price, 2) . " USDT/oz</li>"
                          . "<li><b>Total Cost:</b> $" . number_format(0.01 * $price, 2) . " USDT</li>"
                          . "</ul>"
                          . "<p>Log in to your Aether dashboard to view your updated portfolio balance.</p>";
                    send_email($email, $subject, $body);
                }
                
                return "BUY_EXECUTED";
            }
            return "BUY_FAILED";
        }

        if ($signal === "SELL" && $this->positionManager->canSell()) {
            if (tradeAsset($userId, 'SELL', 'XAU', $price)) {
                $this->positionManager->exitLong();
                
                // Send notification email
                if ($email) {
                    $subject = "Bot Action: SELL Gold (XAU)";
                    $body = "<h3>&#129302; Aether SMA Strategy Bot Execution</h3>"
                          . "<p>Your SMA strategy bot has automatically executed a trade.</p>"
                          . "<ul>"
                          . "<li><b>Action:</b> SELL</li>"
                          . "<li><b>Asset:</b> Gold (XAU)</li>"
                          . "<li><b>Amount:</b> 0.01 oz</li>"
                          . "<li><b>Execution Price:</b> $" . number_format($price, 2) . " USDT/oz</li>"
                          . "<li><b>Total Revenue:</b> $" . number_format(0.01 * $price, 2) . " USDT</li>"
                          . "</ul>"
                          . "<p>Log in to your Aether dashboard to view your updated portfolio balance.</p>";
                    send_email($email, $subject, $body);
                }
                
                return "SELL_EXECUTED";
            }
            return "SELL_FAILED";
        }

        return "NO_ACTION";
    }
}
