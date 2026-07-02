<?php
require_once 'config.php';

$sql = "
CREATE TABLE IF NOT EXISTS news_sentiment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    headline VARCHAR(512) NOT NULL,
    score FLOAT NOT NULL,
    confidence VARCHAR(20) NOT NULL,
    source VARCHAR(100) NOT NULL,
    url VARCHAR(512) NOT NULL,
    published_at INT NOT NULL,
    fetched_at INT NOT NULL,
    UNIQUE KEY idx_nsi_url (url(255)),
    INDEX idx_nsi_published (published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if ($conn->query($sql) === TRUE) {
    echo "Table 'news_sentiment' created or already exists.\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}
?>
