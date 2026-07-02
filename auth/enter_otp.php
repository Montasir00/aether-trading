<?php
session_start();

$otpEmail = $_SESSION['otp_email'] ?? '';
$maskedOtpEmail = '';
if ($otpEmail !== '' && strpos($otpEmail, '@') !== false) {
    [$local, $domain] = explode('@', $otpEmail, 2);
    $maskedLocal = strlen($local) <= 2
        ? str_repeat('*', strlen($local))
        : substr($local, 0, 2) . str_repeat('*', max(strlen($local) - 2, 1));
    $maskedOtpEmail = $maskedLocal . '@' . $domain;
}

$isDevMode = (getenv('APP_ENV') === 'development');
$debugOtp = ($isDevMode && isset($_SESSION['otp_debug'])) ? (string)$_SESSION['otp_debug'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP — Aether</title>
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../global.css">
    <link rel="stylesheet" href="../index.css">
    <style>
        .otp-icon {
            font-size: 3rem;
            margin-bottom: var(--space-md);
            display: block;
            text-align: center;
            filter: drop-shadow(0 0 10px var(--accent-glow));
        }
        .otp-timer {
            text-align: center;
            font-size: 0.85rem;
            color: var(--accent);
            font-weight: 700;
            padding: 10px 20px;
            background: linear-gradient(135deg, rgba(212, 175, 55, 0.1) 0%, rgba(212, 175, 55, 0.03) 100%);
            border: 1px solid var(--border-strong);
            border-radius: var(--radius-md);
            margin-bottom: var(--space-lg);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: inline-block;
            width: 100%;
            box-shadow: var(--shadow-sm);
        }
        .otp-timer.expired {
            color: var(--red);
            background: linear-gradient(135deg, rgba(213, 0, 0, 0.1) 0%, rgba(213, 0, 0, 0.03) 100%);
            border-color: rgba(213, 0, 0, 0.25);
            box-shadow: 0 0 15px rgba(213, 0, 0, 0.05);
        }
        .otp-input {
            text-align: center;
            font-size: 1.75rem !important;
            letter-spacing: 12px;
            font-weight: 800 !important;
            font-family: 'JetBrains Mono', monospace !important;
            color: var(--accent) !important;
            background: var(--bg-secondary) !important;
            border: 1.5px solid var(--border-strong) !important;
            border-radius: var(--radius-md) !important;
            padding: 12px 8px !important;
            transition: border-color var(--duration) var(--ease), box-shadow var(--duration) var(--ease), background-color var(--duration) var(--ease) !important;
        }
        .otp-input:focus {
            border-color: var(--accent) !important;
            box-shadow: 0 0 20px var(--accent-glow-strong) !important;
            background: var(--bg-elevated) !important;
        }
        .resend-section {
            text-align: center;
            margin-top: var(--space-lg);
        }
    </style>
    <script>
        let expiry = <?php echo $_SESSION['otp_expiry'] ?? time(); ?>;
        function countdown() {
            const timer = document.getElementById("timer");
            const now = Math.floor(Date.now() / 1000);
            let secondsLeft = expiry - now;

            if (secondsLeft <= 0) {
                timer.innerText = "OTP has expired";
                timer.classList.add("expired");
                document.getElementById("otp-form").style.display = "none";
                const backBtn = document.createElement('a');
                backBtn.href = '../index.php';
                backBtn.className = 'g-btn g-btn-outline';
                backBtn.style.cssText = 'margin-top:1rem; width:100%; justify-content:center; display:inline-flex;';
                backBtn.textContent = '← Back to Login';
                timer.parentElement.appendChild(backBtn);
                return;
            }

            let min = Math.floor(secondsLeft / 60);
            let sec = secondsLeft % 60;
            timer.innerText = `Expires in ${min}:${sec < 10 ? '0' : ''}${sec}`;
            setTimeout(countdown, 1000);
        }
        window.onload = countdown;
    </script>
</head>
<body>
<div class="g-mesh-glow"></div>
<?php if ($debugOtp !== ''): ?>
<!-- DEV_OTP: <?= htmlspecialchars($debugOtp, ENT_QUOTES, 'UTF-8') ?> -->
<?php endif; ?>

<div class="auth-wrapper">
    <div class="auth-header">
        <div class="auth-logo">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2L2 7l10 5 10-5-10-5z" />
                <path d="M2 17l10 5 10-5" />
                <path d="M2 12l10 5 10-5" />
            </svg>
        </div>
        <span class="g-eyebrow">Security Node</span>
        <h1>Verify Your Identity</h1>
        <p>We sent a 6-digit code to your email</p>
        <?php if ($maskedOtpEmail !== ''): ?>
            <p style="margin-top: 6px; color: var(--text-dim); font-size: 0.85rem;">Sent to <?= htmlspecialchars($maskedOtpEmail) ?></p>
        <?php endif; ?>
    </div>

    <!-- Double-Bezel Auth Card -->
    <div class="g-double-bezel">
      <div class="g-double-bezel-inner">
        <div id="timer" class="otp-timer">Loading...</div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="g-alert g-alert-error">
                <?= htmlspecialchars($_SESSION['error']) ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['flash'])): ?>
            <div class="g-alert g-alert-success">
                <?= htmlspecialchars($_SESSION['flash']) ?>
            </div>
            <?php unset($_SESSION['flash']); ?>
        <?php endif; ?>

        <form id="otp-form" action="verify_otp.php" method="post" class="auth-form">
            <div class="field">
                <label for="otp">Enter OTP Code</label>
                <input type="text" name="otp" id="otp" class="otp-input" placeholder="• • • • • •" maxlength="6" required autocomplete="one-time-code">
            </div>
            <button type="submit" class="g-btn-pill g-btn-pill-primary">
                Verify & Sign In
                <span class="g-btn-icon-circle">
                    <svg aria-hidden="true" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                </span>
            </button>
        </form>

        <div class="resend-section">
            <form action="resend_otp.php" method="post">
                <button type="submit" class="g-btn g-btn-ghost">Didn't receive it? Resend OTP</button>
            </form>
        </div>
      </div>
    </div>
</div>

<script src="../global.js?v=<?php echo filemtime('../global.js'); ?>" defer></script>
</body>
</html>
