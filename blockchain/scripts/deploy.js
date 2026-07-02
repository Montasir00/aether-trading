const hre = require("hardhat");

async function main() {
  const accounts = await hre.ethers.getSigners();
  const deployer = accounts[0]; // Account #1 — your MetaMask wallet

  // Account #2 from Ganache GUI (the exchange treasury wallet)
  // This is the second address shown in Ganache's ACCOUNTS tab
  const exchangeAddress = "0xCd6A4E43c33CE1b6a2E2fc377776fCf19d1390Bc";

  console.log("=".repeat(60));
  console.log("Deploying AetherTrade to Ganache...");
  console.log("=".repeat(60));
  console.log("  Deployer  (Account #1):", deployer.address);
  console.log("  Exchange  (Account #2):", exchangeAddress);

  const AetherTrade = await hre.ethers.getContractFactory("AetherTrade");
  const contract    = await AetherTrade.deploy(exchangeAddress);

  await contract.waitForDeployment();

  const contractAddress = await contract.getAddress();

  console.log("\n" + "=".repeat(60));
  console.log("✅  AetherTrade deployed successfully!");
  console.log("=".repeat(60));
  console.log("\n  CONTRACT_ADDRESS:", contractAddress);
  console.log("  EXCHANGE_WALLET: ", exchangeAddress);
  console.log("\n  ─── ACTION REQUIRED ───────────────────────────────────");
  console.log("  Open config.php and set:");
  console.log("    define('CONTRACT_ADDRESS', '" + contractAddress + "');");
  console.log("    define('EXCHANGE_WALLET',  '" + exchangeAddress  + "');");
  console.log("  ───────────────────────────────────────────────────────\n");
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
