<?php
$host = getenv('DB_HOST') ?: 'db';
$user = getenv('DB_USER') ?: 'app_user';
$password = getenv('DB_PASSWORD') ?: 'app_password';
$dbname = getenv('DB_NAME') ?: 'aether_trading';
$port = (int)(getenv('DB_PORT') ?: 3306);

// When running from host CLI (not inside docker network), service name "db"
// is not resolvable. Fall back to localhost:3307 (docker-compose published port).
if (PHP_SAPI === 'cli' && $host === 'db') {
    $resolved = gethostbyname($host);
    if ($resolved === $host) {
        $host = getenv('DB_HOST_LOCAL') ?: '127.0.0.1';
        $port = (int)(getenv('DB_PORT_LOCAL') ?: 3307);
    }
}

$conn = new mysqli($host, $user, $password, $dbname, $port);

if ($conn->connect_error) {
    error_log("DB connection failed: " . $conn->connect_error);
    die("Service temporarily unavailable.");
}

// ── Blockchain / Ganache Configuration ──────────────────────────────────────
// GANACHE_RPC_URL: Use host.docker.internal when PHP runs inside Docker
//                  Use 127.0.0.1 if PHP runs directly on host
define('GANACHE_RPC_URL',  getenv('GANACHE_RPC_URL')  ?: 'http://host.docker.internal:7545');

// CONTRACT_ADDRESS: Set CONTRACT_ADDRESS in your .env (printed by `truffle migrate`)
// Example: 0x5FbDB2315678afecb367f032d93F642f64180aa3
define('CONTRACT_ADDRESS', getenv('CONTRACT_ADDRESS') ?: '');

// EXCHANGE_WALLET: Set EXCHANGE_WALLET in your .env (copy from the Ganache GUI ACCOUNTS list)
define('EXCHANGE_WALLET',  getenv('EXCHANGE_WALLET')  ?: '');
?>
