<?php
header('Content-Type: text/plain');
$file = __DIR__ . '/blockchain/confirm_trade.php';
echo "File path: $file\n";
echo "File size: " . filesize($file) . "\n";
echo "MD5 hash: " . md5_file($file) . "\n";
$lines = file($file);
echo "Line 34: " . $lines[33] . "\n";
echo "Line 112: " . $lines[111] . "\n";
echo "Line 113: " . $lines[112] . "\n";
echo "Line 114: " . $lines[113] . "\n";
