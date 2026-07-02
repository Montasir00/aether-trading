# Aether Precious Metals Trading & Matching Platform

Aether is a modular, high-performance web-based platform for trading tokenized commodities (XAU/Gold and XAG/Silver). It integrates a PHP web application, a local Ethereum blockchain (via Truffle/Hardhat & Ganache), a Redis-backed Order Matching Engine, and an AI-driven timeseries forecasting system.

---

## 🏗️ Architecture & Project Structure

The project has been refactored into a modular layout to simplify the root directory and separate concerns:

```
├── auth/                       # User authentication, registration, & OTP verification
├── alerts/                     # Price alert core scheduler, triggers, and notification dispatchers
├── orders/                     # Order submission, limit order book UI, and user order logs
├── trading_engine/             # Automated Strategy Controller, Strategy Engine, and Risk Manager
├── matching_engine/            # Order matching engine core and background match daemon
├── api/                        # Backend data providers (balance, market tickers, news feed, risk metrics)
├── blockchain/                 # Truffle/Hardhat smart contract configurations, deployment, and Web3 scripts
│   ├── contracts/              # AetherTrade.sol Solidity contract
│   ├── build/                  # Compiled Solidity contract ABIs
│   └── hardhat.config.js       # Hardhat network environment config
├── scripts/                    # CLI testing utilities & simulators (market price feed, order simulator)
├── config.php                  # Central database, RPC & contract settings (reads from environment)
├── navbar.php                  # Dynamically resolving directory-aware navigation header
├── docker-compose.yml          # Container configuration for web, db, phpmyadmin, redis, and matching daemon
├── .env.example                # Template for environment configuration
└── schema.sql                  # Database migration schema
```

---

## ⚡ Features

1. **Modular Auth System:** Dynamic login, registration, and session security using Email-based OTP authentication (with debug fallback in dev mode).
2. **Order Matching Engine:** High-performance, order book matching engine driven by MySQL and a background Redis-backed Matcher.
3. **Smart Contract Escrow:** Local blockchain network deployment (Ganache) that locks and settles assets securely on-chain.
4. **Commodity Tickers:** Local caching of market prices for gold (`XAUUSDT`) and silver (`XAGUSDT`).
5. **AI Timeseries Forecasting:** Integrates TimesFM forecasting simulation with confidence bands and accuracy logging.
6. **Risk Management Guardrails:** Enforces maximum trade percentage limits, daily trade count, maximum volume check, and drawdown locking.
7. **Price Alert Dispatcher:** Local alerts reaper engine matching real-time market thresholds to send notifications.

---

## 🚀 Getting Started

### 1. Prerequisites
Ensure you have the following installed on your system:
- **Docker Desktop** (Required for containerized runtime)
- **Ganache GUI** or Ganache CLI (for local blockchain emulator)
- **MetaMask browser extension** (configured to connect to local Ganache RPC)

### 2. Environment Configuration
Create a `.env` file in the project root:
```bash
cp .env.example .env
```
Open `.env` and fill in:
- `EXCHANGE_OWNER_PRIVATE_KEY`: Your Ganache Account #0 private key.
- `CONTRACT_ADDRESS`: The deployed address of `AetherTrade` once compiled/deployed.
- `EXCHANGE_WALLET`: Your Ganache Account #2 address (used as the exchange treasury).
- `SMTP_USER` / `SMTP_PASS`: For sending login & alert emails.

### 3. Run with Docker Compose
To launch the full ecosystem:
```bash
docker compose up --build
```
This starts the following services:
* **Web App:** `http://localhost:8080` (Aether Web application)
* **phpMyAdmin:** `http://localhost:8081` (Database GUI)
* **MySQL:** Exposed on host port `3307`
* **Redis:** Exposed on port `6379`
* **Matching Daemon:** Automatic background matching daemon

### 4. Smart Contract Deployment
1. Start Ganache GUI and note the RPC URL (typically `http://127.0.0.1:7545` with chain ID `1337`).
2. Run Truffle migration or deploy using Hardhat inside the `blockchain/` folder:
   ```bash
   cd blockchain
   npm install
   npx truffle migrate --network development
   ```
3. Copy the deployed contract address and paste it into your `.env` file under `CONTRACT_ADDRESS`.
4. Restart your Docker containers:
   ```bash
   docker compose down
   docker compose up
   ```

---

## 🛡️ Security Guidelines

* **Never Commit `.env`:** The `.gitignore` is configured to prevent your secret credentials and private keys from going to public repositories.
* **Ganache Keys Only:** Only ever use local Ganache development private keys inside `EXCHANGE_OWNER_PRIVATE_KEY`. Never use a real Ethereum mainnet account.
* **SMTP Settings:** To run locally without SMTP, set `APP_ENV=development` in your `.env` to dump OTP verification codes directly onto your browser console/session debug badge.
