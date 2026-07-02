const hre = require("hardhat");

async function main() {
  const args = process.argv.slice(2);
  if (args.length < 4) {
    console.error("Usage: npx hardhat run scripts/release_funds.js --network ganache <tradeId> <sellerAddress> <asset> <ethAmountInWei>");
    process.exit(1);
  }

  const tradeId = args[0];
  const sellerAddress = args[1];
  const asset = args[2];
  const ethAmount = args[3];

  const contractAddress = "0xbaab207986a6F9545522BC3D890ECc9de0748f21";
  
  // Get the contract instance
  const AetherTrade = await hre.ethers.getContractAt("AetherTrade", contractAddress);

  // Call releaseFunds
  const tx = await AetherTrade.releaseFunds(tradeId, sellerAddress, asset, {
    value: ethAmount
  });

  await tx.wait();
  console.log(tx.hash);
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
