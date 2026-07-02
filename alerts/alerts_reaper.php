<?php
/**
 * alerts_reaper.php
 * Resets 'processing' flags for alerts stuck in processing state.
 */
require_once __DIR__ . '/../config.php';

$thresholdMinutes = 10;
$sql = "SELECT id, user_id FROM price_alerts WHERE processing = 1 AND processing_started_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log('[alerts_reaper] Failed to prepare statement: ' . $conn->error);
    exit(1);
}
$stmt->bind_param('i', $thresholdMinutes);
$stmt->execute();
$res = $stmt->get_result();
$stuck = [];
$stuck = [];
while ($r = $res->fetch_assoc()) {
    $stuck[] = ['id' => (int)$r['id'], 'user_id' => isset($r['user_id']) ? (int)$r['user_id'] : null];
}
$stmt->close();

if (count($stuck) === 0) {
    echo "No stale processing rows found.\n";
    exit;
}

foreach ($stuck as $row) {
    $id = $row['id'];
    $user_id = $row['user_id'];

    $u = $conn->prepare('UPDATE price_alerts SET processing = 0 WHERE id = ?');
    $u->bind_param('i', $id);
    $u->execute();
    $u->close();

    $ins = $conn->prepare('INSERT INTO notifications (alert_id, user_id, status, attempt, error_text) VALUES (?, ?, ?, 0, ?)');
    $note = 'Reaped stale processing flag';
    $status = 'failed';
    if ($ins) {
        $ins->bind_param('iiss', $id, $user_id, $status, $note);
        $ins->execute();
        $ins->close();
    } else {
        error_log('[alerts_reaper] Failed to prepare notification insert: ' . $conn->error);
    }

    echo "Reaped alert $id\n";
}
