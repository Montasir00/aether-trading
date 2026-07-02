/**
 * 1_initial_migration.js — intentionally empty (no-op).
 * We skip the standard Migrations contract because Truffle's built-in
 * Migrations.sol is incompatible with Ganache's MERGE hardfork setting.
 * AetherTrade is deployed directly in 2_deploy_contracts.js.
 */
module.exports = function (deployer) {
  // intentionally empty — no Migrations contract needed
};
