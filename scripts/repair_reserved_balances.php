<?php
require_once __DIR__ . '/../config.php';

echo "Repairing reserved balances...\n";

$updated = 0;
$res = $conn->query("SELECT wallet_id, asset, reserved FROM balances WHERE reserved < 0");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $walletId = (int)$row['wallet_id'];
        $asset = $row['asset'];
        $reserved = abs((float)$row['reserved']);

        $stmt = $conn->prepare('UPDATE balances SET reserved = ? WHERE wallet_id = ? AND asset = ?');
        $stmt->bind_param('dis', $reserved, $walletId, $asset);
        $stmt->execute();
        $stmt->close();
        $updated++;
    }
}

echo "Updated {$updated} balance row(s).\n";
echo "Done.\n";

exit(0);

?>
