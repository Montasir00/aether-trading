<?php
/**
 * Aether Trading Platform — Blockchain Settlement Simulation Prototype
 * Location: /scratch/blockchain_settlement_demo.php
 *
 * This script demonstrates how the PHP matching engine connects to a local
 * Ethereum blockchain (Ganache) and records executed trades on-chain.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Web3\Web3;
use Web3\Contract;

// 1. Connection settings for local Ganache blockchain node
$ganacheUrl = 'http://127.0.0.1:7545'; // Default Ganache GUI RPC port
echo "Connecting to local Ganache blockchain node at {$ganacheUrl}...\n";

$web3 = new Web3($ganacheUrl);

// 2. Simulated Smart Contract ABI (Application Binary Interface)
// In a real setup, this is compiled from the Solidity file (TradeContract.sol)
$contractAbi = '[
    {
        "anonymous": false,
        "inputs": [
            {"indexed": true, "name": "tradeId", "type": "uint256"},
            {"indexed": false, "name": "buyer", "type": "string"},
            {"indexed": false, "name": "seller", "type": "string"},
            {"indexed": false, "name": "asset", "type": "string"},
            {"indexed": false, "name": "amount", "type": "uint256"},
            {"indexed": false, "name": "price", "type": "uint256"},
            {"indexed": false, "name": "timestamp", "type": "uint256"}
        ],
        "name": "TradeRecorded",
        "type": "event"
    },
    {
        "constant": false,
        "inputs": [
            {"name": "_buyer", "type": "string"},
            {"name": "_seller", "type": "string"},
            {"name": "_asset", "type": "string"},
            {"name": "_amount", "type": "uint256"},
            {"name": "_price", "type": "uint256"}
        ],
        "name": "recordTrade",
        "outputs": [],
        "payable": false,
        "stateMutability": "nonpayable",
        "type": "function"
    }
]';

// Deployed contract address on Ganache (replace with your compiled contract address)
$contractAddress = '0x1234567890123456789012345678901234567890'; 

// 3. Test Connection
$web3->clientVersion(function ($err, $version) use ($web3, $contractAbi, $contractAddress) {
    if ($err !== null) {
        echo "\n❌ Connection Failed: Ganache is not running or unreachable on port 7545.\n";
        echo "💡 To fix: Open Ganache GUI or run 'ganache-cli' in your terminal.\n\n";
        
        // Mock verification for presentation simulator:
        echo "--- RUNNING SIMULATED BLOCKCHAIN FALLBACK ---\n";
        simulateBlockchainSettlement("Client_User_42", "Market_Maker_1", "XAU", 2.5, 2350.00);
        return;
    }
    
    echo "✅ Successfully connected to: " . $version . "\n\n";
    
    // 4. Retrieve available accounts from Ganache
    $web3->eth->accounts(function ($err, $accounts) use ($web3, $contractAbi, $contractAddress) {
        if ($err !== null) {
            echo "Error fetching accounts: " . $err->getMessage() . "\n";
            return;
        }
        
        $fromAccount = $accounts[0]; // Use the first unlocked Ganache address to pay gas fees
        echo "Using unlocked account to sign transaction: {$fromAccount}\n";
        
        // 5. Instantiate the contract object
        $contract = new Contract($web3->provider, $contractAbi);
        
        // Simulated matched trade parameters
        $buyer = "AlexMercer";
        $seller = "AetherLiquidity";
        $asset = "XAU";
        $amountWei = 250000; // Multiplied by 10^5 to prevent decimal floating point issues in Solidity
        $priceUSDT = 235000;
        
        echo "Settling matched trade on-chain:\n";
        echo "  - Buyer: {$buyer}\n";
        echo "  - Seller: {$seller}\n";
        echo "  - Asset: {$asset}\n";
        echo "  - Amount: 2.5 oz\n";
        echo "  - Price: \$2,350.00\n";
        
        // 6. Send transaction call to smart contract's recordTrade function
        $contract->at($contractAddress)->send('recordTrade', $buyer, $seller, $asset, $amountWei, $priceUSDT, [
            'from' => $fromAccount,
            'gas' => 200000
        ], function ($err, $txHash) {
            if ($err !== null) {
                echo "❌ Blockchain execution rejected: " . $err->getMessage() . "\n";
                return;
            }
            echo "✅ Trade record settled in block!\n";
            echo "🔗 Transaction Hash (TxHash): " . $txHash . "\n";
        });
    });
});

/**
 * Mock callback function to simulate blockchain logging during offline demos
 */
function simulateBlockchainSettlement($buyer, $seller, $asset, $amount, $price) {
    $txHash = "0x" . bin2hex(random_bytes(32));
    echo "Simulated Smart Contract Trade Settlement Record:\n";
    echo "  [Web3 Node Status]: MOCKED\n";
    echo "  [Smart Contract]: TradeContract.sol\n";
    echo "  [Parameters]:\n";
    echo "     - Buyer: {$buyer}\n";
    echo "     - Seller: {$seller}\n";
    echo "     - Asset: {$asset}\n";
    echo "     - Volume: {$amount} oz\n";
    echo "     - Execution Price: \$" . number_format($price, 2) . " USDT\n";
    echo "  [Status]: Immutably Settled\n";
    echo "  [Blockchain TxHash]: {$txHash}\n";
}
