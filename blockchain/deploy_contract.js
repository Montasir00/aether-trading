/**
 * deploy_contract.js
 *
 * Standalone Web3 script to deploy AetherTrade.sol to Ganache.
 * Run from the blockchain/ directory: node deploy_contract.js
 *
 * Requires:
 *   - Ganache running on port 7545
 *   - AetherTrade.json artifact in ./build/contracts/ (compiled by truffle compile)
 */

const { Web3 } = require('web3');
const fs   = require('fs');
const path = require('path');

const GANACHE_URL  = 'http://127.0.0.1:7545';
const ARTIFACT     = path.join(__dirname, 'build', 'contracts', 'AetherTrade.json');

async function main() {
    const web3 = new Web3(GANACHE_URL);

    // ── Load compiled artifact ────────────────────────────────────────────────
    if (!fs.existsSync(ARTIFACT)) {
        console.error('❌ AetherTrade.json not found at:', ARTIFACT);
        console.error('   Run first: npx truffle compile (in the blockchain/ directory)');
        process.exit(1);
    }

    const artifact = JSON.parse(fs.readFileSync(ARTIFACT, 'utf8'));
    const abi      = artifact.abi;
    const bytecode = artifact.bytecode;

    if (!bytecode || bytecode === '0x') {
        console.error('❌ Bytecode is empty. Did compilation succeed?');
        process.exit(1);
    }

    // ── Get accounts from Ganache ─────────────────────────────────────────────
    const accounts = await web3.eth.getAccounts();
    if (accounts.length < 2) {
        console.error('❌ Need at least 2 Ganache accounts. Start Ganache first.');
        process.exit(1);
    }

    const deployer       = accounts[0]; // Contract owner
    const exchangeWallet = accounts[1]; // Exchange treasury wallet

    console.log('\n🚀 Deploying AetherTrade...');
    console.log('   Deployer  (Account #1):', deployer);
    console.log('   Exchange  (Account #2):', exchangeWallet);

    // ── Deploy contract ───────────────────────────────────────────────────────
    const contract = new web3.eth.Contract(abi);

    const deployTx = contract.deploy({
        data:      bytecode,
        arguments: [exchangeWallet]
    });

    const gasEstimate = await deployTx.estimateGas({ from: deployer });

    const deployed = await deployTx.send({
        from:     deployer,
        gas:      Math.round(Number(gasEstimate) * 1.5),
        gasPrice: web3.utils.toWei('1', 'gwei')
    });

    const contractAddress = deployed.options.address;

    console.log('\n✅ AetherTrade deployed successfully!');
    console.log('   Contract Address:', contractAddress);
    console.log('   Exchange Wallet: ', exchangeWallet);

    // ── Update the artifact networks section so web3_trade.js can find it ─────
    const chainId = String(await web3.eth.getChainId());
    artifact.networks = artifact.networks || {};
    artifact.networks[chainId] = {
        address:         contractAddress,
        transactionHash: ''
    };
    fs.writeFileSync(ARTIFACT, JSON.stringify(artifact, null, 2));
    console.log(`\n📁 Updated AetherTrade.json with network ${chainId} → ${contractAddress}`);

    // ── Print next steps ──────────────────────────────────────────────────────
    console.log('\n📋 NEXT STEPS:');
    console.log(`   1. Open config.php and set:`);
    console.log(`      define('CONTRACT_ADDRESS', '${contractAddress}');`);
    console.log(`      define('EXCHANGE_WALLET',  '${exchangeWallet}');`);
    console.log(`\n   2. Set EXCHANGE_OWNER_PRIVATE_KEY env var in docker-compose.yml`);
    console.log(`      (Ganache Account #1's private key — click the key icon in Ganache GUI)`);
    console.log(`\n   3. Restart Docker: docker-compose restart\n`);

    process.exit(0);
}

main().catch(err => {
    console.error('❌ Deployment failed:', err.message);
    process.exit(1);
});
