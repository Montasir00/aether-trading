/**
 * web3_trade.js — Aether Platform MetaMask & Ganache Integration
 * ───────────────────────────────────────────────────────────────
 * Handles:
 *   1. Detecting & connecting MetaMask
 *   2. Showing the user's Ganache ETH balance in the UI
 *   3. Executing a BUY trade by sending ETH through the AetherTrade smart contract
 *   4. POSTing the txHash to confirm_trade.php for backend verification
 *   5. Updating the UI balance after each trade
 *
 * This script is loaded by buy_sell_form.php via a <script> tag.
 * It reads CONTRACT_ADDRESS and CONTRACT_ABI from window.AETHER_BLOCKCHAIN
 * which is injected by PHP (so secrets stay server-side).
 */

// ─── Global State ─────────────────────────────────────────────────────────────
let web3Instance    = null;
let connectedAccount = null; // The user's MetaMask address (0x...)
let aetherContract  = null;  // The deployed smart contract instance

// ─── 1. DETECT METAMASK ───────────────────────────────────────────────────────
function isMetaMaskInstalled() {
    return typeof window.ethereum !== 'undefined' && window.ethereum.isMetaMask;
}

// ─── 2. CONNECT WALLET ────────────────────────────────────────────────────────
/**
 * Prompts MetaMask to connect, stores the account, and updates the UI panel.
 * Called when the user clicks the "Connect MetaMask" button.
 */
async function connectMetaMask() {
    if (!isMetaMaskInstalled()) {
        showEthMessage(
            '❌ MetaMask not detected. Please install MetaMask and try again.',
            false
        );
        return;
    }

    try {
        updateConnectButton('Connecting…', true);

        // Ask MetaMask for the user's accounts
        const accounts = await window.ethereum.request({ method: 'eth_requestAccounts' });
        connectedAccount = accounts[0];

        // Make sure we are on the correct Ganache network (Chain ID 1337)
        await ensureGanacheNetwork();

        // Initialize Web3 using MetaMask as the provider
        web3Instance = new Web3(window.ethereum);

        // Build the contract instance
        const config = window.AETHER_BLOCKCHAIN || {};
        if (config.contractAddress && config.contractABI) {
            aetherContract = new web3Instance.eth.Contract(
                config.contractABI,
                config.contractAddress
            );
        }

        // Update UI
        updateConnectButton('✅ Connected', false);
        updateWalletPanel(connectedAccount);

        // Save the address to session via PHP
        await saveEthAddressToSession(connectedAccount);

        // Fetch and display the ETH balance from Ganache
        await refreshEthBalance();

        // Listen for account changes in MetaMask
        window.ethereum.on('accountsChanged', handleAccountChange);
        window.ethereum.on('chainChanged',    () => window.location.reload());

        showEthMessage('✅ Wallet connected! You can now trade with ETH.', true);

    } catch (err) {
        if (err.code === 4001) {
            showEthMessage('⚠️ Connection rejected. Please click "Connect MetaMask" and approve.', false);
        } else {
            showEthMessage('❌ Connection error: ' + err.message, false);
        }
        updateConnectButton('Connect MetaMask', false);
    }
}

// ─── 3. ENSURE GANACHE NETWORK ────────────────────────────────────────────────
/**
 * Checks that MetaMask is connected to the Ganache Local network (chainId 1337).
 * Prompts the user to switch if they are on a different network (e.g. Ethereum Mainnet).
 */
async function ensureGanacheNetwork() {
    const chainId = await window.ethereum.request({ method: 'eth_chainId' });
    const ganacheChainId = '0x539'; // 1337 in hex

    if (chainId !== ganacheChainId) {
        try {
            // Try to switch to Ganache network
            await window.ethereum.request({
                method: 'wallet_switchEthereumChain',
                params: [{ chainId: ganacheChainId }]
            });
        } catch (switchError) {
            if (switchError.code === 4902) {
                // Network not in MetaMask yet — add it automatically
                await window.ethereum.request({
                    method: 'wallet_addEthereumChain',
                    params: [{
                        chainId:         ganacheChainId,
                        chainName:       'Ganache Local',
                        nativeCurrency:  { name: 'Ether', symbol: 'ETH', decimals: 18 },
                        rpcUrls:         ['http://127.0.0.1:7545'],
                        blockExplorerUrls: null
                    }]
                });
            } else {
                throw switchError;
            }
        }
    }
}

