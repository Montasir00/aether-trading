<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aether — Premium Trading Platform</title>
    <meta name="description" content="Aether is a premium commodities trading platform featuring live charts, instant trades, and robust risk management.">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="global.css">
    <link rel="stylesheet" href="index.css">
</head>
<body>
<div class="g-mesh-glow"></div>
<div id="splash-screen" class="splash-screen">
    <div class="splash-logo">
        <svg aria-hidden="true" width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 2L2 7l10 5 10-5-10-5z" />
            <path d="M2 17l10 5 10-5" />
            <path d="M2 12l10 5 10-5" />
        </svg>
    </div>
    <div class="splash-text">Establishing Secure Node Connection...</div>
</div>

<div class="login-split">

    <!-- ── Left: Live Prices Panel ── -->
    <aside class="login-prices-panel" aria-label="Live market prices">
        <div class="prices-panel-inner">

            <div class="prices-panel-brand">
                <div class="prices-brand-logo">
                    <svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                        <path d="M2 17l10 5 10-5"/>
                        <path d="M2 12l10 5 10-5"/>
                    </svg>
                </div>
                <span class="prices-brand-name">Aether</span>
            </div>

            <div class="prices-panel-head">
                <span class="prices-status-dot"></span>
                <span class="prices-status-label">Live Markets</span>
            </div>

            <div class="prices-panel-heading">
                <h2 class="prices-panel-title">Today's<br>Market Pulse</h2>
                <p class="prices-panel-sub">Real-time data across crypto &amp; precious metals.</p>
            </div>

            <div class="ticker-list" id="tickerList">
                <!-- BTC -->
                <div class="ticker-item">
                    <div class="ticker-left">
                        <div class="ticker-icon ticker-icon-btc">₿</div>
                        <div class="ticker-meta">
                            <span class="ticker-name">Bitcoin</span>
                            <span class="ticker-symbol">BTC / USDT</span>
                        </div>
                    </div>
                    <div class="ticker-right">
                        <div class="ticker-price" id="btc-price"><span class="tick-skeleton"></span></div>
                        <div class="ticker-change" id="btc-change">—</div>
                    </div>
                </div>
                <!-- ETH -->
                <div class="ticker-item">
                    <div class="ticker-left">
                        <div class="ticker-icon ticker-icon-eth">Ξ</div>
                        <div class="ticker-meta">
                            <span class="ticker-name">Ethereum</span>
                            <span class="ticker-symbol">ETH / USDT</span>
                        </div>
                    </div>
                    <div class="ticker-right">
                        <div class="ticker-price" id="eth-price"><span class="tick-skeleton"></span></div>
                        <div class="ticker-change" id="eth-change">—</div>
                    </div>
                </div>
                <!-- XAU Gold -->
                <div class="ticker-item">
                    <div class="ticker-left">
                        <div class="ticker-icon ticker-icon-xau">Au</div>
                        <div class="ticker-meta">
                            <span class="ticker-name">Gold</span>
                            <span class="ticker-symbol">XAU / USDT</span>
                        </div>
                    </div>
                    <div class="ticker-right">
                        <div class="ticker-price" id="xau-price"><span class="tick-skeleton"></span></div>
                        <div class="ticker-change" id="xau-change">troy oz</div>
                    </div>
                </div>
                <!-- XAG Silver -->
                <div class="ticker-item">
                    <div class="ticker-left">
                        <div class="ticker-icon ticker-icon-xag">Ag</div>
                        <div class="ticker-meta">
                            <span class="ticker-name">Silver</span>
                            <span class="ticker-symbol">XAG / USDT</span>
                        </div>
                    </div>
                    <div class="ticker-right">
                        <div class="ticker-price" id="xag-price"><span class="tick-skeleton"></span></div>
                        <div class="ticker-change" id="xag-change">troy oz</div>
                    </div>
                </div>
            </div>

            <div class="prices-panel-footer">
                <span>Auto-refreshes every 30s</span>
                <span id="last-refresh-time">—</span>
            </div>
        </div>
    </aside>

    <!-- ── Right: Login / Register Form Panel ── -->
    <main id="main-content" class="login-form-panel">
        <div class="login-form-inner">

            <div class="auth-header">
                <div class="auth-logo">
                    <svg aria-hidden="true" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2L2 7l10 5 10-5-10-5z" />
                        <path d="M2 17l10 5 10-5" />
                        <path d="M2 12l10 5 10-5" />
                    </svg>
                </div>
                <span class="g-eyebrow">Commodities Exchange</span>
                <h1>Aether</h1>
                <p>Trade smarter. Manage risk. Stay ahead.</p>
            </div>

            <div class="g-double-bezel">
              <div class="g-double-bezel-inner">
                <div id="authAlert" class="g-alert g-alert-error" style="display:none; margin-bottom: var(--space-md);"></div>
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

                <!-- Login Form -->
                <form action="auth/login.php" method="POST" class="auth-form" id="loginForm">
                    <div class="field">
                        <label for="loginEmail">Email</label>
                        <input type="email" id="loginEmail" name="email" autocomplete="email" placeholder="client@aether.trade" required>
                    </div>
                    <div class="field">
                        <label for="loginPassword">Password</label>
                        <input type="password" id="loginPassword" name="password" autocomplete="current-password" placeholder="Enter your password" required>
                    </div>
                    <button type="submit" class="g-btn-pill g-btn-pill-primary">
                        Sign In
                        <span class="g-btn-icon-circle">
                            <svg aria-hidden="true" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                        </span>
                    </button>
                    <p class="auth-footer">Don't have an account? <a href="#" id="showRegisterForm" role="button" aria-controls="registerForm">Create one</a></p>
                </form>

                <!-- OTP Verification Form (Dynamically shown) -->
                <form action="auth/verify_otp.php" method="POST" class="auth-form hidden" id="otpForm">
                    <div id="otpTimer" class="otp-timer" style="text-align: center; font-size: 0.82rem; color: var(--accent); font-weight: 700; padding: 10px 20px; background: linear-gradient(135deg, rgba(212, 175, 55, 0.1) 0%, rgba(212, 175, 55, 0.03) 100%); border: 1px solid var(--border-strong); border-radius: var(--radius-md); margin-bottom: var(--space-md); text-transform: uppercase; letter-spacing: 0.05em;">Loading timer...</div>
                    
                    <p style="font-size:0.8rem; color:var(--text-secondary); margin-bottom: var(--space-md); text-align: center;" id="otpInstructions">
                        We sent a 6-digit verification code to your email.
                    </p>

                    <div class="field">
                        <label for="otpCode">Enter OTP Code</label>
                        <input type="text" name="otp" id="otpCode" class="otp-input" placeholder="• • • • • •" maxlength="6" required style="text-align: center; font-size: 1.5rem; letter-spacing: 8px; font-weight: 800; font-family: 'JetBrains Mono', monospace; color: var(--accent); background: var(--bg-secondary); border: 1.5px solid var(--border-strong); border-radius: var(--radius-md); padding: 10px 8px; width: 100%; box-sizing: border-box;">
                    </div>
                    
                    <button type="submit" class="g-btn-pill g-btn-pill-primary" style="margin-top: var(--space-md);">
                        Verify & Sign In
                        <span class="g-btn-icon-circle">
                            <svg aria-hidden="true" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                        </span>
                    </button>
                    <p class="auth-footer"><a href="#" id="cancelOtp" role="button">← Back to Login</a></p>
                </form>

                <!-- Register Form -->
                <form action="auth/register.php" method="POST" class="auth-form hidden" id="registerForm">
                    <div class="field">
                        <label for="registerName">Full Name</label>
                        <input type="text" id="registerName" name="username" autocomplete="name" placeholder="Alexander Mercer" required>
                    </div>
                    <div class="field">
                        <label for="registerEmail">Email</label>
                        <input type="email" id="registerEmail" name="email" autocomplete="email" placeholder="client@aether.trade" required>
                    </div>
                    <div class="field">
                        <label for="registerPassword">Password</label>
                        <input type="password" id="registerPassword" name="password" autocomplete="new-password" placeholder="Create a strong password" required>
                    </div>
                    <button type="submit" class="g-btn-pill g-btn-pill-primary">
                        Create Account
                        <span class="g-btn-icon-circle">
                            <svg aria-hidden="true" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                        </span>
                    </button>
                    <p class="auth-footer">Already have an account? <a href="#" id="showLoginForm" role="button" aria-controls="loginForm">Sign in</a></p>
                </form>
              </div>
            </div>

            <!-- Feature Strip -->
            <div class="auth-features">
                <div class="auth-feature">
                    <span class="feat-icon"><svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg></span>
                    OTP Secured
                </div>
                <div class="auth-feature">
                    <span class="feat-icon"><svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg></span>
                    Live Charts
                </div>
                <div class="auth-feature">
                    <span class="feat-icon"><svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg></span>
                    Instant Trades
                </div>
                <div class="auth-feature">
                    <span class="feat-icon"><svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg></span>
                    Risk Management
                </div>
            </div>
        </div>
    </main>

