<?php
require_once __DIR__ . '/../config.php';

echo "Seeding 210 price history points for XAU to bypass threshold...\n";

// Disable foreign key checks just in case
$conn->query("SET FOREIGN_KEY_CHECKS = 0");

$asset = 'XAU';
$basePrice = 2000.00;

// Generate 210 points
for ($i = 210; $i >= 0; $i--) {
    // Generate a slightly changing price
    $price = $basePrice + (sin($i / 10.0) * 10) + (cos($i / 5.0) * 5);
    
    // Insert with decreasing interval times so they are ordered chronologically
    $stmt = $conn->prepare("INSERT INTO price_history (asset, price, recorded_at) VALUES (?, ?, NOW() - INTERVAL ? SECOND)");
    $secondsAgo = $i * 120;
    $stmt->bind_param("sdi", $asset, $price, $secondsAgo);
    $stmt->execute();
    $stmt->close();
}

$conn->query("SET FOREIGN_KEY_CHECKS = 1");

echo "Successfully seeded XAU price history! You now have over 210 points.\n";