// ─── 4. EXECUTE BUY TRADE ─────────────────────────────────────────────────────
/**
 * Main trading function — triggered when the user submits the ETH buy form.
 *
 * @param {string} asset     Commodity ticker e.g. "XAU"
 * @param {number} ethAmount Amount of ETH to spend (e.g. 2.5)
 * @param {number} assetQty  Quantity of commodity to purchase (e.g. 0.001 oz)
 */
async function buyWithETH(asset, ethAmount, assetQty) {
    if (!connectedAccount || !web3Instance || !aetherContract) {
        showEthMessage('❌ Please connect MetaMask first!', false);
        return;
    }

    if (ethAmount <= 0 || assetQty <= 0) {
        showEthMessage('❌ Invalid trade values.', false);
        return;
    }

    try {
        setBuyEthButton('Waiting for MetaMask…', true);

        // Generate a unique trade ID (timestamp-based — backend also validates)
        const tradeId = Date.now();

        // Scale asset quantity to avoid Solidity decimals (×1e6)
        const assetQtyScaled = Math.round(assetQty * 1_000_000);

        // Convert ETH amount to Wei
        const weiAmount = web3Instance.utils.toWei(ethAmount.toString(), 'ether');

        showEthMessage('⏳ Confirm the transaction in MetaMask…', true);

        // Call the smart contract's buyAsset() function
        // MetaMask will pop up asking for confirmation
        const tx = await aetherContract.methods
            .buyAsset(tradeId, asset, assetQtyScaled)
            .send({ from: connectedAccount, value: weiAmount });

        const txHash = tx.transactionHash;
        showEthMessage(`⛏️ Transaction mined! TxHash: ${txHash.slice(0, 20)}… — Confirming…`, true);

        // POST the txHash to the PHP backend for verification + DB credit
        const result = await confirmWithBackend(txHash, asset, assetQty);

        if (result.success) {
            showEthMessage(
                `✅ Trade confirmed on-chain! ${assetQty} ${asset} added to your account. TxHash: ${txHash.slice(0,20)}…`,
                true
            );
            // Refresh the ETH balance display
            await refreshEthBalance();
            // Trigger the main wallet refresh on the page (defined in buy_sell_form.php)
            if (typeof fetchWallets === 'function') fetchWallets();
            if (typeof fetchTransactions === 'function') fetchTransactions();
        } else {
            showEthMessage('❌ Backend error: ' + (result.error || 'Settlement failed'), false);
        }

    } catch (err) {
        if (err.code === 4001) {
            showEthMessage('⚠️ Transaction rejected by user.', false);
        } else {
            showEthMessage('❌ Trade failed: ' + (err.message || err), false);
        }
    } finally {
        setBuyEthButton('Buy with ETH', false);
    }
}

// ─── 5. BACKEND CONFIRMATION ──────────────────────────────────────────────────
/**
 * POSTs the transaction hash to confirm_trade.php for on-chain verification
 * and MySQL credit.
 */
async function confirmWithBackend(txHash, asset, qty) {
    const formData = new URLSearchParams({
        txHash:      txHash,
        asset:       asset,
        qty:         qty.toString(),
        eth_address: connectedAccount
    });

    const response = await fetch('blockchain/confirm_trade.php', {
        method:      'POST',
        credentials: 'same-origin',
        headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:        formData.toString()
    });

    if (!response.ok) {
        return { success: false, error: `HTTP ${response.status}` };
    }
    // Guard against HTML error pages
    const contentType = response.headers.get('Content-Type') || '';
    if (!contentType.includes('json')) {
        const text = await response.text();
        console.error('confirm_trade returned non-JSON:', text);
        return { success: false, error: 'Server returned invalid response (check logs)' };
    }
    return await response.json();
}

