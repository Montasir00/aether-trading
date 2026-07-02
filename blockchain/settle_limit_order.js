/**
 * settle_limit_order.js
 *
 * Node.js bridge script called by the PHP MatchingEngine to settle a matched
 * on-chain limit order pair. It calls AetherTrade.sol's settleLimitOrder()
 * using the exchange owner's Ganache private key.
 *
 * Usage (called by PHP via exec()):
 *   node settle_limit_order.js <buyOrderId> <sellOrderId> <sellerWallet> <asset>
 *
 * Output (stdout, JSON):
 *   {"txHash":"0x..."} on success
 *   {"error":"..."}    on failure
 *
 * Exit code: 0 = success, 1 = failure
 */

const Web3 = require('web3');
const fs   = require('fs');
const path = require('path');

// ── Parse CLI arguments ────────────────────────────────────────────────────────
const [buyOrderId, sellOrderId, sellerWallet, asset] = process.argv.slice(2);

if (!buyOrderId || !sellOrderId || !sellerWallet || !asset) {
    process.stdout.write(JSON.stringify({ error: 'Missing arguments: buyOrderId sellOrderId sellerWallet asset' }));
    process.exit(1);
}

// ── Load contract config (same file used by web3_trade.js on the frontend) ────
// The contract address and ABI are written to blockchain/config.js by Truffle.
// We read the ABI from the compiled artifact directly.
const artifactPath = path.join(__dirname, 'build', 'contracts', 'AetherTrade.json');
if (!fs.existsSync(artifactPath)) {
    process.stdout.write(JSON.stringify({ error: `Contract artifact not found at ${artifactPath}. Run: truffle migrate` }));
    process.exit(1);
}

const artifact        = JSON.parse(fs.readFileSync(artifactPath, 'utf8'));
const contractABI     = artifact.abi;
const networkId       = Object.keys(artifact.networks || {}).pop();  // Use the latest deployed network
const contractAddress = artifact.networks?.[networkId]?.address;

if (!contractAddress) {
    process.stdout.write(JSON.stringify({ error: 'Contract not deployed. Run: truffle migrate' }));
    process.exit(1);
}

// ── Load exchange owner private key from environment ──────────────────────────
// The EXCHANGE_OWNER_PRIVATE_KEY environment variable must be set to
// Ganache Account #1's private key (the contract deployer/owner).
// In Docker: set in docker-compose.yml or .env
const ownerPrivateKey = process.env.EXCHANGE_OWNER_PRIVATE_KEY;
if (!ownerPrivateKey) {
    process.stdout.write(JSON.stringify({ error: 'EXCHANGE_OWNER_PRIVATE_KEY environment variable not set' }));
    process.exit(1);
}

// ── Connect to Ganache ────────────────────────────────────────────────────────
// Prioritize GANACHE_RPC_URL from PHP/compose, then GANACHE_RPC, then Docker fallback
const ganacheRPC = process.env.GANACHE_RPC_URL || process.env.GANACHE_RPC || 'http://host.docker.internal:7545';

const web3       = new Web3(ganacheRPC);

(async () => {
    try {
        // Import the owner's account from its private key
        const ownerAccount = web3.eth.accounts.privateKeyToAccount(ownerPrivateKey);
        web3.eth.accounts.wallet.add(ownerAccount);

        const contract = new web3.eth.Contract(contractABI, contractAddress);

        // Call settleLimitOrder(buyOrderId, sellOrderId, sellerWallet, asset)
        // This is called by the owner/exchange — no value is sent (ETH was pre-escrowed by buyer)
        const gasEstimate = await contract.methods
            .settleLimitOrder(
                parseInt(buyOrderId),
                parseInt(sellOrderId),
                sellerWallet,
                asset
            )
            .estimateGas({ from: ownerAccount.address });

        const tx = await contract.methods
            .settleLimitOrder(
                parseInt(buyOrderId),
                parseInt(sellOrderId),
                sellerWallet,
                asset
            )
            .send({
                from:     ownerAccount.address,
                gas:      Math.round(gasEstimate * 1.2),   // 20% buffer
                gasPrice: web3.utils.toWei('1', 'gwei')    // Low gas for Ganache
            });

        process.stdout.write(JSON.stringify({ txHash: tx.transactionHash }));
        process.exit(0);

    } catch (err) {
        process.stdout.write(JSON.stringify({ error: err.message }));
        process.exit(1);
    }
})();
