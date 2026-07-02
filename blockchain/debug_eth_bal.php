<?php
echo function_exists('bcdiv') ? "bcdiv: YES\n" : "bcdiv: NO\n";
echo function_exists('gmp_init') ? "gmp: YES\n" : "gmp: NO\n";

// Test the BigNumber->toString() call used in get_eth_balance.php
require_once 'vendor/autoload.php';
use Web3\Web3;

$web3 = new Web3('http://host.docker.internal:7545');
$address = '0x85ee1cbd63897a8732758bbc6f6248ed50025102';

$balanceWei = null;
$web3->eth->getBalance($address, 'latest', function($err, $balance) use (&$balanceWei) {
    if (!$err) $balanceWei = $balance;
});

echo "balanceWei type: " . gettype($balanceWei) . "\n";
if (is_object($balanceWei)) {
    echo "balanceWei class: " . get_class($balanceWei) . "\n";
    echo "has toString: " . (method_exists($balanceWei, 'toString') ? 'YES' : 'NO') . "\n";
    $wei = $balanceWei->toString();
    echo "toString result: $wei\n";
    if (function_exists('bcdiv')) {
        $eth = bcdiv($wei, '1000000000000000000', 6);
        echo "bcdiv result: $eth ETH\n";
    } else {
        $eth = number_format((float)$wei / 1e18, 6, '.', '');
        echo "float result: $eth ETH\n";
    }
} else {
    echo "balanceWei raw: " . var_export($balanceWei, true) . "\n";
}
