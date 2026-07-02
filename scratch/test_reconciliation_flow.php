<?php
/**
 * test_reconciliation_flow.php
 * Helper script to create a stuck pending transaction so you can test the Reconciliation page.
 */
require_once __DIR__ . '/../config.php';

// Find a valid user to associate with the transaction
$userRes = $conn->query("SELECT id, username FROM users LIMIT 1");
$user = $userRes->fetch_assoc();

if (!$user) {
    echo "Error: No users found in the database. Please register a user first.\n";
    exit;
}

$userId = $user['id'];
$username = $user['username'];

// Check if we are running the check phase or the insert phase
if (isset($_GET['check_id'])) {
    $checkId = (int)$_GET['check_id'];
    $stmt = $conn->prepare("SELECT status, created_at FROM transactions WHERE id = ?");
    $stmt->bind_param("i", $checkId);
    $stmt->execute();
    $tx = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($tx) {
        echo json_encode([
            "success" => true,
            "id" => $checkId,
            "status" => $tx['status'],
            "created_at" => $tx['created_at']
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Transaction not found."]);
    }
    exit;
}

// Insert a mock pending transaction created 5 minutes ago
$stmt = $conn->prepare("
    INSERT INTO transactions (user_id, type, coin, amount, price, total, order_type, status, created_at)
    VALUES (?, 'BUY', 'XAU', 0.500000, 2000.00, 1000.00, 'market', 'pending', NOW() - INTERVAL 5 MINUTE)
");
$stmt->bind_param("i", $userId);

if ($stmt->execute()) {
    $insertedId = $conn->insert_id;
    $stmt->close();
    
    // Output UI instruction page
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Test Reconciliation Tool</title>
        <link rel="stylesheet" href="../global.css">
        <style>
            body { font-family: sans-serif; background: #0b0f19; color: #f3f4f6; padding: 40px; }
            .box { max-width: 600px; margin: 0 auto; background: #111827; border: 1px solid #1f2937; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.5); }
            h2 { color: #AB47BC; border-bottom: 1px solid #1f2937; padding-bottom: 10px; }
            .btn { display: inline-block; background: #AB47BC; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none; font-weight: bold; margin-top: 15px; }
            .btn:hover { background: #8e24aa; }
            .step { margin: 15px 0; line-height: 1.6; }
            .badge { background: #374151; padding: 2px 8px; border-radius: 4px; font-family: monospace; font-size: 0.9em; }
        </style>
        <script>
            async function checkStatus() {
                const res = await fetch('?check_id=<?= $insertedId ?>');
                const data = await res.json();
                if (data.success) {
                    const statusEl = document.getElementById('tx-status');
                    statusEl.textContent = data.status.toUpperCase();
                    if (data.status === 'completed') {
                        statusEl.style.color = '#10B981';
                        document.getElementById('success-msg').style.display = 'block';
                    } else {
                        statusEl.style.color = '#F59E0B';
                    }
                }
            }
            setInterval(checkStatus, 3000);
        </script>
    </head>
    <body>
        <div class="box">
            <h2>Reconciliation Flow Test Initialized!</h2>
            <div class="step">
                <strong>1. Transaction Created:</strong><br>
                A mock transaction <span class="badge">#<?= $insertedId ?></span> for user <strong><?= htmlspecialchars($username) ?></strong> has been inserted with status <span id="tx-status" style="color: #F59E0B; font-weight: bold;">PENDING</span> and marked as created 5 minutes ago.
            </div>
            
            <div class="step">
                <strong>2. Perform the Test:</strong><br>
                Open a new browser tab, log in as an administrator, and go to:<br>
                <a href="../admin/admin_trigger_reconciliation.php" target="_blank" class="btn">Go to Admin Reconciliation Page</a>
            </div>

            <div class="step">
                <strong>3. Trigger Reconciliation:</strong><br>
                You should see <strong>1 transaction eligible for reconciliation</strong> on that page. Click the **Trigger Reconciliation** button.
            </div>

            <div class="step">
                <strong>4. Watch Real-time Result below:</strong>
                <p id="success-msg" style="display:none; color:#10B981; font-weight:bold; font-size:1.1em; margin-top:15px;">
                    ✓ Success! The transaction status has changed to COMPLETED in the database!
                </p>
            </div>
        </div>
    </body>
    </html>
    <?php
} else {
    echo "Error inserting transaction: " . $conn->error . "\n";
}
?>
