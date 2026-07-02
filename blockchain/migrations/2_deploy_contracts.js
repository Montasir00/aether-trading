const AetherTrade = artifacts.require("AetherTrade");

module.exports = async function (deployer, network, accounts) {
  /**
   * accounts[0] = The account that deploys the contract (Ganache Account #1 — your MetaMask wallet)
   * accounts[1] = The "exchange" wallet that collects ETH on buy orders (Ganache Account #2)
   *
   * In Ganache GUI, these are the first two rows in the ACCOUNTS tab.
   * Both start with 100 ETH.
   *
   * IMPORTANT: After deploying, copy the contract address printed in the terminal
   *            and paste it into config.php → CONTRACT_ADDRESS constant.
   */
  const exchangeWallet = accounts[1]; // Ganache Account #2 acts as the exchange treasury

  console.log("Deploying AetherTrade contract...");
  console.log("  Deployer  (Account #1):", accounts[0]);
  console.log("  Exchange  (Account #2):", exchangeWallet);

  await deployer.deploy(AetherTrade, exchangeWallet);

  const deployed = await AetherTrade.deployed();
  console.log("\n✅ AetherTrade deployed at:", deployed.address);
  console.log("   → Copy this address into config.php → CONTRACT_ADDRESS");
  console.log("   → Copy", exchangeWallet, "into config.php → EXCHANGE_WALLET");
};
