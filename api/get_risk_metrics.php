<?php
/**
 * API endpoint: Returns portfolio risk metrics as JSON
 * Called by dashboard.js to display the risk panel
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

// Unlock session immediately since we only need to read the ID
session_write_close();

require_once '../config.php';
require_once '../trading_engine/RiskManager.php';

$risk = new RiskManager($conn, $_SESSION['id']);
echo json_encode($risk->getPortfolioMetrics());