// ─── 6. ETH BALANCE REFRESH ───────────────────────────────────────────────────
/**
 * Fetches the user's current Ganache ETH balance from the PHP backend
 * and updates the ETH balance display in the wallet panel.
 */
async function refreshEthBalance() {
    // Attempt to read directly from MetaMask first for instant real-time updates
    if (web3Instance && connectedAccount) {
        try {
            const wei = await web3Instance.eth.getBalance(connectedAccount);
            const eth = web3Instance.utils.fromWei(wei, 'ether');
            const balEl = document.getElementById('eth-balance-display');
            if (balEl) {
                balEl.textContent = parseFloat(eth).toFixed(4) + ' ETH';
                balEl.style.color = 'var(--accent)';
            }
            return;
        } catch (err) {
            console.error('Error fetching balance directly from MetaMask:', err);
        }
    }

    try {
        const res  = await fetch('blockchain/get_eth_balance.php', { credentials: 'same-origin' });
        const data = await res.json();

        const balEl = document.getElementById('eth-balance-display');
        if (balEl) {
            if (data.success) {
                balEl.textContent = parseFloat(data.balance).toFixed(4) + ' ETH';
                balEl.style.color = 'var(--accent)';
            } else {
                balEl.textContent = '—';
            }
        }
    } catch (_) {
        // Silently fail — Ganache may not be reachable
    }
}

// ─── 7. SESSION ADDRESS SAVE ──────────────────────────────────────────────────
async function saveEthAddressToSession(address) {
    try {
        await fetch('blockchain/save_eth_address.php', {
            method:      'POST',
            credentials: 'same-origin',
            headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:        'eth_address=' + encodeURIComponent(address)
        });
    } catch (_) {}
}

// ─── 8. ACCOUNT CHANGE HANDLER ────────────────────────────────────────────────
function handleAccountChange(accounts) {
    if (accounts.length === 0) {
        connectedAccount = null;
        updateConnectButton('Connect MetaMask', false);
        showEthMessage('⚠️ Wallet disconnected.', false);
    } else {
        connectedAccount = accounts[0];
        updateWalletPanel(connectedAccount);
        refreshEthBalance();
    }
}

// ─── 9. UI HELPERS ────────────────────────────────────────────────────────────
function updateConnectButton(text, disabled) {
    const btn = document.getElementById('btn-connect-metamask');
    if (btn) {
        btn.textContent = text;
        btn.disabled    = disabled;
    }
}

function setBuyEthButton(text, disabled) {
    const btn = document.getElementById('btn-buy-eth');
    if (btn) {
        btn.textContent = text;
        btn.disabled    = disabled;
    }
}

function updateWalletPanel(address) {
    const addrEl = document.getElementById('eth-address-display');
    if (addrEl) {
        addrEl.textContent = address
            ? address.slice(0, 6) + '…' + address.slice(-4)
            : '—';
    }

    const badge = document.getElementById('form-connection-badge');
    if (badge) {
        badge.textContent = address ? 'Connected' : 'Disconnected';
        badge.style.color = address ? 'var(--green, #10b981)' : 'var(--red, #ef4444)';
    }

    const panel = document.getElementById('form-wallet-details');
    if (panel) panel.style.display = address ? 'block' : 'none';

    const connectBtn = document.getElementById('btn-connect-metamask');
    if (connectBtn) connectBtn.style.display = address ? 'none' : 'block';
}

