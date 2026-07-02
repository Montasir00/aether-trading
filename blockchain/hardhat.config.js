require("@nomicfoundation/hardhat-toolbox");
require("dotenv").config({ path: "../.env" }); // load from project root .env

/** @type import('hardhat/config').HardhatUserConfig */
module.exports = {
  solidity: {
    version: "0.8.21",
    settings: {
      optimizer: {
        enabled: true,
        runs: 200,
      },
    },
  },
  networks: {
    ganache: {
      url:     "http://127.0.0.1:7545",
      chainId: 1337,
      // Key is read from .env (EXCHANGE_OWNER_PRIVATE_KEY). Never hardcode it here.
      accounts: process.env.EXCHANGE_OWNER_PRIVATE_KEY
        ? [process.env.EXCHANGE_OWNER_PRIVATE_KEY]
        : [],
    },
  },
};