</div><!-- /login-split -->

<script>
document.addEventListener('DOMContentLoaded', () => {
    // ── Splash ──
    const splash = document.getElementById('splash-screen');
    if (splash) {
        if (!sessionStorage.getItem('splashSeen')) {
            sessionStorage.setItem('splashSeen', '1');
            setTimeout(() => { splash.classList.add('fade-out'); setTimeout(() => splash.remove(), 600); }, 600);
        } else { splash.remove(); }
    }

    // ── Form toggle ──
    document.getElementById('showRegisterForm')?.addEventListener('click', e => {
        e.preventDefault();
        document.getElementById('loginForm').classList.add('hidden');
        document.getElementById('registerForm').classList.remove('hidden');
        document.getElementById('otpForm').classList.add('hidden');
        hideAlert();
    });
    document.getElementById('showLoginForm')?.addEventListener('click', e => {
        e.preventDefault();
        document.getElementById('registerForm').classList.add('hidden');
        document.getElementById('loginForm').classList.remove('hidden');
        document.getElementById('otpForm').classList.add('hidden');
        hideAlert();
    });

    const loginForm = document.getElementById('loginForm');
    const otpForm = document.getElementById('otpForm');
    const authAlert = document.getElementById('authAlert');

    function showAlert(msg, type = 'error') {
        if (!authAlert) return;
        authAlert.style.display = 'block';
        authAlert.className = 'g-alert ' + (type === 'error' ? 'g-alert-error' : 'g-alert-success');
        authAlert.textContent = msg;
    }

    function hideAlert() {
        if (authAlert) authAlert.style.display = 'none';
    }

    loginForm?.addEventListener('submit', async e => {
        e.preventDefault();
        hideAlert();
        const btn = loginForm.querySelector('button[type="submit"]');
        const origText = btn.innerHTML;
        btn.disabled = true;
        btn.textContent = 'Authenticating...';

        const formData = new FormData(loginForm);
        try {
            const res = await fetch('auth/login.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            });
            const text = await res.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (_) {
                window.location.reload();
                return;
            }

            if (data && data.success) {
                // Hide login form
                loginForm.classList.add('hidden');
                // Show OTP form
                otpForm.classList.remove('hidden');
                
                // Mask email
                let masked = data.email;
                if (masked && masked.includes('@')) {
                    const [local, dom] = masked.split('@');
                    const maskedLocal = local.length <= 2 ? '*'.repeat(local.length) : local.slice(0, 2) + '*'.repeat(Math.max(local.length - 2, 1));
                    masked = maskedLocal + '@' + dom;
                }
                document.getElementById('otpInstructions').innerHTML = `We sent a 6-digit verification code to <strong style="color:var(--accent);">${masked}</strong>`;

                // Handle countdown
                let expiry = data.expiry;
                const timerEl = document.getElementById('otpTimer');
                
                if (data.debug_otp) {
                    console.log("[DEV MODE] Security Node OTP Code:", data.debug_otp);
                }

                function runTimer() {
                    const now = Math.floor(Date.now() / 1000);
                    let left = expiry - now;
                    if (left <= 0) {
                        timerEl.textContent = 'OTP Expired';
                        timerEl.style.color = 'var(--red)';
                        timerEl.style.borderColor = 'rgba(213,0,0,0.2)';
                        otpForm.querySelector('button[type="submit"]').disabled = true;
                        return;
                    }
                    let min = Math.floor(left / 60);
                    let sec = left % 60;
                    timerEl.textContent = `Expires in ${min}:${sec < 10 ? '0' : ''}${sec}`;
                    setTimeout(runTimer, 1000);
                }
                runTimer();
            } else {
                showAlert(data?.error || 'Invalid credentials');
                btn.disabled = false;
                btn.innerHTML = origText;
            }
        } catch (_) {
            showAlert('Authentication connection failed');
            btn.disabled = false;
            btn.innerHTML = origText;
        }
    });

    otpForm?.addEventListener('submit', async e => {
        e.preventDefault();
        hideAlert();
        const btn = otpForm.querySelector('button[type="submit"]');
        const origText = btn.innerHTML;
        btn.disabled = true;
        btn.textContent = 'Verifying...';

        const formData = new FormData(otpForm);
        try {
            const res = await fetch('auth/verify_otp.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            });
            const text = await res.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (_) {
                window.location.reload();
                return;
            }

            if (data && data.success) {
                window.location.href = data.redirect || 'dashboard.php';
            } else {
                showAlert(data?.error || 'Invalid OTP');
                btn.disabled = false;
                btn.innerHTML = origText;
            }
        } catch (_) {
            showAlert('Verification connection failed');
            btn.disabled = false;
            btn.innerHTML = origText;
        }
    });

    document.getElementById('cancelOtp')?.addEventListener('click', e => {
        e.preventDefault();
        hideAlert();
        otpForm.classList.add('hidden');
        loginForm.classList.remove('hidden');
        const btn = loginForm.querySelector('button[type="submit"]');
        btn.disabled = false;
        btn.innerHTML = `Sign In <span class="g-btn-icon-circle"><svg aria-hidden="true" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg></span>`;
    });

    // ── Live Prices ──
    function fmt(n, dec = 2) {
        return '$' + Number(n).toLocaleString('en-US', { minimumFractionDigits: dec, maximumFractionDigits: dec });
    }
    function setChange(el, pct) {
        el.textContent = (pct >= 0 ? '+' : '') + pct.toFixed(2) + '%';
        el.className = 'ticker-change ' + (pct >= 0 ? 'change-up' : 'change-down');
    }

    async function fetchPrices() {
        // Crypto — Binance public REST (no key needed)
        try {
            const r = await fetch('https://api.binance.com/api/v3/ticker/24hr?symbols=%5B%22BTCUSDT%22%2C%22ETHUSDT%22%5D');
            if (r.ok) {
                const data = await r.json();
                data.forEach(t => {
                    const key = t.symbol === 'BTCUSDT' ? 'btc' : 'eth';
                    const dec = key === 'btc' ? 0 : 2;
                    document.getElementById(key + '-price').textContent = fmt(t.lastPrice, dec);
                    setChange(document.getElementById(key + '-change'), parseFloat(t.priceChangePercent));
                });
            }
        } catch (_) {}

        // Gold & Silver — local endpoint
        for (const coin of ['XAU', 'XAG']) {
            try {
                const r = await fetch('get_market_price.php?coin=' + coin);
                if (r.ok) {
                    const d = await r.json();
                    if (d?.price) {
                        const key = coin.toLowerCase();
                        document.getElementById(key + '-price').textContent = fmt(d.price, 2);
                        document.getElementById(key + '-change').textContent = 'per troy oz';
                        document.getElementById(key + '-change').className = 'ticker-change change-neutral';
                    }
                }
            } catch (_) {}
        }

        const now = new Date();
        const el = document.getElementById('last-refresh-time');
        if (el) el.textContent = 'Updated ' + now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    }

    fetchPrices();
    setInterval(fetchPrices, 30000);
});
</script>
<script src="global.js?v=<?php echo filemtime('global.js'); ?>" defer></script>
<script src="script.js?v=<?php echo filemtime('script.js'); ?>" defer></script>
</body>
</html>