function showEthMessage(msg, isSuccess) {
    const el = document.getElementById('eth-message') || document.getElementById('form-message');
    if (!el) return;
    el.style.display    = 'block';
    el.textContent      = msg;
    el.style.background = isSuccess ? 'var(--green-bg, rgba(16,185,129,0.1))' : 'var(--red-bg, rgba(239,68,68,0.1))';
    el.style.color      = isSuccess ? 'var(--green, #10b981)'                  : 'var(--red, #ef4444)';
    el.style.border     = isSuccess ? '1px solid rgba(16,185,129,0.3)'          : '1px solid rgba(239,68,68,0.3)';
    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// ─── 11. EXECUTE SELL TRADE ───────────────────────────────────────────────────
async function sellWithETH(asset, assetQty) {
    if (!connectedAccount) {
        showEthMessage('❌ Please connect MetaMask first!', false);
        return;
    }
    if (assetQty <= 0) {
        showEthMessage('❌ Invalid quantity.', false);
        return;
    }

    try {
        setBuyEthButton('Settling on-chain…', true);
        showEthMessage('⏳ Initiating on-chain sell settlement…', true);

        const formData = new URLSearchParams({
            asset:       asset,
            qty:         assetQty.toString(),
            eth_address: connectedAccount  // Send so backend can update DB if missing
        });

        const response = await fetch('blockchain/sell_settle.php', {
            method:      'POST',
            credentials: 'same-origin',
            headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:        formData.toString()
        });

        // Guard against HTML error pages (display_errors leak)
        const contentType = response.headers.get('Content-Type') || '';
        if (!response.ok || !contentType.includes('json')) {
            const text = await response.text();
            console.error('Sell endpoint returned non-JSON:', text);
            showEthMessage('❌ Server error during sell settlement. Check logs.', false);
            return;
        }

        const result = await response.json();

        if (result.success) {
            showEthMessage(
                `✅ Sell order filled! Released ${parseFloat(result.eth_released || 0).toFixed(6)} ETH to your wallet. TxHash: ${result.txHash.slice(0,20)}…`,
                true
            );
            await refreshEthBalance();
            if (typeof fetchWallets === 'function') fetchWallets();
            if (typeof fetchTransactions === 'function') fetchTransactions();
        } else {
            showEthMessage('❌ Settlement error: ' + (result.error || 'Failed'), false);
        }
    } catch (err) {
        showEthMessage('❌ Sell failed: ' + err.message, false);
    } finally {
        setBuyEthButton('Submit Order', false);
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// LIMIT ORDER FUNCTIONS (Hybrid DEX)
// ═══════════════════════════════════════════════════════════════════════════════

// ─── L1. SIGN LIMIT ORDER (EIP-712 — Free, Zero Gas) ─────────────────────────
/**
 * Asks MetaMask to sign an EIP-712 typed data message authorizing a limit order.
 * This is FREE — no ETH is sent and no gas is consumed. MetaMask just signs the data.
 *
 * @param {string} asset       Commodity ticker e.g. "XAU"
 * @param {number} qty         Quantity in troy oz
 * @param {number} limitPrice  The limit price in USD
 * @param {string} side        "BUY" or "SELL"
 * @param {number} orderId     The MySQL order ID assigned by the backend
 * @returns {string}           The EIP-712 signature string (0x...)
 */
async function signLimitOrder(asset, qty, limitPrice, side, orderId) {
    if (!connectedAccount || !web3Instance) {
        throw new Error('MetaMask not connected');
    }

    // EIP-712 Domain — ties the signature to this specific contract on this chain
    const domain = {
        name:              'AetherTrade',
        version:           '1',
        chainId:           1337,  // Ganache Local
        verifyingContract: (window.AETHER_BLOCKCHAIN || {}).contractAddress || ''
    };

    // EIP-712 Types — describes the structure of the signed message
    const types = {
        LimitOrder: [
            { name: 'orderId',    type: 'uint256' },
            { name: 'asset',      type: 'string'  },
            { name: 'side',       type: 'string'  },
            { name: 'qty',        type: 'uint256' }, // qty × 1e6 to avoid decimals
            { name: 'limitPrice', type: 'uint256' }, // price × 100 (cents)
            { name: 'trader',     type: 'address' }
        ]
    };

    // The actual message values being signed
    const message = {
        orderId:    orderId,
        asset:      asset,
        side:       side,
        qty:        Math.round(qty * 1_000_000),         // scale to avoid Solidity decimals
        limitPrice: Math.round(limitPrice * 100),        // store as cents (integer)
        trader:     connectedAccount
    };

    // Build the EIP-712 typed data payload
    const typedData = JSON.stringify({
        types: {
            EIP712Domain: [
                { name: 'name',              type: 'string'  },
                { name: 'version',           type: 'string'  },
                { name: 'chainId',           type: 'uint256' },
                { name: 'verifyingContract', type: 'address' }
            ],
            LimitOrder: types.LimitOrder
        },
        primaryType: 'LimitOrder',
        domain:      domain,
        message:     message
    });

    // MetaMask pops up: "Sign this message?" — user clicks Sign (not Confirm, no gas)
    const signature = await window.ethereum.request({
        method: 'eth_signTypedData_v4',
        params: [connectedAccount, typedData]
    });

    return signature;
}

// ─── L2. ESCROW ETH FOR LIMIT BUY (One Gas Payment) ──────────────────────────
/**
 * Locks the user's ETH inside the AetherTrade smart contract.
 * MetaMask pops up asking the user to confirm the ETH transfer INTO the contract.
 * The ETH stays locked until: (a) a matching sell is found and settled, or (b) cancelled.
 *
 * @param {number} orderId    The MySQL order ID (must match what was signed)
 * @param {number} ethAmount  The ETH amount to lock (as a float, e.g. 1.5)
 * @returns {string}          The on-chain transaction hash of the escrow call
 */
async function escrowETHForLimitBuy(orderId, ethAmount) {
    if (!connectedAccount || !web3Instance || !aetherContract) {
        throw new Error('MetaMask not connected or contract not loaded');
    }

    const weiAmount = web3Instance.utils.toWei(ethAmount.toString(), 'ether');

    showEthMessage('⏳ Confirm ETH escrow in MetaMask…', true);

    // Call contract.escrowETH(orderId) and attach the ETH value
    // MetaMask pops up: "Confirm transaction — send X ETH to AetherTrade contract"
    const tx = await aetherContract.methods
        .escrowETH(orderId)
        .send({ from: connectedAccount, value: weiAmount });

    showEthMessage(`🔒 ETH escrowed on-chain! TxHash: ${tx.transactionHash.slice(0, 20)}…`, true);
    return tx.transactionHash;
}

// ─── L3. CANCEL ON-CHAIN LIMIT ORDER (Automatic ETH Refund) ──────────────────
/**
 * Cancels an open limit order and automatically refunds the escrowed ETH
 * back to the original buyer's MetaMask wallet on-chain.
 *
 * MetaMask pops up for the user to confirm the cancellation transaction.
 * The smart contract's cancelLimitOrder() handles the ETH refund automatically.
 *
 * @param {number} orderId   The MySQL order ID to cancel
 * @returns {string}         The on-chain transaction hash of the cancel call
 */
async function cancelOnChainLimitOrder(orderId) {
    if (!connectedAccount || !web3Instance || !aetherContract) {
        throw new Error('MetaMask not connected or contract not loaded');
    }

    showEthMessage('⏳ Confirm cancellation in MetaMask…', true);

    // Call contract.cancelLimitOrder(orderId)
    // Smart contract validates the caller is the original escrower and refunds ETH
    const tx = await aetherContract.methods
        .cancelLimitOrder(orderId)
        .send({ from: connectedAccount });

    showEthMessage(`↩️ Order cancelled. ETH refunded! TxHash: ${tx.transactionHash.slice(0, 20)}…`, true);
    return tx.transactionHash;
}

// ─── L4. PLACE LIMIT ORDER (Full Flow Orchestrator) ───────────────────────────
/**
 * Full limit order placement flow:
 *   Step 1 — Sign the order via EIP-712 (FREE, no gas)
 *   Step 2 — If BUY: escrow the ETH in the contract (ONE gas payment)
 *   Step 3 — POST signature + escrow tx hash to backend to save the order in MySQL
 *
 * @param {string} asset       Commodity ticker e.g. "XAU"
 * @param {number} qty         Quantity in troy oz
 * @param {number} limitPrice  Limit price in USD
 * @param {string} side        "BUY" or "SELL"
 * @param {number} orderId     The MySQL order ID pre-created by submit_order.php
 */
async function placeLimitOrderOnChain(asset, qty, limitPrice, side, orderId) {
    try {
        showEthMessage('✏️ Step 1/2: Sign your limit order (free, no gas)…', true);

        // Step 1: EIP-712 sign — MetaMask shows "Sign this message?" (no ETH sent)
        const signature = await signLimitOrder(asset, qty, limitPrice, side, orderId);
        showEthMessage('✅ Order signed! Step 2/2: Escrow ETH on-chain…', true);

        let escrowTxHash = null;

        if (side === 'BUY') {
            // Step 2: Escrow ETH — MetaMask shows "Confirm Transaction" (ETH sent to contract)
            const ethRes  = await fetch(`get_market_price.php?coin=ETH`);
            const ethData = await ethRes.json();
            const ethPrice = parseFloat(ethData.price) || 3000;

            const ethAmount = ((limitPrice * qty) / ethPrice).toFixed(18);
            escrowTxHash = await escrowETHForLimitBuy(orderId, ethAmount);
        }

        // Step 3: Notify backend to record signature + escrow hash
        const formData = new URLSearchParams({
            order_id:       orderId,
            eth_signature:  signature,
            escrow_tx_hash: escrowTxHash || '',
            eth_address:    connectedAccount
        });

        const resp = await fetch('blockchain/save_limit_order_onchain.php', {
            method:      'POST',
            credentials: 'same-origin',
            headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:        formData.toString()
        });


        const result = await resp.json();
        if (result.success) {
            showEthMessage(`🏦 Limit ${side} order placed on-chain! Waiting for a match…`, true);
            if (typeof fetchWallets === 'function') fetchWallets();
            if (typeof fetchTransactions === 'function') fetchTransactions();
        } else {
            showEthMessage('❌ Backend error: ' + (result.error || 'Unknown error'), false);
        }
    } catch (err) {
        if (err.code === 4001) {
            showEthMessage('⚠️ Cancelled by user.', false);
        } else {
            showEthMessage('❌ Limit order failed: ' + err.message, false);
        }
    }
}

// ─── 12. AUTO-DETECT METAMASK STATE ON PAGE LOAD ──────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const connectBtn = document.getElementById('btn-connect-metamask');
    if (connectBtn) {
        connectBtn.addEventListener('click', connectMetaMask);
    }

    const buyEthBtn = document.getElementById('btn-buy-eth');
    if (buyEthBtn) {
        buyEthBtn.addEventListener('click', async () => {
            const asset    = document.getElementById('coin')?.value || 'XAU';
            const qty      = parseFloat(document.getElementById('amount')?.value) || 0;
            const ethInput = document.getElementById('eth-amount-input');
            const ethAmt   = parseFloat(ethInput?.value) || 0;
            await buyWithETH(asset, ethAmt, qty);
        });
    }

    // Auto-refresh ETH balance if already connected
    if (isMetaMaskInstalled()) {
        window.ethereum.request({ method: 'eth_accounts' }).then(accounts => {
            if (accounts.length > 0) {
                connectedAccount = accounts[0];
                web3Instance = new Web3(window.ethereum);
                const config = window.AETHER_BLOCKCHAIN || {};
                if (config.contractAddress && config.contractABI) {
                    aetherContract = new web3Instance.eth.Contract(
                        config.contractABI,
                        config.contractAddress
                    );
                }
                updateWalletPanel(connectedAccount);
                refreshEthBalance();
            }
        });
    }
});

