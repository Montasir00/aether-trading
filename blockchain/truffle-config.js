/**
 * Truffle Configuration — Aether Trading Platform
 * ─────────────────────────────────────────────────
 * This connects Truffle (the Solidity compiler & deployer) to your
 * local Ganache blockchain running on port 7545.
 *
 * Run: truffle migrate --network development
 */
module.exports = {
  networks: {
    development: {
      host:       "127.0.0.1", // Ganache GUI default host
      port:       7545,         // Ganache GUI default port (NOT 8545 — that's CLI)
      network_id: "*",          // Match any Ganache network ID
      hardfork:   "merge",      // Must match Ganache GUI's HARDFORK setting (shown in header)
    },
  },

  // Configure your Solidity compiler version.
  compilers: {
    solc: {
      version: "0.8.21", // Must match pragma statement in AetherTrade.sol
      settings: {
        optimizer: {
          enabled: true,
          runs: 200,
        },
        // "paris" prevents the push0 opcode (introduced in Shanghai/0.8.20)
        // which Ganache GUI does not support. Without this, deployment fails
        // with "invalid opcode".
        evmVersion: "paris",
      },
    },
  },

};
